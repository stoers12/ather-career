<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/project_technologies.php';
require_once __DIR__ . '/project_presentation.php';
require_once __DIR__ . '/transaction.php';
require_once __DIR__ . '/validation.php';

const PUBLIC_SLUG_MIN_LENGTH = 3;
const PUBLIC_SLUG_MAX_LENGTH = 64;
const PUBLIC_SLUG_RESERVED = ['admin', 'api', 'health', 'login', 'logout', 'owner', 'p'];

final class PublicLifecycleValidationException extends RuntimeException
{
}

final class PublicLifecycleConflictException extends RuntimeException
{
}

final class PublicLifecycleWriteUncertainException extends RuntimeException
{
}

/** Publication owns its short transaction. Reject ambient use before touching
 * it: neither a caller's snapshot nor its lock order can authorize this write.
 * Once COMMIT is attempted, rollback cannot prove that COMMIT did not land.
 */
function runPublicLifecycleWriteTransaction(PDO $database, callable $operation): mixed
{
    if ($database->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION || $database->inTransaction()) {
        throw new LogicException('Publication writes require exception mode and an owned transaction.');
    }
    if (!$database->beginTransaction()) { throw new RuntimeException('Publication write could not start.'); }
    $commitAttempted = false;
    try {
        $result = $operation();
        $commitAttempted = true;
        if (!$database->commit()) { throw new RuntimeException('Publication commit was not acknowledged.'); }
        return $result;
    } catch (Throwable $exception) {
        $rolledBack = false;
        try { $rolledBack = $database->inTransaction() && $database->rollBack(); } catch (Throwable) {}
        if (!$commitAttempted && $rolledBack) { throw $exception; }
        throw new PublicLifecycleWriteUncertainException('Publication outcome is uncertain; reload before another change.', 0, $exception);
    }
}

/** Current authorization, in Profile's User -> Portfolio order. Every read
 * deciding write authority is a locking/current read, including journey proof.
 * A supplied context identifies the expected resource; it grants no authority.
 */
function lockPublicLifecycleOwner(PDO $database, AuthorizedPortfolioContext $context): void
{
    $user = requireAuthenticatedUser($database, true);
    if ($user->userId !== $context->userId) { throw new AuthorizationDeniedException('Publication User mismatch.'); }
    $lock = $database->prepare('SELECT id FROM portfolios WHERE id = ? AND owner_user_id = ? FOR UPDATE');
    $lock->execute([$context->portfolioId, $user->userId]);
    if ($lock->fetchColumn() === false) { throw new AuthorizationDeniedException('Publication ownership missing.'); }
    $journey = ownerJourneyState($database, $user, true);
    if (!$journey['access'] || $journey['portfolio_id'] !== $context->portfolioId) {
        throw new AuthorizationDeniedException('Publication journey authorization failed.');
    }
}

/**
 * A public read capability derived only from a published slug, current
 * publication state, and the owning User account status.
 */
final readonly class PublicReadContext
{
    private function __construct(
        public int $portfolioId,
    ) {
    }

    public static function fromPublishedPortfolio(int $portfolioId): self
    {
        if ($portfolioId < 1) {
            throw new LogicException('Published Portfolio ID is invalid.');
        }

        return new self($portfolioId);
    }
}

function normalizePublicSlug(mixed $candidate): ?string
{
    if (!is_string($candidate)) {
        return null;
    }

    $slug = strtolower(trim($candidate));
    $length = strlen($slug);
    if ($length < PUBLIC_SLUG_MIN_LENGTH
        || $length > PUBLIC_SLUG_MAX_LENGTH
        || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
        return null;
    }

    return $slug;
}

function requirePublicSlug(mixed $candidate): string
{
    $slug = normalizePublicSlug($candidate);
    if ($slug === null) {
        throw new PublicLifecycleValidationException('Use 3–64 lowercase letters, numbers, and single hyphens.');
    }
    if (in_array($slug, PUBLIC_SLUG_RESERVED, true)) {
        throw new PublicLifecycleValidationException('That public slug is reserved.');
    }

    return $slug;
}

/** @return array{public_slug: string|null, is_published: int, published_at: string|null} */
function ownedPublicLifecycleState(PDO $database, AuthorizedPortfolioContext $context, bool $forUpdate = false): array
{
    $statement = $database->prepare(
        'SELECT public_slug, is_published, published_at
         FROM portfolios
         WHERE id = :authorized_portfolio_id
           AND owner_user_id = :authorized_user_id
         LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'authorized_user_id' => $context->userId,
    ]);
    $state = $statement->fetch(PDO::FETCH_ASSOC);
    if ($state === false) {
        throw new AuthorizationDeniedException('Portfolio authorization failed.');
    }

    return [
        'public_slug' => is_string($state['public_slug']) ? $state['public_slug'] : null,
        'is_published' => (int) $state['is_published'],
        'published_at' => is_string($state['published_at']) ? $state['published_at'] : null,
    ];
}

