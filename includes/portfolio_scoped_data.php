<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/profile_version.php';
require_once __DIR__ . '/final_profile_fields.php';
require_once __DIR__ . '/project_technologies.php';
require_once __DIR__ . '/experience.php';
require_once __DIR__ . '/evidence_text_evaluator.php';
require_once __DIR__ . '/portfolio_scoped_projects.php';
require_once __DIR__ . '/portfolio_scoped_experiences.php';

const AUTHORIZED_PERSONAL_INFO_FIELDS = [
    ...FINAL_PROFILE_FIELDS,
    'full_name',
    'professional_title',
    'hero_headline',
    'email',
    'phone_primary',
    'phone_secondary',
    'location',
    'about_me',
    'work_description',
    'linkedin_url',
    'github_url',
    'instagram_url',
    'facebook_url',
    'website_url',
    'profile_image_path',
    'public_email_visible',
    'public_phone_visible',
];

/** @return array<string, mixed> */
function authorizedPersonalInfoValues(array $values): array
{
    if (array_diff(array_keys($values), AUTHORIZED_PERSONAL_INFO_FIELDS) !== []) {
        throw new InvalidArgumentException('Personal information contains an unsupported field.');
    }

    if (isset($values['portfolio_id']) || isset($values['id'])) {
        throw new InvalidArgumentException('Personal information cannot select a Portfolio.');
    }

    $normalized = [];
    foreach ($values as $field => $value) {
        if (in_array($field, ['public_email_visible', 'public_phone_visible'], true)) {
            if (!in_array($value, [true, false, 0, 1], true)) {
                throw new InvalidArgumentException('Contact visibility must be a boolean.');
            }
            $normalized[$field] = (int) $value;
            continue;
        }
        if (!is_string($value) && $value !== null) {
            throw new InvalidArgumentException("Personal information field {$field} is invalid.");
        }
        if (in_array($field, FINAL_PROFILE_FIELDS, true) && finalProfileFieldError($field, $value, finalProfileCatalog()) !== null) {
            throw new InvalidArgumentException('Invalid final profile field.');
        }
        $normalized[$field] = $value;
    }

    return $normalized;
}

/** @return array<string, mixed>|null */
function findAuthorizedPersonalInfo(PDO $database, AuthorizedPortfolioContext $context, int $profileId): ?array
{
    if ($profileId < 1) {
        return null;
    }

    $statement = $database->prepare(
        'SELECT id, full_name, professional_title, hero_headline, email, phone_primary, phone_secondary,
                location, about_me, work_description, linkedin_url, github_url,
                instagram_url, facebook_url, website_url, profile_image_path, public_email_visible, public_phone_visible, updated_at, profile_version,
                country_code, preferred_language, professional_status, specialization
         FROM personal_info
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id
         LIMIT 1'
    );
    $statement->execute([
        'resource_id' => $profileId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);
    $profile = $statement->fetch(PDO::FETCH_ASSOC);

    return $profile === false ? null : $profile;
}

/** @return array<string, mixed>|null */
function loadAuthorizedPersonalInfo(PDO $database, AuthorizedPortfolioContext $context): ?array
{
    $statement = $database->prepare(
        'SELECT id, full_name, professional_title, hero_headline, email, phone_primary, phone_secondary,
                location, about_me, work_description, linkedin_url, github_url,
                instagram_url, facebook_url, website_url, profile_image_path, public_email_visible, public_phone_visible, updated_at, profile_version,
                country_code, preferred_language, professional_status, specialization
         FROM personal_info
         WHERE portfolio_id = :authorized_portfolio_id
         LIMIT 1'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId]);
    $profile = $statement->fetch(PDO::FETCH_ASSOC);

    return $profile === false ? null : $profile;
}

function createAuthorizedPersonalInfo(PDO $database, AuthorizedPortfolioContext $context, array $values): int
{
    $values = authorizedPersonalInfoValues($values);
    if (!isset($values['full_name']) || !is_string($values['full_name']) || $values['full_name'] === '') {
        throw new InvalidArgumentException('Personal information requires a full name.');
    }

    $columns = ['portfolio_id', ...array_keys($values)];
    $parameters = [':authorized_portfolio_id', ...array_map(static fn (string $field): string => ':' . $field, array_keys($values))];
    $statement = $database->prepare(
        'INSERT INTO personal_info (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', $parameters) . ')'
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        ...$values,
    ]);

    return (int) $database->lastInsertId();
}

