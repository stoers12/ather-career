[CmdletBinding()]
param(
    [string]$SourceRoot = (Split-Path -Parent $PSScriptRoot),
    [switch]$IdentityChallenge,
    [switch]$RegistrationChallenge,
    [switch]$Focused,
    [switch]$InstallChromium,
    [string]$EvidenceDirectory = ''
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

function Get-NativeCurl {
    $name = if ($IsWindows) { 'curl.exe' } else { 'curl' }
    return (Get-Command $name -CommandType Application -ErrorAction Stop | Select-Object -First 1).Source
}

function Protect-GateSecret([string]$Value) {
    if ($env:GITHUB_ACTIONS -eq 'true') { Write-Host "::add-mask::$Value" }
}

function Restore-LinuxCookieJarOwnership([string]$Temporary, [string]$Phase) {
    if (-not $IsLinux) { return }
    $uid = (& id -u); if ($LASTEXITCODE -ne 0) { Fail-Gate 'cookie handoff' 'runner UID unavailable' }
    $gid = (& id -g); if ($LASTEXITCODE -ne 0) { Fail-Gate 'cookie handoff' 'runner GID unavailable' }
    if ($uid -notmatch '^[1-9][0-9]*$' -or $gid -notmatch '^[0-9]+$') { Fail-Gate 'cookie handoff' 'a non-root runner is required' }
    $parent = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('/')
    $code = @'
import os, re, stat, sys
root, parent, uid, gid, phase = sys.argv[1:]
fds = []
def require(condition, reason):
    if not condition:
        raise ValueError(reason)
def metadata(kind, name, info):
    print(f"COOKIE_JAR_METADATA phase={phase} kind={kind} name={name} uid={info.st_uid} gid={info.st_gid} mode={stat.S_IMODE(info.st_mode):04o}", flush=True)
try:
    uid, gid = int(uid), int(gid)
    require(uid > 0 and gid >= 0 and os.geteuid() == 0, 'identity')
    require((uid, gid) == (int(os.environ['SUDO_UID']), int(os.environ['SUDO_GID'])), 'sudo-caller')
    require(phase in ('initial', 'before-http', 'after-http', 'before-editor', 'after-editor'), 'phase')
    require(os.path.isabs(parent) and os.path.normpath(parent) == parent, 'parent')
    require(os.path.dirname(root) == parent and re.fullmatch(r'ather-evidence-hub-(gates|focused)-[a-f0-9]{32}', os.path.basename(root)), 'namespace')
    # Traverse every directory by descriptor: no symlink component is followed.
    current = os.open('/', os.O_RDONLY | os.O_DIRECTORY)
    fds.append(current)
    for component in parent.split('/')[1:] + [os.path.basename(root), 'bridge']:
        current = os.open(component, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=current)
        fds.append(current)
        if len(fds) >= len(parent.split('/')) + 1:
            info = os.fstat(current)
            require(info.st_uid == uid and stat.S_IMODE(info.st_mode) == 0o700, 'private-directory')
            metadata('directory', 'bridge' if component == 'bridge' else 'run-root', info)
    names = ['owner-a.cookiejar', 'owner-b.cookiejar']
    if phase != 'initial':
        names.append('fabricated.cookiejar')
    jars = []
    for name in names:
        entry = os.stat(name, dir_fd=current, follow_symlinks=False)
        metadata('before', name, entry)
        require(stat.S_ISREG(entry.st_mode) and entry.st_nlink == 1 and entry.st_uid in (0, uid), 'regular-run-owned-file')
        fd = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=current)
        fds.append(fd)
        info = os.fstat(fd)
        require(stat.S_ISREG(info.st_mode) and info.st_nlink == 1 and info.st_uid in (0, uid), 'regular-run-owned-file')
        require((entry.st_dev, entry.st_ino) == (info.st_dev, info.st_ino), 'entry-changed')
        jars.append((name, fd, info))
    # Validate the complete set before modifying any file. Never read contents,
    # replace a jar, walk recursively, or operate on a pathname with privilege.
    for name, fd, info in jars:
        entry = os.stat(name, dir_fd=current, follow_symlinks=False)
        require((entry.st_dev, entry.st_ino) == (info.st_dev, info.st_ino) and entry.st_nlink == 1, 'entry-changed')
    for name, fd, info in jars:
        os.fchown(fd, uid, gid)
        os.fchmod(fd, 0o600)
        restored = os.fstat(fd)
        require((restored.st_uid, restored.st_gid, stat.S_IMODE(restored.st_mode)) == (uid, gid, 0o600), 'restoration')
        metadata('after', name, restored)
except (OSError, ValueError, KeyError) as error:
    print(f"COOKIE_JAR_HANDOFF_FAILED phase={phase} type={type(error).__name__} errno={getattr(error, 'errno', None)}", file=sys.stderr)
    sys.exit(1)
finally:
    for fd in reversed(fds):
        os.close(fd)
'@
    Invoke-Tool 'sudo' @('--non-interactive', '--', 'python3', '-I', '-c', $code, $Temporary, $parent, $uid, $gid, $Phase)
}

function Remove-DisposableDockerResources([string]$Kind, [string[]]$Names) {
    # Enumerate first: resources may not exist when setup fails. Never prune.
    $format = if ($Kind -eq 'image') { '{{.Repository}}' } else { '{{.Name}}' }
    if ($Kind -eq 'container') { $format = '{{.Names}}' }
    $arguments = @($Kind, 'ls', '--format', $format)
    if ($Kind -eq 'container') { $arguments += '--all' }
    $existing = @(& docker @arguments)
    if ($LASTEXITCODE -ne 0) { Fail-Gate 'cleanup' "cannot enumerate $Kind resources" }
    foreach ($name in $Names) {
        if ($existing -notcontains $name) { continue }
        $remove = @($Kind, 'rm')
        if ($Kind -eq 'container') { $remove += @('--force', '--volumes') }
        if ($Kind -eq 'image') { $remove += '--force' }
        Invoke-Tool 'docker' ($remove + $name) | Out-Host
    }
}

