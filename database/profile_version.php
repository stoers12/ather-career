<?php

declare(strict_types=1);

function verifyProfileVersionSchema(PDO $database): void
{
    $column = requireColumnDefinition($database, 'personal_info', 'profile_version', 'int unsigned', 'NO');
    if ((string) $column['default_value'] !== '1' || $column['extra'] !== '') {
        throw new RuntimeException('Incompatible profile version attributes.');
    }
    $check = $database->query("SELECT cc.check_clause AS check_clause, tc.enforced AS enforced FROM information_schema.table_constraints tc JOIN information_schema.check_constraints cc ON cc.constraint_schema=tc.constraint_schema AND cc.constraint_name=tc.constraint_name WHERE tc.table_schema=DATABASE() AND tc.table_name='personal_info' AND tc.constraint_name='chk_personal_info_profile_version'")->fetch(PDO::FETCH_ASSOC);
    $clause = is_array($check) ? preg_replace('/[\s`]/', '', $check['check_clause']) : '';
    if (!is_array($check) || $check['enforced'] !== 'YES' || $clause !== '((profile_version>0)and(profile_version<=2147483647))') {
        throw new RuntimeException('Incompatible profile version check.');
    }
}

function executeProfileVersionMigration(PDO $database): void
{
    if (fetchColumnDefinition($database, 'personal_info', 'profile_version') === null) {
        $database->exec('ALTER TABLE personal_info ADD COLUMN profile_version INT UNSIGNED NOT NULL DEFAULT 1,
            ADD CONSTRAINT chk_personal_info_profile_version CHECK (profile_version > 0 AND profile_version <= 2147483647)');
    }
    verifyProfileVersionSchema($database);
    testOnlyMigrationFailurePoint('after-profile-version-alter');
}
