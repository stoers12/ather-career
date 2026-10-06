Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$runId = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(6)).ToLowerInvariant()
$container = "ather-phase2a-http-$runId"
$listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
try { $listener.Start(); $port = ([System.Net.IPEndPoint]$listener.LocalEndpoint).Port } finally { $listener.Stop() }
$headers = Join-Path $env:TEMP "ather-phase2a-headers-$runId"
$cookies = Join-Path $env:TEMP "ather-phase2a-cookies-$runId"
$script:containerId = ''
$startStdout = Join-Path $env:TEMP "ather-phase2a-start-out-$runId"
$startStderr = Join-Path $env:TEMP "ather-phase2a-start-err-$runId"
$loginBody = Join-Path $env:TEMP "ather-phase2a-login-body-$runId"
$discoveryErr = Join-Path $env:TEMP "ather-phase2a-discovery-err-$runId"
$vendorVolume = "ather-phase2a-vendor-$runId"
$webRoot = Join-Path $env:TEMP "ather-phase2a-webroot-$runId"
$archivePath = Join-Path $env:TEMP "ather-phase2a-source-$runId.tar"

$setup = @'
set -eu
install -d -m 0700 /tmp/ather-career-ci-oidc /tmp/ather-phase2a-rate
openssl req -x509 -newkey rsa:2048 -sha256 -nodes -days 1 \
  -keyout /tmp/ather-career-ci-oidc/key.pem \
  -out /tmp/ather-career-ci-oidc/cert.pem \
  -subj /CN=ather-career-ci-oidc -addext subjectAltName=IP:127.0.0.1 >/dev/null 2>&1
cp /tmp/ather-career-ci-oidc/cert.pem /usr/local/share/ca-certificates/ather-phase2a.crt
update-ca-certificates >/dev/null 2>&1
php scripts/ci-oidc-discovery-mock.php /tmp/ather-career-ci-oidc >/dev/null 2>&1 &
exec php -S 0.0.0.0:8080 -t /var/www/html >/dev/null 2>&1
'@

function ConvertTo-LfShellPayload([string]$value) {
    return $value.Replace(([string][char]13 + [char]10), [string][char]10).Replace([string][char]13, [string][char]10)
}

function ConvertTo-SafeDiagnostic([string]$value) {
    if ([string]::IsNullOrWhiteSpace($value)) { return '[none]' }
    $safe = $value -replace '(?im)^(?:Set-Cookie|Cookie|Authorization):[^\r\n]*', '[REDACTED HEADER]'
    $safe = $safe -replace '(?i)\b(?:access_token|id_token|refresh_token|client_secret|code|state|nonce|session|subject|sub|email)\s*[:=]\s*[^\s&]+', '[REDACTED FIELD]'
    $safe = $safe -replace '(?i)\beyJ[A-Za-z0-9_-]{20,}(?:\.[A-Za-z0-9_-]+){1,2}', '[REDACTED TOKEN]'
    $safe = $safe -replace '(?i)\b[\w.+-]+@[\w.-]+\.[a-z]{2,}\b', '[REDACTED EMAIL]'
    $safe = $safe -replace '(?i)\b(?:auth0|google-oauth2|windowslive)\|[^\s"<>]+', '[REDACTED IDENTITY]'
    $safe = $safe -replace '([a-zA-Z]:[\\/])[^\s"<>]+', '[LOCAL PATH]'
    $safe = $safe -replace '(https?://[^\s/?]+)\?[^\s"<>]+', '$1?[REDACTED QUERY]'
    if ($safe.Length -gt 4096) { $safe = $safe.Substring(0, 4096) + ' [TRUNCATED]' }
    return $safe.Trim()
}

function Assert-DockerStartup([int]$exitCode, [string]$stderr, [string]$containerId) {
    if ($exitCode -ne 0) {
        throw "Disposable HTTP container failed to start (docker exit $exitCode): $(ConvertTo-SafeDiagnostic $stderr)"
    }
    if ($containerId -notmatch '^[0-9a-f]{64}$') { throw 'Disposable HTTP container did not return a valid container ID.' }
}

