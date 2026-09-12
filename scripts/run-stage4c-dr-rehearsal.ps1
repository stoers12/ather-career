[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidatePattern('^[a-z0-9][a-z0-9._:/-]{1,255}$')]
    [string]$Image,
    [string]$ResultPath = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Assert-Dr([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw "Stage-4C DR rehearsal assertion failed: $Message" }
}

function Get-DrHex([int]$Bytes = 12) {
    $buffer = [byte[]]::new($Bytes)
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($buffer)
    return [Convert]::ToHexString($buffer).ToLowerInvariant()
}

function Invoke-DrDocker([string[]]$Arguments) {
    $output = @(& docker @Arguments 2>&1 | ForEach-Object { $_.ToString() })
    if ($LASTEXITCODE -ne 0) {
        $safe = @($Arguments | Select-Object -First 4) -join ' '
        $safeOutput = (($output -join "`n") -replace '(?i)(password|secret|token|credential)(?:=|:)?\S*', '$1=[REDACTED]')
        throw "Stage-4C DR Docker command failed: $safe`n$($safeOutput.Substring(0, [Math]::Min(1200, $safeOutput.Length)))"
    }
    return ($output -join "`n")
}

function Get-DrPort {
    for ($attempt = 0; $attempt -lt 20; ++$attempt) {
        $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
        try { $listener.Start(); $port = ([System.Net.IPEndPoint]$listener.LocalEndpoint).Port }
        finally { $listener.Stop() }
        if ($port -notin 8088, 8098, 8443) { return $port }
    }
    throw 'Could not allocate a disposable loopback port.'
}

function Wait-DrHealth([string]$Container, [string]$Name) {
    $deadline = [DateTime]::UtcNow.AddMinutes(3)
    do {
        $state = (Invoke-DrDocker @('inspect', '--format', '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}', $Container)).Trim()
        if ($state -eq 'healthy') { return }
        Start-Sleep -Seconds 2
    } while ([DateTime]::UtcNow -lt $deadline)
    throw "The disposable $Name container did not become healthy."
}

function Grant-DrRestoreAccess([string]$Container) {
    $deadline = [DateTime]::UtcNow.AddMinutes(2)
    do {
        try {
            Invoke-DrDocker @('exec', $Container, 'sh', '-lc', 'set -eu; printf "CREATE USER IF NOT EXISTS ''stage4c_restore''@''%%'' IDENTIFIED BY ''%s''; GRANT ALL PRIVILEGES ON *.* TO ''stage4c_restore''@''%%'' WITH GRANT OPTION; FLUSH PRIVILEGES;\n" "$MYSQL_ROOT_PASSWORD" | MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot') | Out-Null
            return
        } catch {
            Start-Sleep -Seconds 2
        }
    } while ([DateTime]::UtcNow -lt $deadline)
    throw 'The disposable restore database did not accept the required isolated restore grant.'
}

function Invoke-DrHttp([string]$Url, [hashtable]$Headers = @{}) {
    $body = [System.IO.Path]::GetTempFileName()
    $header = [System.IO.Path]::GetTempFileName()
    try {
        $arguments = @('--silent', '--show-error', '--max-time', '15', '--output', $body, '--dump-header', $header, '--write-out', '%{http_code}')
        foreach ($item in $Headers.GetEnumerator()) { $arguments += @('--header', "$($item.Key): $($item.Value)") }
        $arguments += $Url
        $statusText = @(& curl.exe @arguments 2>$null | ForEach-Object { $_.ToString() }) -join ''
        if ($LASTEXITCODE -ne 0 -or $statusText -notmatch '^\d{3}$') { throw 'A disposable HTTP request failed.' }
        return [pscustomobject]@{
            Status = [int]$statusText
            Body = [System.IO.File]::ReadAllText($body)
            Headers = [System.IO.File]::ReadAllText($header)
        }
    } finally {
        Remove-Item -LiteralPath $body, $header -Force -ErrorAction SilentlyContinue
    }
}

