<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ownership_backfill.php';

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

function fetchIndexDefinition(PDO $database, string $table, string $index): array
{
    $statement = $database->prepare(
        'SELECT column_name AS column_name, non_unique AS non_unique
         FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index
         ORDER BY seq_in_index'
    );
    $statement->execute(['table' => $table, 'index' => $index]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function fetchColumnDefinition(PDO $database, string $table, string $column): ?array
{
    $statement = $database->prepare(
        'SELECT LOWER(column_type) AS type, is_nullable AS nullable, column_default AS default_value, LOWER(extra) AS extra
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
    );
    $statement->execute(['table' => $table, 'column' => $column]);
    $definition = $statement->fetch(PDO::FETCH_ASSOC);

    return $definition === false ? null : $definition;
}

function requireColumnDefinition(PDO $database, string $table, string $column, string $type, string $nullable): array
{
    $definition = fetchColumnDefinition($database, $table, $column);
    if ($definition === null || $definition['type'] !== $type || $definition['nullable'] !== $nullable) {
        throw new RuntimeException("Migration found an incompatible {$table}.{$column} definition.");
    }

    return $definition;
}

function hasExpectedIndex(PDO $database, string $table, string $index, array $columns, bool $unique): bool
{
    $definition = fetchIndexDefinition($database, $table, $index);
    if ($definition === []) {
        return false;
    }

    $actualColumns = array_column($definition, 'column_name');
    $actualUnique = (string) $definition[0]['non_unique'] === '0';
    if ($actualColumns !== $columns || $actualUnique !== $unique) {
        throw new RuntimeException("Migration found an unexpected {$table}.{$index} definition.");
    }

    return true;
}

function ensureExpectedIndex(PDO $database, string $table, string $index, array $columns, bool $unique): void
{
    if (hasExpectedIndex($database, $table, $index, $columns, $unique)) {
        return;
    }

    $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';
    $quotedColumns = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns));
    $database->exec("CREATE {$kind} `{$index}` ON `{$table}` ({$quotedColumns})");
}

function hasExpectedForeignKey(PDO $database, string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn): bool
{
    $statement = $database->prepare(
        'SELECT key_column_usage.column_name AS local_column,
                key_column_usage.referenced_table_name AS referenced_table,
                key_column_usage.referenced_column_name AS referenced_column,
                referential_constraints.update_rule AS update_rule,
                referential_constraints.delete_rule AS delete_rule
         FROM information_schema.key_column_usage
         JOIN information_schema.referential_constraints
           ON referential_constraints.constraint_schema = key_column_usage.constraint_schema
          AND referential_constraints.table_name = key_column_usage.table_name
          AND referential_constraints.constraint_name = key_column_usage.constraint_name
         WHERE key_column_usage.table_schema = DATABASE()
           AND key_column_usage.table_name = :table
           AND key_column_usage.constraint_name = :constraint'
    );
    $statement->execute(['table' => $table, 'constraint' => $constraint]);
    $definition = $statement->fetch(PDO::FETCH_ASSOC);
    if ($definition === false) {
        return false;
    }

    if ($definition['local_column'] !== $column
        || $definition['referenced_table'] !== $referencedTable
        || $definition['referenced_column'] !== $referencedColumn
        || strtoupper((string) $definition['update_rule']) !== 'RESTRICT'
        || strtoupper((string) $definition['delete_rule']) !== 'RESTRICT') {
        throw new RuntimeException("Migration found an unexpected {$table}.{$constraint} foreign key.");
    }

    return true;
}

function ensureExpectedForeignKey(PDO $database, string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn): void
{
    if (hasExpectedForeignKey($database, $table, $constraint, $column, $referencedTable, $referencedColumn)) {
        return;
    }

    $database->exec(
        "ALTER TABLE `{$table}`
         ADD CONSTRAINT `{$constraint}`
         FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`{$referencedColumn}`)
         ON UPDATE RESTRICT ON DELETE RESTRICT"
    );
}

