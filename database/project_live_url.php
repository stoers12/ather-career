<?php
declare(strict_types=1);

function executeProjectLiveUrlMigration(PDO $database): void
{
    if (fetchColumnDefinition($database, 'projects', 'live_url') === null) {
        $database->exec('ALTER TABLE projects ADD COLUMN live_url VARCHAR(500) NULL DEFAULT NULL AFTER github_url');
    }
    requireColumnDefinition($database, 'projects', 'live_url', 'varchar(500)', 'YES');
}
