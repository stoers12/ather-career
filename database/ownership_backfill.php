<?php

declare(strict_types=1);

final class OwnershipBackfillException extends RuntimeException
{
}

/**
 * Controlled data migration for the single preserved V1 portfolio.
 *
 * The caller supplies the already verified OIDC issuer and subject. This
 * helper deliberately has no email, name, or "first row" fallback: V1 data
 * may be mapped only to that one explicit durable identity.
 */
final class OwnershipBackfill
{
    /** @var array<string, string> */
    private const RESOURCE_OWNERSHIP_COLUMNS = [
        'personal_info' => 'portfolio_id',
        'skills' => 'portfolio_id',
        'projects' => 'portfolio_id',
        'messages' => 'recipient_portfolio_id',
    ];

    /** @return array{user_id: int, portfolio_id: int, updated: array<string, int>} */
    public static function execute(PDO $database, string $issuer, string $subject, ?string $testFailurePoint = null): array
    {
        self::validateIdentity($issuer, $subject);
        self::assertExpandedState($database);

        if (!in_array($testFailurePoint, [null, 'after-user-create', 'during-resource-backfill'], true)) {
            throw new InvalidArgumentException('Unknown ownership backfill failure point.');
        }

        $database->beginTransaction();
        try {
            $userId = self::resolveOrCreatePreservedUser($database, $issuer, $subject);
            if ($testFailurePoint === 'after-user-create') {
                throw new RuntimeException('Test-only ownership backfill failure after preserved User creation.');
            }

            $portfolioId = self::resolveOrCreatePreservedPortfolio($database, $userId);
            self::assertResourceMappingsAreCompatible($database, $portfolioId);

            $updated = [];
            $failureInjected = false;
            foreach (self::RESOURCE_OWNERSHIP_COLUMNS as $table => $column) {
                $assignment = "`{$column}` = :portfolio_id";
                // V1 personal_info.updated_at has ON UPDATE CURRENT_TIMESTAMP.
                // Backfilling ownership is metadata preservation, not a profile
                // edit, so retain the original V1 timestamp explicitly.
                if ($table === 'personal_info') {
                    $assignment .= ', updated_at = updated_at';
                }
                $statement = $database->prepare("UPDATE `{$table}` SET {$assignment} WHERE `{$column}` IS NULL");
                $statement->execute(['portfolio_id' => $portfolioId]);
                $updated[$table] = $statement->rowCount();

                if ($testFailurePoint === 'during-resource-backfill' && !$failureInjected) {
                    $failureInjected = true;
                    throw new RuntimeException('Test-only ownership backfill failure during resource mapping.');
                }
            }

            self::assertResourceMappingsAreComplete($database, $portfolioId);
            $database->commit();

            return [
                'user_id' => $userId,
                'portfolio_id' => $portfolioId,
                'updated' => $updated,
            ];
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public static function assertReadyForContract(PDO $database): void
    {
        self::assertOwnershipSchema($database, false);

        foreach (self::RESOURCE_OWNERSHIP_COLUMNS as $table => $column) {
            $missing = (int) $database->query("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` IS NULL")->fetchColumn();
            if ($missing !== 0) {
                throw new OwnershipBackfillException("Ownership contract requires every {$table}.{$column} value to be backfilled.");
            }

            $orphan = (int) $database->query(
                "SELECT COUNT(*)
                 FROM `{$table}` AS resource
                 LEFT JOIN portfolios AS portfolio ON portfolio.id = resource.`{$column}`
                 WHERE portfolio.id IS NULL"
            )->fetchColumn();
            if ($orphan !== 0) {
                throw new OwnershipBackfillException("Ownership contract found an invalid {$table}.{$column} mapping.");
            }
        }

        $duplicateProfile = $database->query(
            'SELECT portfolio_id
             FROM personal_info
             GROUP BY portfolio_id
             HAVING COUNT(*) > 1
             LIMIT 1'
        )->fetchColumn();
        if ($duplicateProfile !== false) {
            throw new OwnershipBackfillException('Ownership contract found duplicate personal_info portfolio ownership.');
        }

        $duplicateSkill = $database->query(
            'SELECT portfolio_id, skill_name
             FROM skills
             GROUP BY portfolio_id, skill_name
             HAVING COUNT(*) > 1
             LIMIT 1'
        )->fetch();
        if ($duplicateSkill !== false) {
            throw new OwnershipBackfillException('Ownership contract found duplicate skills within one Portfolio.');
        }
    }

    public static function assertExpandedState(PDO $database): void
    {
        self::assertOwnershipSchema($database, true);
    }

    private static function assertOwnershipSchema(PDO $database, bool $requireNullableResourceOwnership): void
    {
        $requiredMigrations = [
            '001' => 'baseline',
            '002' => 'integrity_constraints',
            '003' => 'ownership_expand',
        ];
        $applied = $database->query('SELECT version, name FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($requiredMigrations as $version => $name) {
            if (($applied[$version] ?? null) !== $name) {
                throw new OwnershipBackfillException('Ownership backfill requires the complete Ownership Expand schema.');
            }
        }
        if (isset($applied['004'])) {
            throw new OwnershipBackfillException('Ownership backfill must complete before the Ownership Contract migration.');
        }

        self::assertColumn($database, 'users', 'oidc_issuer', 'varbinary(2048)', 'NO');
        self::assertColumn($database, 'users', 'oidc_subject', 'varbinary(255)', 'NO');
        self::assertColumn($database, 'users', 'authz_version', 'int unsigned', 'NO');
        self::assertColumn($database, 'portfolios', 'owner_user_id', 'int unsigned', 'NO');
        self::assertUniqueIndex($database, 'users', 'uq_users_oidc_subject', ['oidc_subject']);
        self::assertUniqueIndex($database, 'portfolios', 'uq_portfolios_owner_user_id', ['owner_user_id']);
        self::assertForeignKey($database, 'portfolios', 'fk_portfolios_owner_user', 'owner_user_id', 'users', 'id');
        self::assertCheckConstraint($database, 'users', 'chk_users_account_status', ['account_status', 'active', 'disabled']);
        self::assertCheckConstraint($database, 'users', 'chk_users_authz_version_positive', ['authz_version', '> 0']);
        foreach (self::RESOURCE_OWNERSHIP_COLUMNS as $table => $column) {
            self::assertColumn($database, $table, $column, 'int unsigned', $requireNullableResourceOwnership ? 'YES' : null);
        }
    }

    private static function validateIdentity(string $issuer, string $subject): void
    {
        if ($issuer === '' || strlen($issuer) > 2048 || $subject === '' || strlen($subject) > 255) {
            throw new InvalidArgumentException('Preserved OIDC issuer or subject is invalid.');
        }
    }

    private static function resolveOrCreatePreservedUser(PDO $database, string $issuer, string $subject): int
    {
        $matchingStatement = $database->prepare(
            'SELECT id, oidc_issuer
             FROM users
             WHERE oidc_subject = :subject'
        );
        $matchingStatement->execute(['subject' => $subject]);
        $matching = $matchingStatement->fetchAll(PDO::FETCH_ASSOC);

        $userCount = (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($matching === []) {
            if ($userCount !== 0) {
                throw new OwnershipBackfillException('Ownership backfill found an unexpected existing internal User.');
            }

            $insert = $database->prepare(
                "INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
                 VALUES (:issuer, :subject, 'active', 1)"
            );
            $insert->execute(['issuer' => $issuer, 'subject' => $subject]);

            return (int) $database->lastInsertId();
        }

        if (count($matching) !== 1 || !hash_equals((string) $matching[0]['oidc_issuer'], $issuer)) {
            throw new OwnershipBackfillException('Ownership backfill found an incompatible preserved identity binding.');
        }
        if ($userCount !== 1) {
            throw new OwnershipBackfillException('Ownership backfill found ambiguous internal User state.');
        }

        return (int) $matching[0]['id'];
    }

    private static function resolveOrCreatePreservedPortfolio(PDO $database, int $userId): int
    {
        $existing = $database->prepare('SELECT id FROM portfolios WHERE owner_user_id = :user_id');
        $existing->execute(['user_id' => $userId]);
        $portfolio = $existing->fetchAll(PDO::FETCH_COLUMN);

        $portfolioCount = (int) $database->query('SELECT COUNT(*) FROM portfolios')->fetchColumn();
        if ($portfolio === []) {
            if ($portfolioCount !== 0) {
                throw new OwnershipBackfillException('Ownership backfill found an unexpected existing Portfolio.');
            }

            $insert = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:user_id)');
            $insert->execute(['user_id' => $userId]);

            return (int) $database->lastInsertId();
        }

        if (count($portfolio) !== 1 || $portfolioCount !== 1) {
            throw new OwnershipBackfillException('Ownership backfill found ambiguous Portfolio ownership.');
        }

        return (int) $portfolio[0];
    }

    private static function assertResourceMappingsAreCompatible(PDO $database, int $portfolioId): void
    {
        foreach (self::RESOURCE_OWNERSHIP_COLUMNS as $table => $column) {
            $statement = $database->prepare(
                "SELECT COUNT(*)
                 FROM `{$table}`
                 WHERE `{$column}` IS NOT NULL AND `{$column}` <> :portfolio_id"
            );
            $statement->execute(['portfolio_id' => $portfolioId]);
            if ((int) $statement->fetchColumn() !== 0) {
                throw new OwnershipBackfillException("Ownership backfill found incompatible existing {$table}.{$column} data.");
            }
        }
    }

    private static function assertResourceMappingsAreComplete(PDO $database, int $portfolioId): void
    {
        foreach (self::RESOURCE_OWNERSHIP_COLUMNS as $table => $column) {
            $statement = $database->prepare(
                "SELECT COUNT(*)
                 FROM `{$table}`
                 WHERE `{$column}` IS NULL OR `{$column}` <> :portfolio_id"
            );
            $statement->execute(['portfolio_id' => $portfolioId]);
            if ((int) $statement->fetchColumn() !== 0) {
                throw new OwnershipBackfillException("Ownership backfill could not verify {$table}.{$column} ownership.");
            }
        }
    }

    private static function assertColumn(PDO $database, string $table, string $column, string $type, ?string $nullable): void
    {
        $statement = $database->prepare(
            'SELECT LOWER(column_type), is_nullable
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        $actual = $statement->fetch(PDO::FETCH_NUM);
        if ($actual === false || $actual[0] !== $type || ($nullable !== null && $actual[1] !== $nullable)) {
            throw new OwnershipBackfillException("Ownership backfill found an incompatible {$table}.{$column} schema.");
        }
    }

    private static function assertUniqueIndex(PDO $database, string $table, string $index, array $columns): void
    {
        $statement = $database->prepare(
            'SELECT column_name AS local_column, non_unique AS non_unique
             FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index
             ORDER BY seq_in_index'
        );
        $statement->execute(['table' => $table, 'index' => $index]);
        $definition = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($definition === []
            || array_column($definition, 'local_column') !== $columns
            || (string) $definition[0]['non_unique'] !== '0') {
            throw new OwnershipBackfillException("Ownership backfill found an incompatible {$table}.{$index} index.");
        }
    }

    private static function assertForeignKey(PDO $database, string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn): void
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
        $actual = $statement->fetch(PDO::FETCH_ASSOC);
        if ($actual === false
            || $actual['local_column'] !== $column
            || $actual['referenced_table'] !== $referencedTable
            || $actual['referenced_column'] !== $referencedColumn
            || strtoupper((string) $actual['update_rule']) !== 'RESTRICT'
            || strtoupper((string) $actual['delete_rule']) !== 'RESTRICT') {
            throw new OwnershipBackfillException("Ownership backfill found an incompatible {$table}.{$constraint} foreign key.");
        }
    }

    private static function assertCheckConstraint(PDO $database, string $table, string $constraint, array $requiredFragments): void
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
            throw new OwnershipBackfillException("Ownership backfill found a missing {$table}.{$constraint} check constraint.");
        }
        $normalized = strtolower($clause);
        foreach ($requiredFragments as $fragment) {
            if (!str_contains($normalized, strtolower($fragment))) {
                throw new OwnershipBackfillException("Ownership backfill found an incompatible {$table}.{$constraint} check constraint.");
            }
        }
    }
}
