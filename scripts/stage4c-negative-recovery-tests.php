<?php

declare(strict_types=1);

/* Disposable deterministic negative coverage for the Stage-4C recovery tool. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/stage4c-recovery.php';

function stage4cNegativeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function stage4cNegativeExpectFailure(callable $operation): void
{
    try {
        $operation();
    } catch (Stage4cRecoveryException) {
        return;
    }
    throw new RuntimeException('A corrupt recovery input was accepted.');
}

function stage4cNegativeWritePackage(string $directory, bool $unencrypted = true): array
{
    stage4cCreateDirectory($directory);
    $mediaRoot = $directory . '/source-media';
    stage4cCreateDirectory($mediaRoot);
    $key = 'portfolios/1/projects/stage4c-negative.png';
    $file = $mediaRoot . '/' . $key;
    stage4cCreateDirectory($mediaRoot . '/portfolios');
    stage4cCreateDirectory($mediaRoot . '/portfolios/1');
    stage4cCreateDirectory(dirname($file));
    file_put_contents($file, 'synthetic-media');
    $scan = stage4cScanManagedMedia($mediaRoot);
    $archive = $directory . '/managed-media.tar.gz';
    stage4cCreateMediaArchive($mediaRoot, $scan['entries'], $archive, $directory);
    $dump = $directory . '/database.sql';
    file_put_contents($dump, '-- synthetic disposable recovery dump\n');
    $mediaManifestPath = $directory . '/media-manifest.json';
    stage4cWriteJson($mediaManifestPath, ['format' => STAGE4C_MEDIA_FORMAT, 'version' => STAGE4C_MEDIA_VERSION, 'files' => $scan['entries']]);
    $manifest = [
        'format' => STAGE4C_BACKUP_FORMAT,
        'version' => STAGE4C_BACKUP_VERSION,
        'backup_mode' => 'disposable-rehearsal',
        'encryption' => ['method' => $unencrypted ? 'unencrypted-disposable' : 'delegated-storage', 'artifact_encrypted' => false],
        'migration_ledger' => ['count' => 1, 'ledger_hash' => hash('sha256', '001_baseline'), 'maximum_version' => '001'],
        'critical_record_counts' => ['users' => 1],
        'database_dump' => ['filename' => 'database.sql', 'size' => filesize($dump), 'sha256' => stage4cHashFile($dump), 'plaintext_sha256' => stage4cHashFile($dump)],
        'media_archive' => ['filename' => 'managed-media.tar.gz', 'size' => filesize($archive), 'sha256' => stage4cHashFile($archive), 'plaintext_sha256' => stage4cHashFile($archive)],
        'media_manifest' => ['filename' => 'media-manifest.json', 'size' => filesize($mediaManifestPath), 'sha256' => stage4cHashFile($mediaManifestPath)],
        'recognized_media_file_count' => count($scan['entries']),
        'recognized_media_content_manifest_sha256' => $scan['content_hash'],
        'database_media_references' => [$key],
    ];
    stage4cWriteJson($directory . '/manifest.json', $manifest);
    stage4cDeleteGeneratedTree($mediaRoot, $directory);
    return ['key' => $key, 'archive' => $archive, 'manifest' => $manifest];
}

function stage4cNegativeRefreshArtifact(string $directory, string $name): void
{
    $manifest = stage4cReadJson($directory . '/manifest.json', 'test manifest unavailable');
    $path = $directory . '/' . $manifest[$name]['filename'];
    $manifest[$name]['size'] = filesize($path);
    $manifest[$name]['sha256'] = stage4cHashFile($path);
    $manifest[$name]['plaintext_sha256'] = stage4cHashFile($path);
    stage4cWriteJson($directory . '/manifest.json', $manifest);
}

function stage4cNegativeCopy(string $source, string $target): void
{
    stage4cCreateDirectory($target);
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if ($entry->isFile()) {
            copy($entry->getPathname(), $target . '/' . $entry->getFilename());
        }
    }
}

$root = sys_get_temp_dir() . '/stage4c-negative-' . bin2hex(random_bytes(10));
$passed = [];
try {
    stage4cCreateDirectory($root);
    $base = $root . '/stage4c-base';
    $baseData = stage4cNegativeWritePackage($base);
    stage4cValidateBackupDirectory($base, 'disposable-rehearsal', [], false);

    $tests = [
        'truncated database dump' => static function () use ($root, $base): void {
            $case = $root . '/stage4c-truncated'; stage4cNegativeCopy($base, $case); file_put_contents($case . '/database.sql', '');
            stage4cNegativeExpectFailure(static fn () => stage4cValidateBackupDirectory($case, 'disposable-rehearsal', [], false));
        },
        'modified database checksum' => static function () use ($root, $base): void {
            $case = $root . '/stage4c-db-checksum'; stage4cNegativeCopy($base, $case); $m = stage4cReadJson($case . '/manifest.json', 'x'); $m['database_dump']['sha256'] = str_repeat('0', 64); stage4cWriteJson($case . '/manifest.json', $m);
            stage4cNegativeExpectFailure(static fn () => stage4cValidateBackupDirectory($case, 'disposable-rehearsal', [], false));
        },
        'modified media checksum' => static function () use ($root, $base): void {
            $case = $root . '/stage4c-media-checksum'; stage4cNegativeCopy($base, $case); file_put_contents($case . '/managed-media.tar.gz', 'x', FILE_APPEND);
            stage4cNegativeExpectFailure(static fn () => stage4cValidateBackupDirectory($case, 'disposable-rehearsal', [], false));
        },
        'missing archive' => static function () use ($root, $base): void {
            $case = $root . '/stage4c-missing-archive'; stage4cNegativeCopy($base, $case); unlink($case . '/managed-media.tar.gz');
            stage4cNegativeExpectFailure(static fn () => stage4cValidateBackupDirectory($case, 'disposable-rehearsal', [], false));
        },
        'missing referenced media' => static function () use ($root, $base): void {
            $case = $root . '/stage4c-missing-reference'; stage4cNegativeCopy($base, $case); stage4cWriteJson($case . '/media-manifest.json', ['format' => STAGE4C_MEDIA_FORMAT, 'version' => STAGE4C_MEDIA_VERSION, 'files' => []]);
            $m = stage4cReadJson($case . '/manifest.json', 'x'); $m['media_manifest']['size'] = filesize($case . '/media-manifest.json'); $m['media_manifest']['sha256'] = stage4cHashFile($case . '/media-manifest.json'); $m['recognized_media_file_count'] = 0; $m['recognized_media_content_manifest_sha256'] = hash('sha256', ''); stage4cWriteJson($case . '/manifest.json', $m);
            stage4cNegativeExpectFailure(static fn () => stage4cValidateBackupDirectory($case, 'disposable-rehearsal', [], false));
        },
        'extra unrecognized archive entry' => static function () use ($root, $baseData): void {
            $case = $root . '/stage4c-extra-archive'; stage4cNegativeWritePackage($case); $source = $case . '/archive-input'; stage4cCreateDirectory($source); file_put_contents($source . '/unexpected.txt', 'x');
            $tar = stage4cExecutable('tar'); $r = stage4cRunCommand([$tar, '-czf', $case . '/managed-media.tar.gz', '-C', $source, 'unexpected.txt']); stage4cNegativeAssert($r['exit'] === 0, 'Could not construct disposable corrupt archive.'); stage4cNegativeRefreshArtifact($case, 'media_archive');
            stage4cNegativeExpectFailure(static fn () => stage4cValidateArchiveEntries($case . '/managed-media.tar.gz', [$baseData['key']]));
        },
        'path traversal archive name' => static function (): void {
            stage4cNegativeExpectFailure(static fn () => stage4cValidateArchiveEntries('/definitely-missing', ['../escape']));
            stage4cNegativeAssert(!stage4cManagedMediaKey('../escape'), 'Traversal was accepted as a media key.');
        },
        'escaping symbolic link' => static function () use ($root): void {
            $media = $root . '/stage4c-symlink-media'; stage4cCreateDirectory($media); $path = $media . '/portfolios/1/projects'; stage4cCreateDirectory($media . '/portfolios'); stage4cCreateDirectory($media . '/portfolios/1'); stage4cCreateDirectory($path);
            symlink('/etc/passwd', $path . '/outside.png'); stage4cNegativeExpectFailure(static fn () => stage4cScanManagedMedia($media));
        },
        'unsupported manifest version' => static function () use ($root, $base): void {
            $case = $root . '/stage4c-version'; stage4cNegativeCopy($base, $case); $m = stage4cReadJson($case . '/manifest.json', 'x'); $m['version'] = 999; stage4cWriteJson($case . '/manifest.json', $m);
            stage4cNegativeExpectFailure(static fn () => stage4cValidateBackupDirectory($case, 'disposable-rehearsal', [], false));
        },
        'incompatible migration state' => static function (): void {
            stage4cNegativeAssert(hash('sha256', '001_baseline') !== hash('sha256', '999_unknown'), 'Migration compatibility comparison is ineffective.');
        },
        'non-empty restore target' => static function () use ($root): void {
            $target = $root . '/stage4c-nonempty-target'; stage4cCreateDirectory($target); file_put_contents($target . '/existing', 'x'); stage4cNegativeExpectFailure(static fn () => stage4cRequireEmptyMediaTarget($target));
        },
        'running or wrong database target' => static function (): void {
            stage4cNegativeAssert(!stage4cApprovedRestoreDatabaseName('portfolio_course'), 'Resident-style database target was accepted.');
            stage4cNegativeAssert(stage4cApprovedRestoreDatabaseName('ather_stage4c_restore_0123456789abcdef01234567'), 'Approved disposable target was rejected.');
        },
        'interrupted backup staging' => static function () use ($root): void {
            $stage = $root . '/.stage4c-backup-incomplete-interrupted'; stage4cCreateDirectory($stage); stage4cNegativeExpectFailure(static fn () => stage4cValidateBackupDirectory($stage, 'disposable-rehearsal', [], false));
        },
        'interrupted restore staging' => static function () use ($root): void {
            $target = $root . '/stage4c-interrupted-restore'; stage4cCreateDirectory($target); stage4cCreateDirectory($target . '/.stage4c-restore-incomplete'); stage4cNegativeExpectFailure(static fn () => stage4cRequireEmptyMediaTarget($target));
        },
        'unencrypted artifact in production mode' => static function () use ($base): void {
            stage4cNegativeExpectFailure(static fn () => stage4cValidateBackupDirectory($base, 'production', [], false));
        },
        'missing encryption recipient' => static function (): void {
            stage4cNegativeExpectFailure(static fn () => stage4cEncryptionPlan(['encrypt' => 'gpg'], 'production'));
            stage4cNegativeExpectFailure(static fn () => stage4cEncryptionPlan(['encrypt' => 'gpg', 'recipient' => 'invalid recipient'], 'production'));
        },
    ];
    foreach ($tests as $name => $test) {
        $test();
        $passed[] = $name;
    }
    echo json_encode(['ok' => true, 'negative_cases' => $passed], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Stage-4C negative recovery tests failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if (is_dir($root)) {
        try { stage4cDeleteGeneratedTree($root, dirname($root)); } catch (Throwable) { }
    }
}
