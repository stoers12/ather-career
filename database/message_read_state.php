<?php
declare(strict_types=1);

function executeMessageReadStateMigration(PDO $database): void
{
    if (fetchColumnDefinition($database, 'messages', 'read_at') === null) {
        $database->exec('ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL');
    }
    requireColumnDefinition($database, 'messages', 'read_at', 'timestamp', 'YES');
    // Runs on every attempt so a retry after interrupted DDL still backfills;
    // only NULLs change, so no read time already recorded is ever overwritten.
    $database->exec('UPDATE messages SET read_at = created_at WHERE read_at IS NULL');
}
