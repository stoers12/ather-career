[CmdletBinding()]
param(
    [ValidateRange(1, 2)]
    [int]$Runs = 2
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:TemporaryPaths = [System.Collections.Generic.List[string]]::new()
$script:EnvironmentBackup = @{}
$script:EnvironmentGateBefore = $null
$script:ResidentBefore = $null

function Assert-Smoke([bool]$Condition, [string]$Message) {
    if (-not $Condition) {
        throw "Production smoke assertion failed: $Message"
    }
}

function Invoke-Native([string]$Command, [string[]]$Arguments) {
    $output = @(& $Command @Arguments 2>&1 | ForEach-Object { $_.ToString() })
    if ($LASTEXITCODE -ne 0) {
        $safePrefix = @($Arguments | Select-Object -First 3) -join ' '
        $safeOutput = (($output -join "`n") -replace '(?i)(password|secret|token|credential|authorization)\S*', '$1=[REDACTED]')
        throw "Production smoke command failed: $Command $safePrefix`n$safeOutput"
    }
    return ($output -join "`n")
}

function Get-RandomHex([int]$ByteCount = 12) {
    $bytes = [byte[]]::new($ByteCount)
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    return [Convert]::ToHexString($bytes).ToLowerInvariant()
}

function Get-UnusedLoopbackPort {
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
        try {
            $listener.Start()
            $port = ([System.Net.IPEndPoint]$listener.LocalEndpoint).Port
        } finally {
            $listener.Stop()
        }
        if ($port -notin 8098, 8443) {
            return $port
        }
    }
    throw 'Could not reserve a non-protected loopback port.'
}

function New-TemporaryPath {
    $path = [System.IO.Path]::GetTempFileName()
    $script:TemporaryPaths.Add($path)
    return $path
}

function Remove-TemporaryPaths {
    foreach ($path in $script:TemporaryPaths) {
        if ([System.IO.File]::Exists($path)) {
            Remove-Item -LiteralPath $path -Force
        }
    }
    $script:TemporaryPaths.Clear()
}

function Get-LoopbackHttpStatus([string]$Url) {
    $status = @(& curl.exe '--silent' '--show-error' '--insecure' '--max-time' '5' '--output' 'NUL' '--write-out' '%{http_code}' $Url 2>$null | ForEach-Object { $_.ToString() }) -join ''
    if ($status -match '^\d{3}$') {
        return [int]$status
    }
    return 0
}

