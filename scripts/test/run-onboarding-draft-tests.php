<?php
declare(strict_types=1);
require_once __DIR__ . '/../../tests/phase2/support/draft-fixtures.php';
require_once __DIR__ . '/../../database/onboarding_drafts.php';
ob_start();

function draftAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
function draftSnapshot(PDO $db, bool $oldOnly = false): array
{
    $result = [];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if ($oldOnly && in_array($table, ['schema_migrations', 'onboarding_drafts'], true)) { continue; }
        $rows = array_map('serialize', $db->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC));
        sort($rows); $result[$table] = hash('sha256', serialize($rows));
    }
    return $result;
}
function draftDenied(PDO $db, callable $operation, string $class, ?int $mysqlCode = null): void
{
    $before = draftSnapshot($db); $caught = false;
    try { $operation(); } catch (Throwable $error) {
        $caught = true; draftAssert($error instanceof $class, 'Unexpected draft exception class.');
        if ($mysqlCode !== null) { draftAssert($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === $mysqlCode, 'Unrelated SQL error swallowed or changed.'); }
    }
    draftAssert($caught && $before === draftSnapshot($db), 'Denied draft operation changed data or did not fail.');
}
function draftMigrate(bool $success, ?string $failure = null): void
{
    $env = getenv(); unset($env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER']);
    if ($failure !== null) { $env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER'] = $failure; }
    $process = proc_open([PHP_BINARY, __DIR__ . '/../../database/migrate.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    draftAssert(is_resource($process), 'Migration process unavailable.');
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    draftAssert((proc_close($process) === 0) === $success, 'Unexpected draft migration result: ' . $out . $err);
}
function draftRace(int $userId, array $inputs): array
{
    $workers = [];
    try {
        foreach ($inputs as $input) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/run-onboarding-draft-race-worker.php', (string) $userId, base64_encode(json_encode($input, JSON_THROW_ON_ERROR))], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            draftAssert(is_resource($process), 'Race process unavailable.'); stream_set_timeout($pipes[1], 25); $workers[] = [$process, $pipes];
        }
        foreach ($workers as [$process, $pipes]) { draftAssert(fgets($pipes[1]) === "READY\n", 'Concurrent barrier failed.'); }
        foreach ($workers as [$process, $pipes]) { fwrite($pipes[0], "GO\n"); fclose($pipes[0]); }
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $results[] = json_decode(stream_get_contents($pipes[1]), true, 16, JSON_THROW_ON_ERROR);
            draftAssert(stream_get_contents($pipes[2]) === '', 'Concurrent worker diagnostic.'); fclose($pipes[1]); fclose($pipes[2]); draftAssert(proc_close($process) === 0, 'Concurrent worker failed.');
        }
        return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) { foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } } if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
    }
}
final class DraftCommitFailure extends PDO
{
    public int $expectedUser;
    public int $expectedVersion;
    public bool $sawWrite = false;
    public function commit(): bool
    {
        $this->sawWrite = $this->inTransaction() && (int) $this->query('SELECT version FROM onboarding_drafts WHERE user_id=' . $this->expectedUser)->fetchColumn() === $this->expectedVersion;
        throw new PDOException('Synthetic draft commit failure after real write.');
    }
}
final class DraftSqlFailure extends PDO
{
    public string $fault;
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'UPDATE onboarding_drafts') || str_starts_with($query, 'INSERT INTO onboarding_drafts')) {
            $this->exec($this->fault);
        }
        return parent::prepare($query, $options);
    }
}

