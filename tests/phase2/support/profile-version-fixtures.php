<?php
declare(strict_types=1);
require_once __DIR__ . '/draft-fixtures.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/owner_actions.php';
require_once __DIR__ . '/../../../includes/media_migration.php';

function profileTestAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
/** ADD/DROP rehearsal DDL can move column ordinals; compare every named value. */
function profileTestCanonicalRows(array $rows): array
{
    foreach ($rows as &$row) { ksort($row); } unset($row);
    return $rows;
}
function profileTestSnapshot(PDO $db): array
{
    $result = [];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows = profileTestCanonicalRows($db->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC));
        $encoded = array_map(static fn ($row) => serialize($row), $rows);
        sort($encoded); $result[$table] = $encoded;
    }
    ksort($result); return $result;
}
function profileTestMigrate(bool $success, ?string $failure = null): void
{
    $env = getenv(); unset($env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER']);
    if ($failure !== null) { $env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER'] = $failure; }
    $process = proc_open([PHP_BINARY, __DIR__ . '/../../../database/migrate.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    profileTestAssert(is_resource($process), 'Migration child unavailable.'); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    profileTestAssert((proc_close($process) === 0) === $success, 'Unexpected profile migration result: ' . $out . $err);
}
function profileTestContext(PDO $db, int $userId): AuthorizedPortfolioContext
{
    journeyFixtureLegacyOwner($db, $userId);
    attestationTestSession($userId); return requireOwnedPortfolioContext($db);
}
function profileTestPng(string $path): void
{
    $image = imagecreatetruecolor(400, 400); imagefill($image, 0, 0, imagecolorallocate($image, 32, 100, 80));
    imagepng($image, $path); imagedestroy($image);
}
function profileTestImage(string $png, int $portfolioId): string
{
    $key = copyFileToPrivateMedia($png, $portfolioId, 'profile_original', createManagedUploadFilename('profile', 'png'));
    profileTestAssert($key !== null && generateProfilePresentationImage($key, $portfolioId) !== null, 'Could not stage synthetic image.');
    return $key;
}
function profileTestImageExists(string $key, int $portfolioId): bool
{
    clearstatcache();
    $path = resolvePrivateMediaPath($key, $portfolioId, 'profile_original');
    return $path !== null && is_file($path);
}
function profileTestPrivateFiles(string $root): array
{
    clearstatcache(); $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) { $files[$file->getPathname()] = hash_file('sha256', $file->getPathname()); }
    }
    ksort($files); return $files;
}
function profileTestRace(int $userId, array $inputs): array
{
    $workers = [];
    try {
        foreach ($inputs as $input) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/../../../scripts/run-profile-version-race-worker.php', (string) $userId,
                base64_encode(json_encode($input, JSON_THROW_ON_ERROR))], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            profileTestAssert(is_resource($process), 'Profile worker unavailable.'); stream_set_timeout($pipes[1], 25); $workers[] = [$process, $pipes];
        }
        foreach ($workers as $index => [$process, $pipes]) {
            $ready = fgets($pipes[1]);
            profileTestAssert($ready === "READY\n", 'Profile worker barrier failed for ' . $inputs[$index]['mode'] . ': ' . ($ready === false ? stream_get_contents($pipes[2]) : trim($ready)));
        }
        foreach ($workers as [$process, $pipes]) { fwrite($pipes[0], "GO\n"); fclose($pipes[0]); }
        $results = [];
        foreach ($workers as $index => [$process, $pipes]) {
            $results[] = trim(stream_get_contents($pipes[1]));
            $diagnostic = trim(stream_get_contents($pipes[2]));
            foreach ($diagnostic === '' ? [] : explode("\n", $diagnostic) as $line) {
                $record = json_decode(substr($line, (int) strpos($line, '{')), true);
                $normalization = is_array($record) && ($record['event'] ?? null) === 'media_normalized' && ($record['level'] ?? null) === 'notice';
                $legacyNotice = ($inputs[$index]['legacy_notice'] ?? false) && is_array($record)
                    && ($record['event'] ?? null) === 'application_error' && ($record['action'] ?? null) === 'owner_profile_retire_committed_image_path_rejected';
                profileTestAssert($normalization || $legacyNotice, 'Unexpected profile worker diagnostic: ' . $line);
            }
            fclose($pipes[1]); fclose($pipes[2]); profileTestAssert(proc_close($process) === 0, 'Profile worker failure.');
        }
        sort($results); return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) {
            foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        }
    }
}
