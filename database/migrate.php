<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

const MIGRATION_LOCK_NAME = 'ather_career_schema_migrations';
const MIGRATION_LOCK_TIMEOUT_SECONDS = 5;
const BASELINE_MIGRATION_VERSION = '001';
const BASELINE_MIGRATION_NAME = 'baseline';

function migrationFailure(string $message, ?string $version = null): never
{
    if ($version !== null) {
        fwrite(STDERR, "Migration {$version} failed. Manual inspection may be required.\n");
    }
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function discoverMigrations(string $directory): array
{
    $resolvedDirectory = realpath($directory);
    if ($resolvedDirectory === false || !is_dir($resolvedDirectory)) {
        throw new RuntimeException('Migration directory is unavailable.');
    }

    $migrations = [];
    foreach (new DirectoryIterator($resolvedDirectory) as $file) {
        if ($file->isDot()) {
            continue;
        }
        if (!$file->isFile()) {
            throw new RuntimeException('Migration directory contains a non-file entry.');
        }

        $filename = $file->getFilename();
        if (!preg_match('/^(\d{3})_([a-z0-9][a-z0-9_-]*)\.sql$/', $filename, $matches)) {
            throw new RuntimeException('Migration directory contains a malformed filename.');
        }

        $version = $matches[1];
        if (isset($migrations[$version])) {
            throw new RuntimeException("Duplicate migration version {$version}.");
        }

        $path = realpath($file->getPathname());
        if ($path === false || dirname($path) !== $resolvedDirectory) {
            throw new RuntimeException('Migration path is outside the managed directory.');
        }

        $migrations[$version] = ['version' => $version, 'name' => $matches[2], 'path' => $path];
    }

    if (!isset($migrations[BASELINE_MIGRATION_VERSION]) || $migrations[BASELINE_MIGRATION_VERSION]['name'] !== BASELINE_MIGRATION_NAME) {
        throw new RuntimeException('The immutable baseline migration is missing or invalid.');
    }

    ksort($migrations, SORT_NATURAL);
    return array_values($migrations);
}

function createMigrationLedger(PDO $database): void
{
    $database->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function fetchColumns(PDO $database, string $table): array
{
    $statement = $database->prepare(
        'SELECT column_name AS name, LOWER(column_type) AS type, is_nullable AS nullable, column_default AS default_value, LOWER(extra) AS extra
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :table
         ORDER BY ordinal_position'
    );
    $statement->execute(['table' => $table]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function verifyPrimaryIndex(PDO $database, string $table, string $column): bool
{
    $statement = $database->prepare(
        'SELECT column_name
         FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = :table AND index_name = "PRIMARY"
         ORDER BY seq_in_index'
    );
    $statement->execute(['table' => $table]);
    $columns = $statement->fetchAll(PDO::FETCH_COLUMN);

    return $columns === [$column];
}

function verifyExpectedColumns(PDO $database, string $table, array $expectedColumns): void
{
    $actualColumns = fetchColumns($database, $table);
    if (count($actualColumns) !== count($expectedColumns)) {
        throw new RuntimeException("Baseline drift detected in {$table} columns.");
    }

    $actualByName = [];
    foreach ($actualColumns as $column) {
        $actualByName[$column['name']] = $column;
    }

    foreach ($expectedColumns as $name => $expected) {
        if (!isset($actualByName[$name])) {
            throw new RuntimeException("Baseline drift detected in {$table}.{$name}.");
        }

        $actual = $actualByName[$name];
        if ($actual['type'] !== $expected['type'] || $actual['nullable'] !== $expected['nullable']) {
            throw new RuntimeException("Baseline drift detected in {$table}.{$name}.");
        }
        if (isset($expected['default']) && strcasecmp((string) $actual['default_value'], $expected['default']) !== 0) {
            throw new RuntimeException("Baseline drift detected in {$table}.{$name} default.");
        }
        if (isset($expected['extra']) && !str_contains($actual['extra'], $expected['extra'])) {
            throw new RuntimeException("Baseline drift detected in {$table}.{$name} attributes.");
        }
    }

}

function verifyMigrationLedger(PDO $database): void
{
    verifyExpectedColumns($database, 'schema_migrations', [
        'version' => ['type' => 'varchar(50)', 'nullable' => 'NO'],
        'name' => ['type' => 'varchar(255)', 'nullable' => 'NO'],
        'applied_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP'],
    ]);

    if (!verifyPrimaryIndex($database, 'schema_migrations', 'version')) {
        throw new RuntimeException('Migration ledger primary key is invalid.');
    }
}

function verifyBaselineSchema(PDO $database): void
{
    $expectedTables = [
        'projects' => [
            'id' => ['type' => 'int unsigned', 'nullable' => 'NO', 'extra' => 'auto_increment'],
            'title' => ['type' => 'varchar(150)', 'nullable' => 'NO'],
            'category' => ['type' => 'varchar(100)', 'nullable' => 'NO'],
            'description' => ['type' => 'text', 'nullable' => 'NO'],
            'github_url' => ['type' => 'varchar(500)', 'nullable' => 'NO'],
            'created_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP'],
            'image_path' => ['type' => 'varchar(255)', 'nullable' => 'YES'],
        ],
        'messages' => [
            'id' => ['type' => 'int unsigned', 'nullable' => 'NO', 'extra' => 'auto_increment'],
            'name' => ['type' => 'varchar(100)', 'nullable' => 'NO'],
            'email' => ['type' => 'varchar(255)', 'nullable' => 'NO'],
            'message' => ['type' => 'text', 'nullable' => 'NO'],
            'created_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP'],
        ],
        'personal_info' => [
            'id' => ['type' => 'int unsigned', 'nullable' => 'NO', 'extra' => 'auto_increment'],
            'full_name' => ['type' => 'varchar(150)', 'nullable' => 'NO'],
            'professional_title' => ['type' => 'varchar(150)', 'nullable' => 'YES'],
            'email' => ['type' => 'varchar(150)', 'nullable' => 'YES'],
            'phone_primary' => ['type' => 'varchar(30)', 'nullable' => 'YES'],
            'phone_secondary' => ['type' => 'varchar(30)', 'nullable' => 'YES'],
            'location' => ['type' => 'varchar(150)', 'nullable' => 'YES'],
            'about_me' => ['type' => 'text', 'nullable' => 'YES'],
            'work_description' => ['type' => 'text', 'nullable' => 'YES'],
            'linkedin_url' => ['type' => 'varchar(255)', 'nullable' => 'YES'],
            'github_url' => ['type' => 'varchar(255)', 'nullable' => 'YES'],
            'instagram_url' => ['type' => 'varchar(255)', 'nullable' => 'YES'],
            'facebook_url' => ['type' => 'varchar(255)', 'nullable' => 'YES'],
            'website_url' => ['type' => 'varchar(255)', 'nullable' => 'YES'],
            'updated_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'extra' => 'on update current_timestamp'],
            'profile_image_path' => ['type' => 'varchar(255)', 'nullable' => 'YES'],
        ],
        'skills' => [
            'id' => ['type' => 'int unsigned', 'nullable' => 'NO', 'extra' => 'auto_increment'],
            'skill_name' => ['type' => 'varchar(100)', 'nullable' => 'NO'],
            'created_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP'],
        ],
    ];

    foreach ($expectedTables as $table => $columns) {
        verifyExpectedColumns($database, $table, $columns);
        if (!verifyPrimaryIndex($database, $table, 'id')) {
            throw new RuntimeException("Baseline drift detected in {$table} primary key.");
        }
    }
}

