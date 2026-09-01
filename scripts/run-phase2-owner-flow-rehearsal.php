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
require_once __DIR__ . '/../includes/owner_flow.php';
require_once __DIR__ . '/../includes/owner_actions.php';
require_once __DIR__ . '/../includes/portfolio_scoped_data.php';

const OWNER_FLOW_TEST_ISSUER = 'https://issuer.test/ather-career';

function ownerFlowCreateUser(PDO $database, string $label, int $authzVersion = 1): int
{
    $statement = $database->prepare(
        'INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
         VALUES (:issuer, :subject, :account_status, :authz_version)'
    );
    $statement->execute([
        'issuer' => OWNER_FLOW_TEST_ISSUER,
        'subject' => 'p2j04-' . $label . '-' . bin2hex(random_bytes(8)),
        'account_status' => 'active',
        'authz_version' => $authzVersion,
    ]);

    return (int) $database->lastInsertId();
}

function ownerFlowCreatePortfolio(PDO $database, int $userId): int
{
    $statement = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:owner_user_id)');
    $statement->execute(['owner_user_id' => $userId]);

    return (int) $database->lastInsertId();
}

/** @return array<string, list<array<string, mixed>>> */
function ownerFlowSnapshot(PDO $database, int $portfolioId): array
{
    $snapshot = [];
    foreach ([
        'personal_info' => 'SELECT id, full_name, profile_image_path FROM personal_info WHERE portfolio_id = :portfolio_id ORDER BY id',
        'skills' => 'SELECT id, skill_name FROM skills WHERE portfolio_id = :portfolio_id ORDER BY id',
        'projects' => 'SELECT id, title, category, description, github_url, image_path FROM projects WHERE portfolio_id = :portfolio_id ORDER BY id',
        'messages' => 'SELECT id, name, email, message FROM messages WHERE recipient_portfolio_id = :portfolio_id ORDER BY id',
    ] as $resource => $query) {
        $statement = $database->prepare($query);
        $statement->execute(['portfolio_id' => $portfolioId]);
        $snapshot[$resource] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    return $snapshot;
}

/** @return array<string, string> */
function ownerFlowUploadSnapshot(string $uploadsRoot): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $hash = hash_file('sha256', $file->getPathname());
            if ($hash === false) {
                throw new RuntimeException('Could not snapshot a test upload.');
            }
            $files[str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($uploadsRoot) + 1))] = $hash;
        }
    }
    ksort($files);

    return $files;
}

function ownerFlowEstablishSession(int $userId, int $authzVersion): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        destroyInternalUserSession();
    }
    session_name('phase2_owner_flow');
    startApplicationSession();
    establishVerifiedInternalUserSession($userId, $authzVersion);
}