function Get-RunContainer([string]$name) {
    $errorPath = Join-Path $env:TEMP "ather-phase2a-inspect-$runId"
    try {
        $raw = & docker inspect $name 2> $errorPath
        $exitCode = $LASTEXITCODE
        $stderrRaw = if (Test-Path -LiteralPath $errorPath) { Get-Content -LiteralPath $errorPath -Raw } else { $null }
        $stderr = if ($null -eq $stderrRaw) { '' } else { [string]$stderrRaw }
        if ($exitCode -ne 0) {
            if ($stderr -match '(?i)No such (object|container)') { return $null }
            throw "Disposable container inspection failed (docker exit $exitCode): $(ConvertTo-SafeDiagnostic $stderr)"
        }
        if ($null -eq $raw -or [string]::IsNullOrWhiteSpace(($raw -join [char]10))) { throw 'Disposable container inspection returned empty JSON.' }
        try { $items = @($raw | ConvertFrom-Json) } catch { throw 'Disposable container inspection returned invalid JSON.' }
        if ($items.Count -ne 1 -or $null -eq $items[0] -or $items[0] -isnot [pscustomobject]) { throw 'Disposable container inspection returned an unexpected JSON shape.' }
        $info = $items[0]
        $names = @($info.PSObject.Properties.Name)
        if ($names -notcontains 'Name' -or $names -notcontains 'Id' -or $names -notcontains 'Config' -or $names -notcontains 'State') { throw 'Disposable container inspection returned an unexpected JSON shape.' }
        if ($info.Name -isnot [string] -or [string]::IsNullOrWhiteSpace($info.Name) -or $info.Id -isnot [string] -or $info.Id -notmatch '^[0-9a-f]{64}$' -or $info.Config -isnot [pscustomobject] -or $info.State -isnot [pscustomobject]) { throw 'Disposable container inspection returned an unexpected JSON shape.' }
        if (@($info.Config.PSObject.Properties.Name) -notcontains 'Labels' -or $info.Config.Labels -isnot [pscustomobject] -or @($info.State.PSObject.Properties.Name) -notcontains 'Status' -or @($info.State.PSObject.Properties.Name) -notcontains 'ExitCode') { throw 'Disposable container inspection returned an unexpected JSON shape.' }
        return $info
    } finally {
        if (Test-Path -LiteralPath $errorPath) { Remove-Item -LiteralPath $errorPath -Force }
    }
}

function Remove-RunContainer([string]$name, [string]$label, [string]$expectedId) {
    $info = Get-RunContainer $name
    if ($null -eq $info) { return }
    if ($info.Name -ne "/$name" -or $info.Config.Labels.'ather.phase2a.http' -ne $label) {
        throw 'Disposable container identity or ownership label did not match this run.'
    }
    if ($expectedId -and $info.Id -ne $expectedId) { throw 'Disposable container ID did not match this run.' }
    & docker rm -f $name 1>$null 2>$null
    $removeExit = $LASTEXITCODE
    if ($removeExit -ne 0 -and $null -ne (Get-RunContainer $name)) {
        throw "Could not remove run-owned disposable container (docker exit $removeExit)."
    }
}

function Write-RunDiagnostics([string]$name) {
    try {
        $info = Get-RunContainer $name
        if ($null -eq $info) { Write-Output 'DISPOSABLE_CONTAINER=absent'; return }
        if ($info.Name -ne "/$name" -or $info.Config.Labels.'ather.phase2a.http' -ne $runId) {
            Write-Output 'DISPOSABLE_CONTAINER=ownership-mismatch'
            return
        }
        Write-Output "DISPOSABLE_CONTAINER_STATUS=$($info.State.Status) EXIT=$($info.State.ExitCode)"
        $lines = @(& docker logs --tail 80 $name 2>&1 | ForEach-Object { $_.ToString() })
        $logExit = $LASTEXITCODE
        Write-Output "DISPOSABLE_CONTAINER_LOG_EXIT=$logExit"
        foreach ($line in $lines) { Write-Output "DISPOSABLE_CONTAINER_LOG=$(ConvertTo-SafeDiagnostic $line)" }
    } catch {
        Write-Output "DISPOSABLE_DIAGNOSTIC_ERROR=$(ConvertTo-SafeDiagnostic $_.Exception.Message)"
    }
}

