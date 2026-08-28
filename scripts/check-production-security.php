<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Production security gate must run from the CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/session.php';

function productionSecurityDirectiveIsEnabled(string $name): bool
{
    return in_array(strtolower(trim((string) ini_get($name))), ['1', 'on', 'true', 'yes'], true);
}

$failures = [];

if (configuredSessionCookieIsSecure() !== true) {
    $failures[] = 'secure session cookies are required.';
}

if (productionSecurityDirectiveIsEnabled('display_errors')) {
    $failures[] = 'display_errors must be disabled.';
}

if (productionSecurityDirectiveIsEnabled('display_startup_errors')) {
    $failures[] = 'display_startup_errors must be disabled.';
}

if (!productionSecurityDirectiveIsEnabled('log_errors')) {
    $failures[] = 'log_errors must be enabled.';
}

if (productionSecurityDirectiveIsEnabled('expose_php')) {
    $failures[] = 'expose_php must be disabled.';
}

$publicRoot = '/var/www/public';
if (!is_dir($publicRoot)) {
    $failures[] = 'the production public root is unavailable.';
} else {
    foreach (['.env', '.git', 'database', 'includes', 'config', 'Dockerfile', 'docker-compose.yml'] as $internalPath) {
        if (file_exists($publicRoot . '/' . $internalPath)) {
            $failures[] = 'an internal repository path is present in the public root.';
            break;
        }
    }
}

$vhostConfiguration = @file_get_contents('/etc/apache2/sites-enabled/000-default.conf');
if (!is_string($vhostConfiguration) || !preg_match('/^\s*DocumentRoot\s+\/var\/www\/public\s*$/mi', $vhostConfiguration)) {
    $failures[] = 'the Apache production document root is not configured.';
}

$headerConfiguration = @file_get_contents('/etc/apache2/conf-enabled/zzz-portfolio-security-headers.conf');
$requiredHeaderRules = [
    'X-Frame-Options "DENY"',
    "Content-Security-Policy \"frame-ancestors 'none'\"",
    'X-Content-Type-Options "nosniff"',
    'Referrer-Policy "strict-origin-when-cross-origin"',
];
if (!is_file('/etc/apache2/mods-enabled/headers.load') || !is_string($headerConfiguration)) {
    $failures[] = 'the Apache security-header configuration is unavailable.';
} else {
    foreach ($requiredHeaderRules as $requiredHeaderRule) {
        if (!str_contains($headerConfiguration, $requiredHeaderRule)) {
            $failures[] = 'the Apache security-header configuration is incomplete.';
            break;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Production security gate failed:\n");
    foreach (array_unique($failures) as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    // TLS and HSTS must be verified and enforced at the real HTTPS deployment edge.
    exit(1);
}

echo "Production security gate passed.\n";
