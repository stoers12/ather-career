<?php

declare(strict_types=1);

function onboardingAttestationSchemaColumns(): array
{
    $columns = ['user_id' => ['type' => 'int unsigned', 'nullable' => 'NO']];
    foreach (['age', 'terms', 'privacy'] as $kind) {
        foreach (['identifier' => 64, 'version' => 64, 'digest' => 64, 'language' => 35] as $field => $length) {
            $columns[$kind . '_' . $field] = ['type' => "varbinary($length)", 'nullable' => 'NO'];
        }
    }
    $columns['recorded_at'] = ['type' => 'datetime(6)', 'nullable' => 'NO'];
    return $columns;
}

function verifyOnboardingAttestationSchema(PDO $database): void
{
    $columns = onboardingAttestationSchemaColumns();
    verifyExpectedColumns($database, 'onboarding_attestations', $columns);
    $key = array_keys($columns);
    array_pop($key);
    if (!hasExpectedIndex($database, 'onboarding_attestations', 'PRIMARY', $key, true)
        || !hasExpectedForeignKey($database, 'onboarding_attestations', 'fk_onboarding_attestations_user', 'user_id', 'users', 'id')) {
        throw new RuntimeException('Incompatible onboarding attestation constraints.');
    }
    $table = $database->query("SELECT engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'onboarding_attestations'")->fetchColumn();
    if ($table !== 'InnoDB') { throw new RuntimeException('Attestations require InnoDB.'); }
    // Reject defaults, generated columns, extra indexes/constraints or triggers:
    // an interrupted whole CREATE is recoverable; an altered shape needs review.
    foreach (fetchColumns($database, 'onboarding_attestations') as $column) {
        if ($column['default_value'] !== null || $column['extra'] !== '') {
            throw new RuntimeException('Incompatible attestation column attributes.');
        }
    }
    $indexes = $database->query("SELECT DISTINCT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'onboarding_attestations'")->fetchAll(PDO::FETCH_COLUMN);
    if ($indexes !== ['PRIMARY']) { throw new RuntimeException('Unexpected attestation indexes.'); }
    $invalidKeyParts = $database->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'onboarding_attestations' AND (sub_part IS NOT NULL OR collation <> 'A' OR index_type <> 'BTREE')")->fetchColumn();
    if ((int) $invalidKeyParts !== 0) { throw new RuntimeException('Incompatible attestation key attributes.'); }
    $constraints = $database->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'onboarding_attestations'")->fetchColumn();
    $triggers = $database->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND event_object_table = 'onboarding_attestations'")->fetchColumn();
    if ((int) $constraints !== 2 || (int) $triggers !== 0) { throw new RuntimeException('Unexpected attestation constraints or triggers.'); }
}

function executeOnboardingAttestationMigration(PDO $database): void
{
    $definitions = [];
    $columns = onboardingAttestationSchemaColumns();
    foreach ($columns as $name => $column) { $definitions[] = "$name {$column['type']} NOT NULL"; }
    $key = array_keys($columns);
    array_pop($key);
    $definitions[] = 'PRIMARY KEY (' . implode(', ', $key) . ')';
    $definitions[] = 'CONSTRAINT fk_onboarding_attestations_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT';
    $database->exec('CREATE TABLE IF NOT EXISTS onboarding_attestations (' . implode(', ', $definitions) . ') ENGINE=InnoDB');
    verifyOnboardingAttestationSchema($database);
    if ((int) $database->query('SELECT COUNT(*) FROM onboarding_attestations')->fetchColumn() !== 0) {
        throw new RuntimeException('Unrecorded migration 015 requires an empty evidence table; inspect existing rows manually.');
    }
    testOnlyMigrationFailurePoint('after-attestation-table-create');
}