function requireExpectedCheckConstraint(PDO $database, string $table, string $constraint, array $requiredFragments): void
{
    $statement = $database->prepare(
        'SELECT check_constraints.check_clause
         FROM information_schema.table_constraints
         JOIN information_schema.check_constraints
           ON check_constraints.constraint_schema = table_constraints.constraint_schema
          AND check_constraints.constraint_name = table_constraints.constraint_name
         WHERE table_constraints.table_schema = DATABASE()
           AND table_constraints.table_name = :table
           AND table_constraints.constraint_name = :constraint
           AND table_constraints.constraint_type = "CHECK"'
    );
    $statement->execute(['table' => $table, 'constraint' => $constraint]);
    $clause = $statement->fetchColumn();
    if (!is_string($clause)) {
        throw new RuntimeException("Migration found a missing {$table}.{$constraint} check constraint.");
    }

    $normalized = strtolower($clause);
    foreach ($requiredFragments as $fragment) {
        if (!str_contains($normalized, strtolower($fragment))) {
            throw new RuntimeException("Migration found an incompatible {$table}.{$constraint} check constraint.");
        }
    }
}

function hasExpectedUniqueIndex(PDO $database, string $table, string $index, string $column): bool
{
    $definition = fetchIndexDefinition($database, $table, $index);
    if ($definition === []) {
        return false;
    }

    if (count($definition) !== 1 || $definition[0]['column_name'] !== $column || (string) $definition[0]['non_unique'] !== '0') {
        throw new RuntimeException("Migration 002 found an unexpected {$table}.{$index} definition.");
    }

    return true;
}

function hasExpectedSingletonGuard(PDO $database): bool
{
    $statement = $database->query(
        "SELECT generation_expression
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'personal_info' AND column_name = 'singleton_guard'"
    );
    $expression = $statement->fetchColumn();
    if ($expression === false) {
        return false;
    }

    if (trim((string) $expression) !== '1') {
        throw new RuntimeException('Migration 002 found an unexpected personal_info.singleton_guard definition.');
    }

    return true;
}

function verifyIntegrityConstraintPreconditions(PDO $database): void
{
    if ((int) $database->query('SELECT COUNT(*) FROM personal_info')->fetchColumn() > 1) {
        throw new MigrationPreconditionException('Migration 002 requires at most one personal_info row.');
    }

    $duplicate = $database->query(
        'SELECT skill_name
         FROM skills
         GROUP BY skill_name
         HAVING COUNT(*) > 1
         LIMIT 1'
    )->fetchColumn();
    if ($duplicate !== false) {
        throw new MigrationPreconditionException('Migration 002 requires unique skills under the current database collation.');
    }
}

function executeIntegrityConstraintsMigration(PDO $database): void
{
    verifyIntegrityConstraintPreconditions($database);

    if (!hasExpectedUniqueIndex($database, 'skills', 'uq_skills_skill_name', 'skill_name')) {
        $database->exec('CREATE UNIQUE INDEX uq_skills_skill_name ON skills (skill_name)');
    }

    $hasGuard = hasExpectedSingletonGuard($database);
    $hasSingletonIndex = hasExpectedUniqueIndex($database, 'personal_info', 'uq_personal_info_singleton_guard', 'singleton_guard');
    if (!$hasGuard && !$hasSingletonIndex) {
        // This is one atomic MySQL DDL operation: a failed uniqueness check leaves neither change applied.
        $database->exec(
            'ALTER TABLE personal_info
             ADD COLUMN singleton_guard TINYINT GENERATED ALWAYS AS (1) STORED,
             ADD UNIQUE INDEX uq_personal_info_singleton_guard (singleton_guard)'
        );
        return;
    }

    if (!$hasGuard || !$hasSingletonIndex) {
        throw new RuntimeException('Migration 002 found a partial personal_info singleton constraint. Manual inspection is required.');
    }
}

