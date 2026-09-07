<?php

declare(strict_types=1);

final class LegacyBackendRemovalStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        foreach ([
            'admin.php',
            'login.php',
            'logout.php',
            'personal_info.php',
            'projects.php',
            'messages.php',
            'api/projects.php',
            'public/admin.php',
            'public/login.php',
            'public/logout.php',
            'public/personal_info.php',
            'public/projects.php',
            'public/messages.php',
            'public/api/projects.php',
            'includes/admin_session.php',
            'includes/admin_sidebar.php',
            'classes/Project.php',
        ] as $path) {
            phase2Assert(!file_exists(PHASE2_REPOSITORY_ROOT . '/' . $path), "Retired backend artifact remains: {$path}");
        }

        $runtimePhp = self::runtimePhp();
        foreach (self::retiredAuthorityTerms() as $term) {
            phase2Assert(!str_contains($runtimePhp, $term), "Runtime PHP still references retired authority: {$term}");
        }

        $compose = self::read('docker-compose.yml') . self::read('docker-compose.production.yml');
        foreach (self::retiredConfigurationTerms() as $term) {
            phase2Assert(!str_contains($compose, $term), "Compose configuration still exposes retired authority: {$term}");
        }

        foreach ([
            'owner.php',
            'owner_login.php',
            'owner_logout.php',
            'owner_oidc_callback.php',
            'owner_onboarding.php',
            'owner_profile.php',
            'owner_projects.php',
            'owner_experiences.php',
            'owner_messages.php',
            'owner_publication.php',
            'owner_preview.php',
            'owner_media.php',
        ] as $path) {
            phase2Assert(is_file(PHASE2_REPOSITORY_ROOT . '/' . $path), "Owner/Auth0 route is missing: {$path}");
        }

        $smoke = self::read('scripts/run-production-smoke.ps1');
        foreach (['/admin.php', '/login.php', '/logout.php', '/personal_info.php', '/projects.php', '/messages.php', '/api/projects.php'] as $path) {
            phase2Assert(str_contains($smoke, "'{$path}'") && str_contains($smoke, 'Assert-HttpStatus'), "Production smoke coverage no longer requires HTTP 404 for {$path}.");
        }

        $profileActions = self::read('includes/profile_actions.php');
        $projectActions = self::read('includes/project_actions.php');
        $ownerActions = self::read('includes/owner_actions.php');
        phase2Assert(!str_contains($profileActions, 'function ' . 'handle' . 'Profile' . 'Action('), 'An unscoped Profile action handler remains.');
        phase2Assert(!str_contains($projectActions, 'function ' . 'handle' . 'Project' . 'Action('), 'An unscoped Project action handler remains.');
        phase2Assert(str_contains($ownerActions, 'function ' . 'handle' . 'Authorized' . 'Profile' . 'Action('), 'The authorized Owner Profile action handler is missing.');
        phase2Assert(str_contains($ownerActions, 'function ' . 'handle' . 'Authorized' . 'Project' . 'Action('), 'The authorized Owner Project action handler is missing.');

        foreach (['docker/apache/development-vhost.conf', 'docker/apache/production-vhost.conf'] as $path) {
            phase2Assert(str_contains(self::read($path), 'RewriteRule ^/p/'), "Canonical /p/<slug> routing is missing from {$path}.");
        }
    }

    /** @return list<string> */
    private static function retiredAuthorityTerms(): array
    {
        return [
            'start' . 'Admin' . 'Session',
            'require' . 'Admin' . 'Authentication',
            'is' . 'Admin' . 'Authenticated',
            'destroy' . 'Admin' . 'Session',
            'portfolio_' . 'admin_' . 'session',
            'admin_' . 'logged_in',
            'admin_' . 'username',
        ];
    }

    /** @return list<string> */
    private static function retiredConfigurationTerms(): array
    {
        return [
            'LEGACY_' . 'ADMIN_' . 'AUTH_' . 'ENABLED',
            'ADMIN_' . 'USERNAME',
            'ADMIN_' . 'PASSWORD_' . 'HASH',
        ];
    }

    private static function runtimePhp(): string
    {
        $contents = [];
        foreach (glob(PHASE2_REPOSITORY_ROOT . '/*.php') ?: [] as $path) {
            $source = file_get_contents($path);
            phase2Assert(is_string($source), 'Runtime PHP source is unreadable: ' . $path);
            $contents[] = $source;
        }

        foreach (['api', 'classes', 'config', 'includes', 'public'] as $directory) {
            $path = PHASE2_REPOSITORY_ROOT . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $source = file_get_contents($file->getPathname());
                phase2Assert(is_string($source), 'Runtime PHP source is unreadable: ' . $file->getPathname());
                $contents[] = $source;
            }
        }

        return implode("\n", $contents);
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