function acquireMigrationLock(PDO $database): void
{
    $statement = $database->prepare('SELECT GET_LOCK(:name, :timeout)');
    $statement->execute(['name' => MIGRATION_LOCK_NAME, 'timeout' => MIGRATION_LOCK_TIMEOUT_SECONDS]);
    if ((string) $statement->fetchColumn() !== '1') {
        throw new RuntimeException('Could not acquire the migration execution lock.');
    }
}

function releaseMigrationLock(PDO $database): void
{
    try {
        $statement = $database->prepare('SELECT RELEASE_LOCK(:name)');
        $statement->execute(['name' => MIGRATION_LOCK_NAME]);
    } catch (Throwable) {
        // Closing the PDO connection also releases the MySQL advisory lock.
    }
}

function fetchAppliedMigrations(PDO $database): array
{
    $rows = $database->query('SELECT version, name FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);
    $applied = [];
    foreach ($rows as $row) {
        $applied[$row['version']] = $row['name'];
    }

    return $applied;
}

function recordMigration(PDO $database, array $migration): void
{
    $statement = $database->prepare('INSERT INTO schema_migrations (version, name) VALUES (:version, :name)');
    $statement->execute(['version' => $migration['version'], 'name' => $migration['name']]);
}

function executeSqlMigration(PDO $database, array $migration): void
{
    $sql = file_get_contents($migration['path']);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Migration SQL is empty or unreadable.');
    }

    // MySQL DDL can auto-commit. Do not pretend a filesystem-like rollback exists;
    // record the migration only after SQL succeeds and inspect failed DDL manually.
    $database->exec($sql);
}

function runMigrations(): void
{
    global $argc;
    if ($argc !== 1) {
        migrationFailure('Usage: php database/migrate.php');
    }

    $database = null;
    $lockHeld = false;
    $currentVersion = null;

    try {
        $migrations = discoverMigrations(__DIR__ . '/migrations');
        $database = getDatabaseConnection();
        acquireMigrationLock($database);
        $lockHeld = true;
        createMigrationLedger($database);
        verifyMigrationLedger($database);
        $applied = fetchAppliedMigrations($database);

        foreach ($applied as $version => $name) {
            $migration = null;
            foreach ($migrations as $candidate) {
                if ($candidate['version'] === $version) {
                    $migration = $candidate;
                    break;
                }
            }
            if ($migration === null || $migration['name'] !== $name) {
                throw new RuntimeException('Applied migration history does not match repository files.');
            }
        }

        $pending = 0;
        foreach ($migrations as $migration) {
            if (isset($applied[$migration['version']])) {
                continue;
            }

            $pending++;
            $currentVersion = $migration['version'];
            if ($migration['version'] === BASELINE_MIGRATION_VERSION) {
                verifyBaselineSchema($database);
                recordMigration($database, $migration);
                fwrite(STDOUT, "Adopted baseline migration {$migration['version']}_{$migration['name']}.\n");
                continue;
            }

            executeSqlMigration($database, $migration);
            recordMigration($database, $migration);
            fwrite(STDOUT, "Applied migration {$migration['version']}_{$migration['name']}.\n");
        }

        if ($pending === 0) {
            fwrite(STDOUT, "No pending migrations.\n");
        }
    } catch (Throwable) {
        if ($currentVersion !== null) {
            fwrite(STDERR, "Migration {$currentVersion} failed. Manual inspection may be required.\n");
        }
        fwrite(STDERR, "Migration runner stopped without applying later migrations.\n");
        exit(1);
    } finally {
        if ($lockHeld && $database instanceof PDO) {
            releaseMigrationLock($database);
        }
    }
}

runMigrations();
