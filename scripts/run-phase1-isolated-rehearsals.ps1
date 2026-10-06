param(
    [switch]$SkipUpgradedCopy,
    [string]$UpgradedDumpPath
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
if ($SkipUpgradedCopy -and $UpgradedDumpPath) { throw 'Select either fresh-only or an upgraded dump.' }
if ((git -C $root rev-parse --show-toplevel).Trim().Replace('\','/') -ne $root.Replace('\','/')) {
    throw 'Phase 1 rehearsal must run from the canonical repository.'
}
$runId = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(6)).ToLowerInvariant()
$network = "ather-phase1-$runId"
$container = "ather-phase1-db-$runId"
$password = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
$env:DB_PASSWORD = $password
$env:MYSQL_PWD = $password
$databases = @{
    fresh = "ather_phase1_fresh_$runId"
    malformed = "ather_phase1_malformed_$runId"
    conflicting = "ather_phase1_conflicting_$runId"
    partial = "ather_phase1_partial_$runId"
    upgraded = "ather_phase1_upgraded_$runId"
}
$networkCreated = $false
$containerCreated = $false

function Assert-LastExit([string]$step) {
    if ($LASTEXITCODE -ne 0) { throw "Phase 1 isolated rehearsal failed at $step." }
}

function Invoke-AppPhp([string]$database, [string[]]$arguments) {
    & docker run --rm --network $network --entrypoint php `
        -v "${root}:/var/www/html:ro" -w /var/www/html `
        -e DB_HOST=phase1-db -e DB_PORT=3306 -e "DB_NAME=$database" `
        -e DB_USER=root -e DB_PASSWORD -e APP_ENV=test `
        -e ATHERCAR_TEST_MODE=1 -e ATHERCAR_PHASE1_ISOLATED_DB=1 `
        portfolio_course-web @arguments
    Assert-LastExit "PHP $($arguments -join ' ')"
}

try {
    & docker network create --internal --label "ather.phase1.run=$runId" $network | Out-Null
    Assert-LastExit 'network create'
    $networkCreated = $true
    & docker run -d --rm --name $container --network $network --network-alias phase1-db `
        --label "ather.phase1.run=$runId" -e "MYSQL_ROOT_PASSWORD=$password" `
        -e 'MYSQL_ROOT_HOST=%' mysql:8.4 | Out-Null
    Assert-LastExit 'isolated MySQL start'
    $containerCreated = $true
    $ready = $false
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        & docker exec -e MYSQL_PWD=$password $container mysqladmin ping -h 127.0.0.1 -u root --silent *> $null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'Isolated MySQL did not become ready.' }

    foreach ($database in $databases.Values) {
        & docker exec -e MYSQL_PWD=$password $container mysql -u root `
            -e "CREATE DATABASE $database CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
        Assert-LastExit 'isolated database create'
    }

    foreach ($mode in @('fresh','malformed','conflicting','partial')) {
        $database = $databases[$mode]
        & docker run --rm --network $network -v "${root}:/source:ro" `
            -e MYSQL_PWD mysql:8.4 sh -c `
            'mysql -h phase1-db -u root "$1" < /source/database/portfolio_db.sql' sh $database
        Assert-LastExit "bootstrap $mode"
        # The established V1 bootstrap includes one project. Migration 003
        # expands ownership; the existing owner backfill must assign that
        # project before Migration 004 contracts ownership to NOT NULL.
        Invoke-AppPhp $database @('database/migrate.php','--through=003')
        Invoke-AppPhp $database @(
            'database/backfill-v1-ownership.php',
            '--issuer','https://phase1-fixture.invalid/',
            '--subject',"fixture-$mode-$runId"
        )
        Invoke-AppPhp $database @('database/migrate.php','--through=013')
    }

    if (-not $SkipUpgradedCopy) {
        if ($UpgradedDumpPath) {
            $dump = (Resolve-Path -LiteralPath $UpgradedDumpPath -ErrorAction Stop).Path
            if ([System.IO.Path]::GetExtension($dump) -ne '.sql' -or -not (Test-Path -LiteralPath $dump -PathType Leaf)) {
                throw 'The protected upgraded-copy source is not a readable SQL dump.'
            }
            & docker run --rm --network $network -v "${dump}:/source.sql:ro" `
                -e MYSQL_PWD mysql:8.4 sh -c `
                'mysql -h phase1-db -u root "$1" < /source.sql' sh $databases.upgraded
            Assert-LastExit 'isolated protected-dump restore'
        } else {
            $source = 'portfolio_course-db-1'
            $sourceInfo = (docker inspect $source | ConvertFrom-Json)[0]
            if ($sourceInfo.Name -ne "/$source" -or -not $sourceInfo.State.Running) {
                throw 'Canonical source database identity is unavailable; no copy was made.'
            }
            # A transaction-consistent read only from a canonical 001–013 source.
            & docker exec $source sh -c `
                'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction --skip-lock-tables --set-gtid-purged=OFF -u root "$MYSQL_DATABASE"' |
                & docker exec -i -e MYSQL_PWD=$password $container mysql -u root $databases.upgraded
            Assert-LastExit 'isolated upgraded-copy restore'
        }
    }

    Invoke-AppPhp $databases.fresh @('scripts/run-phase1-identity-rehearsal.php','fresh')
    Invoke-AppPhp $databases.malformed @('scripts/run-phase1-identity-rehearsal.php','malformed')
    Invoke-AppPhp $databases.conflicting @('scripts/run-phase1-identity-rehearsal.php','conflicting')
    Invoke-AppPhp $databases.partial @('scripts/run-phase1-identity-rehearsal.php','partial')
    if (-not $SkipUpgradedCopy) {
        Invoke-AppPhp $databases.upgraded @('scripts/run-phase1-identity-rehearsal.php','upgraded')
    }
    Write-Output 'PASS PHASE1-ISOLATED-REHEARSALS'
} finally {
    if ($containerCreated) {
        $info = (docker inspect $container 2>$null | ConvertFrom-Json)[0]
        if ($info -and $info.Config.Labels.'ather.phase1.run' -eq $runId) {
            & docker stop $container | Out-Null
        }
    }
    if ($networkCreated) {
        $info = (docker network inspect $network 2>$null | ConvertFrom-Json)[0]
        if ($info -and $info.Labels.'ather.phase1.run' -eq $runId) {
            & docker network rm $network | Out-Null
        }
    }
    Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}
