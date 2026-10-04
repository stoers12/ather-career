<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/identity_repository.php';

function phase1Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{code: int, output: string} */
function phase1RunMigration(): array
{
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/../database/migrate.php', '--through=014'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Isolated migration process could not start.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'output' => $output];
}

/** @return array<string, array{count: int, hash: string}> */
function phase1ProtectedSnapshot(PDO $database): array
{
    $snapshot = [];
    foreach (['users', 'portfolios', 'personal_info', 'projects', 'skills', 'experiences', 'messages', 'recommendation_dispositions'] as $table) {
        $order = $table === 'recommendation_dispositions' ? 'portfolio_id, recommendation_key' : 'id';
        $rows = $database->query('SELECT * FROM `' . $table . '` ORDER BY ' . $order)->fetchAll(PDO::FETCH_ASSOC);
        $snapshot[$table] = ['count' => count($rows), 'hash' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
    }
    return $snapshot;
}

/** @return list<array<string, mixed>> */
function phase1LegacyUsers(PDO $database): array
{
    return $database->query('SELECT id, oidc_issuer, oidc_subject, account_status, authz_version FROM users ORDER BY id')
        ->fetchAll(PDO::FETCH_ASSOC);
}

function phase1CheckRepository(PDO $database, array $users): void
{
    foreach ($users as $user) {
        $found = findCompatibleIdentityUser($database, $user['oidc_issuer'], $user['oidc_subject']);
        phase1Assert($found !== null && $found['user_id'] === (int) $user['id']
            && $found['account_status'] === $user['account_status']
            && $found['authz_version'] === (int) $user['authz_version'], 'A legacy account did not resolve to its original user.');
        phase1Assert(findCompatibleIdentityUser($database, 'https://different.invalid/', $user['oidc_subject']) === null,
            'Issuer mismatch resolved to a legacy user.');
    }
    phase1Assert(findCompatibleIdentityUser($database, 'https://different.invalid/', 'unknown-subject') === null,
        'An unknown identity resolved to a user.');
}

function phase1CheckBindings(PDO $database, array $users): void
{
    $bindings = $database->query('SELECT user_id, oidc_issuer, oidc_subject, is_primary, provider_name, connection_name FROM user_identities ORDER BY user_id')
        ->fetchAll(PDO::FETCH_ASSOC);
    phase1Assert(count($bindings) === count($users), 'Identity binding count differs from legacy user count.');
    foreach ($users as $index => $user) {
        $binding = $bindings[$index];
        phase1Assert((int) $binding['user_id'] === (int) $user['id']
            && hash_equals($user['oidc_issuer'], $binding['oidc_issuer'])
            && hash_equals($user['oidc_subject'], $binding['oidc_subject'])
            && (int) $binding['is_primary'] === 1
            && $binding['provider_name'] === null && $binding['connection_name'] === null,
            'Identity backfill changed an account relationship or inferred a provider.');
    }
    phase1CheckRepository($database, $users);
}

try {
    $mode = $argv[1] ?? '';
    $databaseName = getenv('DB_NAME');
    phase1Assert(in_array($mode, ['fresh', 'upgraded', 'malformed', 'conflicting', 'partial'], true), 'Unknown rehearsal mode.');
    phase1Assert(getenv('APP_ENV') === 'test' && getenv('ATHERCAR_TEST_MODE') === '1'
        && getenv('ATHERCAR_PHASE1_ISOLATED_DB') === '1'
        && getenv('DB_HOST') === 'phase1-db'
        && is_string($databaseName)
        && preg_match('/^ather_phase1_[a-z0-9_]{8,48}$/D', $databaseName) === 1,
        'Phase 1 rehearsal refuses a non-isolated database target.');
    $database = getDatabaseConnection();
    $ledger = array_column($database->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC), 'version');
    phase1Assert($ledger === array_map(static fn (int $i): string => sprintf('%03d', $i), range(1, 13)),
        'Phase 1 rehearsal requires exactly ledger 001–013.');

    if ($mode === 'malformed') {
        $database->exec("INSERT INTO users (oidc_issuer, oidc_subject) VALUES ('', 'synthetic-malformed')");
        phase1Assert(phase1RunMigration()['code'] !== 0, 'Malformed legacy identity was accepted.');
        phase1Assert((int) $database->query("SELECT COUNT(*) FROM schema_migrations WHERE version = '014'")->fetchColumn() === 0,
            'Malformed migration changed the ledger.');
        phase1Assert(!identityRepositoryHasBindingsTable($database), 'Malformed input caused schema DDL.');
        echo "PASS PHASE1-MALFORMED-LEGACY-PRECONDITION\n";
        exit(0);
    }

    if ($mode === 'conflicting') {
        $database->exec("INSERT INTO users (oidc_issuer, oidc_subject) VALUES ('https://issuer.invalid/', 'synthetic-conflict')");
        $users = phase1LegacyUsers($database);
        phase1Assert(count($users) === 2, 'Conflict fixture requires two distinct legacy users.');
        putenv('ATHERCAR_TEST_MIGRATION_FAIL_AFTER=after-identity-table-create');
        phase1Assert(phase1RunMigration()['code'] !== 0, 'Conflict fixture could not establish isolated table DDL.');
        putenv('ATHERCAR_TEST_MIGRATION_FAIL_AFTER');
        phase1Assert(identityRepositoryHasBindingsTable($database)
            && (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn() === 0,
            'Conflict fixture did not start with an empty identity table.');
        $insert = $database->prepare(
            'INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject)
             VALUES (:user_id, :issuer, :subject)'
        );
        $insert->execute([
            'user_id' => $users[0]['id'],
            'issuer' => $users[1]['oidc_issuer'],
            'subject' => $users[1]['oidc_subject'],
        ]);
        $before = phase1ProtectedSnapshot($database);
        phase1Assert(phase1RunMigration()['code'] !== 0, 'Conflicting identity binding was accepted.');
        phase1Assert($before === phase1ProtectedSnapshot($database)
            && (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn() === 1
            && (int) $database->query("SELECT COUNT(*) FROM schema_migrations WHERE version = '014'")->fetchColumn() === 0,
            'Conflicting identity rejection changed protected rows or ledger.');
        try {
            $insert->execute([
                'user_id' => $users[1]['id'],
                'issuer' => $users[1]['oidc_issuer'],
                'subject' => $users[1]['oidc_subject'],
            ]);
            throw new RuntimeException('Duplicate issuer/subject pair was accepted.');
        } catch (PDOException $exception) {
            phase1Assert(($exception->errorInfo[1] ?? null) === 1062, 'Unexpected duplicate-pair rejection.');
        }
        echo "PASS PHASE1-CONFLICTING-BINDING-AND-UNIQUE-PAIR\n";
        exit(0);
    }

    if ($mode === 'partial') {
        $database->exec("INSERT INTO users (oidc_issuer, oidc_subject) VALUES ('https://issuer.invalid/', 'synthetic-partial')");
        putenv('ATHERCAR_TEST_MIGRATION_FAIL_AFTER=after-identity-table-create');
        phase1Assert(phase1RunMigration()['code'] !== 0, 'Partial-DDL injection did not stop migration.');
        putenv('ATHERCAR_TEST_MIGRATION_FAIL_AFTER');
        phase1Assert(identityRepositoryHasBindingsTable($database)
            && (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn() === 0
            && (int) $database->query("SELECT COUNT(*) FROM schema_migrations WHERE version = '014'")->fetchColumn() === 0,
            'Partial DDL did not leave the expected recoverable state.');
    }

    $users = phase1LegacyUsers($database);
    if ($mode === 'fresh') {
        phase1Assert(count($users) === 1
            && (int) $database->query('SELECT COUNT(*) FROM portfolios')->fetchColumn() === 1
            && (int) $database->query('SELECT COUNT(*) FROM projects WHERE portfolio_id IS NULL')->fetchColumn() === 0,
            'Fresh V1 seed was not assigned to its fixture owner before Migration 004.');
    }
    if ($mode === 'upgraded') {
        phase1Assert(count($users) === 5
            && (int) $database->query('SELECT COUNT(*) FROM portfolios')->fetchColumn() === 5
            && (int) $database->query('SELECT COUNT(*) FROM projects WHERE id BETWEEN 18 AND 21')->fetchColumn() === 4,
            'Upgraded copy does not match the protected five-account baseline.');
    }
    // The incomplete table is deliberately not an application-serving state.
    // Callback cutover is gated on a successful 014 runner and reconciliation.
    if ($mode !== 'partial') {
        phase1CheckRepository($database, $users);
    }
    $before = phase1ProtectedSnapshot($database);
    $result = phase1RunMigration();
    phase1Assert($result['code'] === 0, 'Migration 014 failed on the isolated database.');
    phase1CheckBindings($database, $users);
    phase1Assert($before === phase1ProtectedSnapshot($database), 'Migration 014 changed protected legacy rows.');
    $ledger = array_column($database->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC), 'version');
    phase1Assert(count($ledger) === 14 && end($ledger) === '014', 'Migration 014 ledger is incorrect.');
    $repeat = phase1RunMigration();
    phase1Assert($repeat['code'] === 0 && str_contains($repeat['output'], 'No pending migrations.'),
        'Migration 014 repeat run was not a no-op.');
    phase1Assert($before === phase1ProtectedSnapshot($database), 'Migration 014 repeat run changed protected rows.');
    phase1CheckBindings($database, $users);
    echo 'PASS PHASE1-', strtoupper($mode), '-BACKFILL users=', count($users), ' portfolios=',
        $before['portfolios']['count'], ' projects=', $before['projects']['count'], " ledger=001-014\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL Phase 1 identity rehearsal: ' . $exception->getMessage() . "\n");
    exit(1);
}
