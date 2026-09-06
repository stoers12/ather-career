<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/owner_actions.php';
require_once __DIR__ . '/../includes/portfolio_scoped_data.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';

const EXPERIENCE_REHEARSAL_ISSUER = 'https://issuer.test/ather-career';
const EXPERIENCE_REHEARSAL_REFERENCE_MONTH = '2026-09';

function experienceRehearsalRunPhp(array $arguments): void
{
    $pipes = [];
    $process = proc_open($arguments, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the Experience migration process.');
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Experience migration process failed: ' . trim((string) $stderr));
    }
}

function experienceRehearsalCreateUser(PDO $database, string $label): int
{
    $statement = $database->prepare(
        'INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
         VALUES (:issuer, :subject, \'active\', 1)'
    );
    $statement->execute([
        'issuer' => EXPERIENCE_REHEARSAL_ISSUER,
        'subject' => 's07a-' . $label . '-' . bin2hex(random_bytes(8)),
    ]);

    return (int) $database->lastInsertId();
}

function experienceRehearsalCreatePortfolio(PDO $database, int $userId): int
{
    $statement = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:owner_user_id)');
    $statement->execute(['owner_user_id' => $userId]);

    return (int) $database->lastInsertId();
}

function experienceRehearsalContext(int $userId, int $portfolioId): AuthorizedPortfolioContext
{
    return AuthorizedPortfolioContext::fromValidatedOwnership(
        AuthenticatedUserContext::fromValidatedUser($userId),
        $portfolioId,
    );
}

/** @return array<string, mixed> */
function experienceRehearsalValues(string $roleTitle, bool $isCurrent, string $startMonth, string $endMonth = ''): array
{
    return [
        'experience_type' => 'internship',
        'role_title' => $roleTitle,
        'organization' => 'Example Organization',
        'location' => 'Amman',
        'start_month' => $startMonth,
        'end_month' => $endMonth,
        'is_current' => $isCurrent ? '1' : null,
        'description' => 'Plain-text rehearsal record.',
    ];
}

/** @param array<string, mixed> $post */
function experienceRehearsalAction(PDO $database, AuthorizedPortfolioContext $context, array $post): array
{
    return handleAuthorizedExperienceAction($database, $context, $post, EXPERIENCE_REHEARSAL_REFERENCE_MONTH);
}