function updateAuthorizedPersonalInfo(PDO $database, AuthorizedPortfolioContext $context, int $profileId, array $values, int $expectedVersion): bool
{
    if ($profileId < 1) {
        return false;
    }

    $values = authorizedPersonalInfoValues($values);
    if ($expectedVersion < 1 || $expectedVersion >= PROFILE_MAX_VERSION) { return false; }

    $assignments = array_map(static fn (string $field): string => "{$field} = :{$field}", array_keys($values));
    $assignments[] = 'profile_version = profile_version + 1';
    $statement = $database->prepare(
        'UPDATE personal_info
         SET ' . implode(', ', $assignments) . '
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id
           AND profile_version = :expected_profile_version
           AND profile_version < 2147483647'
    );
    $statement->execute([
        ...$values,
        'resource_id' => $profileId,
        'authorized_portfolio_id' => $context->portfolioId,
        'expected_profile_version' => $expectedVersion,
    ]);
    return $statement->rowCount() === 1;
}

/** @return list<array<string, mixed>> */
function listAuthorizedSkills(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT skills.id, skills.skill_name, skills.created_at
         FROM skills
         JOIN portfolios ON portfolios.id = skills.portfolio_id
         WHERE skills.portfolio_id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         ORDER BY skills.created_at ASC, skills.id ASC'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId, 'authorized_user_id' => $context->userId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed>|null */
function findAuthorizedSkill(PDO $database, AuthorizedPortfolioContext $context, int $skillId): ?array
{
    if ($skillId < 1) {
        return null;
    }

    $statement = $database->prepare(
        'SELECT id, skill_name, created_at
         FROM skills
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id
         LIMIT 1'
    );
    $statement->execute([
        'resource_id' => $skillId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);
    $skill = $statement->fetch(PDO::FETCH_ASSOC);

    return $skill === false ? null : $skill;
}

function createAuthorizedSkill(PDO $database, AuthorizedPortfolioContext $context, string $skillName): int
{
    $statement = $database->prepare(
        'INSERT INTO skills (portfolio_id, skill_name)
         VALUES (:authorized_portfolio_id, :skill_name)'
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'skill_name' => $skillName,
    ]);

    return (int) $database->lastInsertId();
}

function updateAuthorizedSkill(PDO $database, AuthorizedPortfolioContext $context, int $skillId, string $skillName): bool
{
    if ($skillId < 1) {
        return false;
    }

    $statement = $database->prepare(
        'UPDATE skills
         SET skill_name = :skill_name
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute([
        'skill_name' => $skillName,
        'resource_id' => $skillId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);

    return $statement->rowCount() === 1;
}

function deleteAuthorizedSkill(PDO $database, AuthorizedPortfolioContext $context, int $skillId): bool
{
    if ($skillId < 1) {
        return false;
    }

    $statement = $database->prepare(
        'DELETE FROM skills
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute([
        'resource_id' => $skillId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);

    return $statement->rowCount() === 1;
}

/** @return list<array<string, mixed>> */
function listAuthorizedMessages(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT id, name, email, message, created_at
         FROM messages
         WHERE recipient_portfolio_id = :authorized_portfolio_id
         ORDER BY created_at DESC, id DESC'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed>|null */
function findAuthorizedMessage(PDO $database, AuthorizedPortfolioContext $context, int $messageId): ?array
{
    if ($messageId < 1) {
        return null;
    }

    $statement = $database->prepare(
        'SELECT id, name, email, message, created_at
         FROM messages
         WHERE id = :resource_id
           AND recipient_portfolio_id = :authorized_portfolio_id
         LIMIT 1'
    );
    $statement->execute([
        'resource_id' => $messageId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);
    $message = $statement->fetch(PDO::FETCH_ASSOC);

    return $message === false ? null : $message;
}

/** @return array{project_count: int, skill_count: int, message_count: int, profile_count: int} */
function authorizedPortfolioDashboardAggregate(PDO $database, AuthorizedPortfolioContext $context): array
{
    $projectCount = $database->prepare('SELECT COUNT(*) FROM projects WHERE portfolio_id = :authorized_portfolio_id');
    $projectCount->execute(['authorized_portfolio_id' => $context->portfolioId]);
    $skillCount = $database->prepare('SELECT COUNT(*) FROM skills WHERE portfolio_id = :authorized_portfolio_id');
    $skillCount->execute(['authorized_portfolio_id' => $context->portfolioId]);
    $messageCount = $database->prepare('SELECT COUNT(*) FROM messages WHERE recipient_portfolio_id = :authorized_portfolio_id');
    $messageCount->execute(['authorized_portfolio_id' => $context->portfolioId]);
    $profileCount = $database->prepare('SELECT COUNT(*) FROM personal_info WHERE portfolio_id = :authorized_portfolio_id');
    $profileCount->execute(['authorized_portfolio_id' => $context->portfolioId]);

    return [
        'project_count' => (int) $projectCount->fetchColumn(),
        'skill_count' => (int) $skillCount->fetchColumn(),
        'message_count' => (int) $messageCount->fetchColumn(),
        'profile_count' => (int) $profileCount->fetchColumn(),
    ];
}
