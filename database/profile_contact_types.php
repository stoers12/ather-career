<?php
declare(strict_types=1);

/** Both columns/checks are added by one atomic ALTER; partial shapes fail closed. */
function verifyProfileContactTypesSchema(PDO $database, bool $staging = false): void
{
    foreach (['public_email_visible', 'public_phone_visible'] as $field) {
        $column = requireColumnDefinition($database, 'personal_info', $field, 'tinyint(1)', $staging ? 'YES' : 'NO');
        if (($staging ? $column['default_value'] !== null : (string) $column['default_value'] !== '0') || $column['extra'] !== '') {
            throw new RuntimeException('Incompatible contact visibility attributes.');
        }
        $query = $database->prepare("SELECT cc.check_clause AS check_clause, tc.enforced AS enforced FROM information_schema.table_constraints tc JOIN information_schema.check_constraints cc ON cc.constraint_schema=tc.constraint_schema AND cc.constraint_name=tc.constraint_name WHERE tc.table_schema=DATABASE() AND tc.table_name='personal_info' AND tc.constraint_name=?");
        $query->execute(['chk_profile_' . $field]); $check = $query->fetch(PDO::FETCH_ASSOC);
        $clause = is_array($check) ? preg_replace('/[\\s`]/', '', $check['check_clause']) : '';
        if (!is_array($check) || $check['enforced'] !== 'YES' || $clause !== '(' . $field . 'in(0,1))') {
            throw new RuntimeException('Incompatible contact visibility check.');
        }
    }
}

function executeProfileContactTypesMigration(PDO $database): void
{
    $legacy = requireColumnDefinition($database, 'personal_info', 'public_contact_visible', 'tinyint(1)', 'NO');
    if ((string) $legacy['default_value'] !== '0' || $legacy['extra'] !== '') {
        throw new RuntimeException('Incompatible legacy contact visibility source.');
    }
    $email = fetchColumnDefinition($database, 'personal_info', 'public_email_visible');
    $phone = fetchColumnDefinition($database, 'personal_info', 'public_phone_visible');
    if ($email === null && $phone === null) {
        $database->exec('ALTER TABLE personal_info
            ADD COLUMN public_email_visible TINYINT(1) NULL DEFAULT NULL,
            ADD COLUMN public_phone_visible TINYINT(1) NULL DEFAULT NULL,
            ADD CONSTRAINT chk_profile_public_email_visible CHECK (public_email_visible IN (0,1)),
            ADD CONSTRAINT chk_profile_public_phone_visible CHECK (public_phone_visible IN (0,1))');
        $email = fetchColumnDefinition($database, 'personal_info', 'public_email_visible');
        $phone = fetchColumnDefinition($database, 'personal_info', 'public_phone_visible');
    }
    if ($email === null || $phone === null || $email['nullable'] !== $phone['nullable']) {
        throw new RuntimeException('Partial contact visibility migration requires inspection.');
    }
    if ($email['nullable'] === 'NO') {
        // The final ALTER committed but its ledger insert may not have. No backfill.
        verifyProfileContactTypesSchema($database);
        return;
    }
    verifyProfileContactTypesSchema($database, true);
    testOnlyMigrationFailurePoint('after-contact-types-add');
    if ((int) $database->query('SELECT COUNT(*) FROM personal_info WHERE
        (public_email_visible IS NULL) <> (public_phone_visible IS NULL)
        OR public_contact_visible IS NULL OR public_contact_visible NOT IN (0,1)')->fetchColumn() !== 0) {
        throw new RuntimeException('Ambiguous contact visibility migration data requires inspection.');
    }
    // NULL pairs alone have never received independent choices. Atomic UPDATE,
    // explicit timestamp preservation; initialized pairs are never overwritten.
    $database->exec('UPDATE personal_info SET public_email_visible=public_contact_visible,
        public_phone_visible=public_contact_visible, updated_at=updated_at
        WHERE public_email_visible IS NULL AND public_phone_visible IS NULL');
    testOnlyMigrationFailurePoint('after-contact-types-backfill');
    $database->exec('ALTER TABLE personal_info
        MODIFY COLUMN public_email_visible TINYINT(1) NOT NULL DEFAULT 0,
        MODIFY COLUMN public_phone_visible TINYINT(1) NOT NULL DEFAULT 0');
    verifyProfileContactTypesSchema($database);
    testOnlyMigrationFailurePoint('after-contact-types-finalize');
}
