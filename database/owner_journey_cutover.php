<?php
declare(strict_types=1);

const OWNER_JOURNEY_CUTOVER_REVISION = 'owner-journey-cutover-v1';

function verifyOwnerJourneyCutoverSchema(PDO $database): void
{
    requireColumnDefinition($database, 'portfolios', 'legacy_owner_user_id', 'int unsigned', 'YES');
    $definition = fetchColumnDefinition($database, 'portfolios', 'legacy_owner_user_id');
    if ($definition['default_value'] !== null || $definition['extra'] !== '') { throw new RuntimeException('Incompatible legacy owner column.'); }
    verifyExpectedColumns($database, 'owner_journey_cutover', [
        'id'=>['type'=>'tinyint unsigned','nullable'=>'NO'],
        'revision'=>['type'=>'varbinary(64)','nullable'=>'NO'],
        'snapshot_count'=>['type'=>'int unsigned','nullable'=>'NO'],
        'snapshot_digest'=>['type'=>'binary(32)','nullable'=>'NO'],
        'captured_at'=>['type'=>'datetime(6)','nullable'=>'NO'],
    ]);
    if ($database->query("SELECT engine FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='owner_journey_cutover'")->fetchColumn() !== 'InnoDB') { throw new RuntimeException('Cutover requires InnoDB.'); }
    $indexes=$database->query("SELECT index_name AS index_name,column_name AS column_name,non_unique AS non_unique FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='owner_journey_cutover' ORDER BY index_name,seq_in_index")->fetchAll(PDO::FETCH_ASSOC);
    if (count($indexes)!==1 || $indexes[0]['index_name']!=='PRIMARY' || $indexes[0]['column_name']!=='id' || (int)$indexes[0]['non_unique']!==0) { throw new RuntimeException('Incompatible cutover index.'); }
    $checks=$database->query("SELECT cc.check_clause AS check_clause,tc.enforced AS enforced FROM information_schema.table_constraints tc JOIN information_schema.check_constraints cc ON tc.constraint_schema=cc.constraint_schema AND tc.constraint_name=cc.constraint_name WHERE tc.table_schema=DATABASE() AND tc.table_name='owner_journey_cutover'")->fetchAll(PDO::FETCH_ASSOC);
    if (count($checks)!==1 || $checks[0]['enforced']!=='YES' || preg_replace('/[\s`]/','',$checks[0]['check_clause'])!=='(id=1)') { throw new RuntimeException('Incompatible cutover singleton.'); }
    if ((int)$database->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='owner_journey_cutover'")->fetchColumn()!==2) { throw new RuntimeException('Unexpected cutover constraints.'); }
    foreach (fetchColumns($database,'owner_journey_cutover') as $column) { if ($column['default_value']!==null || $column['extra']!=='') { throw new RuntimeException('Incompatible cutover column attributes.'); } }
    if ((int)$database->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table IN ('owner_journey_cutover','portfolios')")->fetchColumn()!==0) { throw new RuntimeException('Unexpected cutover trigger.'); }
}

function executeOwnerJourneyCutoverMigration(PDO $database): void
{
    if (fetchColumnDefinition($database,'portfolios','legacy_owner_user_id')===null) { $database->exec('ALTER TABLE portfolios ADD COLUMN legacy_owner_user_id INT UNSIGNED NULL DEFAULT NULL'); }
    testOnlyMigrationFailurePoint('after-legacy-owner-column');
    $database->exec('CREATE TABLE IF NOT EXISTS owner_journey_cutover (id TINYINT UNSIGNED NOT NULL, revision VARBINARY(64) NOT NULL, snapshot_count INT UNSIGNED NOT NULL, snapshot_digest BINARY(32) NOT NULL, captured_at DATETIME(6) NOT NULL, PRIMARY KEY(id), CONSTRAINT chk_owner_journey_singleton CHECK (id=1)) ENGINE=InnoDB');
    verifyOwnerJourneyCutoverSchema($database);
    if ((int)$database->query('SELECT COUNT(*) FROM owner_journey_cutover')->fetchColumn()!==0 || (int)$database->query('SELECT COUNT(*) FROM portfolios WHERE legacy_owner_user_id IS NOT NULL')->fetchColumn()!==0) { throw new RuntimeException('Unrecorded cutover data requires manual inspection; no adoption.'); }
    testOnlyMigrationFailurePoint('after-owner-cutover-table');
}

/** Only the explicit maintenance command calls this. A retry verifies the frozen
 * set, never rescans unmarked Portfolios into it. No HTTP/startup caller exists.
 * The caller must stop ALL writers before capture, including pending requests.
 */
function captureLegacyOwnerCutover(PDO $database): void
{
    if ($database->inTransaction() || $database->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) { throw new LogicException('Capture owns its transaction.'); }
    if ($database->query("SELECT name FROM schema_migrations WHERE version='020'")->fetchColumn()!=='owner_journey_cutover') { throw new RuntimeException('Cutover schema is not recorded.'); }
    verifyOwnerJourneyCutoverSchema($database);
    if ((int)$database->query("SELECT GET_LOCK('ather_owner_journey_cutover',5)")->fetchColumn()!==1) { throw new RuntimeException('Capture lock unavailable.'); }
    $commitAttempted=false;
    try {
        $database->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $database->beginTransaction();
        $sealed=$database->query('SELECT * FROM owner_journey_cutover WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if ($sealed!==false) {
            $rows=$database->query('SELECT id,legacy_owner_user_id AS owner_user_id FROM portfolios WHERE legacy_owner_user_id IS NOT NULL ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
            if ($sealed['revision']!==OWNER_JOURNEY_CUTOVER_REVISION || (int)$sealed['snapshot_count']!==count($rows) || !hash_equals($sealed['snapshot_digest'],legacyOwnerSnapshotDigest($rows))) { throw new RuntimeException('Frozen cutover snapshot changed; manual inspection required.'); }
        } else {
            $rows=$database->query('SELECT id,owner_user_id,legacy_owner_user_id FROM portfolios ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) { if ($row['legacy_owner_user_id']!==null) { throw new RuntimeException('Partial cutover state must not be adopted.'); } }
            $database->exec('UPDATE portfolios SET legacy_owner_user_id=owner_user_id');
            testOnlyMigrationFailurePoint('after-owner-cutover-capture');
            $insert=$database->prepare('INSERT INTO owner_journey_cutover (id,revision,snapshot_count,snapshot_digest,captured_at) VALUES (1,?,?,?,UTC_TIMESTAMP(6))');
            $insert->execute([OWNER_JOURNEY_CUTOVER_REVISION,count($rows),legacyOwnerSnapshotDigest($rows)]);
            testOnlyMigrationFailurePoint('after-owner-cutover-seal');
        }
        $commitAttempted=true;
        if (!$database->commit()) { throw new RuntimeException('Capture commit not acknowledged.'); }
    } catch (Throwable $error) {
        try { if ($database->inTransaction()) { $database->rollBack(); } } catch (Throwable) {}
        if ($commitAttempted) { throw new RuntimeException('Capture outcome uncertain; inspect frozen snapshot before retry.',0,$error); }
        throw $error;
    } finally { $database->query("SELECT RELEASE_LOCK('ather_owner_journey_cutover')"); }
}

function legacyOwnerSnapshotDigest(array $rows): string
{
    $pairs=array_map(static fn(array $row): array=>[(int)$row['id'],(int)$row['owner_user_id']],$rows);
    return hash('sha256',json_encode($pairs,JSON_THROW_ON_ERROR),true);
}
