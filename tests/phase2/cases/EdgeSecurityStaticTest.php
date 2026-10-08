<?php

declare(strict_types=1);

final class EdgeSecurityStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/rate_limit.php';
        $edge = self::read('includes/edge_security.php');
        $rate = self::read('includes/rate_limit.php');
        $apache = self::read('docker/apache/security-headers.conf');
        $dockerfile = self::read('Dockerfile');
        $productionDockerfile = self::read('Dockerfile.production');
        $development = self::read('docker/apache/development-vhost.conf');
        $production = self::read('docker/apache/production-vhost.conf');
        $caddy = self::read('docker/Caddyfile.owner-local');
        $httpsCompose = self::read('docker-compose.owner-https.yml');
        $issuerGuard = self::read('docker/validate-oidc-issuer.sh');
        $entrypoint = self::read('docker/production-entrypoint.sh');
        $portfolio = self::read('includes/portfolio_presentation.php');
        $storage = self::read('includes/storage.php');
        $apacheOwnedHeaders = [
            'Content-Security-Policy',
            'X-Content-Type-Options',
            'X-Frame-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ];

        foreach (["default-src 'self'", "base-uri 'none'", "object-src 'none'", "frame-ancestors 'none'", "script-src 'self'", "style-src 'self'", "form-action 'self'"] as $directive) {
            phase2Assert(str_contains($apache, $directive) && str_contains($caddy, $directive), "Enforced CSP directive {$directive} is missing.");
        }
        phase2Assert(!str_contains($apache . $caddy, 'unsafe-inline') && !str_contains($apache . $caddy, 'unsafe-eval')
            && !preg_match('/(?:default|script|style|img|font|connect|form|media)-src\s+[^;]*\*/', $apache . $caddy), 'CSP contains an unsafe broad execution or source allowance.');
        phase2Assert(str_contains($apache, 'form-action \'self\' ${EXPECTED_OIDC_ISSUER}')
            && substr_count($caddy, 'form-action \'self\' {$EXPECTED_OIDC_ISSUER}') === 2
            && str_contains($httpsCompose, 'EXPECTED_OIDC_ISSUER: ${EXPECTED_OIDC_ISSUER:?EXPECTED_OIDC_ISSUER must be configured}'),
            'The exact configured OIDC issuer must be the only cross-origin form-action target at both edges.');
        phase2Assert(str_contains($issuerGuard, "grep -Eq '^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?/$'")
            && str_contains($entrypoint, '/bin/sh /usr/local/bin/validate-oidc-issuer.sh')
            && str_contains($dockerfile, 'COPY docker/validate-oidc-issuer.sh')
            && str_contains($productionDockerfile, 'COPY docker/validate-oidc-issuer.sh')
            && str_contains($httpsCompose, 'entrypoint: ["/bin/sh", "/etc/caddy/validate-oidc-issuer.sh"]')
            && str_contains($httpsCompose, './docker/validate-oidc-issuer.sh:/etc/caddy/validate-oidc-issuer.sh:ro'),
            'Both edges must reject missing or malformed issuer values before emitting CSP.');
        phase2Assert(str_contains($edge, 'Cache-Control: no-store') && str_contains($apache, 'X-Content-Type-Options') && str_contains($apache, 'Permissions-Policy') && str_contains($apache, 'X-Frame-Options'), 'Server security or sensitive-cache headers are incomplete.');
        phase2Assert(str_contains($apache, 'Header always set') && str_contains($apache, 'ather_oidc_callback') && str_contains($caddy, 'Content-Security-Policy'), 'Server/proxy error and callback header protection is incomplete.');
        phase2Assert(str_contains($development, 'LimitRequestBody 16777216') && str_contains($production, 'LimitRequestBody 16777216'), 'Apache request-size boundary is missing.');
        phase2Assert(preg_match('#<Directory\s+"/var/www/public">[\s\S]*?LimitRequestBody\s+16777216#', $production) === 1, 'Production request-size enforcement is not scoped to the public document root.');
        phase2Assert(str_contains($dockerfile, 'a2enmod unique_id') && str_contains($productionDockerfile, 'a2enmod headers rewrite unique_id'), 'Apache must enable unique request IDs for generated 413 responses.');
        phase2Assert(substr_count($apache, 'Header always set X-Request-ID "%{UNIQUE_ID}e"') === 1
            && str_contains($apache, "Header always set Cache-Control \"no-store\" \"expr=%{REQUEST_STATUS} >= 400 && resp('Cache-Control') == ''\""), 'Apache-generated errors must retain a correlation and safe-cache fallback without competing with PHP response IDs.');
        phase2Assert(!preg_match('/<script>|onerror=|style=/', $portfolio), 'Public Portfolio retains an inline CSP dependency.');
        phase2Assert(str_contains($portfolio, '<link rel="icon" type="image/png" href="/assets/images/ather-navbar-logo.png">'), 'The public Portfolio must provide an explicit public favicon instead of generating a browser 404.');
        phase2Assert(str_contains($storage, "header('Cache-Control: no-store')")
            && str_contains($storage, "header('Content-Type: '")
            && str_contains($storage, "header('Content-Disposition: inline')")
            && str_contains($storage, "header('Content-Length: '")
            && !str_contains($storage, 'X-Content-Type-Options'), 'Private media must retain functional headers while Apache exclusively owns X-Content-Type-Options.');

        foreach ($apacheOwnedHeaders as $header) {
            phase2Assert(str_contains($apache, "Header always set {$header}"), "Apache is not the authoritative {$header} emitter.");
            phase2Assert(!preg_match('/(?im)^\\s*(?:header|header_remove)\\s*\\(\\s*[\\\'\"]' . preg_quote($header, '/') . '(?:\\s*:|[\\\'\"])/', self::runtimePhpSources()), "PHP runtime source emits Apache-owned {$header}.");
            phase2Assert(!preg_match('/(?im)^\\s*(?:header(?:_down)?\\s*\\+|\\+)\\s*' . preg_quote($header, '/') . '\\b/', $caddy), "Caddy appends a duplicate {$header} instead of replacing an upstream value.");
        }
        foreach ($apacheOwnedHeaders as $header) {
            phase2Assert(str_contains($caddy, 'header_down -' . $header), "Caddy does not remove upstream {$header} before applying its boundary policy.");
        }
        phase2Assert(str_contains($caddy, '@oidc_callback path /owner_oidc_callback.php') && str_contains($caddy, 'Referrer-Policy "no-referrer"'), 'Caddy does not preserve the OIDC callback Referrer-Policy override.');
        phase2Assert(substr_count($apache, 'Header always set X-Content-Type-Options') === 1, 'The effective header matrix must have one Apache X-Content-Type-Options value.');
        phase2Assert(substr_count($apache, 'Header always set Content-Security-Policy') === 1
            && substr_count($apache, 'Header always set X-Frame-Options') === 1
            && substr_count($apache, 'Header always set Permissions-Policy') === 1, 'The Apache effective header matrix has duplicate authoritative values.');
        phase2Assert(substr_count($apache, 'Header always set Referrer-Policy') === 2
            && str_contains($apache, 'env=!ather_oidc_callback')
            && str_contains($apache, 'env=ather_oidc_callback'), 'OIDC callback Referrer-Policy rules are not mutually exclusive.');

        $originalServer = $_SERVER;
        $originalTrusted = getenv('TRUSTED_PROXY_CIDRS');
        try {
            $_SERVER = ['REMOTE_ADDR' => '198.51.100.10', 'HTTP_X_FORWARDED_FOR' => '203.0.113.8'];
            putenv('TRUSTED_PROXY_CIDRS');
            phase2AssertSame('198.51.100.10', rateLimitClientIp(), 'Untrusted peers must not control rate-limit identity through forwarded headers.');
            putenv('TRUSTED_PROXY_CIDRS=198.51.100.0/24,2001:db8::/32');
            phase2AssertSame('203.0.113.8', rateLimitClientIp(), 'Trusted proxy chain did not resolve the first untrusted client hop.');
            $_SERVER['HTTP_X_FORWARDED_FOR'] = "203.0.113.8\r\nInjected: value";
            phase2AssertSame('unknown', rateLimitClientIp(), 'Injected forwarded header was accepted.');
            putenv('TRUSTED_PROXY_CIDRS=not-a-cidr');
            phase2Assert(rateLimitTrustedProxyCidrs() === null, 'Malformed trusted-proxy configuration was accepted.');
        } finally {
            $_SERVER = $originalServer;
            $originalTrusted === false ? putenv('TRUSTED_PROXY_CIDRS') : putenv('TRUSTED_PROXY_CIDRS=' . $originalTrusted);
        }
    }

    private static function read(string $path): string
    {
        $value = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $path);
        phase2Assert(is_string($value), "{$path} is unreadable.");
        return $value;
    }

    private static function runtimePhpSources(): string
    {
        $sources = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(PHASE2_REPOSITORY_ROOT, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            $relative = substr($path, strlen(str_replace('\\', '/', PHASE2_REPOSITORY_ROOT)) + 1);
            if (str_starts_with($relative, 'tests/') || str_starts_with($relative, 'vendor/')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            phase2Assert(is_string($contents), "Runtime PHP source {$relative} is unreadable.");
            $sources[] = $contents;
        }

        return implode("\n", $sources);
    }
}
