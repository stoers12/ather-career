Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$source = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot 'run-phase2a-oidc-http-rehearsal.ps1')).Path
$tokens = $null
$errors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile($source, [ref]$tokens, [ref]$errors)
if ($errors.Count -ne 0) { throw 'OIDC rehearsal harness has a PowerShell parse error.' }
$names = @('ConvertTo-LfShellPayload','ConvertTo-SafeDiagnostic','Assert-DockerStartup','Get-RunContainer','Remove-RunContainer','Invoke-WithRunCleanup','Assert-DependencyProvision','Invoke-CheckedDocker','Get-RunVendorVolume','Remove-RunVendorVolume','Assert-OwnerLoginStatus')
$functions = @($ast.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $true))
foreach ($name in $names) {
    $definition = @($functions | Where-Object { $_.Name -eq $name })
    if ($definition.Count -ne 1) { throw "Missing or duplicate harness function: $name" }
    . ([scriptblock]::Create($definition[0].Extent.Text))
}

$cr = [string][char]13
$lf = [string][char]10
$normalized = ConvertTo-LfShellPayload ("set -eu" + $cr + $lf + "line two" + $cr + "line three" + $lf)
if ($normalized -cne ("set -eu" + $lf + "line two" + $lf + "line three" + $lf) -or $normalized.Contains([char]13)) {
    throw 'CRLF and isolated-CR normalization failed.'
}
Write-Output 'PASS OIDC HARNESS CRLF AND ISOLATED CR'

$runId = 'synthetic-run'
$script:mockExit = 0
$script:mockStdoutMode = 'none'
$script:mockStdout = ''
$script:mockStderr = ''
function docker {
    if ($script:mockStdoutMode -eq 'empty') { Write-Output '' }
    elseif ($script:mockStdoutMode -eq 'text') { Write-Output $script:mockStdout }
    if ($script:mockStderr -ne '') { Write-Error -Message $script:mockStderr -ErrorAction Continue }
    $global:LASTEXITCODE = $script:mockExit
}

$script:mockStderr = 'composer.json is valid with warnings'
$nullStdout = Invoke-CheckedDocker 'validate' @('mock')
if ($null -eq $nullStdout -or $nullStdout -cne '') { throw 'Warning-only successful validation did not normalize null stdout.' }
$script:mockStdoutMode = 'empty'
$emptyStdout = Invoke-CheckedDocker 'validate' @('mock')
if ($null -eq $emptyStdout -or $emptyStdout -cne '') { throw 'Successful validation did not normalize empty stdout.' }
Write-Output 'PASS OIDC HARNESS NULL AND EMPTY STDOUT WITH WARNING STDERR'

$script:mockStdoutMode = 'text'
$script:mockStdout = 'ather-phase2a-vendor-synthetic-run'
$script:mockStderr = 'permission denied client_secret=synthetic-secret'
$script:mockExit = 42
$failedNative = ''
try { Invoke-CheckedDocker 'volume-create' @('mock') 'ather-phase2a-vendor-synthetic-run' }
catch { $failedNative = $_.Exception.Message }
if ($failedNative -notmatch 'volume-create failed \(docker exit 42\)' -or $failedNative -notmatch 'permission denied' -or $failedNative -match 'synthetic-secret') {
    throw 'Nonzero native exit was masked by expected-looking stdout or lost sanitized stderr.'
}
$script:mockStdoutMode = 'none'
$script:mockStderr = 'dependency download denied'
$script:mockExit = 43
$emptyFailure = ''
try { Invoke-CheckedDocker 'install' @('mock') }
catch { $emptyFailure = $_.Exception.Message }
if ($emptyFailure -notmatch 'install failed \(docker exit 43\)' -or $emptyFailure -notmatch 'dependency download denied') {
    throw 'Nonzero native exit with empty stdout and populated stderr was not retained.'
}
Write-Output 'PASS OIDC HARNESS NONZERO EXIT PRECEDES OUTPUT'

$script:mockExit = 0
$script:mockStderr = ''
$script:mockStdoutMode = 'text'
$script:mockStdout = 'ather-phase2a-vendor-synthetic-run'
$volumeName = Invoke-CheckedDocker 'volume-create' @('mock') 'ather-phase2a-vendor-synthetic-run'
if ($volumeName -cne 'ather-phase2a-vendor-synthetic-run') { throw 'Exact volume-create name was not accepted.' }
$script:mockStdout = 'ather-phase2a-vendor-other-run'
$wrongVolume = ''
try { Invoke-CheckedDocker 'volume-create' @('mock') 'ather-phase2a-vendor-synthetic-run' }
catch { $wrongVolume = $_.Exception.Message }
if ($wrongVolume -notmatch 'volume-create output contract') { throw 'Unexpected volume-create name was accepted.' }
Write-Output 'PASS OIDC HARNESS EXACT VOLUME OUTPUT'

