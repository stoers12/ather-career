<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

const OWNERSHIP_TEST_ISSUER = 'https://issuer.test/ather-career';
const OWNERSHIP_TEST_SUBJECT = 'subject-preserved-v1-owner';

/** @return list<array<string, mixed>> */
function ownershipSnapshot(PDO $database, string $table, string $columns): array
{
    return $database->query("SELECT {$columns} FROM `{$table}` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, list<array<string, mixed>>> */
function ownershipPreservationSnapshot(PDO $database): array
{
    return [
        'personal_info' => ownershipSnapshot($database, 'personal_info', 'id, full_name, professional_title, email, phone_primary, phone_secondary, location, about_me, work_description, linkedin_url, github_url, instagram_url, facebook_url, website_url, profile_image_path, updated_at'),
        'skills' => ownershipSnapshot($database, 'skills', 'id, skill_name, created_at'),
        'projects' => ownershipSnapshot($database, 'projects', 'id, title, category, description, github_url, image_path, created_at'),
        'messages' => ownershipSnapshot($database, 'messages', 'id, name, email, message, created_at'),
    ];
}

function ownershipRunPhp(array $arguments, bool $expectSuccess = true): string
{
    $command = escapeshellarg(PHP_BINARY);
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }
    $command .= ' 2>&1';

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $result = implode("\n", $output);
    if (($exitCode === 0) !== $expectSuccess) {
        throw new RuntimeException('Unexpected child command result: ' . $result);
    }

    return $result;
}

function ownershipAssertDatabaseFailure(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (PDOException) {
        return;
    }

    throw new RuntimeException($message);
}

function ownershipCreateUser(PDO $database, string $subject, string $status = 'active', int $authzVersion = 1): int
{
    $statement = $database->prepare(
        'INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
         VALUES (:issuer, :subject, :status, :authz_version)'
    );
    $statement->execute([
        'issuer' => OWNERSHIP_TEST_ISSUER,
        'subject' => $subject,
        'status' => $status,
        'authz_version' => $authzVersion,
    ]);

    return (int) $database->lastInsertId();
}

function ownershipCreatePortfolio(PDO $database, int $userId): int
{
    $statement = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:user_id)');
    $statement->execute(['user_id' => $userId]);

    return (int) $database->lastInsertId();
}

