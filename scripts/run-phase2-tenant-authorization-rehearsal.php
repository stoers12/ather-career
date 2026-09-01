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
require_once __DIR__ . '/../includes/portfolio_scoped_data.php';

const TENANT_AUTHORIZATION_TEST_ISSUER = 'https://issuer.test/ather-career';

function tenantAssertDenied(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (AuthorizationDeniedException) {
        return;
    }

    throw new RuntimeException($message);
}

function tenantAssertInvalidInput(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

function tenantCreateUser(PDO $database, string $label, string $accountStatus, int $authzVersion): int
{
    $statement = $database->prepare(
        'INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
         VALUES (:issuer, :subject, :account_status, :authz_version)'
    );
    $statement->execute([
        'issuer' => TENANT_AUTHORIZATION_TEST_ISSUER,
        'subject' => 'p2j03-' . $label . '-' . bin2hex(random_bytes(8)),
        'account_status' => $accountStatus,
        'authz_version' => $authzVersion,
    ]);

    return (int) $database->lastInsertId();
}

function tenantCreatePortfolio(PDO $database, int $userId): int
{
    $statement = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:owner_user_id)');
    $statement->execute(['owner_user_id' => $userId]);

    return (int) $database->lastInsertId();
}

function tenantSeedProfile(PDO $database, int $portfolioId, string $fullName): int
{
    $statement = $database->prepare(
        'INSERT INTO personal_info (portfolio_id, full_name, email)
         VALUES (:portfolio_id, :full_name, :email)'
    );
    $statement->execute([
        'portfolio_id' => $portfolioId,
        'full_name' => $fullName,
        'email' => strtolower(str_replace(' ', '.', $fullName)) . '@example.test',
    ]);

    return (int) $database->lastInsertId();
}

function tenantSeedSkill(PDO $database, int $portfolioId, string $skillName): int
{
    $statement = $database->prepare('INSERT INTO skills (portfolio_id, skill_name) VALUES (:portfolio_id, :skill_name)');
    $statement->execute(['portfolio_id' => $portfolioId, 'skill_name' => $skillName]);

    return (int) $database->lastInsertId();
}

function tenantSeedProject(PDO $database, int $portfolioId, string $title, ?string $imagePath): int
{
    $statement = $database->prepare(
        'INSERT INTO projects (portfolio_id, title, category, description, github_url, image_path)
         VALUES (:portfolio_id, :title, :category, :description, :github_url, :image_path)'
    );
    $statement->execute([
        'portfolio_id' => $portfolioId,
        'title' => $title,
        'category' => 'Tenant Test',
        'description' => $title . ' private description.',
        'github_url' => 'https://example.test/' . rawurlencode(strtolower(str_replace(' ', '-', $title))),
        'image_path' => $imagePath,
    ]);

    return (int) $database->lastInsertId();
}

function tenantSeedMessage(PDO $database, int $portfolioId, string $sender): int
{
    $statement = $database->prepare(
        'INSERT INTO messages (recipient_portfolio_id, name, email, message)
         VALUES (:recipient_portfolio_id, :name, :email, :message)'
    );
    $statement->execute([
        'recipient_portfolio_id' => $portfolioId,
        'name' => $sender,
        'email' => strtolower(str_replace(' ', '.', $sender)) . '@example.test',
        'message' => 'Private message for ' . $sender . '.',
    ]);

    return (int) $database->lastInsertId();
}

