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
        $dockerfile = self::read('Dockerfile');
        $gate = self::read('scripts/run-evidence-hub-required-gates.ps1');
        $bootstrap = self::read('tests/Support/evidence-hub-web-session-bootstrap.php');
        $httpMatrix = self::read('tests/Legacy/cases/EvidenceHubRecommendationActionHttpMySqlTest.php');
        $httpRunner = self::read('tests/Integration/run-evidence-hub-http-action-test.php');
        $mysqlMatrix = self::read('tests/Legacy/cases/EvidenceHubRecommendationDispositionMySqlTest.php');
        // The fast runner executes the shared registry; both must hold for a case to run.
        phase2Assert(str_contains(self::read('scripts/run-phase2-tests.php'), "\$tests = require __DIR__ . '/../tests/Legacy/registry.php';"), 'The Phase 2 runner no longer executes the shared registry.');
        $phase2Runner = self::read('tests/Legacy/registry.php');

        phase2Assert(
            str_contains($compose, 'EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY: ${EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY:?EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY is required}'),
            'Production Compose must require the externally supplied Evidence Hub opaque-target HMAC key.'
        );
        phase2Assert(!preg_match('/(?m)^\s*EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY:(?!\s*\$\{)[^\r\n]+/', $compose), 'Production Compose contains an Evidence Hub HMAC default.');
        // Every image publishes the whole repository public/ tree; nothing is copied by name.
        phase2Assert(substr_count($dockerfile, 'cp -a /var/www/app/public/. /var/www/public/') === 2 && !preg_match('/\bcp\b[^\n]*\.(?:css|js)\b/', $dockerfile), 'Both images must publish exactly the repository public/ tree.');
        foreach (['public/assets/css/evidence_hub.css', 'public/assets/js/evidence_hub.js', 'public/assets/js/owner_theme.js'] as $asset) {
            phase2Assert(is_file(PHASE2_REPOSITORY_ROOT . '/' . $asset), 'Production image does not publish the Evidence Hub and Owner theme assets.');
        }
        // A repository file Docker drops from the build context can never be in the image,
        // so the source-identity manifest must exclude it too or every gate run fails.
        preg_match('/\$excluded = @\(([^)]*)\)/', $gate, $excludedMatch);
        preg_match_all("/'([^']+)'/", $excludedMatch[1] ?? '', $excludedNames);
        foreach (preg_split('/\R/', self::read('.dockerignore')) ?: [] as $pattern) {
            $pattern = trim($pattern);
            if ($pattern !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $pattern) === 1 && is_file(PHASE2_REPOSITORY_ROOT . '/' . $pattern)) {
                phase2Assert(in_array($pattern, $excludedNames[1], true), "Required-gates source manifest must exclude {$pattern}, which .dockerignore keeps out of the image.");
            }
        }
        phase2Assert(str_contains($gate, 'diff -r /var/www/app/public /var/www/public'), 'Required gates must verify the published public tree in every image.');
        foreach (['public/assets/css/owner_workspace.css', 'public/assets/css/owner_dashboard.css', 'public/assets/images/dashboard-projects.svg'] as $asset) {
            phase2Assert(is_file(PHASE2_REPOSITORY_ROOT . '/' . $asset), 'Production must publish and verify the shared Owner and Dashboard assets.');
        }

        foreach ([
            'EvidenceHubRecommendationActionHttpMySqlTest',
            'EvidenceHubRecommendationDispositionMySqlTest',
            'EvidenceHubTimestampMySqlTest',
            'EvidenceHubSkillEvidenceTest',
            'EvidenceHubContractV8Test',
            'EvidenceTextRules110Test',
            'EvidenceProjectTextRules110Test',
            'EvidenceHubContractV9Test',
            'EvidenceTextOwnerHintsTest',
            'tests/Integration/check-evidence-edit-http.php::textRules110',
            'tests/Browser/evidence-hub-text-rules-visual.cjs',
            'EvidenceHubRecommendationDispositionMySqlTest::skillEvidenceLifecycle',
            'EvidenceHubRecommendationActionHttpMySqlTest::skillEvidenceLifecycle',
            'tests/Browser/evidence-hub-skill-evidence-visual.cjs',
            'tests/Browser/evidence-hub-owner-visual.cjs',
            'tests/Browser/evidence-hub-owner-actions-visual.cjs',
            'tests/Browser/evidence-hub-editor-writing-visual.cjs',
            'tests/Browser/evidence-hub-technology-review-visual.cjs',
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
            preg_match('/Invoke-Tool\s+\'node\'\s+@\(\(Join-Path \$Root \'tests\/Browser\/evidence-hub-owner-visual\.cjs\'\), \$screenshots\)/', $gate) === 1
            && preg_match('/Invoke-Tool\s+\'node\'\s+@\(\(Join-Path \$Root \'tests\/Browser\/evidence-hub-owner-actions-visual\.cjs\'\)\)/', $gate) === 1
            && preg_match('/Invoke-Tool\s+\'node\'\s+@\(\(Join-Path \$Root \'tests\/Browser\/evidence-hub-editor-writing-visual\.cjs\'\)\)/', $gate) === 1
            && preg_match('/Invoke-Tool\s+\'node\'\s+@\(\(Join-Path \$Root \'tests\/Browser\/evidence-hub-technology-review-visual\.cjs\'\)\)/', $gate) === 1
            && str_contains($gate, 'EVIDENCE_HUB_ACTION_SESSION_COOKIE'),
            'Required-gates entry point must dynamically execute every direct browser harness with the native Owner session.'
        );
        phase2Assert(!str_contains($gate, 'WebRequestSession') && !str_contains($gate, 'SESSION_HANDOFF') && str_contains($gate, 'curl.exe') && str_contains($gate, 'owner-a.cookiejar') && str_contains($gate, 'owner-b.cookiejar') && str_contains($gate, 'fabricated.cookiejar') && str_contains($gate, 'bootstrap endpoint remained reachable after removal'), 'Required-gates entry point must use two native cookie jars, reject a fabricated cookie, and remove its bootstrap endpoint.');
        phase2Assert(str_contains($gate, 'BOOTSTRAP_NONCE_A') && str_contains($gate, 'BOOTSTRAP_NONCE_B') && str_contains($gate, 'Owner A bootstrap nonce replay was accepted') && str_contains($gate, 'Owner B bootstrap nonce replay was accepted') && str_contains($gate, "'--seed'"), 'Required-gates entry point must seed both owners before Web-SAPI nonce-bound bootstrap and prove replay rejection.');
        $seedPosition = strpos($gate, "'tests/Integration/run-evidence-hub-http-action-test.php', '--seed'");
        $cutoverPosition = strpos($gate, "'database/capture-owner-journey-cutover.php', '--confirm-exclusive-maintenance', \"--database=\$databaseName\"");
        $apachePosition = strpos($gate, "@('run', '--detach', '--name', \$web");
        phase2Assert($seedPosition !== false && $cutoverPosition !== false && $apachePosition !== false
            && $seedPosition < $cutoverPosition && $cutoverPosition < $apachePosition,
            'Synthetic legacy owners must have a guarded exact-database cutover after seeding and before Apache starts, without changing session authentication.');
        phase2Assert(str_contains($bootstrap, "PHP_SAPI === 'cli'") && str_contains($bootstrap, '$_SERVER[\'REQUEST_METHOD\'] !== \'POST\'') && str_contains($bootstrap, 'hash_equals') && str_contains($bootstrap, 'fopen($marker, \'x\')') && str_contains($bootstrap, 'establishVerifiedInternalUserSession') && str_contains($bootstrap, 'session_write_close') && !str_contains($bootstrap, '$_POST'), 'Web-SAPI bootstrap must be CLI-safe, POST-only, nonce-bound, atomically single-use, canonical, and request-authority-free.');
        phase2Assert(!str_contains($httpMatrix, 'session_id(') && !str_contains($httpMatrix, 'session_save_path(') && !str_contains($httpMatrix, 'Cookie:') && str_contains($httpMatrix, "'/usr/bin/curl'") && str_contains($httpMatrix, 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_') && str_contains($httpMatrix, "ownerJar('a')") && str_contains($httpMatrix, "ownerJar('b')") && str_contains($httpMatrix, 'fabricatedCookieDenial') && str_contains($httpMatrix, 'crossTenantDenial'), 'HTTP action matrix must be a two-owner native cookie-jar client with no CLI session authority.');
        phase2Assert(str_contains($phase2Runner, 'EvidenceHubSkillEvidenceTest::class') && str_contains($phase2Runner, 'EvidenceHubContractV8Test::class') && str_contains($mysqlMatrix, 'self::skillEvidenceLifecycle(') && str_contains($httpMatrix, '$test->skillEvidenceLifecycle()') && str_contains($gate, "Invoke-Tool 'node' @((Join-Path \$Root 'tests/Browser/evidence-hub-skill-evidence-visual.cjs'))"), 'Every registered skill-evidence gate must execute.');
        phase2Assert(!str_contains($httpRunner, 'SESSION_HANDOFF') && str_contains($httpRunner, 'seedSyntheticOwners') && str_contains($httpRunner, "'--seed'"), 'HTTP action runner must allow identity-free seeding but never session handoff.');
        foreach (['EvidenceTextRules110Test', 'EvidenceProjectTextRules110Test', 'EvidenceHubContractV9Test', 'EvidenceTextOwnerHintsTest'] as $test) {
            phase2Assert(str_contains($phase2Runner, $test . '::class'), 'A registered text-rule unit gate is not executed.');
        }
        $editorHttp = self::read('tests/Integration/check-evidence-edit-http.php');
        phase2Assert(str_contains($editorHttp, 'verifyTextRules110Http($fields, $route, $cookieA)') && str_contains($editorHttp, 'EVIDENCE_TEXT_RULES_110_HTTP_PASSED') && str_contains($gate, "Invoke-Tool 'node' @((Join-Path \$Root 'tests/Browser/evidence-hub-text-rules-visual.cjs'))"), 'Registered text-rule HTTP and browser gates must execute.');
        $editorPosition = strpos($gate, "'tests/Integration/check-evidence-edit-http.php'");
        $technologyReviewPosition = strpos($gate, "'tests/Integration/check-evidence-technology-review-http.php'");
        phase2Assert($editorPosition !== false && $technologyReviewPosition !== false && $editorPosition < $technologyReviewPosition, 'Required-gates entry point must run the Owner technology review HTTP and MySQL check with the native Owner sessions.');
        $sourcesPosition = strpos($gate, "'tests/Integration/check-evidence-sources-http.php'");
        phase2Assert($sourcesPosition !== false && $technologyReviewPosition < $sourcesPosition && str_contains($phase2Runner, 'EvidenceHubContractV10Test::class'), 'Required-gates entry point must run the evidence sources HTTP and MySQL check and the v10 contract test.');
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