/** @return list<int> */
function ownerFlowRunPortfolioRace(int $userId): array
{
    $worker = __DIR__ . '/run-phase2-ownership-race-worker.php';
    $processes = [];
    for ($index = 0; $index < 2; $index++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, $worker, 'owner_portfolio', (string) $userId], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the Portfolio-create race worker.');
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

/** @return array<string, string> */
function ownerFlowProfileValues(string $fullName): array
{
    return [
        'full_name' => $fullName,
        'professional_title' => 'Owner Tester',
        'email' => strtolower(str_replace(' ', '.', $fullName)) . '@example.test',
        'phone_primary' => '',
        'phone_secondary' => '',
        'location' => 'Amman',
        'about_me' => 'Private owner profile.',
        'work_description' => '',
        'linkedin_url' => '',
        'github_url' => 'https://example.test/' . rawurlencode(strtolower(str_replace(' ', '-', $fullName))),
        'instagram_url' => '',
        'facebook_url' => '',
        'website_url' => '',
    ];
}

$environment = null;
$bImagePath = null;
$passed = [];
try {
    TestEnvironment::assertSafeEnvironment(getenv());
    $environment = TestEnvironment::create();
    $database = getDatabaseConnection();
    phase2AssertSame('ownership_contract', $database->query("SELECT name FROM schema_migrations WHERE version = '004'")->fetchColumn(), 'P2J-04 rehearsal requires the P2J-02 ownership contract.');

    $userA = ownerFlowCreateUser($database, 'user-a', 7);
    $portfolioA = ownerFlowCreatePortfolio($database, $userA);
    $userB = ownerFlowCreateUser($database, 'user-b', 9);
    $portfolioB = ownerFlowCreatePortfolio($database, $userB);
    $userNoPortfolio = ownerFlowCreateUser($database, 'user-no-portfolio', 3);

    ownerFlowEstablishSession($userNoPortfolio, 3);
    phase2AssertSame($userNoPortfolio, requireAuthenticatedUser($database)->userId, 'No-Portfolio User did not retain authenticated account context.');
    phase2AssertSame(false, ownerHasPortfolio($database, requireAuthenticatedUser($database)), 'No-Portfolio User was treated as an owner.');
    try {
        requireOwnedPortfolioContext($database);
        throw new RuntimeException('No-Portfolio User received management context.');
    } catch (AuthorizationDeniedException) {
    }
    $createdPortfolio = createOwnedPortfolio($database, requireAuthenticatedUser($database));
    phase2Assert($createdPortfolio !== null && $createdPortfolio > 0, 'Onboarding did not create a server-owned Portfolio.');
    phase2AssertSame($createdPortfolio, requireOwnedPortfolioContext($database)->portfolioId, 'Created Portfolio was not derived from the authenticated User.');
    phase2AssertSame(null, createOwnedPortfolio($database, requireAuthenticatedUser($database)), 'Onboarding created a second Portfolio for one User.');

    $raceUser = ownerFlowCreateUser($database, 'portfolio-race', 5);
    phase2AssertSame([0, 0], ownerFlowRunPortfolioRace($raceUser), 'Concurrent Portfolio creation did not resolve safely.');
    $raceCount = $database->prepare('SELECT COUNT(*) FROM portfolios WHERE owner_user_id = :owner_user_id');
    $raceCount->execute(['owner_user_id' => $raceUser]);
    phase2AssertSame(1, (int) $raceCount->fetchColumn(), 'Concurrent Portfolio creation produced more than one Portfolio.');
    $passed[] = 'T-OWNER-ONBOARDING';

    ownerFlowEstablishSession($userA, 7);
    $_GET = ['portfolio_id' => (string) $portfolioB, 'slug' => 'foreign'];
    $_POST = ['portfolio_id' => (string) $portfolioB, 'owner_user_id' => (string) $userB];
    $_COOKIE = ['portfolio_id' => (string) $portfolioB];
    $_SERVER['HTTP_X_PORTFOLIO_ID'] = (string) $portfolioB;
    $contextA = requireOwnedPortfolioContext($database);
    phase2AssertSame($portfolioA, $contextA->portfolioId, 'Client tenant candidates changed A owner context.');

    $profileA = createAuthorizedPersonalInfo($database, $contextA, ownerFlowProfileValues('Private A'));
    $skillA = createAuthorizedSkill($database, $contextA, 'A Skill');
    $projectA = createAuthorizedProject($database, $contextA, 'A Project', 'Owner Test', 'A private project.', 'https://example.test/a-project', null);
    $messageA = $database->prepare('INSERT INTO messages (recipient_portfolio_id, name, email, message) VALUES (:portfolio_id, :name, :email, :message)');
    $messageA->execute(['portfolio_id' => $portfolioA, 'name' => 'Sender A', 'email' => 'sender.a@example.test', 'message' => 'A private message.']);
    $messageAId = (int) $database->lastInsertId();

    ownerFlowEstablishSession($userB, 9);
    $contextB = requireOwnedPortfolioContext($database);
    $profileB = createAuthorizedPersonalInfo($database, $contextB, ownerFlowProfileValues('Private B'));
    $skillB = createAuthorizedSkill($database, $contextB, 'B Skill');
    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    if ($uploadsRoot === false) {
        throw new RuntimeException('Test uploads root is unavailable.');
    }
    $bImageFilename = 'p2j04-b-' . bin2hex(random_bytes(8)) . '.bin';
    $bImagePath = $uploadsRoot . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $bImageFilename;
    if (file_put_contents($bImagePath, 'B private image', LOCK_EX) === false) {
        throw new RuntimeException('Could not create B project image fixture.');
    }
    $projectB = createAuthorizedProject($database, $contextB, 'B Project', 'Owner Test', 'B private project.', 'https://example.test/b-project', 'uploads/projects/' . $bImageFilename);
    $messageB = $database->prepare('INSERT INTO messages (recipient_portfolio_id, name, email, message) VALUES (:portfolio_id, :name, :email, :message)');
    $messageB->execute(['portfolio_id' => $portfolioB, 'name' => 'Sender B', 'email' => 'sender.b@example.test', 'message' => 'B private message.']);
    $messageBId = (int) $database->lastInsertId();

    ownerFlowEstablishSession($userA, 7);
    $contextA = requireOwnedPortfolioContext($database);
    $currentA = loadAuthorizedPersonalInfo($database, $contextA);
    phase2Assert(is_array($currentA), 'A profile is unavailable for owner route rehearsal.');
    $bBefore = ownerFlowSnapshot($database, $portfolioB);
    $filesystemBefore = ownerFlowUploadSnapshot($uploadsRoot);

    $foreignProfile = handleAuthorizedProfileAction($database, $contextA, [
        'action' => 'save_profile', 'profile_id' => (string) $profileB, 'portfolio_id' => (string) $portfolioB,
        ...ownerFlowProfileValues('Attempted B Profile'),
    ], [], $currentA, array_keys(ownerFlowProfileValues('')), ownerFlowProfileValues('Private A'));
    phase2Assert($foreignProfile['errors'] !== [], 'A foreign profile ID was accepted by an owner route action.');

    $ownProfile = handleAuthorizedProfileAction($database, $contextA, [
        'action' => 'save_profile', 'profile_id' => (string) $profileA, 'portfolio_id' => (string) $portfolioB,
        ...ownerFlowProfileValues('A Updated Only'),
    ], [], $currentA, array_keys(ownerFlowProfileValues('')), ownerFlowProfileValues('Private A'));
    phase2AssertSame('owner_profile.php?saved=1', $ownProfile['redirect'], 'Profile save did not PRG.');

    $createSkill = handleAuthorizedProfileAction($database, $contextA, ['action' => 'add_skill', 'skill_name' => 'A Added Skill', 'portfolio_id' => (string) $portfolioB], [], $currentA, array_keys(ownerFlowProfileValues('')), ownerFlowProfileValues('A Updated Only'));
    phase2AssertSame('owner_profile.php?skill_added=1', $createSkill['redirect'], 'Skill creation did not PRG.');
    $foreignSkillUpdate = handleAuthorizedProfileAction($database, $contextA, ['action' => 'update_skill', 'skill_id' => (string) $skillB, 'skill_name' => 'A Overwrote B'], [], $currentA, array_keys(ownerFlowProfileValues('')), ownerFlowProfileValues('A Updated Only'));
    phase2Assert($foreignSkillUpdate['errors'] !== [], 'A foreign skill update was accepted.');
    $foreignSkillDelete = handleAuthorizedProfileAction($database, $contextA, ['action' => 'delete_skill', 'skill_id' => (string) $skillB], [], $currentA, array_keys(ownerFlowProfileValues('')), ownerFlowProfileValues('A Updated Only'));
    phase2Assert($foreignSkillDelete['errors'] !== [], 'A foreign skill delete was accepted.');
    $ownSkillUpdate = handleAuthorizedProfileAction($database, $contextA, ['action' => 'update_skill', 'skill_id' => (string) $skillA, 'skill_name' => 'A Skill Updated'], [], $currentA, array_keys(ownerFlowProfileValues('')), ownerFlowProfileValues('A Updated Only'));
    phase2AssertSame('owner_profile.php?skill_updated=1', $ownSkillUpdate['redirect'], 'Skill update did not PRG.');

    $foreignProject = handleAuthorizedProjectAction($database, $contextA, [
        'action' => 'update', 'id' => (string) $projectB, 'portfolio_id' => (string) $portfolioB,
        'title' => 'A Overwrote B', 'category' => 'Owner Test', 'description' => 'Foreign update.', 'github_url' => 'https://example.test/foreign',
    ], []);
    phase2Assert($foreignProject['errors'] !== [], 'A foreign project update was accepted.');
    $foreignProjectDelete = handleAuthorizedProjectAction($database, $contextA, ['action' => 'delete', 'id' => (string) $projectB], []);
    phase2Assert($foreignProjectDelete['errors'] !== [], 'A foreign project delete was accepted.');
    $ownProject = handleAuthorizedProjectAction($database, $contextA, [
        'action' => 'update', 'id' => (string) $projectA, 'portfolio_id' => (string) $portfolioB,
        'title' => 'A Project Updated', 'category' => 'Owner Test', 'description' => 'A update only.', 'github_url' => 'https://example.test/a-project-updated',
    ], []);
    phase2AssertSame('owner_projects.php', $ownProject['redirect'], 'Project update did not PRG.');
    $createProject = handleAuthorizedProjectAction($database, $contextA, [
        'action' => 'add', 'portfolio_id' => (string) $portfolioB,
        'title' => 'A Created Project', 'category' => 'Owner Test', 'description' => 'Created for A.', 'github_url' => 'https://example.test/a-created',
    ], []);
    phase2AssertSame('owner_projects.php', $createProject['redirect'], 'Project creation did not PRG.');

    phase2AssertSame(null, findAuthorizedMessage($database, $contextA, $messageBId), 'A read B message through an owner route candidate.');
    phase2AssertSame(null, findAuthorizedPersonalInfo($database, $contextA, $profileB), 'A read B profile.');
    phase2AssertSame(null, findAuthorizedProject($database, $contextA, $projectB), 'A read B project.');
    phase2AssertSame($bBefore, ownerFlowSnapshot($database, $portfolioB), 'A owner route attempt changed B database rows.');
    phase2AssertSame($filesystemBefore, ownerFlowUploadSnapshot($uploadsRoot), 'A owner route attempt changed B filesystem state.');
    phase2AssertSame('B private image', file_get_contents($bImagePath), 'A owner route attempt changed B project image bytes.');
    $passed[] = 'T-OWNER-XTENANT A_TO_B';

    ownerFlowEstablishSession($userB, 9);
    $contextB = requireOwnedPortfolioContext($database);
    $aBefore = ownerFlowSnapshot($database, $portfolioA);
    $aFilesystemBefore = ownerFlowUploadSnapshot($uploadsRoot);
    phase2AssertSame(null, findAuthorizedPersonalInfo($database, $contextB, $profileA), 'B read A profile.');
    phase2AssertSame(false, updateAuthorizedSkill($database, $contextB, $skillA, 'B Overwrote A'), 'B updated A skill.');
    phase2AssertSame(false, deleteAuthorizedProject($database, $contextB, $projectA), 'B deleted A project.');
    phase2AssertSame(null, findAuthorizedMessage($database, $contextB, $messageAId), 'B message candidate bypassed scope.');
    phase2AssertSame($aBefore, ownerFlowSnapshot($database, $portfolioA), 'B owner route attempt changed A database rows.');
    phase2AssertSame($aFilesystemBefore, ownerFlowUploadSnapshot($uploadsRoot), 'B owner route attempt changed A filesystem state.');
    $passed[] = 'T-OWNER-XTENANT B_TO_A';

    ownerFlowEstablishSession($userA, 7);
    $contextA = requireOwnedPortfolioContext($database);
    $aggregate = authorizedPortfolioDashboardAggregate($database, $contextA);
    phase2AssertSame(2, $aggregate['project_count'], 'A dashboard count included foreign projects.');
    phase2AssertSame(2, $aggregate['skill_count'], 'A dashboard count included foreign skills.');
    phase2AssertSame(1, $aggregate['message_count'], 'A dashboard count included foreign messages.');
    phase2AssertSame([$profileA], array_map(static fn (array $row): int => (int) $row['id'], array_filter([loadAuthorizedPersonalInfo($database, $contextA)])), 'A profile collection was not scoped.');
    $passed[] = 'T-OWNER-COLLECT';
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL owner flow rehearsal: {$exception->getMessage()}\n");
    exit(1);
} finally {
    if (is_string($bImagePath) && is_file($bImagePath) && !unlink($bImagePath)) {
        fwrite(STDERR, "FAIL owner flow fixture cleanup: could not remove B image.\n");
        exit(1);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        destroyInternalUserSession();
    }
    if ($environment instanceof TestEnvironment) {
        try {
            $environment->tearDown();
        } catch (Throwable $exception) {
            fwrite(STDERR, "FAIL owner flow teardown: {$exception->getMessage()}\n");
            exit(1);
        }
    }
}

foreach ($passed as $name) {
    echo "PASS {$name}\n";
}
echo "PASS owner flow rehearsal\n";