function publicLifecycleDuplicateKey(PDOException $exception): bool
{
    $driverCode = $exception->errorInfo[1] ?? null;

    return safePdoErrorCode($exception) === '23000'
        && (is_int($driverCode) || ctype_digit((string) $driverCode))
        && (int) $driverCode === 1062;
}

function setOwnedPublicSlug(PDO $database, AuthorizedPortfolioContext $context, mixed $candidate): string
{
    $slug = requirePublicSlug($candidate);
    try {
        return runPublicLifecycleWriteTransaction($database, static function () use ($database, $context, $slug): string {
            lockPublicLifecycleOwner($database, $context);
            $state = ownedPublicLifecycleState($database, $context, true);
            if ($state['published_at'] !== null) {
                throw new PublicLifecycleConflictException('Your public slug is permanent after first publication.');
            }

            $statement = $database->prepare(
                'UPDATE portfolios
                 SET public_slug = :public_slug
                 WHERE id = :authorized_portfolio_id
                   AND owner_user_id = :authorized_user_id
                   AND published_at IS NULL'
            );
            $statement->execute([
                'public_slug' => $slug,
                'authorized_portfolio_id' => $context->portfolioId,
                'authorized_user_id' => $context->userId,
            ]);

            $updated = ownedPublicLifecycleState($database, $context, true);
            if ($updated['published_at'] !== null) {
                throw new PublicLifecycleConflictException('Your public slug is permanent after first publication.');
            }
            if ($updated['public_slug'] !== $slug) {
                throw new PublicLifecycleConflictException('That public slug could not be saved.');
            }

            return $slug;
        });
    } catch (PDOException $exception) {
        if (publicLifecycleDuplicateKey($exception)) {
            throw new PublicLifecycleConflictException('That public slug is already in use.', 0, $exception);
        }
        throw $exception;
    }
}

function publishOwnedPortfolio(PDO $database, AuthorizedPortfolioContext $context): void
{
    runPublicLifecycleWriteTransaction($database, static function () use ($database, $context): void {
        lockPublicLifecycleOwner($database, $context);
        $state = ownedPublicLifecycleState($database, $context, true);
        $publicationChanged = $state['is_published'] !== 1;
        $slug = normalizePublicSlug($state['public_slug']);
        if ($slug === null || $slug !== $state['public_slug'] || in_array($slug, PUBLIC_SLUG_RESERVED, true)) {
            throw new PublicLifecycleValidationException('Set a valid public slug before publishing.');
        }

        $profile = $database->prepare(
            'SELECT full_name
             FROM personal_info
             WHERE portfolio_id = :authorized_portfolio_id
             LIMIT 1 FOR UPDATE'
        );
        $profile->execute(['authorized_portfolio_id' => $context->portfolioId]);
        $fullName = $profile->fetchColumn();
        if (!is_string($fullName) || trim($fullName) === '') {
            throw new PublicLifecycleValidationException('Add your professional name before publishing.');
        }

        $statement = $database->prepare(
            'UPDATE portfolios
             SET is_published = 1,
                 published_at = COALESCE(published_at, CURRENT_TIMESTAMP)
             WHERE id = :authorized_portfolio_id
               AND owner_user_id = :authorized_user_id'
        );
        $statement->execute([
            'authorized_portfolio_id' => $context->portfolioId,
            'authorized_user_id' => $context->userId,
        ]);
        if ($statement->rowCount() !== 1 && ownedPublicLifecycleState($database, $context, true)['is_published'] !== 1) {
            throw new RuntimeException('Portfolio publication could not be saved.');
        }
        if ($publicationChanged) {
            $projects = $database->prepare(
                'UPDATE projects SET updated_at = CURRENT_TIMESTAMP(6)
                 WHERE portfolio_id = :authorized_portfolio_id'
            );
            $projects->execute(['authorized_portfolio_id' => $context->portfolioId]);
        }
    });
}

