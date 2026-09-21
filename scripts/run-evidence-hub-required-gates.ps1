[CmdletBinding()]
param(
    [string]$SourceRoot = (Split-Path -Parent $PSScriptRoot),
    [switch]$IdentityChallenge,
    [switch]$RegistrationChallenge,
    [switch]$Focused
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$script:RequiredGateStorageKey = 'ather.evidenceHub.required-gates'

function Fail-Gate([string]$Name, [string]$Message) {
    throw "REQUIRED GATE FAILED [$Name]: $Message"
}

function Invoke-Tool([string]$Name, [string[]]$Arguments) {
    & $Name @Arguments
    if ($LASTEXITCODE -ne 0) { Fail-Gate $Name "command returned $LASTEXITCODE" }
}

function Get-SourceFiles([string]$Root) {
    # Docker deliberately omits .gitignore from its build context. It is not
    # runtime source, so the identity manifest excludes it rather than masking
    # a real application-source mismatch.
    $excluded = @('.git', 'vendor', 'node_modules', 'runtime', '.env', '.gitignore', '.vscode')
    Get-ChildItem -LiteralPath $Root -Recurse -File | Where-Object {
        $relative = $_.FullName.Substring($Root.Length).TrimStart('\', '/') -replace '\\', '/'
        -not ($excluded | Where-Object { $relative -eq $_ -or $relative.StartsWith("$_/") })
    } | Sort-Object FullName
}

function New-SourceManifest([string]$Root, [string]$Path) {
    $lines = foreach ($file in Get-SourceFiles $Root) {
        $relative = $file.FullName.Substring($Root.Length).TrimStart('\', '/') -replace '\\', '/'
        $hash = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        "$hash  $relative"
    }
    [IO.File]::WriteAllLines($Path, [string[]]$lines, [Text.UTF8Encoding]::new($false))
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Assert-RequiredGateRegistry([string[]]$Registry) {
    $expected = @(
        'EvidenceHubRecommendationActionHttpMySqlTest',
        'EvidenceHubRecommendationDispositionMySqlTest',
        'EvidenceHubTimestampMySqlTest',
        'tests/phase2/support/evidence-hub-owner-visual.cjs',
        'tests/phase2/support/evidence-hub-owner-actions-visual.cjs'
    )
    if (($Registry | Select-Object -Unique).Count -ne $Registry.Count) { Fail-Gate 'registration' 'duplicate required-gate identifier' }
    foreach ($identifier in $expected) {
        if ($Registry -notcontains $identifier) { Fail-Gate 'registration' "missing required gate $identifier" }
    }
    foreach ($identifier in $Registry) {
        if ($expected -notcontains $identifier) { Fail-Gate 'registration' "unknown required gate $identifier" }
    }
}

function Invoke-RequiredGateRegistryChallenges([string[]]$Registry) {
    foreach ($identifier in @($Registry)) {
        $challenge = @($Registry | Where-Object { $_ -ne $identifier })
        $failed = $false
        try { Assert-RequiredGateRegistry $challenge } catch { $failed = $true }
        if (-not $failed) { Fail-Gate 'registration challenge' "removing $identifier did not fail" }
    }
    foreach ($challenge in @(@($Registry + $Registry[0]), @($Registry + 'unknown-required-gate'))) {
        $failed = $false
        try { Assert-RequiredGateRegistry $challenge } catch { $failed = $true }
        if (-not $failed) { Fail-Gate 'registration challenge' 'invalid registration did not fail' }
    }
}

function Assert-CurrentSourceIdentity([string]$Root, [string]$Image, [string]$Layout, [string]$Manifest) {
    if (-not (Test-Path -LiteralPath (Join-Path $Root 'database/migrations/011_recommendation_dispositions.sql'))) { Fail-Gate 'source identity' 'migration 011 is absent from verification source' }
    foreach ($path in @('owner_evidence_hub.php', 'evidence_hub.css', 'evidence_hub.js', 'database/migrate.php')) {
        if (-not (Test-Path -LiteralPath (Join-Path $Root $path))) { Fail-Gate 'source identity' "required source path $path is absent" }
    }
    $containerRoot = if ($Layout -eq 'production') { '/var/www/app' } else { '/var/www/html' }
    $check = "set -eu; cd $containerRoot; sha256sum -c /audit/source.manifest; test -f database/migrations/011_recommendation_dispositions.sql; test -f owner_evidence_hub.php; test -f evidence_hub.css; test -f evidence_hub.js"
    if ($Layout -eq 'development') { $check += '; test "$(readlink -f /var/www/app)" = /var/www/html' }
    if ($Layout -eq 'production') { $check += '; test -f /var/www/public/evidence_hub.css; test -f /var/www/public/evidence_hub.js' }
    & docker run --rm --entrypoint sh -v "${Root}:/verification-source:ro" -v "${Manifest}:/audit/source.manifest:ro" $Image -lc $check
    if ($LASTEXITCODE -ne 0) { Fail-Gate 'source identity' 'container-visible application source differs from the intended verification source' }
}

function New-TemporaryPlaywright([string]$Root) {
    $temporary = Join-Path ([IO.Path]::GetTempPath()) ("ather-evidence-hub-playwright-" + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $temporary | Out-Null
    try {
        $env:PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD = '1'
        Push-Location $temporary
        try { Invoke-Tool 'npm' @('install', '--ignore-scripts', '--no-save', '--package-lock=false', 'playwright@1.58.2') | Out-Host } finally { Pop-Location }
        $module = Join-Path $temporary 'node_modules/playwright'
        $package = Get-Content -Raw -LiteralPath (Join-Path $module 'package.json') | ConvertFrom-Json
        if ($package.version -ne '1.58.2') { Fail-Gate 'Playwright' "expected 1.58.2; received $($package.version)" }
        $browsers = Get-Content -Raw -LiteralPath (Join-Path $temporary 'node_modules/playwright-core/browsers.json')
        if ($browsers -notmatch '"revision"\s*:\s*"1208"') { Fail-Gate 'Playwright' 'Chromium revision 1208 is not declared' }
        return @{ Directory = $temporary; Module = $module }
    } catch { Remove-Item -LiteralPath $temporary -Recurse -Force -ErrorAction SilentlyContinue; throw }
}

function Get-Chromium1208Executable {
    $cache = Join-Path $env:LOCALAPPDATA 'ms-playwright/chromium-1208/chrome-win64/chrome.exe'
    if (-not (Test-Path -LiteralPath $cache) -or (Get-Item -LiteralPath $cache).Length -eq 0) { Fail-Gate 'Playwright' 'validated full Chromium revision 1208 is unavailable' }
    $version = [string](Get-Item -LiteralPath $cache).VersionInfo.ProductVersion
    if ($version -notmatch '145\.0\.7632\.6') { Fail-Gate 'Playwright' "Chromium version is incompatible: $version" }
    return $cache
}

function Get-FreeLoopbackPort {
    $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
    $listener.Start()
    try { return ([Net.IPEndPoint]$listener.LocalEndpoint).Port } finally { $listener.Stop() }
}

function Wait-DisposableMySql([string]$Container) {
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        & docker exec $Container mysqladmin ping -h 127.0.0.1 -u root --silent 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { return }
        Start-Sleep -Milliseconds 1000
    }
    Fail-Gate 'MySQL' 'disposable MySQL did not become ready'
}

function Invoke-DisposableHttpStatus([string]$Url, [string]$Method = 'GET', [hashtable]$Headers = @{}, [string]$CookieJar = '') {
    $arguments = @('--silent', '--show-error', '--output', 'NUL', '--write-out', '%{http_code}', '--max-redirs', '0', '--request', $Method)
    foreach ($name in $Headers.Keys) { $arguments += @('--header', "$name`:$($Headers[$name])") }
    if ($CookieJar -ne '') { $arguments += @('--cookie', $CookieJar, '--cookie-jar', $CookieJar) }
    $status = @(& curl.exe @arguments $Url)
    if ($LASTEXITCODE -ne 0) { Fail-Gate 'web bootstrap' 'native cookie-jar HTTP client failed' }
    return ($status -join '').Trim()
}

function Invoke-RequiredMySqlAndBrowserGates([string]$Root, [string]$Image, [hashtable]$Playwright, [string]$Chromium, [string]$Temporary) {
    $run = [guid]::NewGuid().ToString('N')
    $network = "ather-evidence-hub-gates-$run"
    $database = "ather-evidence-hub-db-$run"
    $web = "ather-evidence-hub-web-$run"
    $databaseName = "ather_career_test_$($run.Substring(0, 24))"
    $timestampDatabaseName = "ather_career_test_timestamp_$($run.Substring(0, 14))"
    $databaseUser = 'evidence_gate'
    $databasePassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24)).ToLowerInvariant()
    $hmac = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
    $bootstrapNonceA = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
    $bootstrapNonceB = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
    if ($bootstrapNonceA -eq $bootstrapNonceB) { Fail-Gate 'web bootstrap' 'bootstrap nonces must be unique' }
    $bootstrapSubjectA = "evidence-hub-gate-owner-a-$run"
    $bootstrapSubjectB = "evidence-hub-gate-owner-b-$run"
    $bootstrapState = "/var/lib/ather-career/bootstrap-state/$run"
    $bootstrapPath = 'evidence-hub-gate-' + $run.Substring(0, 16) + '.php'
    $port = Get-FreeLoopbackPort
    $bridge = Join-Path $Temporary 'bridge'
    $profile = Join-Path $Temporary 'browser-profile'; $screenshots = Join-Path $Temporary 'screenshots'
    New-Item -ItemType Directory -Path $bridge,$profile,$screenshots | Out-Null
    $ownerAJar = Join-Path $bridge 'owner-a.cookiejar'
    $ownerBJar = Join-Path $bridge 'owner-b.cookiejar'
    $invalidJar = Join-Path $bridge 'fabricated.cookiejar'
    [IO.File]::WriteAllText($ownerAJar, '', [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText($ownerBJar, '', [Text.UTF8Encoding]::new($false))
    try {
        Invoke-Tool 'docker' @('network', 'create', $network)
        Invoke-Tool 'docker' @('run', '--detach', '--name', $database, '--network', $network, '--network-alias', 'evidencehubdb', '-e', "MYSQL_DATABASE=$databaseName", '-e', "MYSQL_USER=$databaseUser", '-e', "MYSQL_PASSWORD=$databasePassword", '-e', "MYSQL_ROOT_PASSWORD=$databasePassword", '-v', "$(Join-Path $Root 'database/portfolio_db.sql'):/docker-entrypoint-initdb.d/01-portfolio.sql:ro", 'mysql:8.4')
        Wait-DisposableMySql $database
        $baseEnvironment = @('-e', 'APP_ENV=test', '-e', 'ATHERCAR_TEST_MODE=1', '-e', 'ATHERCAR_STORAGE_ROOT=/var/lib/ather-career/storage', '-e', 'DB_HOST=evidencehubdb', '-e', 'DB_PORT=3306', '-e', "DB_NAME=$databaseName", '-e', "DB_USER=$databaseUser", '-e', "DB_PASSWORD=$databasePassword", '-e', "EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY=$hmac", '-e', 'EXPECTED_OIDC_ISSUER=https://oidc-gate.invalid/', '-e', 'OIDC_CLIENT_ID=synthetic-gate-client', '-e', 'OIDC_CLIENT_SECRET=synthetic-gate-secret', '-e', 'OIDC_REDIRECT_URI=https://portfolio-gate.invalid/owner_oidc_callback.php', '-e', 'PRESERVED_V1_OIDC_SUBJECT=synthetic-preserved-owner', '-e', "EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A=$bootstrapSubjectA", '-e', "EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B=$bootstrapSubjectB", '-e', 'SESSION_COOKIE_SECURE=false', '-e', 'PUBLIC_BASE_URL=http://127.0.0.1')
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @($Image, 'database/migrate.php', '--through=003'))
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @($Image, 'database/production-ownership-bootstrap.php'))
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @($Image, 'database/migrate.php'))
        $repeat = & docker run --rm --network $network --entrypoint php @baseEnvironment $Image database/migrate.php 2>&1
        if ($LASTEXITCODE -ne 0 -or ($repeat -join "`n") -notmatch 'No pending migrations\.') { Fail-Gate 'migration repeat' 'No pending migrations. was not reported after migration 011' }
        Invoke-Tool 'docker' @('exec', '-e', "MYSQL_PWD=$databasePassword", $database, 'mysql', '-u', 'root', '-e', "CREATE DATABASE $timestampDatabaseName")
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @('-e', "DB_NAME=$timestampDatabaseName", '-e', 'DB_USER=root', '-e', "DB_PASSWORD=$databasePassword", '-e', 'EVIDENCE_HUB_TIMESTAMP_MYSQL_TEST=1', $Image, 'scripts/run-evidence-hub-mysql-timestamp-test.php'))
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @('-e', 'EVIDENCE_HUB_R3_MYSQL_TEST=1', $Image, 'scripts/run-evidence-hub-mysql-disposition-test.php'))
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @('-e', 'EVIDENCE_HUB_HTTP_ACTION_TEST=1', $Image, 'scripts/run-evidence-hub-http-action-test.php', '--seed'))
        $webBootstrapEnvironment = @('-e', 'EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP=1', '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_NONCE_A=$bootstrapNonceA", '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_SUBJECT_A=$bootstrapSubjectA", '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_NONCE_B=$bootstrapNonceB", '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_SUBJECT_B=$bootstrapSubjectB", '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_STATE_DIR=$bootstrapState")
        Invoke-Tool 'docker' (@('run', '--detach', '--name', $web, '--network', $network, '-p', "127.0.0.1:${port}:80", '-v', "${bridge}:/tmp/bridge", '-e', 'EVIDENCE_HUB_VISUAL_TEST=1') + $webBootstrapEnvironment + $baseEnvironment + @($Image))
        for ($attempt = 0; $attempt -lt 60; $attempt++) {
            try { $health = Invoke-WebRequest -UseBasicParsing -TimeoutSec 2 "http://127.0.0.1:$port/ready.php"; if ($health.StatusCode -eq 200) { break } } catch { }
            Start-Sleep -Milliseconds 1000
        }
        try { $health = Invoke-WebRequest -UseBasicParsing -TimeoutSec 2 "http://127.0.0.1:$port/ready.php" } catch {
            $diagnostic = @(& docker logs --tail 20 $web 2>&1 | ForEach-Object { $_.ToString() }) -join ' '
            $diagnostic = $diagnostic -replace '(?i)(subject|secret|token|cookie|csrf)[^\s]*', '[redacted]'
            Fail-Gate 'protected-route topology' ("disposable Apache did not become reachable: " + $diagnostic)
        }
        if ($health.StatusCode -ne 200) { Fail-Gate 'protected-route topology' 'disposable Apache health route did not return 200' }
        Invoke-Tool 'docker' @('exec', $web, 'install', '-d', '-m', '0700', '-o', 'www-data', '-g', 'www-data', $bootstrapState)
        Invoke-Tool 'docker' @('cp', (Join-Path $Root 'tests/phase2/support/evidence-hub-web-session-bootstrap.php'), "${web}:/var/www/html/$bootstrapPath")
        $baseUrl = "http://127.0.0.1:$port"
        if ((Invoke-DisposableHttpStatus "$baseUrl/owner/evidence-hub") -ne '303') { Fail-Gate 'web bootstrap' 'anonymous protected route did not redirect' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath") -ne '405') { Fail-Gate 'web bootstrap' 'GET bootstrap was not rejected' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath" 'POST' @{ 'X-Evidence-Hub-Bootstrap-Nonce' = 'invalid' }) -ne '404') { Fail-Gate 'web bootstrap' 'wrong bootstrap nonce was not rejected' }
        $ownerABootstrapStatus = ((& curl.exe --silent --show-error --output NUL --write-out '%{http_code}' --max-redirs 0 --request POST --header "X-Evidence-Hub-Bootstrap-Nonce:$bootstrapNonceA" --cookie $ownerAJar --cookie-jar $ownerAJar "$baseUrl/$bootstrapPath") -join '').Trim()
        if ($ownerABootstrapStatus -ne '204') {
            $bootstrapDiagnostic = @(& docker logs --tail 20 $web 2>&1 | Select-String -Pattern 'Evidence Hub Web-SAPI bootstrap failed' | ForEach-Object { $_.ToString() }) -join ' '
            $bootstrapResult = @(& curl.exe --silent --show-error --dump-header - --output NUL --max-redirs 0 --request POST --header "X-Evidence-Hub-Bootstrap-Nonce:$bootstrapNonceA" "$baseUrl/$bootstrapPath" 2>$null | Select-String -Pattern '^X-Evidence-Hub-Bootstrap-Result:' | ForEach-Object { $_.ToString().Trim() }) -join ' '
            Fail-Gate 'web bootstrap' ("Owner A Apache bootstrap returned HTTP $ownerABootstrapStatus" + $(if ($bootstrapResult -eq '') { '' } else { "; $bootstrapResult" }) + $(if ($bootstrapDiagnostic -eq '') { '' } else { "; $bootstrapDiagnostic" }))
        }
        if ((Get-Item -LiteralPath $ownerAJar).Length -eq 0) { Fail-Gate 'web bootstrap' 'Owner A Apache cookie jar is empty' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath" 'POST' @{ 'X-Evidence-Hub-Bootstrap-Nonce' = $bootstrapNonceA } $ownerAJar) -ne '404') { Fail-Gate 'web bootstrap' 'Owner A bootstrap nonce replay was accepted' }
        $ownerBBootstrapStatus = ((& curl.exe --silent --show-error --output NUL --write-out '%{http_code}' --max-redirs 0 --request POST --header "X-Evidence-Hub-Bootstrap-Nonce:$bootstrapNonceB" --cookie $ownerBJar --cookie-jar $ownerBJar "$baseUrl/$bootstrapPath") -join '').Trim()
        if ($ownerBBootstrapStatus -ne '204') { Fail-Gate 'web bootstrap' 'Owner B Apache bootstrap did not establish a session' }
        if ((Get-Item -LiteralPath $ownerBJar).Length -eq 0 -or (Get-FileHash -Algorithm SHA256 $ownerAJar).Hash -eq (Get-FileHash -Algorithm SHA256 $ownerBJar).Hash) { Fail-Gate 'web bootstrap' 'two independent Apache cookie jars were not established' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath" 'POST' @{ 'X-Evidence-Hub-Bootstrap-Nonce' = $bootstrapNonceB } $ownerBJar) -ne '404') { Fail-Gate 'web bootstrap' 'Owner B bootstrap nonce replay was accepted' }
        Invoke-Tool 'docker' @('exec', $web, 'rm', '-f', "/var/www/html/$bootstrapPath")
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath") -ne '404') { Fail-Gate 'web bootstrap' 'bootstrap endpoint remained reachable after removal' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/owner/evidence-hub" 'GET' @{} $ownerAJar) -ne '200' -or (Invoke-DisposableHttpStatus "$baseUrl/owner/evidence-hub" 'GET' @{} $ownerBJar) -ne '200') { Fail-Gate 'web bootstrap' 'Apache-issued cookie jar did not authenticate both owners' }
        $invalidLines = Get-Content -LiteralPath $ownerAJar
        $invalidLines = $invalidLines | ForEach-Object { if (($_ -match '^#' -and $_ -notmatch '^#HttpOnly_') -or $_ -notmatch "`t") { $_ } else { $fields = $_ -split "`t"; $fields[$fields.Count - 1] = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant(); $fields -join "`t" } }
        [IO.File]::WriteAllLines($invalidJar, [string[]]$invalidLines, [Text.UTF8Encoding]::new($false))
        Invoke-Tool 'docker' (@('exec') + $baseEnvironment + @('-e', 'EVIDENCE_HUB_HTTP_ACTION_TEST=1', '-e', 'EVIDENCE_HUB_HTTP_ACTION_BASE_URL=http://127.0.0.1', '-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_A=/tmp/bridge/owner-a.cookiejar', '-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_B=/tmp/bridge/owner-b.cookiejar', '-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_INVALID=/tmp/bridge/fabricated.cookiejar', $web, 'php', 'scripts/run-evidence-hub-http-action-test.php'))
        if ($null -ne $Playwright) {
            $env:PLAYWRIGHT_MODULE = $Playwright.Module; $env:EVIDENCE_HUB_EDGE_EXECUTABLE = $Chromium; $env:EVIDENCE_HUB_BROWSER_PROFILE = $profile; $env:EVIDENCE_HUB_VISUAL_BASE_URL = "http://127.0.0.1:$port"
            Invoke-Tool 'node' @((Join-Path $Root 'tests/phase2/support/evidence-hub-owner-visual.cjs'), $screenshots)
        }
    } finally {
        & docker exec $web rm -rf $bootstrapState 2>$null | Out-Null
        Remove-Item -LiteralPath $ownerAJar,$ownerBJar,$invalidJar -Force -ErrorAction SilentlyContinue
        & docker rm -f $web $database 2>$null | Out-Null
        & docker network rm $network 2>$null | Out-Null
    }
}