/** @return array<string, list<array<string, mixed>>> */
function tenantPortfolioSnapshot(PDO $database, int $portfolioId): array
{
    $queries = [
        'personal_info' => 'SELECT id, full_name, email, profile_image_path FROM personal_info WHERE portfolio_id = :portfolio_id ORDER BY id',
        'skills' => 'SELECT id, skill_name FROM skills WHERE portfolio_id = :portfolio_id ORDER BY id',
        'projects' => 'SELECT id, title, category, description, github_url, image_path FROM projects WHERE portfolio_id = :portfolio_id ORDER BY id',
        'messages' => 'SELECT id, name, email, message FROM messages WHERE recipient_portfolio_id = :portfolio_id ORDER BY id',
    ];
    $snapshot = [];
    foreach ($queries as $resource => $query) {
        $statement = $database->prepare($query);
        $statement->execute(['portfolio_id' => $portfolioId]);
        $snapshot[$resource] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    return $snapshot;
}

/** @return array<string, string> */
function tenantUploadSnapshot(string $uploadsRoot): array
{
    if (!is_dir($uploadsRoot)) {
        throw new RuntimeException('The disposable uploads root is unavailable.');
    }

    $snapshot = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('Could not snapshot a disposable upload.');
        }
        $snapshot[str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($uploadsRoot) + 1))] = $hash;
    }
    ksort($snapshot);

    return $snapshot;
}

function tenantEstablishSession(int $userId, int $authzVersion): void
{
    establishVerifiedInternalUserSession($userId, $authzVersion);
}

function tenantSetClientTenantCandidates(int $portfolioId): void
{
    $_SESSION['portfolio_id'] = $portfolioId;
    $_SESSION['user_id'] = 4294967295;
    $_SESSION['slug'] = 'foreign-portfolio';
    $_GET = ['portfolio_id' => (string) $portfolioId, 'slug' => 'foreign-portfolio'];
    $_POST = ['portfolio_id' => (string) $portfolioId, 'user_id' => '4294967295', 'slug' => 'foreign-portfolio'];
    $_COOKIE = ['portfolio_id' => (string) $portfolioId, 'portfolio_slug' => 'foreign-portfolio'];
    $_SERVER['HTTP_X_PORTFOLIO_ID'] = (string) $portfolioId;
    $_SERVER['HTTP_X_PORTFOLIO_SLUG'] = 'foreign-portfolio';
}

/** @return list<int> */
function tenantIds(array $rows): array
{
    return array_map(static fn (array $row): int => (int) $row['id'], $rows);
}

function tenantRequireOwnershipContract(PDO $database): void
{
    $statement = $database->prepare('SELECT name FROM schema_migrations WHERE version = :version');
    $statement->execute(['version' => '004']);
    phase2AssertSame('ownership_contract', $statement->fetchColumn(), 'P2J-03 rehearsal requires the completed P2J-02 ownership contract.');
}