function unpublishOwnedPortfolio(PDO $database, AuthorizedPortfolioContext $context): void
{
    runPublicLifecycleWriteTransaction($database, static function () use ($database, $context): void {
        lockPublicLifecycleOwner($database, $context);
        $publicationChanged = ownedPublicLifecycleState($database, $context, true)['is_published'] === 1;
        $statement = $database->prepare(
            'UPDATE portfolios
             SET is_published = 0
             WHERE id = :authorized_portfolio_id
               AND owner_user_id = :authorized_user_id'
        );
        $statement->execute([
            'authorized_portfolio_id' => $context->portfolioId,
            'authorized_user_id' => $context->userId,
        ]);
        if ($statement->rowCount() !== 1 && ownedPublicLifecycleState($database, $context, true)['is_published'] !== 0) {
            throw new RuntimeException('Portfolio unpublication could not be saved.');
        }
        if ($publicationChanged) {
            $projects = $database->prepare(
                'UPDATE projects SET updated_at = CURRENT_TIMESTAMP(6)
                 WHERE portfolio_id = :authorized_portfolio_id'
            );
            $projects->execute(['authorized_portfolio_id' => $context->portfolioId]);
        }
    });
}

function resolvePublicReadContext(PDO $database, mixed $candidate): ?PublicReadContext
{
    $slug = normalizePublicSlug($candidate);
    if ($slug === null) {
        return null;
    }

    $statement = $database->prepare(
        "SELECT portfolios.id
         FROM portfolios
         JOIN users ON users.id = portfolios.owner_user_id
         WHERE portfolios.public_slug = :public_slug
           AND portfolios.is_published = 1
           AND users.account_status = 'active'
         LIMIT 1"
    );
    $statement->execute(['public_slug' => $slug]);
    $portfolioId = authorizationPositiveInteger($statement->fetchColumn());

    return $portfolioId === null ? null : PublicReadContext::fromPublishedPortfolio($portfolioId);
}

/** @return array<string, mixed>|null */
function loadPublicPersonalInfo(PDO $database, PublicReadContext $context): ?array
{
    $statement = $database->prepare(
        'SELECT full_name, professional_title, hero_headline, location, about_me, work_description,
                linkedin_url, github_url, instagram_url, facebook_url, website_url, profile_image_path,
                CASE WHEN public_email_visible = 1 THEN email ELSE NULL END AS email,
                CASE WHEN public_phone_visible = 1 THEN phone_primary ELSE NULL END AS phone_primary
         FROM personal_info
         WHERE portfolio_id = :public_portfolio_id
         LIMIT 1'
    );
    $statement->execute(['public_portfolio_id' => $context->portfolioId]);
    $profile = $statement->fetch(PDO::FETCH_ASSOC);

    return $profile === false ? null : $profile;
}

