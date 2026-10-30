<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ownership_backfill.php';
require_once __DIR__ . '/identity_foundation.php';
require_once __DIR__ . '/onboarding_attestations.php';
require_once __DIR__ . '/onboarding_drafts.php';
require_once __DIR__ . '/profile_version.php';
require_once __DIR__ . '/final_profile_fields.php';
require_once __DIR__ . '/onboarding_completions.php';
require_once __DIR__ . '/owner_journey_cutover.php';
require_once __DIR__ . '/profile_contact_types.php';
require_once __DIR__ . '/project_publication.php';
require_once __DIR__ . '/message_read_state.php';
require_once __DIR__ . '/project_live_url.php';
require_once __DIR__ . '/migration_schema_inspection.php';
require_once __DIR__ . '/legacy_schema_migrations.php';

class MigrationPreconditionException extends RuntimeException
{
}

const MIGRATION_LOCK_NAME = 'ather_career_schema_migrations';
const MIGRATION_LOCK_TIMEOUT_SECONDS = 5;
const BASELINE_MIGRATION_VERSION = '001';
const BASELINE_MIGRATION_NAME = 'baseline';
const OWNERSHIP_EXPAND_MIGRATION_VERSION = '003';
const OWNERSHIP_EXPAND_MIGRATION_NAME = 'ownership_expand';
const OWNERSHIP_CONTRACT_MIGRATION_VERSION = '004';
const OWNERSHIP_CONTRACT_MIGRATION_NAME = 'ownership_contract';
const PUBLIC_LIFECYCLE_MIGRATION_VERSION = '005';
const PUBLIC_LIFECYCLE_MIGRATION_NAME = 'public_lifecycle';
const PROJECT_UPDATED_AT_MIGRATION_VERSION = '012';
const PROJECT_UPDATED_AT_MIGRATION_NAME = 'project_updated_at';
const OPTIONAL_PROJECT_GITHUB_URL_MIGRATION_VERSION = '013';
const OPTIONAL_PROJECT_GITHUB_URL_MIGRATION_NAME = 'optional_project_github_url';
const IDENTITY_FOUNDATION_MIGRATION_VERSION = '014';
const IDENTITY_FOUNDATION_MIGRATION_NAME = 'identity_foundation';

