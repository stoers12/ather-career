<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/error_reporting.php';

const PUBLIC_SLUG_MIN_LENGTH = 3;
const PUBLIC_SLUG_MAX_LENGTH = 64;
const PUBLIC_SLUG_RESERVED = ['admin', 'api', 'health', 'login', 'logout', 'owner', 'p'];

final class PublicLifecycleValidationException extends RuntimeException
{
}

final class PublicLifecycleConflictException extends RuntimeException
{
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
function ownedPublicLifecycleState(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT public_slug, is_published, published_at
         FROM portfolios
         WHERE id = :authorized_portfolio_id
           AND owner_user_id = :authorized_user_id
         LIMIT 1'
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
    $state = ownedPublicLifecycleState($database, $context);
    if ($state['published_at'] !== null) {
        throw new PublicLifecycleConflictException('Your public slug is permanent after first publication.');
    }

    try {
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
    } catch (PDOException $exception) {
        if (publicLifecycleDuplicateKey($exception)) {
            throw new PublicLifecycleConflictException('That public slug is already in use.', 0, $exception);
        }
        throw $exception;
    }

    $updated = ownedPublicLifecycleState($database, $context);
    if ($updated['published_at'] !== null) {
        throw new PublicLifecycleConflictException('Your public slug is permanent after first publication.');
    }
    if ($updated['public_slug'] !== $slug) {
        throw new PublicLifecycleConflictException('That public slug could not be saved.');
    }

    return $slug;
}

function publishOwnedPortfolio(PDO $database, AuthorizedPortfolioContext $context): void
{
    $state = ownedPublicLifecycleState($database, $context);
    $slug = normalizePublicSlug($state['public_slug']);
    if ($slug === null || $slug !== $state['public_slug'] || in_array($slug, PUBLIC_SLUG_RESERVED, true)) {
        throw new PublicLifecycleValidationException('Set a valid public slug before publishing.');
    }

    $profile = $database->prepare(
        'SELECT full_name
         FROM personal_info
         WHERE portfolio_id = :authorized_portfolio_id
         LIMIT 1'
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
    if ($statement->rowCount() !== 1 && ownedPublicLifecycleState($database, $context)['is_published'] !== 1) {
        throw new RuntimeException('Portfolio publication could not be saved.');
    }
}

function unpublishOwnedPortfolio(PDO $database, AuthorizedPortfolioContext $context): void
{
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
    if ($statement->rowCount() !== 1 && ownedPublicLifecycleState($database, $context)['is_published'] !== 0) {
        throw new RuntimeException('Portfolio unpublication could not be saved.');
    }
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
        'SELECT full_name, professional_title, location, about_me, work_description,
                linkedin_url, github_url, instagram_url, facebook_url, website_url, profile_image_path
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

/** @return list<array<string, mixed>> */
function listPublicProjects(PDO $database, PublicReadContext $context): array
{
    $statement = $database->prepare(
        'SELECT id, title, category, description, github_url, image_path, created_at
         FROM projects
         WHERE portfolio_id = :public_portfolio_id
         ORDER BY created_at ASC, id ASC'
    );
    $statement->execute(['public_portfolio_id' => $context->portfolioId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}
