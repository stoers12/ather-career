<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';

const PUBLIC_LIFECYCLE_TEST_ISSUER = 'https://issuer.test/ather-career';

function publicLifecycleRunPhp(array $arguments): string
{
    $pipes = [];
    $process = proc_open($arguments, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start a public lifecycle test process.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException('Public lifecycle child process failed: ' . trim((string) $stderr));
    }

    return (string) $stdout;
}

function publicLifecycleCreateUser(PDO $database, string $label, int $authzVersion): int
{
    $statement = $database->prepare(
        'INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
         VALUES (:issuer, :subject, \'active\', :authz_version)'
    );
    $statement->execute([
        'issuer' => PUBLIC_LIFECYCLE_TEST_ISSUER,
        'subject' => 'p2j05-' . $label . '-' . bin2hex(random_bytes(8)),
        'authz_version' => $authzVersion,
    ]);

    return (int) $database->lastInsertId();
}

function publicLifecycleCreatePortfolio(PDO $database, int $userId): int
{
    $statement = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:owner_user_id)');
    $statement->execute(['owner_user_id' => $userId]);

    return (int) $database->lastInsertId();
}

function publicLifecycleContext(int $userId, int $portfolioId): AuthorizedPortfolioContext
{
    return AuthorizedPortfolioContext::fromValidatedOwnership(
        AuthenticatedUserContext::fromValidatedUser($userId),
        $portfolioId,
    );
}

function publicLifecycleSeedProfile(PDO $database, int $portfolioId, string $name): void
{
    $statement = $database->prepare(
        'INSERT INTO personal_info (portfolio_id, full_name, professional_title, about_me)
         VALUES (:portfolio_id, :full_name, :professional_title, :about_me)'
    );
    $statement->execute([
        'portfolio_id' => $portfolioId,
        'full_name' => $name,
        'professional_title' => 'Portfolio Tester',
        'about_me' => $name . ' profile',
    ]);
}

function publicLifecycleSeedSkill(PDO $database, int $portfolioId, string $name): void
{
    $statement = $database->prepare('INSERT INTO skills (portfolio_id, skill_name) VALUES (:portfolio_id, :skill_name)');
    $statement->execute(['portfolio_id' => $portfolioId, 'skill_name' => $name]);
}

function publicLifecycleSeedProject(PDO $database, int $portfolioId, string $title): void
{
    $statement = $database->prepare(
        'INSERT INTO projects (portfolio_id, title, category, description, github_url)
         VALUES (:portfolio_id, :title, :category, :description, :github_url)'
    );
    $statement->execute([
        'portfolio_id' => $portfolioId,
        'title' => $title,
        'category' => 'Testing',
        'description' => $title . ' description',
        'github_url' => 'https://example.test/' . rawurlencode(strtolower(str_replace(' ', '-', $title))),
    ]);
}

/** @return list<int> */
function publicLifecycleRunSlugRace(int $userA, int $portfolioA, int $userB, int $portfolioB, string $slug): array
{
    $worker = __DIR__ . '/run-phase2-public-slug-race-worker.php';
    $processes = [];
    foreach ([[$userA, $portfolioA], [$userB, $portfolioB]] as [$userId, $portfolioId]) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, $worker, (string) $userId, (string) $portfolioId, $slug], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a public slug race worker.');
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