function ownershipRunRace(string $mode, array $arguments): array
{
    $worker = __DIR__ . '/run-phase2-ownership-race-worker.php';
    $processes = [];
    for ($index = 0; $index < 2; $index++) {
        $command = [PHP_BINARY, $worker, $mode, ...$arguments];
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start an ownership race worker.');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }

    $results = [];
    foreach ($processes as [$process, $pipes]) {
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $results[] = proc_close($process);
    }

    sort($results);
    return $results;
}

function ownershipAssertColumnNullable(PDO $database, string $table, string $column, string $expected): void
{
    $statement = $database->prepare(
        'SELECT is_nullable
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
    );
    $statement->execute(['table' => $table, 'column' => $column]);
    phase2AssertSame($expected, $statement->fetchColumn(), "Unexpected {$table}.{$column} nullability.");
}

function ownershipAssertMigrationLedger(PDO $database, array $expected): void
{
    $actual = $database->query('SELECT version, name FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_KEY_PAIR);
    phase2AssertSame($expected, $actual, 'Migration ledger does not match the expected checkpoint.');
}

function ownershipAssertNoPublicLifecycleColumns(PDO $database): void
{
    $statement = $database->query(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'portfolios'
           AND column_name IN ('public_slug', 'is_published', 'published_at')"
    );
    phase2AssertSame(0, (int) $statement->fetchColumn(), 'P2J-02 must not add public lifecycle columns.');
}

function ownershipSeedRepresentativeV1Data(PDO $database): void
{
    phase2AssertSame(false, $database->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn(), 'Ownership rehearsal requires a fresh disposable V1 database.');
    phase2AssertSame(0, (int) $database->query('SELECT COUNT(*) FROM personal_info')->fetchColumn(), 'Ownership rehearsal requires unmodified V1 personal_info.');
    phase2AssertSame(0, (int) $database->query('SELECT COUNT(*) FROM skills')->fetchColumn(), 'Ownership rehearsal requires unmodified V1 skills.');
    phase2AssertSame(0, (int) $database->query('SELECT COUNT(*) FROM messages')->fetchColumn(), 'Ownership rehearsal requires unmodified V1 messages.');

    $profile = $database->prepare(
        'INSERT INTO personal_info (full_name, professional_title, email, location, about_me, profile_image_path, updated_at)
         VALUES (:full_name, :professional_title, :email, :location, :about_me, :profile_image_path, :updated_at)'
    );
    $profile->execute([
        'full_name' => 'Preserved V1 Owner',
        'professional_title' => 'Software Engineer',
        'email' => 'preserved@example.test',
        'location' => 'Amman',
        'about_me' => 'Preserved V1 profile content.',
        'profile_image_path' => 'uploads/profile/preserved-original.png',
        'updated_at' => '2025-01-02 03:04:05',
    ]);

    $skill = $database->prepare('INSERT INTO skills (skill_name, created_at) VALUES (:skill_name, :created_at)');
    $skill->execute(['skill_name' => 'PHP', 'created_at' => '2025-01-02 03:04:05']);
    $skill->execute(['skill_name' => 'MySQL', 'created_at' => '2025-01-02 03:04:06']);

    $project = $database->prepare(
        'INSERT INTO projects (title, category, description, github_url, image_path, created_at)
         VALUES (:title, :category, :description, :github_url, :image_path, :created_at)'
    );
    $project->execute([
        'title' => 'Preserved Project',
        'category' => 'Web',
        'description' => 'Preserved V1 project content.',
        'github_url' => 'https://github.com/example/preserved-project',
        'image_path' => 'uploads/projects/preserved-project.png',
        'created_at' => '2025-01-02 03:04:07',
    ]);

    $message = $database->prepare('INSERT INTO messages (name, email, message, created_at) VALUES (:name, :email, :message, :created_at)');
    $message->execute([
        'name' => 'Preserved Sender',
        'email' => 'sender@example.test',
        'message' => 'Preserved V1 message content.',
        'created_at' => '2025-01-02 03:04:08',
    ]);
}

$environment = null;
try {
    TestEnvironment::assertSafeEnvironment(getenv());
    $environment = TestEnvironment::create();
    $database = getDatabaseConnection();
    ownershipSeedRepresentativeV1Data($database);
    $before = ownershipPreservationSnapshot($database);

    ownershipRunPhp([__DIR__ . '/../database/migrate.php', '--through=003']);
    ownershipAssertMigrationLedger($database, [
        '001' => 'baseline',
        '002' => 'integrity_constraints',
        '003' => 'ownership_expand',
    ]);
    ownershipAssertColumnNullable($database, 'portfolios', 'owner_user_id', 'NO');
    foreach (['personal_info' => 'portfolio_id', 'skills' => 'portfolio_id', 'projects' => 'portfolio_id', 'messages' => 'recipient_portfolio_id'] as $table => $column) {
        ownershipAssertColumnNullable($database, $table, $column, 'YES');
    }
    ownershipAssertNoPublicLifecycleColumns($database);

    ownershipRunPhp([__DIR__ . '/../database/migrate.php', '--through=004'], false);
    ownershipAssertMigrationLedger($database, [
        '001' => 'baseline',
        '002' => 'integrity_constraints',
        '003' => 'ownership_expand',
    ]);
    ownershipAssertColumnNullable($database, 'personal_info', 'portfolio_id', 'YES');

    ownershipRunPhp([
        __DIR__ . '/../database/backfill-v1-ownership.php',
        '--issuer', OWNERSHIP_TEST_ISSUER,
        '--subject', OWNERSHIP_TEST_SUBJECT,
        '--test-fail-after', 'after-user-create',
    ], false);
    phase2AssertSame(0, (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'Backfill failure after User creation must roll back the User.');
    phase2AssertSame(0, (int) $database->query('SELECT COUNT(*) FROM portfolios')->fetchColumn(), 'Backfill failure after User creation must roll back the Portfolio.');

    ownershipRunPhp([
        __DIR__ . '/../database/backfill-v1-ownership.php',
        '--issuer', OWNERSHIP_TEST_ISSUER,
        '--subject', OWNERSHIP_TEST_SUBJECT,
        '--test-fail-after', 'during-resource-backfill',
    ], false);
    phase2AssertSame(0, (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'Backfill failure during mapping must roll back the User.');
    phase2AssertSame(0, (int) $database->query('SELECT COUNT(*) FROM portfolios')->fetchColumn(), 'Backfill failure during mapping must roll back the Portfolio.');

    ownershipRunPhp([
        __DIR__ . '/../database/backfill-v1-ownership.php',
        '--issuer', OWNERSHIP_TEST_ISSUER,
        '--subject', OWNERSHIP_TEST_SUBJECT,
    ]);
    $preservedPortfolioId = (int) $database->query('SELECT id FROM portfolios')->fetchColumn();
    phase2Assert($preservedPortfolioId > 0, 'Backfill did not create the preserved Portfolio.');

    $skillId = (int) $database->query("SELECT id FROM skills WHERE skill_name = 'PHP'")->fetchColumn();
    $database->prepare('UPDATE skills SET portfolio_id = NULL WHERE id = :id')->execute(['id' => $skillId]);
    ownershipRunPhp([
        __DIR__ . '/../database/backfill-v1-ownership.php',
        '--issuer', OWNERSHIP_TEST_ISSUER,
        '--subject', OWNERSHIP_TEST_SUBJECT,
    ]);
    $skillPortfolio = $database->prepare('SELECT portfolio_id FROM skills WHERE id = :id');
    $skillPortfolio->execute(['id' => $skillId]);
    phase2AssertSame($preservedPortfolioId, (int) $skillPortfolio->fetchColumn(), 'Backfill retry did not repair an owned partial resource mapping.');
    ownershipRunPhp([
        __DIR__ . '/../database/backfill-v1-ownership.php',
        '--issuer', OWNERSHIP_TEST_ISSUER,
        '--subject', OWNERSHIP_TEST_SUBJECT,
    ]);
    phase2AssertSame($before, ownershipPreservationSnapshot($database), 'Expand/backfill changed preserved V1 values, IDs, timestamps, or image references.');

    putenv('ATHERCAR_TEST_MIGRATION_FAIL_AFTER=after-first-contract-column');
    ownershipRunPhp([__DIR__ . '/../database/migrate.php', '--through=004'], false);
    putenv('ATHERCAR_TEST_MIGRATION_FAIL_AFTER');
    ownershipAssertMigrationLedger($database, [
        '001' => 'baseline',
        '002' => 'integrity_constraints',
        '003' => 'ownership_expand',
    ]);
    ownershipAssertColumnNullable($database, 'personal_info', 'portfolio_id', 'NO');
    ownershipAssertColumnNullable($database, 'skills', 'portfolio_id', 'YES');

    ownershipRunPhp([__DIR__ . '/../database/migrate.php', '--through=004']);
    ownershipRunPhp([__DIR__ . '/../database/migrate.php']);
    ownershipAssertMigrationLedger($database, [
        '001' => 'baseline',
        '002' => 'integrity_constraints',
        '003' => 'ownership_expand',
        '004' => 'ownership_contract',
    ]);
    foreach (['personal_info' => 'portfolio_id', 'skills' => 'portfolio_id', 'projects' => 'portfolio_id', 'messages' => 'recipient_portfolio_id'] as $table => $column) {
        ownershipAssertColumnNullable($database, $table, $column, 'NO');
    }
    phase2AssertSame($before, ownershipPreservationSnapshot($database), 'Ownership Contract changed preserved V1 values, IDs, timestamps, or image references.');

    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->exec('INSERT INTO portfolios (owner_user_id) VALUES (NULL)') !== false,
        'Portfolio with a NULL owner was accepted.'
    );
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->exec('INSERT INTO portfolios (owner_user_id) VALUES (4294967295)') !== false,
        'Portfolio with a missing owner User was accepted.'
    );
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->exec("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES ('https://issuer.test/ather-career', 'invalid-status', 'other', 1)") !== false,
        'An unsupported account status was accepted.'
    );
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->exec("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES ('https://issuer.test/ather-career', 'zero-version', 'active', 0)") !== false,
        'A non-positive authz_version was accepted.'
    );

    $userWithoutPortfolio = ownershipCreateUser($database, 'subject-user-no-portfolio');
    $noPortfolioStatement = $database->prepare('SELECT COUNT(*) FROM portfolios WHERE owner_user_id = :user_id');
    $noPortfolioStatement->execute(['user_id' => $userWithoutPortfolio]);
    phase2AssertSame(0, (int) $noPortfolioStatement->fetchColumn(), 'A User without a Portfolio was not representable.');

    $userB = ownershipCreateUser($database, 'subject-user-b');
    $portfolioB = ownershipCreatePortfolio($database, $userB);
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:user_id)')->execute(['user_id' => $userB]),
        'One User was allowed to own two Portfolios.'
    );
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->prepare('INSERT INTO personal_info (full_name, portfolio_id) VALUES (:full_name, :portfolio_id)')->execute(['full_name' => 'Duplicate Profile', 'portfolio_id' => $preservedPortfolioId]),
        'A Portfolio was allowed to own two personal_info rows.'
    );
    $database->prepare('INSERT INTO skills (portfolio_id, skill_name) VALUES (:portfolio_id, :skill_name)')->execute(['portfolio_id' => $portfolioB, 'skill_name' => 'PHP']);
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->prepare('INSERT INTO skills (portfolio_id, skill_name) VALUES (:portfolio_id, :skill_name)')->execute(['portfolio_id' => $portfolioB, 'skill_name' => 'PHP']),
        'Duplicate skills within one Portfolio were accepted.'
    );
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->exec("INSERT INTO projects (title, category, description, github_url, portfolio_id) VALUES ('Orphan', 'Test', 'Orphan project', 'https://example.test/orphan', 4294967295)") !== false,
        'Project with an invalid Portfolio was accepted.'
    );
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->exec("INSERT INTO messages (name, email, message, recipient_portfolio_id) VALUES ('Orphan', 'orphan@example.test', 'Orphan message', 4294967295)") !== false,
        'Message with an invalid recipient Portfolio was accepted.'
    );
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->prepare('DELETE FROM portfolios WHERE id = :id')->execute(['id' => $preservedPortfolioId]),
        'RESTRICT allowed deletion of a Portfolio with resources.'
    );
    $preservedUserId = (int) $database->query('SELECT owner_user_id FROM portfolios WHERE id = ' . $preservedPortfolioId)->fetchColumn();
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $preservedUserId]),
        'RESTRICT allowed deletion of a User with a Portfolio.'
    );

    ownershipCreateUser($database, 'Subject-Case-Sensitive');
    ownershipCreateUser($database, 'subject-case-sensitive');
    ownershipAssertDatabaseFailure(
        static fn (): bool => $database->exec("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES ('https://issuer.test/ather-career', 'Subject-Case-Sensitive', 'active', 1)") !== false,
        'Exact duplicate OIDC subject was accepted.'
    );

    $identityRaceSubject = 'race-identity-' . bin2hex(random_bytes(4));
    phase2AssertSame([0, 1], ownershipRunRace('identity', [$identityRaceSubject]), 'Duplicate identity race did not produce exactly one winner.');
    $raceUser = ownershipCreateUser($database, 'race-portfolio-user-' . bin2hex(random_bytes(4)));
    phase2AssertSame([0, 1], ownershipRunRace('portfolio', [(string) $raceUser]), 'Two Portfolio-create race did not produce exactly one winner.');
    $racePortfolioStatement = $database->prepare('SELECT id FROM portfolios WHERE owner_user_id = :user_id');
    $racePortfolioStatement->execute(['user_id' => $raceUser]);
    $racePortfolio = (int) $racePortfolioStatement->fetchColumn();
    phase2Assert($racePortfolio > 0, 'Portfolio race did not create the expected Portfolio.');
    $raceSkill = 'race-skill-' . bin2hex(random_bytes(4));
    phase2AssertSame([0, 1], ownershipRunRace('skill', [(string) $racePortfolio, $raceSkill]), 'Duplicate same-Portfolio skill race did not produce exactly one winner.');

} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL ownership migration rehearsal: {$exception->getMessage()}\n");
    exit(1);
} finally {
    if ($environment instanceof TestEnvironment) {
        try {
            $environment->tearDown();
        } catch (Throwable $exception) {
            fwrite(STDERR, "FAIL ownership migration test namespace teardown: {$exception->getMessage()}\n");
            exit(1);
        }
    }
}

echo "PASS ownership migration rehearsal\n";
