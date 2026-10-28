<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/^ather_career_test_[a-f0-9]{24}$/', (string) getenv('DB_NAME')) !== 1
    || getenv('DB_NAME') !== getenv('ATHERCAR_TEST_DB_NAME')) {
    exit(2);
}
require_once __DIR__ . '/../../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth0_identity.php';
require_once __DIR__ . '/../../includes/auth0_registration.php';

// A test-owned connection intercepts only the second INSERT. The first INSERT
// runs against real MySQL; a SELECT within that transaction proves it happened.
// This avoids privileged triggers and adds no hooks to production code.
final class RegistrationFailureDatabase extends PDO
{
    public string $failureSql;
    public string $insertedSubject;
    public bool $sawInsertedUser = false;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'INSERT INTO user_identities')) {
            $this->sawInsertedUser = (int) $this->query('SELECT COUNT(*) FROM users WHERE oidc_subject=' . $this->quote($this->insertedSubject))->fetchColumn() === 1;
            return parent::prepare($this->failureSql, $options);
        }
        return parent::prepare($query, $options);
    }
}

function registrationConfiguration(string $issuer = 'https://registration.invalid/'): Auth0OidcConfiguration
{
    return new Auth0OidcConfiguration($issuer, 'registration.invalid', 'synthetic-client', 'synthetic-secret', 'https://app.invalid/owner_oidc_callback.php');
}

/** @return array<string, string> */
function registrationSnapshot(PDO $database, bool $protectedOnly = false): array
{
    $snapshot = [];
    foreach ($database->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if ($protectedOnly && in_array($table, ['users', 'user_identities'], true)) { continue; }
        $rows = $database->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
        $serialized = array_map(static fn (array $row): string => serialize($row), $rows);
        sort($serialized);
        $snapshot[$table] = hash('sha256', serialize($serialized));
    }
    return $snapshot;
}

function registrationDenied(PDO $database, callable $operation, string $expectedClass = IdentityRepositoryException::class, ?int $mysqlCode = null): void
{
    $before = registrationSnapshot($database);
    $caught = false;
    try {
        $operation();
    } catch (Throwable $exception) {
        phase2Assert($exception instanceof $expectedClass, 'Registration failed with an unexpected exception class.');
        if ($mysqlCode !== null) {
            phase2Assert($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === $mysqlCode, 'Database failure was swallowed or reclassified.');
        }
        $caught = true;
    }
    phase2Assert($caught, 'Registration unexpectedly succeeded.');
    phase2AssertSame($before, registrationSnapshot($database), 'Denied registration changed rows.');
}

function registrationFixture(PDO $database, string $issuer, string $subject, string $status = 'active', ?string $bindingSubject = null, int $primary = 1, bool $binding = true): int
{
    $database->prepare('INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (?, ?, ?, 7)')->execute([$issuer, $subject, $status]);
    $id = (int) $database->lastInsertId();
    if ($binding) {
        $database->prepare('INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject, is_primary) VALUES (?, ?, ?, ?)')->execute([$id, $issuer, $bindingSubject ?? $subject, $primary]);
    }
    return $id;
}

/** @return list<array<string, mixed>> */
function registrationRace(array $identities, ?callable $whilePaused = null): array
{
    $workers = [];
    try {
        foreach ($identities as [$issuer, $subject]) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/run-registration-core-race-worker.php', base64_encode($issuer), base64_encode($subject)],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) { throw new RuntimeException('Registration worker could not start.'); }
            stream_set_timeout($pipes[1], 25);
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [$process, $pipes]) {
            phase2AssertSame("READY\n", fgets($pipes[1]), 'Worker did not reach the pre-insert barrier.');
        }
        if ($whilePaused !== null) { $whilePaused(); }
        foreach ($workers as [$process, $pipes]) {
            fwrite($pipes[0], "GO\n");
            fclose($pipes[0]);
        }
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $diagnostic = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            phase2AssertSame(0, proc_close($process), 'Registration worker failed.');
            phase2AssertSame('', $diagnostic, 'Registration worker emitted a diagnostic.');
            $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) {
            foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        }
    }
}

