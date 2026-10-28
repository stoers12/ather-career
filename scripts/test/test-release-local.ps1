#requires -Version 7.0
[CmdletBinding()]
param(
    [string]$ComposeRoot, [string]$ReleasesRoot, [string]$ProjectName,
    [string]$ExtraComposeFile, [string]$MergeRef,
    [string]$HttpBase, [string]$HttpsBase
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$repository = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$releaseScript = Join-Path $repository 'scripts/ops/release-local.ps1'
$tokens = $null; $parseErrors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile($releaseScript, [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw 'Release script parse failed' }
$strings = @($ast.FindAll({ param($node) $node -is [Management.Automation.Language.StringConstantExpressionAst] }, $true))
foreach ($forbidden in @('down', 'prune', '-v')) {
    if (@($strings | Where-Object { $_.Value -ceq $forbidden }).Count) { throw 'Forbidden release lifecycle operation' }
}
$commands = @($ast.FindAll({ param($node) $node -is [Management.Automation.Language.CommandAst] -and $node.GetCommandName() -eq 'Invoke-Compose' }, $true))
foreach ($command in $commands) {
    $literals = @($command.FindAll({ param($node) $node -is [Management.Automation.Language.StringConstantExpressionAst] }, $true))
    if (@($literals | Where-Object { $_.Value -ceq 'db' }).Count) { throw 'Release script has a db service operation' }
}
$template = [IO.File]::ReadAllText((Join-Path $repository 'scripts/ops/templates/local-owner-release.yml'))
$placeholders = @([regex]::Matches($template, '\{\{([A-Z_]+)\}\}') | ForEach-Object { $_.Groups[1].Value } | Sort-Object -Unique)
if (($placeholders -join ',') -cne 'FULL_SHA,IMAGE,RATE_LIMIT_DIR,SOURCE') { throw 'Template placeholder contract changed' }
if ($template.Contains('/var/www/html') -or $template.Contains('owner-local-health.conf') -or $template.Contains('.:/var/www/app')) { throw 'Forbidden template mount' }
if ([regex]::Matches($template, 'volumes: !override').Count -ne 2) { throw 'Both mount lists must replace base mounts' }
$dryRunBlocks = @($ast.FindAll({ param($node)
    $node -is [Management.Automation.Language.IfStatementAst] -and
    $node.Clauses[0].Item1.Extent.Text -eq '$DryRun' -and
    $node.Clauses[0].Item2.Extent.Text.Contains("'RELEASE_LOCAL_DRYRUN_PASSED'")
}, $true))
if ($dryRunBlocks.Count -ne 1 -or $dryRunBlocks[0].Clauses[0].Item2.Extent.Text.Contains("'RELEASE_LOCAL_PASSED'")) {
    throw 'DryRun must have a distinct success sentinel'
}

& {
    # Load function definitions only, never the release entry point.
    foreach ($definition in $ast.FindAll({ param($node) $node -is [Management.Automation.Language.FunctionDefinitionAst] }, $true)) {
        . ([scriptblock]::Create($definition.Extent.Text))
    }
    $ProjectName = 'eng05-test-unit'
    $ComposeRoot = [IO.Path]::GetFullPath((Join-Path ([IO.Path]::GetTempPath()) 'eng05-unit-compose'))
    $source = [IO.Path]::GetFullPath((Join-Path ([IO.Path]::GetTempPath()) "eng05-unit-release/source"))
    $script:FullSha = 'a' * 40
    $script:Image = 'ather-career-local:' + ('a' * 12)
    $script:Previous = @{
        WebMounts = @(
            @{ Destination = '/var/www/private-storage'; Name = 'unit_private' },
            @{ Destination = '/var/www/app/runtime/rate-limit'; Source = (Join-Path $ComposeRoot 'runtime/rate-limit') }
        )
        ProxyMountSources = @(@{ Destination = '/data'; Source = '/var/lib/docker/volumes/unit_caddy/_data' })
    }
    $script:RenderedConfig = @{
        name = $ProjectName
        volumes = @{ portfolio_private_storage = @{ name = 'unit_private' }; caddy_data = @{ name = 'unit_caddy' } }
        services = @{
            web = @{
                image = $script:Image; entrypoint = @('/usr/local/bin/portfolio-maintenance-entrypoint'); command = @('apache2-foreground')
                labels = @{ 'org.ather-career.release-source' = (Normalize-Bind $source) }
                build = @{ target = 'production'; context = $source; dockerfile = 'Dockerfile'; args = @{ APP_VERSION = $script:FullSha } }
                environment = @{
                    APP_VERSION = $script:FullSha; RATE_LIMIT_STATE_DIR = '/var/www/app/runtime/rate-limit'
                    ATHERCAR_REGISTRATION_ENABLED = '0'; ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED = '0'
                    ATHERCAR_ONBOARDING_ATTESTATION_HTTP_ENABLED = '0'; ATHERCAR_ONBOARDING_COMPLETION_HTTP_ENABLED = '0'
                }
                volumes = @(
                    @{ type = 'volume'; source = 'portfolio_private_storage'; target = '/var/www/private-storage' },
                    @{ type = 'bind'; source = (Join-Path $ComposeRoot 'runtime/rate-limit'); target = '/var/www/app/runtime/rate-limit' }
                )
            }
            owner_https = @{ volumes = @(
                @{ type = 'volume'; source = 'caddy_data'; target = '/data' },
                @{ type = 'bind'; source = (Join-Path $source 'docker/Caddyfile.owner-local'); target = '/etc/caddy/Caddyfile'; read_only = $true },
                @{ type = 'bind'; source = (Join-Path $source 'docker/validate-oidc-issuer.sh'); target = '/etc/caddy/validate-oidc-issuer.sh'; read_only = $true }
            ) }
        }
    }
    function Invoke-Compose { return @{ Output = ($script:RenderedConfig | ConvertTo-Json -Depth 12) } }
    $valid = $script:RenderedConfig | ConvertTo-Json -Depth 12
    Assert-Config @() '' $source -RequireReleaseLabel
    foreach ($mutation in @(
        { $script:RenderedConfig.services.web.image = 'unreviewed-image' },
        { $script:RenderedConfig.services.web.labels['org.ather-career.release-source'] = '/stale-release/source' },
        { $script:RenderedConfig.services.web.entrypoint = @('/usr/local/bin/portfolio-production-entrypoint') },
        { $script:RenderedConfig.services.web.environment.APP_VERSION = 'b' * 40 },
        { $script:RenderedConfig.services.web.environment.ATHERCAR_REGISTRATION_ENABLED = '1' },
        { $script:RenderedConfig.services.web.volumes += @{ type = 'bind'; source = $ComposeRoot; target = '/var/www/app' } },
        { $script:RenderedConfig.services.web.volumes[0].read_only = $true },
        { $script:RenderedConfig.services.web.volumes += @{ type = 'bind'; source = 'owner-local-health.conf'; target = '/etc/apache2/conf-enabled/owner-local-health.conf' } },
        { $script:RenderedConfig.services.owner_https.volumes[1].read_only = $false },
        { $script:RenderedConfig.services.owner_https.volumes[1].source = '/stale-release/docker/Caddyfile.owner-local' },
        { $script:RenderedConfig.volumes.portfolio_private_storage.name = 'another_volume' }
    )) {
        $script:RenderedConfig = $valid | ConvertFrom-Json -AsHashtable
        & $mutation
        $refused = $false
        try { Assert-Config @() '' $source -RequireReleaseLabel } catch { $refused = $true }
        if (-not $refused) { throw 'Unsafe rendered configuration was accepted' }
    }
    $script:Masks = [Collections.Generic.HashSet[string]]::new()
    [void]$script:Masks.Add('unit-dummy-sensitive-value')
    $safe = Protect-Text 'unit-dummy-sensitive-value password=other-value subject=private-value'
    if ($safe.Contains('unit-dummy-sensitive-value') -or $safe.Contains('other-value') -or $safe.Contains('private-value')) { throw 'Output redaction failed' }
    foreach ($key in @('MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD', 'MYSQL_USER', 'MYSQL_DATABASE')) {
        $value = "unit-sensitive-$key"
        Add-SecretMask $key $value
        if ((Protect-Text $value) -cne '[REDACTED]') { throw 'MySQL secret-bearing key was not masked' }
    }
    foreach ($entry in @(@('MYSQL_MAJOR', '8.4'), @('MYSQL_VERSION', '8.4.8-1.el9'), @('MYSQL_SHELL_VERSION', '8.4.8-1.el9'))) {
        Add-SecretMask $entry[0] $entry[1]
        if ((Protect-Text $entry[1]) -cne $entry[1]) { throw 'MySQL version metadata was masked' }
    }
    Assert-TestProject
    $ProjectName = 'non-disposable-project'
    $refused = $false
    try { Assert-TestProject } catch { $refused = $true }
    if (-not $refused) { throw 'Failure injection accepted a non-disposable project' }
    $ProjectName = 'eng05-test-unit'
    function Invoke-Compose([string[]]$Files, [string[]]$Command) { $script:MigrationCommand = $Command }
    $script:CurrentFiles = @('unit-canonical.yml')
    $TestMigrationFailure = $false
    Invoke-ReleaseMigration
    if (($script:MigrationCommand -join '|') -cne 'run|--rm|--no-deps|--entrypoint|php|web|database/migrate.php') { throw 'Migration command changed' }
    $TestMigrationFailure = $true
    Invoke-ReleaseMigration
    if (($script:MigrationCommand[0..6] -join '|') -cne 'run|--rm|--no-deps|--entrypoint|php|web|-r' -or
        -not $script:MigrationCommand[7].Contains('exit(1)')) { throw 'Migration failure injection did not select a failing PHP process' }
    $recovery = Format-RollbackCommand @('previous canonical.yml') 'ather-career-local:rollback-unit' 'ather-career-local:unit'
    if (-not $recovery.StartsWith("docker image tag 'ather-career-local:rollback-unit' 'ather-career-local:unit'") -or
        $recovery.IndexOf('Rollback image restore failed') -gt $recovery.IndexOf("docker 'compose'") -or
        -not $recovery.Contains("'--no-deps' '--no-build' 'web' 'owner_https'")) { throw 'Recorded rollback command lost its pinned image or service boundary' }
}
Write-Host 'RELEASE_LOCAL_SAFETY_PASSED'

if ($ComposeRoot) {
    if ($ProjectName -notmatch '^eng05-test(?:-|$)' -or -not $MergeRef -or -not $ReleasesRoot -or -not $ExtraComposeFile) {
        throw 'DryRun integration requires an explicit disposable project and all paths'
    }
    $before = if (Test-Path -LiteralPath $ReleasesRoot) { @(Get-ChildItem -LiteralPath $ReleasesRoot -Force | Select-Object -ExpandProperty Name) -join '|' } else { '<absent>' }
    $gitCommon = (& git -C $repository rev-parse --path-format=absolute --git-common-dir).Trim()
    $fetchHead = Join-Path $gitCommon 'FETCH_HEAD'
    $fetchBefore = if (Test-Path -LiteralPath $fetchHead) { (Get-FileHash -LiteralPath $fetchHead).Hash } else { '<absent>' }
    $containersBefore = @(& docker ps -a --filter "label=com.docker.compose.project=$ProjectName" --format '{{.ID}}|{{.Image}}|{{.Status}}') -join '`n'
    $output = @(& pwsh -NoProfile -File $releaseScript -MergeRef $MergeRef -ComposeRoot $ComposeRoot -ReleasesRoot $ReleasesRoot -ProjectName $ProjectName -ExtraComposeFile $ExtraComposeFile -HttpBase $HttpBase -HttpsBase $HttpsBase -Label dryrun-proof -DryRun 2>&1)
    if ($LASTEXITCODE) { throw 'Real DryRun failed' }
    $output | ForEach-Object { Write-Host $_ }
    if ($output[-1] -cne 'RELEASE_LOCAL_DRYRUN_PASSED' -or $output -ccontains 'RELEASE_LOCAL_PASSED') { throw 'Real DryRun emitted the wrong success sentinel' }
    $after = if (Test-Path -LiteralPath $ReleasesRoot) { @(Get-ChildItem -LiteralPath $ReleasesRoot -Force | Select-Object -ExpandProperty Name) -join '|' } else { '<absent>' }
    $fetchAfter = if (Test-Path -LiteralPath $fetchHead) { (Get-FileHash -LiteralPath $fetchHead).Hash } else { '<absent>' }
    $containersAfter = @(& docker ps -a --filter "label=com.docker.compose.project=$ProjectName" --format '{{.ID}}|{{.Image}}|{{.Status}}') -join '`n'
    # Container uptime text changes naturally, so compare stable ID/image here.
    $identitiesBefore = ($containersBefore -split '`n' | ForEach-Object { ($_ -split '\|')[0..1] -join '|' }) -join '`n'
    $identitiesAfter = ($containersAfter -split '`n' | ForEach-Object { ($_ -split '\|')[0..1] -join '|' }) -join '`n'
    if ($before -cne $after -or $fetchBefore -cne $fetchAfter -or $identitiesBefore -cne $identitiesAfter) { throw 'DryRun changed filesystem, Git or container identities' }
    Write-Host 'RELEASE_LOCAL_DRYRUN_NO_WRITES_PASSED'
}
