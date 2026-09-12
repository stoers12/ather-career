<?php

declare(strict_types=1);

/* CLI-only synthetic data for the isolated Stage-4C rehearsal. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/portfolio_scoped_data.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';
require_once __DIR__ . '/../includes/media_access.php';
require_once __DIR__ . '/../includes/owner_session.php';
require_once __DIR__ . '/stage4c-recovery.php';

const STAGE4C_DR_PREFIX = 'ather_stage4c_dr_';

/** @return never */
function stage4cFixtureFail(string $reason): never
{
    fwrite(STDERR, "Stage-4C rehearsal fixture refused: {$reason}\n");
    exit(1);
}

function stage4cFixtureProject(): string
{
    $project = getenv('ATHERCAR_STAGE4C_DR_PROJECT');
    $database = getenv('DB_NAME');
    if (getenv('ATHERCAR_STAGE4C_DR') !== '1'
        || !is_string($project)
        || preg_match('/^ather_stage4c_(?:dr|restore)_[a-f0-9]{24}$/', $project) !== 1
        || !is_string($database)
        || !hash_equals($project, $database)
        || getenv('APP_ENV') !== 'production'
        || getenv('ATHERCAR_TEST_MODE') !== false
        || getenv('ATHERCAR_STORAGE_ROOT') !== '/var/lib/ather-career/storage') {
        stage4cFixtureFail('the exact disposable production rehearsal namespace is required.');
    }
    return $project;
}

function stage4cFixtureSlug(mixed $candidate): string
{
    if (!is_string($candidate) || preg_match('/^stage4c-dr-[a-f0-9]{24}$/', $candidate) !== 1) {
        stage4cFixtureFail('the exact synthetic public slug is required.');
    }
    return $candidate;
}

function stage4cFixturePng(string $path, int $red, int $green, int $blue): void
{
    $image = imagecreatetruecolor(112, 80);
    if ($image === false) {
        throw new RuntimeException('Synthetic image allocation failed.');
    }
    imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));
    if (!imagepng($image, $path)) {
        imagedestroy($image);
        throw new RuntimeException('Synthetic image write failed.');
    }
    imagedestroy($image);
}

/** @return array{user_id:int,portfolio_id:int,project_ids:list<int>} */
function stage4cFixtureSeed(PDO $database, string $slug): array
{
    $exists = $database->prepare('SELECT COUNT(*) FROM portfolios WHERE public_slug = :slug');
    $exists->execute(['slug' => $slug]);
    if ((int) $exists->fetchColumn() !== 0) {
        throw new RuntimeException('The disposable rehearsal slug already exists.');
    }
    $user = $database->prepare('INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:issuer, :subject, "active", 1)');
    $user->execute(['issuer' => 'https://stage4c-owner.invalid/', 'subject' => 'owner-' . substr($slug, -24)]);
    $userId = (int) $database->lastInsertId();
    $portfolio = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:user_id)');
    $portfolio->execute(['user_id' => $userId]);
    $portfolioId = (int) $database->lastInsertId();
    $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($userId), $portfolioId);

    $sources = [tempnam(sys_get_temp_dir(), 'stage4c-profile-'), tempnam(sys_get_temp_dir(), 'stage4c-project-a-'), tempnam(sys_get_temp_dir(), 'stage4c-project-b-')];
    if (in_array(false, $sources, true)) {
        throw new RuntimeException('Synthetic media staging is unavailable.');
    }
    try {
        stage4cFixturePng($sources[0], 28, 104, 180);
        stage4cFixturePng($sources[1], 70, 150, 90);
        stage4cFixturePng($sources[2], 165, 92, 76);
        $profile = copyFileToPrivateMedia($sources[0], $portfolioId, 'profile_original', 'stage4c-profile.png');
        $projectA = copyFileToPrivateMedia($sources[1], $portfolioId, 'projects', 'stage4c-project-a.png');
        $projectB = copyFileToPrivateMedia($sources[2], $portfolioId, 'projects', 'stage4c-project-b.png');
        if ($profile === null || $projectA === null || $projectB === null
            || generateProfilePresentationImage($profile, $portfolioId) === null
            || generateProjectPresentationImage($projectA, $portfolioId) === null
            || generateProjectPresentationImage($projectB, $portfolioId) === null) {
            throw new RuntimeException('Synthetic media normalization failed.');
        }
    } finally {
        foreach ($sources as $source) {
            if (is_string($source)) {
                @unlink($source);
            }
        }
    }

    createAuthorizedPersonalInfo($database, $context, [
        'full_name' => 'Stage Four C Owner',
        'professional_title' => 'Synthetic Recovery Engineer',
        'hero_headline' => 'Synthetic disaster-recovery rehearsal Portfolio.',
        'email' => 'owner@stage4c.invalid',
        'phone_primary' => '+962700000000',
        'phone_secondary' => '',
        'location' => 'Synthetic City',
        'about_me' => 'Synthetic content only.',
        'work_description' => 'Synthetic operational recovery validation.',
        'linkedin_url' => 'https://stage4c.invalid/linkedin',
        'github_url' => 'https://stage4c.invalid/github',
        'instagram_url' => '',
        'facebook_url' => '',
        'website_url' => '',
        'profile_image_path' => $profile,
        'public_contact_visible' => true,
    ]);
    foreach (['Recovery', 'MySQL', 'PHP', 'Apache', 'Security'] as $skill) {
        createAuthorizedSkill($database, $context, $skill);
    }
    $projectIds = [];
    for ($index = 1; $index <= 7; ++$index) {
        $projectIds[] = createAuthorizedProject(
            $database,
            $context,
            'Recovery Project ' . $index,
            'Synthetic',
            'Synthetic published project ' . $index . ' retained for restoration.',
            'https://stage4c.invalid/project-' . $index,
            $index === 1 ? $projectA : ($index === 2 ? $projectB : null),
            ['PHP', 'MySQL'],
        );
    }
    for ($index = 1; $index <= 4; ++$index) {
        createAuthorizedExperience($database, $context, [
            'experience_type' => 'employment',
            'role_title' => 'Synthetic Recovery Role ' . $index,
            'organization' => 'Stage-4C Rehearsal',
            'location' => 'Synthetic City',
            'start_month' => '202' . ($index - 1) . '-01',
            'end_month' => '202' . ($index - 1) . '-12',
            'is_current' => false,
            'description' => 'Synthetic recovery experience ' . $index . '.',
        ]);
    }
    $message = $database->prepare('INSERT INTO messages (name, email, message, recipient_portfolio_id) VALUES (:name, :email, :message, :portfolio_id)');
    $message->execute([
        'name' => 'Synthetic Contact',
        'email' => 'contact@stage4c.invalid',
        'message' => 'Synthetic contact retained for disaster-recovery verification.',
        'portfolio_id' => $portfolioId,
    ]);
    setOwnedPublicSlug($database, $context, $slug);
    publishOwnedPortfolio($database, $context);
    return ['user_id' => $userId, 'portfolio_id' => $portfolioId, 'project_ids' => $projectIds];
}

