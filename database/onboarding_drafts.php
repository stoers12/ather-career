<?php

declare(strict_types=1);

function onboardingDraftSchemaColumns(): array
{
    return [
        'user_id' => ['type' => 'int unsigned', 'nullable' => 'NO'],
        'version' => ['type' => 'int unsigned', 'nullable' => 'NO'],
        'payload' => ['type' => 'varbinary(16384)', 'nullable' => 'NO'],
        'last_section' => ['type' => 'varbinary(24)', 'nullable' => 'NO'],
        'last_operation_id' => ['type' => 'varbinary(43)', 'nullable' => 'NO'],
        'last_operation_digest' => ['type' => 'binary(32)', 'nullable' => 'NO'],
        'created_at' => ['type' => 'datetime(6)', 'nullable' => 'NO'],
        'updated_at' => ['type' => 'datetime(6)', 'nullable' => 'NO'],
    ];
}

function onboardingDraftSchemaChecks(): array
{
    return [
        'chk_onboarding_drafts_version' => '((version > 0) and (version <= 2147483647))',
        'chk_onboarding_drafts_section' => "(last_section in ('public_identity', 'professional_snapshot', 'profile_identity'))",
        'chk_onboarding_drafts_payload' => "(json_valid(convert(payload using utf8mb4)) and (json_type(convert(payload using utf8mb4)) = 'OBJECT'))",
    ];
}

function verifyOnboardingDraftSchema(PDO $database): void
{
    verifyExpectedColumns($database, 'onboarding_drafts', onboardingDraftSchemaColumns());
    if (!hasExpectedIndex($database, 'onboarding_drafts', 'PRIMARY', ['user_id'], true)
        || !hasExpectedForeignKey($database, 'onboarding_drafts', 'fk_onboarding_drafts_user', 'user_id', 'users', 'id')) {
        throw new RuntimeException('Incompatible draft ownership constraints.');
    }
    $engine = $database->query("SELECT engine FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='onboarding_drafts'")->fetchColumn();
    if ($engine !== 'InnoDB') { throw new RuntimeException('Draft store requires InnoDB.'); }
    foreach (fetchColumns($database, 'onboarding_drafts') as $column) {
        if ($column['default_value'] !== null || $column['extra'] !== '') { throw new RuntimeException('Incompatible draft column attributes.'); }
    }
    $indexes = $database->query("SELECT index_name AS index_name, sub_part AS sub_part, collation AS collation, index_type AS index_type FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='onboarding_drafts'")->fetchAll(PDO::FETCH_ASSOC);
    if (count($indexes) !== 1 || $indexes[0] !== ['index_name' => 'PRIMARY', 'sub_part' => null, 'collation' => 'A', 'index_type' => 'BTREE']) {
        throw new RuntimeException('Incompatible draft indexes.');
    }
    $statement = $database->query("SELECT tc.constraint_name AS constraint_name, cc.check_clause AS check_clause, tc.enforced AS enforced FROM information_schema.table_constraints tc JOIN information_schema.check_constraints cc ON cc.constraint_schema=tc.constraint_schema AND cc.constraint_name=tc.constraint_name WHERE tc.table_schema=DATABASE() AND tc.table_name='onboarding_drafts'");
    $checks = $statement->fetchAll(PDO::FETCH_ASSOC);
    $expected = onboardingDraftSchemaChecks();
    // Match MySQL's canonical expression, ignoring only identifier quoting,
    // whitespace and metadata literal escaping/charset introducers. Keep all
    // parentheses and literal case/content so altered semantics cannot pass.
    $normalize = static function (string $clause): string {
        $clause = preg_replace("/\\\\+(?=')/", '', $clause);
        $clause = preg_replace("/(?<![A-Za-z0-9'])_(?:utf8mb4|utf8mb3|ascii|binary)(?=')/", '', $clause);
        // Preserve whitespace and backticks inside string literals.
        $parts = preg_split("/('(?:''|[^'])*')/", $clause, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) { $parts[$index] = preg_replace('/[\s`]/', '', $part); }
        }
        return implode('', $parts);
    };
    if (count($checks) !== count($expected)) { throw new RuntimeException('Incompatible draft checks.'); }
    foreach ($checks as $check) {
        $clause = $expected[$check['constraint_name']] ?? null;
        if ($clause === null || $check['enforced'] !== 'YES' || $normalize($clause) !== $normalize($check['check_clause'])) {
            throw new RuntimeException('Incompatible draft check expression.');
        }
    }
    $constraints = $database->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='onboarding_drafts'")->fetchColumn();
    $triggers = $database->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table='onboarding_drafts'")->fetchColumn();
    if ((int) $constraints !== 5 || (int) $triggers !== 0) { throw new RuntimeException('Unexpected draft constraints or triggers.'); }
}

function executeOnboardingDraftMigration(PDO $database): void
{
    $definitions = [];
    foreach (onboardingDraftSchemaColumns() as $name => $column) { $definitions[] = "$name {$column['type']} NOT NULL"; }
    $definitions[] = 'PRIMARY KEY (user_id)';
    $definitions[] = 'CONSTRAINT fk_onboarding_drafts_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT';
    foreach (onboardingDraftSchemaChecks() as $name => $clause) { $definitions[] = "CONSTRAINT $name CHECK ($clause)"; }
    $database->exec('CREATE TABLE IF NOT EXISTS onboarding_drafts (' . implode(', ', $definitions) . ') ENGINE=InnoDB');
    verifyOnboardingDraftSchema($database);
    if ((int) $database->query('SELECT COUNT(*) FROM onboarding_drafts')->fetchColumn() !== 0) {
        throw new RuntimeException('Unrecorded migration 016 requires an empty draft table; inspect manually.');
    }
    testOnlyMigrationFailurePoint('after-draft-table-create');
}
