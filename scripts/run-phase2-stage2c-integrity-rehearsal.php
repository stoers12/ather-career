<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/owner_actions.php';
require_once __DIR__ . '/../includes/public_contact.php';
require_once __DIR__ . '/../includes/media_access.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';

const STAGE2C_TEST_ISSUER = 'https://issuer.test/stage2c';
const STAGE2C_REFERENCE_MONTH = '2026-09';

function stage2cCreateUser(PDO $database, string $label): int
{
    $statement = $database->prepare(
        "INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
         VALUES (:issuer, :subject, 'active', 1)"
    );
    $statement->execute([
        'issuer' => STAGE2C_TEST_ISSUER,
        'subject' => 'stage2c-' . $label . '-' . bin2hex(random_bytes(8)),
    ]);

    return (int) $database->lastInsertId();
}

function stage2cCreatePortfolio(PDO $database, int $userId): int
{
    $statement = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:owner_user_id)');
    $statement->execute(['owner_user_id' => $userId]);

    return (int) $database->lastInsertId();
}

function stage2cContext(int $userId, int $portfolioId): AuthorizedPortfolioContext
{
    return AuthorizedPortfolioContext::fromValidatedOwnership(
        AuthenticatedUserContext::fromValidatedUser($userId),
        $portfolioId,
    );
}

/** @return array<string, mixed> */
function stage2cProfileValues(string $name): array
{
    return [
        'full_name' => $name,
        'professional_title' => 'Systems Engineer',
        'hero_headline' => 'Builds dependable systems.',
        'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
        'phone_primary' => '',
        'phone_secondary' => '',
        'location' => 'Amman',
        'about_me' => 'Isolated Stage-2C fixture.',
        'work_description' => 'Transaction and authorization test.',
        'linkedin_url' => '',
        'github_url' => '',
        'instagram_url' => '',
        'facebook_url' => '',
        'website_url' => '',
        'profile_image_path' => null,
        'public_contact_visible' => false,
    ];
}

/** @return array<string, mixed> */
function stage2cExperienceValues(string $title): array
{
    return [
        'experience_type' => 'employment',
        'role_title' => $title,
        'organization' => 'Fixture Org',
        'location' => 'Amman',
        'start_month' => '2025-01',
        'end_month' => '',
        'is_current' => '1',
        'description' => 'Isolated fixture.',
    ];
}

/** @return array<string, mixed> */
function stage2cProjectPost(string $action, array $extra = []): array
{
    return [
        'action' => $action,
        'title' => 'Stage2C Project',
        'category' => 'Testing',
        'description' => 'A private, isolated project fixture.',
        'github_url' => 'https://example.test/stage2c-project',
        'technologies' => "PHP\nMySQL",
        ...$extra,
    ];
}

function stage2cCreatePng(string $path, int $red): void
{
    $image = imagecreatetruecolor(64, 48);
    if ($image === false) {
        throw new RuntimeException('Could not create Stage-2C image fixture.');
    }
    imagefill($image, 0, 0, imagecolorallocate($image, $red, 70, 120));
    $written = imagepng($image, $path);
    imagedestroy($image);
    if (!$written) {
        throw new RuntimeException('Could not write Stage-2C image fixture.');
    }
}

/** @param list<int> $portfolioIds */
function stage2cDeleteCreatedRows(PDO $database, array $portfolioIds, array $userIds): void
{
    if ($portfolioIds !== []) {
        $placeholders = implode(', ', array_fill(0, count($portfolioIds), '?'));
        foreach ([
            'DELETE FROM messages WHERE recipient_portfolio_id IN (' . $placeholders . ')',
            'DELETE FROM projects WHERE portfolio_id IN (' . $placeholders . ')',
            'DELETE FROM experiences WHERE portfolio_id IN (' . $placeholders . ')',
            'DELETE FROM skills WHERE portfolio_id IN (' . $placeholders . ')',
            'DELETE FROM personal_info WHERE portfolio_id IN (' . $placeholders . ')',
            'DELETE FROM portfolios WHERE id IN (' . $placeholders . ')',
        ] as $sql) {
            $statement = $database->prepare($sql);
            $statement->execute($portfolioIds);
        }
    }
    if ($userIds !== []) {
        $statement = $database->prepare('DELETE FROM users WHERE id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')');
        $statement->execute($userIds);
    }
}

