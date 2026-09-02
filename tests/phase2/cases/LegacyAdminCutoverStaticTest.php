<?php

declare(strict_types=1);

final class LegacyAdminCutoverStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/admin_session.php';

        $adminSession = self::read('includes/admin_session.php');
        $compose = self::read('docker-compose.production.yml');
        $guard = self::read('scripts/check-production-security.php');
        phase2Assert(str_contains($adminSession, "LEGACY_ADMIN_AUTH_ENABLED_ENV = 'LEGACY_ADMIN_AUTH_ENABLED'")
            && str_contains($adminSession, 'function denyRetiredLegacyAdminAuthority(): never')
            && str_contains($adminSession, 'http_response_code(404)'), 'P2J-11 legacy authority retirement gate is missing.');
        phase2Assert(str_contains($compose, 'LEGACY_ADMIN_AUTH_ENABLED: ${LEGACY_ADMIN_AUTH_ENABLED:-true}')
            && str_contains($guard, 'legacyAdminAuthorityConfigurationIsValid'), 'P2J-11 legacy authority runtime configuration is incomplete.');

        foreach (['login.php', 'logout.php', 'admin.php', 'personal_info.php', 'projects.php', 'messages.php'] as $route) {
            phase2Assert(str_contains(self::read($route), 'startAdminSession();'), "{$route} bypasses the P2J-11 legacy authority gate.");
        }

        $original = getenv(LEGACY_ADMIN_AUTH_ENABLED_ENV);
        try {
            putenv(LEGACY_ADMIN_AUTH_ENABLED_ENV);
            phase2Assert(legacyAdminAuthorityEnabled() && legacyAdminAuthorityConfigurationIsValid(), 'Legacy authority must remain enabled by default before cutover.');
            putenv(LEGACY_ADMIN_AUTH_ENABLED_ENV . '=true');
            phase2Assert(legacyAdminAuthorityEnabled() && legacyAdminAuthorityConfigurationIsValid(), 'Explicit legacy authority enablement is invalid.');
            putenv(LEGACY_ADMIN_AUTH_ENABLED_ENV . '=false');
            phase2Assert(!legacyAdminAuthorityEnabled() && legacyAdminAuthorityConfigurationIsValid(), 'Legacy authority retirement does not fail closed.');
            putenv(LEGACY_ADMIN_AUTH_ENABLED_ENV . '=invalid');
            phase2Assert(!legacyAdminAuthorityEnabled() && !legacyAdminAuthorityConfigurationIsValid(), 'Malformed legacy authority configuration was accepted.');
        } finally {
            if ($original === false) {
                putenv(LEGACY_ADMIN_AUTH_ENABLED_ENV);
            } else {
                putenv(LEGACY_ADMIN_AUTH_ENABLED_ENV . '=' . $original);
            }
        }
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
