Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$runId = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(12)).ToLowerInvariant()
$network = "ather-phase2a-$runId"
$container = "ather-phase2a-db-$runId"
$database = "ather_career_test_$runId"
$password = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
$networkCreated = $false
$containerCreated = $false
$env:DB_PASSWORD = $password
$env:MYSQL_PWD = $password

function Assert-Exit([string]$step) {
    if ($LASTEXITCODE -ne 0) { throw "Phase 2A isolated rehearsal failed at $step." }
}

function Invoke-Php([string[]]$arguments) {
    & docker run --rm --network $network --entrypoint php `
        -v ($root + ':/var/www/html:ro') -w /var/www/html `
        -e APP_ENV=test -e ATHERCAR_TEST_MODE=1 `
        -e "ATHERCAR_TEST_DB_NAME=$database" `
        -e DB_HOST=phase2a-db -e DB_PORT=3306 -e "DB_NAME=$database" `
        -e DB_USER=root -e DB_PASSWORD portfolio_course-web @arguments
    Assert-Exit "PHP $($arguments[0])"
}

try {
    if ((git -C $root rev-parse --show-toplevel).Trim().Replace('\','/') -ne $root.Replace('\','/')) {
        throw 'Phase 2A rehearsal must run from the canonical repository.'
    }
    & docker network create --internal --label "ather.phase2a.run=$runId" $network | Out-Null
    Assert-Exit 'network create'
    $networkCreated = $true
    & docker run -d --rm --name $container --network $network --network-alias phase2a-db `
        --label "ather.phase2a.run=$runId" -e "MYSQL_ROOT_PASSWORD=$password" `
        -e 'MYSQL_ROOT_HOST=%' mysql:8.4 | Out-Null
    Assert-Exit 'isolated MySQL start'
    $containerCreated = $true
    $ready = $false
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        & docker exec -e MYSQL_PWD=$password $container mysqladmin ping -h 127.0.0.1 -u root --silent *> $null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'Isolated MySQL did not become ready.' }
    & docker exec -e MYSQL_PWD=$password $container mysql -u root `
        -e "CREATE DATABASE $database CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
    Assert-Exit 'database create'
    & docker run --rm --network $network -v ($root + ':/source:ro') `
        -e MYSQL_PWD mysql:8.4 sh -c `
        'mysql -h phase2a-db -u root "$1" < /source/database/portfolio_db.sql' sh $database
    Assert-Exit 'V1 bootstrap'
    Invoke-Php @('database/migrate.php','--through=003')
    Invoke-Php @('database/backfill-v1-ownership.php','--issuer','https://phase2a-fixture.invalid/','--subject',"fixture-$runId")
    Invoke-Php @('database/migrate.php','--through=014')
    Invoke-Php @('scripts/run-phase2-auth0-rehearsal.php')
    Write-Output 'PASS PHASE2A-ISOLATED-CALLBACK-REHEARSAL'
} finally {
    if ($containerCreated) {
        $info = (docker inspect $container 2>$null | ConvertFrom-Json)[0]
        if ($info -and $info.Config.Labels.'ather.phase2a.run' -eq $runId) {
            & docker stop $container | Out-Null
        }
    }
    if ($networkCreated) {
        $info = (docker network inspect $network 2>$null | ConvertFrom-Json)[0]
        if ($info -and $info.Labels.'ather.phase2a.run' -eq $runId) {
            & docker network rm $network | Out-Null
        }
    }
    Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}