function Invoke-WithRunCleanup([scriptblock]$body, [scriptblock]$diagnose, [scriptblock]$cleanup) {
    $primary = $null
    $cleanupError = $null
    try { & $body }
    catch {
        $primary = ConvertTo-SafeDiagnostic $_.Exception.Message
        try { & $diagnose } catch { Write-Output "DISPOSABLE_DIAGNOSTIC_ERROR=$(ConvertTo-SafeDiagnostic $_.Exception.Message)" }
    } finally {
        try { & $cleanup } catch { $cleanupError = ConvertTo-SafeDiagnostic $_.Exception.Message }
    }
    if ($primary) {
        if ($cleanupError) { Write-Output "DISPOSABLE_CLEANUP_ERROR=$cleanupError" }
        throw $primary
    }
    if ($cleanupError) { throw $cleanupError }
}

function Assert-DependencyProvision([string]$stage, [int]$exitCode, [string]$output) {
    if ($exitCode -ne 0) {
        throw "Disposable dependency $stage failed (docker exit $exitCode): $(ConvertTo-SafeDiagnostic $output)"
    }
}

function Invoke-CheckedDocker([string]$stage, [string[]]$arguments, [string]$expectedOutput = '') {
    $outPath = Join-Path $env:TEMP "ather-phase2a-$runId-$stage-out"
    $errPath = Join-Path $env:TEMP "ather-phase2a-$runId-$stage-err"
    $stderr = ''
    try {
        & docker @arguments 1> $outPath 2> $errPath
        $exitCode = $LASTEXITCODE
        if ($exitCode -ne 0) {
            $failedStdoutRaw = if (Test-Path -LiteralPath $outPath) { Get-Content -LiteralPath $outPath -Raw } else { $null }
            $failedStderrRaw = if (Test-Path -LiteralPath $errPath) { Get-Content -LiteralPath $errPath -Raw } else { $null }
            $failedStdout = if ($null -eq $failedStdoutRaw) { '' } else { [string]$failedStdoutRaw }
            $stderr = if ($null -eq $failedStderrRaw) { '' } else { [string]$failedStderrRaw }
            Assert-DependencyProvision $stage $exitCode ($stderr + $failedStdout)
        }
        $stdoutRaw = if (Test-Path -LiteralPath $outPath) { Get-Content -LiteralPath $outPath -Raw } else { $null }
        $stderrRaw = if (Test-Path -LiteralPath $errPath) { Get-Content -LiteralPath $errPath -Raw } else { $null }
        $stdout = if ($null -eq $stdoutRaw) { '' } else { [string]$stdoutRaw }
        $stderr = if ($null -eq $stderrRaw) { '' } else { [string]$stderrRaw }
        $result = if ([string]::IsNullOrWhiteSpace($stdout)) { '' } else { $stdout.Trim() }
        switch ($stage) {
            'volume-create' {
                if ([string]::IsNullOrWhiteSpace($expectedOutput) -or $result -cne $expectedOutput) {
                    throw "Disposable dependency volume-create output contract failed (expected exact run-owned name; stderr: $(ConvertTo-SafeDiagnostic $stderr))."
                }
            }
            'validate' { }
            'install' { }
            'platform-composer' { }
            'locked-packages' {
                if ($result -cnotmatch '^[1-9][0-9]*$') {
                    throw "Disposable dependency locked-packages output contract failed (expected positive package count; stderr: $(ConvertTo-SafeDiagnostic $stderr))."
                }
            }
            'platform-php83' {
                if ($result -cne 'PHP83_READY') {
                    throw "Disposable dependency platform-php83 output contract failed (expected PHP83_READY; stderr: $(ConvertTo-SafeDiagnostic $stderr))."
                }
            }
            default { throw "Disposable dependency $stage has no output contract." }
        }
        return $result
    } catch {
        if ($_.Exception.Message -like "Disposable dependency $stage*") { throw }
        $primary = ConvertTo-SafeDiagnostic $_.Exception.Message
        throw [InvalidOperationException]::new("Disposable dependency $stage PowerShell failure: $primary; stderr: $(ConvertTo-SafeDiagnostic $stderr)", $_.Exception)
    } finally {
        foreach ($path in @($outPath, $errPath)) {
            if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Force }
        }
    }
}