function Export-GateEvidence([string]$Temporary) {
    if ($EvidenceDirectory -eq '') { return }
    # Only synthetic screenshots and source hashes; never browser profiles/jars.
    foreach ($path in @('source.manifest', 'screenshots')) {
        $source = Join-Path $Temporary $path
        if (Test-Path -LiteralPath $source) { Copy-Item -LiteralPath $source -Destination $EvidenceDirectory -Recurse -Force }
    }
}

function Remove-GateTemporary([string]$Path) {
    $target = [IO.Path]::GetFullPath($Path)
    $parent = [IO.Path]::GetDirectoryName($target).TrimEnd([IO.Path]::DirectorySeparatorChar)
    $expected = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd([IO.Path]::DirectorySeparatorChar)
    if ($parent -ne $expected -or [IO.Path]::GetFileName($target) -notmatch '^ather-evidence-hub-(gates|focused|playwright)-[a-f0-9]{32}$') { Fail-Gate 'cleanup' 'temporary target is outside the run-owned namespace' }
    Remove-Item -LiteralPath $target -Recurse -Force
}

function Complete-GateCleanup([string]$Temporary, [hashtable]$Playwright, [string[]]$Images) {
    $failures = @()
    try { Export-GateEvidence $Temporary } catch { $failures += 'evidence export' }
    if ($Playwright) {
        try { Remove-GateTemporary $Playwright.Directory } catch { $failures += 'temporary Playwright' }
    }
    try { Remove-GateTemporary $Temporary } catch { $failures += 'temporary gate files' }
    try { Remove-DisposableDockerResources 'image' $Images } catch { $failures += 'disposable images' }
    if ($failures.Count -gt 0) { Fail-Gate 'cleanup' ($failures -join ', ') }
}