/** @return list<array<string, mixed>> */
function listPublicSkills(PDO $database, PublicReadContext $context): array
{
    $statement = $database->prepare(
        'SELECT skill_name
         FROM skills
         WHERE portfolio_id = :public_portfolio_id
         ORDER BY created_at ASC, id ASC'
    );
    $statement->execute(['public_portfolio_id' => $context->portfolioId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** Fixed SQL, shared by HTML, allow-listed JSON and project media. A context
 * identifies the Portfolio; recheck current account and publication at query time.
 */
function publicProjectEligibilitySql(): string
{
    return " FROM projects JOIN portfolios ON portfolios.id=projects.portfolio_id
        JOIN users ON users.id=portfolios.owner_user_id
        WHERE projects.portfolio_id=:public_portfolio_id
          AND portfolios.is_published=1 AND users.account_status='active'
          AND projects.is_published=1";
}

function setOwnedProjectPublication(PDO $database, AuthorizedPortfolioContext $context, int $projectId, mixed $published): void
{
    if ($projectId < 1 || !in_array($published, [0, 1], true)) {
        throw new PublicLifecycleValidationException('Choose a valid project and publication value.');
    }
    runPublicLifecycleWriteTransaction($database, static function () use ($database, $context, $projectId, $published): void {
        lockPublicLifecycleOwner($database, $context);
        $query = $database->prepare('SELECT is_published FROM projects WHERE id=? AND portfolio_id=? FOR UPDATE');
        $query->execute([$projectId, $context->portfolioId]);
        $current = $query->fetchColumn();
        if ($current === false) { throw new AuthorizationDeniedException('Project publication ownership missing.'); }
        if ((int)$current === $published) { return; }
        $update = $database->prepare('UPDATE projects SET is_published=?, updated_at=CURRENT_TIMESTAMP(6) WHERE id=? AND portfolio_id=?');
        $update->execute([$published, $projectId, $context->portfolioId]);
        if ($update->rowCount() !== 1) { throw new RuntimeException('Project publication could not be saved.'); }
    });
}

/** @return list<array<string, mixed>> */
function listPublicProjects(PDO $database, PublicReadContext $context): array
{
    $statement = $database->prepare(
        'SELECT projects.id, projects.title, projects.category, projects.description, projects.github_url, projects.image_path, projects.technologies, projects.created_at'
        . publicProjectEligibilitySql() . ' ORDER BY projects.created_at DESC, projects.id DESC'
    );
    $statement->execute(['public_portfolio_id' => $context->portfolioId]);

    $projects = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($projects as &$project) {
        $project['technologies'] = projectTechnologiesFromStorage($project['technologies'] ?? null);
    }
    unset($project);

    return $projects;
}

/**
 * Build the deliberately small public JSON representation of Projects for a
 * resolved published Portfolio. Raw storage locators remain server-side: the
 * optional image URL is a scoped public-media capability instead.
 *
 * @return list<array{title: string, category: string, description: string, github_url?: string, technologies: list<string>, image_url?: string}>
 */
function listPublicProjectJsonPayload(PDO $database, PublicReadContext $context, string $slug): array
{
    $normalizedSlug = normalizePublicSlug($slug);
    if ($normalizedSlug === null) {
        throw new LogicException('Public Project JSON requires a canonical slug.');
    }

    $statement = $database->prepare(
        'SELECT projects.id, projects.title, projects.category, projects.description, projects.github_url, projects.image_path, projects.technologies'
        . publicProjectEligibilitySql() . ' ORDER BY projects.created_at DESC, projects.id DESC'
    );
    $statement->execute(['public_portfolio_id' => $context->portfolioId]);

    $projects = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $project = [
            'title' => (string) ($record['title'] ?? ''),
            'category' => (string) ($record['category'] ?? ''),
            'description' => (string) ($record['description'] ?? ''),
        ];

        $githubUrl = is_string($record['github_url'] ?? null) ? trim($record['github_url']) : '';
        $githubLength = utf8CharacterLength($githubUrl);
        if ($githubUrl !== '' && $githubLength !== null
            && $githubLength <= PROJECT_GITHUB_URL_MAX_LENGTH && isSafeHttpUrl($githubUrl)) {
            $project['github_url'] = $githubUrl;
        }
        $project['technologies'] = projectTechnologiesFromStorage($record['technologies'] ?? null);

        $imagePath = $record['image_path'] ?? null;
        $projectId = authorizationPositiveInteger($record['id'] ?? null);
        if (is_string($imagePath)
            && trim($imagePath) !== ''
            && $projectId !== null
            && publicProjectPresentationIsReadable($imagePath, $context->portfolioId)
        ) {
            $project['image_url'] = publicProjectMediaUrl($normalizedSlug, $projectId);
        }

        $projects[] = $project;
    }

    return $projects;
}

function publicProjectMediaUrl(string $slug, int $projectId): string
{
    return '/p/' . rawurlencode($slug) . '/media/project/' . $projectId;
}

function publicProjectPresentationIsReadable(string $originalKey, int $portfolioId): bool
{
    $presentationKey = projectPresentationKey($originalKey, $portfolioId);

    return $presentationKey !== null
        && privateMediaDescriptor($presentationKey, $portfolioId, 'project_presentation') !== null;
}

/** @return list<array{experience_type: string, role_title: string, organization: string, location: string|null, start_month: string, end_month: string|null, is_current: bool, description: string|null}> */
function listPublicExperiences(PDO $database, PublicReadContext $context): array
{
    $statement = $database->prepare(
        'SELECT experience_type, role_title, organization, location, start_month, end_month, is_current, description
         FROM experiences
         WHERE portfolio_id = :public_portfolio_id
         ORDER BY start_month DESC, id DESC'
    );
    $statement->execute(['public_portfolio_id' => $context->portfolioId]);

    $records = $statement->fetchAll(PDO::FETCH_ASSOC);
    $experiences = [];
    foreach ($records as $record) {
        $experiences[] = [
            'experience_type' => (string) $record['experience_type'],
            'role_title' => (string) $record['role_title'],
            'organization' => (string) $record['organization'],
            'location' => isset($record['location']) && is_string($record['location']) ? $record['location'] : null,
            'start_month' => (string) $record['start_month'],
            'end_month' => isset($record['end_month']) && is_string($record['end_month']) ? $record['end_month'] : null,
            'is_current' => (int) $record['is_current'] === 1,
            'description' => isset($record['description']) && is_string($record['description']) ? $record['description'] : null,
        ];
    }

    return $experiences;
}