function Wait-DrReady([string]$BaseUrl) {
    $deadline = [DateTime]::UtcNow.AddMinutes(3)
    do {
        try {
            $health = Invoke-DrHttp "$BaseUrl/health.php"
            $ready = Invoke-DrHttp "$BaseUrl/ready.php"
            if ($health.Status -eq 200 -and $ready.Status -eq 200) { return }
        } catch { }
        Start-Sleep -Seconds 2
    } while ([DateTime]::UtcNow -lt $deadline)
    throw 'The disposable application did not become healthy and ready.'
}

function Convert-DrJson([string]$Value, [string]$Name) {
    $line = @($Value -split "`r?`n" | Where-Object { $_ -match '^\{"ok":true' } | Select-Object -Last 1) -join ''
    try { return $line | ConvertFrom-Json -ErrorAction Stop }
    catch { throw "The disposable $Name result was not valid structured output." }
}

function Invoke-DrFixture([string]$Container, [string]$DatabaseNamespace, [string]$Action, [string]$Slug) {
    $output = Invoke-DrDocker @('exec', '--user', 'www-data', '--env', 'ATHERCAR_STAGE4C_DR=1', '--env', "ATHERCAR_STAGE4C_DR_PROJECT=$DatabaseNamespace", $Container, 'php', 'scripts/stage4c-dr-fixture.php', $Action, $Slug)
    return Convert-DrJson $output 'fixture'
}

function Invoke-DrCompose([string]$Project, [string[]]$Arguments) {
    return Invoke-DrDocker (@('compose', '--project-name', $Project, '--file', 'docker-compose.production.yml') + $Arguments)
}

function Set-DrCheckpoint([string]$State) {
    if ($ResultPath -eq '') { return }
    [System.IO.File]::WriteAllText(
        $ResultPath,
        (([pscustomobject]@{ ok = $false; state = $State }) | ConvertTo-Json -Compress),
        [System.Text.UTF8Encoding]::new($false)
    )
}

function Get-DrService([string]$Project, [string]$Service) {
    $id = (Invoke-DrCompose $Project @('ps', '-q', $Service)).Trim()
    Assert-Dr ($id -match '^[a-f0-9]{12,64}$') "The disposable $Service container is unavailable."
    return $id
}

$environmentBackup = @{}
function Set-DrEnvironment([string]$Project, [int]$Port, [string]$Database, [string]$BootstrapDatabase, [string]$ImageName, [string]$Head, [string]$RootPassword, [bool]$UseRoot) {
    $applicationPassword = Get-DrHex 24
    $values = @{
        APP_VERSION = $Head
        PORTFOLIO_PRODUCTION_IMAGE = $ImageName
        PORTFOLIO_PRODUCTION_PORT = [string]$Port
        DB_HOST = 'db'
        DB_PORT = '3306'
        DB_NAME = $Database
        DB_USER = $(if ($UseRoot) { 'stage4c_restore' } else { 'stage4c_app' })
        DB_PASSWORD = $(if ($UseRoot) { $RootPassword } else { $applicationPassword })
        MYSQL_DATABASE = $BootstrapDatabase
        MYSQL_USER = 'stage4c_app'
        MYSQL_PASSWORD = $applicationPassword
        MYSQL_ROOT_PASSWORD = $RootPassword
        PUBLIC_BASE_URL = 'https://stage4c.invalid'
        SESSION_COOKIE_SECURE = 'true'
        EXPECTED_OIDC_ISSUER = 'https://stage4c-oidc.invalid/'
        PRESERVED_V1_OIDC_SUBJECT = ('stage4c-owner-' + $Project.Substring($Project.LastIndexOf('_') + 1))
        OIDC_CLIENT_ID = ('stage4c-client-' + (Get-DrHex 8))
        OIDC_CLIENT_SECRET = Get-DrHex 24
        OIDC_REDIRECT_URI = 'https://stage4c.invalid/owner_oidc_callback.php'
    }
    foreach ($entry in $values.GetEnumerator()) {
        if (-not $environmentBackup.ContainsKey($entry.Key)) { $environmentBackup[$entry.Key] = [Environment]::GetEnvironmentVariable($entry.Key, 'Process') }
        [Environment]::SetEnvironmentVariable($entry.Key, [string]$entry.Value, 'Process')
    }
}