function executeOwnershipExpandMigration(PDO $database): void
{
    $database->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            oidc_issuer VARBINARY(2048) NOT NULL,
            oidc_subject VARBINARY(255) NOT NULL,
            account_status VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
            authz_version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT chk_users_account_status CHECK (account_status IN ('active', 'disabled')),
            CONSTRAINT chk_users_authz_version_positive CHECK (authz_version > 0),
            UNIQUE KEY uq_users_oidc_subject (oidc_subject)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    requireColumnDefinition($database, 'users', 'id', 'int unsigned', 'NO');
    requireColumnDefinition($database, 'users', 'oidc_issuer', 'varbinary(2048)', 'NO');
    requireColumnDefinition($database, 'users', 'oidc_subject', 'varbinary(255)', 'NO');
    $accountStatus = requireColumnDefinition($database, 'users', 'account_status', 'varchar(8)', 'NO');
    $authzVersion = requireColumnDefinition($database, 'users', 'authz_version', 'int unsigned', 'NO');
    if ((string) $accountStatus['default_value'] !== 'active' || (string) $authzVersion['default_value'] !== '1') {
        throw new RuntimeException('Migration 003 found incompatible users defaults.');
    }
    requireColumnDefinition($database, 'users', 'created_at', 'timestamp', 'NO');
    if (!verifyPrimaryIndex($database, 'users', 'id')) {
        throw new RuntimeException('Migration 003 found an invalid users primary key.');
    }
    ensureExpectedIndex($database, 'users', 'uq_users_oidc_subject', ['oidc_subject'], true);
    requireExpectedCheckConstraint($database, 'users', 'chk_users_account_status', ['account_status', 'active', 'disabled']);
    requireExpectedCheckConstraint($database, 'users', 'chk_users_authz_version_positive', ['authz_version', '> 0']);

    $database->exec(
        "CREATE TABLE IF NOT EXISTS portfolios (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            owner_user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_portfolios_owner_user_id (owner_user_id),
            CONSTRAINT fk_portfolios_owner_user
                FOREIGN KEY (owner_user_id) REFERENCES users (id)
                ON UPDATE RESTRICT ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    requireColumnDefinition($database, 'portfolios', 'id', 'int unsigned', 'NO');
    requireColumnDefinition($database, 'portfolios', 'owner_user_id', 'int unsigned', 'NO');
    requireColumnDefinition($database, 'portfolios', 'created_at', 'timestamp', 'NO');
    if (!verifyPrimaryIndex($database, 'portfolios', 'id')) {
        throw new RuntimeException('Migration 003 found an invalid portfolios primary key.');
    }
    ensureExpectedIndex($database, 'portfolios', 'uq_portfolios_owner_user_id', ['owner_user_id'], true);
    ensureExpectedForeignKey($database, 'portfolios', 'fk_portfolios_owner_user', 'owner_user_id', 'users', 'id');

    ensureNullableOwnershipColumn($database, 'personal_info', 'portfolio_id');
    ensureNullableOwnershipColumn($database, 'skills', 'portfolio_id');
    ensureNullableOwnershipColumn($database, 'projects', 'portfolio_id');
    ensureNullableOwnershipColumn($database, 'messages', 'recipient_portfolio_id');

    // These are the two legacy resource indexes not superseded by the scoped
    // unique constraints installed in Migration 004.
    ensureExpectedIndex($database, 'projects', 'idx_projects_portfolio_id', ['portfolio_id'], false);
    ensureExpectedIndex($database, 'messages', 'idx_messages_recipient_portfolio_id', ['recipient_portfolio_id'], false);
}

function ensureNullableOwnershipColumn(PDO $database, string $table, string $column): void
{
    $definition = fetchColumnDefinition($database, $table, $column);
    if ($definition === null) {
        $database->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` INT UNSIGNED NULL");
        $definition = fetchColumnDefinition($database, $table, $column);
    }
    if ($definition === null || $definition['type'] !== 'int unsigned' || $definition['nullable'] !== 'YES') {
        throw new RuntimeException("Migration 003 found an incompatible {$table}.{$column} definition.");
    }
}

function ensureOwnershipColumnNotNull(PDO $database, string $table, string $column): void
{
    $definition = fetchColumnDefinition($database, $table, $column);
    if ($definition === null || $definition['type'] !== 'int unsigned') {
        throw new RuntimeException("Migration 004 found an incompatible {$table}.{$column} definition.");
    }
    if ($definition['nullable'] === 'YES') {
        $database->exec("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` INT UNSIGNED NOT NULL");
        $definition = fetchColumnDefinition($database, $table, $column);
    }
    if ($definition === null || $definition['nullable'] !== 'NO') {
        throw new RuntimeException("Migration 004 could not require {$table}.{$column} ownership.");
    }
}

function testOnlyMigrationFailurePoint(string $point): void
{
    $configured = getenv('ATHERCAR_TEST_MIGRATION_FAIL_AFTER');
    if (!is_string($configured) || $configured === '') {
        return;
    }
    if (getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1') {
        throw new RuntimeException('Test-only migration failure injection is unavailable outside the explicit test environment.');
    }
    if ($configured === $point) {
        throw new RuntimeException("Test-only migration failure at {$point}.");
    }
}

function removeLegacyPersonalInfoSingletonConstraint(PDO $database): void
{
    $hasGuard = hasExpectedSingletonGuard($database);
    $hasIndex = hasExpectedUniqueIndex($database, 'personal_info', 'uq_personal_info_singleton_guard', 'singleton_guard');
    if ($hasGuard !== $hasIndex) {
        throw new RuntimeException('Migration 004 found a partial personal_info singleton constraint. Manual inspection is required.');
    }
    if ($hasGuard) {
        $database->exec(
            'ALTER TABLE personal_info
             DROP INDEX uq_personal_info_singleton_guard,
             DROP COLUMN singleton_guard'
        );
    }
}

function replaceGlobalSkillUniqueness(PDO $database): void
{
    $hasGlobal = hasExpectedUniqueIndex($database, 'skills', 'uq_skills_skill_name', 'skill_name');
    $hasScoped = hasExpectedIndex($database, 'skills', 'uq_skills_portfolio_skill_name', ['portfolio_id', 'skill_name'], true);
    if ($hasGlobal && $hasScoped) {
        throw new RuntimeException('Migration 004 found both global and scoped skill uniqueness constraints. Manual inspection is required.');
    }
    if ($hasGlobal) {
        $database->exec(
            'ALTER TABLE skills
             DROP INDEX uq_skills_skill_name,
             ADD UNIQUE INDEX uq_skills_portfolio_skill_name (portfolio_id, skill_name)'
        );
        return;
    }
    if (!$hasScoped) {
        throw new RuntimeException('Migration 004 found no recognized skill uniqueness constraint. Manual inspection is required.');
    }
}

function executeOwnershipContractMigration(PDO $database): void
{
    OwnershipBackfill::assertReadyForContract($database);

    ensureOwnershipColumnNotNull($database, 'personal_info', 'portfolio_id');
    testOnlyMigrationFailurePoint('after-first-contract-column');
    ensureOwnershipColumnNotNull($database, 'skills', 'portfolio_id');
    ensureOwnershipColumnNotNull($database, 'projects', 'portfolio_id');
    ensureOwnershipColumnNotNull($database, 'messages', 'recipient_portfolio_id');

    removeLegacyPersonalInfoSingletonConstraint($database);
    ensureExpectedIndex($database, 'personal_info', 'uq_personal_info_portfolio_id', ['portfolio_id'], true);
    replaceGlobalSkillUniqueness($database);

    ensureExpectedForeignKey($database, 'personal_info', 'fk_personal_info_portfolio', 'portfolio_id', 'portfolios', 'id');
    ensureExpectedForeignKey($database, 'skills', 'fk_skills_portfolio', 'portfolio_id', 'portfolios', 'id');
    ensureExpectedForeignKey($database, 'projects', 'fk_projects_portfolio', 'portfolio_id', 'portfolios', 'id');
    ensureExpectedForeignKey($database, 'messages', 'fk_messages_recipient_portfolio', 'recipient_portfolio_id', 'portfolios', 'id');
}

function executeSqlMigration(PDO $database, array $migration): void
{
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

runMigrations();