$environment = null;
$bImageFile = null;
try {
    TestEnvironment::assertSafeEnvironment(getenv());
    $environment = TestEnvironment::create();
    session_name('phase2_tenant_authorization');

    $database = getDatabaseConnection();
    tenantRequireOwnershipContract($database);

    $userA = tenantCreateUser($database, 'user-a', 'active', 7);
    $portfolioA = tenantCreatePortfolio($database, $userA);
    $userB = tenantCreateUser($database, 'user-b', 'active', 9);
    $portfolioB = tenantCreatePortfolio($database, $userB);
    $userNoPortfolio = tenantCreateUser($database, 'user-no-portfolio', 'active', 3);
    $userDisabled = tenantCreateUser($database, 'user-disabled', 'disabled', 4);

    tenantEstablishSession($userA, 7);
    tenantSetClientTenantCandidates($portfolioB);
    $contextA = requireOwnedPortfolioContext($database);
    phase2AssertSame($userA, $contextA->userId, 'A context did not retain the validated User.');
    phase2AssertSame($portfolioA, $contextA->portfolioId, 'A client candidate selected B Portfolio.');

    $profileA = createAuthorizedPersonalInfo($database, $contextA, ['full_name' => 'Private A Profile']);
    $skillA = createAuthorizedSkill($database, $contextA, 'A Skill');
    $projectA = createAuthorizedProject($database, $contextA, 'A Project', 'Tenant Test', 'A private project.', 'https://example.test/a-project', null);
    $messageA = tenantSeedMessage($database, $portfolioA, 'Sender A');

    $profileB = tenantSeedProfile($database, $portfolioB, 'Private B Profile');
    $skillB = tenantSeedSkill($database, $portfolioB, 'B Skill');
    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    if ($uploadsRoot === false) {
        throw new RuntimeException('The disposable uploads root is unavailable.');
    }
    $bImageFilename = 'p2j03-b-' . bin2hex(random_bytes(8)) . '.bin';
    $bImageFile = $uploadsRoot . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $bImageFilename;
    if (file_put_contents($bImageFile, 'B private project image', LOCK_EX) === false) {
        throw new RuntimeException('Could not create the disposable B image fixture.');
    }
    $projectB = tenantSeedProject($database, $portfolioB, 'B Project', 'uploads/projects/' . $bImageFilename);
    $messageB = tenantSeedMessage($database, $portfolioB, 'Sender B');

    $passed = [];

    // T-AUTHZ: session state is validated against users and no Portfolio is trusted from client input.
    phase2AssertSame($userA, requireAuthenticatedUser($database)->userId, 'A active User was not accepted.');
    phase2AssertSame($portfolioA, requireOwnedPortfolioContext($database)->portfolioId, 'A client candidate selected B Portfolio.');

    $failedLookupDatabase = getDatabaseConnection();
    $failedLookupConnectionId = (int) $failedLookupDatabase->query('SELECT CONNECTION_ID()')->fetchColumn();
    $database->exec('KILL CONNECTION ' . $failedLookupConnectionId);
    tenantAssertDenied(static fn (): AuthenticatedUserContext => requireAuthenticatedUser($failedLookupDatabase), 'Current-User database lookup failure did not fail closed.');

    tenantEstablishSession($userA, 8);
    tenantAssertDenied(static fn (): AuthenticatedUserContext => requireAuthenticatedUser($database), 'Authz-version mismatch did not fail closed.');
    tenantEstablishSession($userDisabled, 4);
    tenantAssertDenied(static fn (): AuthenticatedUserContext => requireAuthenticatedUser($database), 'Disabled User was accepted.');
    tenantEstablishSession(4294967295, 1);
    tenantAssertDenied(static fn (): AuthenticatedUserContext => requireAuthenticatedUser($database), 'Missing User was accepted.');

    tenantEstablishSession($userNoPortfolio, 3);
    phase2AssertSame($userNoPortfolio, requireAuthenticatedUser($database)->userId, 'Active User without a Portfolio was not authenticated.');
    tenantAssertDenied(static fn (): AuthorizedPortfolioContext => requireOwnedPortfolioContext($database), 'No-Portfolio User received owner access.');
    $portfolioCount = $database->prepare('SELECT COUNT(*) FROM portfolios WHERE owner_user_id = :owner_user_id');
    $portfolioCount->execute(['owner_user_id' => $userNoPortfolio]);
    phase2AssertSame(0, (int) $portfolioCount->fetchColumn(), 'No-Portfolio User caused Portfolio creation.');

    $_SESSION[INTERNAL_USER_SESSION_KEY] = ['internal_user_id' => 'malformed', 'authz_version' => 3, 'authenticated_at' => time()];
    tenantAssertDenied(static fn (): AuthenticatedUserContext => requireAuthenticatedUser($database), 'Malformed session was accepted.');

    tenantEstablishSession($userA, 7);
    tenantSetClientTenantCandidates($portfolioB);
    $contextA = requireOwnedPortfolioContext($database);
    phase2AssertSame($portfolioA, $contextA->portfolioId, 'Slug/query/hidden/header/cookie input changed A tenant.');
    $passed[] = 'T-AUTHZ';

    // T-XTENANT: A sees B identifiers but every read/mutation remains scoped to A.
    $bBeforeA = tenantPortfolioSnapshot($database, $portfolioB);
    $uploadsBeforeA = tenantUploadSnapshot($uploadsRoot);
    phase2AssertSame(null, findAuthorizedPersonalInfo($database, $contextA, $profileB), 'A read B private profile.');
    phase2AssertSame(false, updateAuthorizedPersonalInfo($database, $contextA, $profileB, ['full_name' => 'A overwrote B']), 'A updated B profile.');
    phase2AssertSame(null, findAuthorizedSkill($database, $contextA, $skillB), 'A read B skill.');
    phase2AssertSame(false, updateAuthorizedSkill($database, $contextA, $skillB, 'A overwrote B Skill'), 'A updated B skill.');
    phase2AssertSame(false, deleteAuthorizedSkill($database, $contextA, $skillB), 'A deleted B skill.');

    $createdForA = createAuthorizedSkill($database, $contextA, 'A Skill From Foreign Portfolio Candidate');
    $createdForAPortfolio = $database->prepare('SELECT portfolio_id FROM skills WHERE id = :id');
    $createdForAPortfolio->execute(['id' => $createdForA]);
    phase2AssertSame($portfolioA, (int) $createdForAPortfolio->fetchColumn(), 'A submitted portfolio_id selected B for skill creation.');

    phase2AssertSame(null, findAuthorizedProject($database, $contextA, $projectB), 'A read B private project.');
    phase2AssertSame(false, updateAuthorizedProject($database, $contextA, $projectB, 'A overwrote B Project', 'Tenant Test', 'Attempted overwrite.', 'https://example.test/overwrite', null), 'A updated B project.');
    phase2AssertSame(false, deleteAuthorizedProject($database, $contextA, $projectB), 'A deleted B project.');
    phase2AssertSame(null, findAuthorizedMessage($database, $contextA, $messageB), 'A read B message.');
    phase2AssertSame($bBeforeA, tenantPortfolioSnapshot($database, $portfolioB), 'A cross-tenant operation changed B database rows.');
    phase2AssertSame($uploadsBeforeA, tenantUploadSnapshot($uploadsRoot), 'A cross-tenant operation changed B filesystem state.');
    phase2AssertSame('B private project image', file_get_contents($bImageFile), 'A cross-tenant operation changed B project file contents.');
    $passed[] = 'T-XTENANT A_TO_B';

    // The same scoped helpers must deny the symmetric B -> A candidates.
    tenantEstablishSession($userB, 9);
    tenantSetClientTenantCandidates($portfolioA);
    $contextB = requireOwnedPortfolioContext($database);
    $aBeforeB = tenantPortfolioSnapshot($database, $portfolioA);
    $uploadsBeforeB = tenantUploadSnapshot($uploadsRoot);
    phase2AssertSame(null, findAuthorizedPersonalInfo($database, $contextB, $profileA), 'B read A private profile.');
    phase2AssertSame(false, updateAuthorizedPersonalInfo($database, $contextB, $profileA, ['full_name' => 'B overwrote A']), 'B updated A profile.');
    phase2AssertSame(null, findAuthorizedSkill($database, $contextB, $skillA), 'B read A skill.');
    phase2AssertSame(false, updateAuthorizedSkill($database, $contextB, $skillA, 'B overwrote A Skill'), 'B updated A skill.');
    phase2AssertSame(false, deleteAuthorizedSkill($database, $contextB, $skillA), 'B deleted A skill.');
    phase2AssertSame(null, findAuthorizedProject($database, $contextB, $projectA), 'B read A private project.');
    phase2AssertSame(false, updateAuthorizedProject($database, $contextB, $projectA, 'B overwrote A Project', 'Tenant Test', 'Attempted overwrite.', 'https://example.test/overwrite', null), 'B updated A project.');
    phase2AssertSame(false, deleteAuthorizedProject($database, $contextB, $projectA), 'B deleted A project.');
    phase2AssertSame(null, findAuthorizedMessage($database, $contextB, $messageA), 'B read A message.');
    phase2AssertSame($aBeforeB, tenantPortfolioSnapshot($database, $portfolioA), 'B cross-tenant operation changed A database rows.');
    phase2AssertSame($uploadsBeforeB, tenantUploadSnapshot($uploadsRoot), 'B cross-tenant operation changed filesystem state.');
    $passed[] = 'T-XTENANT B_TO_A';

    // T-COLLECT: every collection and aggregate is constrained before PHP receives rows.
    tenantEstablishSession($userA, 7);
    tenantSetClientTenantCandidates($portfolioB);
    $contextA = requireOwnedPortfolioContext($database);
    phase2AssertSame([$skillA, $createdForA], tenantIds(listAuthorizedSkills($database, $contextA)), 'A skill collection included a foreign row.');
    phase2AssertSame([$projectA], tenantIds(listAuthorizedProjects($database, $contextA)), 'A project collection included a foreign row.');
    phase2AssertSame([$messageA], tenantIds(listAuthorizedMessages($database, $contextA)), 'A message collection included a foreign row.');
    phase2AssertSame(['project_count' => 1, 'skill_count' => 2, 'message_count' => 1, 'profile_count' => 1], authorizedPortfolioDashboardAggregate($database, $contextA), 'A dashboard aggregate included B rows.');

    tenantEstablishSession($userB, 9);
    tenantSetClientTenantCandidates($portfolioA);
    $contextB = requireOwnedPortfolioContext($database);
    phase2AssertSame([$skillB], tenantIds(listAuthorizedSkills($database, $contextB)), 'B skill collection included a foreign row.');
    phase2AssertSame([$projectB], tenantIds(listAuthorizedProjects($database, $contextB)), 'B project collection included a foreign row.');
    phase2AssertSame([$messageB], tenantIds(listAuthorizedMessages($database, $contextB)), 'B message collection included a foreign row.');
    phase2AssertSame(['project_count' => 1, 'skill_count' => 1, 'message_count' => 1, 'profile_count' => 1], authorizedPortfolioDashboardAggregate($database, $contextB), 'B dashboard aggregate included A rows.');
    $passed[] = 'T-COLLECT';

    // T-MASS: client-supplied tenant identity is rejected from profile fields and cannot redirect creation.
    tenantEstablishSession($userA, 7);
    tenantSetClientTenantCandidates($portfolioB);
    $contextA = requireOwnedPortfolioContext($database);
    $bBeforeMass = tenantPortfolioSnapshot($database, $portfolioB);
    $uploadsBeforeMass = tenantUploadSnapshot($uploadsRoot);
    tenantAssertInvalidInput(
        static fn (): int => createAuthorizedPersonalInfo($database, $contextA, ['full_name' => 'Rejected tenant selector', 'portfolio_id' => $portfolioB]),
        'Client portfolio_id was accepted for profile creation.'
    );
    tenantAssertInvalidInput(
        static fn (): bool => updateAuthorizedPersonalInfo($database, $contextA, $profileA, ['portfolio_id' => $portfolioB]),
        'Client portfolio_id was accepted for profile update.'
    );
    phase2AssertSame($bBeforeMass, tenantPortfolioSnapshot($database, $portfolioB), 'Client mass-assignment attempt changed B database rows.');
    phase2AssertSame($uploadsBeforeMass, tenantUploadSnapshot($uploadsRoot), 'Client mass-assignment attempt changed filesystem state.');
    $passed[] = 'T-MASS';
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL tenant authorization rehearsal: {$exception->getMessage()}\n");
    exit(1);
} finally {
    if (is_string($bImageFile) && is_file($bImageFile) && !unlink($bImageFile)) {
        fwrite(STDERR, "FAIL tenant authorization fixture cleanup: could not remove test-owned B image.\n");
        exit(1);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        destroyInternalUserSession();
    }
    if ($environment instanceof TestEnvironment) {
        try {
            $environment->tearDown();
        } catch (Throwable $exception) {
            fwrite(STDERR, "FAIL tenant authorization test namespace teardown: {$exception->getMessage()}\n");
            exit(1);
        }
    }
}

foreach ($passed as $name) {
    echo "PASS {$name}\n";
}
echo "PASS tenant authorization rehearsal\n";
