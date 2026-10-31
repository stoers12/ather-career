[CmdletBinding()]
param(
    [ValidateSet('smoke', 'load', 'stress', 'spike')]
    [string]$Profile = 'smoke',
    # An existing production-target image. Defaults to the image the resident web container runs,
    # which is only read: this script never stops, removes or retags it.
    [string]$Image = '',
    [string]$OutputDirectory = '',
    [string]$K6Image = 'grafana/k6:0.57.0',
    # Also write every request sample (gzipped NDJSON) for per-URL analysis.
    [switch]$RawMetrics
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# Load tests run only against a disposable, production-shaped stack with one synthetic
# portfolio. The live stack, its database and the resident portfolio are never targeted.

function Assert-Load([bool]$Condition, [string]$Message) {
    if (-not $Condition) {
        throw "Load test assertion failed: $Message"
    }
}

function Invoke-Native([string]$Command, [string[]]$Arguments) {
    $output = & $Command @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        $safe = ($output | Out-String) -replace '(?i)(password|secret|token|credential|authorization)\S*', '$1=[REDACTED]'
        throw "$Command failed with exit code ${LASTEXITCODE}: $safe"
    }
    return ($output | Out-String)
}

function Get-RandomHex([int]$ByteCount) {
    $bytes = [byte[]]::new($ByteCount)
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    return -join ($bytes | ForEach-Object { $_.ToString('x2') })
}

function Get-UnusedLoopbackPort {
    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
    $listener.Start()
    try {
        return $listener.LocalEndpoint.Port
    } finally {
        $listener.Stop()
    }
}

function Invoke-Compose([string]$Project, [string[]]$Arguments) {
    return Invoke-Native 'docker' (@('compose', '--project-name', $Project, '--file', 'docker-compose.production.yml') + $Arguments)
}

function Wait-Healthy([string]$Container, [string]$Name) {
    for ($attempt = 0; $attempt -lt 90; $attempt++) {
        $state = (Invoke-Native 'docker' @('inspect', '--format', '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}', $Container)).Trim()
        if ($state -eq 'healthy') {
            return
        }
        Start-Sleep -Seconds 2
    }
    throw "The disposable $Name container did not become healthy."
}

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $repoRoot

if ($Image -eq '') {
    $Image = (Invoke-Native 'docker' @('inspect', '--format', '{{.Config.Image}}', 'portfolio_course-web-1')).Trim()
}
Invoke-Native 'docker' @('image', 'inspect', $Image) | Out-Null
if ($OutputDirectory -eq '') {
    $OutputDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ('ather-load-' + (Get-Date -Format 'yyyyMMddTHHmmss'))
}
New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null

# The fixture only seeds inside a run-owned namespace with this exact shape.
$suffix = Get-RandomHex 12
$project = "ather_production_smoke_$suffix"
$slug = "production-smoke-$suffix"
$port = Get-UnusedLoopbackPort
$dbSecret = Get-RandomHex 24
$settings = @{
    APP_VERSION = 'load-test'
    PORTFOLIO_PRODUCTION_IMAGE = $Image
    PORTFOLIO_PRODUCTION_PORT = [string]$port
    DB_HOST = 'db'
    DB_PORT = '3306'
    DB_NAME = $project
    DB_USER = 'load_app'
    DB_PASSWORD = $dbSecret
    MYSQL_DATABASE = $project
    MYSQL_USER = 'load_app'
    MYSQL_PASSWORD = $dbSecret
    MYSQL_ROOT_PASSWORD = (Get-RandomHex 24)
    PUBLIC_BASE_URL = 'https://portfolio-load.invalid'
    SESSION_COOKIE_SECURE = 'true'
    EXPECTED_OIDC_ISSUER = 'https://oidc-load.invalid/'
    PRESERVED_V1_OIDC_SUBJECT = "load-owner-$suffix"
    OIDC_CLIENT_ID = 'load-client-' + (Get-RandomHex 8)
    OIDC_CLIENT_SECRET = (Get-RandomHex 24)
    OIDC_REDIRECT_URI = 'https://portfolio-load.invalid/owner_oidc_callback.php'
    EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY = (Get-RandomHex 32)
}
$environmentBackup = @{}
foreach ($entry in $settings.GetEnumerator()) {
    $environmentBackup[$entry.Key] = [Environment]::GetEnvironmentVariable($entry.Key, 'Process')
    [Environment]::SetEnvironmentVariable($entry.Key, [string]$entry.Value, 'Process')
}