$db = getDatabaseConnection(); $initial = draftSnapshot($db); $old = draftSnapshot($db, true);
draftAssert((int) $db->query('SELECT COUNT(*) FROM onboarding_drafts')->fetchColumn() === 0, 'Requires empty disposable draft store.');
$ledger = $db->query("SELECT * FROM schema_migrations WHERE version='016'")->fetch(PDO::FETCH_ASSOC);
draftAssert(is_array($ledger), 'Requires fully migrated disposable schema.');
$users = []; $root = attestationTestStartSession();
try {
    $db->exec("DELETE FROM schema_migrations WHERE version='016'"); $db->exec('DROP TABLE onboarding_drafts');
    draftMigrate(false, 'after-draft-table-create');
    draftAssert((int) $db->query("SELECT COUNT(*) FROM schema_migrations WHERE version='016'")->fetchColumn() === 0, 'Interrupted DDL recorded ledger.');
    draftMigrate(true); draftMigrate(true);
    $db->exec("DELETE FROM schema_migrations WHERE version='016'");
    $db->exec('ALTER TABLE onboarding_drafts DROP FOREIGN KEY fk_onboarding_drafts_user'); draftMigrate(false);
    $db->exec('DROP TABLE onboarding_drafts'); $db->exec('CREATE TABLE onboarding_drafts (user_id INT UNSIGNED NOT NULL) ENGINE=InnoDB'); draftMigrate(false);
    $db->exec('DROP TABLE onboarding_drafts'); draftMigrate(true);
    $db->exec('ALTER TABLE onboarding_drafts MODIFY last_operation_id VARBINARY(42) NOT NULL'); draftMigrate(false);
    $db->exec('ALTER TABLE onboarding_drafts MODIFY last_operation_id VARBINARY(43) NOT NULL');
    $db->exec('ALTER TABLE onboarding_drafts ALTER CHECK chk_onboarding_drafts_version NOT ENFORCED'); draftMigrate(false);
    $db->exec('ALTER TABLE onboarding_drafts ALTER CHECK chk_onboarding_drafts_version ENFORCED');
    $db->exec('ALTER TABLE onboarding_drafts DROP CHECK chk_onboarding_drafts_version');
    $db->exec('ALTER TABLE onboarding_drafts ADD CONSTRAINT chk_onboarding_drafts_version CHECK (version > 0 OR 1=1)'); draftMigrate(false);
    $db->exec('ALTER TABLE onboarding_drafts DROP CHECK chk_onboarding_drafts_version');
    $db->exec('ALTER TABLE onboarding_drafts ADD CONSTRAINT chk_onboarding_drafts_version CHECK (' . onboardingDraftSchemaChecks()['chk_onboarding_drafts_version'] . ')');
    $db->exec('ALTER TABLE onboarding_drafts DROP CHECK chk_onboarding_drafts_section');
    $alteredSection = str_replace('public_identity', 'public_ identity', onboardingDraftSchemaChecks()['chk_onboarding_drafts_section']);
    $db->exec('ALTER TABLE onboarding_drafts ADD CONSTRAINT chk_onboarding_drafts_section CHECK (' . $alteredSection . ')'); draftMigrate(false);
    $db->exec('ALTER TABLE onboarding_drafts DROP CHECK chk_onboarding_drafts_section');
    $db->exec('ALTER TABLE onboarding_drafts ADD CONSTRAINT chk_onboarding_drafts_section CHECK (' . onboardingDraftSchemaChecks()['chk_onboarding_drafts_section'] . ')');
    draftMigrate(true);
    draftAssert($old === draftSnapshot($db, true), 'Migration changed existing rows.');
    echo "PASS draft 001–015 upgrade, repeat, interrupted recovery, incompatible schema, exact enforced checks and preservation\n";

    for ($i = 0; $i < 4; $i++) {
        $db->prepare('INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (?, ?, ?, 1)')->execute(['https://draft.invalid/', 'test-' . bin2hex(random_bytes(16)), 'active']);
        $users[] = (int) $db->lastInsertId();
    }
    [$id, $other, $raceId, $legacyId] = $users; $context = attestationTestSession($id);
    $empty = ['version' => 0, 'fields' => [], 'last_edited_section' => null, 'created_at' => null, 'updated_at' => null];
    draftAssert(readOnboardingDraft($db, $context) === $empty, 'Absent draft contract changed.');
    $firstId = draftTestOperation(0);
    $db->exec("SET time_zone='+09:00'"); $utc = $db->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
    $first = saveOnboardingDraft($db, $context, 0, $firstId, 'public_identity', ['display_name' => '  مومن Example  ', 'country_code' => 'KW', 'preferred_language' => 'en']);
    draftAssert($first['created_at'] === $first['updated_at'] && $first['created_at'] >= $utc && $first['created_at'] <= $db->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn(), 'Server UTC creation time failed.');
    $db->exec("SET time_zone='+00:00'");
    $second = saveOnboardingDraft($db, $context, 1, draftTestOperation(1), 'professional_snapshot', ['professional_status' => 'other', 'headline' => 'Draft headline', 'biography' => "Private\nbiography", 'graduation_year' => '2031']);
    draftAssert($second['fields']['display_name'] === '  مومن Example  ' && $second['fields']['country_code'] === 'KW' && $second['fields']['professional_status'] === 'other', 'Sections/catalog candidates or exact text changed.');
    $third = saveOnboardingDraft($db, $context, 2, draftTestOperation(2), 'public_identity', ['display_name' => null, 'city' => 'New city']);
    draftAssert(!isset($third['fields']['display_name']) && $third['fields']['country_code'] === 'KW' && $third['fields']['biography'] === "Private\nbiography", 'Clear/omission/section preservation failed.');
    $fourth = saveOnboardingDraft($db, $context, 3, draftTestOperation(3), 'profile_identity', ['slug_candidate' => 'unfinished-', 'phone' => '+962 candidate']);
    draftAssert($fourth === readOnboardingDraft($db, $context) && $fourth['created_at'] === $first['created_at'] && $fourth['last_edited_section'] === 'profile_identity', 'Exact resume failed.');
    $fifth = saveOnboardingDraft($db, $context, 4, draftTestOperation(4), 'profile_identity', []);
    draftAssert($fifth['version'] === 5 && $fifth['fields'] === $fourth['fields'], 'Empty patch erased fields.');
    echo "PASS draft partial save, explicit clear, section isolation, candidate catalogs, exact resume and server UTC\n";

    foreach ([false, 1, 1.5, [], new stdClass(), '', ' ', "bad\0text", "bad\ntext", "\xC3\x28", str_repeat('a', 151), str_repeat('界', 151)] as $bad) {
        draftDenied($db, fn () => saveOnboardingDraft($db, $context, 5, draftTestOperation(5), 'public_identity', ['display_name' => $bad]), InvalidArgumentException::class);
    }
    foreach (['user_id', 'owner_user_id', 'version', 'completed', 'email_verified', 'age_18_or_older', 'terms_accepted', 'privacy_notice_acknowledged', 'is_published', 'headline', 'created_at'] as $key) {
        draftDenied($db, fn () => saveOnboardingDraft($db, $context, 5, draftTestOperation(5), 'public_identity', ['city' => 'Must not partially write', $key => 'unexpected']), InvalidArgumentException::class);
    }
    foreach (['account_readiness', 'review', '', 'PUBLIC_IDENTITY', 2, null] as $badSection) {
        draftDenied($db, fn () => saveOnboardingDraft($db, $context, 5, draftTestOperation(5), $badSection, []), InvalidArgumentException::class);
    }
    foreach (['country_code' => 'nz', 'preferred_language' => 'en_US'] as $key => $value) {
        draftDenied($db, fn () => saveOnboardingDraft($db, $context, 5, draftTestOperation(5), 'public_identity', [$key => $value]), InvalidArgumentException::class);
    }
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 5, draftTestOperation(5), 'professional_snapshot', ['graduation_year' => 2030]), InvalidArgumentException::class);
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 5, draftTestOperation(5), 'profile_identity', ['slug_candidate' => 'Invalid slug']), InvalidArgumentException::class);
    // The JSON byte budget also applies after merging sections, including escaping.
    saveOnboardingDraft($db, $context, 5, draftTestOperation(5), 'professional_snapshot', ['biography' => str_repeat('😀', 2000), 'headline' => str_repeat('😀', 200), 'university' => str_repeat('😀', 200), 'specialization' => str_repeat('😀', 150), 'professional_status' => 'other']);
    $sixth = readOnboardingDraft($db, $context); draftAssert($sixth['version'] === 6, 'Valid maximum text rejected.');
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 6, draftTestOperation(6), 'professional_snapshot', ['biography' => str_repeat('"', 2000) . str_repeat('😀', 1)]), InvalidArgumentException::class);
    // Unicode line separators require JSON escaping; each field is within its
    // own character/UTF-8 limit, but the merged serialized payload exceeds 16 KiB.
    $escaped = static fn (int $length): string => 'x' . str_repeat("\u{2028}", $length - 1);
    $otherContext = attestationTestSession($other);
    saveOnboardingDraft($db, $otherContext, 0, draftTestOperation(0), 'professional_snapshot', ['biography' => $escaped(2000), 'headline' => $escaped(200), 'university' => $escaped(200), 'specialization' => $escaped(150), 'professional_status' => 'other']);
    draftDenied($db, fn () => saveOnboardingDraft($db, $otherContext, 1, draftTestOperation(1), 'public_identity', ['display_name' => $escaped(150), 'name_ar' => $escaped(150), 'name_en' => $escaped(150), 'city' => $escaped(100)]), InvalidArgumentException::class);
    $db->exec("DELETE FROM onboarding_drafts WHERE user_id=$other");
    attestationTestSession($id);
    echo "PASS draft malformed, oversized, wrong-section and authority input rejection without partial writes\n";

    foreach ([-1, '6', 6.0, null, true, 2147483648] as $badVersion) {
        draftDenied($db, fn () => saveOnboardingDraft($db, $context, $badVersion, draftTestOperation(6), 'public_identity', []), InvalidArgumentException::class);
    }
    foreach (['6:ABC', draftTestOperation(5), '06:' . str_repeat('a', 32), '6:' . str_repeat('a', 33), []] as $badOperation) {
        draftDenied($db, fn () => saveOnboardingDraft($db, $context, 6, $badOperation, 'public_identity', []), InvalidArgumentException::class);
    }
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 0, draftTestOperation(0), 'public_identity', []), OnboardingDraftConflictException::class);
    $operation = draftTestOperation(6); $patch = ['city' => 'Private city', 'name_en' => null];
    $saved = saveOnboardingDraft($db, $context, 6, $operation, 'public_identity', $patch);
    draftAssert($saved === saveOnboardingDraft($db, $context, 6, $operation, 'public_identity', array_reverse($patch, true)), 'Same operation/key-order retry changed version or result.');
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 6, $operation, 'public_identity', ['city' => 'Different']), OnboardingDraftConflictException::class);
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 6, $operation, 'public_identity', ['city' => 'Private city']), OnboardingDraftConflictException::class);
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 6, $operation, 'professional_snapshot', []), OnboardingDraftConflictException::class);
    saveOnboardingDraft($db, $context, 7, draftTestOperation(7), 'public_identity', []);
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 6, $operation, 'public_identity', $patch), OnboardingDraftConflictException::class);
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 8, $operation, 'public_identity', $patch), InvalidArgumentException::class);
    echo "PASS draft initial/stale versions, exact bounded retry receipt, changed-input conflict, operation-ID reuse rejection and evicted-operation refusal\n";

    foreach (['anonymous', 'inactive', 'disabled', 'revoked', 'idle', 'absolute', 'malformed', 'cross-user'] as $case) {
        $context = attestationTestSession($id);
        if ($case === 'anonymous') { $_SESSION = []; }
        if ($case === 'inactive') { session_write_close(); }
        if ($case === 'disabled') { $db->exec("UPDATE users SET account_status='disabled' WHERE id=$id"); }
        if ($case === 'revoked') { $db->exec("UPDATE users SET authz_version=2 WHERE id=$id"); }
        if ($case === 'idle') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] -= 2000; $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] -= 2000; }
        if ($case === 'absolute') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] -= 50000; }
        if ($case === 'malformed') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authz_version'] = '1'; }
        if ($case === 'cross-user') { $context = AuthenticatedUserContext::fromValidatedUser($other); }
        $session = $_SESSION;
        draftDenied($db, fn () => readOnboardingDraft($db, $context), AuthorizationDeniedException::class);
        if ($case !== 'inactive') { attestationTestSession($id); $_SESSION = $session; }
        draftDenied($db, fn () => saveOnboardingDraft($db, $context, 8, draftTestOperation(8), 'public_identity', []), AuthorizationDeniedException::class);
        $db->exec("UPDATE users SET account_status='active', authz_version=1 WHERE id=$id");
    }
    $context = attestationTestSession($other); draftAssert(readOnboardingDraft($db, $context) === $empty, 'Cross-user resume leaked fields.');
    $revoke = new class('mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]) extends PDO {
        public Closure $beforeLock;
        public function beginTransaction(): bool { ($this->beforeLock)(); return parent::beginTransaction(); }
    };
    $revoke->beforeLock = static function () use ($db, $other): void { $db->exec("UPDATE users SET authz_version=2 WHERE id=$other"); };
    $caught = false;
    try { saveOnboardingDraft($revoke, $context, 0, draftTestOperation(0), 'public_identity', []); } catch (AuthorizationDeniedException) { $caught = true; }
    draftAssert($caught && !$revoke->inTransaction(), 'Revocation between authorization and User lock accepted.');
    $db->exec("UPDATE users SET authz_version=1 WHERE id=$other");
    echo "PASS draft current-session/account/lifetime/context revalidation and cross-user resume denial\n";

    $context = attestationTestSession($other); $db->beginTransaction();
    $db->exec("UPDATE users SET authz_version=9 WHERE id=$other");
    draftDenied($db, fn () => readOnboardingDraft($db, $context), LogicException::class);
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 0, draftTestOperation(0), 'public_identity', []), LogicException::class);
    draftAssert($db->inTransaction() && (int) $db->query("SELECT authz_version FROM users WHERE id=$other")->fetchColumn() === 9, 'Caller work committed/rolled back.'); $db->rollBack();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
    draftDenied($db, fn () => readOnboardingDraft($db, $context), LogicException::class);
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 0, draftTestOperation(0), 'public_identity', []), LogicException::class);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $fault = draftTestConnection(DraftCommitFailure::class); $fault->expectedUser = $other; $fault->expectedVersion = 1;
    draftDenied($db, fn () => saveOnboardingDraft($fault, $context, 0, draftTestOperation(0), 'public_identity', ['city' => 'Rollback insert']), PDOException::class);
    draftAssert($fault->sawWrite && !$fault->inTransaction(), 'Real INSERT not rolled back.');
    saveOnboardingDraft($db, $context, 0, draftTestOperation(0), 'public_identity', ['city' => 'Preserve before update']);
    $fault->expectedVersion = 2; $fault->sawWrite = false;
    draftDenied($db, fn () => saveOnboardingDraft($fault, $context, 1, draftTestOperation(1), 'public_identity', ['city' => 'Rollback update']), PDOException::class);
    draftAssert($fault->sawWrite && !$fault->inTransaction(), 'Real UPDATE not rolled back.');
    $sqlFault = draftTestConnection(DraftSqlFailure::class); $sqlFault->fault = 'SELECT * FROM deliberately_missing_draft_test_table';
    draftDenied($db, fn () => saveOnboardingDraft($sqlFault, $context, 1, draftTestOperation(1), 'public_identity', []), PDOException::class, 1146);
    $sqlFault->fault = "INSERT INTO onboarding_drafts SELECT * FROM onboarding_drafts WHERE user_id=$other";
    draftDenied($db, fn () => saveOnboardingDraft($sqlFault, $context, 1, draftTestOperation(1), 'public_identity', []), PDOException::class, 1062);
    $db->exec("UPDATE onboarding_drafts SET version=2147483647 WHERE user_id=$other");
    draftDenied($db, fn () => saveOnboardingDraft($db, $context, 2147483647, draftTestOperation(2147483647), 'public_identity', []), OnboardingDraftConflictException::class);
    draftDenied($db, fn () => $db->exec("UPDATE onboarding_drafts SET version=0 WHERE user_id=$other"), PDOException::class, 3819);
    draftDenied($db, fn () => $db->exec("UPDATE onboarding_drafts SET payload='[]' WHERE user_id=$other"), PDOException::class, 3819);
    draftDenied($db, fn () => $db->exec("UPDATE onboarding_drafts SET last_section='account_readiness' WHERE user_id=$other"), PDOException::class, 3819);
    draftDenied($db, fn () => $db->exec("DELETE FROM users WHERE id=$other"), PDOException::class, 1451);
    echo "PASS draft owned transactions, real INSERT/UPDATE rollback, unrelated SQL propagation, version cap and schema/FK constraints\n";

    $op = draftTestOperation(0); $same = [0, $op, 'public_identity', ['display_name' => 'Concurrent']];
    $results = draftRace($raceId, [$same, $same]);
    draftAssert($results[0]['status'] === 'saved' && $results[0] === $results[1] && $results[0]['result']['version'] === 1, 'Concurrent identical creation duplicated.');
    $db->exec("DELETE FROM onboarding_drafts WHERE user_id=$raceId");
    $results = draftRace($raceId, [[0, draftTestOperation(0), 'public_identity', ['display_name' => 'A']], [0, draftTestOperation(0), 'public_identity', ['display_name' => 'B']]]);
    $statuses = array_column($results, 'status'); sort($statuses); draftAssert($statuses === ['conflict', 'saved'], 'Concurrent different creation lost version conflict.');
    $raceContext = attestationTestSession($raceId); $original = readOnboardingDraft($db, $raceContext);
    $results = draftRace($raceId, [[1, draftTestOperation(1), 'public_identity', ['city' => 'A']], [1, draftTestOperation(1), 'professional_snapshot', ['headline' => 'B']]]);
    $statuses = array_column($results, 'status'); sort($statuses); draftAssert($statuses === ['conflict', 'saved'], 'Concurrent update lost version conflict.');
    $after = readOnboardingDraft($db, $raceContext);
    draftAssert($after['version'] === 2 && $after['fields']['display_name'] === $original['fields']['display_name'] && count($after['fields']) === 2, 'Competing updates changed untouched fields.');
    $op = draftTestOperation(2); $same = [2, $op, 'public_identity', ['city' => 'Same update']];
    $results = draftRace($raceId, [$same, $same]);
    draftAssert($results[0] === $results[1] && $results[0]['result']['version'] === 3, 'Concurrent identical update duplicated.');
    $op = draftTestOperation(3);
    $results = draftRace($raceId, [[3, $op, 'public_identity', ['city' => 'First']], [3, $op, 'public_identity', ['city' => 'Second']]]);
    $statuses = array_column($results, 'status'); sort($statuses); draftAssert($statuses === ['conflict', 'saved'], 'Concurrent operation ID input mismatch silently succeeded.');
    draftAssert(readOnboardingDraft($db, $raceContext)['version'] === 4 && (int) $db->query("SELECT COUNT(*) FROM onboarding_drafts WHERE user_id=$raceId")->fetchColumn() === 1, 'Concurrent draft count/version incorrect.');
    echo "PASS draft genuine concurrent creation/update, same-operation retries and different-input conflicts\n";

    // Current writes share Profile membership/rules; compatibility decoding does not.
    $catalog = finalProfileCatalog();
    draftAssert(array_keys($catalog['countries']) === ['JO','PS','SA','AE','QA','KW','BH','OM','IQ','LB','SY','YE','EG'] && array_keys($catalog['languages']) === ['ar', 'en']
        && count($catalog['statuses']) === 6, 'Shared catalog coverage changed.');
    foreach (['countries' => 'country_code', 'languages' => 'preferred_language', 'statuses' => 'professional_status'] as $group => $field) {
        foreach ($catalog[$group] as $token => $labels) {
            onboardingDraftValidateCurrentValues([$field => $token]);
            draftAssert(is_string($labels['ar']) && $labels['ar'] !== '' && is_string($labels['en']) && $labels['en'] !== '', 'Missing catalog label.');
        }
    }
    foreach (['country_code' => ['US','CA','ZZ','XK','UK','jo','JO '], 'preferred_language' => ['fr-CA', 'AR', 'en-US'],
        'professional_status' => ['early', 'independent', 'Research sabbatical', 'Employed'],
        'specialization' => ['', ' ', "bad\0text", "bad\ntext", "\xC3\x28", str_repeat('😀', 151)]] as $field => $badValues) {
        foreach ($badValues as $bad) {
            try { onboardingDraftValidateCurrentValues([$field => $bad]); throw new RuntimeException('Current unsupported value accepted.'); }
            catch (InvalidArgumentException) { /* Expected, no mutation. */ }
        }
    }
    foreach (['  تخصص é 😀  ', str_repeat('😀', 150), null] as $value) { onboardingDraftValidateCurrentValues(['specialization' => $value]); }
    draftAssert(onboardingDraftDecode(onboardingDraftEncode(['professional_status' => str_repeat('😀',80), 'preferred_language' => 'fr-CA', 'country_code' => 'ZZ']))
        === ['country_code' => 'ZZ', 'preferred_language' => 'fr-CA', 'professional_status' => str_repeat('😀',80)], 'Stored compatibility was tightened.');
    echo "PASS draft shared Profile catalogs, exact AR-EN coverage and specialization rules with unchanged stored bounds\n";

    $legacyFields = ['country_code' => 'US', 'preferred_language' => 'fr-CA', 'professional_status' => str_repeat('😀',80),
        'specialization' => '  تخصص é  ', 'headline' => str_repeat('界',200), 'slug_candidate' => 'unfinished-', 'phone' => str_repeat('1',40)];
    $legacyPatch = array_intersect_key($legacyFields, array_flip(['country_code','preferred_language']));
    $receipt = draftTestSeedHistorical($db,$legacyId,3,'public_identity',$legacyPatch,$legacyFields);
    $legacyContext = attestationTestSession($legacyId);
    $legacy = readOnboardingDraft($db,$legacyContext); $beforeRetry = draftSnapshot($db);
    $retry = static fn (array $patch): array => saveOnboardingDraft($db,$legacyContext,2,$receipt['operationId'],'public_identity',$patch);
    draftAssert($legacy['fields'] === onboardingDraftDecode(onboardingDraftEncode($legacyFields)) && $legacy === $retry(array_reverse($legacyPatch,true))
        && $beforeRetry === draftSnapshot($db), 'Historical exact receipt or read changed fields/time/version.');
    draftDenied($db, fn () => $retry(['country_code'=>'CA']), OnboardingDraftConflictException::class);
    draftDenied($db, fn () => $retry(['country_code'=>['invalid']]), InvalidArgumentException::class);
    $db->exec("UPDATE users SET authz_version=2 WHERE id=$legacyId");
    draftDenied($db, fn () => $retry($legacyPatch), AuthorizationDeniedException::class);
    $db->exec("UPDATE users SET authz_version=1 WHERE id=$legacyId"); attestationTestSession($legacyId);
    foreach (['country_code'=>'ZZ','preferred_language'=>'fr-CA','professional_status'=>str_repeat('😀',80)] as $field=>$value) {
        $section=$field==='professional_status'?'professional_snapshot':'public_identity';
        draftDenied($db, fn () => saveOnboardingDraft($db,$legacyContext,3,draftTestOperation(3),$section,[$field=>$value]), InvalidArgumentException::class);
    }
    $beforeRetry=draftSnapshot($db);
    $same=[2,$receipt['operationId'],'public_identity',$legacyPatch];
    $results=draftRace($legacyId,[$same,$same]);
    draftAssert($results[0] === $results[1] && $results[0]['status']==='saved' && $results[0]['result']===$legacy
        && $beforeRetry === draftSnapshot($db), 'Concurrent historical retries changed receipt/result.');
    $results=draftRace($legacyId,[[3,draftTestOperation(3),'public_identity',['display_name'=>'Unrelated edit']],
        [3,draftTestOperation(3),'professional_snapshot',['headline'=>'Concurrent headline']]]);
    $statuses=array_column($results,'status'); sort($statuses);
    draftAssert($statuses===['conflict','saved'], 'Historical-row concurrent edits lost version conflict.');
    $current=readOnboardingDraft($db,$legacyContext);
    foreach (['country_code','preferred_language','professional_status','phone','slug_candidate'] as $field) {
        draftAssert($current['fields'][$field]===$legacyFields[$field], 'Unrelated concurrent save changed historical/omitted values.');
    }
    draftDenied($db, fn () => $retry($legacyPatch), OnboardingDraftConflictException::class);
    echo "PASS draft historical resume and exact authorized receipts, misuse rejection, concurrent retries and unrelated writes\n";

    $current=saveOnboardingDraft($db,$legacyContext,4,draftTestOperation(4),'public_identity',['country_code'=>null,'preferred_language'=>'ar']);
    draftAssert(!isset($current['fields']['country_code']) && $current['fields']['preferred_language']==='ar'
        && $current['fields']['professional_status']===$legacyFields['professional_status'], 'Historical clearing/replacement changed other fields.');
    $current=saveOnboardingDraft($db,$legacyContext,5,draftTestOperation(5),'professional_snapshot',['professional_status'=>'self_employed','specialization'=>str_repeat('😀',150)]);
    draftAssert($current['fields']['specialization']===str_repeat('😀',150) && $current['fields']['professional_status']==='self_employed', 'Supported replacement/Unicode boundary failed.');
    $current=saveOnboardingDraft($db,$legacyContext,6,draftTestOperation(6),'professional_snapshot',['professional_status'=>null,'specialization'=>null]);
    draftAssert(!isset($current['fields']['professional_status']) && !isset($current['fields']['specialization']) && $current['fields']['phone']===$legacyFields['phone'], 'Explicit historical clears or omitted optional data failed.');
    $results=draftRace($legacyId,[[7,draftTestOperation(7),'public_identity',['country_code'=>'OM']],
        [7,draftTestOperation(7),'professional_snapshot',['professional_status'=>'graduate']]]);
    $statuses=array_column($results,'status');sort($statuses);
    draftAssert($statuses===['conflict','saved'],'Concurrent current catalog saves did not choose one winner.');
    $current=readOnboardingDraft($db,$legacyContext);
    draftAssert($current['version']===8 && (isset($current['fields']['country_code']) xor isset($current['fields']['professional_status']))
        && $current['fields']['phone']===$legacyFields['phone'],'Concurrent catalog save changed omitted historical fields.');
    echo "PASS draft historical replacement and explicit null clearing remain partial and incomplete with no profile writes\n";

} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
    foreach ($users as $id) { $db->exec("DELETE FROM onboarding_drafts WHERE user_id=$id"); $db->exec("DELETE FROM users WHERE id=$id"); }
    $db->prepare("UPDATE schema_migrations SET applied_at=? WHERE version='016'")->execute([$ledger['applied_at']]);
    attestationTestEndSession($root);
}
draftAssert($initial === draftSnapshot($db), 'Existing rows changed or draft fixtures remain.');
echo "PASS draft existing Users/bindings/attestations/ownership/content preserved and private fixture cleanup\n";