function Get-RunVendorVolume([string]$name) {
    $errorPath = Join-Path $env:TEMP "ather-phase2a-$runId-volume-inspect"
    try {
        $raw = & docker volume inspect $name 2> $errorPath
        $exitCode = $LASTEXITCODE
        $stderrRaw = if (Test-Path -LiteralPath $errorPath) { Get-Content -LiteralPath $errorPath -Raw } else { $null }
        $stderr = if ($null -eq $stderrRaw) { '' } else { [string]$stderrRaw }
        if ($exitCode -ne 0) {
            if ($stderr -match '(?i)No such volume') { return $null }
            throw "Disposable dependency volume-inspect failed (docker exit $exitCode): $(ConvertTo-SafeDiagnostic $stderr)"
        }
        if ($null -eq $raw -or [string]::IsNullOrWhiteSpace(($raw -join [char]10))) { throw 'Disposable dependency volume-inspect returned empty JSON.' }
        try { $items = @($raw | ConvertFrom-Json) } catch { throw 'Disposable dependency volume-inspect returned invalid JSON.' }
        if ($items.Count -ne 1 -or $null -eq $items[0] -or $items[0] -isnot [pscustomobject]) { throw 'Disposable dependency volume-inspect returned an unexpected JSON shape.' }
        $info = $items[0]
        $names = @($info.PSObject.Properties.Name)
        if ($names -notcontains 'Name' -or $names -notcontains 'Labels') { throw 'Disposable dependency volume-inspect returned an unexpected JSON shape.' }
        if ($info.Name -isnot [string] -or [string]::IsNullOrWhiteSpace($info.Name) -or $info.Labels -isnot [pscustomobject]) { throw 'Disposable dependency volume-inspect returned an unexpected JSON shape.' }
        return $info
    } finally {
        if (Test-Path -LiteralPath $errorPath) { Remove-Item -LiteralPath $errorPath -Force }
    }
}

function Remove-RunVendorVolume([string]$name, [string]$label) {
    $info = Get-RunVendorVolume $name
    if ($null -eq $info) { return }
    if ($info.Name -ne $name -or $info.Labels.'ather.phase2a.vendor' -ne $label) {
        throw 'Disposable dependency volume ownership did not match this run.'
    }
    & docker volume rm $name 1>$null 2>$null
    $removeExit = $LASTEXITCODE
    if ($removeExit -ne 0 -and $null -ne (Get-RunVendorVolume $name)) {
        throw "Could not remove run-owned dependency volume (docker exit $removeExit)."
    }
}

function Initialize-RunVendorFixture([string]$sourceRoot, [string]$volumeName, [string]$label) {
    $manifest = Join-Path $sourceRoot 'composer.json'
    $lock = Join-Path $sourceRoot 'composer.lock'
    if (-not (Test-Path -LiteralPath $manifest) -or -not (Test-Path -LiteralPath $lock)) {
        throw 'Isolated Composer manifest or lock is missing.'
    }
    $manifestHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $manifest).Hash
    $lockHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $lock).Hash
    if ($null -ne (Get-RunVendorVolume $volumeName)) { throw 'Disposable dependency volume name was already in use.' }
    $volumeOutput = Invoke-CheckedDocker 'volume-create' @('volume','create','--label',("ather.phase2a.vendor="+$label),$volumeName) $volumeName
    if ($volumeOutput -cne $volumeName) { throw 'Disposable dependency volume-create returned an unexpected name.' }
    $composerArgs = @('run','--rm','--network','bridge','--label',("ather.phase2a.fixture="+$label),'-v',($manifest+':/fixture/composer.json:ro'),'-v',($lock+':/fixture/composer.lock:ro'),'-v',($volumeName+':/fixture/vendor'),'-w','/fixture','--entrypoint','composer','composer:2')
    $null = Invoke-CheckedDocker 'validate' ($composerArgs + @('validate','--no-check-publish'))
    $null = Invoke-CheckedDocker 'install' ($composerArgs + @('install','--no-dev','--prefer-dist','--no-interaction','--optimize-autoloader'))
    $null = Invoke-CheckedDocker 'platform-composer' ($composerArgs + @('check-platform-reqs','--no-dev'))
    $verifyCode = 'try{$l=json_decode(file_get_contents("/fixture/composer.lock"),true,512,JSON_THROW_ON_ERROR);$i=json_decode(file_get_contents("/fixture/vendor/composer/installed.json"),true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){exit(9);}if(!is_array($l)||!isset($l["packages"])||!is_array($l["packages"])||!is_array($i)){exit(9);}$i=$i["packages"]??$i;if(!is_array($i)){exit(9);}$a=[];$b=[];foreach($l["packages"] as $p){if(!is_array($p)||!isset($p["name"],$p["version"])||!is_string($p["name"])||!is_string($p["version"])){exit(9);}$a[$p["name"]]=$p["version"];}foreach($i as $p){if(!is_array($p)||!isset($p["name"],$p["version"])||!is_string($p["name"])||!is_string($p["version"])){exit(9);}$b[$p["name"]]=$p["version"];}ksort($a);ksort($b);if($a!==$b||!is_readable("/fixture/vendor/autoload.php")){exit(5);}echo count($a);'
    $verifyArgs = @('run','--rm','--network','none','--label',("ather.phase2a.fixture="+$label),'-v',($manifest+':/fixture/composer.json:ro'),'-v',($lock+':/fixture/composer.lock:ro'),'-v',($volumeName+':/fixture/vendor:ro'),'-w','/fixture','--entrypoint','php','composer:2','-r',$verifyCode)
    $packageCount = Invoke-CheckedDocker 'locked-packages' $verifyArgs
    if ($packageCount -cne '26') { throw 'Disposable dependency locked-packages expected 26 matching packages.' }
    $runtimeCode = 'foreach(["openssl","json","mbstring","pdo_mysql"] as $e){if(!extension_loaded($e)){exit(6);}}if(!is_readable("/var/www/html/vendor/autoload.php")){exit(7);}require "/var/www/html/vendor/autoload.php";if(!class_exists("GuzzleHttp\\Client")||!class_exists("Auth0\\SDK\\Configuration\\SdkConfiguration")){exit(8);}echo "PHP83_READY";'
    $runtimeArgs = @('run','--rm','--network','none','--user','33:33','--label',("ather.phase2a.fixture="+$label),'-v',($volumeName+':/var/www/html/vendor:ro'),'-w','/var/www/html','--entrypoint','php','portfolio_course-web','-r',$runtimeCode)
    $runtime = Invoke-CheckedDocker 'platform-php83' $runtimeArgs
    if ($runtime -cne 'PHP83_READY') { throw 'Disposable dependency platform-php83 autoload probe failed.' }
    if ((Get-FileHash -Algorithm SHA256 -LiteralPath $manifest).Hash -ne $manifestHash -or (Get-FileHash -Algorithm SHA256 -LiteralPath $lock).Hash -ne $lockHash) {
        throw 'Isolated Composer manifest or lock changed during provisioning.'
    }
    Write-Output "DISPOSABLE_VENDOR_LOCKED_PACKAGES=$packageCount PHP83_READY=yes"
}