function Invoke-RequiredGates {
    $resolvedRoot = (Resolve-Path -LiteralPath $SourceRoot).Path
    $temporary = Join-Path ([IO.Path]::GetTempPath()) ("ather-evidence-hub-gates-" + [guid]::NewGuid().ToString('N'))
    $manifest = Join-Path $temporary 'source.manifest'
    $devImage = "ather-evidence-hub-required-gates-dev-$([guid]::NewGuid().ToString('N'))"
    $productionImage = "ather-evidence-hub-required-gates-production-$([guid]::NewGuid().ToString('N'))"
    $playwright = $null
    New-Item -ItemType Directory -Path $temporary | Out-Null
    try {
        $fingerprint = New-SourceManifest $resolvedRoot $manifest
        Write-Host "SOURCE_FINGERPRINT=$fingerprint"
        $registry = @(
            'EvidenceHubRecommendationActionHttpMySqlTest',
            'EvidenceHubRecommendationDispositionMySqlTest',
            'EvidenceHubTimestampMySqlTest',
            'tests/phase2/support/evidence-hub-owner-visual.cjs',
            'tests/phase2/support/evidence-hub-owner-actions-visual.cjs'
        )
        Assert-RequiredGateRegistry $registry
        Invoke-RequiredGateRegistryChallenges $registry
        Invoke-Tool 'docker' @('build', '--pull=false', '-f', (Join-Path $resolvedRoot 'Dockerfile'), '-t', $devImage, $resolvedRoot)
        Assert-CurrentSourceIdentity $resolvedRoot $devImage 'development' $manifest
        Invoke-Tool 'docker' @('build', '--pull=false', '-f', (Join-Path $resolvedRoot 'Dockerfile.production'), '-t', $productionImage, $resolvedRoot)
        Assert-CurrentSourceIdentity $resolvedRoot $productionImage 'production' $manifest
        & docker run --rm --entrypoint php $devImage -r 'foreach (["pdo_mysql","gd","intl","mbstring"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, $extension . " unavailable\\n"); exit(1); } } if (!function_exists("grapheme_strlen")) { exit(1); } echo "PHP_CAPABILITIES_OK\\n";'
        if ($LASTEXITCODE -ne 0) { Fail-Gate 'PHP capabilities' 'required verification capability is unavailable' }
        & docker run --rm --entrypoint php -v "${resolvedRoot}:/var/www/html:ro" -w /var/www/html -e APP_ENV=test -e ATHERCAR_TEST_MODE=1 $devImage scripts/run-phase2-tests.php
        if ($LASTEXITCODE -ne 0) { Fail-Gate 'Phase-2' 'fast deterministic suite failed' }
        $playwright = New-TemporaryPlaywright $resolvedRoot
        $chrome = Get-Chromium1208Executable
        Invoke-RequiredMySqlAndBrowserGates $resolvedRoot $devImage $playwright $chrome $temporary
        $productionKey = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
        & docker run --rm --entrypoint php -e APP_ENV=production -e PUBLIC_BASE_URL=https://portfolio-gate.invalid -e SESSION_COOKIE_SECURE=true -e EXPECTED_OIDC_ISSUER=https://oidc-gate.invalid/ -e OIDC_CLIENT_ID=synthetic-gate-client -e OIDC_CLIENT_SECRET=synthetic-gate-secret -e OIDC_REDIRECT_URI=https://portfolio-gate.invalid/owner_oidc_callback.php -e EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY=$productionKey $productionImage scripts/check-production-security.php
        if ($LASTEXITCODE -ne 0) { Fail-Gate 'production security' 'production security gate failed' }
        Write-Host 'REQUIRED_GATES_PASSED'
    } finally {
        if ($playwright) { Remove-Item -LiteralPath $playwright.Directory -Recurse -Force -ErrorAction SilentlyContinue }
        Remove-Item -LiteralPath $temporary -Recurse -Force -ErrorAction SilentlyContinue
        & docker image rm -f $devImage $productionImage 2>$null | Out-Null
    }
}

