<?php

declare(strict_types=1);

/**
 * Keeps the release-only Evidence Hub gates explicit without making the fast
 * deterministic Phase-2 runner depend on Docker, MySQL, or a browser.
 */
final class EvidenceHubRequiredGatesStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $compose = self::read('docker-compose.production.yml');
        $dockerfile = self::read('Dockerfile.production');
        $gate = self::read('scripts/run-evidence-hub-required-gates.ps1');
        $bootstrap = self::read('tests/phase2/support/evidence-hub-web-session-bootstrap.php');
        $httpMatrix = self::read('tests/phase2/cases/EvidenceHubRecommendationActionHttpMySqlTest.php');
        $httpRunner = self::read('scripts/run-evidence-hub-http-action-test.php');

        phase2Assert(
            str_contains($compose, 'EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY: ${EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY:?EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY is required}'),
            'Production Compose must require the externally supplied Evidence Hub opaque-target HMAC key.'
        );
        phase2Assert(!preg_match('/(?m)^\s*EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY:(?!\s*\$\{)[^\r\n]+/', $compose), 'Production Compose contains an Evidence Hub HMAC default.');
        phase2Assert(str_contains($dockerfile, '/var/www/app/evidence_hub.css') && str_contains($dockerfile, '/var/www/app/evidence_hub.js'), 'Production image does not publish the Evidence Hub CSS and JavaScript assets.');

        foreach ([
            'EvidenceHubRecommendationActionHttpMySqlTest',
            'EvidenceHubRecommendationDispositionMySqlTest',
            'EvidenceHubTimestampMySqlTest',
            'tests/phase2/support/evidence-hub-owner-visual.cjs',
            'tests/phase2/support/evidence-hub-owner-actions-visual.cjs',
            'ather.evidenceHub.required-gates',
            'PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD',
            'playwright@1.58.2',
            'Chromium revision 1208',
            'Assert-CurrentSourceIdentity',
            'Invoke-RequiredGateRegistryChallenges',
            'No pending migrations.',
        ] as $required) {
            phase2Assert(str_contains($gate, $required), "Evidence Hub required-gates entry point is missing {$required}.");
        }

        phase2Assert(!preg_match('/Write-(Host|Output)\s+[^\r\n]*(skip|omit)/i', $gate), 'Required-gates entry point may silently skip a required gate.');
        phase2Assert(
            preg_match('/Invoke-Tool\s+\'node\'\s+@\(\(Join-Path \$Root \'tests\/phase2\/support\/evidence-hub-owner-visual\.cjs\'\), \$screenshots\)/', $gate) === 1
            && preg_match('/Invoke-Tool\s+\'node\'\s+@\(\(Join-Path \$Root \'tests\/phase2\/support\/evidence-hub-owner-actions-visual\.cjs\'\)\)/', $gate) === 1
            && str_contains($gate, 'EVIDENCE_HUB_ACTION_SESSION_COOKIE'),
            'Required-gates entry point must dynamically execute both direct browser harnesses with the native Owner session.'
        );
        phase2Assert(!str_contains($gate, 'WebRequestSession') && !str_contains($gate, 'SESSION_HANDOFF') && str_contains($gate, 'curl.exe') && str_contains($gate, 'owner-a.cookiejar') && str_contains($gate, 'owner-b.cookiejar') && str_contains($gate, 'fabricated.cookiejar') && str_contains($gate, 'bootstrap endpoint remained reachable after removal'), 'Required-gates entry point must use two native cookie jars, reject a fabricated cookie, and remove its bootstrap endpoint.');
        phase2Assert(str_contains($gate, 'BOOTSTRAP_NONCE_A') && str_contains($gate, 'BOOTSTRAP_NONCE_B') && str_contains($gate, 'Owner A bootstrap nonce replay was accepted') && str_contains($gate, 'Owner B bootstrap nonce replay was accepted') && str_contains($gate, "'--seed'"), 'Required-gates entry point must seed both owners before Web-SAPI nonce-bound bootstrap and prove replay rejection.');
        phase2Assert(str_contains($bootstrap, "PHP_SAPI === 'cli'") && str_contains($bootstrap, '$_SERVER[\'REQUEST_METHOD\'] !== \'POST\'') && str_contains($bootstrap, 'hash_equals') && str_contains($bootstrap, 'fopen($marker, \'x\')') && str_contains($bootstrap, 'establishVerifiedInternalUserSession') && str_contains($bootstrap, 'session_write_close') && !str_contains($bootstrap, '$_POST'), 'Web-SAPI bootstrap must be CLI-safe, POST-only, nonce-bound, atomically single-use, canonical, and request-authority-free.');
        phase2Assert(!str_contains($httpMatrix, 'session_id(') && !str_contains($httpMatrix, 'session_save_path(') && !str_contains($httpMatrix, 'Cookie:') && str_contains($httpMatrix, "'/usr/bin/curl'") && str_contains($httpMatrix, 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_') && str_contains($httpMatrix, "ownerJar('a')") && str_contains($httpMatrix, "ownerJar('b')") && str_contains($httpMatrix, 'fabricatedCookieDenial') && str_contains($httpMatrix, 'crossTenantDenial'), 'HTTP action matrix must be a two-owner native cookie-jar client with no CLI session authority.');
        phase2Assert(!str_contains($httpRunner, 'SESSION_HANDOFF') && str_contains($httpRunner, 'seedSyntheticOwners') && str_contains($httpRunner, "'--seed'"), 'HTTP action runner must allow identity-free seeding but never session handoff.');
        phase2Assert(!str_contains($dockerfile, '/var/www/public/evidence-hub-web-session-bootstrap.php') && !str_contains($compose, 'EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP'), 'Production artifacts must not publish or enable the Web-SAPI bootstrap.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        if (!is_string($contents)) {
            throw new RuntimeException("Required-gates source {$relativePath} is unreadable.");
        }

        return $contents;
    }
}