$environment = null;
$database = null;
$transactionStarted = false;
$passed = [];
try {
    TestEnvironment::assertSafeEnvironment(getenv());
    $environment = TestEnvironment::create();
    experienceRehearsalRunPhp([PHP_BINARY, __DIR__ . '/../database/migrate.php']);
    $database = getDatabaseConnection();

    phase2AssertSame('experiences', $database->query("SELECT name FROM schema_migrations WHERE version = '007'")->fetchColumn(), 'Experience migration was not recorded.');
    $columns = $database->query(
        "SELECT column_name AS column_name, column_type AS column_type, is_nullable AS is_nullable
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'experiences'
         ORDER BY ordinal_position"
    )->fetchAll(PDO::FETCH_ASSOC);
    phase2AssertSame(
        ['id', 'portfolio_id', 'experience_type', 'role_title', 'organization', 'location', 'start_month', 'end_month', 'is_current', 'description', 'created_at', 'updated_at'],
        array_column($columns, 'column_name'),
        'Experience schema columns are incomplete or out of contract order.',
    );
    phase2AssertSame('NO', $columns[1]['is_nullable'], 'Experience portfolio ownership must be required.');
    phase2AssertSame('YES', $columns[5]['is_nullable'], 'Experience location must remain optional.');
    phase2AssertSame('YES', $columns[7]['is_nullable'], 'Experience end month must be nullable for current records.');
    $foreignKey = $database->query(
        "SELECT referenced_table_name AS referenced_table_name, update_rule AS update_rule, delete_rule AS delete_rule
         FROM information_schema.referential_constraints
         WHERE constraint_schema = DATABASE()
           AND table_name = 'experiences'
           AND constraint_name = 'fk_experiences_portfolio'"
    )->fetch(PDO::FETCH_ASSOC);
    phase2Assert(is_array($foreignKey) && $foreignKey['referenced_table_name'] === 'portfolios' && strtoupper((string) $foreignKey['update_rule']) === 'RESTRICT' && strtoupper((string) $foreignKey['delete_rule']) === 'RESTRICT', 'Experience Portfolio foreign-key lifecycle is invalid.');
    $indexColumns = $database->query(
        "SELECT column_name
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = 'experiences'
           AND index_name = 'idx_experiences_portfolio_display'
         ORDER BY seq_in_index"
    )->fetchAll(PDO::FETCH_COLUMN);
    phase2AssertSame(['portfolio_id', 'is_current', 'start_month', 'id'], $indexColumns, 'Experience display index is invalid.');
    $passed[] = 'T-EXPERIENCE-MIGRATION';

    // Runtime records are test-only and must not persist in a retained visual
    // acceptance database. MySQL DML below uses this one connection.
    $database->beginTransaction();
    $transactionStarted = true;

    $userA = experienceRehearsalCreateUser($database, 'a');
    $portfolioA = experienceRehearsalCreatePortfolio($database, $userA);
    $contextA = experienceRehearsalContext($userA, $portfolioA);
    createAuthorizedPersonalInfo($database, $contextA, ['full_name' => 'Experience A']);
    $userB = experienceRehearsalCreateUser($database, 'b');
    $portfolioB = experienceRehearsalCreatePortfolio($database, $userB);
    $contextB = experienceRehearsalContext($userB, $portfolioB);
    createAuthorizedPersonalInfo($database, $contextB, ['full_name' => 'Experience B']);

    $invalidType = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('Invalid Type', false, '2025-06', '2025-09'), 'experience_type' => 'contractor']);
    phase2Assert($invalidType['errors'] !== [], 'Unsupported Experience type was accepted by the owner action.');
    $invalidMonth = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('Invalid Month', false, '2025-13', '2025-09')]);
    phase2Assert($invalidMonth['errors'] !== [], 'Invalid Experience month was accepted by the owner action.');
    $invalidRange = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('Invalid Range', false, '2025-09', '2025-06')]);
    phase2Assert($invalidRange['errors'] !== [], 'Earlier Experience end month was accepted by the owner action.');
    $missingEnd = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('Missing End', false, '2025-06')]);
    phase2Assert($missingEnd['errors'] !== [], 'Ended Experience without an end month was accepted by the owner action.');
    $futureStart = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('Future Start', false, '2026-10', '2026-10')]);
    phase2Assert($futureStart['errors'] !== [], 'Future Experience start month was accepted by direct POST.');
    $futureEnd = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('Future End', false, '2026-08', '2026-10')]);
    phase2Assert($futureEnd['errors'] !== [], 'Future Experience end month was accepted by direct POST.');
    $malformedStart = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('Malformed Start', false, '2026/08', '2026-09')]);
    phase2Assert($malformedStart['errors'] !== [], 'Malformed Experience start month was accepted by direct POST.');
    $malformedEnd = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('Malformed End', false, '2026-08', '2026/09')]);
    phase2Assert($malformedEnd['errors'] !== [], 'Malformed Experience end month was accepted by direct POST.');
    $contradictoryCurrent = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('Contradictory Current', true, '2025-06', '2026-01')]);
    phase2Assert($contradictoryCurrent['errors'] !== [], 'Current Experience with an end month was accepted by direct POST.');
    phase2AssertSame(0, count(listAuthorizedExperiences($database, $contextA)), 'Invalid Experience creates wrote database rows.');
    $passed[] = 'T-EXPERIENCE-VALIDATION';

    $createCurrent = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('A Current', true, '2025-06')]);
    phase2AssertSame('owner_experiences.php', $createCurrent['redirect'], 'Current Experience creation did not PRG.');
    $currentRows = listAuthorizedExperiences($database, $contextA);
    phase2AssertSame(1, count($currentRows), 'Owned Experience record did not persist.');
    $currentId = (int) $currentRows[0]['id'];
    phase2AssertSame(null, $currentRows[0]['end_month'], 'Current Experience retained a stale end month.');
    phase2AssertSame('1', (string) $currentRows[0]['is_current'], 'Current Experience state was not persisted.');

    $endCurrent = experienceRehearsalAction($database, $contextA, [
        'action' => 'update',
        'id' => (string) $currentId,
        ...experienceRehearsalValues('A Current', false, '2025-06', '2026-01'),
    ]);
    phase2AssertSame('owner_experiences.php', $endCurrent['redirect'], 'Ending an Experience did not PRG.');
    phase2AssertSame('2026-01', findAuthorizedExperience($database, $contextA, $currentId)['end_month'] ?? null, 'Ending an Experience did not persist its end month.');
    $beforeInvalidUpdate = findAuthorizedExperience($database, $contextA, $currentId);
    $invalidUpdate = experienceRehearsalAction($database, $contextA, [
        'action' => 'update',
        'id' => (string) $currentId,
        ...experienceRehearsalValues('Changed by invalid update', true, '2025-06', '2027-01'),
    ]);
    phase2Assert($invalidUpdate['errors'] !== [], 'Contradictory current Experience update was accepted.');
    phase2AssertSame($beforeInvalidUpdate, findAuthorizedExperience($database, $contextA, $currentId), 'Invalid Experience update changed the persisted row.');
    $restoreCurrent = experienceRehearsalAction($database, $contextA, [
        'action' => 'update',
        'id' => (string) $currentId,
        ...experienceRehearsalValues('A Current', true, '2025-06'),
    ]);
    phase2AssertSame('owner_experiences.php', $restoreCurrent['redirect'], 'Restoring a current Experience did not PRG.');
    phase2AssertSame(null, findAuthorizedExperience($database, $contextA, $currentId)['end_month'] ?? null, 'Current-state transition left a stale end month.');

    $createEnded = experienceRehearsalAction($database, $contextA, ['action' => 'add', ...experienceRehearsalValues('A Ended', false, '2026-01', '2026-02')]);
    phase2AssertSame('owner_experiences.php', $createEnded['redirect'], 'Ended Experience creation did not PRG.');
    $endedRows = array_values(array_filter(listAuthorizedExperiences($database, $contextA), static fn (array $record): bool => $record['role_title'] === 'A Ended'));
    phase2AssertSame(1, count($endedRows), 'Ended Experience record did not persist.');
    $tieOlderId = createAuthorizedExperience($database, $contextA, experienceRehearsalValues('A Tie Older ID', false, '2024-08', '2024-09'), EXPERIENCE_REHEARSAL_REFERENCE_MONTH);
    $tieNewerId = createAuthorizedExperience($database, $contextA, experienceRehearsalValues('A Tie Newer ID', false, '2024-08', '2024-10'), EXPERIENCE_REHEARSAL_REFERENCE_MONTH);
    phase2Assert($tieNewerId > $tieOlderId, 'Experience tie-break fixture IDs were not monotonic.');
    phase2AssertSame(
        ['A Ended', 'A Current', 'A Tie Newer ID', 'A Tie Older ID'],
        array_column(listAuthorizedExperiences($database, $contextA), 'role_title'),
        'Owner Experience ordering is not start-month descending with an ID-descending tie-breaker.',
    );

    $experienceB = createAuthorizedExperience($database, $contextB, experienceRehearsalValues('B Private', false, '2024-01', '2024-06'), EXPERIENCE_REHEARSAL_REFERENCE_MONTH);
    $foreignUpdate = experienceRehearsalAction($database, $contextA, [
        'action' => 'update',
        'id' => (string) $experienceB,
        ...experienceRehearsalValues('A Overwrote B', false, '2024-01', '2024-06'),
    ]);
    phase2Assert($foreignUpdate['errors'] !== [], 'Cross-Portfolio Experience update was accepted.');
    $foreignDelete = experienceRehearsalAction($database, $contextA, ['action' => 'delete', 'id' => (string) $experienceB]);
    phase2Assert($foreignDelete['errors'] !== [], 'Cross-Portfolio Experience deletion was accepted.');
    phase2AssertSame('B Private', findAuthorizedExperience($database, $contextB, $experienceB)['role_title'] ?? null, 'Cross-Portfolio Experience mutation changed B data.');

    $deleteId = createAuthorizedExperience($database, $contextA, experienceRehearsalValues('A Delete', false, '2023-01', '2023-02'), EXPERIENCE_REHEARSAL_REFERENCE_MONTH);
    $deleteOwn = experienceRehearsalAction($database, $contextA, ['action' => 'delete', 'id' => (string) $deleteId]);
    phase2AssertSame('owner_experiences.php', $deleteOwn['redirect'], 'Owned Experience deletion did not PRG.');
    phase2AssertSame(null, findAuthorizedExperience($database, $contextA, $deleteId), 'Owned Experience deletion did not remove the record.');
    try {
        createAuthorizedExperience($database, $contextA, experienceRehearsalValues('Repository Bypass', false, '2026-10', '2026-10'), EXPERIENCE_REHEARSAL_REFERENCE_MONTH);
        phase2Assert(false, 'Repository boundary accepted a future Experience create.');
    } catch (InvalidArgumentException) {
    }
    phase2AssertSame(4, count(listAuthorizedExperiences($database, $contextA)), 'Rejected repository create changed Experience row count.');
    $passed[] = 'T-EXPERIENCE-OWNER-CRUD-AUTHORIZATION';

    $slugSuffix = bin2hex(random_bytes(5));
    setOwnedPublicSlug($database, $contextA, 'experience-a-' . $slugSuffix);
    publishOwnedPortfolio($database, $contextA);
    setOwnedPublicSlug($database, $contextB, 'experience-b-' . $slugSuffix);
    publishOwnedPortfolio($database, $contextB);
    $publicA = resolvePublicReadContext($database, 'experience-a-' . $slugSuffix);
    $publicB = resolvePublicReadContext($database, 'experience-b-' . $slugSuffix);
    phase2Assert($publicA instanceof PublicReadContext && $publicB instanceof PublicReadContext, 'Published Experience fixture Portfolios did not resolve publicly.');
    $publicARecords = listPublicExperiences($database, $publicA);
    phase2AssertSame(['A Ended', 'A Current', 'A Tie Newer ID', 'A Tie Older ID'], array_column($publicARecords, 'role_title'), 'Public Experience ordering or Portfolio scoping is incorrect.');
    phase2AssertSame(array_column(listAuthorizedExperiences($database, $contextA), 'role_title'), array_column($publicARecords, 'role_title'), 'Owner Preview and Public Experience ordering diverged.');
    phase2AssertSame(array_column($publicARecords, 'role_title'), array_column(listPublicExperiences($database, $publicA), 'role_title'), 'Repeated Public Experience reads were not deterministic.');
    phase2AssertSame(['B Private'], array_column(listPublicExperiences($database, $publicB), 'role_title'), 'Public Experience query leaked A rows to B.');
    phase2AssertSame(['experience_type', 'role_title', 'organization', 'location', 'start_month', 'end_month', 'is_current', 'description'], array_keys($publicARecords[0]), 'Public Experience shape exposed storage metadata.');
    $userEmpty = experienceRehearsalCreateUser($database, 'empty');
    $portfolioEmpty = experienceRehearsalCreatePortfolio($database, $userEmpty);
    phase2AssertSame([], listPublicExperiences($database, PublicReadContext::fromPublishedPortfolio($portfolioEmpty)), 'Empty Portfolio did not produce an empty Experience collection.');
    $passed[] = 'T-EXPERIENCE-PUBLIC-READ';
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL Experience rehearsal: {$exception->getMessage()}\n");
    exit(1);
} finally {
    if ($transactionStarted && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    if ($environment instanceof TestEnvironment) {
        try {
            $environment->tearDown();
        } catch (Throwable $exception) {
            fwrite(STDERR, "FAIL Experience rehearsal teardown: {$exception->getMessage()}\n");
            exit(1);
        }
    }
}

foreach ($passed as $name) {
    echo "PASS {$name}\n";
}
echo "PASS Experience rehearsal\n";
