<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/media_access.php';
require_once __DIR__ . '/../includes/media_migration.php';
require_once __DIR__ . '/../includes/project_actions.php';

const PRIVATE_MEDIA_TEST_ISSUER = 'https://issuer.test/ather-career';

function privateMediaCreatePng(string $path, int $width, int $height, int $red): void
{
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) throw new RuntimeException('Could not create media fixture.');
    imagefill($image, 0, 0, imagecolorallocate($image, $red, 40, 90));
    if (!imagepng($image, $path)) { imagedestroy($image); throw new RuntimeException('Could not write media fixture.'); }
    imagedestroy($image);
}

function privateMediaCreateUser(PDO $database, string $label): int
{
    $statement = $database->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:issuer, :subject, 'active', 1)");
    $statement->execute(['issuer' => PRIVATE_MEDIA_TEST_ISSUER, 'subject' => 'p2j07-' . $label . '-' . bin2hex(random_bytes(8))]);
    return (int) $database->lastInsertId();
}

/** @return array{int, AuthorizedPortfolioContext} */
function privateMediaCreatePortfolio(PDO $database, int $userId, string $slug, bool $published): array
{
    $statement = $database->prepare('INSERT INTO portfolios (owner_user_id, public_slug, is_published, published_at) VALUES (:user_id, :slug, :published, :published_at)');
    $statement->execute(['user_id' => $userId, 'slug' => $slug, 'published' => $published ? 1 : 0, 'published_at' => $published ? '2026-01-01 00:00:00' : null]);
    $portfolioId = (int) $database->lastInsertId();
    return [$portfolioId, AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($userId), $portfolioId)];
}

/** @return array<string, string> */
function privateMediaFilesystemSnapshot(string $root, int $portfolioId): array
{
    $directory = $root . DIRECTORY_SEPARATOR . 'portfolios' . DIRECTORY_SEPARATOR . $portfolioId;
    if (!is_dir($directory)) return [];
    $snapshot = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) $snapshot[str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1))] = (string) hash_file('sha256', $file->getPathname());
    }
    ksort($snapshot);
    return $snapshot;
}