$environment = null;
$exitCode = 0;
try {
    $environment = TestEnvironment::create();
    $database = getDatabaseConnection();
    phase2AssertSame('identity_foundation', $database->query("SELECT name FROM schema_migrations WHERE version = '014'")->fetchColumn(), 'Registration requires migrated schema 014.');
    $configuration = registrationConfiguration();
    $issuer = $configuration->issuer;
    $prefix = 'registration-' . $environment->runId . '-';
    $protected = registrationSnapshot($database, true);
    $existingUsers = $database->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $existingBindings = $database->query('SELECT * FROM user_identities ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $sessionStatus = session_status();
    $_SESSION = ['registration_test_marker' => 'unchanged'];
    $session = $_SESSION;
    $newIdentity = new Auth0ValidatedIdentity($issuer, $prefix . 'new', true, true);
    registrationDenied($database, static fn () => resolveAuth0InternalUser($database, $configuration, $newIdentity), Auth0OidcException::class);
    $beforeUsers = (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $beforeBindings = (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn();
    $created = registerAuth0InternalUser($database, $configuration, $newIdentity);
    phase2AssertSame(['user_id', 'authz_version'], array_keys($created), 'Registration returned unnecessary external data.');
    phase2AssertSame(1, $created['authz_version'], 'New authorization version is invalid.');
    phase2AssertSame($beforeUsers + 1, (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'New User was not created exactly once.');
    phase2AssertSame($beforeBindings + 1, (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn(), 'New binding was not created exactly once.');
    $row = $database->query('SELECT u.oidc_issuer, u.oidc_subject, u.account_status, i.user_id, i.oidc_issuer AS binding_issuer, i.oidc_subject AS binding_subject, i.is_primary, i.provider_name, i.connection_name FROM users u JOIN user_identities i ON i.user_id=u.id WHERE u.id=' . $created['user_id'])->fetch(PDO::FETCH_ASSOC);
    phase2AssertSame($issuer, $row['oidc_issuer'], 'Issuer bytes changed.');
    phase2AssertSame($newIdentity->subject, $row['oidc_subject'], 'Subject bytes changed.');
    phase2AssertSame($row['oidc_issuer'], $row['binding_issuer'], 'Issuer compatibility is invalid.');
    phase2AssertSame($row['oidc_subject'], $row['binding_subject'], 'Subject compatibility is invalid.');
    phase2AssertSame('active', $row['account_status'], 'New User status is invalid.');
    phase2AssertSame(1, (int) $row['is_primary'], 'New binding is not primary.');
    phase2AssertSame(null, $row['provider_name'], 'Provider was inferred.');
    phase2AssertSame(null, $row['connection_name'], 'Connection was inferred.');
    $unchanged = registrationSnapshot($database);
    foreach ([true, false, null] as $claim) {
        phase2AssertSame($created, registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $newIdentity->subject, $claim, false)), 'Repeat registration did not resolve original User.');
    }
    phase2AssertSame($unchanged, registrationSnapshot($database), 'Repeat resolution changed rows.');
    phase2AssertSame($created, resolveAuth0InternalUser($database, $configuration, $newIdentity), 'Ordinary Sign In cannot resolve the registered binding.');
    echo "PASS registration new creation, repeat resolution, exact primary compatibility, unset metadata, ordinary Sign In boundary\n";

    foreach ([[false, true], [null, true], [true, false], ['true', true], [1, true], [[], true]] as $index => [$claim, $email]) {
        registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $prefix . 'readiness-' . $index, $claim, $email)));
    }
    foreach (['', '   ', "bad\0subject", "bad\nsubject", "bad\x7fsubject", "\xff", str_repeat('x', 256)] as $subject) {
        registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $subject, true, true)));
    }
    foreach (['', 'not-a-url', 'http://registration.invalid/', 'https://registration.invalid/path/', 'https://registration.invalid/?x=1', 'https://user:pass@registration.invalid/', "https://registration.invalid/\n", 'https://' . str_repeat('x', 2048) . '/'] as $badIssuer) {
        registrationDenied($database, static fn () => registerAuth0InternalUser($database, registrationConfiguration($badIssuer), new Auth0ValidatedIdentity($badIssuer, $prefix . 'bad-issuer', true, true)));
    }
    foreach (['', 'https://other.invalid/', str_repeat('x', 2049)] as $badIssuer) {
        registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($badIssuer, $prefix . 'mismatch', true, true)));
    }
    registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, [], true, true)), TypeError::class);
    echo "PASS registration strict readiness and malformed/empty/oversized/mismatched inputs create nothing\n";

    $ids = [];
    foreach (['google-oauth2|' . $prefix . 'provider-unknown', $prefix . 'Case', $prefix . 'case', $prefix . 'case ', $prefix . "caf\u{00e9}", $prefix . "cafe\u{0301}", str_repeat('s', 255)] as $subject) {
        $id = registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $subject, true, true));
        $ids[] = $id['user_id'];
        $stored = $database->query('SELECT oidc_subject FROM users WHERE id=' . $id['user_id'])->fetchColumn();
        phase2AssertSame($subject, $stored, 'Opaque subject bytes were normalized.');
    }
    phase2AssertSame(count($ids), count(array_unique($ids)), 'Distinct subjects were merged.');
    $otherConfig = registrationConfiguration('https://Registration.invalid/');
    $otherSubject = $prefix . 'other-issuer';
    $other = registerAuth0InternalUser($database, $otherConfig, new Auth0ValidatedIdentity($otherConfig->issuer, $otherSubject, true, true));
    phase2AssertSame($otherConfig->issuer, $database->query('SELECT oidc_issuer FROM users WHERE id=' . $other['user_id'])->fetchColumn(), 'Issuer bytes were normalized.');
    registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $otherSubject, true, true)));
    echo "PASS registration binary case/space/Unicode distinctions and same-subject different-issuer collision\n";

    $active = registrationFixture($database, $issuer, $prefix . 'active');
    $before = registrationSnapshot($database);
    phase2AssertSame(['user_id' => $active, 'authz_version' => 7], registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $prefix . 'active')), 'Existing active binding changed account or authorization version.');
    phase2AssertSame($before, registrationSnapshot($database), 'Existing account resolution mutated data.');
    registrationFixture($database, $issuer, $prefix . 'disabled', 'disabled');
    registrationFixture($database, $issuer, $prefix . 'legacy', 'active', null, 1, false);
    registrationFixture($database, $issuer, $prefix . 'nonprimary', 'active', null, 0);
    registrationFixture($database, $issuer, $prefix . 'legacy-subject', 'active', $prefix . 'binding-subject');
    foreach (['disabled', 'legacy', 'nonprimary', 'binding-subject'] as $suffix) {
        registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $prefix . $suffix, true, true)));
    }
    $database->prepare('INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject, is_primary) VALUES (?, ?, ?, 1)')->execute([$active, $issuer, $prefix . 'extra-primary']);
    registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $prefix . 'active', true, true)));
    $database->prepare('DELETE FROM user_identities WHERE oidc_subject=?')->execute([$prefix . 'extra-primary']);
    echo "PASS registration existing active, disabled, legacy-only, nonprimary, legacy mismatch and duplicate-primary bindings\n";

    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
    try {
        registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, $newIdentity), LogicException::class);
    } finally { $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    $database->beginTransaction();
    $database->prepare('INSERT INTO users (oidc_issuer, oidc_subject) VALUES (?, ?)')->execute([$issuer, $prefix . 'caller-work']);
    registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, $newIdentity), LogicException::class);
    phase2Assert($database->inTransaction(), 'Registration committed or rolled back caller-owned work.');
    $database->rollBack();
    phase2AssertSame(0, (int) $database->query("SELECT COUNT(*) FROM users WHERE oidc_subject=" . $database->quote($prefix . 'caller-work'))->fetchColumn(), 'Caller-owned work escaped rollback.');
    echo "PASS registration refuses caller-owned transactions without committing or rolling them back\n";

    $database->exec('CREATE TABLE registration_unique_probe (id INT PRIMARY KEY) ENGINE=InnoDB');
    $database->exec('INSERT INTO registration_unique_probe VALUES (1)');
    try {
        foreach (['SELECT * FROM registration_missing_table' => 1146, 'INSERT INTO registration_unique_probe (id) SELECT 1 WHERE :user_id > 0 AND :issuer IS NOT NULL AND :subject IS NOT NULL' => 1062] as $failureSql => $driverCode) {
            $fault = new RegistrationFailureDatabase(
                'mysql:host=' . getRequiredDatabaseEnvironment('DB_HOST') . ';port=' . getRequiredDatabaseEnvironment('DB_PORT')
                    . ';dbname=' . getRequiredDatabaseEnvironment('DB_NAME') . ';charset=utf8mb4',
                getRequiredDatabaseEnvironment('DB_USER'), getRequiredDatabaseEnvironment('DB_PASSWORD'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
            );
            $fault->failureSql = $failureSql;
            $fault->insertedSubject = $prefix . 'rollback-' . $driverCode;
            registrationDenied($database, static fn () => registerAuth0InternalUser($fault, $configuration, new Auth0ValidatedIdentity($issuer, $fault->insertedSubject, true, true)), PDOException::class, $driverCode);
            phase2Assert($fault->sawInsertedUser, 'Fault did not occur after a real User INSERT.');
            phase2Assert(!$fault->inTransaction(), 'Failed registration retained a transaction.');
        }
    } finally { $database->exec('DROP TABLE registration_unique_probe'); }
    echo "PASS registration real MySQL failures between inserts roll back both rows and unrelated duplicate errors propagate\n";

    $raceSubject = $prefix . 'race';
    $beforeUsers = (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $beforeBindings = (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn();
    $race = registrationRace([[$issuer, $raceSubject], [$issuer, $raceSubject]]);
    phase2AssertSame($race[0]['result'], $race[1]['result'], 'Concurrent registration did not converge on one User.');
    phase2AssertSame(1, array_sum(array_column($race, 'conflicts')), 'Both workers did not attempt a genuinely new registration.');
    phase2AssertSame($beforeUsers + 1, (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'Concurrent attempts duplicated Users.');
    phase2AssertSame($beforeBindings + 1, (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn(), 'Concurrent attempts duplicated bindings.');
    $different = $prefix . 'race-issuer';
    $race = registrationRace([[$issuer, $different], [$otherConfig->issuer, $different]]);
    phase2AssertSame(1, count(array_filter($race, static fn (array $result): bool => isset($result['result']))), 'Different-issuer subject collision did not deny one attempt.');
    phase2AssertSame(1, count(array_filter($race, static fn (array $result): bool => ($result['denied'] ?? false) === true)), 'Different-issuer collision was silently accepted.');
    phase2AssertSame(1, array_sum(array_column($race, 'conflicts')), 'Different-issuer attempts did not collide.');
    foreach (['disabled', 'legacy-only', 'mismatch', 'nonprimary'] as $mode) {
        $subject = $prefix . 'winner-' . $mode;
        $race = registrationRace([[$issuer, $subject]], static function () use ($database, $issuer, $subject, $mode): void {
            registrationFixture($database, $issuer, $mode === 'mismatch' ? $subject . '-legacy' : $subject,
                $mode === 'disabled' ? 'disabled' : 'active', $subject, $mode === 'nonprimary' ? 0 : 1, $mode !== 'legacy-only');
        });
        phase2AssertSame(true, $race[0]['denied'] ?? false, 'An invalid race winner was accepted.');
        phase2AssertSame(1, $race[0]['conflicts'], 'Invalid winner did not exercise post-conflict resolution.');
        phase2AssertSame(0, (int) $database->query('SELECT COUNT(*) FROM users u LEFT JOIN user_identities i ON i.user_id=u.id WHERE u.oidc_subject=' . $database->quote($subject) . " AND i.id IS NULL")->fetchColumn() - ($mode === 'legacy-only' ? 1 : 0), 'Race loser left an orphan User.');
    }
    echo "PASS registration synchronized real MySQL concurrent attempts converge; different issuer and invalid race winners fail closed\n";

    $database->exec('RENAME TABLE user_identities TO registration_missing_bindings');
    try {
        registrationDenied($database, static fn () => registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $prefix . 'missing-foundation', true, true)));
    } finally { $database->exec('RENAME TABLE registration_missing_bindings TO user_identities'); }
    foreach ($existingUsers as $row) {
        phase2AssertSame($row, $database->query('SELECT * FROM users WHERE id=' . (int) $row['id'])->fetch(PDO::FETCH_ASSOC), 'Registration changed a preexisting User.');
    }
    foreach ($existingBindings as $row) {
        phase2AssertSame($row, $database->query('SELECT * FROM user_identities WHERE id=' . (int) $row['id'])->fetch(PDO::FETCH_ASSOC), 'Registration changed a preexisting binding.');
    }
    phase2AssertSame($protected, registrationSnapshot($database, true), 'Registration changed Portfolio, ownership, content or migration rows.');
    phase2AssertSame($sessionStatus, session_status(), 'Registration established a session.');
    phase2AssertSame($session, $_SESSION, 'Registration changed session authority.');
    registrationDenied($database, static fn () => resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $prefix . 'still-unknown', true, true)), Auth0OidcException::class);
    phase2AssertSame(0, (int) $database->query('SELECT COUNT(*) FROM user_identities WHERE oidc_subject = ' . $database->quote('google-oauth2|' . $prefix . 'provider-unknown') . ' AND (provider_name IS NOT NULL OR connection_name IS NOT NULL)')->fetchColumn(), 'Subject prefix inferred provider metadata.');
    echo "PASS registration missing foundation denies, preexisting Users/bindings and all Portfolio/content rows preserved, no session, unknown Sign In still denied\n";
} catch (Throwable $exception) {
    // Never print raw SQL exceptions, identity input, credentials or sessions.
    fwrite(STDERR, 'FAIL registration core: ' . ($exception instanceof PDOException ? 'database operation failed (SQLSTATE ' . ($exception->errorInfo[0] ?? 'unknown') . ', driver ' . (int) ($exception->errorInfo[1] ?? 0) . ', line ' . $exception->getLine() . ')' : $exception->getMessage()) . "\n");
    $exitCode = 1;
} finally {
    if ($environment instanceof TestEnvironment) { $environment->tearDown(); }
}
exit($exitCode);