function Restore-DrEnvironment {
    foreach ($entry in $environmentBackup.GetEnumerator()) { [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process') }
    $environmentBackup.Clear()
}

$suffix = Get-DrHex 12
$sourceProject = "ather_stage4c_dr_$suffix"
$restoreProject = "ather_stage4c_restorestack_$suffix"
$sourceDatabase = $sourceProject
$restoreDatabase = "ather_stage4c_restore_$suffix"
$restoreBootstrapDatabase = "ather_stage4c_boot_$suffix"
$slug = "stage4c-dr-$suffix"
$sourcePort = Get-DrPort
$restorePort = Get-DrPort
$rootPassword = Get-DrHex 24
$temporaryRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("stage4c-dr-" + $suffix)
$sourceStarted = $false
$restoreStarted = $false
$runnerContainer = ''
$backupDuration = [TimeSpan]::Zero
$restoreDuration = [TimeSpan]::Zero
$rehearsalResult = $null

if ($ResultPath -ne '') {
    if (-not [System.IO.Path]::IsPathRooted($ResultPath)) { throw 'The optional DR result path must be absolute.' }
    $resultParent = Split-Path -Parent $ResultPath
    if ($resultParent -eq '' -or -not (Test-Path -LiteralPath $resultParent -PathType Container)) { throw 'The optional DR result directory is unavailable.' }
}

Assert-Dr ($sourceProject -match '^ather_stage4c_dr_[a-f0-9]{24}$') 'The source project namespace is unsafe.'
Assert-Dr ($restoreDatabase -match '^ather_stage4c_restore_[a-f0-9]{24}$') 'The restore database namespace is unsafe.'
Assert-Dr ($slug -match '^stage4c-dr-[a-f0-9]{24}$') 'The public synthetic slug is unsafe.'

try {
    Set-DrCheckpoint 'starting'
    New-Item -ItemType Directory -Path $temporaryRoot -Force | Out-Null
    $head = (git rev-parse HEAD).Trim()
    $imageId = (Invoke-DrDocker @('image', 'inspect', '--format', '{{.Id}}', $Image)).Trim()
    Assert-Dr ($head -match '^[a-f0-9]{40}$' -and $imageId -match '^sha256:[a-f0-9]{64}$') 'The supplied image identity is unavailable.'

    Set-DrEnvironment $sourceProject $sourcePort $sourceDatabase $sourceDatabase $Image $head $rootPassword $false
    Invoke-DrCompose $sourceProject @('up', '--detach', '--no-build') | Out-Null
    $sourceStarted = $true
    $sourceWeb = Get-DrService $sourceProject 'web'; $sourceDb = Get-DrService $sourceProject 'db'
    Wait-DrHealth $sourceDb 'source database'; Wait-DrHealth $sourceWeb 'source web'
    $sourceBase = "http://127.0.0.1:$sourcePort"
    Wait-DrReady $sourceBase
    Set-DrCheckpoint 'source-ready'
    $seed = Invoke-DrFixture $sourceWeb $sourceProject 'seed' $slug
    $before = Invoke-DrFixture $sourceWeb $sourceProject 'fingerprint' $slug
    Assert-Dr ($seed.ok -eq $true -and [int]$before.projects -ge 7 -and [int]$before.skills -ge 5 -and [int]$before.experiences -ge 4 -and [int]$before.messages -ge 1 -and [int]$before.media_files -ge 6) 'The source rehearsal dataset is incomplete.'
    $sourceJson = Invoke-DrHttp "$sourceBase/p/$slug/projects.json"
    Assert-Dr ($sourceJson.Status -eq 200 -and $sourceJson.Body -notmatch 'image_path') 'The source public Project JSON contract failed.'
    try { $null = $sourceJson.Body | ConvertFrom-Json -ErrorAction Stop } catch { throw 'The source public Project JSON was not valid.' }
    $sourceProjectMediaUrls = @([regex]::Matches($sourceJson.Body, '"image_url"\s*:\s*"(?<url>[^"]+)"') | ForEach-Object { $_.Groups['url'].Value -replace '\\/', '/' })
    Assert-Dr ($sourceProjectMediaUrls.Count -ge 1 -and @($sourceProjectMediaUrls | Where-Object { $_ -notmatch ('^/p/' + [regex]::Escape($slug) + '/media/project/[1-9][0-9]*$') }).Count -eq 0) 'The source media-bearing Project did not expose a scoped presentation URL.'
    foreach ($sourceProjectMediaUrl in $sourceProjectMediaUrls) {
        $sourceProjectMedia = Invoke-DrHttp ("$sourceBase" + $sourceProjectMediaUrl)
        Assert-Dr ($sourceProjectMedia.Status -eq 200 -and $sourceProjectMedia.Headers -match '(?im)^Content-Type: image/') 'A source public Project derivative did not render.'
    }
    Set-DrCheckpoint 'source-seeded'

    Invoke-DrDocker @('exec', $sourceWeb, 'sh', '-lc', 'mkdir -p /tmp/stage4c-backups && chown www-data:www-data /tmp/stage4c-backups') | Out-Null
    $backupWatch = [System.Diagnostics.Stopwatch]::StartNew()
    $backupOutput = Invoke-DrDocker @('exec', '--user', 'www-data', '--env', "STAGE4C_REHEARSAL_PASSWORD=$($env:DB_PASSWORD)", $sourceWeb, 'php', 'scripts/stage4c-recovery.php', 'backup', '--mode', 'disposable-rehearsal', '--output-dir', '/tmp/stage4c-backups', '--media-root', '/var/lib/ather-career/storage', '--db-host', 'db', '--db-port', '3306', '--db-name', $sourceDatabase, '--db-user', 'stage4c_app', '--db-password-env', 'STAGE4C_REHEARSAL_PASSWORD', '--quiesced-confirmation', 'I_CONFIRM_QUIESCED_STAGE4C', '--allow-unencrypted-rehearsal', '--app-commit', $head, '--image-identity', $imageId)
    $backupDuration = $backupWatch.Elapsed
    $backup = Convert-DrJson $backupOutput 'backup'
    Assert-Dr ($backup.ok -eq $true -and [int]$backup.recognized_media_file_count -eq [int]$before.media_files) 'The disposable backup result is incomplete.'
    $verifyOutput = Invoke-DrDocker @('exec', '--user', 'www-data', $sourceWeb, 'php', 'scripts/stage4c-recovery.php', 'verify', '--mode', 'disposable-rehearsal', '--backup-dir', ("/tmp/stage4c-backups/" + [string]$backup.directory))
    $verified = Convert-DrJson $verifyOutput 'backup verification'
    Assert-Dr ($verified.ok -eq $true -and $verified.content_manifest_sha256 -eq $before.media_manifest_sha256) 'The source backup manifest did not verify.'
    Set-DrCheckpoint 'backup-verified'
    $backupHost = Join-Path $temporaryRoot 'backup'
    Invoke-DrDocker @('cp', ("${sourceWeb}:/tmp/stage4c-backups/" + [string]$backup.directory), $backupHost) | Out-Null

    Invoke-DrCompose $sourceProject @('down', '--volumes', '--remove-orphans') | Out-Null
    $sourceStarted = $false
    Set-DrCheckpoint 'source-destroyed'

    Restore-DrEnvironment
    Set-DrEnvironment $restoreProject $restorePort $restoreDatabase $restoreBootstrapDatabase $Image $head $rootPassword $true
    Invoke-DrCompose $restoreProject @('up', '--detach', '--no-build', 'db') | Out-Null
    $restoreStarted = $true
    $restoreDb = Get-DrService $restoreProject 'db'; Wait-DrHealth $restoreDb 'restore database'
    Set-DrCheckpoint 'restore-db-ready'
    Set-DrCheckpoint 'restore-grant-starting'
    Grant-DrRestoreAccess $restoreDb
    Set-DrCheckpoint 'restore-grant-ready'
    Set-DrCheckpoint 'restore-runner-starting'
    Invoke-DrCompose $restoreProject @('run', '--detach', '--no-deps', '--entrypoint', 'sh', 'web', '-lc', 'while :; do sleep 60; done') | Out-Null
    Set-DrCheckpoint 'restore-runner-created'
    $runnerIds = @((Invoke-DrDocker @('ps', '--quiet', '--filter', "label=com.docker.compose.project=$restoreProject", '--filter', 'label=com.docker.compose.service=web')).Trim() -split "`r?`n" | Where-Object { $_ -match '^[a-f0-9]{12,64}$' })
    Assert-Dr ($runnerIds.Count -eq 1) 'The disposable restore runner is unavailable.'
    $runnerContainer = $runnerIds[0]
    Invoke-DrDocker @('exec', $runnerContainer, 'sh', '-lc', 'mkdir -p /tmp/stage4c-inbox') | Out-Null
    Invoke-DrDocker @('cp', $backupHost, ("${runnerContainer}:/tmp/stage4c-inbox/backup")) | Out-Null
    $restoreWatch = [System.Diagnostics.Stopwatch]::StartNew()
    $restoreOutput = Invoke-DrDocker @('exec', '--env', "STAGE4C_REHEARSAL_PASSWORD=$rootPassword", $runnerContainer, 'php', 'scripts/stage4c-recovery.php', 'restore', '--mode', 'disposable-rehearsal', '--backup-dir', '/tmp/stage4c-inbox/backup', '--target-media-root', '/var/lib/ather-career/storage', '--db-host', 'db', '--db-port', '3306', '--target-db', $restoreDatabase, '--db-user', 'stage4c_restore', '--db-password-env', 'STAGE4C_REHEARSAL_PASSWORD')
    $restoreDuration = $restoreWatch.Elapsed
    $restored = Convert-DrJson $restoreOutput 'restore'
    Assert-Dr ($restored.ok -eq $true -and [int]$restored.recognized_media_file_count -eq [int]$before.media_files) 'The disposable restore result is incomplete.'
    Set-DrCheckpoint 'restore-complete'
    $negativeOutput = Invoke-DrDocker @('exec', $runnerContainer, 'php', 'scripts/stage4c-negative-recovery-tests.php')
    $negative = Convert-DrJson $negativeOutput 'negative recovery tests'
    Assert-Dr ($negative.ok -eq $true -and @($negative.negative_cases).Count -ge 16) 'The required negative recovery cases did not pass.'
    Set-DrCheckpoint 'negative-complete'
    Invoke-DrDocker @('rm', '--force', $runnerContainer) | Out-Null
    $runnerContainer = ''
    Invoke-DrCompose $restoreProject @('up', '--detach', '--no-build', 'web') | Out-Null
    $restoredWeb = Get-DrService $restoreProject 'web'; Wait-DrHealth $restoredWeb 'restored web'
    $restoreBase = "http://127.0.0.1:$restorePort"; Wait-DrReady $restoreBase
    Set-DrCheckpoint 'restored-stack-ready'
    $after = Invoke-DrFixture $restoredWeb $restoreDatabase 'fingerprint' $slug
    foreach ($field in @('published', 'profiles', 'projects', 'skills', 'experiences', 'messages', 'media_files', 'media_manifest_sha256', 'migration_count', 'migration_ledger_sha256')) {
        Assert-Dr ($before.$field -eq $after.$field) "Restored $field does not match the source rehearsal state."
    }
    $public = Invoke-DrHttp "$restoreBase/p/$slug"; Assert-Dr ($public.Status -eq 200 -and $public.Body -match 'Stage Four C Owner') 'The restored public Portfolio is not meaningful.'
    $json = Invoke-DrHttp "$restoreBase/p/$slug/projects.json"; Assert-Dr ($json.Status -eq 200 -and $json.Body -notmatch 'image_path') 'The restored public Project JSON contract failed.'
    try { $null = $json.Body | ConvertFrom-Json -ErrorAction Stop } catch { throw 'The restored public Project JSON was not valid.' }
    $restoredProjectMediaUrls = @([regex]::Matches($json.Body, '"image_url"\s*:\s*"(?<url>[^"]+)"') | ForEach-Object { $_.Groups['url'].Value -replace '\\/', '/' })
    Assert-Dr ($restoredProjectMediaUrls.Count -eq $sourceProjectMediaUrls.Count -and (($restoredProjectMediaUrls | Sort-Object) -join "`n") -eq (($sourceProjectMediaUrls | Sort-Object) -join "`n")) 'The restored media-bearing Project URLs do not match the source rehearsal state.'
    $profileMedia = Invoke-DrHttp "$restoreBase/p/$slug/media/profile"; Assert-Dr ($profileMedia.Status -eq 200 -and $profileMedia.Headers -match '(?im)^Content-Type: image/') 'The restored public profile derivative did not render.'
    foreach ($restoredProjectMediaUrl in $restoredProjectMediaUrls) {
        $projectMedia = Invoke-DrHttp ("$restoreBase" + $restoredProjectMediaUrl)
        Assert-Dr ($projectMedia.Status -eq 200 -and $projectMedia.Headers -match '(?im)^Content-Type: image/') 'A restored public Project derivative did not render.'
    }
    $private = Invoke-DrHttp "$restoreBase/portfolios/$($after.portfolio_id)/profile/original/stage4c-profile.png"; Assert-Dr ($private.Status -in 403,404) 'A private original became public.'
    $session = Invoke-DrFixture $restoredWeb $restoreDatabase 'session' $slug
    $owner = Invoke-DrHttp "$restoreBase/owner.php" @{ Cookie = ('portfolio_owner_session=' + [string]$session.session_id) }
    Assert-Dr ($owner.Status -eq 200 -and $owner.Body -match 'Owner Dashboard') 'The synthetic Owner session cannot access restored Owner pages.'
    $contact = Invoke-DrHttp "$restoreBase/p/$slug/contact"; Assert-Dr ($contact.Status -in 405,200) 'The restored public Contact endpoint is unavailable.'

    $rehearsalResult = [pscustomobject]@{
        ok = $true
        dataset = 'owner=1 profile=1 published=1 projects=7 skills=5 experiences=4 messages=1 profile/project originals+derivatives'
        backup_duration_ms = [int]$backupDuration.TotalMilliseconds
        restore_duration_ms = [int]$restoreDuration.TotalMilliseconds
        restored_projects = [int]$after.projects
        restored_skills = [int]$after.skills
        restored_experiences = [int]$after.experiences
        restored_messages = [int]$after.messages
        restored_media_files = [int]$after.media_files
        media_manifest_sha256 = [string]$after.media_manifest_sha256
        negative_cases = @($negative.negative_cases).Count
    } | ConvertTo-Json -Compress
    if ($ResultPath -ne '') { [System.IO.File]::WriteAllText($ResultPath, $rehearsalResult, [System.Text.UTF8Encoding]::new($false)) }
    Write-Output $rehearsalResult
} catch {
    if ($ResultPath -ne '') {
        $safeReason = ($_.Exception.Message -replace '(?i)(password|secret|token|credential)(?:=|:)?\S*', '$1=[REDACTED]')
        [System.IO.File]::WriteAllText(
            $ResultPath,
            (([pscustomobject]@{ ok = $false; state = 'failed'; reason = $safeReason.Substring(0, [Math]::Min(800, $safeReason.Length)) }) | ConvertTo-Json -Compress),
            [System.Text.UTF8Encoding]::new($false)
        )
    }
    throw
} finally {
    if ($runnerContainer -match '^[a-f0-9]{12,64}$') { try { Invoke-DrDocker @('rm', '--force', $runnerContainer) | Out-Null } catch { } }
    if ($sourceStarted) { try { Invoke-DrCompose $sourceProject @('down', '--volumes', '--remove-orphans') | Out-Null } catch { } }
    if ($restoreStarted) { try { Invoke-DrCompose $restoreProject @('down', '--volumes', '--remove-orphans') | Out-Null } catch { } }
    Restore-DrEnvironment
    if (Test-Path -LiteralPath $temporaryRoot) { Remove-Item -LiteralPath $temporaryRoot -Recurse -Force }
}
