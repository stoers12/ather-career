<?php

declare(strict_types=1);

final class PublicLifecycleStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $migration = self::read('database/migrate.php');
        $lifecycle = self::normalizeLineEndings(self::read('includes/public_lifecycle.php'));
        $ownerRoute = self::read('owner_publication.php');
        $publicRoute = self::read('public_portfolio.php');
        $publicJson = self::read('public_projects_json.php');
        $root = self::read('index.php');
        $developmentDockerfile = self::read('Dockerfile');
        $developmentVhost = self::read('docker/apache/development-vhost.conf');
        $productionVhost = self::read('docker/apache/production-vhost.conf');

        phase2Assert(str_contains($migration, "PUBLIC_LIFECYCLE_MIGRATION_VERSION = '005'"), 'P2J-05 Migration C is missing.');
        phase2Assert(str_contains($migration, 'public_slug VARCHAR(64)') && str_contains($migration, 'is_published TINYINT(1) NOT NULL DEFAULT 0') && str_contains($migration, 'published_at TIMESTAMP NULL DEFAULT NULL'), 'P2J-05 additive lifecycle columns are incomplete.');
        phase2Assert(str_contains($migration, "'uq_portfolios_public_slug', ['public_slug'], true"), 'P2J-05 public slug unique constraint is missing.');

        phase2Assert(str_contains($lifecycle, "preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', \$slug)"), 'P2J-05 slug validation is incomplete.');
        phase2Assert(str_contains($lifecycle, 'PUBLIC_SLUG_RESERVED'), 'P2J-05 reserved route names are missing.');
        phase2Assert(str_contains($lifecycle, 'published_at IS NULL') && str_contains($lifecycle, 'COALESCE(published_at, CURRENT_TIMESTAMP)') && str_contains($lifecycle, 'SET is_published = 0'), 'P2J-05 publication immutability contract is incomplete.');
        phase2Assert(str_contains($lifecycle, "AND portfolios.is_published = 1\n           AND users.account_status = 'active'"), 'P2J-05 PublicReadContext must require publication and an active owner.');
        foreach ([
            'WHERE portfolio_id = :public_portfolio_id',
        ] as $fragment) {
            phase2Assert(str_contains($lifecycle, $fragment), "P2J-05 public data access is not Portfolio scoped: {$fragment}");
        }
        phase2Assert(preg_match('/FROM projects\\s+WHERE portfolio_id = :public_portfolio_id/', $lifecycle) === 1, 'P2J-05 public projects query is not Portfolio scoped.');
        phase2Assert(preg_match('/FROM skills\\s+WHERE portfolio_id = :public_portfolio_id/', $lifecycle) === 1, 'P2J-05 public skills query is not Portfolio scoped.');
        phase2Assert(!str_contains($lifecycle, '$_SESSION') && !str_contains($lifecycle, 'requireOwnedPortfolioContext'), 'P2J-05 public context must not use owner authority.');

        phase2Assert(str_contains($ownerRoute, 'requireOwnerPortfolioContext($database)') && str_contains($ownerRoute, 'requireValidCsrfToken') && str_contains($ownerRoute, 'httpRedirect('), 'P2J-05 publication actions require owner context, CSRF, and PRG.');
        phase2Assert(!str_contains($ownerRoute, 'portfolio_id') && !str_contains($ownerRoute, 'owner_user_id'), 'P2J-05 publication route must not accept tenant authority.');
        phase2Assert(str_contains($publicRoute, 'resolvePublicReadContext') && str_contains($publicRoute, 'loadPublicPersonalInfo') && str_contains($publicRoute, 'listPublicSkills') && str_contains($publicRoute, 'listPublicProjects'), 'P2J-05 public Portfolio route is incomplete.');
        phase2Assert(!str_contains($publicRoute, 'requireOwnerPortfolioContext'), 'P2J-05 public route must remain independent of owner authority.');
        phase2Assert(str_contains($publicJson, 'resolvePublicReadContext') && str_contains($publicJson, 'listPublicProjects') && str_contains($publicJson, 'httpJsonResponse'), 'P2J-05 public projects JSON is incomplete.');
        phase2Assert(!str_contains($root, 'FROM projects') && !str_contains($root, 'FROM personal_info') && !str_contains($root, '<form'), 'P2J-05 root must not retain a global Portfolio fallback or contact action.');
        phase2Assert(str_contains($developmentDockerfile, 'a2enmod rewrite'), 'P2J-05 Owner development image must enable mod_rewrite.');
        self::assertPublicRouteContract($developmentVhost, [
            '/public_media.php?slug=$1&type=profile',
            '/public_media.php?slug=$1&type=project&id=$2',
            '/public_projects_json.php?slug=$1',
            '/public_contact.php?slug=$1',
            '/public_portfolio.php?slug=$1',
        ], 'Owner development');
        self::assertPublicRouteContract($productionVhost, [
            '/p_media.php?slug=$1&type=profile',
            '/p_media.php?slug=$1&type=project&id=$2',
            '/p_projects.php?slug=$1',
            '/p_contact.php?slug=$1',
            '/p.php?slug=$1',
        ], 'Production');
        phase2Assert(str_contains($developmentVhost, 'Options -Indexes +FollowSymLinks') && str_contains($developmentVhost, 'AllowOverride None'), 'P2J-05 Owner development public routes must retain no-directory-listing and must not enable .htaccess overrides.');
    }

    /** @param list<string> $targets */
    private static function assertPublicRouteContract(string $vhost, array $targets, string $environment): void
    {
        $slug = '([a-z0-9][a-z0-9-]{1,62}[a-z0-9])';
        $expectedPatterns = [
            "^/p/{$slug}/media/profile/?$",
            "^/p/{$slug}/media/project/([1-9][0-9]*)/?$",
            "^/p/{$slug}/projects\\.json$",
            "^/p/{$slug}/contact/?$",
            "^/p/{$slug}/?$",
        ];

        phase2Assert(str_contains($vhost, 'RewriteEngine On'), "P2J-05 {$environment} public routes must enable rewriting.");
        foreach ($expectedPatterns as $index => $pattern) {
            $rule = "RewriteRule {$pattern} {$targets[$index]} [END,NE]";
            phase2Assert(str_contains($vhost, $rule), "P2J-05 {$environment} route contract is missing {$rule}.");
        }
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }

    private static function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
