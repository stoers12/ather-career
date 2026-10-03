<?php

declare(strict_types=1);

/*
 * MySQL can accept health probes before its mounted baseline SQL has finished.
 * Wait for the immutable baseline tables before the migration ledger inspects
 * them, so Production startup never treats that initialization window as a
 * failed application boot.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

const PRODUCTION_BOOTSTRAP_WAIT_SECONDS = 60;

$deadline = microtime(true) + PRODUCTION_BOOTSTRAP_WAIT_SECONDS;
do {
    try {
        $database = getDatabaseConnection();
        $tables = (int) $database->query(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name IN ('projects', 'messages', 'personal_info', 'skills')"
        )->fetchColumn();
        if ($tables === 4) {
            echo "Production database bootstrap is ready.\n";
            exit;
        }
    } catch (PDOException | DatabaseConfigurationException) {
        // The application account and baseline schema are created by MySQL's
        // initialization sequence; neither is assumed ready yet.
    }
    usleep(500000);
} while (microtime(true) < $deadline);

fwrite(STDERR, "Production database bootstrap did not become ready in time.\n");
exit(1);
