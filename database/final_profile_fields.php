<?php
declare(strict_types=1);

function finalProfileSchemaColumns(): array
{
    return [
        'country_code' => ['char(2)', 'ascii', 'ascii_bin'],
        'preferred_language' => ['char(2)', 'ascii', 'ascii_bin'],
        'professional_status' => ['varchar(32)', 'ascii', 'ascii_bin'],
        'specialization' => ['varchar(150)', 'utf8mb4', 'utf8mb4_0900_ai_ci'],
    ];
}

function verifyFinalProfileSchema(PDO $database): void
{
    foreach (finalProfileSchemaColumns() as $name => [$type, $charset, $collation]) {
        $column = requireColumnDefinition($database, 'personal_info', $name, $type, 'YES');
        $query = $database->prepare('SELECT character_set_name, collation_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'personal_info\' AND column_name=?');
        $query->execute([$name]); $encoding = $query->fetch(PDO::FETCH_NUM);
        if ($column['default_value'] !== null || $column['extra'] !== '' || $encoding !== [$charset, $collation]) {
            throw new RuntimeException('Incompatible final profile field attributes.');
        }
    }
}

function executeFinalProfileMigration(PDO $database): void
{
    $present = array_filter(array_keys(finalProfileSchemaColumns()), static fn (string $name): bool => fetchColumnDefinition($database, 'personal_info', $name) !== null);
    if ($present === []) {
        $database->exec('ALTER TABLE personal_info
            ADD COLUMN country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL AFTER public_contact_visible,
            ADD COLUMN preferred_language CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL AFTER country_code,
            ADD COLUMN professional_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL AFTER preferred_language,
            ADD COLUMN specialization VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL AFTER professional_status');
    }
    // A whole committed ALTER is recoverable; a partial/changed shape stops.
    verifyFinalProfileSchema($database);
    testOnlyMigrationFailurePoint('after-final-profile-alter');
}
