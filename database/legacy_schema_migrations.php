<?php

declare(strict_types=1);

// PHP-guarded schema steps for migrations 002-005, 012 and 013; dispatched by executeSqlMigration().
// Loaded by migrate.php.

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

function executePublicLifecycleMigration(PDO $database): void
{
    $slug = fetchColumnDefinition($database, 'portfolios', 'public_slug');
    if ($slug === null) {
        $database->exec(
            'ALTER TABLE portfolios
             ADD COLUMN public_slug VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL'
        );
    }
    requireColumnDefinition($database, 'portfolios', 'public_slug', 'varchar(64)', 'YES');

    $published = fetchColumnDefinition($database, 'portfolios', 'is_published');
    if ($published === null) {
        $database->exec(
            'ALTER TABLE portfolios
             ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0'
        );
    }
    $published = requireColumnDefinition($database, 'portfolios', 'is_published', 'tinyint(1)', 'NO');
    if ((string) $published['default_value'] !== '0') {
        throw new RuntimeException('Migration 005 found an incompatible portfolios.is_published default.');
    }

    $publishedAt = fetchColumnDefinition($database, 'portfolios', 'published_at');
    if ($publishedAt === null) {
        $database->exec(
            'ALTER TABLE portfolios
             ADD COLUMN published_at TIMESTAMP NULL DEFAULT NULL'
        );
    }
    requireColumnDefinition($database, 'portfolios', 'published_at', 'timestamp', 'YES');

    ensureExpectedIndex($database, 'portfolios', 'uq_portfolios_public_slug', ['public_slug'], true);
}

function executeProjectUpdatedAtMigration(PDO $database): void
{
    $definition = fetchColumnDefinition($database, 'projects', 'updated_at');
    if ($definition === null) {
        $database->exec('ALTER TABLE projects ADD COLUMN updated_at TIMESTAMP(6) NULL DEFAULT NULL');
        $definition = requireColumnDefinition($database, 'projects', 'updated_at', 'timestamp(6)', 'YES');
    }
    if ($definition['type'] !== 'timestamp(6)' || !in_array($definition['nullable'], ['YES', 'NO'], true)
        || str_contains((string) $definition['extra'], 'on update')) {
        throw new RuntimeException('Migration 012 found an incompatible projects.updated_at definition.');
    }

    // A retry after interrupted DDL preserves every already populated timestamp.
    $database->exec('UPDATE projects SET updated_at = created_at WHERE updated_at IS NULL');
    if ($definition['nullable'] === 'YES') {
        $database->exec('ALTER TABLE projects MODIFY COLUMN updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)');
    }
    requireColumnDefinition($database, 'projects', 'updated_at', 'timestamp(6)', 'NO');
}

function executeOptionalProjectGithubUrlMigration(PDO $database): void
{
    $definition = fetchColumnDefinition($database, 'projects', 'github_url');
    if ($definition === null || $definition['type'] !== 'varchar(500)'
        || !in_array($definition['nullable'], ['YES', 'NO'], true)) {
        throw new RuntimeException('Migration 013 found an incompatible projects.github_url definition.');
    }
    if ($definition['nullable'] === 'NO') {
        $database->exec('ALTER TABLE projects MODIFY COLUMN github_url VARCHAR(500) NULL DEFAULT NULL');
    }
    requireColumnDefinition($database, 'projects', 'github_url', 'varchar(500)', 'YES');
}
