<?php

declare(strict_types=1);

final class PublicProjectJsonContractTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/public_lifecycle.php';

        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY, account_status TEXT NOT NULL);
             CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT, is_published INTEGER NOT NULL);
             CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, title TEXT, category TEXT, description TEXT, github_url TEXT, image_path TEXT, technologies TEXT, created_at TEXT NOT NULL)"
        );

        $storageRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'json-public-media';
        $presentationDirectory = $storageRoot . DIRECTORY_SEPARATOR . 'portfolios' . DIRECTORY_SEPARATOR . '10' . DIRECTORY_SEPARATOR . 'project' . DIRECTORY_SEPARATOR . 'presentation';
        if (!mkdir($presentationDirectory, 0700, true)) {
            throw new RuntimeException('Could not create test-owned public Project presentation storage.');
        }
        $presentationImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        if ($presentationImage === false || file_put_contents($presentationDirectory . DIRECTORY_SEPARATOR . 'managed-private_presentation.png', $presentationImage, LOCK_EX) === false) {
            throw new RuntimeException('Could not create test-owned public Project presentation image.');
        }
        $previousStorageRoot = getenv('ATHERCAR_STORAGE_ROOT');
        putenv('ATHERCAR_STORAGE_ROOT=' . $storageRoot);
        $database->exec(
            "INSERT INTO users (id, account_status) VALUES (1, 'active'), (2, 'active');
             INSERT INTO portfolios (id, owner_user_id, public_slug, is_published) VALUES (10, 1, 'public-owner', 1), (20, 2, 'draft-owner', 0);
             INSERT INTO projects (id, portfolio_id, title, category, description, github_url, image_path, technologies, created_at) VALUES
                (100, 10, 'Published image', 'Web', 'Published description', 'https://example.test/project', 'portfolios/10/projects/managed-private.png', '[\"PHP\", \"UTF-8 ✓\"]', '2026-01-02'),
                (101, 10, 'Published no image', 'Data', 'No image description', '', NULL, '[]', '2026-01-01'),
                (200, 20, 'Draft image', 'Private', 'Draft description', '', 'portfolios/20/projects/draft-private.webp', '[]', '2026-01-03')"
        );

        try {
            $context = resolvePublicReadContext($database, 'public-owner');
            phase2Assert($context instanceof PublicReadContext, 'Published Portfolio did not resolve for JSON contract test.');
            $projects = listPublicProjectJsonPayload($database, $context, 'public-owner');
            phase2AssertSame(2, count($projects), 'Public JSON included another Portfolio\'s Project.');

            $withImage = $projects[0];
            phase2AssertSame(
            ['title', 'category', 'description', 'github_url', 'technologies', 'image_url'],
            array_keys($withImage),
            'Public Project JSON field allow-list changed or leaked a field.'
        );
            foreach (['id', 'portfolio_id', 'owner_user_id', 'user_id', 'authz_version', 'image_path', 'created_at', 'is_published'] as $forbidden) {
                phase2Assert(!array_key_exists($forbidden, $withImage), "Public Project JSON leaked {$forbidden}.");
            }
            phase2AssertSame('/p/public-owner/media/project/100', $withImage['image_url'], 'Public Project JSON image URL does not use the scoped public-media route.');
            phase2Assert(!str_contains(json_encode($withImage, JSON_THROW_ON_ERROR), 'managed-private.png'), 'Public Project JSON leaked a managed-media key.');
            phase2AssertSame(['PHP', 'UTF-8 ✓'], $withImage['technologies'], 'Public Project JSON did not preserve valid UTF-8 technology data.');

            $withoutImage = $projects[1];
            phase2AssertSame(['title', 'category', 'description', 'github_url', 'technologies'], array_keys($withoutImage), 'An image-less Project exposed an unnecessary media field.');
            phase2Assert(!array_key_exists('image_url', $withoutImage), 'An image-less Project exposed an image URL.');
            phase2Assert(resolvePublicReadContext($database, 'draft-owner') === null, 'Unpublished Portfolio Projects resolved through the public JSON context.');
            phase2AssertSame(null, normalizePublicSlug('invalid_slug'), 'A non-canonical route slug entered the JSON application contract.');
            phase2AssertSame(null, normalizePublicSlug('a--b'), 'A matched but invalid slug did not fail application validation.');

            $lifecycle = self::read('includes/public_lifecycle.php');
            $jsonRoute = self::read('public_projects_json.php');
            $developmentVhost = self::read('docker/apache/development-vhost.conf');
            $productionVhost = self::read('docker/apache/production-vhost.conf');
            $documentation = self::read('docs/HTTP_CONTRACTS.md');
            $frontend = self::read('portfolio.js');

            phase2Assert(str_contains($jsonRoute, 'listPublicProjectJsonPayload') && !str_contains($jsonRoute, 'listPublicProjects($database, $context)'), 'Public JSON route still serializes the presentation row mapping.');
            phase2Assert(!preg_match('/function listPublicProjectJsonPayload.*?SELECT\s+\*/s', $lifecycle), 'Public JSON mapper uses SELECT * instead of an allow-list query.');
            phase2Assert(str_contains($lifecycle, "\$project['image_url'] = publicProjectMediaUrl(\$normalizedSlug, \$projectId);") && str_contains($lifecycle, 'publicProjectPresentationIsReadable'), 'Public JSON mapper does not build its image URL through the approved readable-media helper.');
            phase2Assert(str_contains($lifecycle, "return '/p/' . rawurlencode(\$slug) . '/media/project/' . \$projectId;"), 'Public image URLs are not restricted to the public Project media route.');
            phase2Assert(!str_contains($frontend, 'projects.json') && !str_contains($frontend, 'image_path'), 'A browser JSON consumer still depends on a raw Project media path.');
            phase2Assert(str_contains($developmentVhost, '/projects\\.json$ /public_projects_json.php?slug=$1 [END,NE]') && str_contains($productionVhost, '/projects\\.json$ /p_projects.php?slug=$1 [END,NE]'), 'Canonical Project JSON route is missing.');
            phase2Assert(!str_contains($developmentVhost, 'projects\\.json.*') && !str_contains($productionVhost, 'projects\\.json.*'), 'A broad Project JSON catch-all rewrite was introduced.');
            phase2Assert(str_contains($documentation, 'non-matching path') && str_contains($documentation, 'managed-media paths'), 'HTTP documentation does not state JSON route and media disclosure boundaries.');
        } finally {
            putenv('ATHERCAR_STORAGE_ROOT' . (is_string($previousStorageRoot) ? '=' . $previousStorageRoot : ''));
        }
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