$activated = $false
$k6Exit = 1
try {
    $existing = (Invoke-Native 'docker' @('ps', '--all', '--quiet', '--filter', "label=com.docker.compose.project=$project")).Trim()
    Assert-Load ($existing -eq '') 'the generated project name is already in use.'

    $activated = $true
    Invoke-Compose $project @('up', '--detach', '--no-build') | Out-Null
    $web = (Invoke-Compose $project @('ps', '-q', 'web')).Trim()
    $db = (Invoke-Compose $project @('ps', '-q', 'db')).Trim()
    Wait-Healthy $db 'database'
    Wait-Healthy $web 'web'

    $seedOutput = Invoke-Native 'docker' @(
        'exec', '--user', 'www-data',
        '--env', 'ATHERCAR_PRODUCTION_SMOKE=1',
        '--env', "ATHERCAR_PRODUCTION_SMOKE_PROJECT=$project",
        $web, 'php', 'scripts/ops/production-smoke-fixture.php', 'seed', $slug
    )
    $seedLine = @($seedOutput -split "`r?`n" | Where-Object { $_ -match '^\{"ok":' } | Select-Object -Last 1) -join ''
    $seed = $seedLine | ConvertFrom-Json
    Assert-Load ($seed.ok -eq $true -and [int]$seed.project_id -gt 0) 'the synthetic portfolio was not seeded.'

    $network = (Invoke-Native 'docker' @('inspect', '--format', '{{range $name, $_ := .NetworkSettings.Networks}}{{$name}}{{end}}', $web)).Trim()
    Assert-Load ($network -like "${project}_*") 'the disposable web container is not on its own project network.'

    Write-Output "INFO load test profile=$Profile project=$project image=$Image output=$OutputDirectory"
    $outMount = "${OutputDirectory}:/out"
    $k6Arguments = @('run', '--quiet')
    if ($RawMetrics) {
        $k6Arguments += @('--out', "json=/out/raw-$Profile.json.gz")
    }
    $scriptMount = (Join-Path $repoRoot 'tests\Load') + ':/scripts:ro'
    & docker run --rm --network $network `
        --volume $scriptMount --volume $outMount `
        --env BASE_URL=http://web --env "SLUG=$slug" --env "PROJECT_ID=$($seed.project_id)" `
        --env "PROFILE=$Profile" --env "SUMMARY_PATH=/out/summary-$Profile.json" `
        $K6Image @k6Arguments /scripts/public-visitor.js
    $k6Exit = $LASTEXITCODE

    $stats = Invoke-Native 'docker' @('stats', '--no-stream', '--format', '{{.Name}} cpu={{.CPUPerc}} mem={{.MemUsage}}', $web, $db)
    Write-Output "INFO after run: $($stats.Trim())"
    $restarts = (Invoke-Native 'docker' @('inspect', '--format', '{{.RestartCount}} oom={{.State.OOMKilled}}', $web)).Trim()
    Write-Output "INFO web restarts=$restarts"
} finally {
    if ($activated) {
        Invoke-Compose $project @('down', '--volumes', '--remove-orphans') | Out-Null
        $left = (Invoke-Native 'docker' @('ps', '--all', '--quiet', '--filter', "label=com.docker.compose.project=$project")).Trim()
        Assert-Load ($left -eq '') 'the disposable stack was not fully removed.'
        Write-Output "PASS load test cleanup project=$project"
    }
    foreach ($entry in $environmentBackup.GetEnumerator()) {
        [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process')
    }
}

if ($k6Exit -eq 0) {
    Write-Output "LOAD_TEST_PASSED profile=$Profile"
} else {
    Write-Output "LOAD_TEST_THRESHOLDS_FAILED profile=$Profile (k6 exit $k6Exit)"
    exit $k6Exit
}