/** @param list<int> $ids */
function stage2cCountRows(PDO $database, string $table, string $column, array $ids): int
{
    if ($ids === []) {
        return 0;
    }
    $statement = $database->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')');
    $statement->execute($ids);

    return (int) $statement->fetchColumn();
}

$environment = null;
$database = null;
$priorStorageRoot = getenv('ATHERCAR_STORAGE_ROOT');
$priorDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
$userIds = [];
$portfolioIds = [];
$passed = [];

try {
    TestEnvironment::assertSafeEnvironment(getenv());
    $environment = TestEnvironment::create();
    $storageRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'private-media';
    $publicRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'public-root';
    if (!mkdir($storageRoot, 0700, true) || !mkdir($publicRoot, 0700, true)) {
        throw new RuntimeException('Could not create Stage-2C test storage.');
    }
    putenv('ATHERCAR_STORAGE_ROOT=' . $storageRoot);
    $_SERVER['DOCUMENT_ROOT'] = $publicRoot;

    $database = getDatabaseConnection();
    phase2AssertSame('profile_contact_visibility', $database->query("SELECT name FROM schema_migrations WHERE version = '009'")->fetchColumn(), 'Stage-2C rehearsal requires the complete Phase-2 schema.');

    $userA = stage2cCreateUser($database, 'a');
    $userIds[] = $userA;
    $portfolioA = stage2cCreatePortfolio($database, $userA);
    $portfolioIds[] = $portfolioA;
    $contextA = stage2cContext($userA, $portfolioA);
    $profileA = createAuthorizedPersonalInfo($database, $contextA, stage2cProfileValues('Stage2C A'));

    $userB = stage2cCreateUser($database, 'b');
    $userIds[] = $userB;
    $portfolioB = stage2cCreatePortfolio($database, $userB);
    $portfolioIds[] = $portfolioB;
    $contextB = stage2cContext($userB, $portfolioB);
    $profileB = createAuthorizedPersonalInfo($database, $contextB, stage2cProfileValues('Stage2C B'));

    $userC = stage2cCreateUser($database, 'incomplete');
    $userIds[] = $userC;
    $portfolioC = stage2cCreatePortfolio($database, $userC);
    $portfolioIds[] = $portfolioC;
    $contextC = stage2cContext($userC, $portfolioC);

    phase2AssertSame(null, resolvePublicReadContext($database, 'stage2c-missing'), 'Unknown public portfolio resolved.');
    phase2AssertSame(null, resolvePublicReadContext($database, 'stage2c-a'), 'Unpublished public portfolio resolved.');
    phase2AssertSame(null, findAuthorizedPersonalInfo($database, $contextA, $profileB), 'Owner A read Owner B profile.');
    phase2AssertSame(false, updateAuthorizedPersonalInfo($database, $contextA, $profileB, ['location' => 'Foreign']), 'Owner A updated Owner B profile.');
    $passed[] = 'T2C-PROFILE-OWNER-SCOPE';

    $fields = array_values(array_diff(AUTHORIZED_PERSONAL_INFO_FIELDS, ['profile_image_path', 'public_contact_visible']));
    $profileState = loadAuthorizedPersonalInfo($database, $contextA);
    $skillResult = handleAuthorizedProfileAction($database, $contextA, [
        'action' => 'add_skill',
        'skill_name' => 'Stage2C Skill A',
        'portfolio_id' => (string) $portfolioB,
        'owner_user_id' => (string) $userB,
    ], [], $profileState, $fields, $profileState);
    phase2AssertSame([], $skillResult['errors'], 'Owner A could not add an owned skill.');
    $skillA = (int) listAuthorizedSkills($database, $contextA)[0]['id'];
    $skillB = createAuthorizedSkill($database, $contextB, 'Stage2C Skill B');
    phase2AssertSame(false, updateAuthorizedSkill($database, $contextA, $skillB, 'Foreign overwrite'), 'Owner A updated Owner B skill.');
    phase2AssertSame(false, deleteAuthorizedSkill($database, $contextA, $skillB), 'Owner A deleted Owner B skill.');
    phase2AssertSame($portfolioA, (int) $database->query('SELECT portfolio_id FROM skills WHERE id = ' . $skillA)->fetchColumn(), 'Submitted portfolio candidate changed skill ownership.');
    $passed[] = 'T2C-SKILL-OWNER-SCOPE';

    $projectResult = handleAuthorizedProjectAction($database, $contextA, stage2cProjectPost('add', [
        'portfolio_id' => (string) $portfolioB,
        'owner_user_id' => (string) $userB,
    ]), []);
    phase2AssertSame([], $projectResult['errors'], 'Owner A could not create an owned project.');
    $projectA = (int) listAuthorizedProjects($database, $contextA)[0]['id'];
    $projectB = createAuthorizedProject($database, $contextB, 'Stage2C B Project', 'Testing', 'Private B project.', 'https://example.test/stage2c-b', null, []);
    $foreignProject = handleAuthorizedProjectAction($database, $contextA, stage2cProjectPost('update', ['id' => (string) $projectB]), []);
    phase2AssertSame(404, $foreignProject['status'], 'Cross-owner project update did not return sanitized not-found.');
    phase2AssertSame('', $foreignProject['editing_project']['id'], 'Cross-owner project ID was reflected in the response form.');
    phase2AssertSame('Stage2C B Project', findAuthorizedProject($database, $contextB, $projectB)['title'] ?? null, 'Cross-owner project update changed B data.');
    $foreignDelete = handleAuthorizedProjectAction($database, $contextA, ['action' => 'delete', 'id' => (string) $projectB], []);
    phase2AssertSame(404, $foreignDelete['status'], 'Cross-owner project deletion did not return sanitized not-found.');
    $malformedTechnologies = handleAuthorizedProjectAction($database, $contextA, stage2cProjectPost('add', ['technologies' => ['not', 'text']]), []);
    phase2AssertSame(422, $malformedTechnologies['status'], 'Malformed technology collection was accepted.');
    phase2AssertSame(1, count(listAuthorizedProjects($database, $contextA)), 'Malformed project submission wrote an unintended project.');
    $passed[] = 'T2C-PROJECT-OWNER-SCOPE-AND-VALIDATION';

    $experienceResult = handleAuthorizedExperienceAction($database, $contextA, [
        'action' => 'add',
        ...stage2cExperienceValues('Stage2C A Experience'),
        'portfolio_id' => (string) $portfolioB,
    ], STAGE2C_REFERENCE_MONTH);
    phase2AssertSame([], $experienceResult['errors'], 'Owner A could not create an owned experience.');
    $experienceA = (int) listAuthorizedExperiences($database, $contextA)[0]['id'];
    $experienceB = createAuthorizedExperience($database, $contextB, stage2cExperienceValues('Stage2C B Experience'), STAGE2C_REFERENCE_MONTH);
    $foreignExperience = handleAuthorizedExperienceAction($database, $contextA, [
        'action' => 'update', 'id' => (string) $experienceB, ...stage2cExperienceValues('Foreign overwrite'),
    ], STAGE2C_REFERENCE_MONTH);
    phase2AssertSame(404, $foreignExperience['status'], 'Cross-owner experience update did not return sanitized not-found.');
    phase2AssertSame('', $foreignExperience['editing_experience']['id'], 'Cross-owner experience ID was reflected in the response form.');
    phase2AssertSame(false, deleteAuthorizedExperience($database, $contextA, $experienceB), 'Owner A deleted Owner B experience.');
    phase2AssertSame($portfolioA, (int) $database->query('SELECT portfolio_id FROM experiences WHERE id = ' . $experienceA)->fetchColumn(), 'Submitted portfolio candidate changed experience ownership.');
    $passed[] = 'T2C-EXPERIENCE-OWNER-SCOPE';

    $rollbackSkill = 'Stage2C rollback ' . bin2hex(random_bytes(4));
    try {
        runDatabaseTransaction($database, static function () use ($database, $contextA, $rollbackSkill): void {
            createAuthorizedSkill($database, $contextA, $rollbackSkill);
            throw new RuntimeException('test-only injected dependent write failure');
        });
        throw new RuntimeException('Injected transaction failure unexpectedly completed.');
    } catch (RuntimeException $exception) {
        phase2AssertSame('test-only injected dependent write failure', $exception->getMessage(), 'Transaction failure was not the controlled test fault.');
    }
    $rollbackCheck = $database->prepare('SELECT COUNT(*) FROM skills WHERE portfolio_id = :portfolio_id AND skill_name = :skill_name');
    $rollbackCheck->execute(['portfolio_id' => $portfolioA, 'skill_name' => $rollbackSkill]);
    phase2AssertSame(0, (int) $rollbackCheck->fetchColumn(), 'Failed dependent write left an earlier write committed.');
    phase2Assert(!$database->inTransaction(), 'Transaction was left open after a failure.');
    $passed[] = 'T2C-TRANSACTION-ROLLBACK';

    $suffix = bin2hex(random_bytes(5));
    setOwnedPublicSlug($database, $contextC, 'stage2c-incomplete-' . $suffix);
    try {
        publishOwnedPortfolio($database, $contextC);
        throw new RuntimeException('Incomplete portfolio was published.');
    } catch (PublicLifecycleValidationException) {
    }
    setOwnedPublicSlug($database, $contextA, 'stage2c-a-' . $suffix);
    try {
        setOwnedPublicSlug($database, $contextB, 'stage2c-a-' . $suffix);
        throw new RuntimeException('Duplicate public slug was accepted.');
    } catch (PublicLifecycleConflictException) {
    }
    publishOwnedPortfolio($database, $contextA);
    $publicA = resolvePublicReadContext($database, 'stage2c-a-' . $suffix);
    phase2Assert($publicA instanceof PublicReadContext && $publicA->portfolioId === $portfolioA, 'Published portfolio did not resolve publicly.');
    $publicPayload = listPublicProjectJsonPayload($database, $publicA, 'stage2c-a-' . $suffix);
    phase2AssertSame(['title', 'category', 'description', 'github_url', 'technologies'], array_keys($publicPayload[0]), 'Public project JSON exposed internal fields.');
    $messageId = createPublicContactMessage($database, $publicA, ['name' => 'Stage2C Visitor', 'email' => 'visitor@example.test', 'message' => 'Isolated public contact fixture.']);
    phase2Assert(findAuthorizedMessage($database, $contextA, $messageId) !== null, 'Owner could not read their public contact message.');
    phase2AssertSame(null, findAuthorizedMessage($database, $contextB, $messageId), 'Owner B read Owner A private message.');
    $passed[] = 'T2C-PUBLICATION-SLUG-AND-MESSAGE-SCOPE';

    $source = $environment->storageRoot . DIRECTORY_SEPARATOR . 'source.png';
    stage2cCreatePng($source, 120);
    $oldKey = copyFileToPrivateMedia($source, $portfolioA, 'projects', 'stage2c-old.png');
    phase2Assert(is_string($oldKey) && generateProjectPresentationImage($oldKey, $portfolioA) !== null, 'Could not stage Stage-2C old media.');
    phase2Assert(runDatabaseTransaction($database, static fn (): bool => updateAuthorizedProject($database, $contextA, $projectA, 'Stage2C Project', 'Testing', 'A private, isolated project fixture.', 'https://example.test/stage2c-project', $oldKey, ['PHP', 'MySQL'])), 'Could not attach Stage-2C old media.');
    phase2Assert(ownerMediaDescriptor($database, $contextA, 'project', (string) $projectA) !== null, 'Owner could not read owned private media.');
    phase2AssertSame(null, ownerMediaDescriptor($database, $contextB, 'project', (string) $projectA), 'Owner B read Owner A private media.');
    phase2Assert(publicMediaDescriptor($database, $publicA, 'project', (string) $projectA) !== null, 'Published project media was unavailable.');

    $replacementKey = copyFileToPrivateMedia($source, $portfolioA, 'projects', 'stage2c-replacement.png');
    phase2Assert(is_string($replacementKey) && generateProjectPresentationImage($replacementKey, $portfolioA) !== null, 'Could not stage replacement media.');
    try {
        runDatabaseTransaction($database, static function () use ($database, $contextA, $projectA, $replacementKey): void {
            phase2Assert(updateAuthorizedProject($database, $contextA, $projectA, 'Stage2C Project', 'Testing', 'A private, isolated project fixture.', 'https://example.test/stage2c-project', $replacementKey, ['PHP', 'MySQL']), 'Injected replacement did not reach its dependent database write.');
            throw new RuntimeException('test-only injected replacement failure');
        });
        throw new RuntimeException('Injected replacement failure unexpectedly completed.');
    } catch (RuntimeException $exception) {
        phase2AssertSame('test-only injected replacement failure', $exception->getMessage(), 'Replacement failure was not the controlled test fault.');
    }
    phase2AssertSame($oldKey, findAuthorizedProject($database, $contextA, $projectA)['image_path'] ?? null, 'Failed replacement changed the database reference.');
    phase2Assert(is_file(resolvePrivateMediaPath($oldKey, $portfolioA, 'projects')), 'Failed replacement removed the existing media.');
    phase2Assert(!$database->inTransaction(), 'Replacement failure left a transaction open.');
    cleanProjectImage($replacementKey, 'stage2c_test_replacement_compensation', $portfolioA);
    phase2Assert(!is_file(resolvePrivateMediaPath($replacementKey, $portfolioA, 'projects')), 'Failed replacement did not compensate request-owned media.');

    $sharedProject = createAuthorizedProject($database, $contextA, 'Stage2C Shared Media', 'Testing', 'Reference safety fixture.', 'https://example.test/stage2c-shared', $oldKey, []);
    $deletePrimary = handleAuthorizedProjectAction($database, $contextA, ['action' => 'delete', 'id' => (string) $projectA], []);
    phase2AssertSame([], $deletePrimary['errors'], 'Owner could not delete owned project.');
    phase2Assert(is_file(resolvePrivateMediaPath($oldKey, $portfolioA, 'projects')), 'Shared project media was deleted while still referenced.');
    $deleteShared = handleAuthorizedProjectAction($database, $contextA, ['action' => 'delete', 'id' => (string) $sharedProject], []);
    phase2AssertSame([], $deleteShared['errors'], 'Owner could not delete final media reference.');
    phase2Assert(!is_file(resolvePrivateMediaPath($oldKey, $portfolioA, 'projects')), 'Unreferenced project media was not retired after successful delete.');
    $passed[] = 'T2C-MEDIA-COMPENSATION-AND-REFERENCE-SAFETY';

    unpublishOwnedPortfolio($database, $contextA);
    phase2AssertSame(null, resolvePublicReadContext($database, 'stage2c-a-' . $suffix), 'Unpublished portfolio remained publicly available.');
    phase2AssertSame(null, publicMediaDescriptor($database, PublicReadContext::fromPublishedPortfolio($portfolioA), 'project', (string) $projectA), 'Deleted/unpublished public media remained available.');
    $passed[] = 'T2C-UNPUBLISHED-PUBLIC-DENIAL';
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL Stage-2C integrity rehearsal: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if ($database instanceof PDO) {
        while ($database->inTransaction()) {
            $database->rollBack();
        }
        try {
            stage2cDeleteCreatedRows($database, $portfolioIds, $userIds);
            phase2AssertSame(0, stage2cCountRows($database, 'portfolios', 'id', $portfolioIds), 'Stage-2C cleanup left test portfolios.');
            phase2AssertSame(0, stage2cCountRows($database, 'users', 'id', $userIds), 'Stage-2C cleanup left test users.');
        } catch (Throwable $exception) {
            fwrite(STDERR, 'FAIL Stage-2C database cleanup: ' . $exception->getMessage() . "\n");
            exit(1);
        }
    }
    $priorStorageRoot === false ? putenv('ATHERCAR_STORAGE_ROOT') : putenv('ATHERCAR_STORAGE_ROOT=' . $priorStorageRoot);
    if ($priorDocumentRoot === null) {
        unset($_SERVER['DOCUMENT_ROOT']);
    } else {
        $_SERVER['DOCUMENT_ROOT'] = $priorDocumentRoot;
    }
    if ($environment instanceof TestEnvironment) {
        try {
            $environment->tearDown();
        } catch (Throwable $exception) {
            fwrite(STDERR, 'FAIL Stage-2C storage cleanup: ' . $exception->getMessage() . "\n");
            exit(1);
        }
    }
}

foreach ($passed as $name) {
    echo "PASS {$name}\n";
}
echo "PASS Stage-2C integrity rehearsal\n";