function migrationFailure(string $message, ?string $version = null): never
{
    if ($version !== null) {
        fwrite(STDERR, "Migration {$version} failed. Manual inspection may be required.\n");
    }
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function parseMigrationTarget(array $arguments): ?string
{
    if (count($arguments) === 1) {
        return null;
    }
    if (count($arguments) === 2 && preg_match('/^--through=(\d{3})$/', $arguments[1], $matches)) {
        return $matches[1];
    }

    migrationFailure('Usage: php database/migrate.php [--through=NNN]');
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
    if ($migration['version'] === '026' && $migration['name'] === 'project_live_url') { executeProjectLiveUrlMigration($database); return; }
    if ($migration['version'] === '025' && $migration['name'] === 'message_read_state') { executeMessageReadStateMigration($database); return; }
    if ($migration['version'] === '022' && $migration['name'] === 'project_publication') { executeProjectPublicationMigration($database); return; }
    if ($migration['version'] === '021' && $migration['name'] === 'profile_contact_types') { executeProfileContactTypesMigration($database); return; }
    if ($migration['version'] === '020' && $migration['name'] === 'owner_journey_cutover') { executeOwnerJourneyCutoverMigration($database); return; }
    if ($migration['version'] === '019' && $migration['name'] === 'onboarding_completions') {
        executeOnboardingCompletionMigration($database);
        return;
    }
    if ($migration['version'] === '018' && $migration['name'] === 'final_profile_fields') {
        executeFinalProfileMigration($database);
        return;
    }
    if ($migration['version'] === '017' && $migration['name'] === 'profile_version') {
        executeProfileVersionMigration($database);
        return;
    }
    if ($migration['version'] === '016' && $migration['name'] === 'onboarding_drafts') {
        executeOnboardingDraftMigration($database);
        return;
    }
    if ($migration['version'] === '015' && $migration['name'] === 'onboarding_attestations') {
        executeOnboardingAttestationMigration($database);
        return;
    }
    if ($migration['version'] === '002' && $migration['name'] === 'integrity_constraints') {
        executeIntegrityConstraintsMigration($database);
        return;
    }
    if ($migration['version'] === OWNERSHIP_EXPAND_MIGRATION_VERSION && $migration['name'] === OWNERSHIP_EXPAND_MIGRATION_NAME) {
        executeOwnershipExpandMigration($database);
        return;
    }
    if ($migration['version'] === OWNERSHIP_CONTRACT_MIGRATION_VERSION && $migration['name'] === OWNERSHIP_CONTRACT_MIGRATION_NAME) {
        executeOwnershipContractMigration($database);
        return;
    }
    if ($migration['version'] === PUBLIC_LIFECYCLE_MIGRATION_VERSION && $migration['name'] === PUBLIC_LIFECYCLE_MIGRATION_NAME) {
        executePublicLifecycleMigration($database);
        return;
    }
    if ($migration['version'] === PROJECT_UPDATED_AT_MIGRATION_VERSION && $migration['name'] === PROJECT_UPDATED_AT_MIGRATION_NAME) {
        executeProjectUpdatedAtMigration($database);
        return;
    }
    if ($migration['version'] === OPTIONAL_PROJECT_GITHUB_URL_MIGRATION_VERSION && $migration['name'] === OPTIONAL_PROJECT_GITHUB_URL_MIGRATION_NAME) {
        executeOptionalProjectGithubUrlMigration($database);
        return;
    }
    if ($migration['version'] === IDENTITY_FOUNDATION_MIGRATION_VERSION && $migration['name'] === IDENTITY_FOUNDATION_MIGRATION_NAME) {
        executeIdentityFoundationMigration($database);
        return;
    }

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
    global $argv;
    $targetVersion = parseMigrationTarget($argv);

    $database = null;
    $lockHeld = false;
    $currentVersion = null;

    try {
        $migrations = discoverMigrations(__DIR__ . '/migrations');
        if ($targetVersion !== null) {
            $targetKnown = false;
            foreach ($migrations as $migration) {
                if ($migration['version'] === $targetVersion) {
                    $targetKnown = true;
                    break;
                }
            }
            if (!$targetKnown) {
                throw new RuntimeException("Migration target {$targetVersion} is unavailable.");
            }
        }
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
            if ($targetVersion !== null && strcmp($migration['version'], $targetVersion) > 0) {
                break;
            }
            if (isset($applied[$migration['version']])) {
                if ($migration['version'] === '015') { verifyOnboardingAttestationSchema($database); }
                if ($migration['version'] === '016') { verifyOnboardingDraftSchema($database); }
                if ($migration['version'] === '017') { verifyProfileVersionSchema($database); }
                if ($migration['version'] === '018') { verifyFinalProfileSchema($database); }
                if ($migration['version'] === '019') { verifyOnboardingCompletionSchema($database); }
                if ($migration['version'] === '020') { verifyOwnerJourneyCutoverSchema($database); }
                if ($migration['version'] === '021') { verifyProfileContactTypesSchema($database); }
                if ($migration['version'] === '022') { verifyProjectPublicationSchema($database); }
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
    } catch (Throwable $exception) {
        if ($currentVersion !== null) {
            fwrite(STDERR, "Migration {$currentVersion} failed. Manual inspection may be required.\n");
        }
        if ($exception instanceof MigrationPreconditionException || $exception instanceof OwnershipBackfillException) {
            fwrite(STDERR, $exception->getMessage() . "\n");
        }
        fwrite(STDERR, "Migration runner stopped without applying later migrations.\n");
        exit(1);
    } finally {
        if ($lockHeld && $database instanceof PDO) {
            releaseMigrationLock($database);
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { runMigrations(); }