function Get-SourceFiles([string]$Root) {
    # Docker deliberately omits .gitignore and the agent guide AGENTS.md from its
    # build context. They are not runtime source, so the identity manifest
    # excludes them rather than masking a real application-source mismatch.
    $excluded = @('.git', 'vendor', 'node_modules', 'runtime', '.env', '.gitignore', 'AGENTS.md', '.vscode')
    Get-ChildItem -LiteralPath $Root -Recurse -File -Force | Where-Object {
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
        'EvidenceHubTimestampMySqlTest', 'EvidenceHubSkillEvidenceTest', 'EvidenceHubContractV8Test', 'EvidenceTextRules110Test', 'EvidenceProjectTextRules110Test', 'EvidenceHubContractV9Test', 'EvidenceTextOwnerHintsTest', 'tests/Integration/check-evidence-edit-http.php::textRules110', 'tests/Browser/evidence-hub-text-rules-visual.cjs', 'EvidenceHubRecommendationDispositionMySqlTest::skillEvidenceLifecycle', 'EvidenceHubRecommendationActionHttpMySqlTest::skillEvidenceLifecycle',
        'tests/Browser/evidence-hub-owner-visual.cjs',
        'tests/Browser/evidence-hub-owner-actions-visual.cjs',
        'tests/Browser/evidence-hub-editor-writing-visual.cjs',
        'tests/Browser/evidence-hub-technology-review-visual.cjs',
        'tests/Browser/evidence-hub-skill-evidence-visual.cjs'
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
    foreach ($path in @('handlers/owner_evidence_hub.php', 'public/assets/css/evidence_hub.css', 'public/assets/js/evidence_hub.js', 'public/assets/js/owner_theme.js', 'database/migrate.php')) {
        if (-not (Test-Path -LiteralPath (Join-Path $Root $path))) { Fail-Gate 'source identity' "required source path $path is absent" }
    }
    # Every target shares one layout: code in /var/www/app, and the document
    # root is an exact copy of the repository public/ tree.
    $check = "set -eu; cd /var/www/app; sha256sum -c /audit/source.manifest; diff -r /var/www/app/public /var/www/public; test -f database/migrations/011_recommendation_dispositions.sql; test -f handlers/owner_evidence_hub.php; test -f public/assets/css/evidence_hub.css; test -f public/assets/js/evidence_hub.js; test -f public/assets/js/owner_theme.js"
    if ($Layout -eq 'production') { $check += '; test "${PORTFOLIO_PRODUCTION_SECURITY_CHECK:-}" = 1; test -f /usr/local/etc/php/conf.d/portfolio-production.ini' }
    & docker run --rm --entrypoint sh -v "${Root}:/verification-source:ro" -v "${Manifest}:/audit/source.manifest:ro" $Image -lc $check
    if ($LASTEXITCODE -ne 0) { Fail-Gate 'source identity' 'container-visible application source differs from the intended verification source' }
}

function New-TemporaryPlaywright([string]$Root) {
    $temporary = Join-Path ([IO.Path]::GetTempPath()) ("ather-evidence-hub-playwright-" + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $temporary | Out-Null
    try {
        $env:PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD = '1'
        if ($InstallChromium) { $env:PLAYWRIGHT_BROWSERS_PATH = '0' }
        Push-Location $temporary
        try { Invoke-Tool 'npm' @('install', '--ignore-scripts', '--no-save', '--package-lock=false', 'playwright@1.58.2') | Out-Host } finally { Pop-Location }
        $module = Join-Path $temporary 'node_modules/playwright'
        $package = Get-Content -Raw -LiteralPath (Join-Path $module 'package.json') | ConvertFrom-Json
        if ($package.version -ne '1.58.2') { Fail-Gate 'Playwright' "expected 1.58.2; received $($package.version)" }
        $browsers = Get-Content -Raw -LiteralPath (Join-Path $temporary 'node_modules/playwright-core/browsers.json') | ConvertFrom-Json
        $chromium = @($browsers.browsers | Where-Object name -eq 'chromium')
        if ($chromium.Count -ne 1 -or $chromium[0].revision -ne '1208' -or $chromium[0].browserVersion -ne '145.0.7632.6') { Fail-Gate 'Playwright' 'full Chromium revision 1208 / version 145.0.7632.6 is not declared' }
        if ($InstallChromium) {
            Invoke-Tool 'node' @((Join-Path $module 'cli.js'), 'install', '--with-deps', '--no-shell', 'chromium') | Out-Host
        }
        return @{ Directory = $temporary; Module = $module }
    } catch { try { Remove-GateTemporary $temporary } catch { Write-Warning 'Temporary Playwright cleanup failed' }; throw }
}

function Get-Chromium1208Executable([hashtable]$Playwright) {
    $cache = (& node -e 'process.stdout.write(require(process.argv[1]).chromium.executablePath())' $Playwright.Module)
    if ($LASTEXITCODE -ne 0 -or ($cache -replace '\\', '/') -notmatch '/chromium-1208/') { Fail-Gate 'Playwright' 'full Chromium revision 1208 executable resolution failed' }
    if (-not (Test-Path -LiteralPath $cache) -or (Get-Item -LiteralPath $cache).Length -eq 0) { Fail-Gate 'Playwright' 'validated full Chromium revision 1208 is unavailable' }
    if ($IsWindows) {
        $version = [string](Get-Item -LiteralPath $cache).VersionInfo.ProductVersion
        if ($version -ne '145.0.7632.6') { Fail-Gate 'Playwright' 'Chromium product version is incompatible' }
    } else {
        $version = ((& $cache --version) -join '').Trim()
        if ($LASTEXITCODE -ne 0 -or $version -notmatch '^(Chromium|Google Chrome for Testing) 145\.0\.7632\.6$') { Fail-Gate 'Playwright' 'Chromium executable version is incompatible' }
    }
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
    $sink = if ($IsWindows) { 'NUL' } else { '/dev/null' }
    $arguments = @('--silent', '--show-error', '--output', $sink, '--write-out', '%{http_code}', '--max-redirs', '0', '--request', $Method)
    foreach ($name in $Headers.Keys) { $arguments += @('--header', "$name`:$($Headers[$name])") }
    if ($CookieJar -ne '') { $arguments += @('--cookie', $CookieJar, '--cookie-jar', $CookieJar) }
    $status = @(& (Get-NativeCurl) @arguments $Url)
    if ($LASTEXITCODE -ne 0) { Fail-Gate 'web bootstrap' 'native cookie-jar HTTP client failed' }
    if ($CookieJar -ne '' -and (Test-Path -LiteralPath $CookieJar)) {
        foreach ($line in Get-Content -LiteralPath $CookieJar) {
            if ($line -match '(^|\t)portfolio_owner_session\t') { Protect-GateSecret (($line -split "`t")[-1]) }
        }
    }
    return ($status -join '').Trim()
}

# The document root is the repository public/ tree only (docs/RUNTIME_TOPOLOGY.md).
# Probe real internal files that exist in the image: each must be refused, while
# every kind of published asset and the liveness probe must be served directly.
function Assert-PublicBoundary([string]$BaseUrl, [string]$Label) {
    foreach ($path in @('/tests/Legacy/cases/AccountSelectionStaticTest.php', '/scripts/README.md', '/scripts/check-production-security.php', '/docs/README.md', '/vendor/autoload.php', '/vendor/composer/installed.json', '/composer.json', '/composer.lock', '/phpstan.neon.dist', '/contracts/evidence-hub-contract-v1.schema.json', '/data/profile-catalog/catalog.json', '/config/database.php', '/includes/presentation.php', '/src/EvidenceHub/Text/EvidenceTextEvaluator.php', '/src/', '/database/portfolio_db.sql', '/docker/production-entrypoint.sh', '/Dockerfile', '/docker-compose.yml', '/.env', '/.git/HEAD', '/public/index.php', '/app/index.php', '/tests/', '/vendor/', '/includes/')) {
        $status = Invoke-DisposableHttpStatus "$BaseUrl$path"
        if ($status -notin @('403', '404')) { Fail-Gate 'public boundary' "$Label served internal path $path with status $status" }
    }
    foreach ($path in @('/assets/css/evidence_hub.css', '/assets/js/evidence_hub.js', '/assets/js/owner_theme.js', '/assets/fonts/noto-sans-arabic-400.ttf', '/assets/images/ather-navbar-logo.png', '/assets/images/legacy-professional-portfolio.png', '/health.php')) {
        $status = Invoke-DisposableHttpStatus "$BaseUrl$path"
        if ($status -ne '200') { Fail-Gate 'public boundary' "$Label did not serve public path $path directly (status $status)" }
    }
    foreach ($path in @('/evidence_hub.css', '/style.css', '/admin.js', '/ChatGPT-Image-Apr-14-2025-065043-PM.png')) {
        $status = Invoke-DisposableHttpStatus "$BaseUrl$path"
        if ($status -ne '404') { Fail-Gate 'public boundary' "$Label still serves retired root asset $path (status $status)" }
    }
    # Front controller (ENG-09): the Auth0 callback keeps no-referrer through the
    # internal rewrite, and unknown paths keep Apache's default 404 exactly.
    $callback = Invoke-WebRequest -UseBasicParsing -MaximumRedirection 0 -SkipHttpErrorCheck -TimeoutSec 10 "$BaseUrl/owner_oidc_callback.php"
    if ("$($callback.Headers['Referrer-Policy'])" -ne 'no-referrer') { Fail-Gate 'public boundary' "$Label OIDC callback lost Referrer-Policy no-referrer" }
    $signin = Invoke-WebRequest -UseBasicParsing -MaximumRedirection 0 -SkipHttpErrorCheck -TimeoutSec 10 "$BaseUrl/signin.php"
    if ("$($signin.Headers['Referrer-Policy'])" -ne 'strict-origin-when-cross-origin') { Fail-Gate 'public boundary' "$Label default Referrer-Policy changed" }
    $missing = Invoke-WebRequest -UseBasicParsing -MaximumRedirection 0 -SkipHttpErrorCheck -TimeoutSec 10 "$BaseUrl/no-such-page"
    if ($missing.StatusCode -ne 404 -or "$($missing.Headers['Content-Type'])" -ne 'text/html; charset=iso-8859-1' -or $missing.Headers.ContainsKey('X-Powered-By') -or
        -not "$($missing.Content)".Contains('<p>The requested URL was not found on this server.</p>')) {
        Fail-Gate 'public boundary' "$Label unknown paths no longer get the Apache-identical 404"
    }
    Write-Host "PASS public boundary ($Label)"
}

# Serve the packaged production image without a database: enough to prove what
# its document root exposes, with the image's own Apache configuration.
function Invoke-ProductionBoundaryGate([string]$Image) {
    $container = "ather-evidence-hub-boundary-$([guid]::NewGuid().ToString('N'))"
    $port = Get-FreeLoopbackPort
    try {
        Invoke-Tool 'docker' @('run', '--detach', '--name', $container, '-p', "127.0.0.1:${port}:80", '--entrypoint', 'docker-php-entrypoint', $Image, 'apache2-foreground')
        # Until Apache listens, Docker's port proxy can return an empty reply; the
        # strict curl helper would abort on that, so wait with a tolerant probe.
        for ($attempt = 0; $attempt -lt 60; $attempt++) {
            try { if ((Invoke-WebRequest -UseBasicParsing -TimeoutSec 2 "http://127.0.0.1:$port/health.php").StatusCode -eq 200) { break } } catch { }
            Start-Sleep -Milliseconds 500
        }
        try { $null = Invoke-WebRequest -UseBasicParsing -TimeoutSec 2 "http://127.0.0.1:$port/health.php" } catch {
            Fail-Gate 'public boundary' 'production image did not become reachable'
        }
        Assert-PublicBoundary "http://127.0.0.1:$port" 'production image'
    } finally {
        Remove-DisposableDockerResources 'container' @($container)
    }
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
    foreach ($secret in @($databasePassword, $hmac, $bootstrapNonceA, $bootstrapNonceB)) { Protect-GateSecret $secret }
    if ($bootstrapNonceA -eq $bootstrapNonceB) { Fail-Gate 'web bootstrap' 'bootstrap nonces must be unique' }
    $bootstrapSubjectA = "evidence-hub-gate-owner-a-$run"
    $bootstrapSubjectB = "evidence-hub-gate-owner-b-$run"
    $bootstrapState = "/var/lib/ather-career/bootstrap-state/$run"
    $bootstrapPath = 'evidence-hub-gate-' + $run.Substring(0, 16) + '.php'
    $port = Get-FreeLoopbackPort
    $bridge = Join-Path $Temporary 'bridge'
    $profile = Join-Path $Temporary 'browser-profile'; $screenshots = Join-Path $Temporary 'screenshots'
    New-Item -ItemType Directory -Path $bridge,$profile,$screenshots | Out-Null
    if ($IsLinux) {
        $parent = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('/')
        if ([IO.Path]::GetDirectoryName([IO.Path]::GetFullPath($Temporary)) -ne $parent -or [IO.Path]::GetFileName($Temporary) -notmatch '^ather-evidence-hub-(gates|focused)-[a-f0-9]{32}$') { Fail-Gate 'cookie handoff' 'invalid run-owned directory' }
        foreach ($directory in @($Temporary, $bridge, $profile, $screenshots)) {
            if ((Get-Item -LiteralPath $directory -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { Fail-Gate 'cookie handoff' 'unexpected directory symlink' }
            [IO.File]::SetUnixFileMode($directory, [IO.UnixFileMode]::UserRead -bor [IO.UnixFileMode]::UserWrite -bor [IO.UnixFileMode]::UserExecute)
        }
    }
    $ownerAJar = Join-Path $bridge 'owner-a.cookiejar'
    $ownerBJar = Join-Path $bridge 'owner-b.cookiejar'
    $invalidJar = Join-Path $bridge 'fabricated.cookiejar'
    [IO.File]::WriteAllText($ownerAJar, '', [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText($ownerBJar, '', [Text.UTF8Encoding]::new($false))
    try {
        Restore-LinuxCookieJarOwnership $Temporary 'initial'
        Invoke-Tool 'docker' @('network', 'create', $network)
        Invoke-Tool 'docker' @('run', '--detach', '--name', $database, '--network', $network, '--network-alias', 'evidencehubdb', '-e', "MYSQL_DATABASE=$databaseName", '-e', "MYSQL_USER=$databaseUser", '-e', "MYSQL_PASSWORD=$databasePassword", '-e', "MYSQL_ROOT_PASSWORD=$databasePassword", '-v', "$(Join-Path $Root 'database/portfolio_db.sql'):/docker-entrypoint-initdb.d/01-portfolio.sql:ro", 'mysql:8.4')
        Wait-DisposableMySql $database
        $baseEnvironment = @('-e', 'APP_ENV=test', '-e', 'ATHERCAR_TEST_MODE=1', '-e', 'ATHERCAR_STORAGE_ROOT=/var/lib/ather-career/storage', '-e', 'DB_HOST=evidencehubdb', '-e', 'DB_PORT=3306', '-e', "DB_NAME=$databaseName", '-e', "DB_USER=$databaseUser", '-e', "DB_PASSWORD=$databasePassword", '-e', "EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY=$hmac", '-e', 'EXPECTED_OIDC_ISSUER=https://oidc-gate.invalid/', '-e', 'OIDC_CLIENT_ID=synthetic-gate-client', '-e', 'OIDC_CLIENT_SECRET=synthetic-gate-secret', '-e', 'OIDC_REDIRECT_URI=https://portfolio-gate.invalid/owner_oidc_callback.php', '-e', 'PRESERVED_V1_OIDC_SUBJECT=synthetic-preserved-owner', '-e', "EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A=$bootstrapSubjectA", '-e', "EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B=$bootstrapSubjectB", '-e', 'SESSION_COOKIE_SECURE=false', '-e', 'PUBLIC_BASE_URL=http://127.0.0.1')
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @($Image, 'database/migrate.php', '--through=003'))
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @($Image, 'database/production-ownership-bootstrap.php'))
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @($Image, 'database/migrate.php'))
        $repeat = & docker run --rm --network $network --entrypoint php @baseEnvironment $Image database/migrate.php 2>&1
        if ($LASTEXITCODE -ne 0 -or ($repeat -join "`n") -notmatch 'No pending migrations\.') { Fail-Gate 'migration repeat' 'No pending migrations. was not reported after migration 012' }
        Invoke-Tool 'docker' @('exec', '-e', "MYSQL_PWD=$databasePassword", $database, 'mysql', '-u', 'root', '-e', "CREATE DATABASE $timestampDatabaseName")
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @('-e', "DB_NAME=$timestampDatabaseName", '-e', 'DB_USER=root', '-e', "DB_PASSWORD=$databasePassword", '-e', 'EVIDENCE_HUB_TIMESTAMP_MYSQL_TEST=1', $Image, 'tests/Integration/run-evidence-hub-mysql-timestamp-test.php'))
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @('-e', 'EVIDENCE_HUB_R3_MYSQL_TEST=1', $Image, 'tests/Integration/run-evidence-hub-mysql-disposition-test.php'))
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @('-e', 'EVIDENCE_HUB_HTTP_ACTION_TEST=1', $Image, 'tests/Integration/run-evidence-hub-http-action-test.php', '--seed'))
        # These fixtures model existing owners. Seal their legacy ownership while no Apache/writers are running.
        Invoke-Tool 'docker' (@('run', '--rm', '--network', $network, '--entrypoint', 'php') + $baseEnvironment + @($Image, 'database/capture-owner-journey-cutover.php', '--confirm-exclusive-maintenance', "--database=$databaseName"))
        $webBootstrapEnvironment = @('-e', 'EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP=1', '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_NONCE_A=$bootstrapNonceA", '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_SUBJECT_A=$bootstrapSubjectA", '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_NONCE_B=$bootstrapNonceB", '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_SUBJECT_B=$bootstrapSubjectB", '-e', "EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_STATE_DIR=$bootstrapState")
        Invoke-Tool 'docker' (@('run', '--detach', '--name', $web, '--network', $network, '-p', "127.0.0.1:${port}:80", '-v', "${bridge}:/tmp/bridge", '-v', "$(Join-Path $Root 'tests/Support/apache-gate-fixtures.conf'):/etc/apache2/conf-enabled/zzzz-gate-fixtures.conf:ro", '-e', 'EVIDENCE_HUB_VISUAL_TEST=1') + $webBootstrapEnvironment + $baseEnvironment + @($Image))
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
        Assert-PublicBoundary "http://127.0.0.1:$port" 'development image'
        Invoke-Tool 'docker' @('exec', $web, 'install', '-d', '-m', '0700', '-o', 'www-data', '-g', 'www-data', $bootstrapState)
        # The bootstrap handler lives with the application code; a one-line
        # front controller (the public/ shim pattern) exposes it temporarily.
        Invoke-Tool 'docker' @('cp', (Join-Path $Root 'tests/Support/evidence-hub-web-session-bootstrap.php'), "${web}:/var/www/app/$bootstrapPath")
        $bootstrapShim = Join-Path $Temporary 'bootstrap-front-controller.php'
        [IO.File]::WriteAllText($bootstrapShim, "<?php`nrequire dirname(__DIR__) . '/app/$bootstrapPath';`n", [Text.UTF8Encoding]::new($false))
        Invoke-Tool 'docker' @('cp', $bootstrapShim, "${web}:/var/www/public/$bootstrapPath")
        $baseUrl = "http://127.0.0.1:$port"
        if ((Invoke-DisposableHttpStatus "$baseUrl/owner/evidence-hub") -ne '303') { Fail-Gate 'web bootstrap' 'anonymous protected route did not redirect' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath") -ne '405') { Fail-Gate 'web bootstrap' 'GET bootstrap was not rejected' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath" 'POST' @{ 'X-Evidence-Hub-Bootstrap-Nonce' = 'invalid' }) -ne '404') { Fail-Gate 'web bootstrap' 'wrong bootstrap nonce was not rejected' }
        $ownerABootstrapStatus = Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath" 'POST' @{ 'X-Evidence-Hub-Bootstrap-Nonce' = $bootstrapNonceA } $ownerAJar
        if ($ownerABootstrapStatus -ne '204') {
            $bootstrapDiagnostic = @(& docker logs --tail 20 $web 2>&1 | Select-String -Pattern 'Evidence Hub Web-SAPI bootstrap failed' | ForEach-Object { $_.ToString() }) -join ' '
            $sink = if ($IsWindows) { 'NUL' } else { '/dev/null' }
            $bootstrapResult = @(& (Get-NativeCurl) --silent --show-error --dump-header - --output $sink --max-redirs 0 --request POST --header "X-Evidence-Hub-Bootstrap-Nonce:$bootstrapNonceA" "$baseUrl/$bootstrapPath" 2>$null | Select-String -Pattern '^X-Evidence-Hub-Bootstrap-Result:' | ForEach-Object { $_.ToString().Trim() }) -join ' '
            Fail-Gate 'web bootstrap' ("Owner A Apache bootstrap returned HTTP $ownerABootstrapStatus" + $(if ($bootstrapResult -eq '') { '' } else { "; $bootstrapResult" }) + $(if ($bootstrapDiagnostic -eq '') { '' } else { "; $bootstrapDiagnostic" }))
        }
        if ((Get-Item -LiteralPath $ownerAJar).Length -eq 0) { Fail-Gate 'web bootstrap' 'Owner A Apache cookie jar is empty' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath" 'POST' @{ 'X-Evidence-Hub-Bootstrap-Nonce' = $bootstrapNonceA } $ownerAJar) -ne '404') { Fail-Gate 'web bootstrap' 'Owner A bootstrap nonce replay was accepted' }
        $ownerBBootstrapStatus = Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath" 'POST' @{ 'X-Evidence-Hub-Bootstrap-Nonce' = $bootstrapNonceB } $ownerBJar
        if ($ownerBBootstrapStatus -ne '204') { Fail-Gate 'web bootstrap' 'Owner B Apache bootstrap did not establish a session' }
        if ((Get-Item -LiteralPath $ownerBJar).Length -eq 0 -or (Get-FileHash -Algorithm SHA256 $ownerAJar).Hash -eq (Get-FileHash -Algorithm SHA256 $ownerBJar).Hash) { Fail-Gate 'web bootstrap' 'two independent Apache cookie jars were not established' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath" 'POST' @{ 'X-Evidence-Hub-Bootstrap-Nonce' = $bootstrapNonceB } $ownerBJar) -ne '404') { Fail-Gate 'web bootstrap' 'Owner B bootstrap nonce replay was accepted' }
        Invoke-Tool 'docker' @('exec', $web, 'rm', '-f', "/var/www/public/$bootstrapPath", "/var/www/app/$bootstrapPath")
        if ((Invoke-DisposableHttpStatus "$baseUrl/$bootstrapPath") -ne '404') { Fail-Gate 'web bootstrap' 'bootstrap endpoint remained reachable after removal' }
        if ((Invoke-DisposableHttpStatus "$baseUrl/owner/evidence-hub" 'GET' @{} $ownerAJar) -ne '200' -or (Invoke-DisposableHttpStatus "$baseUrl/owner/evidence-hub" 'GET' @{} $ownerBJar) -ne '200') { Fail-Gate 'web bootstrap' 'Apache-issued cookie jar did not authenticate both owners' }
        $invalidLines = Get-Content -LiteralPath $ownerAJar
        $invalidLines = $invalidLines | ForEach-Object { if (($_ -match '^#' -and $_ -notmatch '^#HttpOnly_') -or $_ -notmatch "`t") { $_ } else { $fields = $_ -split "`t"; $fields[$fields.Count - 1] = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant(); $fields -join "`t" } }
        [IO.File]::WriteAllLines($invalidJar, [string[]]$invalidLines, [Text.UTF8Encoding]::new($false))
        Restore-LinuxCookieJarOwnership $Temporary 'before-http'
        if ($IsLinux) { Invoke-Tool 'docker' @('exec', $web, 'sh', '-ec', 'uid=$(id -u); gid=$(id -g); printf "COOKIE_JAR_WRITER consumer=http uid=%s gid=%s\n" "$uid" "$gid"') }
        Invoke-Tool 'docker' (@('exec') + $baseEnvironment + @('-e', 'EVIDENCE_HUB_HTTP_ACTION_TEST=1', '-e', 'EVIDENCE_HUB_HTTP_ACTION_BASE_URL=http://127.0.0.1', '-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_A=/tmp/bridge/owner-a.cookiejar', '-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_B=/tmp/bridge/owner-b.cookiejar', '-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_INVALID=/tmp/bridge/fabricated.cookiejar', $web, 'php', 'tests/Integration/run-evidence-hub-http-action-test.php'))
        Restore-LinuxCookieJarOwnership $Temporary 'after-http'
        if ($null -ne $Playwright) {
            $sessionLine = Get-Content -LiteralPath $ownerAJar | Where-Object { $_ -match '(^|\t)portfolio_owner_session\t' -or $_ -match '^#HttpOnly_.*\tportfolio_owner_session\t' } | Select-Object -First 1
            if (-not [string]::IsNullOrWhiteSpace($sessionLine)) { $actionSession = ($sessionLine -split "`t")[-1] } else { $actionSession = '' }
            if ([string]::IsNullOrWhiteSpace($actionSession)) { Fail-Gate 'action visual session' 'Owner A native cookie jar did not contain an application session.' }
            Protect-GateSecret $actionSession
            $env:PLAYWRIGHT_MODULE = $Playwright.Module; $env:EVIDENCE_HUB_EDGE_EXECUTABLE = $Chromium; $env:EVIDENCE_HUB_BROWSER_PROFILE = $profile; $env:EVIDENCE_HUB_VISUAL_BASE_URL = "http://127.0.0.1:$port"; $env:EVIDENCE_HUB_ACTION_TEST_URL = "http://127.0.0.1:$port"; $env:EVIDENCE_HUB_ACTION_SESSION_COOKIE = $actionSession
            Invoke-Tool 'node' @((Join-Path $Root 'tests/Browser/evidence-hub-owner-visual.cjs'), $screenshots)
            Invoke-Tool 'node' @((Join-Path $Root 'tests/Browser/evidence-hub-owner-actions-visual.cjs'))
            Invoke-Tool 'node' @((Join-Path $Root 'tests/Browser/evidence-hub-editor-writing-visual.cjs'))
            Invoke-Tool 'node' @((Join-Path $Root 'tests/Browser/evidence-hub-technology-review-visual.cjs'))
            Invoke-Tool 'node' @((Join-Path $Root 'tests/Browser/evidence-hub-skill-evidence-visual.cjs'))
            Invoke-Tool 'node' @((Join-Path $Root 'tests/Browser/evidence-hub-text-rules-visual.cjs'))
        }
        Restore-LinuxCookieJarOwnership $Temporary 'before-editor'
        if ($IsLinux) { Invoke-Tool 'docker' @('exec', $web, 'sh', '-ec', 'uid=$(id -u); gid=$(id -g); printf "COOKIE_JAR_WRITER consumer=editor uid=%s gid=%s\n" "$uid" "$gid"') }
        Invoke-Tool 'docker' (@('exec') + $baseEnvironment + @('-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_A=/tmp/bridge/owner-a.cookiejar', '-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_B=/tmp/bridge/owner-b.cookiejar', $web, 'php', 'tests/Integration/check-evidence-edit-http.php'))
        Invoke-Tool 'docker' (@('exec') + $baseEnvironment + @('-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_A=/tmp/bridge/owner-a.cookiejar', '-e', 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_B=/tmp/bridge/owner-b.cookiejar', $web, 'php', 'tests/Integration/check-evidence-technology-review-http.php'))
        Restore-LinuxCookieJarOwnership $Temporary 'after-editor'
    } finally {
        & docker exec $web rm -rf $bootstrapState 2>$null | Out-Null
        Remove-Item -LiteralPath $ownerAJar,$ownerBJar,$invalidJar -Force -ErrorAction SilentlyContinue
        try { Remove-DisposableDockerResources 'container' @($web, $database) } finally {
            Remove-DisposableDockerResources 'network' @($network)
        }
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
            'EvidenceHubTimestampMySqlTest', 'EvidenceHubSkillEvidenceTest', 'EvidenceHubContractV8Test', 'EvidenceTextRules110Test', 'EvidenceProjectTextRules110Test', 'EvidenceHubContractV9Test', 'EvidenceTextOwnerHintsTest', 'tests/Integration/check-evidence-edit-http.php::textRules110', 'tests/Browser/evidence-hub-text-rules-visual.cjs', 'EvidenceHubRecommendationDispositionMySqlTest::skillEvidenceLifecycle', 'EvidenceHubRecommendationActionHttpMySqlTest::skillEvidenceLifecycle',
            'tests/Browser/evidence-hub-owner-visual.cjs',
            'tests/Browser/evidence-hub-owner-actions-visual.cjs',
            'tests/Browser/evidence-hub-editor-writing-visual.cjs',
            'tests/Browser/evidence-hub-technology-review-visual.cjs',
            'tests/Browser/evidence-hub-skill-evidence-visual.cjs'
        )
        Assert-RequiredGateRegistry $registry
        Invoke-RequiredGateRegistryChallenges $registry
        Invoke-Tool 'docker' @('build', '--pull=false', '-f', (Join-Path $resolvedRoot 'Dockerfile'), '--target', 'development', '-t', $devImage, $resolvedRoot)
        Assert-CurrentSourceIdentity $resolvedRoot $devImage 'development' $manifest
        Invoke-Tool 'docker' @('build', '--pull=false', '-f', (Join-Path $resolvedRoot 'Dockerfile'), '--target', 'production', '-t', $productionImage, $resolvedRoot)
        Assert-CurrentSourceIdentity $resolvedRoot $productionImage 'production' $manifest
        Invoke-ProductionBoundaryGate $productionImage
        & docker run --rm --entrypoint php $devImage -r 'foreach (["pdo_mysql","gd","intl","mbstring"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, $extension . " unavailable\\n"); exit(1); } } if (!function_exists("grapheme_strlen")) { exit(1); } echo "PHP_CAPABILITIES_OK\\n";'
        if ($LASTEXITCODE -ne 0) { Fail-Gate 'PHP capabilities' 'required verification capability is unavailable' }
        # Run the source baked into the identity-checked image: it carries the
        # Composer autoloader for src/, which a fresh checkout does not have.
        & docker run --rm --entrypoint php -w /var/www/app -e APP_ENV=test -e ATHERCAR_TEST_MODE=1 $devImage scripts/run-phase2-tests.php
        if ($LASTEXITCODE -ne 0) { Fail-Gate 'Phase-2' 'fast deterministic suite failed' }
        # The development image carries PHPUnit: the same registry plus native tests/Unit tests.
        & docker run --rm --entrypoint php -w /var/www/app -e APP_ENV=test -e ATHERCAR_TEST_MODE=1 $devImage vendor/bin/phpunit --do-not-cache-result
        if ($LASTEXITCODE -ne 0) { Fail-Gate 'PHPUnit' 'registered Phase 2 cases or native PHPUnit tests failed' }
        $playwright = New-TemporaryPlaywright $resolvedRoot
        $chrome = Get-Chromium1208Executable $playwright
        Invoke-RequiredMySqlAndBrowserGates $resolvedRoot $devImage $playwright $chrome $temporary
        $productionKey = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
        Protect-GateSecret $productionKey
        & docker run --rm --entrypoint php -e APP_ENV=production -e PUBLIC_BASE_URL=https://portfolio-gate.invalid -e SESSION_COOKIE_SECURE=true -e EXPECTED_OIDC_ISSUER=https://oidc-gate.invalid/ -e OIDC_CLIENT_ID=synthetic-gate-client -e OIDC_CLIENT_SECRET=synthetic-gate-secret -e OIDC_REDIRECT_URI=https://portfolio-gate.invalid/owner_oidc_callback.php -e EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY=$productionKey $productionImage scripts/check-production-security.php
        if ($LASTEXITCODE -ne 0) { Fail-Gate 'production security' 'production security gate failed' }
        Write-Host 'REQUIRED_GATES_PASSED'
    } finally {
        Complete-GateCleanup $temporary $playwright @($devImage, $productionImage)
    }
}

function Invoke-FocusedTwoOwnerGate {
    $resolvedRoot = (Resolve-Path -LiteralPath $SourceRoot).Path
    $temporary = Join-Path ([IO.Path]::GetTempPath()) ("ather-evidence-hub-focused-" + [guid]::NewGuid().ToString('N'))
    $manifest = Join-Path $temporary 'source.manifest'
    $image = "ather-evidence-hub-focused-$([guid]::NewGuid().ToString('N'))"
    $playwright = $null
    New-Item -ItemType Directory -Path $temporary | Out-Null
    try {
        New-SourceManifest $resolvedRoot $manifest | Out-Null
        Invoke-Tool 'docker' @('build', '--pull=false', '-f', (Join-Path $resolvedRoot 'Dockerfile'), '--target', 'development', '-t', $image, $resolvedRoot)
        Assert-CurrentSourceIdentity $resolvedRoot $image 'development' $manifest
        $playwright = New-TemporaryPlaywright $resolvedRoot
        $chromium = Get-Chromium1208Executable $playwright
        Invoke-RequiredMySqlAndBrowserGates $resolvedRoot $image $playwright $chromium $temporary
        Write-Host 'FOCUSED_TWO_OWNER_GATE_PASSED'
    } finally {
        Complete-GateCleanup $temporary $playwright @($image)
    }
}

if ($IdentityChallenge -or $RegistrationChallenge) {
    $registry = @('EvidenceHubRecommendationActionHttpMySqlTest', 'EvidenceHubRecommendationDispositionMySqlTest', 'EvidenceHubTimestampMySqlTest', 'EvidenceHubSkillEvidenceTest', 'EvidenceHubContractV8Test', 'EvidenceTextRules110Test', 'EvidenceProjectTextRules110Test', 'EvidenceHubContractV9Test', 'EvidenceTextOwnerHintsTest', 'tests/Integration/check-evidence-edit-http.php::textRules110', 'tests/Browser/evidence-hub-text-rules-visual.cjs', 'EvidenceHubRecommendationDispositionMySqlTest::skillEvidenceLifecycle', 'EvidenceHubRecommendationActionHttpMySqlTest::skillEvidenceLifecycle', 'tests/Browser/evidence-hub-owner-visual.cjs', 'tests/Browser/evidence-hub-owner-actions-visual.cjs', 'tests/Browser/evidence-hub-editor-writing-visual.cjs', 'tests/Browser/evidence-hub-technology-review-visual.cjs', 'tests/Browser/evidence-hub-skill-evidence-visual.cjs')
    if ($RegistrationChallenge) { Invoke-RequiredGateRegistryChallenges $registry; Write-Host 'REGISTRATION_CHALLENGES_PASSED' }
    if ($IdentityChallenge) { Write-Host 'Identity challenge is exercised by Assert-CurrentSourceIdentity against a deliberately stale disposable image.' }
    exit 0
}

$savedEnvironment = @{}
foreach ($name in @('PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD', 'PLAYWRIGHT_BROWSERS_PATH', 'PLAYWRIGHT_MODULE', 'EVIDENCE_HUB_EDGE_EXECUTABLE', 'EVIDENCE_HUB_BROWSER_PROFILE', 'EVIDENCE_HUB_VISUAL_BASE_URL', 'EVIDENCE_HUB_ACTION_TEST_URL', 'EVIDENCE_HUB_ACTION_SESSION_COOKIE')) {
    $savedEnvironment[$name] = [Environment]::GetEnvironmentVariable($name)
}
Push-Location (Resolve-Path -LiteralPath $SourceRoot).Path
try {
    if ($EvidenceDirectory -ne '') {
        $EvidenceDirectory = [IO.Path]::GetFullPath($EvidenceDirectory)
        $root = (Get-Location).Path.TrimEnd([IO.Path]::DirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar
        if ($EvidenceDirectory.StartsWith($root, [StringComparison]::OrdinalIgnoreCase) -or $EvidenceDirectory -eq (Get-Location).Path) { Fail-Gate 'evidence' 'evidence must be outside verification source' }
        if (Test-Path -LiteralPath $EvidenceDirectory) { Fail-Gate 'evidence' 'evidence destination must be new' }
        New-Item -ItemType Directory -Path $EvidenceDirectory | Out-Null
    }
    if ($Focused) { Invoke-FocusedTwoOwnerGate } else { Invoke-RequiredGates }
} finally {
    Pop-Location
    foreach ($name in $savedEnvironment.Keys) { [Environment]::SetEnvironmentVariable($name, $savedEnvironment[$name]) }
}