function Get-ContainerLabel([string]$Container, [string]$Label) {
    return (Invoke-Native 'docker' @('inspect', '--format', "{{index .Config.Labels `"$Label`"}}", $Container)).Trim()
}

function Get-ContainerMountIdentity([string]$Container) {
    $mounts = (Invoke-Native 'docker' @('inspect', '--format', '{{range .Mounts}}{{.Name}}|{{.Source}}|{{.Destination}}{{"\n"}}{{end}}', $Container)).Trim()
    return @($mounts -split "`r?`n" | Where-Object { $_ -ne '' } | Sort-Object)
}

function Get-ContainerIdentity([string]$Container) {
    $identity = (Invoke-Native 'docker' @('inspect', '--format', '{{.Id}}|{{.RestartCount}}|{{.Name}}', $Container)).Trim() -split '\|', 3
    Assert-Smoke ($identity.Count -eq 3) 'A container identity could not be captured.'
    return [pscustomobject]@{
        Id = $identity[0]
        RestartCount = [int]$identity[1]
        Name = $identity[2]
        Project = Get-ContainerLabel $Container 'com.docker.compose.project'
        Service = Get-ContainerLabel $Container 'com.docker.compose.service'
        Mounts = Get-ContainerMountIdentity $Container
    }
}

function Get-ContainerEnvironmentValues([string]$Container, [string[]]$Names) {
    $values = @{}
    $environment = Invoke-Native 'docker' @('inspect', '--format', '{{range .Config.Env}}{{println .}}{{end}}', $Container)
    foreach ($entry in ($environment -split "`r?`n")) {
        $separator = $entry.IndexOf('=')
        if ($separator -lt 1) {
            continue
        }
        $name = $entry.Substring(0, $separator)
        if ($name -in $Names) {
            $values[$name] = $entry.Substring($separator + 1)
        }
    }
    return $values
}

function Get-PrivacySafeDatabaseFingerprint([string]$WebContainer, [string]$DbContainer) {
    $values = Get-ContainerEnvironmentValues $WebContainer @('DB_USER', 'DB_PASSWORD', 'DB_NAME')
    if (-not $values.ContainsKey('DB_USER') -or -not $values.ContainsKey('DB_PASSWORD') -or -not $values.ContainsKey('DB_NAME')) {
        return 'UNAVAILABLE'
    }
    $query = @'
SELECT SHA2(CONCAT_WS('|',
    (SELECT COUNT(*) FROM portfolios),
    (SELECT COUNT(*) FROM personal_info),
    (SELECT COUNT(*) FROM projects),
    (SELECT COUNT(*) FROM experiences),
    (SELECT COUNT(*) FROM messages),
    (SELECT COALESCE(SHA2(GROUP_CONCAT(CONCAT_WS(CHAR(31), id, owner_user_id, public_slug, is_published, published_at) ORDER BY id SEPARATOR ''), 256), SHA2('', 256)) FROM portfolios),
    (SELECT COALESCE(SHA2(GROUP_CONCAT(CONCAT_WS(CHAR(31), id, portfolio_id, image_path) ORDER BY id SEPARATOR ''), 256), SHA2('', 256)) FROM projects),
    (SELECT COALESCE(SHA2(GROUP_CONCAT(CONCAT_WS(CHAR(31), id, recipient_portfolio_id, created_at) ORDER BY id SEPARATOR ''), 256), SHA2('', 256)) FROM messages)
), 256);
'@
    $result = @(& docker exec '--env' ("MYSQL_PWD=" + [string]$values['DB_PASSWORD']) $DbContainer 'mysql' '--batch' '--skip-column-names' '--user' ([string]$values['DB_USER']) ([string]$values['DB_NAME']) '--execute' $query 2>$null | ForEach-Object { $_.ToString() }) -join ''
    if ($LASTEXITCODE -ne 0 -or $result -notmatch '^[a-f0-9]{64}$') {
        return 'UNAVAILABLE'
    }
    return $result
}

function Get-PrivateMediaFileCount([string]$WebContainer) {
    $count = @(& docker exec $WebContainer 'sh' '-lc' 'find "$ATHERCAR_STORAGE_ROOT/portfolios" -type f 2>/dev/null | wc -l' 2>$null | ForEach-Object { $_.ToString() }) -join ''
    if ($LASTEXITCODE -ne 0 -or $count -notmatch '^\d+$') {
        return -1
    }
    return [int]$count
}

function Get-EnvironmentGateSnapshot([string]$Name, [int]$Port, [string]$Scheme) {
    $baseUrl = "${Scheme}://localhost:$Port"
    $rootStatus = Get-LoopbackHttpStatus "$baseUrl/"
    $healthStatus = Get-LoopbackHttpStatus "$baseUrl/health.php"
    $portContainers = @((Invoke-Native 'docker' @('ps', '--all', '--quiet', '--filter', "publish=$Port")).Trim() -split "`r?`n" | Where-Object { $_ -match '^[a-f0-9]{12,64}$' } | Sort-Object)
    if ($portContainers.Count -eq 0 -and $rootStatus -eq 0 -and $healthStatus -eq 0) {
        return [pscustomobject]@{
            Name = $Name; State = 'NOT_PRESENT'; Port = $Port; RootStatus = 0; HealthStatus = 0
            Project = ''; Containers = @(); Networks = @(); VolumeIdentities = @(); DataFingerprint = 'NOT_PRESENT'; PrivateMediaFileCount = -1
        }
    }
    Assert-Smoke ($portContainers.Count -gt 0) "$Name has an endpoint but no attributable container; it cannot be safely baselined."
    Assert-Smoke ($rootStatus -eq 200 -and $healthStatus -eq 200) "$Name is present but its root or health endpoint is not healthy."
    $web = $portContainers[0]
    $project = Get-ContainerLabel $web 'com.docker.compose.project'
    Assert-Smoke ($project -ne '') "$Name is present without a Compose project identity."
    $projectContainers = @((Invoke-Native 'docker' @('ps', '--all', '--quiet', '--filter', "label=com.docker.compose.project=$project")).Trim() -split "`r?`n" | Where-Object { $_ -match '^[a-f0-9]{12,64}$' } | Sort-Object)
    $containers = @($projectContainers | ForEach-Object { Get-ContainerIdentity $_ })
    $db = @($containers | Where-Object { $_.Service -eq 'db' } | Select-Object -First 1)
    $networks = @((Invoke-Native 'docker' @('network', 'ls', '--quiet', '--filter', "label=com.docker.compose.project=$project")).Trim() -split "`r?`n" | Where-Object { $_ -ne '' } | Sort-Object)
    $volumes = @($containers | ForEach-Object { $_.Mounts } | Sort-Object -Unique)
    $privateMediaCount = Get-PrivateMediaFileCount $web
    $fingerprint = if ($db.Count -eq 1) { Get-PrivacySafeDatabaseFingerprint $web $db[0].Id } else { 'UNAVAILABLE' }
    return [pscustomobject]@{
        Name = $Name; State = 'PRESENT'; Port = $Port; RootStatus = $rootStatus; HealthStatus = $healthStatus
        Project = $project; Containers = $containers; Networks = $networks; VolumeIdentities = $volumes
        DataFingerprint = $fingerprint; PrivateMediaFileCount = $privateMediaCount
    }
}

function Get-ResidentContainerSnapshot {
    $containers = @()
    foreach ($name in @('portfolio_course-web-1', 'portfolio_course-db-1')) {
        $id = @(& docker ps '--all' '--quiet' '--filter' "name=^/$name$" 2>$null | ForEach-Object { $_.ToString() }) -join ''
        if ($LASTEXITCODE -eq 0 -and $id -match '^[a-f0-9]{12,64}$') {
            $containers += Get-ContainerIdentity $id
        }
    }
    return @($containers | Sort-Object Name)
}

function Convert-Snapshot([object]$Snapshot) {
    return $Snapshot | ConvertTo-Json -Depth 12 -Compress
}

function Assert-EnvironmentGatePreserved([object]$Before, [object]$After) {
    if ($Before.State -eq 'NOT_PRESENT') {
        Assert-Smoke ($After.State -eq 'NOT_PRESENT') "$($Before.Name) was absent at preflight but appeared during smoke testing."
        return
    }
    Assert-Smoke ($After.State -eq 'PRESENT') "$($Before.Name) disappeared during smoke testing."
    Assert-Smoke ($After.RootStatus -eq 200 -and $After.HealthStatus -eq 200) "$($Before.Name) is not healthy after smoke testing."
    Assert-Smoke ((Convert-Snapshot $Before) -eq (Convert-Snapshot $After)) "$($Before.Name) resources or privacy-safe state changed during smoke testing."
}

function Assert-ResidentContainersPreserved([object[]]$Before, [object[]]$After) {
    Assert-Smoke ((Convert-Snapshot $Before) -eq (Convert-Snapshot $After)) 'The unrelated portfolio_course resident containers changed during smoke testing.'
}

function Invoke-SmokeHttp {
    param(
        [Parameter(Mandatory)][string]$Url,
        [ValidateSet('GET', 'POST', 'PUT', 'TRACE')][string]$Method = 'GET',
        [hashtable]$Form = @{},
        [hashtable]$Headers = @{}
    )

    $bodyPath = New-TemporaryPath
    $headerPath = New-TemporaryPath
    $arguments = @('--silent', '--show-error', '--max-time', '15', '--request', $Method, '--output', $bodyPath, '--dump-header', $headerPath, '--write-out', '%{http_code}')
    foreach ($header in $Headers.GetEnumerator()) {
        $arguments += @('--header', "$($header.Key): $($header.Value)")
    }
    foreach ($field in $Form.GetEnumerator()) {
        $arguments += @('--data-urlencode', "$($field.Key)=$($field.Value)")
    }
    $arguments += $Url
    $statusText = @(& curl.exe @arguments 2>&1 | ForEach-Object { $_.ToString() }) -join ''
    if ($LASTEXITCODE -ne 0 -or $statusText -notmatch '^\d{3}$') {
        throw 'Local HTTP inspection failed.'
    }

    $bytes = [System.IO.File]::ReadAllBytes($bodyPath)
    return [pscustomobject]@{
        Status = [int]$statusText
        Headers = [System.IO.File]::ReadAllText($headerPath)
        Bytes = $bytes
        Body = [System.Text.Encoding]::UTF8.GetString($bytes)
    }
}

function Assert-HttpStatus($Response, [int[]]$Expected, [string]$Name) {
    Assert-Smoke ($Response.Status -in $Expected) "$Name returned HTTP $($Response.Status), expected $($Expected -join '/')."
}

function Get-ContainerId([string]$Project, [string]$Service) {
    $id = (Invoke-Native 'docker' @('compose', '--project-name', $Project, '--file', 'docker-compose.production.yml', 'ps', '-q', $Service)).Trim()
    Assert-Smoke ($id -match '^[a-f0-9]{12,64}$') "The smoke $Service container ID is unavailable."
    return $id
}

function Get-ContainerHealth([string]$Container) {
    return (Invoke-Native 'docker' @('inspect', '--format', '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}', $Container)).Trim()
}

function Wait-ContainerHealth([string]$Container, [string]$Name) {
    $deadline = [DateTime]::UtcNow.AddMinutes(2)
    do {
        if ((Get-ContainerHealth $Container) -eq 'healthy') {
            return
        }
        Start-Sleep -Seconds 2
    } while ([DateTime]::UtcNow -lt $deadline)
    throw "The smoke $Name container did not become healthy."
}

function Wait-HealthyHttp([string]$BaseUrl) {
    $deadline = [DateTime]::UtcNow.AddMinutes(2)
    do {
        try {
            $response = Invoke-SmokeHttp -Url "$BaseUrl/health.php"
            if ($response.Status -eq 200 -and [System.Text.Encoding]::UTF8.GetString($response.Bytes) -eq "OK`n") {
                return
            }
        } catch {
            # Startup is expected to race while Apache comes online.
        }
        Start-Sleep -Seconds 2
    } while ([DateTime]::UtcNow -lt $deadline)
    throw 'The smoke health endpoint did not become ready.'
}