function Assert-RunTempPath([string]$path, [string]$expectedName) {
    $tempPrefix = [IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
    $actual = [IO.Path]::GetFullPath($path)
    $expected = [IO.Path]::GetFullPath((Join-Path $env:TEMP $expectedName))
    if (-not $actual.StartsWith($tempPrefix, [StringComparison]::OrdinalIgnoreCase) -or
        -not $actual.Equals($expected, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Disposable web source path was not the exact run-owned temporary path.'
    }
    return $actual
}

function Initialize-RunWebRoot([string]$sourceRoot, [string]$webRootPath, [string]$archiveFile) {
    $safeRoot = Assert-RunTempPath $webRootPath "ather-phase2a-webroot-$runId"
    $safeArchive = Assert-RunTempPath $archiveFile "ather-phase2a-source-$runId.tar"
    if ((Test-Path -LiteralPath $safeRoot) -or (Test-Path -LiteralPath $safeArchive)) {
        throw 'Disposable web source path was already in use.'
    }
    New-Item -ItemType Directory -Path $safeRoot | Out-Null
    $gitError = $safeArchive + '.git-err'
    $tarError = $safeArchive + '.tar-err'
    try {
        & git -C $sourceRoot archive --format=tar --output=$safeArchive HEAD 2> $gitError
        $gitExit = $LASTEXITCODE
        if ($gitExit -ne 0) {
            $raw = if (Test-Path -LiteralPath $gitError) { Get-Content -LiteralPath $gitError -Raw } else { $null }
            throw "Disposable web source archive failed (git exit $gitExit): $(ConvertTo-SafeDiagnostic $raw)"
        }
        & tar -xf $safeArchive -C $safeRoot 2> $tarError
        $tarExit = $LASTEXITCODE
        if ($tarExit -ne 0) {
            $raw = if (Test-Path -LiteralPath $tarError) { Get-Content -LiteralPath $tarError -Raw } else { $null }
            throw "Disposable web source extraction failed (tar exit $tarExit): $(ConvertTo-SafeDiagnostic $raw)"
        }
        # Test the review candidate before its local commit. Only fixed runtime
        # paths may override the approved HEAD archive in this run-owned copy.
        $candidateFiles = @(
            'includes/auth0_oidc.php', 'includes/observability.php',
            'includes/owner_layout.php', 'includes/owner_session.php',
            'includes/session.php', 'owner_login.php', 'owner_onboarding.php',
            'owner_switch_account.php', 'public/owner_switch_account.php'
        )
        foreach ($relative in $candidateFiles) {
            $original = Join-Path $sourceRoot $relative
            $staged = Join-Path $safeRoot $relative
            if (-not (Test-Path -LiteralPath $original -PathType Leaf)) { throw 'Candidate runtime source is missing.' }
            Copy-Item -LiteralPath $original -Destination $staged -Force
        }
        $vendorMountpoint = Join-Path $safeRoot 'vendor'
        if (Test-Path -LiteralPath $vendorMountpoint) { throw 'Disposable web source already contained a vendor path.' }
        New-Item -ItemType Directory -Path $vendorMountpoint | Out-Null
        foreach ($relative in @('owner_oidc_callback.php', 'scripts/ci-oidc-discovery-mock.php', 'composer.lock') + $candidateFiles) {
            $original = Join-Path $sourceRoot $relative
            $staged = Join-Path $safeRoot $relative
            if (-not (Test-Path -LiteralPath $staged -PathType Leaf) -or
                (Get-FileHash -LiteralPath $original -Algorithm SHA256).Hash -ne (Get-FileHash -LiteralPath $staged -Algorithm SHA256).Hash) {
                throw 'Disposable web source did not match isolated candidate.'
            }
        }
        Write-Output 'DISPOSABLE_WEB_SOURCE=verified'
    } finally {
        foreach ($path in @($safeArchive, $gitError, $tarError)) {
            if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Force }
        }
    }
}

function Remove-RunWebRoot([string]$path) {
    $safeRoot = Assert-RunTempPath $path "ather-phase2a-webroot-$runId"
    if (Test-Path -LiteralPath $safeRoot) { Remove-Item -LiteralPath $safeRoot -Recurse -Force }
}

function Assert-OwnerLoginStatus([string]$status, [int]$curlExit, [string]$contentType, [string]$reason, [string]$locationSummary) {
    $safeType = if ($contentType -match '^[A-Za-z0-9/+.;= -]{1,100}$') { $contentType } else { '[REDACTED]' }
    $safeReason = if ($reason -in @('empty','sign_in_unavailable','rate_limited')) { $reason } else { 'redacted_body' }
    Write-Output "OWNER_LOGIN_STATUS=$status CURL_EXIT=$curlExit CONTENT_TYPE=$safeType REASON=$safeReason LOCATION=$locationSummary"
    if ($curlExit -ne 0) { throw "Disposable OIDC start HTTP request failed (curl exit $curlExit)." }
    if ($status -ne '302') { throw "OIDC start expected 302, received $status (curl exit $curlExit; reason $safeReason)." }
}

function Invoke-Callback([string]$query, [int]$expected) {
    & curl.exe --silent --show-error --dump-header $headers --output NUL `
        --cookie $cookies --max-redirs 0 "http://127.0.0.1:${port}/owner_oidc_callback.php?$query"
    if ($LASTEXITCODE -ne 0) { throw 'Disposable callback HTTP request failed.' }
    $callbackLines = if (Test-Path -LiteralPath $headers) { @(Get-Content -LiteralPath $headers) } else { @() }
    $statuses = @($callbackLines | Where-Object { $_ -match '^HTTP/' })
    if ($statuses.Count -eq 0) { throw 'Disposable callback returned no HTTP status.' }
    $status = $statuses[-1]
    if ($status -notmatch " $expected ") { throw 'Disposable callback returned an unexpected status.' }
}

$setup = ConvertTo-LfShellPayload $setup
Invoke-WithRunCleanup {
    Initialize-RunVendorFixture $root $vendorVolume $runId
    Initialize-RunWebRoot $root $webRoot $archivePath
    $dockerArgs = @('run','-d','--name',$container,'--label',("ather.phase2a.http="+$runId),'-p',("127.0.0.1:"+$port+":8080"),'-v',($webRoot+':/var/www/html:ro'),'-v',($vendorVolume+':/var/www/html/vendor:ro'),'-w','/var/www/html','-e','APP_ENV=production','-e','ATHERCAR_CI_OIDC_MOCK=1','-e','EXPECTED_OIDC_ISSUER=https://127.0.0.1:9443/','-e','OIDC_CLIENT_ID=synthetic-phase2a-client','-e','OIDC_CLIENT_SECRET=synthetic-phase2a-secret','-e','OIDC_REDIRECT_URI=https://phase2a.invalid/owner_oidc_callback.php','-e','SESSION_COOKIE_SECURE=false','-e','RATE_LIMIT_STATE_DIR=/tmp/ather-phase2a-rate','--entrypoint','sh','portfolio_course-web','-lc',$setup)
    & docker @dockerArgs 1> $startStdout 2> $startStderr
    $startExit = $LASTEXITCODE
    $startRaw = if (Test-Path -LiteralPath $startStdout) { Get-Content -LiteralPath $startStdout -Raw } else { $null }
    $script:containerId = if ([string]::IsNullOrWhiteSpace($startRaw)) { '' } else { $startRaw.Trim() }
    $stderr = if (Test-Path -LiteralPath $startStderr) { Get-Content -LiteralPath $startStderr -Raw } else { '' }
    Write-Output "DISPOSABLE_START_EXIT=$startExit STDERR=$(ConvertTo-SafeDiagnostic $stderr)"
    Assert-DockerStartup $startExit $stderr $script:containerId
    $ready = $false
    $healthStatus = 'none'
    $healthExit = -1
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        $healthRaw = & curl.exe --silent --output NUL --write-out '%{http_code}' --max-time 2 ("http://127.0.0.1:"+$port+"/public/health.php")
        $healthExit = $LASTEXITCODE
        $healthStatus = if ([string]::IsNullOrWhiteSpace($healthRaw)) { 'none' } else { $healthRaw.Trim() }
        if ($healthExit -eq 0 -and $healthStatus -eq '200') { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw "Disposable HTTP service did not become healthy (last curl exit $healthExit; status $healthStatus)." }
    $discoveryCode = '$raw=@file_get_contents("https://127.0.0.1:9443/.well-known/openid-configuration");if(!is_string($raw)){exit(2);}try{$j=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){exit(3);}if(!is_array($j)||($j["issuer"]??null)!=="https://127.0.0.1:9443/"||($j["authorization_endpoint"]??null)!=="https://127.0.0.1:9443/authorize"){exit(4);}echo "DISCOVERY_READY";'
    $discoveryReady = $false
    $discoveryExit = -1
    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        $probe = & docker exec $container php -r $discoveryCode 2> $discoveryErr
        $discoveryExit = $LASTEXITCODE
        if ($discoveryExit -eq 0 -and $probe -ceq 'DISCOVERY_READY') { $discoveryReady = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $discoveryReady) {
        $raw = if (Test-Path -LiteralPath $discoveryErr) { Get-Content -LiteralPath $discoveryErr -Raw } else { $null }
        throw "Synthetic OIDC discovery did not become ready (last docker exit $discoveryExit): $(ConvertTo-SafeDiagnostic $raw)"
    }
    Write-Output 'DISPOSABLE_HEALTH=200 DISCOVERY_READY=yes'
    $switchUrl = "http://127.0.0.1:$port/owner_switch_account.php"
    $switchGet = & curl.exe --silent --show-error --output NUL --write-out '%{http_code}' --max-redirs 0 $switchUrl
    if ($LASTEXITCODE -ne 0 -or $switchGet -ne '405') { throw 'Account selection GET did not return 405.' }
    $switchWithoutCsrf = & curl.exe --silent --show-error --output NUL --write-out '%{http_code}' --max-redirs 0 --request POST --data '' $switchUrl
    if ($LASTEXITCODE -ne 0 -or $switchWithoutCsrf -ne '403') { throw 'Account selection POST without CSRF did not return 403.' }
    Write-Output 'ACCOUNT_SELECTION_GET=405 POST_WITHOUT_CSRF=403'
    & curl.exe --silent --show-error --dump-header $headers --output $loginBody --cookie-jar $cookies --max-redirs 0 ("http://127.0.0.1:"+$port+"/owner_login.php")
    $loginCurlExit = $LASTEXITCODE
    $lines = if (Test-Path -LiteralPath $headers) { @(Get-Content -LiteralPath $headers) } else { @() }
    $statuses = @($lines | Where-Object { $_ -match '^HTTP/' })
    $actualStatus = if ($statuses.Count -gt 0 -and $statuses[-1] -match '^HTTP/\S+\s+(\d{3})') { $Matches[1] } else { 'none' }
    $contentTypes = @($lines | Where-Object { $_ -match '(?i)^Content-Type:' })
    $contentType = if ($contentTypes.Count -gt 0) { ($contentTypes[-1] -split ':', 2)[1].Trim() } else { 'absent' }
    $bodyRaw = if (Test-Path -LiteralPath $loginBody) { Get-Content -LiteralPath $loginBody -Raw } else { $null }
    $responseBody = if ([string]::IsNullOrWhiteSpace($bodyRaw)) { '' } else { $bodyRaw.Trim() }
    $reason = if ($responseBody -eq '') { 'empty' } elseif ($responseBody -eq 'Sign-in is temporarily unavailable.') { 'sign_in_unavailable' } elseif ($responseBody -eq 'Please try again later.') { 'rate_limited' } else { 'redacted_body' }
    $locations = @($lines | Where-Object { $_ -match '(?i)^Location:' })
    $locationSummary = 'absent'
    $requiredNames = @('client_id','response_type','redirect_uri','scope','state','nonce','code_challenge','code_challenge_method')
    if ($locations.Count -gt 0) {
        $locationValue = $locations[-1].Substring(9).Trim()
        try {
            $locationUri = [uri]$locationValue
            $parameterNames = @($locationUri.Query.TrimStart('?') -split '&' | Where-Object { $_ } | ForEach-Object { [uri]::UnescapeDataString(($_ -split '=', 2)[0]) } | Sort-Object -Unique)
            $safeNames = @($parameterNames | ForEach-Object { if ($_ -match '^[a-z_]{1,40}$') { $_ } else { '[REDACTED]' } })
            $locationSummary = $locationUri.Scheme + '://' + $locationUri.Host + $locationUri.AbsolutePath + '?names=' + ($safeNames -join ',')
        } catch { $locationSummary = 'invalid_location' }
    }
    Assert-OwnerLoginStatus $actualStatus $loginCurlExit $contentType $reason $locationSummary
    if ($locations.Count -eq 0 -or $locationSummary -eq 'invalid_location') { throw 'OIDC start redirect lacked a valid Location.' }
    foreach ($required in $requiredNames) {
        if ($parameterNames -notcontains $required) { throw 'OIDC authorization redirect lacks a required parameter.' }
    }
    $location = @($lines | Where-Object { $_ -match '^Location:' })[-1].Substring(9).Trim()
    $validLocation = $location -match '^https://127\.0\.0\.1:9443/authorize\?'
    $validLocation = $validLocation -and ($location -match '[?&]code_challenge_method=S256(?:&|$)')
    $validLocation = $validLocation -and ($location -match '[?&]nonce=[^&]+')
    $validLocation = $validLocation -and ($location -match '[?&]state=([^&]+)')
    if (-not $validLocation) { throw 'OIDC authorization redirect lacks required controls.' }
    $state = [uri]::UnescapeDataString($Matches[1])
    Invoke-Callback 'state=wrong&code=synthetic' 403
    Invoke-Callback ('state=' + [uri]::EscapeDataString($state) + '&code=synthetic') 403
    Invoke-Callback ('state=' + [uri]::EscapeDataString($state) + '&code=synthetic') 403
} { Write-RunDiagnostics $container } {
    $cleanupFailure = $null
    try { Remove-RunContainer $container $runId $script:containerId } catch { $cleanupFailure = $_.Exception.Message }
    try { Remove-RunVendorVolume $vendorVolume $runId } catch { if (-not $cleanupFailure) { $cleanupFailure = $_.Exception.Message } }
    try { Remove-RunWebRoot $webRoot } catch { if (-not $cleanupFailure) { $cleanupFailure = $_.Exception.Message } }
    foreach ($path in @($headers, $cookies, $startStdout, $startStderr, $loginBody, $discoveryErr)) {
        try {
            if ($path.StartsWith(($env:TEMP.TrimEnd('\') + '\'), [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $path)) {
                Remove-Item -LiteralPath $path -Force
            }
        } catch {
            if (-not $cleanupFailure) { $cleanupFailure = $_.Exception.Message }
        }
    }
    if ($cleanupFailure) { throw (ConvertTo-SafeDiagnostic $cleanupFailure) }
}
Write-Output 'PASS PHASE2A-ISOLATED-OIDC-HTTP-START-STATE-ERROR-REPLAY'
