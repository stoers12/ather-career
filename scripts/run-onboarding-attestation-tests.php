<?php
declare(strict_types=1);
require_once __DIR__ . '/../tests/phase2/support/attestation-fixtures.php';
ob_start();

function attestationAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
function attestationSnapshot(PDO $db, bool $oldOnly = false): array
{
    $snapshot = [];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if ($oldOnly && in_array($table, ['schema_migrations', 'onboarding_attestations'], true)) { continue; }
        $rows = array_map('serialize', $db->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC));
        sort($rows); $snapshot[$table] = hash('sha256', serialize($rows));
    }
    return $snapshot;
}
function attestationDenied(PDO $db, callable $operation, string $class): void
{
    $before = attestationSnapshot($db); $caught = false;
    try { $operation(); } catch (Throwable $error) { $caught = true; attestationAssert($error instanceof $class, 'Unexpected exception: ' . get_class($error)); }
    attestationAssert($caught && $before === attestationSnapshot($db), 'Denial changed rows or did not fail.');
}
function attestationMigrate(bool $success, ?string $failure = null): void
{
    $env = getenv(); unset($env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER']);
    if ($failure !== null) { $env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER'] = $failure; }
    $process = proc_open([PHP_BINARY, __DIR__ . '/../database/migrate.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    if (!is_resource($process)) { throw new RuntimeException('Migration process unavailable.'); }
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    attestationAssert((proc_close($process) === 0) === $success, 'Unexpected migration result: ' . $out . $err);
}

// Substitutes a real failing INSERT on the owned PDO connection. No production hook.
final class AttestationFailureDatabase extends PDO
{
    public bool $insertReached = false;
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'INSERT INTO onboarding_attestations')) {
            $this->insertReached = $this->inTransaction();
            $this->exec('INSERT INTO onboarding_attestations SELECT * FROM onboarding_attestations WHERE 0');
            throw new PDOException('Synthetic failure before commit.');
        }
        return parent::prepare($query, $options);
    }
}

$db = getDatabaseConnection(); $initial = attestationSnapshot($db); $old = attestationSnapshot($db, true);
attestationAssert((int) $db->query('SELECT COUNT(*) FROM onboarding_attestations')->fetchColumn() === 0, 'Requires empty disposable evidence table.');
$ledger = $db->query("SELECT * FROM schema_migrations WHERE version='015'")->fetch(PDO::FETCH_ASSOC);
attestationAssert(is_array($ledger), 'Requires fully migrated disposable schema.');
$users = []; $workers = []; $sessionRoot = attestationTestStartSession();
try {
    // Simulate upgrading 001–014, interrupted atomic CREATE, incomplete and drifted DDL.
    $db->exec("DELETE FROM schema_migrations WHERE version='015'");
    $db->exec('DROP TABLE onboarding_attestations');
    attestationMigrate(false, 'after-attestation-table-create');
    attestationAssert((int) $db->query("SELECT COUNT(*) FROM schema_migrations WHERE version='015'")->fetchColumn() === 0, 'Failed DDL recorded ledger.');
    attestationMigrate(true); attestationMigrate(true);
    $db->exec("DELETE FROM schema_migrations WHERE version='015'");
    $db->exec('ALTER TABLE onboarding_attestations DROP FOREIGN KEY fk_onboarding_attestations_user');
    attestationMigrate(false);
    $db->exec('DROP TABLE onboarding_attestations');
    $db->exec('CREATE TABLE onboarding_attestations (user_id INT UNSIGNED NOT NULL) ENGINE=InnoDB');
    attestationMigrate(false);
    $db->exec('DROP TABLE onboarding_attestations'); attestationMigrate(true);
    $db->exec('ALTER TABLE onboarding_attestations MODIFY terms_digest VARBINARY(63) NOT NULL');
    attestationMigrate(false);
    $db->exec('ALTER TABLE onboarding_attestations MODIFY terms_digest VARBINARY(64) NOT NULL'); attestationMigrate(true);
    attestationAssert($old === attestationSnapshot($db, true), 'Migration changed preexisting data.');
    echo "PASS 001–014 upgrade, interrupted CREATE recovery, repeat, partial/incompatible schema, preservation\n";
    for ($i = 0; $i < 2; $i++) {
        $db->prepare('INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (?, ?, ?, 1)')->execute(['https://attestation.invalid/', 'test-' . bin2hex(random_bytes(16)), 'active']);
        $users[] = (int) $db->lastInsertId();
    }
    [$id, $other] = $users;
    $context = attestationTestSession($id); $bundle = attestationTestBundle(); $yes = attestationTestAffirmations();
    attestationAssert(currentOnboardingAttestationBundle() === null, 'Real policy availability activated.');
    attestationAssert(readOnboardingAttestations($db, $context, $bundle) === null, 'Missing evidence accepted.');
    attestationAssert(readOnboardingAttestations($db, $context, null) === null, 'Unavailable policy accepted.');
    attestationDenied($db, fn () => recordOnboardingAttestations($db, $context, null, $yes), LogicException::class);
    foreach (array_keys($yes) as $key) {
        foreach ([false, null, 1, 'true', '1', [], new stdClass()] as $bad) {
            $input = $yes; $input[$key] = $bad;
            attestationDenied($db, fn () => recordOnboardingAttestations($db, $context, $bundle, $input), InvalidArgumentException::class);
        }
        $input = $yes; unset($input[$key]);
        attestationDenied($db, fn () => recordOnboardingAttestations($db, $context, $bundle, $input), InvalidArgumentException::class);
    }
    foreach (['user_id', 'version', 'recorded_at', 'digest', 'email', 'ip_address'] as $extra) {
        attestationDenied($db, fn () => recordOnboardingAttestations($db, $context, $bundle, $yes + [$extra => 'unexpected']), InvalidArgumentException::class);
    }
    foreach ([['', 'v1', str_repeat('a', 64), 'en'], [str_repeat('a', 65), 'v1', str_repeat('a', 64), 'en'], ['id', 'v 1', str_repeat('a', 64), 'en'], ['id', 'v1', str_repeat('A', 64), 'en'], ['id', 'v1', str_repeat('a', 63), 'en'], ['id', 'v1', str_repeat('a', 64), 'en_XX']] as $arguments) {
        attestationDenied($db, fn () => new OnboardingDocumentDescriptor(...$arguments), InvalidArgumentException::class);
    }
    $db->exec("SET time_zone = '+09:00'");
    $beforeTime = $db->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
    $record = recordOnboardingAttestations($db, $context, $bundle, $yes);
    attestationAssert($record['recorded_at'] >= $beforeTime && $record['recorded_at'] <= $db->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn(), 'Timestamp not generated as UTC.');
    $db->exec("SET time_zone = '+00:00'");
    foreach ($bundle->parameters() as $key => $value) { attestationAssert($record[$key] === $value, 'Descriptor changed.'); }
    attestationAssert($record === recordOnboardingAttestations($db, $context, $bundle, $yes), 'Repeated evidence changed.');
    attestationDenied($db, fn () => $db->exec('INSERT INTO onboarding_attestations SELECT * FROM onboarding_attestations'), PDOException::class);
    $maximumDocument = new OnboardingDocumentDescriptor(str_repeat('i', 64), str_repeat('v', 64), str_repeat('f', 64), 'en-abcdefgh-abcdefgh-abcdefgh-abcde');
    $maximumBundle = new OnboardingAttestationBundle($maximumDocument, $maximumDocument, $maximumDocument);
    $maximumRecord = recordOnboardingAttestations($db, $context, $maximumBundle, $yes);
    foreach ($maximumBundle->parameters() as $key => $value) { attestationAssert($maximumRecord[$key] === $value, 'Maximum bounded value changed.'); }
    foreach ([attestationTestBundle('test-v2'), attestationTestBundle('test-v1', 'ar'), new OnboardingAttestationBundle($bundle->age, new OnboardingDocumentDescriptor('test-terms', 'test-v1', str_repeat('a', 64), 'en'), $bundle->privacy), new OnboardingAttestationBundle($bundle->age, $bundle->terms, new OnboardingDocumentDescriptor('TEST-PRIVACY', 'test-v1', $bundle->privacy->digest, 'en'))] as $changed) {
        attestationAssert(readOnboardingAttestations($db, $context, $changed) === null, 'Stale bundle carried forward.');
        recordOnboardingAttestations($db, $context, $changed, $yes);
        attestationAssert($record === readOnboardingAttestations($db, $context, $bundle), 'History overwritten.');
    }
    echo "PASS strict complete affirmations, exact descriptors/UTC, unavailable/stale policies, idempotence, history\n";
    foreach (['anonymous', 'inactive', 'disabled', 'revoked', 'expired', 'absolute', 'malformed', 'cross-user'] as $case) {
        $context = attestationTestSession($id);
        if ($case === 'anonymous') { $_SESSION = []; }
        if ($case === 'inactive') { session_write_close(); }
        if ($case === 'disabled') { $db->exec("UPDATE users SET account_status='disabled' WHERE id=$id"); }
        if ($case === 'revoked') { $db->exec("UPDATE users SET authz_version=2 WHERE id=$id"); }
        if ($case === 'expired') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] -= 2000; $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] -= 2000; }
        if ($case === 'absolute') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] -= 50000; }
        if ($case === 'malformed') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authz_version'] = '1'; }
        if ($case === 'cross-user') { $context = AuthenticatedUserContext::fromValidatedUser($other); }
        attestationDenied($db, fn () => readOnboardingAttestations($db, $context, $bundle), AuthorizationDeniedException::class);
        attestationDenied($db, fn () => recordOnboardingAttestations($db, $context, $bundle, $yes), AuthorizationDeniedException::class);
        $db->exec("UPDATE users SET account_status='active', authz_version=1 WHERE id=$id");
    }
    $context = attestationTestSession($other);
    attestationAssert(readOnboardingAttestations($db, $context, $bundle) === null, 'Cross-user evidence leaked.');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
    attestationDenied($db, fn () => recordOnboardingAttestations($db, $context, $bundle, $yes), LogicException::class);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction();
    attestationDenied($db, fn () => recordOnboardingAttestations($db, $context, $bundle, $yes), LogicException::class);
    attestationAssert($db->inTransaction(), 'Caller transaction ended.'); $db->rollBack();
    $fault = new AttestationFailureDatabase('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    attestationDenied($db, fn () => recordOnboardingAttestations($fault, $context, $bundle, $yes), PDOException::class);
    attestationAssert($fault->insertReached && !$fault->inTransaction(), 'Failure did not roll back.');
    // Separate commit-failure connection permits the real INSERT before throwing.
    $commitFault = new class('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]) extends PDO {
        public int $expectedUser;
        public bool $sawInsertedEvidence = false;
        public function commit(): bool {
            $this->sawInsertedEvidence = $this->inTransaction() && (int) $this->query('SELECT COUNT(*) FROM onboarding_attestations WHERE user_id=' . $this->expectedUser)->fetchColumn() === 1;
            throw new PDOException('Synthetic failure after INSERT.');
        }
    };
    $commitFault->expectedUser = $other;
    attestationDenied($db, fn () => recordOnboardingAttestations($commitFault, $context, $bundle, $yes), PDOException::class);
    attestationAssert($commitFault->sawInsertedEvidence && !$commitFault->inTransaction(), 'Real INSERT not rolled back.');
    // Revoke between initial authorization and User locking using an independent
    // committed connection; the second helper call must observe current authority.
    $revoke = new class('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]) extends PDO {
        public Closure $beforeLock;
        public function beginTransaction(): bool { ($this->beforeLock)(); return parent::beginTransaction(); }
    };
    $revoke->beforeLock = static function () use ($db, $other): void { $db->exec("UPDATE users SET authz_version=2 WHERE id=$other"); };
    $caught = false;
    try { recordOnboardingAttestations($revoke, $context, $bundle, $yes); } catch (AuthorizationDeniedException) { $caught = true; }
    attestationAssert($caught && !$revoke->inTransaction(), 'Revocation between validations was accepted.');
    $db->exec("UPDATE users SET authz_version=1 WHERE id=$other");
    attestationTestSession($other);
    attestationAssert(readOnboardingAttestations($db, $context, $bundle) === null, 'Revoked submission persisted.');
    echo "PASS anonymous, disabled, expired, revoked, malformed, cross-user, caller transaction and rollback\n";
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __DIR__ . '/run-onboarding-attestation-race-worker.php', (string) $id], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        attestationAssert(is_resource($process), 'Race process unavailable.'); stream_set_timeout($pipes[1], 25); $workers[] = [$process, $pipes];
    }
    foreach ($workers as [$process, $pipes]) { attestationAssert(fgets($pipes[1]) === "READY\n", 'Worker barrier failed.'); }
    foreach ($workers as [$process, $pipes]) { fwrite($pipes[0], "GO\n"); fclose($pipes[0]); }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $results[] = json_decode(stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
        attestationAssert(stream_get_contents($pipes[2]) === '', 'Race diagnostics.'); fclose($pipes[1]); fclose($pipes[2]); attestationAssert(proc_close($process) === 0, 'Race worker failed.');
    }
    $workers = [];
    attestationAssert($results[0] === $results[1], 'Concurrent submissions disagreed.');
    $context = attestationTestSession($id);
    attestationAssert(readOnboardingAttestations($db, $context, attestationTestBundle('test-race')) === $results[0], 'Concurrent evidence missing.');
    attestationDenied($db, fn () => $db->exec("DELETE FROM users WHERE id=$id"), PDOException::class);
    echo "PASS genuinely concurrent workers, one durable exact result, FK deletion RESTRICT\n";
} finally {
    foreach ($workers as [$process, $pipes]) { foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } } if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
    if ($db->inTransaction()) { $db->rollBack(); }
    foreach ($users as $id) { $db->exec("DELETE FROM onboarding_attestations WHERE user_id=$id"); $db->exec("DELETE FROM users WHERE id=$id"); }
    $db->prepare("UPDATE schema_migrations SET applied_at=? WHERE version='015'")->execute([$ledger['applied_at']]);
    attestationTestEndSession($sessionRoot);
}
attestationAssert($initial === attestationSnapshot($db), 'Existing rows changed or fixtures remain.');
echo "PASS complete existing-row preservation and fixture cleanup\n";