$script:mockStdoutMode = 'none'
$script:mockStderr = 'Installing dependencies from lock file'
$install = Invoke-CheckedDocker 'install' @('mock')
if ($null -eq $install -or $install -cne '') { throw 'Successful install with stderr progress failed.' }
$script:mockStderr = 'Checking non-dev platform requirements'
$platform = Invoke-CheckedDocker 'platform-composer' @('mock')
if ($null -eq $platform -or $platform -cne '') { throw 'Successful platform check with stderr progress failed.' }
Write-Output 'PASS OIDC HARNESS INSTALL AND PLATFORM STDERR PROGRESS'

$script:mockStderr = ''
$script:mockStdoutMode = 'text'
$script:mockStdout = '26'
if ((Invoke-CheckedDocker 'locked-packages' @('mock')) -cne '26') { throw 'Locked package count was not accepted.' }
$script:mockStdout = 'PHP83_READY'
if ((Invoke-CheckedDocker 'platform-php83' @('mock')) -cne 'PHP83_READY') { throw 'Exact PHP autoload probe failed.' }
$script:mockStdout = 'PHP83_READY extra'
$badProbe = ''
try { Invoke-CheckedDocker 'platform-php83' @('mock') }
catch { $badProbe = $_.Exception.Message }
if ($badProbe -notmatch 'platform-php83 output contract') { throw 'Unexpected PHP probe output was accepted.' }
Write-Output 'PASS OIDC HARNESS LOCKED COUNT AND EXACT PHP83 READY'

$script:mockStdout = 'not-json'
$badJson = ''
try { Get-RunVendorVolume 'ather-phase2a-vendor-synthetic-run' }
catch { $badJson = $_.Exception.Message }
if ($badJson -notmatch 'volume-inspect returned invalid JSON') { throw 'Malformed Docker volume JSON was not rejected.' }
$script:mockStdout = '[{"Name":"ather-phase2a-vendor-synthetic-run"}]'
$badShape = ''
try { Get-RunVendorVolume 'ather-phase2a-vendor-synthetic-run' }
catch { $badShape = $_.Exception.Message }
if ($badShape -notmatch 'volume-inspect returned an unexpected JSON shape') { throw 'Unexpected Docker volume JSON shape was not rejected.' }
$script:mockStdout = '[]'
$emptyJson = ''
try { Get-RunVendorVolume 'ather-phase2a-vendor-synthetic-run' }
catch { $emptyJson = $_.Exception.Message }
if ($emptyJson -notmatch 'volume-inspect returned an unexpected JSON shape') { throw 'Empty Docker volume JSON array was accepted.' }
Write-Output 'PASS OIDC HARNESS MALFORMED AND UNEXPECTED JSON'
$startup = ''
try { Assert-DockerStartup 125 'permission denied client_secret=synthetic-secret email=person@example.invalid' '' }
catch { $startup = $_.Exception.Message }
if ($startup -notmatch 'docker exit 125' -or $startup -notmatch 'permission denied' -or $startup -match 'synthetic-secret|person@example.invalid') {
    throw 'Startup failure did not retain the exit and sanitized original error.'
}
function docker { throw 'synthetic Docker invocation exception' }
$invocationFailure = ''
try { Invoke-CheckedDocker 'validate' @('mock') }
catch { $invocationFailure = $_.Exception.Message }
if ($invocationFailure -notmatch 'dependency validate PowerShell failure' -or $invocationFailure -notmatch 'synthetic Docker invocation exception') {
    throw 'PowerShell-native invocation failure lost its provisioning stage or primary error.'
}
Write-Output 'PASS OIDC HARNESS NATIVE INVOCATION STAGE'
Write-Output 'PASS OIDC HARNESS STARTUP ERROR RETENTION'

$runId = 'synthetic-run'
function Get-RunContainer([string]$name) { return $null }
Remove-RunContainer 'ather-phase2a-http-synthetic-run' $runId ''
Write-Output 'PASS OIDC HARNESS MISSING CONTAINER CLEANUP'

$script:present = $true
$script:removedName = ''
$script:failRemove = $false
function Get-RunContainer([string]$name) {
    if (-not $script:present) { return $null }
    return [pscustomobject]@{
        Name = '/' + $name
        Id = ('a' * 64)
        Config = [pscustomobject]@{ Labels = [pscustomobject]@{ 'ather.phase2a.http' = 'synthetic-run' } }
    }
}
function docker {
    if ($args[0] -ne 'rm' -or $args[1] -ne '-f') { throw 'Unexpected Docker operation in cleanup test.' }
    $script:removedName = [string]$args[2]
    if ($script:failRemove) { $global:LASTEXITCODE = 1; return }
    $script:present = $false
    $global:LASTEXITCODE = 0
}
Remove-RunContainer 'ather-phase2a-http-synthetic-run' $runId ('a' * 64)
if ($script:present -or $script:removedName -ne 'ather-phase2a-http-synthetic-run') {
    throw 'Successful cleanup did not remove the exact run-owned container.'
}
Write-Output 'PASS OIDC HARNESS EXACT CONTAINER CLEANUP'

