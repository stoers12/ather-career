[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidatePattern('^[a-z0-9][a-z0-9_.-]+$')]
    [string]$WebContainer,

    [Parameter(Mandatory)]
    [ValidatePattern('^[a-z0-9][a-z0-9_.-]+$')]
    [string]$DbContainer
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$bytes = [byte[]]::new(12)
[System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
$runId = [Convert]::ToHexString($bytes).ToLowerInvariant()
$testDatabase = "ather_career_test_$runId"
if ($testDatabase -notmatch '^ather_career_test_[a-f0-9]{24}$') {
    throw 'Refusing an invalid generated lifecycle test database name.'
}

$createDatabaseScript = @'
set -eu
printf '%s' "$ATHERCAR_TEST_DB_NAME" | grep -Eq '^ather_career_test_[a-f0-9]{24}$'
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot -e "CREATE DATABASE \`$ATHERCAR_TEST_DB_NAME\` CHARACTER SET utf8mb4;"
'@

$grantDatabaseScript = @'
set -eu
printf '%s' "$ATHERCAR_TEST_DB_NAME" | grep -Eq '^ather_career_test_[a-f0-9]{24}$'
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot -e "GRANT ALL PRIVILEGES ON \`$ATHERCAR_TEST_DB_NAME\`.* TO '$MYSQL_USER'@'%'; FLUSH PRIVILEGES;"
'@

$cleanupScript = @'
set -eu
printf '%s' "$ATHERCAR_TEST_DB_NAME" | grep -Eq '^ather_career_test_[a-f0-9]{24}$'
(MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot -e "REVOKE ALL PRIVILEGES ON \`$ATHERCAR_TEST_DB_NAME\`.* FROM '$MYSQL_USER'@'%';" || true)
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot -e "DROP DATABASE \`$ATHERCAR_TEST_DB_NAME\`; FLUSH PRIVILEGES;"
remaining=$(MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -N -B -h 127.0.0.1 -uroot -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$ATHERCAR_TEST_DB_NAME';")
test "$remaining" = '0'
remaining_grants=$(MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -N -B -h 127.0.0.1 -uroot -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMA_PRIVILEGES WHERE TABLE_SCHEMA = '$ATHERCAR_TEST_DB_NAME';")
test "$remaining_grants" = '0'
'@

$databaseCreated = $false
$testFailure = $null
$cleanupFailure = $null
try {
    & docker exec -e "ATHERCAR_TEST_DB_NAME=$testDatabase" $DbContainer sh -lc $createDatabaseScript
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not provision the exact run-owned lifecycle test database.'
    }
    $databaseCreated = $true
    & docker exec -e "ATHERCAR_TEST_DB_NAME=$testDatabase" $DbContainer sh -lc $grantDatabaseScript
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not grant the lifecycle test database to the application test account.'
    }

    & docker exec `
        -e 'APP_ENV=test' `
        -e 'ATHERCAR_TEST_MODE=1' `
        -e 'DB_NAME=' `
        -e 'PORTFOLIO_DB_NAME=' `
        -e "ATHERCAR_TEST_DB_NAME=$testDatabase" `
        -e 'ATHERCAR_TEST_DB_PROVISIONED=1' `
        $WebContainer php scripts/run-phase2-publication-lifecycle-e2e.php
    if ($LASTEXITCODE -ne 0) {
        throw 'Publication lifecycle E2E failed.'
    }
} catch {
    $testFailure = $_
} finally {
    if ($databaseCreated) {
        try {
            & docker exec -e "ATHERCAR_TEST_DB_NAME=$testDatabase" $DbContainer sh -lc $cleanupScript
            if ($LASTEXITCODE -ne 0) {
                throw 'The lifecycle test database was not removed.'
            }
        } catch {
            $cleanupFailure = $_
        }
    }
}

if ($null -ne $cleanupFailure) {
    throw $cleanupFailure
}
if ($null -ne $testFailure) {
    throw $testFailure
}

Write-Output 'PASS isolated publication lifecycle database teardown'