function Invoke-SmokeFixture([string]$Container, [string]$Project, [string]$Action, [string]$Slug) {
    $result = Invoke-Native 'docker' @(
        'exec',
        '--user', 'www-data',
        '--env', 'ATHERCAR_PRODUCTION_SMOKE=1',
        '--env', "ATHERCAR_PRODUCTION_SMOKE_PROJECT=$Project",
        $Container,
        'php', 'scripts/production-smoke-fixture.php', $Action, $Slug
    )
    $jsonResult = @($result -split "`r?`n" | Where-Object { $_ -match '^\{"ok":' } | Select-Object -Last 1) -join ''
    try {
        return ($jsonResult | ConvertFrom-Json -ErrorAction Stop)
    } catch {
        throw 'The CLI-only smoke fixture returned an invalid structured result.'
    }
}

function Test-EmptyProjectResources([string]$Project) {
    $containers = (Invoke-Native 'docker' @('ps', '--all', '--quiet', '--filter', "label=com.docker.compose.project=$Project")).Trim()
    $networks = (Invoke-Native 'docker' @('network', 'ls', '--quiet', '--filter', "label=com.docker.compose.project=$Project")).Trim()
    $volumes = (Invoke-Native 'docker' @('volume', 'ls', '--quiet', '--filter', "label=com.docker.compose.project=$Project")).Trim()
    Assert-Smoke ($containers -eq '') 'Run-owned smoke containers remain after cleanup.'
    Assert-Smoke ($networks -eq '') 'Run-owned smoke networks remain after cleanup.'
    Assert-Smoke ($volumes -eq '') 'Run-owned smoke volumes remain after cleanup.'
}

function Assert-SmokeIsolation([string]$Project, [string]$Port, [string]$WebContainer, [string]$DbContainer) {
    Assert-Smoke ($Project -match '^ather_production_smoke_[a-f0-9]{24}$') 'The smoke test used a non-owned Compose project name.'
    Assert-Smoke ($Port -notin '8098', '8443') 'The smoke test referenced a protected loopback port.'
    $gate = @($script:EnvironmentGateBefore.Owner, $script:EnvironmentGateBefore.Protected)
    foreach ($environment in $gate) {
        Assert-Smoke ($Project -ne $environment.Project) "The smoke project conflicts with $($environment.Name)."
        $protectedIds = @($environment.Containers | ForEach-Object { $_.Id })
        Assert-Smoke ($WebContainer -notin $protectedIds -and $DbContainer -notin $protectedIds) "The smoke test reused a $($environment.Name) container."
        $protectedSources = @($environment.VolumeIdentities | ForEach-Object { ($_ -split '\|')[1] } | Where-Object { $_ -ne '' })
        $smokeSources = @((Get-ContainerMountIdentity $WebContainer) + (Get-ContainerMountIdentity $DbContainer) | ForEach-Object { ($_ -split '\|')[1] } | Where-Object { $_ -ne '' })
        Assert-Smoke (@($smokeSources | Where-Object { $_ -in $protectedSources }).Count -eq 0) "The smoke test reused a $($environment.Name) volume."
        $smokeNetworks = @((Invoke-Native 'docker' @('network', 'ls', '--quiet', '--filter', "label=com.docker.compose.project=$Project")).Trim() -split "`r?`n" | Where-Object { $_ -ne '' })
        Assert-Smoke (@($smokeNetworks | Where-Object { $_ -in $environment.Networks }).Count -eq 0) "The smoke test reused a $($environment.Name) network."
    }
    $webEnvironment = Get-ContainerEnvironmentValues $WebContainer @('DB_NAME')
    Assert-Smoke ($webEnvironment['DB_NAME'] -eq $Project) 'The smoke web container does not target its own run-owned database.'
}

function Test-ImageContract([string]$Image, [string]$Head) {
    $label = (Invoke-Native 'docker' @('image', 'inspect', '--format', '{{ index .Config.Labels "org.opencontainers.image.revision" }}', $Image)).Trim()
    Assert-Smoke ($label -eq $Head) 'The image revision label does not match the tested Git commit.'
    $contract = Invoke-Native 'docker' @(
        'run', '--rm', '--entrypoint', 'sh', $Image, '-lc',
        'set -eu; check() { "$@" || { echo "image-contract-failed:$*" >&2; exit 1; }; }; check test -f /var/www/app/vendor/autoload.php; check test -f /var/www/app/vendor/composer/autoload_static.php; check test ! -d /var/www/app/vendor/phpunit; check test -f /var/www/app/database/production-ownership-bootstrap.php; check test -f /var/www/app/database/wait-for-production-bootstrap.php; check test -f /usr/local/etc/php/conf.d/portfolio-production.ini; check grep -q "^display_errors[[:space:]]*=[[:space:]]*Off" /usr/local/etc/php/conf.d/portfolio-production.ini; check grep -q "^[[:space:]]*DocumentRoot[[:space:]]*/var/www/public" /etc/apache2/sites-available/000-default.conf; check sh -c "apache2ctl -M | grep -q rewrite_module"; check test -f /etc/apache2/conf-enabled/zzz-portfolio-security-headers.conf; vips --version; check test -d /var/www/public; check test ! -e /var/www/public/.env; check test ! -e /var/www/public/includes; check test ! -e /var/www/public/database'
    )
    Assert-Smoke ($contract -match '(?m)^vips-') 'The Production image does not contain a working libvips runtime.'
}

