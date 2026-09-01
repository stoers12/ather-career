<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/media_migration.php';

if (count($argv) !== 3 || $argv[1] !== '--source-root' || !isAbsoluteFilesystemPath($argv[2])) {
    fwrite(STDERR, "Usage: php scripts/migrate-phase2-private-media.php --source-root <absolute-v1-root>\n");
    exit(1);
}

try {
    $result = migrateLegacyMedia(getDatabaseConnection(), $argv[2]);
    fwrite(STDOUT, "Migrated {$result['profiles']} profile image(s) and {$result['projects']} project image(s). Source files were preserved.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Private media migration stopped: ' . $exception->getMessage() . "\n");
    exit(1);
}