$environment = null;
$passed = [];
try {
    TestEnvironment::assertSafeEnvironment(getenv());
    $environment = TestEnvironment::create();
    $storageRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'private-media';
    $sourceRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'v1-source';
    foreach ([$storageRoot, $sourceRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile', $sourceRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'projects'] as $directory) {
        if (!mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Could not create disposable media directory.');
    }
    putenv('ATHERCAR_STORAGE_ROOT=' . $storageRoot);
    $_SERVER['DOCUMENT_ROOT'] = $environment->storageRoot . DIRECTORY_SEPARATOR . 'public-root';
    mkdir($_SERVER['DOCUMENT_ROOT'], 0700, true);

    $database = getDatabaseConnection();
    phase2AssertSame('public_lifecycle', $database->query("SELECT name FROM schema_migrations WHERE version = '005'")->fetchColumn(), 'P2J-07 requires P2J-05 schema.');
    $database->exec('DELETE FROM messages');
    $database->exec('DELETE FROM projects');
    $database->exec('DELETE FROM skills');
    $database->exec('DELETE FROM personal_info');

    $userA = privateMediaCreateUser($database, 'a');
    [$portfolioA, $contextA] = privateMediaCreatePortfolio($database, $userA, 'media-a', true);
    $userB = privateMediaCreateUser($database, 'b');
    [$portfolioB, $contextB] = privateMediaCreatePortfolio($database, $userB, 'media-b', false);
    foreach ([['profile', 'a-profile.png', 480, 480, 100], ['profile', 'b-profile.png', 480, 480, 130], ['projects', 'a-project.png', 64, 48, 160], ['projects', 'b-project.png', 64, 48, 190]] as [$folder, $name, $width, $height, $red]) {
        privateMediaCreatePng($sourceRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $name, $width, $height, $red);
    }
    $sourceHashes = [
        'a-profile' => hash_file('sha256', $sourceRoot . '/uploads/profile/a-profile.png'),
        'b-profile' => hash_file('sha256', $sourceRoot . '/uploads/profile/b-profile.png'),
        'a-project' => hash_file('sha256', $sourceRoot . '/uploads/projects/a-project.png'),
        'b-project' => hash_file('sha256', $sourceRoot . '/uploads/projects/b-project.png'),
    ];

    $profile = $database->prepare('INSERT INTO personal_info (portfolio_id, full_name, profile_image_path) VALUES (:portfolio_id, :name, :path)');
    $profile->execute(['portfolio_id' => $portfolioA, 'name' => 'Media A', 'path' => 'uploads/profile/a-profile.png']);
    $profileA = (int) $database->lastInsertId();
    $profile->execute(['portfolio_id' => $portfolioB, 'name' => 'Media B', 'path' => 'uploads/profile/b-profile.png']);
    $profileB = (int) $database->lastInsertId();
    $project = $database->prepare('INSERT INTO projects (portfolio_id, title, category, description, github_url, image_path) VALUES (:portfolio_id, :title, \'Media\', \'Private media test\', \'https://example.test/media\', :path)');
    $project->execute(['portfolio_id' => $portfolioA, 'title' => 'Media A Project', 'path' => 'uploads/projects/a-project.png']);
    $projectA = (int) $database->lastInsertId();
    $project->execute(['portfolio_id' => $portfolioB, 'title' => 'Media B Project', 'path' => 'uploads/projects/b-project.png']);
    $projectB = (int) $database->lastInsertId();

    phase2AssertSame(['profiles' => 2, 'projects' => 2], migrateLegacyMedia($database, $sourceRoot), 'V1 media migration count is incorrect.');
    $rowA = loadAuthorizedPersonalInfo($database, $contextA);
    $rowB = loadAuthorizedPersonalInfo($database, $contextB);
    $projectRowA = findAuthorizedProject($database, $contextA, $projectA);
    $projectRowB = findAuthorizedProject($database, $contextB, $projectB);
    phase2AssertSame($profileA, (int) $rowA['id'], 'Profile A ID changed during media migration.');
    phase2AssertSame($profileB, (int) $rowB['id'], 'Profile B ID changed during media migration.');
    phase2AssertSame($projectA, (int) $projectRowA['id'], 'Project A ID changed during media migration.');
    phase2AssertSame($projectB, (int) $projectRowB['id'], 'Project B ID changed during media migration.');
    foreach ($sourceHashes as $label => $hash) {
        $parts = explode('-', $label);
        $path = $sourceRoot . '/uploads/' . ($parts[1] === 'profile' ? 'profile/' : 'projects/') . $label . '.png';
        phase2AssertSame($hash, hash_file('sha256', $path), 'V1 source media changed: ' . $label);
    }
    $passed[] = 'T-MEDIA-V1-PRESERVATION';

    $ownerAProfile = ownerMediaDescriptor($database, $contextA, 'profile', null);
    $ownerAProject = ownerMediaDescriptor($database, $contextA, 'project', (string) $projectA);
    phase2Assert($ownerAProfile !== null && $ownerAProject !== null, 'Owner A media was unavailable.');
    phase2AssertSame(null, ownerMediaDescriptor($database, $contextA, 'project', (string) $projectB), 'Owner A read B project media.');
    phase2AssertSame(null, ownerMediaDescriptor($database, $contextA, 'project', '4294967295'), 'Forged project ID exposed media.');
    phase2AssertSame(null, privateMediaDescriptor($projectRowB['image_path'], $portfolioA, 'projects'), 'Foreign managed key exposed B media under A.');
    $originalA = resolvePrivateMediaPath($rowA['profile_image_path'], $portfolioA, 'profile_original');
    phase2Assert($originalA !== null && $ownerAProfile['path'] !== $originalA && str_contains(str_replace('\\', '/', $ownerAProfile['path']), '/profile/presentation/'), 'Profile handler exposed the private original or failed to use its derivative.');
    $bBefore = privateMediaFilesystemSnapshot($storageRoot, $portfolioB);
    cleanProjectImage($projectRowB['image_path'], 'denied_foreign_cleanup', $portfolioA);
    phase2AssertSame($bBefore, privateMediaFilesystemSnapshot($storageRoot, $portfolioB), 'Denied A-to-B mutation changed B filesystem.');
    $passed[] = 'T-MEDIA-OWNER-ISOLATION';

    $publicA = resolvePublicReadContext($database, 'media-a');
    phase2Assert($publicA !== null && publicMediaDescriptor($database, $publicA, 'profile', null) !== null && publicMediaDescriptor($database, $publicA, 'project', (string) $projectA) !== null, 'Published A public media was unavailable.');
    phase2AssertSame(null, resolvePublicReadContext($database, 'media-b'), 'Unpublished B public media was available.');
    phase2AssertSame(null, publicMediaDescriptor($database, $publicA, 'project', (string) $projectB), 'A public context exposed B project media.');
    $database->prepare('UPDATE portfolios SET is_published = 1, published_at = CURRENT_TIMESTAMP WHERE id = :id')->execute(['id' => $portfolioB]);
    $publicB = resolvePublicReadContext($database, 'media-b');
    phase2Assert($publicB !== null && publicMediaDescriptor($database, $publicB, 'profile', null) !== null && publicMediaDescriptor($database, $publicB, 'project', (string) $projectB) !== null, 'Published B public media was unavailable.');
    $database->prepare("UPDATE users SET account_status = 'disabled' WHERE id = :id")->execute(['id' => $userB]);
    phase2AssertSame(null, resolvePublicReadContext($database, 'media-b'), 'Disabled-owner public media remained available.');
    $passed[] = 'T-MEDIA-PUBLIC-ISOLATION';

    foreach (['../x.png', '..\\x.png', '%2e%2e/x.png', '/etc/passwd', 'C:\\secret.png', 'portfolios//1/projects/x.png', 'portfolios/1/projects/a%2fb.png', $projectRowB['image_path']] as $candidate) {
        $parsed = parseManagedMediaKey($candidate);
        if ($candidate === $projectRowB['image_path']) phase2Assert($parsed !== null && resolvePrivateMediaPath($candidate, $portfolioA, 'projects') === null, 'Foreign key was not context rejected.');
        else phase2AssertSame(null, $parsed, 'Unsafe managed key was accepted: ' . $candidate);
    }
    $passed[] = 'T-MEDIA-TRAVERSAL';

    $collisionName = 'collision.png';
    $collisionSource = $sourceRoot . '/uploads/projects/a-project.png';
    $collisionKey = copyFileToPrivateMedia($collisionSource, $portfolioA, 'projects', $collisionName);
    phase2Assert($collisionKey !== null, 'Final-rename collision fixture could not be created.');
    $collisionHash = hash_file('sha256', resolvePrivateMediaPath($collisionKey, $portfolioA, 'projects'));
    phase2AssertSame(null, copyFileToPrivateMedia($collisionSource, $portfolioA, 'projects', $collisionName), 'Existing final media was overwritten.');
    phase2AssertSame($collisionHash, hash_file('sha256', resolvePrivateMediaPath($collisionKey, $portfolioA, 'projects')), 'Failed final rename changed the committed file.');

    $oldAKey = (string) $projectRowA['image_path'];
    $replacementKey = copyFileToPrivateMedia($sourceRoot . '/uploads/projects/b-project.png', $portfolioA, 'projects', 'replacement.png');
    phase2Assert($replacementKey !== null && updateAuthorizedProject($database, $contextA, $projectA, 'Media A Project', 'Media', 'Private media test', 'https://example.test/media', $replacementKey), 'Replacement DB update failed.');
    phase2Assert(is_file(resolvePrivateMediaPath($oldAKey, $portfolioA, 'projects')), 'Old media disappeared before replacement committed.');
    phase2Assert(deletePrivateMediaFile($oldAKey, $portfolioA, 'projects'), 'Committed replacement could not retire old media.');

    $failedKey = copyFileToPrivateMedia($collisionSource, $portfolioA, 'projects', 'db-failure.png');
    phase2Assert($failedKey !== null, 'DB failure compensation fixture could not be staged.');
    phase2AssertSame(false, updateAuthorizedProject($database, $contextA, $projectB, 'No', 'No', 'No', 'https://example.test/no', $failedKey), 'Foreign DB reference update unexpectedly succeeded.');
    phase2Assert(deletePrivateMediaFile($failedKey, $portfolioA, 'projects'), 'Failed DB update left staged media behind.');
    phase2AssertSame($bBefore, privateMediaFilesystemSnapshot($storageRoot, $portfolioB), 'Failed DB update changed B filesystem.');
    $passed[] = 'T-MEDIA-REPLACEMENT-FAILURES';

    $blockedRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'blocked-root';
    mkdir($blockedRoot, 0700);
    file_put_contents($blockedRoot . DIRECTORY_SEPARATOR . 'portfolios', 'blocked');
    putenv('ATHERCAR_STORAGE_ROOT=' . $blockedRoot);
    phase2AssertSame(null, copyFileToPrivateMedia($collisionSource, $portfolioA, 'projects', 'blocked.png'), 'Unavailable staging path accepted a write.');
    putenv('ATHERCAR_STORAGE_ROOT=' . $environment->storageRoot . DIRECTORY_SEPARATOR . 'missing-root');
    try { requirePrivateStorageRoot(true); throw new RuntimeException('Missing storage root was accepted.'); } catch (PrivateStorageConfigurationException) {}
    putenv('ATHERCAR_STORAGE_ROOT=' . $storageRoot);
    $passed[] = 'T-MEDIA-STORAGE-FAIL-CLOSED';
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL private media rehearsal: {$exception->getMessage()}\n");
    exit(1);
} finally {
    putenv('ATHERCAR_STORAGE_ROOT');
    if ($environment instanceof TestEnvironment) {
        try { $environment->tearDown(); } catch (Throwable $exception) { fwrite(STDERR, "FAIL private media teardown: {$exception->getMessage()}\n"); exit(1); }
    }
}

foreach ($passed as $name) echo "PASS {$name}\n";
echo "PASS private media rehearsal\n";