function Set-SmokeEnvironment([string]$Project, [string]$Port, [string]$Image, [string]$Head) {
    $adminSecret = Get-RandomHex 24
    $adminHash = (Invoke-Native 'docker' @('run', '--rm', '--env', "SMOKE_ADMIN_SECRET=$adminSecret", 'php:8.3-cli', 'php', '-r', 'echo password_hash(getenv("SMOKE_ADMIN_SECRET"), PASSWORD_BCRYPT);')).Trim()
    Assert-Smoke ($adminHash -match '^\$2[aby]\$') 'A synthetic Production admin credential could not be generated.'
    $dbSecret = Get-RandomHex 24
    $oidcSecret = Get-RandomHex 24
    $settings = @{
        APP_VERSION = $Head
        PORTFOLIO_PRODUCTION_IMAGE = $Image
        PORTFOLIO_PRODUCTION_PORT = $Port
        DB_HOST = 'db'
        DB_PORT = '3306'
        DB_NAME = $Project
        DB_USER = 'smoke_app'
        DB_PASSWORD = $dbSecret
        MYSQL_DATABASE = $Project
        MYSQL_USER = 'smoke_app'
        MYSQL_PASSWORD = $dbSecret
        MYSQL_ROOT_PASSWORD = (Get-RandomHex 24)
        PUBLIC_BASE_URL = 'https://portfolio-smoke.invalid'
        SESSION_COOKIE_SECURE = 'true'
        ADMIN_USERNAME = 'smoke-owner'
        ADMIN_PASSWORD_HASH = $adminHash
        LEGACY_ADMIN_AUTH_ENABLED = 'false'
        EXPECTED_OIDC_ISSUER = 'https://oidc-smoke.invalid/'
        PRESERVED_V1_OIDC_SUBJECT = ('smoke-owner-' + $Project.Substring('ather_production_smoke_'.Length))
        OIDC_CLIENT_ID = ('smoke-client-' + (Get-RandomHex 8))
        OIDC_CLIENT_SECRET = $oidcSecret
        OIDC_REDIRECT_URI = 'https://portfolio-smoke.invalid/owner_oidc_callback.php'
    }
    foreach ($entry in $settings.GetEnumerator()) {
        $script:EnvironmentBackup[$entry.Key] = [Environment]::GetEnvironmentVariable($entry.Key, 'Process')
        [Environment]::SetEnvironmentVariable($entry.Key, [string]$entry.Value, 'Process')
    }
}

