<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../tests/Legacy/bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/authorization.php';
require_once __DIR__ . '/../../includes/owner_flow.php';
require_once __DIR__ . '/../../includes/owner_actions.php';
require_once __DIR__ . '/../../includes/portfolio_scoped_data.php';

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

    $id = (int) $database->lastInsertId();
    journeyFixtureLegacyOwner($database, $userId);
    return $id;
}

/** @return array<string, list<array<string, mixed>>> */
function ownerFlowSnapshot(PDO $database, int $portfolioId): array
{
    $snapshot = [];
    foreach ([
        'personal_info' => 'SELECT id, full_name, profile_image_path FROM personal_info WHERE portfolio_id = :portfolio_id ORDER BY id',
        'skills' => 'SELECT id, skill_name FROM skills WHERE portfolio_id = :portfolio_id ORDER BY id',
        'projects' => 'SELECT id, title, category, description, github_url, live_url, image_path FROM projects WHERE portfolio_id = :portfolio_id ORDER BY id',
        'messages' => 'SELECT id, name, email, message, read_at FROM messages WHERE recipient_portfolio_id = :portfolio_id ORDER BY id',
    ] as $resource => $query) {
        $statement = $database->prepare($query);
        $statement->execute(['portfolio_id' => $portfolioId]);
        $snapshot[$resource] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    return $snapshot;
}

function ownerFlowAssertRepositoryUploadsAbsent(): void
{
    $repositoryRoot = realpath(__DIR__ . '/../..');
    if ($repositoryRoot === false) {
        throw new RuntimeException('Cannot resolve the repository root for the uploads absence check.');
    }
    foreach (array_unique([$repositoryRoot, '/var/www/app']) as $root) {
        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new RuntimeException('Cannot resolve an application root for the uploads absence check.');
        }
        // Listing the parent rejects every entry, including a broken symlink;
        // a failed lookup must not be mistaken for an absent path.
        $entries = @scandir($resolvedRoot);
        if ($entries === false) {
            throw new RuntimeException('Cannot inspect an application root for the uploads absence check.');
        }
        phase2Assert(!in_array('uploads', $entries, true), 'Repository uploads path must remain absent.');
    }
}