$script:present = $true
$script:present = $true
$script:removedName = ''
$script:failRemove = $false
$wrongContainerLabel = ''
try { Remove-RunContainer 'ather-phase2a-http-synthetic-run' 'other-run' ('a' * 64) }
catch { $wrongContainerLabel = $_.Exception.Message }
if ($wrongContainerLabel -notmatch 'ownership label' -or -not $script:present -or $script:removedName -ne '') {
    throw 'Container cleanup accepted a mismatched ownership label.'
}
Write-Output 'PASS OIDC HARNESS CONTAINER OWNERSHIP LABEL'
$script:failRemove = $true
$cleanupFailure = ''
try { Remove-RunContainer 'ather-phase2a-http-synthetic-run' $runId ('a' * 64) }
catch { $cleanupFailure = $_.Exception.Message }
if ($cleanupFailure -notmatch 'Could not remove run-owned') { throw 'Genuine cleanup failure was not raised.' }

$primary = ''
try {
    Invoke-WithRunCleanup { throw 'PRIMARY_STARTUP_ERROR' } { } { throw 'CLEANUP_ERROR' }
} catch { $primary = $_.Exception.Message }
if ($primary -notmatch 'PRIMARY_STARTUP_ERROR' -or $primary -match 'CLEANUP_ERROR') {
    throw 'Cleanup error replaced the primary failure.'
}
$cleanupOnly = ''
try {
    Invoke-WithRunCleanup { } { } { throw 'CLEANUP_ONLY_ERROR' }
} catch { $cleanupOnly = $_.Exception.Message }
if ($cleanupOnly -notmatch 'CLEANUP_ONLY_ERROR') { throw 'Cleanup-only failure did not fail the harness.' }
Write-Output 'PASS OIDC HARNESS PRIMARY AND CLEANUP ERROR PRIORITY'

$dependencyFailure = ''
try { Assert-DependencyProvision 'install' 42 'dependency download failed client_secret=synthetic-secret' }
catch { $dependencyFailure = $_.Exception.Message }
if ($dependencyFailure -notmatch 'install failed \(docker exit 42\)' -or $dependencyFailure -notmatch 'dependency download failed' -or $dependencyFailure -match 'synthetic-secret') {
    throw 'Failed provisioning did not retain its original sanitized exit and error.'
}
Write-Output 'PASS OIDC HARNESS DEPENDENCY FAILURE RETENTION'

$statusFailure = ''
try { Assert-OwnerLoginStatus '503' 0 'text/plain' 'sign_in_unavailable' 'absent' }
catch { $statusFailure = $_.Exception.Message }
if ($statusFailure -notmatch 'expected 302, received 503' -or $statusFailure -notmatch 'curl exit 0' -or $statusFailure -notmatch 'sign_in_unavailable') {
    throw 'Unexpected Owner-login status was not retained before assertion failure.'
}
Write-Output 'PASS OIDC HARNESS STATUS EVIDENCE BEFORE ASSERTION'

$script:volumePresent = $true
$script:volumeRemoved = ''
$script:volumeRemoveFailure = $false
function Get-RunVendorVolume([string]$name) {
    if (-not $script:volumePresent) { return $null }
    return [pscustomobject]@{ Name = $name; Labels = [pscustomobject]@{ 'ather.phase2a.vendor' = 'synthetic-run' } }
}
function docker {
    if ($args[0] -ne 'volume' -or $args[1] -ne 'rm') { throw 'Unexpected Docker operation in vendor cleanup test.' }
    $script:volumeRemoved = [string]$args[2]
    if ($script:volumeRemoveFailure) { $global:LASTEXITCODE = 1; return }
    $script:volumePresent = $false
    $global:LASTEXITCODE = 0
}
Remove-RunVendorVolume 'ather-phase2a-vendor-synthetic-run' 'synthetic-run'
if ($script:volumePresent -or $script:volumeRemoved -ne 'ather-phase2a-vendor-synthetic-run') {
    throw 'Successful dependency cleanup did not remove the exact run-owned volume.'
}
Remove-RunVendorVolume 'ather-phase2a-vendor-synthetic-run' 'synthetic-run'
$script:volumePresent = $true
$script:volumePresent = $true
$script:volumeRemoved = ''
$script:volumeRemoveFailure = $false
$wrongVolumeLabel = ''
try { Remove-RunVendorVolume 'ather-phase2a-vendor-synthetic-run' 'other-run' }
catch { $wrongVolumeLabel = $_.Exception.Message }
if ($wrongVolumeLabel -notmatch 'ownership' -or -not $script:volumePresent -or $script:volumeRemoved -ne '') {
    throw 'Volume cleanup accepted a mismatched ownership label.'
}
Write-Output 'PASS OIDC HARNESS VOLUME OWNERSHIP LABEL'
$script:volumeRemoveFailure = $true
$volumeFailure = ''
try { Remove-RunVendorVolume 'ather-phase2a-vendor-synthetic-run' 'synthetic-run' }
catch { $volumeFailure = $_.Exception.Message }
if ($volumeFailure -notmatch 'Could not remove run-owned dependency volume') { throw 'Dependency cleanup failure was not raised.' }
Write-Output 'PASS OIDC HARNESS DEPENDENCY VOLUME CLEANUP'