function Restore-SmokeEnvironment {
    foreach ($entry in $script:EnvironmentBackup.GetEnumerator()) {
        [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process')
    }
    $script:EnvironmentBackup.Clear()
}

function Invoke-Compose([string]$Project, [string[]]$Arguments) {
    return Invoke-Native 'docker' (@('compose', '--project-name', $Project, '--file', 'docker-compose.production.yml') + $Arguments)
}

function Convert-FixtureOutput([string]$Output) {
    $jsonResult = @($Output -split "`r?`n" | Where-Object { $_ -match '^\{"ok":' } | Select-Object -Last 1) -join ''
    try {
        return ($jsonResult | ConvertFrom-Json -ErrorAction Stop)
    } catch {
        throw 'The CLI-only smoke fixture returned an invalid structured result.'
    }
}

function Invoke-SmokeFixtureCompose([string]$Project, [string]$Action, [string]$Slug) {
    $output = Invoke-Compose $Project @(
        'run', '--rm', '--no-deps', '--user', 'www-data',
        '--entrypoint', 'php',
        '--env', 'ATHERCAR_PRODUCTION_SMOKE=1',
        '--env', "ATHERCAR_PRODUCTION_SMOKE_PROJECT=$Project",
        'web', 'scripts/production-smoke-fixture.php', $Action, $Slug
    )
    return Convert-FixtureOutput $output
}

function Invoke-ComposePhp([string]$Project, [string[]]$ScriptArguments) {
    return Invoke-Compose $Project (@(
        'run', '--rm', '--no-deps', '--user', 'www-data', '--entrypoint', 'php', 'web'
    ) + $ScriptArguments)
}

function Assert-UpgradeFingerprintPreserved($Before, $After) {
    foreach ($property in @(
        'user_id', 'portfolio_id', 'is_published', 'users', 'portfolios', 'profiles',
        'skills', 'projects', 'experiences', 'messages', 'media_references', 'media_files',
        'data_fingerprint', 'media_fingerprint', 'owner_fingerprint', 'ownership_fingerprint'
    )) {
        Assert-Smoke ($Before.$property -eq $After.$property) "The synthetic existing-data $property changed during Production startup."
    }
}

function Invoke-ProductionUpgradeRehearsal([int]$Number, [string]$Head) {
    $suffix = Get-RandomHex 12
    $project = "ather_production_smoke_$suffix"
    $slug = "production-smoke-$suffix"
    $port = [string](Get-UnusedLoopbackPort)
    $image = "ather-production-smoke:$suffix"
    $baseUrl = "http://127.0.0.1:$port"
    $projectActivated = $false
    $cleanupSucceeded = $false

    Assert-Smoke ($project -match '^ather_production_smoke_[a-f0-9]{24}$') 'The generated upgrade Compose project name is unsafe.'
    Assert-Smoke ($slug -match '^production-smoke-[a-f0-9]{24}$') 'The generated upgrade slug is unsafe.'
    Assert-Smoke ($port -notin '8098', '8443') 'The upgrade rehearsal attempted to claim a protected port.'

    try {
        Test-EmptyProjectResources $project
        Set-SmokeEnvironment -Project $project -Port $port -Image $image -Head $Head
        Invoke-Compose $project @('config', '--quiet') | Out-Null
        Invoke-Compose $project @('build') | Out-Null
        Test-ImageContract -Image $image -Head $Head
        $projectActivated = $true
        Invoke-Compose $project @('up', '--detach', '--no-build', 'db') | Out-Null
        $db = Get-ContainerId $project 'db'
        Wait-ContainerHealth $db 'upgrade database'
        $baselineReady = Invoke-ComposePhp $project @('database/wait-for-production-bootstrap.php')
        Assert-Smoke ($baselineReady -match 'Production database bootstrap is ready\.') 'The upgrade rehearsal did not wait for the immutable Production baseline schema.'

        $throughOwnershipExpand = Invoke-ComposePhp $project @('database/migrate.php', '--through=003')
        Assert-Smoke ($throughOwnershipExpand -match 'Adopted baseline migration 001_baseline\.' -and $throughOwnershipExpand -match 'Applied migration 003_ownership_expand\.') 'The isolated upgrade state did not reach the ownership expansion point.'
        $bootstrap = Invoke-ComposePhp $project @('database/production-ownership-bootstrap.php')
        Assert-Smoke ($bootstrap -match 'Production ownership bootstrap completed\.') 'The explicit preserved-owner bootstrap did not complete for the isolated upgrade rehearsal.'
        $throughPreUpgrade = Invoke-ComposePhp $project @('database/migrate.php', '--through=008')
        Assert-Smoke ($throughPreUpgrade -match 'Applied migration 008_personal_info_hero_headline\.') 'The isolated upgrade state did not reach the intended pre-upgrade ledger.'
        $preUpgrade = Invoke-SmokeFixtureCompose $project 'upgrade-seed' $slug
        Assert-Smoke ($preUpgrade.ok -eq $true -and [int]$preUpgrade.is_published -eq 1) 'The synthetic existing-data upgrade fixture was not published.'

        Invoke-Compose $project @('up', '--detach', '--no-build', 'web') | Out-Null
        $web = Get-ContainerId $project 'web'
        Assert-SmokeIsolation -Project $project -Port $port -WebContainer $web -DbContainer $db
        Wait-ContainerHealth $web 'upgrade web'
        Wait-HealthyHttp $baseUrl
        Assert-Smoke ((Invoke-Native 'docker' @('inspect', '--format', '{{.RestartCount}}', $web)).Trim() -eq '0') 'The Production upgrade web container restarted during startup.'

        $schema = Invoke-SmokeFixture $web $project 'assert-schema' $slug
        Assert-Smoke ($schema.ok -eq $true) 'The Production entrypoint did not complete the upgrade migration ledger.'
        $postUpgrade = Invoke-SmokeFixture $web $project 'upgrade-fingerprint' $slug
        Assert-Smoke ($postUpgrade.ok -eq $true) 'The post-upgrade fingerprint could not be captured.'
        Assert-UpgradeFingerprintPreserved $preUpgrade $postUpgrade
        $repeatMigration = Invoke-Native 'docker' @('exec', $web, 'php', 'database/migrate.php')
        Assert-Smoke ($repeatMigration -match 'No pending migrations\.') 'The upgraded database reported pending migrations after Production startup.'
        $repeatBootstrap = Invoke-Native 'docker' @('exec', $web, 'php', 'database/production-ownership-bootstrap.php')
        Assert-Smoke ($repeatBootstrap -match 'Production ownership bootstrap already complete\.') 'The ownership bootstrap was not idempotent on existing data.'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug") @(200) 'upgraded canonical Portfolio route'
        $media = Invoke-SmokeHttp -Url "$baseUrl/p/$slug/media/project/$([int]$preUpgrade.project_id)"
        Assert-HttpStatus $media @(200) 'upgraded project media'
        Assert-Smoke ($media.Headers -match '(?im)^Content-Type:\s*image/(jpeg|png|webp)' -and $media.Bytes.Length -gt 32) 'The pre-upgrade private media did not survive Production startup.'
        $logs = Invoke-Native 'docker' @('logs', $web)
        Assert-Smoke ($logs -notmatch '(?i)(fatal error|production security gate failed|migration [0-9]{3} failed)') 'The upgraded Production web log contains a fatal startup error.'

        Write-Output "PASS production upgrade rehearsal $Number project=$project port=$port image=$image"
    } finally {
        $cleanupError = $null
        try {
            if ($projectActivated) {
                Invoke-Compose $project @('down', '--volumes', '--remove-orphans') | Out-Null
            }
            $imageExists = & docker image inspect $image 2>$null
            if ($LASTEXITCODE -eq 0) {
                Invoke-Native 'docker' @('image', 'rm', $image) | Out-Null
            }
            Test-EmptyProjectResources $project
            Write-Output "PASS production upgrade cleanup $Number project=$project"
            $cleanupSucceeded = $true
        } catch {
            $cleanupError = $_
        } finally {
            Remove-TemporaryPaths
            Restore-SmokeEnvironment
        }
        if ($null -ne $cleanupError) {
            throw $cleanupError
        }
        Assert-Smoke $cleanupSucceeded 'The upgrade rehearsal cleanup did not complete.'
    }
}

function Invoke-ComposeExpectedFailure([string]$Project, [string[]]$Arguments) {
    $output = @(& docker compose '--project-name' $Project '--file' 'docker-compose.production.yml' @Arguments 2>&1 | ForEach-Object { $_.ToString() })
    if ($LASTEXITCODE -eq 0) {
        throw 'Production smoke assertion failed: An intentionally invalid Production bootstrap unexpectedly succeeded.'
    }
    return (($output -join "`n") -replace '(?i)(password|secret|token|credential|authorization)\S*', '$1=[REDACTED]')
}

function Invoke-ProductionFailureSafetyRehearsal([string]$Head) {
    $suffix = Get-RandomHex 12
    $project = "ather_production_smoke_$suffix"
    $port = [string](Get-UnusedLoopbackPort)
    $image = "ather-production-smoke:$suffix"
    $projectActivated = $false
    $cleanupSucceeded = $false

    try {
        Test-EmptyProjectResources $project
        Set-SmokeEnvironment -Project $project -Port $port -Image $image -Head $Head
        Invoke-Compose $project @('config', '--quiet') | Out-Null
        Invoke-Compose $project @('build') | Out-Null
        Test-ImageContract -Image $image -Head $Head
        $projectActivated = $true
        Invoke-Compose $project @('up', '--detach', '--no-build', 'db') | Out-Null
        $db = Get-ContainerId $project 'db'
        Wait-ContainerHealth $db 'failure-safety database'

        $missingSchemaStarted = [DateTime]::UtcNow
        $missingSchema = Invoke-ComposeExpectedFailure $project @(
            'run', '--rm', '--no-deps', '--env', "DB_NAME=${project}_missing_schema", 'web'
        )
        $missingSchemaElapsed = ([DateTime]::UtcNow - $missingSchemaStarted).TotalSeconds
        Assert-Smoke ($missingSchema -match 'Production database bootstrap did not become ready in time\.') 'An incomplete schema did not fail in the bounded Production bootstrap wait.'
        Assert-Smoke ($missingSchemaElapsed -ge 55 -and $missingSchemaElapsed -le 85) 'The bounded Production schema wait did not use its deterministic timeout.'

        $missingOwnerBinding = Invoke-ComposeExpectedFailure $project @(
            'run', '--rm', '--no-deps', '--env', 'PRESERVED_V1_OIDC_SUBJECT=', 'web'
        )
        Assert-Smoke ($missingOwnerBinding -match 'Production ownership bootstrap stopped: PRESERVED_V1_OIDC_SUBJECT is required') 'A required ownership bootstrap binding did not fail clearly.'
        $webService = (Invoke-Compose $project @('ps', '--quiet', 'web')).Trim()
        Assert-Smoke ($webService -eq '') 'A failed Production bootstrap left a web service running.'
        Assert-Smoke ((Get-LoopbackHttpStatus "http://127.0.0.1:$port/health.php") -eq 0) 'A failed Production bootstrap exposed an unsafe web health endpoint.'
        Write-Output "PASS production failure-safe bootstrap rehearsal project=$project"
    } finally {
        $cleanupError = $null
        try {
            if ($projectActivated) {
                Invoke-Compose $project @('down', '--volumes', '--remove-orphans') | Out-Null
            }
            $imageExists = & docker image inspect $image 2>$null
            if ($LASTEXITCODE -eq 0) {
                Invoke-Native 'docker' @('image', 'rm', $image) | Out-Null
            }
            Test-EmptyProjectResources $project
            Write-Output "PASS production failure-safe cleanup project=$project"
            $cleanupSucceeded = $true
        } catch {
            $cleanupError = $_
        } finally {
            Remove-TemporaryPaths
            Restore-SmokeEnvironment
        }
        if ($null -ne $cleanupError) {
            throw $cleanupError
        }
        Assert-Smoke $cleanupSucceeded 'The failure-safe bootstrap rehearsal cleanup did not complete.'
    }
}

function Invoke-ProductionSmokeRun([int]$Number, [string]$Head) {
    $suffix = Get-RandomHex 12
    $project = "ather_production_smoke_$suffix"
    $slug = "production-smoke-$suffix"
    $port = [string](Get-UnusedLoopbackPort)
    $image = "ather-production-smoke:$suffix"
    $baseUrl = "http://127.0.0.1:$port"
    $projectActivated = $false
    $cleanupSucceeded = $false

    Assert-Smoke ($project -match '^ather_production_smoke_[a-f0-9]{24}$') 'The generated Compose project name is unsafe.'
    Assert-Smoke ($slug -match '^production-smoke-[a-f0-9]{24}$') 'The generated public slug is unsafe.'
    Assert-Smoke ($port -notin '8098', '8443') 'The smoke test attempted to claim a protected port.'

    try {
        Test-EmptyProjectResources $project
        Set-SmokeEnvironment -Project $project -Port $port -Image $image -Head $Head
        Invoke-Compose $project @('config', '--quiet') | Out-Null
        Invoke-Compose $project @('build') | Out-Null
        Test-ImageContract -Image $image -Head $Head
        $projectActivated = $true
        Invoke-Compose $project @('up', '--detach', '--no-build') | Out-Null

        $web = Get-ContainerId $project 'web'
        $db = Get-ContainerId $project 'db'
        Assert-SmokeIsolation -Project $project -Port $port -WebContainer $web -DbContainer $db
        Wait-ContainerHealth $db 'database'
        Wait-ContainerHealth $web 'web'
        Wait-HealthyHttp $baseUrl
        $initialRestartCount = (Invoke-Native 'docker' @('inspect', '--format', '{{.RestartCount}}', $web)).Trim()
        if ($initialRestartCount -ne '0') {
            $safeStartupLog = (Invoke-Native 'docker' @('logs', '--tail', '80', $web)) -replace '(?i)(password|secret|token|credential|authorization)\S*', '$1=[REDACTED]'
            throw "Production smoke assertion failed: The web container restarted during initial startup (count=$initialRestartCount). $safeStartupLog"
        }
        $mountTargets = Invoke-Native 'docker' @('inspect', '--format', '{{range .Mounts}}{{.Destination}}{{"\n"}}{{end}}', $web)
        $mountTargetList = @($mountTargets -split "`r?`n" | Where-Object { $_ -ne '' })
        $allowedMountTargets = @('/var/lib/ather-career/storage', '/var/www/app/runtime/rate-limit')
        $missingMountTargets = @($allowedMountTargets | Where-Object { $_ -notin $mountTargetList })
        $unexpectedMountTargets = @($mountTargetList | Where-Object { $_ -notin $allowedMountTargets })
        Assert-Smoke ($missingMountTargets.Count -eq 0) 'The Production web container is missing an expected disposable volume.'
        Assert-Smoke ($unexpectedMountTargets.Count -eq 0) 'The Production web container has an unsafe source mount.'
        Assert-Smoke ((Invoke-Native 'docker' @('exec', $web, 'sh', '-lc', 'test "$APP_ENV" = production; test -z "${ATHERCAR_TEST_MODE:-}"; test "$ATHERCAR_STORAGE_ROOT" = /var/lib/ather-career/storage')).Trim() -eq '') 'The Production runtime guard or storage configuration is invalid.'

        $health = Invoke-SmokeHttp -Url "$baseUrl/health.php"
        Assert-HttpStatus $health @(200) 'health.php'
        Assert-Smoke ([System.Text.Encoding]::UTF8.GetString($health.Bytes) -eq "OK`n") 'health.php did not return the exact healthy body.'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/") @(200) 'root route'

        $schema = Invoke-SmokeFixture $web $project 'assert-schema' $slug
        Assert-Smoke ($schema.ok -eq $true) 'The entrypoint did not fully bootstrap the migration ledger.'
        $repeatMigration = Invoke-Native 'docker' @('exec', $web, 'php', 'database/migrate.php')
        Assert-Smoke ($repeatMigration -match 'No pending migrations\.') 'The Production migration runner is not idempotent.'
        $fixture = Invoke-SmokeFixture $web $project 'seed' $slug
        Assert-Smoke ($fixture.ok -eq $true -and [int]$fixture.project_id -gt 0 -and $fixture.profile_media_ready -eq $true -and $fixture.project_media_ready -eq $true) 'The CLI-only synthetic fixture could not provision public media.'
        $canonical = Invoke-SmokeFixture $web $project 'assert-canonical-url' $slug
        Assert-Smoke ($canonical.ok -eq $true) 'PUBLIC_BASE_URL did not generate the configured canonical HTTPS URL.'

        $portfolio = Invoke-SmokeHttp -Url "$baseUrl/p/$slug"
        Assert-HttpStatus $portfolio @(200) 'canonical Portfolio route'
        Assert-Smoke ($portfolio.Headers -notmatch '(?im)^Location:') 'The canonical Portfolio route redirected to a direct controller.'
        Assert-Smoke ($portfolio.Body -match 'Production Smoke Owner' -and $portfolio.Body -match 'portfolio-header' -and $portfolio.Body -match 'ATHER' -and $portfolio.Body -match 'portfolio-footer') 'The public Portfolio presentation contract did not render.'
        Assert-Smoke ($portfolio.Body -notmatch 'Private preview|owner\.php|/var/www|[A-Za-z]:\\') 'The public Portfolio leaked preview, owner, or filesystem controls.'
        $hostHeaderPortfolio = Invoke-SmokeHttp -Url "$baseUrl/p/$slug" -Headers @{ Host = 'host-header-smoke.invalid' }
        Assert-HttpStatus $hostHeaderPortfolio @(200) 'Host-header Portfolio route'
        Assert-Smoke ($hostHeaderPortfolio.Body -notmatch 'host-header-smoke\.invalid') 'Public rendering derived a value from the untrusted Host header.'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/unknown-smoke-slug") @(404) 'unknown Portfolio route'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/not--a-slug") @(404) 'malformed Portfolio route'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/%2e%2e%2fconfig") @(400, 404) 'traversal route'

        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/portfolio.css") @(200) 'Portfolio CSS asset'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/portfolio.js") @(200) 'Portfolio JavaScript asset'
        $profileMedia = Invoke-SmokeHttp -Url "$baseUrl/p/$slug/media/profile"
        Assert-HttpStatus $profileMedia @(200) 'profile media'
        Assert-Smoke ($profileMedia.Headers -match '(?im)^Content-Type:\s*image/(jpeg|png|webp)' -and $profileMedia.Bytes.Length -gt 32) 'Profile media is not a real image response.'
        $projectMedia = Invoke-SmokeHttp -Url "$baseUrl/p/$slug/media/project/$($fixture.project_id)"
        Assert-HttpStatus $projectMedia @(200) 'project media'
        Assert-Smoke ($projectMedia.Headers -match '(?im)^Content-Type:\s*image/(jpeg|png|webp)' -and $projectMedia.Bytes.Length -gt 32) 'Project media is not a real image response.'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug/media/project/999999") @(404) 'unknown project media'
        Assert-Smoke ($portfolio.Body -notmatch '/var/lib/ather-career/storage|portfolios/[0-9]+/(profile|projects)') 'Private media storage was exposed in public markup.'

        $projectsResponse = Invoke-SmokeHttp -Url "$baseUrl/p/$slug/projects.json"
        Assert-HttpStatus $projectsResponse @(200) 'projects JSON'
        Assert-Smoke ($projectsResponse.Headers -match '(?im)^Content-Type:\s*application/json') 'Projects endpoint did not return JSON.'
        try { $projectsJson = $projectsResponse.Body | ConvertFrom-Json -ErrorAction Stop } catch { throw 'Projects endpoint returned invalid JSON.' }
        Assert-Smoke ($projectsJson.success -eq $true -and @($projectsJson.projects).Count -eq 2 -and @($projectsJson.projects.title) -contains 'Smoke Media Project' -and @($projectsJson.projects.title) -contains 'Smoke Text Project') 'Projects JSON did not retain synthetic Portfolio tenant scope.'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug/projects.json" -Method POST) @(405) 'invalid projects JSON method'

        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug/contact") @(404) 'Contact GET'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug/contact" -Method POST) @(422) 'empty Contact POST'
        $beforeMessage = Invoke-SmokeFixture $web $project 'message-count' $slug
        Assert-Smoke ([int]$beforeMessage.count -eq 0) 'An empty Contact POST inserted a message.'
        $validContact = Invoke-SmokeHttp -Url "$baseUrl/p/$slug/contact" -Method POST -Form @{ name = 'Synthetic Sender'; email = 'sender@portfolio-smoke.invalid'; message = 'Synthetic smoke payload.' }
        Assert-HttpStatus $validContact @(303) 'valid Contact POST'
        Assert-Smoke ($validContact.Headers -match [regex]::Escape("Location: /p/${slug}?contact=sent#contact")) 'Contact POST did not use the canonical PRG redirect.'
        $afterFirstMessage = Invoke-SmokeFixture $web $project 'message-count' $slug
        Assert-Smoke ([int]$afterFirstMessage.count -eq 1) 'The valid Contact POST did not create exactly one synthetic message.'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/${slug}?contact=sent") @(200) 'Contact success GET'
        Assert-Smoke ([int](Invoke-SmokeFixture $web $project 'message-count' $slug).count -eq 1) 'The Contact success GET duplicated a message.'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug/contact" -Method POST -Form @{ name = 'Rate One'; email = 'rate-one@portfolio-smoke.invalid'; message = 'Synthetic rate payload one.' }) @(303) 'rate-limit first additional POST'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug/contact" -Method POST -Form @{ name = 'Rate Two'; email = 'rate-two@portfolio-smoke.invalid'; message = 'Synthetic rate payload two.' }) @(303) 'rate-limit second additional POST'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug/contact" -Method POST -Form @{ name = 'Rate Three'; email = 'rate-three@portfolio-smoke.invalid'; message = 'Synthetic rate payload three.' }) @(429) 'rate-limit denial'
        Assert-Smoke ([int](Invoke-SmokeFixture $web $project 'message-count' $slug).count -eq 3) 'Rate limiting did not preserve the expected message count.'

        $unpublished = Invoke-SmokeFixture $web $project 'unpublish' $slug
        Assert-Smoke ([int]$unpublished.is_published -eq 0) 'The synthetic Portfolio did not unpublish.'
        foreach ($path in @("/p/$slug", "/p/$slug/media/profile", "/p/$slug/media/project/$($fixture.project_id)", "/p/$slug/projects.json", "/p/$slug/contact")) {
            Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl$path") @(404) "unpublished $path"
        }
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug/contact" -Method POST -Form @{ name = 'Denied'; email = 'denied@portfolio-smoke.invalid'; message = 'Synthetic denied payload.' }) @(404) 'unpublished Contact POST'
        Assert-Smoke ([int](Invoke-SmokeFixture $web $project 'message-count' $slug).count -eq 3) 'Unpublished Contact changed message rows.'
        $republished = Invoke-SmokeFixture $web $project 'republish' $slug
        Assert-Smoke ([int]$republished.is_published -eq 1 -and [int]$republished.portfolio_id -eq [int]$fixture.portfolio_id) 'Republishing changed the permanent synthetic Portfolio identity.'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug") @(200) 'republished Portfolio route'

        foreach ($path in @('/.env', '/.git/HEAD', '/config/database.php', '/database/portfolio_db.sql', '/Dockerfile.production', '/docker-compose.production.yml', '/vendor/composer/installed.json', '/uploads/', '/production-smoke-fixture.php', '/assets/')) {
            Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl$path") @(403, 404) "Production source protection $path"
        }
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/" -Method TRACE) @(405) 'TRACE request'
        $secured = Invoke-SmokeHttp -Url "$baseUrl/p/$slug"
        foreach ($header in @('X-Frame-Options:\s*DENY', "Content-Security-Policy:\s*frame-ancestors 'none'", 'X-Content-Type-Options:\s*nosniff', 'Referrer-Policy:\s*strict-origin-when-cross-origin')) {
            Assert-Smoke ($secured.Headers -match "(?im)^$header") 'A required Production security header is missing.'
        }
        Assert-Smoke ($secured.Headers -notmatch '(?im)^Server:.*(?:/[0-9]|PHP)') 'The server exposed unnecessary version diagnostics.'

        $phaseTwo = Invoke-Native 'docker' @('run', '--rm', '--entrypoint', 'php', '--env', 'APP_ENV=test', '--env', 'ATHERCAR_TEST_MODE=1', '--volume', "$(Get-Location):/workspace:ro", '--workdir', '/workspace', $image, 'scripts/run-phase2-tests.php')
        Assert-Smoke ($phaseTwo -match 'Phase 2 test foundation passed\.') 'The full Phase-2 gate did not pass in the libvips-capable Production image.'
        $lifecycle = & (Join-Path $PSHOME 'pwsh.exe') -NoProfile -File 'scripts/run-phase2-publication-lifecycle-e2e.ps1' -WebContainer $web -DbContainer $db -PublicBaseUrl 'https://localhost:8443' 2>&1 | ForEach-Object { $_.ToString() }
        if ($LASTEXITCODE -ne 0 -or (($lifecycle -join "`n") -notmatch 'PASS isolated publication lifecycle database teardown')) {
            $lifecycleFailure = @($lifecycle | Where-Object { $_ -match '^FAIL' } | Select-Object -First 1) -join ''
            if ($lifecycleFailure -eq '') {
                $lifecycleFailure = @($lifecycle | Where-Object { $_ -match '^(?:.*failed|Exception:|Error:)' } | Select-Object -First 1) -join ''
            }
            throw "The focused publication lifecycle E2E regression did not pass. $lifecycleFailure"
        }

        Invoke-Compose $project @('restart', 'web') | Out-Null
        $web = Get-ContainerId $project 'web'
        Wait-ContainerHealth $web 'web after its required restart'
        Wait-HealthyHttp $baseUrl
        Assert-Smoke ((Invoke-Native 'docker' @('inspect', '--format', '{{.RestartCount}}', $web)).Trim() -eq '0') 'The smoke web container entered a restart loop.'
        Assert-HttpStatus (Invoke-SmokeHttp -Url "$baseUrl/p/$slug") @(200) 'Portfolio after web restart'
        $persistedMedia = Invoke-SmokeHttp -Url "$baseUrl/p/$slug/media/profile"
        Assert-HttpStatus $persistedMedia @(200) 'profile media after web restart'
        Assert-Smoke ($persistedMedia.Bytes.Length -gt 32) 'Private media did not persist in the disposable storage volume.'
        $logs = Invoke-Native 'docker' @('logs', $web)
        Assert-Smoke ($logs -notmatch '(?i)(fatal error|production security gate failed|migration [0-9]{3} failed)') 'The Production web log contains a fatal startup error.'

        Write-Output "PASS production smoke run $Number project=$project port=$port image=$image"
    } finally {
        $cleanupError = $null
        try {
            if ($projectActivated) {
                Invoke-Compose $project @('down', '--volumes', '--remove-orphans') | Out-Null
            }
            $imageExists = & docker image inspect $image 2>$null
            if ($LASTEXITCODE -eq 0) {
                Invoke-Native 'docker' @('image', 'rm', $image) | Out-Null
            }
            Test-EmptyProjectResources $project
            Write-Output "PASS production smoke cleanup $Number project=$project"
            $cleanupSucceeded = $true
        } catch {
            $cleanupError = $_
        } finally {
            Remove-TemporaryPaths
            Restore-SmokeEnvironment
        }
        if ($null -ne $cleanupError) {
            throw $cleanupError
        }
        Assert-Smoke $cleanupSucceeded 'The smoke cleanup did not complete.'
    }
}

