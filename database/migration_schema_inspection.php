<?php

declare(strict_types=1);

// Schema inspection and idempotent ensure helpers shared by every migration step.
// Loaded by migrate.php.

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