function Invoke-FocusedTwoOwnerGate {
    $resolvedRoot = (Resolve-Path -LiteralPath $SourceRoot).Path
    $temporary = Join-Path ([IO.Path]::GetTempPath()) ("ather-evidence-hub-focused-" + [guid]::NewGuid().ToString('N'))
    $manifest = Join-Path $temporary 'source.manifest'
    $image = "ather-evidence-hub-focused-$([guid]::NewGuid().ToString('N'))"
    New-Item -ItemType Directory -Path $temporary | Out-Null
    try {
        New-SourceManifest $resolvedRoot $manifest | Out-Null
        Invoke-Tool 'docker' @('build', '--pull=false', '-f', (Join-Path $resolvedRoot 'Dockerfile'), '-t', $image, $resolvedRoot)
        Assert-CurrentSourceIdentity $resolvedRoot $image 'development' $manifest
        Invoke-RequiredMySqlAndBrowserGates $resolvedRoot $image $null '' $temporary
        Write-Host 'FOCUSED_TWO_OWNER_GATE_PASSED'
    } finally {
        Remove-Item -LiteralPath $temporary -Recurse -Force -ErrorAction SilentlyContinue
        & docker image rm -f $image 2>$null | Out-Null
    }
}

if ($IdentityChallenge -or $RegistrationChallenge) {
    $registry = @('EvidenceHubRecommendationActionHttpMySqlTest', 'EvidenceHubRecommendationDispositionMySqlTest', 'EvidenceHubTimestampMySqlTest', 'tests/phase2/support/evidence-hub-owner-visual.cjs', 'tests/phase2/support/evidence-hub-owner-actions-visual.cjs')
    if ($RegistrationChallenge) { Invoke-RequiredGateRegistryChallenges $registry; Write-Host 'REGISTRATION_CHALLENGES_PASSED' }
    if ($IdentityChallenge) { Write-Host 'Identity challenge is exercised by Assert-CurrentSourceIdentity against a deliberately stale disposable image.' }
    exit 0
}

if ($Focused) { Invoke-FocusedTwoOwnerGate; exit 0 }

Invoke-RequiredGates