/** @return array<string,int|string> */
function stage4cFixtureFingerprint(PDO $database, string $slug): array
{
    $portfolio = $database->prepare('SELECT id, owner_user_id, is_published FROM portfolios WHERE public_slug = :slug LIMIT 1');
    $portfolio->execute(['slug' => $slug]);
    $row = $portfolio->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || (int) $row['id'] < 1 || (int) $row['owner_user_id'] < 1) {
        throw new RuntimeException('The synthetic Portfolio is unavailable.');
    }
    $counts = [];
    foreach ([
        'profiles' => 'SELECT COUNT(*) FROM personal_info WHERE portfolio_id = :portfolio_id',
        'projects' => 'SELECT COUNT(*) FROM projects WHERE portfolio_id = :portfolio_id',
        'skills' => 'SELECT COUNT(*) FROM skills WHERE portfolio_id = :portfolio_id',
        'experiences' => 'SELECT COUNT(*) FROM experiences WHERE portfolio_id = :portfolio_id',
        'messages' => 'SELECT COUNT(*) FROM messages WHERE recipient_portfolio_id = :portfolio_id',
    ] as $name => $query) {
        $statement = $database->prepare($query);
        $statement->execute(['portfolio_id' => $row['id']]);
        $counts[$name] = (int) $statement->fetchColumn();
    }
    $media = stage4cScanManagedMedia('/var/lib/ather-career/storage');
    $ledger = stage4cMigrationSummary($database);
    return [
        'user_id' => (int) $row['owner_user_id'],
        'portfolio_id' => (int) $row['id'],
        'published' => (int) $row['is_published'],
        ...$counts,
        'media_files' => count($media['entries']),
        'media_manifest_sha256' => $media['content_hash'],
        'migration_count' => $ledger['count'],
        'migration_ledger_sha256' => $ledger['ledger_hash'],
    ];
}

try {
    stage4cFixtureProject();
    $action = $argv[1] ?? '';
    $slug = stage4cFixtureSlug($argv[2] ?? null);
    $database = getDatabaseConnection();
    if ($action === 'seed') {
        $seed = stage4cFixtureSeed($database, $slug);
        $fingerprint = stage4cFixtureFingerprint($database, $slug);
        if ($fingerprint['published'] !== 1 || $fingerprint['profiles'] !== 1 || $fingerprint['projects'] < 7
            || $fingerprint['skills'] < 5 || $fingerprint['experiences'] < 4 || $fingerprint['messages'] < 1
            || $fingerprint['media_files'] < 6 || $fingerprint['migration_count'] < 1) {
            throw new RuntimeException('The synthetic rehearsal dataset is incomplete.');
        }
        echo json_encode(['ok' => true, 'action' => 'seed', ...$seed, ...$fingerprint], JSON_THROW_ON_ERROR) . "\n";
        exit;
    }
    if ($action === 'fingerprint') {
        echo json_encode(['ok' => true, 'action' => 'fingerprint', ...stage4cFixtureFingerprint($database, $slug)], JSON_THROW_ON_ERROR) . "\n";
        exit;
    }
    if ($action === 'session') {
        $fingerprint = stage4cFixtureFingerprint($database, $slug);
        startOwnerSession();
        establishVerifiedInternalUserSession((int) $fingerprint['user_id'], 1);
        echo json_encode(['ok' => true, 'action' => 'session', 'session_id' => session_id()], JSON_THROW_ON_ERROR) . "\n";
        exit;
    }
    stage4cFixtureFail('an allowed action is required.');
} catch (Throwable $exception) {
    fwrite(STDERR, 'Stage-4C rehearsal fixture failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
