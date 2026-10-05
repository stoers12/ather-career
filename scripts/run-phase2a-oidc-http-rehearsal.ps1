Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$runId = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(6)).ToLowerInvariant()
$container = "ather-phase2a-http-$runId"
$listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
try { $listener.Start(); $port = ([System.Net.IPEndPoint]$listener.LocalEndpoint).Port } finally { $listener.Stop() }
$headers = Join-Path $env:TEMP "ather-phase2a-headers-$runId"
$cookies = Join-Path $env:TEMP "ather-phase2a-cookies-$runId"
$started = $false

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

function Invoke-Callback([string]$query, [int]$expected) {
    & curl.exe --silent --show-error --dump-header $headers --output NUL `
        --cookie $cookies --max-redirs 0 "http://127.0.0.1:${port}/owner_oidc_callback.php?$query"
    if ($LASTEXITCODE -ne 0) { throw 'Disposable callback HTTP request failed.' }
    $status = @(Get-Content -LiteralPath $headers | Where-Object { $_ -match '^HTTP/' })[-1]
    if ($status -notmatch " $expected ") { throw 'Disposable callback returned an unexpected status.' }
}

try {
    $id = & docker run -d --rm --name $container --label "ather.phase2a.http=$runId" `
        -p "127.0.0.1:${port}:8080" -v ($root + ':/var/www/html:ro') -w /var/www/html `
        -e APP_ENV=production -e ATHERCAR_CI_OIDC_MOCK=1 `
        -e EXPECTED_OIDC_ISSUER=https://127.0.0.1:9443/ `
        -e OIDC_CLIENT_ID=synthetic-phase2a-client -e OIDC_CLIENT_SECRET=synthetic-phase2a-secret `
        -e OIDC_REDIRECT_URI=https://phase2a.invalid/owner_oidc_callback.php `
        -e SESSION_COOKIE_SECURE=false -e RATE_LIMIT_STATE_DIR=/tmp/ather-phase2a-rate `
        --entrypoint sh portfolio_course-web -lc $setup
    if ($LASTEXITCODE -ne 0) { throw 'Disposable HTTP container failed to start.' }
    $started = $true
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        & curl.exe --silent --output NUL --max-time 2 "http://127.0.0.1:${port}/health.php"
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'Disposable HTTP service did not become ready.' }
    & curl.exe --silent --show-error --dump-header $headers --output NUL `
        --cookie-jar $cookies --max-redirs 0 "http://127.0.0.1:${port}/owner_login.php"
    if ($LASTEXITCODE -ne 0) { throw 'Disposable OIDC start HTTP request failed.' }
    $lines = Get-Content -LiteralPath $headers
    if (@($lines | Where-Object { $_ -match '^HTTP/' })[-1] -notmatch ' 302 ') { throw 'OIDC start did not redirect.' }
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
    Write-Output 'PASS PHASE2A-ISOLATED-OIDC-HTTP-START-STATE-ERROR-REPLAY'
} finally {
    if ($started) {
        $info = (docker inspect $container 2>$null | ConvertFrom-Json)[0]
        if ($info -and $info.Config.Labels.'ather.phase2a.http' -eq $runId) {
            & docker stop $container | Out-Null
        }
    }
    foreach ($path in @($headers, $cookies)) {
        if ($path.StartsWith(($env:TEMP.TrimEnd('\') + '\'), [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $path)) {
            Remove-Item -LiteralPath $path -Force
        }
    }
}