$environment = null;
$passed = [];
try {
    TestEnvironment::assertSafeEnvironment(getenv());
    $environment = TestEnvironment::create();
    $database = getDatabaseConnection();
    phase2AssertSame('ownership_contract', $database->query("SELECT name FROM schema_migrations WHERE version = '004'")->fetchColumn(), 'P2J-05 rehearsal requires the P2J-02 ownership contract.');

    publicLifecycleRunPhp([PHP_BINARY, __DIR__ . '/../database/migrate.php']);
    publicLifecycleRunPhp([PHP_BINARY, __DIR__ . '/../database/migrate.php']);
    phase2AssertSame('public_lifecycle', $database->query("SELECT name FROM schema_migrations WHERE version = '005'")->fetchColumn(), 'Migration C was not recorded.');
    $columns = $database->query("SELECT column_name AS column_name, column_type AS column_type, is_nullable AS is_nullable, column_default AS column_default FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'portfolios' AND column_name IN ('public_slug', 'is_published', 'published_at') ORDER BY column_name")->fetchAll(PDO::FETCH_ASSOC);
    phase2AssertSame(['is_published', 'public_slug', 'published_at'], array_column($columns, 'column_name'), 'Migration C columns are incomplete.');
    phase2AssertSame('tinyint(1)', strtolower((string) $columns[0]['column_type']), 'Migration C is_published type is invalid.');
    phase2AssertSame('0', (string) $columns[0]['column_default'], 'Migration C is_published default is invalid.');
    phase2AssertSame('YES', $columns[1]['is_nullable'], 'Migration C public_slug must be nullable before claim.');
    $index = $database->query("SELECT non_unique FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'portfolios' AND index_name = 'uq_portfolios_public_slug'")->fetchColumn();
    phase2AssertSame('0', (string) $index, 'Migration C public_slug unique constraint is missing.');
    $passed[] = 'T-PUBLIC-MIGRATION';

    phase2AssertSame('a-public', normalizePublicSlug(' A-Public '), 'Slug normalization did not lowercase and trim.');
    foreach (['ab', str_repeat('a', 65), '-bad', 'bad-', 'bad--slug', 'bad_slug', 'äbc'] as $invalid) {
        phase2AssertSame(null, normalizePublicSlug($invalid), 'Invalid slug was accepted: ' . $invalid);
    }
    foreach (PUBLIC_SLUG_RESERVED as $reserved) {
        try {
            requirePublicSlug($reserved);
            throw new RuntimeException('Reserved public slug was accepted: ' . $reserved);
        } catch (PublicLifecycleValidationException) {
        }
    }

    $userA = publicLifecycleCreateUser($database, 'a', 7);
    $portfolioA = publicLifecycleCreatePortfolio($database, $userA);
    $contextA = publicLifecycleContext($userA, $portfolioA);
    publicLifecycleSeedProfile($database, $portfolioA, 'Public A');
    publicLifecycleSeedSkill($database, $portfolioA, 'A Skill');
    publicLifecycleSeedProject($database, $portfolioA, 'A Project');
    $userB = publicLifecycleCreateUser($database, 'b', 9);
    $portfolioB = publicLifecycleCreatePortfolio($database, $userB);
    $contextB = publicLifecycleContext($userB, $portfolioB);
    publicLifecycleSeedProfile($database, $portfolioB, 'Public B');
    publicLifecycleSeedSkill($database, $portfolioB, 'B Skill');
    publicLifecycleSeedProject($database, $portfolioB, 'B Project');

    phase2AssertSame('a-draft', setOwnedPublicSlug($database, $contextA, 'a-draft'), 'Never-published Portfolio could not claim a slug.');
    phase2AssertSame('a-public', setOwnedPublicSlug($database, $contextA, 'A-Public'), 'Never-published Portfolio could change its slug.');
    phase2AssertSame('b-unpublished', setOwnedPublicSlug($database, $contextB, 'b-unpublished'), 'B could not claim a draft slug.');
    $passed[] = 'T-PUBLIC-SLUG';

    phase2AssertSame(null, resolvePublicReadContext($database, 'a-public'), 'Unpublished A was publicly available.');
    phase2AssertSame(null, resolvePublicReadContext($database, 'missing-slug'), 'Unknown slug was publicly available.');
    publishOwnedPortfolio($database, $contextA);
    $stateA = ownedPublicLifecycleState($database, $contextA);
    phase2AssertSame(1, $stateA['is_published'], 'A was not published.');
    phase2Assert(is_string($stateA['published_at']) && $stateA['published_at'] !== '', 'First publication did not set published_at.');
    $firstPublishedAt = $stateA['published_at'];
    $publicA = resolvePublicReadContext($database, 'a-public');
    phase2AssertSame($portfolioA, $publicA?->portfolioId, 'Published A did not resolve to its Portfolio.');
    phase2AssertSame(['A Skill'], array_column(listPublicSkills($database, $publicA), 'skill_name'), 'A public skills leaked B rows.');
    phase2AssertSame(['A Project'], array_column(listPublicProjects($database, $publicA), 'title'), 'A public projects leaked B rows.');
    phase2AssertSame('Public A', (string) loadPublicPersonalInfo($database, $publicA)['full_name'], 'A public profile leaked B data.');
    phase2AssertSame(null, resolvePublicReadContext($database, 'b-unpublished'), 'Unpublished B was publicly available.');

    phase2AssertSame($portfolioA, publicLifecycleContext($userA, $portfolioA)->portfolioId, 'A owner context changed while reading public data.');
    setOwnedPublicSlug($database, $contextB, 'b-public');
    publishOwnedPortfolio($database, $contextB);
    $publicB = resolvePublicReadContext($database, 'b-public');
    phase2AssertSame($portfolioB, $publicB?->portfolioId, 'Published B did not resolve to its Portfolio.');
    phase2AssertSame(['B Project'], array_column(listPublicProjects($database, $publicB), 'title'), 'B public JSON source leaked A projects.');
    session_name('phase2_public_lifecycle');
    startApplicationSession();
    establishVerifiedInternalUserSession($userA, 7);
    phase2AssertSame($portfolioA, requireOwnedPortfolioContext($database)->portfolioId, 'A did not retain its owner context while viewing B publicly.');
    phase2AssertSame($portfolioB, resolvePublicReadContext($database, 'b-public')?->portfolioId, 'A could not receive the separately-derived public view of B.');
    phase2AssertSame($portfolioA, requireOwnedPortfolioContext($database)->portfolioId, 'A gained B owner authority through B public slug.');
    $passed[] = 'T-PUBLIC-READ';

    unpublishOwnedPortfolio($database, $contextA);
    $afterUnpublish = ownedPublicLifecycleState($database, $contextA);
    phase2AssertSame(0, $afterUnpublish['is_published'], 'Unpublish did not hide A.');
    phase2AssertSame($firstPublishedAt, $afterUnpublish['published_at'], 'Unpublish cleared or changed published_at.');
    try {
        setOwnedPublicSlug($database, $contextA, 'a-renamed');
        throw new RuntimeException('A slug changed after first publication.');
    } catch (PublicLifecycleConflictException) {
    }
    publishOwnedPortfolio($database, $contextA);
    phase2AssertSame($firstPublishedAt, ownedPublicLifecycleState($database, $contextA)['published_at'], 'Republish changed the first publication timestamp.');
    $database->prepare("UPDATE users SET account_status = 'disabled' WHERE id = :id")->execute(['id' => $userB]);
    phase2AssertSame(null, resolvePublicReadContext($database, 'b-public'), 'Disabled owner remained publicly available.');
    $passed[] = 'T-PUBLIC-LIFECYCLE';

    $userRaceA = publicLifecycleCreateUser($database, 'race-a', 1);
    $portfolioRaceA = publicLifecycleCreatePortfolio($database, $userRaceA);
    $userRaceB = publicLifecycleCreateUser($database, 'race-b', 1);
    $portfolioRaceB = publicLifecycleCreatePortfolio($database, $userRaceB);
    phase2AssertSame([0, 1], publicLifecycleRunSlugRace($userRaceA, $portfolioRaceA, $userRaceB, $portfolioRaceB, 'same-public-slug'), 'Concurrent public slug claim did not produce exactly one winner.');
    $claim = $database->prepare('SELECT COUNT(*) FROM portfolios WHERE public_slug = :public_slug');
    $claim->execute(['public_slug' => 'same-public-slug']);
    phase2AssertSame(1, (int) $claim->fetchColumn(), 'Unique public slug race did not leave exactly one authoritative winner.');
    $passed[] = 'T-PUBLIC-RACE';
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL public lifecycle rehearsal: {$exception->getMessage()}\n");
    exit(1);
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        destroyInternalUserSession();
    }
    if ($environment instanceof TestEnvironment) {
        try {
            $environment->tearDown();
        } catch (Throwable $exception) {
            fwrite(STDERR, "FAIL public lifecycle test namespace teardown: {$exception->getMessage()}\n");
            exit(1);
        }
    }
}

foreach ($passed as $name) {
    echo "PASS {$name}\n";
}
echo "PASS public lifecycle rehearsal\n";
