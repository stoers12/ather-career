#requires -Version 7.0
[CmdletBinding(DefaultParameterSetName = 'Release')]
param(
    [Parameter(Mandatory, ParameterSetName = 'Release')][string]$MergeRef,
    [Parameter(ParameterSetName = 'Release')][string]$PrHead,
    [ValidatePattern('^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$')][string]$Label = 'merge',
    [string]$ComposeRoot,
    [string]$ReleasesRoot,
    [ValidatePattern('^[a-z0-9][a-z0-9_-]+$')][string]$ProjectName = 'portfolio_course',
    [string]$ExtraComposeFile,
    [string]$HttpBase = 'http://127.0.0.1:8088',
    [string]$HttpsBase = 'https://localhost:8443',
    [Parameter(ParameterSetName = 'Release')][switch]$Migrate,
    [switch]$DryRun,
    [Parameter(Mandatory, ParameterSetName = 'Rollback')][string]$Rollback,
    [Parameter(DontShow, ParameterSetName = 'Release')][string]$TestBuildTarget
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$script:Repository = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$script:Masks = [Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
$script:Steps = [Collections.Generic.List[object]]::new()
$script:Smoke = [Collections.Generic.List[object]]::new()
$script:Started = [DateTime]::UtcNow
$script:Step = 'preflight'
$script:StepClock = [Diagnostics.Stopwatch]::StartNew()
$script:ReleaseDirectory = $null
$script:ChangedWeb = $false
$script:ChangedProxy = $false
$script:Previous = $null
$script:FullSha = ''
$script:Tree = ''
$script:PrTree = ''
$script:Image = ''
$script:ImageLabel = ''
$script:BackupHash = ''
$script:BackupCounts = ''
$script:InitialCounts = $null
$script:CurrentFiles = @()
$script:BackupFile = ''
$script:RollbackCommand = ''
$script:Outcome = 'FAILED (no service changed)'

function Protect-Text([string]$Text) {
    foreach ($value in @($script:Masks | Sort-Object Length -Descending)) {
        if ($value.Length -gt 0) { $Text = $Text.Replace($value, '[REDACTED]') }
    }
    # CI's exact-value and field redaction; bodies, config JSON and dumps are
    # never sent to the console. Native diagnostics are withheld altogether.
    $Text = $Text -replace "Duplicate entry '[^']*'", "Duplicate entry '[REDACTED]'"
    $Text = $Text -replace '(?i)(subject|state|nonce|cookie|password|secret)[\s_]*[=:]\s*[^\s,;]+', '$1=[REDACTED]'
    $Text = $Text -replace '(?i)((?:token|csrf|session|authorization)[\w-]*\s*[=:]\s*)[^\s,;]+', '$1[REDACTED]'
    return $Text
}

function Start-Step([string]$Name) {
    $script:Step = $Name
    $script:StepClock.Restart()
}

function Complete-Step([string]$Detail, [bool]$Passed = $true) {
    $status = if ($Passed) { 'OK' } else { 'FAIL' }
    $safe = Protect-Text $Detail
    Write-Host "STEP $script:Step $status $safe"
    $script:Steps.Add([pscustomobject]@{
        Name = $script:Step; Status = $status
        Seconds = [Math]::Round($script:StepClock.Elapsed.TotalSeconds, 3); Detail = $safe
    })
}

function Assert-Release([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw $Message }
}

function Invoke-ReleaseNative {
    param(
        [string]$Program, [string[]]$Arguments,
        [string]$InputFile, [string]$OutputFile, [string]$InputText,
        [hashtable]$ChildEnvironment = @{}, [switch]$AllowFailure,
        [int]$TimeoutSeconds = 900
    )
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $Program
    $start.WorkingDirectory = $script:Repository
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.RedirectStandardInput = $true
    foreach ($argument in $Arguments) { $start.ArgumentList.Add($argument) }
    foreach ($key in $ChildEnvironment.Keys) { $start.Environment[$key] = [string]$ChildEnvironment[$key] }
    $process = [Diagnostics.Process]::new()
    $process.StartInfo = $start
    $fileStream = $null
    try {
        [void]$process.Start()
        $errorTask = $process.StandardError.ReadToEndAsync()
        if ($OutputFile) {
            $fileStream = [IO.File]::Open($OutputFile, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write)
            $outputTask = $process.StandardOutput.BaseStream.CopyToAsync($fileStream)
        } else { $outputTask = $process.StandardOutput.ReadToEndAsync() }
        if ($InputFile) {
            $inputStream = [IO.File]::OpenRead($InputFile)
            try { $inputStream.CopyTo($process.StandardInput.BaseStream) } finally { $inputStream.Dispose() }
        } elseif ($InputText) { $process.StandardInput.Write($InputText) }
        $process.StandardInput.Close()
        if (-not $process.WaitForExit($TimeoutSeconds * 1000)) {
            $process.Kill($true)
            $process.WaitForExit()
            throw "$Program exceeded its time limit"
        }
        $outputTask.GetAwaiter().GetResult() | Out-Null
        $errorTask.GetAwaiter().GetResult() | Out-Null
        $output = if ($OutputFile) { '' } else { $outputTask.Result }
        if ($process.ExitCode -ne 0 -and -not $AllowFailure) {
            throw "$Program command failed (exit $($process.ExitCode)); native diagnostics withheld"
        }
        return [pscustomobject]@{ Code = $process.ExitCode; Output = $output; ErrorOutput = $errorTask.Result }
    } finally {
        if ($null -ne $fileStream) { $fileStream.Dispose() }
        $process.Dispose()
    }
}

function Git-Text([string[]]$Arguments) {
    return (Invoke-ReleaseNative git (@('-C', $script:Repository) + $Arguments)).Output.Trim()
}

function Resolve-Commit([string]$Reference) {
    $resolved = Git-Text @('rev-parse', '--verify', '--end-of-options', ($Reference + '^{commit}'))
    Assert-Release ($resolved -cmatch '^[a-f0-9]{40}$') 'Git did not resolve a full commit SHA'
    return $resolved
}

function Normalize-Bind([string]$Path) {
    $normalized = $Path.Replace('\', '/').TrimEnd('/')
    if ($normalized -match '^/run/desktop/mnt/host/([a-z])/(.*)$') {
        $normalized = $Matches[1] + ':/' + $Matches[2]
    }
    return $normalized
}

function Compose-Arguments([string[]]$Files) {
    $arguments = @('compose', '-p', $ProjectName, '--project-directory', $ComposeRoot,
        '--env-file', (Join-Path $ComposeRoot '.env'), '--env-file', (Join-Path $ComposeRoot '.env.owner.local'))
    foreach ($file in $Files) { $arguments += @('-f', $file) }
    return $arguments
}

function Invoke-Compose([string[]]$Files, [string[]]$Command, [string]$OverlayText) {
    return Invoke-ReleaseNative docker ((Compose-Arguments $Files) + $Command) -InputText $OverlayText
}

function Get-Service([string]$Service, [switch]$RequireHealthy) {
    $result = Invoke-ReleaseNative docker @('ps', '-a', '--filter', "label=com.docker.compose.project=$ProjectName",
        '--filter', "label=com.docker.compose.service=$Service", '--filter', 'label=com.docker.compose.oneoff=False', '--format', '{{.ID}}')
    $ids = @($result.Output.Trim() -split '\r?\n' | Where-Object { $_ })
    Assert-Release ($ids.Count -eq 1) "Exactly one $Service container is required"
    $container = (Invoke-ReleaseNative docker @('inspect', '--format', '{{json .}}', $ids[0])).Output | ConvertFrom-Json -AsHashtable
    foreach ($entry in $container.Config.Env) {
        $parts = $entry -split '=', 2
        if ($parts.Count -eq 2 -and $parts[0] -match '(?i)^(MYSQL_|DB_PASSWORD|OIDC_|EXPECTED_OIDC_|PRESERVED_|EVIDENCE_HUB_OPAQUE_)') {
            [void]$script:Masks.Add($parts[1])
        }
    }
    if ($RequireHealthy) {
        Assert-Release ($container.State.Running -and $container.State.ContainsKey('Health') -and
            $container.State.Health.Status -eq 'healthy') "$Service is not running and healthy"
    }
    return $container
}

function Get-Previous {
    $web = Get-Service web -RequireHealthy
    $null = Get-Service db -RequireHealthy
    $proxy = Get-Service owner_https -RequireHealthy
    $files = @($web.Config.Labels['com.docker.compose.project.config_files'] -split ',')
    Assert-Release ($files.Count -ge 3) 'Current Compose file labels are incomplete'
    foreach ($file in $files) { Assert-Release ([IO.File]::Exists($file)) 'A previous Compose input is unreadable' }
    # ExtraComposeFile follows canonical-release.yml in isolated projects.
    $canonical = @($files | Where-Object { [IO.Path]::GetFileName($_) -eq 'canonical-release.yml' })
    Assert-Release ($canonical.Count -eq 1) 'Exactly one current canonical overlay is required'
    $imageId = Get-ImageId $web.Config.Image
    Assert-Release ($web.Config.Labels['com.docker.compose.image'] -ceq $imageId) 'Running image no longer matches its tag'
    return @{
        Image = $web.Config.Image; ImageId = $imageId; ContainerImageId = $web.Image; ConfigFiles = $files
        Canonical = $canonical[0]
        ProxyMountSources = @($proxy.Mounts | ForEach-Object {
            @{ Type = $_.Type; Source = $_.Source; Destination = $_.Destination; RW = $_.RW }
        })
        WebMounts = $web.Mounts; Time = [DateTime]::UtcNow.ToString('o')
    }
}

function Get-ImageId([string]$Reference) {
    # With Docker's containerd store, the tag can name an OCI index while
    # container.Image is a config digest. Compose records the platform image
    # manifest ID. Compare that identity, not these different digest types.
    $platform = (Invoke-ReleaseNative docker @('image', 'inspect', '--format', '{{.Os}}/{{.Architecture}}', $Reference)).Output.Trim()
    return (Invoke-ReleaseNative docker @('image', 'inspect', '--platform', $platform, '--format', '{{.Id}}', $Reference)).Output.Trim()
}

function Get-ImageRevision([string]$Reference, [switch]$Optional) {
    $result = Invoke-ReleaseNative docker @('image', 'inspect', '--format', '{{json .Config.Labels}}', $Reference) -AllowFailure
    if ($result.Code -ne 0) {
        if ($Optional) { return $null }
        throw 'Image metadata is unavailable'
    }
    $labels = $result.Output | ConvertFrom-Json -AsHashtable
    Assert-Release ($null -ne $labels -and $labels.ContainsKey('org.opencontainers.image.revision')) 'Image revision label is missing'
    return [string]$labels['org.opencontainers.image.revision']
}

function New-Overlay([string]$Source) {
    $template = [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'templates/local-owner-release.yml'))
    $values = @{
        IMAGE = $script:Image; FULL_SHA = $script:FullSha
        SOURCE = (Normalize-Bind $Source); RATE_LIMIT_DIR = (Normalize-Bind (Join-Path $ComposeRoot 'runtime/rate-limit'))
    }
    foreach ($key in $values.Keys) {
        $template = $template.Replace('{{' + $key + '}}', $values[$key].Replace("'", "''"))
    }
    Assert-Release ($template -notmatch '\{\{|\}\}') 'An overlay placeholder was not resolved'
    return $template
}

function Assert-Config([string[]]$Files, [string]$OverlayText, [string]$Source, [switch]$RequireReleaseLabel) {
    $json = (Invoke-Compose $Files @('config', '--format', 'json') $OverlayText).Output
    $config = $json | ConvertFrom-Json -AsHashtable
    $web = $config.services.web
    Assert-Release ($config.name -eq $ProjectName) 'Rendered project name differs'
    Assert-Release ($web.image -ceq $script:Image) 'Rendered web image differs'
    if ($RequireReleaseLabel) {
        Assert-Release ((Normalize-Bind $web.labels['org.ather-career.release-source']) -eq (Normalize-Bind $Source)) 'Rendered release label differs'
    }
    Assert-Release (($web.entrypoint -join '|') -ceq '/usr/local/bin/portfolio-maintenance-entrypoint') 'Rendered entrypoint differs'
    Assert-Release (($web.command -join '|') -ceq 'apache2-foreground') 'Rendered command differs'
    Assert-Release ($web.environment.APP_VERSION -ceq $script:FullSha) 'Rendered APP_VERSION differs'
    Assert-Release ($web.build.target -ceq 'production' -and $web.build.args.APP_VERSION -ceq $script:FullSha -and
        $web.build.dockerfile -ceq 'Dockerfile' -and
        (Normalize-Bind $web.build.context) -eq (Normalize-Bind $Source)) 'Rendered build contract differs'
    foreach ($flag in @('ATHERCAR_REGISTRATION_ENABLED', 'ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED',
        'ATHERCAR_ONBOARDING_ATTESTATION_HTTP_ENABLED', 'ATHERCAR_ONBOARDING_COMPLETION_HTTP_ENABLED')) {
        Assert-Release ($web.environment[$flag] -ceq '0') 'A rendered capability is enabled'
    }
    Assert-Release ($web.environment.RATE_LIMIT_STATE_DIR -ceq '/var/www/app/runtime/rate-limit') 'Rendered state path differs'
    $mounts = @($web.volumes)
    Assert-Release ($mounts.Count -eq 2) 'Web must have exactly two mounts'
    $storage = @($mounts | Where-Object { $_.type -eq 'volume' -and $_.source -eq 'portfolio_private_storage' -and $_.target -eq '/var/www/private-storage' })
    $rate = @($mounts | Where-Object { $_.type -eq 'bind' -and $_.target -eq '/var/www/app/runtime/rate-limit' })
    Assert-Release ($storage.Count -eq 1 -and $rate.Count -eq 1 -and
        (Normalize-Bind $rate[0].source) -eq (Normalize-Bind (Join-Path $ComposeRoot 'runtime/rate-limit'))) 'Rendered web mounts differ'
    foreach ($mount in $mounts) {
        Assert-Release (-not $mount.ContainsKey('read_only') -or -not $mount.read_only) 'Web state mount became read-only'
    }
    $proxy = @($config.services.owner_https.volumes)
    Assert-Release ($proxy.Count -eq 3) 'Proxy must have exactly three mounts'
    $ca = @($proxy | Where-Object { $_.type -eq 'volume' -and $_.source -eq 'caddy_data' -and $_.target -eq '/data' })
    Assert-Release ($ca.Count -eq 1) 'Rendered Caddy data mount differs'
    Assert-Release (-not $ca[0].ContainsKey('read_only') -or -not $ca[0].read_only) 'Caddy state mount became read-only'
    foreach ($pair in @(@('Caddyfile.owner-local', '/etc/caddy/Caddyfile'), @('validate-oidc-issuer.sh', '/etc/caddy/validate-oidc-issuer.sh'))) {
        $found = @($proxy | Where-Object { $_.type -eq 'bind' -and $_.target -eq $pair[1] })
        Assert-Release ($found.Count -eq 1 -and $found[0].read_only -eq $true -and
            (Normalize-Bind $found[0].source) -eq (Normalize-Bind (Join-Path $Source ('docker/' + $pair[0])))) 'Rendered Caddy config mount differs'
    }
    $currentStorage = @($script:Previous.WebMounts | Where-Object { $_.Destination -eq '/var/www/private-storage' })
    $currentRate = @($script:Previous.WebMounts | Where-Object { $_.Destination -eq '/var/www/app/runtime/rate-limit' })
    $currentCa = @($script:Previous.ProxyMountSources | Where-Object { $_.Destination -eq '/data' })
    Assert-Release ($currentStorage.Count -eq 1 -and $currentStorage[0].Name -eq $config.volumes.portfolio_private_storage.name) 'Private volume identity differs'
    Assert-Release ($currentRate.Count -eq 1 -and
        (Normalize-Bind $currentRate[0].Source) -eq (Normalize-Bind $rate[0].source)) 'Rate-limit bind identity differs'
    Assert-Release ($currentCa.Count -eq 1 -and $currentCa[0].Source -match ('/' + [regex]::Escape($config.volumes.caddy_data.name) + '/_data$')) 'Caddy volume identity differs'
}

function Get-DatabaseCounts {
    $db = Get-Service db -RequireHealthy
    $query = 'SELECT (SELECT COUNT(*) FROM schema_migrations),(SELECT COUNT(*) FROM portfolios),(SELECT COUNT(*) FROM projects),(SELECT COUNT(*) FROM users)'
    $shell = 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --batch --skip-column-names "$MYSQL_DATABASE" -e "$1"'
    $line = (Invoke-ReleaseNative docker @('exec', $db.Id, 'sh', '-ec', $shell, 'counts', $query)).Output.Trim()
    Assert-Release ($line -match '^\d+\s+\d+\s+\d+\s+\d+$') 'Database counts could not be verified'
    return @($line -split '\s+' | ForEach-Object { [long]$_ })
}

function Backup-AndVerify {
    $db = Get-Service db -RequireHealthy
    $script:InitialCounts = Get-DatabaseCounts
    $backupDirectory = Join-Path $script:ReleaseDirectory 'backup'
    [void][IO.Directory]::CreateDirectory($backupDirectory)
    $script:BackupFile = Join-Path $backupDirectory 'pre-release.sql'
    $dump = 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"'
    $null = Invoke-ReleaseNative docker @('exec', $db.Id, 'sh', '-ec', $dump) -OutputFile $script:BackupFile
    Assert-Release ((Get-Item -LiteralPath $script:BackupFile).Length -gt 0) 'Database backup is empty'
    $script:BackupHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $script:BackupFile).Hash.ToLowerInvariant()
    [IO.File]::WriteAllText(($script:BackupFile + '.sha256'), $script:BackupHash + "`n")
    $restore = 'eng05-restore-' + [guid]::NewGuid().ToString('N')
    $password = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    [void]$script:Masks.Add($password)
    try {
        # No published port, host mount or persistent volume on the verifier.
        $null = Invoke-ReleaseNative docker @('run', '--detach', '--name', $restore, '--network', 'none',
            '--tmpfs', '/var/lib/mysql:rw', '--env', 'MYSQL_ROOT_PASSWORD', 'mysql:8.4') -ChildEnvironment @{ MYSQL_ROOT_PASSWORD = $password }
        $ready = $false
        for ($attempt = 0; $attempt -lt 90; $attempt++) {
            $probe = Invoke-ReleaseNative docker @('exec', $restore, 'sh', '-ec',
                'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -h127.0.0.1 -uroot --batch --skip-column-names -e "SELECT 1"') -AllowFailure
            if ($probe.Code -eq 0) { $ready = $true; break }
            Start-Sleep -Seconds 1
        }
        Assert-Release $ready 'Disposable restore server did not start'
        $null = Invoke-ReleaseNative docker @('exec', $restore, 'sh', '-ec',
            'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -e "CREATE DATABASE release_verify"')
        $null = Invoke-ReleaseNative docker @('exec', '-i', $restore, 'sh', '-ec',
            'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot release_verify') -InputFile $script:BackupFile
        $query = 'SELECT (SELECT COUNT(*) FROM schema_migrations),(SELECT COUNT(*) FROM portfolios),(SELECT COUNT(*) FROM projects),(SELECT COUNT(*) FROM users)'
        $restored = (Invoke-ReleaseNative docker @('exec', $restore, 'sh', '-ec',
            'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --batch --skip-column-names release_verify -e "$1"', 'counts', $query)).Output.Trim()
        $after = Get-DatabaseCounts
        $expected = $script:InitialCounts -join "`t"
        Assert-Release ($restored -ceq $expected -and ($after -join "`t") -ceq $expected) 'Restore counts differ or source counts changed during backup'
        $script:BackupCounts = 'schema_migrations, portfolios, projects, users: ' + ($script:InitialCounts -join ', ')
    } finally {
        $null = Invoke-ReleaseNative docker @('rm', '--force', $restore)
    }
}

function Get-Http([string]$Base, [string]$Path) {
    $handler = [Net.Http.HttpClientHandler]::new()
    $handler.AllowAutoRedirect = $false
    $handler.UseCookies = $false
    $handler.ServerCertificateCustomValidationCallback = [Net.Http.HttpClientHandler]::DangerousAcceptAnyServerCertificateValidator
    $client = [Net.Http.HttpClient]::new($handler)
    $client.Timeout = [TimeSpan]::FromSeconds(15)
    try {
        $response = $client.GetAsync($Base.TrimEnd('/') + $Path).GetAwaiter().GetResult()
        $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        $location = if ($null -eq $response.Headers.Location) { '' } else { $response.Headers.Location.ToString() }
        return @{ Status = [int]$response.StatusCode; Body = $body; Location = $location }
    } finally { $client.Dispose(); $handler.Dispose() }
}

function Read-WebLog([string]$Id) {
    $result = Invoke-ReleaseNative docker @('logs', $Id)
    return $result.Output + $result.ErrorOutput
}

function Smoke-Release([string]$ExpectedSha, [long]$MigrationCount, [string]$Stage = 'candidate') {
    $web = Get-Service web -RequireHealthy
    $null = Get-Service owner_https -RequireHealthy
    $version = @($web.Config.Env | Where-Object { $_.StartsWith('APP_VERSION=') })
    Assert-Release ($version.Count -eq 1 -and $version[0] -ceq ('APP_VERSION=' + $ExpectedSha)) 'Running APP_VERSION differs'
    Assert-Release ($web.Config.Labels['org.opencontainers.image.revision'] -ceq $ExpectedSha -and
        (Get-ImageRevision $web.Config.Image) -ceq $ExpectedSha -and
        $web.Config.Labels['com.docker.compose.image'] -ceq (Get-ImageId $web.Config.Image)) 'Running image revision or identity differs'
    $log = Read-WebLog $web.Id
    Assert-Release ($log.Contains('Production security gate passed.')) 'Production security gate line is missing'
    Assert-Release ($log -notmatch 'PHP (Fatal|Warning|Parse)|Uncaught') 'Web log contains PHP errors'
    $db = Get-Service db -RequireHealthy
    $slug = (Invoke-ReleaseNative docker @('exec', $db.Id, 'sh', '-ec',
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --batch --skip-column-names "$MYSQL_DATABASE" -e "SELECT public_slug FROM portfolios WHERE is_published=1 AND public_slug IS NOT NULL ORDER BY id LIMIT 1"')).Output.Trim()
    Assert-Release ($slug -cmatch '^[a-z0-9][a-z0-9-]{1,62}[a-z0-9]$') 'A published portfolio is required for smoke verification'
    foreach ($base in @($HttpBase, $HttpsBase)) {
        $assets = [Collections.Generic.HashSet[string]]::new()
        foreach ($path in @('/health.php', '/ready.php', '/', '/signin.php', "/p/$slug", "/p/$slug/projects.json", "/p/$slug/media/profile",
            '/owner/evidence-hub', '/owner.php', '/composer.json', '/vendor/autoload.php', '/includes/presentation.php', '/config/database.php', '/.env', '/Dockerfile')) {
            $response = Get-Http $base $path
            $script:Smoke.Add(@{ Stage = $Stage; Origin = $base; Path = $path; Status = $response.Status })
            if ($path -in @('/owner/evidence-hub', '/owner.php')) {
                Assert-Release ($response.Status -eq 303 -and ($response.Location -split '\?')[0] -ceq '/owner_login.php') 'Owner redirect smoke failed'
            } elseif ($path -in @('/composer.json', '/vendor/autoload.php', '/includes/presentation.php', '/config/database.php', '/.env', '/Dockerfile')) {
                Assert-Release ($response.Status -in 403, 404) 'Internal-path smoke failed'
            } else {
                Assert-Release ($response.Status -eq 200) 'Public-page smoke failed'
                if ($path -eq '/health.php') { Assert-Release ($response.Body -ceq "OK`n") 'Liveness body differs' }
                if ($path -eq '/ready.php') { Assert-Release ($response.Body -ceq "READY`n") 'Readiness body differs' }
                if ($path -in @('/', '/signin.php', "/p/$slug")) {
                    foreach ($match in [regex]::Matches($response.Body, '(?:src|href)\s*=\s*["''](/assets/[^"''<>\s]+)["'']')) {
                        $asset = [Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
                        Assert-Release ($asset -cmatch '^/assets/(css|js|fonts|images)/[A-Za-z0-9_./-]+(?:\?v=[a-f0-9]{16})?$' -and $asset -notmatch '\.\.') 'Unexpected asset URL in smoke page'
                        [void]$assets.Add($asset)
                    }
                }
            }
        }
        Assert-Release ($assets.Count -gt 0) 'No public assets were discovered'
        foreach ($asset in $assets) {
            $response = Get-Http $base $asset
            $script:Smoke.Add(@{ Stage = $Stage; Origin = $base; Path = $asset; Status = $response.Status })
            Assert-Release ($response.Status -eq 200) 'Page asset smoke failed'
        }
    }
    $counts = Get-DatabaseCounts
    if ($MigrationCount -ge 0) { Assert-Release ($counts[0] -eq $MigrationCount) 'Migration ledger changed unexpectedly' }
    $log = Read-WebLog $web.Id
    Assert-Release ($log -notmatch 'PHP (Fatal|Warning|Parse)|Uncaught') 'Web log contains PHP errors after smoke'
}

function Quote-PowerShell([string]$Value) { return "'" + $Value.Replace("'", "''") + "'" }

function Format-RollbackCommand([string[]]$Files, [string]$ImagePin, [string]$OriginalImage) {
    $restore = 'docker image tag ' + (Quote-PowerShell $ImagePin) + ' ' + (Quote-PowerShell $OriginalImage) + [Environment]::NewLine +
        'if ($LASTEXITCODE -ne 0) { throw ''Rollback image restore failed'' }' + [Environment]::NewLine
    return $restore + 'docker ' + (((Compose-Arguments $Files) + @('up', '-d', '--no-deps', '--no-build', 'web', 'owner_https') |
        ForEach-Object { Quote-PowerShell $_ }) -join ' ')
}

function Write-Record {
    if (-not $script:ReleaseDirectory) { return }
    $lines = [Collections.Generic.List[string]]::new()
    foreach ($line in @(('# Local release: ' + $script:Outcome), '',
        "- Source SHA: $script:FullSha", "- Source tree: $script:Tree",
        "- PR head: $(if ($PrHead) { $PrHead } else { '(not supplied)' })",
        "- PR tree: $(if ($script:PrTree) { $script:PrTree + ' (equal)' } else { '(not supplied)' })",
        "- Image: $script:Image", "- Image revision label: $script:ImageLabel",
        "- Backup: $(if ($script:BackupFile) { 'backup/pre-release.sql' } else { '(not taken in this mode)' })",
        "- Backup SHA-256: $script:BackupHash", "- Restore verification: $script:BackupCounts",
        '- Overlay: production target, maintenance entrypoint, default-off capabilities; exactly two web and three proxy mounts.',
        '- Credentials, SQL contents, HTTP bodies and native diagnostics are excluded from this record.',
        "- Duration seconds: $([Math]::Round(([DateTime]::UtcNow - $script:Started).TotalSeconds, 3))", '',
        '| Step | Result | Seconds | Detail |', '| --- | --- | --- | --- |')) { $lines.Add($line) }
    foreach ($step in $script:Steps) { $lines.Add("| $($step.Name) | $($step.Status) | $($step.Seconds) | $($step.Detail) |") }
    $lines.Add(''); $lines.Add('| Stage | Origin | Path | Status |'); $lines.Add('| --- | --- | --- | --- |')
    foreach ($row in $script:Smoke) { $lines.Add("| $($row.Stage) | $($row.Origin) | $($row.Path) | $($row.Status) |") }
    $lines.Add(''); $lines.Add('Exact command to restore the preceding release (run from the Compose root):')
    $lines.Add('~~~powershell'); $lines.Add($script:RollbackCommand); $lines.Add('~~~')
    $lines.Add(''); $lines.Add($(if ($script:Outcome -eq 'PASSED') { 'RELEASE_LOCAL_PASSED' } else { 'RELEASE_LOCAL_FAILED' }))
    [IO.File]::WriteAllText((Join-Path $script:ReleaseDirectory 'RELEASE.md'), (Protect-Text ($lines -join "`n")) + "`n")
}

try {
    if (-not $ComposeRoot) {
        # Common Git directory locates the original checkout from a worktree.
        $common = Git-Text @('rev-parse', '--path-format=absolute', '--git-common-dir')
        $ComposeRoot = Split-Path -Parent $common
    }
    $ComposeRoot = [IO.Path]::GetFullPath($ComposeRoot)
    if (-not $ReleasesRoot) { $ReleasesRoot = Join-Path $ComposeRoot '../ather-career-releases' }
    $ReleasesRoot = [IO.Path]::GetFullPath($ReleasesRoot)
    foreach ($name in @('.env', '.env.owner.local', 'docker-compose.yml', 'docker-compose.owner-https.yml')) {
        Assert-Release ([IO.File]::Exists((Join-Path $ComposeRoot $name))) 'Required Compose input is missing'
    }
    foreach ($name in @('.env', '.env.owner.local')) {
        foreach ($line in [IO.File]::ReadAllLines((Join-Path $ComposeRoot $name))) {
            if ($line -match '^\s*(?:export\s+)?(?:MYSQL_[A-Z_]+|DB_PASSWORD|OIDC_[A-Z_]+|EXPECTED_OIDC_ISSUER|PRESERVED_V1_OIDC_SUBJECT|EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY)\s*=\s*(.*)$') {
                [void]$script:Masks.Add($Matches[1].Trim().Trim('"', "'"))
            }
        }
    }
    foreach ($base in @($HttpBase, $HttpsBase)) {
        $uri = [uri]$base
        Assert-Release ($uri.IsAbsoluteUri -and $uri.Scheme -in @('http', 'https') -and $uri.IsLoopback -and
            $uri.UserInfo -eq '' -and $uri.Query -eq '' -and $uri.Fragment -eq '' -and $uri.AbsolutePath -eq '/') 'Smoke origins must be loopback origins'
    }
    Assert-Release (([uri]$HttpBase).Scheme -ceq 'http' -and ([uri]$HttpsBase).Scheme -ceq 'https') 'Both HTTP and HTTPS smoke origins are required'
    if ($TestBuildTarget) { Assert-Release ($ProjectName -match '^eng05-test(?:-|$)') 'Build injection is restricted to disposable test projects' }
    $baseFiles = @((Join-Path $ComposeRoot 'docker-compose.yml'), (Join-Path $ComposeRoot 'docker-compose.owner-https.yml'))
    if ($ExtraComposeFile) {
        $ExtraComposeFile = [IO.Path]::GetFullPath($ExtraComposeFile)
        Assert-Release ([IO.File]::Exists($ExtraComposeFile)) 'Extra Compose input is missing'
    }
    if ($PSCmdlet.ParameterSetName -eq 'Release') {
        # DryRun is strictly read-only, including Git metadata. The actual run
        # fetches before accepting ancestry; a dry plan uses cached origin/main.
        if (-not $DryRun) { $null = Git-Text @('fetch', 'origin') }
        $script:FullSha = Resolve-Commit $MergeRef
        $ancestry = Invoke-ReleaseNative git @('-C', $script:Repository, 'merge-base', '--is-ancestor', $script:FullSha, 'origin/main') -AllowFailure
        Assert-Release ($ancestry.Code -eq 0) 'MergeRef is not on origin/main'
        $script:Tree = Git-Text @('rev-parse', ($script:FullSha + '^{tree}'))
        if ($PrHead) {
            $head = Resolve-Commit $PrHead
            $script:PrTree = Git-Text @('rev-parse', ($head + '^{tree}'))
            Assert-Release ($script:Tree -ceq $script:PrTree) 'Reviewed PR tree differs from release tree'
            $PrHead = $head
        }
        $script:Image = 'ather-career-local:' + $script:FullSha.Substring(0, 12)
        $existing = Get-ImageRevision $script:Image -Optional
        Assert-Release ($null -eq $existing -or $existing -ceq $script:FullSha) 'Existing image tag has a different revision'
    }
    $null = Invoke-ReleaseNative docker @('info', '--format', '{{.ServerVersion}}')
    $script:Previous = Get-Previous
    if ($Rollback) {
        $Rollback = [IO.Path]::GetFullPath($Rollback)
        $canonical = Join-Path $Rollback 'canonical-release.yml'
        Assert-Release ([IO.File]::Exists($canonical)) 'Rollback overlay is unreadable'
        $script:CurrentFiles = $baseFiles + @($canonical)
        if ($ExtraComposeFile) { $script:CurrentFiles += $ExtraComposeFile }
        $config = (Invoke-Compose $script:CurrentFiles @('config', '--format', 'json')).Output | ConvertFrom-Json -AsHashtable
        $script:Image = $config.services.web.image
        $script:FullSha = Resolve-Commit (Get-ImageRevision $script:Image)
        $script:ImageLabel = $script:FullSha
        $script:Tree = Git-Text @('rev-parse', ($script:FullSha + '^{tree}'))
        Assert-Config $script:CurrentFiles '' (Join-Path $Rollback 'source')
        $Label = 'rollback'
    }
    $directory = Join-Path $ReleasesRoot ([DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ') + '-' + $Label + '-' + $script:FullSha.Substring(0, 12))
    Assert-Release (-not (Test-Path -LiteralPath $directory)) 'Release folder already exists'
    $source = Join-Path $directory 'source'
    # A same-commit rebuild can move its existing tag (for example after a
    # base-image update). Retain an alias before build so rollback can restore
    # the captured image instead of silently selecting the rebuilt image.
    $script:Previous.ImagePin = 'ather-career-local:rollback-' + [IO.Path]::GetFileName($directory)
    $script:RollbackCommand = Format-RollbackCommand $script:Previous.ConfigFiles $script:Previous.ImagePin $script:Previous.Image
    Complete-Step 'Git identity, inputs, image tag and healthy project verified'
    if (-not $Rollback) {
        $overlay = New-Overlay $source
        if ($DryRun) {
            $dryFiles = $baseFiles + @('-')
            if ($ExtraComposeFile) { $dryFiles += $ExtraComposeFile }
            Assert-Config $dryFiles $overlay $source -RequireReleaseLabel
        }
    }
    if ($DryRun) {
        Start-Step 'render'
        Complete-Step 'Read-only config assertion passed; no fetch or filesystem/runtime writes'
        foreach ($step in @('rollback-descriptor', 'preserve-image', 'archive', 'backup', 'restore-verify', 'build', 'config-assert', 'optional-migrate', 'up-web', 'up-owner_https', 'smoke', 'record')) {
            Start-Step $step; Complete-Step 'DRY RUN: would execute; actual release fetches and revalidates origin/main'
        }
        if ($Rollback) { Write-Host ([IO.File]::ReadAllText((Join-Path $Rollback 'canonical-release.yml'))) } else { Write-Host $overlay }
        Write-Host 'RELEASE_LOCAL_PASSED'
        exit 0
    }
    Start-Step 'rollback-descriptor'
    # First artifact, before archive, backup, build or service operations.
    [void][IO.Directory]::CreateDirectory($directory)
    $script:ReleaseDirectory = $directory
    [IO.File]::WriteAllText((Join-Path $directory 'rollback.json'), ($script:Previous | ConvertTo-Json -Depth 12))
    Complete-Step 'Rollback descriptor written as first artifact'
    Start-Step 'preserve-image'
    $null = Invoke-ReleaseNative docker @('tag', $script:Previous.Image, $script:Previous.ImagePin)
    Assert-Release ((Get-ImageId $script:Previous.ImagePin) -ceq $script:Previous.ImageId) 'Previous image could not be retained'
    Complete-Step 'Captured rollback image retained under a release-specific tag'
    if (-not $Rollback) {
        Start-Step 'archive'
        $archive = Join-Path $directory 'source.tar'
        $null = Invoke-ReleaseNative git @('-C', $script:Repository, '-c', 'core.autocrlf=false', 'archive', '--format=tar', $script:FullSha) -OutputFile $archive
        [void][IO.Directory]::CreateDirectory($source)
        $null = Invoke-ReleaseNative tar @('-xf', $archive, '-C', $source)
        Remove-Item -LiteralPath $archive
        Complete-Step 'Clean committed source archived with LF content'
        Start-Step 'backup'
        Backup-AndVerify
        Complete-Step ('Backup hash and disposable restore counts verified: ' + $script:BackupCounts)
        Start-Step 'build'
        $target = if ($TestBuildTarget) { $TestBuildTarget } else { 'production' }
        $null = Invoke-ReleaseNative docker @('build', '-f', (Join-Path $source 'Dockerfile'), '--target', $target,
            '--build-arg', ('APP_VERSION=' + $script:FullSha), '-t', $script:Image, $source)
        $script:ImageLabel = Get-ImageRevision $script:Image
        Assert-Release ($script:ImageLabel -ceq $script:FullSha) 'Built image revision differs'
        Complete-Step 'Repo production image built; full revision label verified'
        Start-Step 'config-assert'
        $canonical = Join-Path $directory 'canonical-release.yml'
        [IO.File]::WriteAllText($canonical, $overlay)
        $script:CurrentFiles = $baseFiles + @($canonical)
        if ($ExtraComposeFile) { $script:CurrentFiles += $ExtraComposeFile }
        Assert-Config $script:CurrentFiles '' $source -RequireReleaseLabel
        Complete-Step 'Rendered image, version, startup and exact mount lists verified'
        if ($Migrate) {
            Start-Step 'migrate'
            $null = Invoke-Compose $script:CurrentFiles @('run', '--rm', '--no-deps', '--entrypoint', 'php', 'web', 'database/migrate.php')
            Complete-Step 'Explicit migration completed after verified backup'
        }
    } else {
        $script:InitialCounts = Get-DatabaseCounts
    }
    Start-Step 'up-web'
    $script:ChangedWeb = $true
    $null = Invoke-Compose $script:CurrentFiles @('up', '-d', '--no-deps', '--no-build', '--wait', '--wait-timeout', '120', 'web')
    $web = Get-Service web -RequireHealthy
    if (-not $Rollback) {
        Assert-Release ((Normalize-Bind $web.Config.Labels['org.ather-career.release-source']) -eq (Normalize-Bind $source)) 'Web did not adopt this release'
    }
    Assert-Release ((Read-WebLog $web.Id).Contains('Production security gate passed.')) 'Production security gate line is missing'
    Complete-Step 'Web healthy within 120 seconds; security gate line present'
    Start-Step 'up-owner_https'
    $script:ChangedProxy = $true
    $null = Invoke-Compose $script:CurrentFiles @('up', '-d', '--no-deps', '--no-build', '--wait', '--wait-timeout', '120', 'owner_https')
    Complete-Step 'Proxy healthy'
    Start-Step 'smoke'
    $expectedCount = if ($Migrate) { -1 } else { $script:InitialCounts[0] }
    Smoke-Release $script:FullSha $expectedCount
    Complete-Step 'Both origins, assets, redirects, private paths, logs, version and ledger passed'
    $script:Outcome = 'PASSED'
    Write-Record
    Write-Host 'RELEASE_LOCAL_PASSED'
    exit 0
} catch {
    Complete-Step (Protect-Text $_.Exception.Message) $false
    if ($script:ChangedWeb -and $null -ne $script:Previous) {
        Start-Step 'automatic-rollback'
        try {
            Assert-Release ((Get-ImageId $script:Previous.ImagePin) -ceq $script:Previous.ImageId) 'Captured rollback image is unavailable'
            if ((Get-ImageId $script:Previous.Image) -cne $script:Previous.ImageId) {
                $null = Invoke-ReleaseNative docker @('tag', $script:Previous.ImagePin, $script:Previous.Image)
            }
            $null = Invoke-Compose $script:Previous.ConfigFiles @('up', '-d', '--no-deps', '--no-build', '--wait', '--wait-timeout', '120', 'web')
            if ($script:ChangedProxy) {
                $null = Invoke-Compose $script:Previous.ConfigFiles @('up', '-d', '--no-deps', '--no-build', '--wait', '--wait-timeout', '120', 'owner_https')
            }
            $oldSha = Resolve-Commit (Get-ImageRevision $script:Previous.Image)
            $oldCount = if ($Migrate) { -1 } else { $script:InitialCounts[0] }
            Smoke-Release $oldSha $oldCount 'restored'
            $script:Outcome = 'FAILED + rolled back'
            Complete-Step 'Previous label-derived configuration restored; rollback smoke passed'
        } catch {
            $script:Outcome = 'FAILED + rollback FAILED (manual recovery required)'
            Complete-Step (Protect-Text $_.Exception.Message) $false
        }
    }
    try { Write-Record } catch { Write-Host 'STEP record FAIL Release record could not be written' }
    Write-Host 'RELEASE_LOCAL_FAILED'
    exit 1
}