/** @return array<string, string> */
function ownerFlowUploadSnapshot(string $legacyFixtureRoot): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($legacyFixtureRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $hash = hash_file('sha256', $file->getPathname());
            if ($hash === false) {
                throw new RuntimeException('Could not snapshot a test upload.');
            }
            $files[str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($legacyFixtureRoot) + 1))] = $hash;
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
    ownerFlowAssertRepositoryUploadsAbsent();
    $database = getDatabaseConnection();
    phase2AssertSame('ownership_contract', $database->query("SELECT name FROM schema_migrations WHERE version = '004'")->fetchColumn(), 'P2J-04 rehearsal requires the P2J-02 ownership contract.');

    $userA = ownerFlowCreateUser($database, 'user-a', 7);
    $portfolioA = ownerFlowCreatePortfolio($database, $userA);
    $userB = ownerFlowCreateUser($database, 'user-b', 9);
    $portfolioB = ownerFlowCreatePortfolio($database, $userB);
    $userNoPortfolio = ownerFlowCreateUser($database, 'user-no-portfolio', 3);

    ownerFlowEstablishSession($userNoPortfolio, 3);
    recordOwnerReadinessObservation($userNoPortfolio, 3, true, true);
    phase2AssertSame($userNoPortfolio, requireAuthenticatedUser($database)->userId, 'No-Portfolio User did not retain authenticated account context.');
    phase2AssertSame(false, ownerHasPortfolio($database, requireAuthenticatedUser($database)), 'No-Portfolio User was treated as an owner.');
    try {
        requireOwnedPortfolioContext($database);
        throw new RuntimeException('No-Portfolio User received management context.');
    } catch (AuthorizationDeniedException) {
    }
    try { createOwnedPortfolio($database, requireAuthenticatedUser($database)); throw new RuntimeException('Retired helper created a Portfolio.'); } catch (OwnerReadinessDeniedException) {}
    phase2AssertSame(false, ownerHasPortfolio($database, requireAuthenticatedUser($database)), 'Retired helper wrote a bare Portfolio.');

    $raceUser = ownerFlowCreateUser($database, 'portfolio-race', 5);
    phase2AssertSame([1, 1], ownerFlowRunPortfolioRace($raceUser), 'Concurrent Portfolio creation did not resolve safely.');
    $raceCount = $database->prepare('SELECT COUNT(*) FROM portfolios WHERE owner_user_id = :owner_user_id');
    $raceCount->execute(['owner_user_id' => $raceUser]);
    phase2AssertSame(0, (int) $raceCount->fetchColumn(), 'Concurrent retired creation created a Portfolio.');
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
    $projectA = createAuthorizedProject($database, $contextA, 'A Project', 'Owner Test', 'A private project.', 'https://example.test/a-project', null, []);
    $messageA = $database->prepare('INSERT INTO messages (recipient_portfolio_id, name, email, message) VALUES (:portfolio_id, :name, :email, :message)');
    $messageA->execute(['portfolio_id' => $portfolioA, 'name' => 'Sender A', 'email' => 'sender.a@example.test', 'message' => 'A private message.']);
    $messageAId = (int) $database->lastInsertId();

    ownerFlowEstablishSession($userB, 9);
    $contextB = requireOwnedPortfolioContext($database);
    $profileB = createAuthorizedPersonalInfo($database, $contextB, ownerFlowProfileValues('Private B'));
    $skillB = createAuthorizedSkill($database, $contextB, 'B Skill');
    $legacyFixtureDirectory = $environment->namespaceRoot . '/legacy-uploads';
    if (!mkdir($legacyFixtureDirectory . '/projects', 0700, true)) {
        throw new RuntimeException('Could not create the disposable legacy fixture root.');
    }
    $legacyFixtureRoot = realpath($legacyFixtureDirectory);
    if ($legacyFixtureRoot === false) {
        throw new RuntimeException('Test legacy fixture root is unavailable.');
    }
    $bImageFilename = 'p2j04-b-' . bin2hex(random_bytes(8)) . '.bin';
    $bImagePath = $legacyFixtureRoot . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $bImageFilename;
    if (file_put_contents($bImagePath, 'B private image', LOCK_EX) === false) {
        throw new RuntimeException('Could not create B project image fixture.');
    }
    $projectB = createAuthorizedProject($database, $contextB, 'B Project', 'Owner Test', 'B private project.', 'https://example.test/b-project', 'uploads/projects/' . $bImageFilename, []);
    $messageB = $database->prepare('INSERT INTO messages (recipient_portfolio_id, name, email, message) VALUES (:portfolio_id, :name, :email, :message)');
    $messageB->execute(['portfolio_id' => $portfolioB, 'name' => 'Sender B', 'email' => 'sender.b@example.test', 'message' => 'B private message.']);
    $messageBId = (int) $database->lastInsertId();

    ownerFlowEstablishSession($userA, 7);
    $contextA = requireOwnedPortfolioContext($database);
    $currentA = loadAuthorizedPersonalInfo($database, $contextA);
    phase2Assert(is_array($currentA), 'A profile is unavailable for owner route rehearsal.');
    $bBefore = ownerFlowSnapshot($database, $portfolioB);
    $filesystemBefore = ownerFlowUploadSnapshot($legacyFixtureRoot);

    $foreignProfile = handleAuthorizedProfileAction($database, $contextA, [
        'action' => 'save_profile', 'profile_id' => (string) $profileB, 'expected_profile_version' => (string) $currentA['profile_version'],
        ...ownerFlowProfileValues('Attempted B Profile'),
    ], [], $currentA, array_keys(ownerFlowProfileValues('')), ownerFlowProfileValues('Private A'));
    phase2Assert($foreignProfile['errors'] !== [], 'A foreign profile ID was accepted by an owner route action.');

    $ownProfile = handleAuthorizedProfileAction($database, $contextA, [
        'action' => 'save_profile', 'profile_id' => (string) $profileA, 'expected_profile_version' => (string) $currentA['profile_version'],
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
        'title' => 'A Overwrote B', 'category' => 'Owner Test', 'description' => 'Foreign update.', 'github_url' => 'https://example.test/foreign', 'live_url' => 'https://example.test/foreign-live',
    ], []);
    phase2Assert($foreignProject['errors'] !== [], 'A foreign project update was accepted.');
    $foreignProjectDelete = handleAuthorizedProjectAction($database, $contextA, ['action' => 'delete', 'id' => (string) $projectB], []);
    phase2Assert($foreignProjectDelete['errors'] !== [], 'A foreign project delete was accepted.');
    $ownProject = handleAuthorizedProjectAction($database, $contextA, [
        'action' => 'update', 'id' => (string) $projectA, 'portfolio_id' => (string) $portfolioB,
        'title' => 'A Project Updated', 'category' => 'Owner Test', 'description' => 'A update only.', 'github_url' => 'https://example.test/a-project-updated', 'technologies' => "Python\nPandas\npython",
    ], []);
    phase2AssertSame('owner_projects.php', $ownProject['redirect'], 'Project update did not PRG.');
    phase2AssertSame(['Python', 'Pandas'], findAuthorizedProject($database, $contextA, $projectA)['technologies'], 'Owner Project update did not normalize and persist technologies.');
    $removeProjectTechnologies = handleAuthorizedProjectAction($database, $contextA, [
        'action' => 'update', 'id' => (string) $projectA,
        'title' => 'A Project Updated', 'category' => 'Owner Test', 'description' => 'A update only.', 'github_url' => 'https://example.test/a-project-updated', 'technologies' => '',
    ], []);
    phase2AssertSame('owner_projects.php', $removeProjectTechnologies['redirect'], 'Owner Project technology removal did not PRG.');
    phase2AssertSame([], findAuthorizedProject($database, $contextA, $projectA)['technologies'], 'Removing all Project technologies must produce the empty semantic value.');
    $createProject = handleAuthorizedProjectAction($database, $contextA, [
        'action' => 'add', 'portfolio_id' => (string) $portfolioB,
        'title' => 'A Created Project', 'category' => 'Owner Test', 'description' => 'Created for A.', 'github_url' => 'https://example.test/a-created', 'technologies' => 'SQL',
    ], []);
    phase2AssertSame('owner_projects.php', $createProject['redirect'], 'Project creation did not PRG.');
    $createdProjects = array_values(array_filter(listAuthorizedProjects($database, $contextA), static fn (array $project): bool => $project['title'] === 'A Created Project'));
    phase2AssertSame(['SQL'], $createdProjects[0]['technologies'] ?? null, 'Owner Project creation did not persist technologies.');

    // Live project URL (B-04): saved; kept by a form without the field; unsafe rejected; cleared by an empty value.
    $liveUrlOf = static function () use ($database, $contextA, $projectA): ?string {
        $project = findAuthorizedProject($database, $contextA, $projectA);
        phase2Assert(is_array($project) && array_key_exists('live_url', $project), 'Owner project read omitted live_url.');
        return $project['live_url'];
    };
    $liveFields = ['action' => 'update', 'id' => (string) $projectA, 'title' => 'A Project Updated', 'category' => 'Owner Test', 'description' => 'A update only.', 'github_url' => 'https://example.test/a-project-updated', 'technologies' => ''];
    phase2AssertSame('owner_projects.php', handleAuthorizedProjectAction($database, $contextA, [...$liveFields, 'live_url' => ' https://demo.example.test/a '], [])['redirect'], 'A live project URL was not saved.');
    phase2AssertSame('https://demo.example.test/a', $liveUrlOf(), 'The live project URL was not trimmed and stored.');
    phase2AssertSame('owner_projects.php', handleAuthorizedProjectAction($database, $contextA, [...$liveFields, 'description' => 'Edited by a form without the live field.'], [])['redirect'], 'An edit without the live field failed.');
    phase2AssertSame('https://demo.example.test/a', $liveUrlOf(), 'A form without the live field erased the saved link.');
    foreach (['javascript:alert(1)', 'ftp://example.test/file', 'https://' . str_repeat('a', PROJECT_LIVE_URL_MAX_LENGTH) . '.test'] as $unsafeLiveUrl) {
        $rejected = handleAuthorizedProjectAction($database, $contextA, [...$liveFields, 'live_url' => $unsafeLiveUrl], []);
        phase2Assert(isset($rejected['field_errors']['live_url']) && $rejected['status'] === 422 && $rejected['redirect'] === null, 'An unsafe or oversized live URL was accepted.');
        phase2AssertSame('https://demo.example.test/a', $liveUrlOf(), 'A rejected live URL changed the stored link.');
    }
    phase2AssertSame('owner_projects.php', handleAuthorizedProjectAction($database, $contextA, [...$liveFields, 'live_url' => ''], [])['redirect'], 'Clearing the live URL failed.');
    phase2AssertSame(null, $liveUrlOf(), 'An empty live field did not clear the link.');
    $createWithLive = handleAuthorizedProjectAction($database, $contextA, [
        'action' => 'add', 'title' => 'A Live Project', 'category' => 'Owner Test', 'description' => 'Created with a live link.', 'github_url' => '', 'live_url' => 'https://demo.example.test/new', 'technologies' => '',
    ], []);
    phase2AssertSame('owner_projects.php', $createWithLive['redirect'], 'Project creation with a live URL did not PRG.');
    $liveProjects = array_values(array_filter(listAuthorizedProjects($database, $contextA), static fn (array $project): bool => $project['title'] === 'A Live Project'));
    phase2AssertSame('https://demo.example.test/new', $liveProjects[0]['live_url'] ?? null, 'Project creation did not persist the live URL.');
    phase2Assert(deleteAuthorizedProject($database, $contextA, (int) $liveProjects[0]['id']), 'The live URL fixture project could not be removed.');
    $passed[] = 'T-OWNER-PROJECT-LIVE-URL';

    phase2AssertSame(null, findAuthorizedMessage($database, $contextA, $messageBId), 'A read B message through an owner route candidate.');
    phase2AssertSame(false, setAuthorizedMessageRead($database, $contextA, $messageBId, true), 'A marked B message read.');
    phase2AssertSame(false, setAuthorizedMessageRead($database, $contextA, $messageBId, false), 'A marked B message unread.');
    phase2AssertSame(null, findAuthorizedPersonalInfo($database, $contextA, $profileB), 'A read B profile.');
    phase2AssertSame(null, findAuthorizedProject($database, $contextA, $projectB), 'A read B project.');
    phase2AssertSame($bBefore, ownerFlowSnapshot($database, $portfolioB), 'A owner route attempt changed B database rows.');
    phase2AssertSame($filesystemBefore, ownerFlowUploadSnapshot($legacyFixtureRoot), 'A owner route attempt changed B filesystem state.');
    phase2AssertSame('B private image', file_get_contents($bImagePath), 'A owner route attempt changed B project image bytes.');
    ownerFlowAssertRepositoryUploadsAbsent();
    $passed[] = 'T-OWNER-XTENANT A_TO_B';

    ownerFlowEstablishSession($userB, 9);
    $contextB = requireOwnedPortfolioContext($database);
    $aBefore = ownerFlowSnapshot($database, $portfolioA);
    $aFilesystemBefore = ownerFlowUploadSnapshot($legacyFixtureRoot);
    phase2AssertSame(null, findAuthorizedPersonalInfo($database, $contextB, $profileA), 'B read A profile.');
    phase2AssertSame(false, updateAuthorizedSkill($database, $contextB, $skillA, 'B Overwrote A'), 'B updated A skill.');
    phase2AssertSame(false, deleteAuthorizedProject($database, $contextB, $projectA), 'B deleted A project.');
    phase2AssertSame(null, findAuthorizedMessage($database, $contextB, $messageAId), 'B message candidate bypassed scope.');
    phase2AssertSame(false, setAuthorizedMessageRead($database, $contextB, $messageAId, true), 'B marked A message read.');
    phase2AssertSame($aBefore, ownerFlowSnapshot($database, $portfolioA), 'B owner route attempt changed A database rows.');
    phase2AssertSame($aFilesystemBefore, ownerFlowUploadSnapshot($legacyFixtureRoot), 'B owner route attempt changed A filesystem state.');
    ownerFlowAssertRepositoryUploadsAbsent();
    $passed[] = 'T-OWNER-XTENANT B_TO_A';

    ownerFlowEstablishSession($userA, 7);
    $contextA = requireOwnedPortfolioContext($database);
    $aggregate = authorizedPortfolioDashboardAggregate($database, $contextA);
    phase2AssertSame(2, $aggregate['project_count'], 'A dashboard count included foreign projects.');
    phase2AssertSame(2, $aggregate['skill_count'], 'A dashboard count included foreign skills.');
    phase2AssertSame(1, $aggregate['message_count'], 'A dashboard count included foreign messages.');
    // Read state (B-03): a new message is unread; opening keeps the first read time; unread clears it.
    phase2AssertSame(1, countAuthorizedUnreadMessages($database, $contextA), 'A new message must start unread, and only A messages count.');
    phase2AssertSame(true, setAuthorizedMessageRead($database, $contextA, $messageAId, true), 'A could not mark its message read.');
    $firstReadAt = findAuthorizedMessage($database, $contextA, $messageAId)['read_at'] ?? null;
    phase2Assert(is_string($firstReadAt), 'Marking read did not record a read time.');
    phase2AssertSame(0, countAuthorizedUnreadMessages($database, $contextA), 'A read message still counted as unread.');
    $database->exec('DO SLEEP(1.1)');
    setAuthorizedMessageRead($database, $contextA, $messageAId, true);
    phase2AssertSame($firstReadAt, findAuthorizedMessage($database, $contextA, $messageAId)['read_at'] ?? null, 'Opening again overwrote the first read time.');
    phase2AssertSame(true, setAuthorizedMessageRead($database, $contextA, $messageAId, false), 'A could not mark its message unread.');
    $unreadMessage = findAuthorizedMessage($database, $contextA, $messageAId);
    phase2Assert(is_array($unreadMessage) && array_key_exists('read_at', $unreadMessage) && $unreadMessage['read_at'] === null, 'Mark unread did not clear the read time.');
    phase2AssertSame(1, countAuthorizedUnreadMessages($database, $contextA), 'A message marked unread was not counted.');
    phase2AssertSame(false, setAuthorizedMessageRead($database, $contextA, 0, true), 'A non-positive message id was accepted.');
    $passed[] = 'T-OWNER-MESSAGE-READ-STATE';
    phase2AssertSame([$profileA], array_map(static fn (array $row): int => (int) $row['id'], array_filter([loadAuthorizedPersonalInfo($database, $contextA)])), 'A profile collection was not scoped.');
    $passed[] = 'T-OWNER-COLLECT';
    ownerFlowAssertRepositoryUploadsAbsent();
    $passed[] = 'T-OWNER-REPOSITORY-UPLOADS-ABSENT';
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