try {
    $head = (Invoke-Native 'git' @('rev-parse', 'HEAD')).Trim()
    Assert-Smoke ($head -match '^[a-f0-9]{40}$') 'The exact Git commit is unavailable.'
    $script:EnvironmentGateBefore = [pscustomobject]@{
        Owner = Get-EnvironmentGateSnapshot -Name 'Owner 8443' -Port 8443 -Scheme 'https'
        Protected = Get-EnvironmentGateSnapshot -Name 'Protected 8098' -Port 8098 -Scheme 'http'
    }
    $script:ResidentBefore = Get-ResidentContainerSnapshot
    Write-Output "INFO environment gate Owner=$($script:EnvironmentGateBefore.Owner.State) Protected=$($script:EnvironmentGateBefore.Protected.State) resident_portfolio_course=$($script:ResidentBefore.Count)"
    for ($run = 1; $run -le $Runs; $run++) {
        Invoke-ProductionSmokeRun -Number $run -Head $head
    }
    for ($run = 1; $run -le $Runs; $run++) {
        Invoke-ProductionUpgradeRehearsal -Number $run -Head $head
    }
    Invoke-ProductionFailureSafetyRehearsal -Head $head
} finally {
    Remove-TemporaryPaths
    Restore-SmokeEnvironment
    if ($null -ne $script:EnvironmentGateBefore) {
        $ownerAfter = Get-EnvironmentGateSnapshot -Name 'Owner 8443' -Port 8443 -Scheme 'https'
        $protectedAfter = Get-EnvironmentGateSnapshot -Name 'Protected 8098' -Port 8098 -Scheme 'http'
        Assert-EnvironmentGatePreserved $script:EnvironmentGateBefore.Owner $ownerAfter
        Assert-EnvironmentGatePreserved $script:EnvironmentGateBefore.Protected $protectedAfter
        $residentAfter = Get-ResidentContainerSnapshot
        Assert-ResidentContainersPreserved $script:ResidentBefore $residentAfter
        Write-Output "PASS environment gate Owner=$($ownerAfter.State)_PRESERVED Protected=$($protectedAfter.State)_PRESERVED resident_portfolio_course=UNCHANGED"
    }
}
