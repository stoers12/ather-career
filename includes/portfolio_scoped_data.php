<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/project_technologies.php';
require_once __DIR__ . '/experience.php';
require_once __DIR__ . '/evidence_text_evaluator.php';

const AUTHORIZED_PERSONAL_INFO_FIELDS = [
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
    'public_contact_visible',
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
        if ($field === 'public_contact_visible') {
            if (!in_array($value, [true, false, 0, 1], true)) {
                throw new InvalidArgumentException('Contact visibility must be a boolean.');
            }
            $normalized[$field] = (int) $value;
            continue;
        }
        if (!is_string($value) && $value !== null) {
            throw new InvalidArgumentException("Personal information field {$field} is invalid.");
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
                instagram_url, facebook_url, website_url, profile_image_path, public_contact_visible, updated_at
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
                instagram_url, facebook_url, website_url, profile_image_path, public_contact_visible, updated_at
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

function updateAuthorizedPersonalInfo(PDO $database, AuthorizedPortfolioContext $context, int $profileId, array $values): bool
{
    if ($profileId < 1) {
        return false;
    }

    $values = authorizedPersonalInfoValues($values);
    if ($values === []) {
        throw new InvalidArgumentException('Personal information update is empty.');
    }

    $assignments = array_map(static fn (string $field): string => "{$field} = :{$field}", array_keys($values));
    $statement = $database->prepare(
        'UPDATE personal_info
         SET ' . implode(', ', $assignments) . '
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute([
        ...$values,
        'resource_id' => $profileId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);

    if ($statement->rowCount() === 1) {
        return true;
    }

    // MySQL reports zero affected rows when an Owner resubmits unchanged
    // values. Confirm the same scoped record still exists so that case is a
    // successful no-op, while a missing or cross-Owner record remains false.
    $exists = $database->prepare(
        'SELECT 1
         FROM personal_info
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id
         LIMIT 1'
    );
    $exists->execute([
        'resource_id' => $profileId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);

    return (int) $exists->fetchColumn() === 1;
}

/** @return list<array<string, mixed>> */
function listAuthorizedSkills(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT id, skill_name, created_at
         FROM skills
         WHERE portfolio_id = :authorized_portfolio_id
         ORDER BY created_at ASC, id ASC'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId]);

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
function listAuthorizedProjects(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT id, title, category, description, github_url, image_path, technologies, created_at
         FROM projects
         WHERE portfolio_id = :authorized_portfolio_id
         ORDER BY created_at DESC, id DESC'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId]);

    $projects = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($projects as &$project) {
        $project['technologies'] = projectTechnologiesFromStorage($project['technologies'] ?? null);
    }
    unset($project);

    return $projects;
}

/** @return array<string, mixed>|null */
function findAuthorizedProject(PDO $database, AuthorizedPortfolioContext $context, int $projectId): ?array
{
    if ($projectId < 1) {
        return null;
    }

    $statement = $database->prepare(
        'SELECT id, title, category, description, github_url, image_path, technologies, created_at
         FROM projects
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id
         LIMIT 1'
    );
    $statement->execute([
        'resource_id' => $projectId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);
    $project = $statement->fetch(PDO::FETCH_ASSOC);

    if ($project === false) {
        return null;
    }

    $project['technologies'] = projectTechnologiesFromStorage($project['technologies'] ?? null);

    return $project;
}

function createAuthorizedProject(
    PDO $database,
    AuthorizedPortfolioContext $context,
    string $title,
    string $category,
    string $description,
    ?string $githubUrl,
    ?string $imagePath,
    array $technologies,
    ?array $evidenceFields = null,
): int {
    $evidenceValues = projectEvidenceStorageValues($evidenceFields);
    if ($evidenceValues === []) {
        // Preserve the legacy column set when no private evidence is supplied.
        $statement = $database->prepare(
            'INSERT INTO projects (portfolio_id, title, category, description, github_url, image_path, technologies)
             VALUES (:authorized_portfolio_id, :title, :category, :description, :github_url, :image_path, :technologies)'
        );
    } else {
        $evidenceColumns = array_keys($evidenceValues);
        $columns = ['portfolio_id', 'title', 'category', 'description', 'github_url', 'image_path', 'technologies', ...$evidenceColumns];
        $parameters = [':authorized_portfolio_id', ':title', ':category', ':description', ':github_url', ':image_path', ':technologies', ...array_map(static fn (string $field): string => ':' . $field, $evidenceColumns)];
        $statement = $database->prepare(
            'INSERT INTO projects (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $parameters) . ')'
        );
    }
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'github_url' => $githubUrl,
        'image_path' => $imagePath,
        'technologies' => projectTechnologiesToStorage($technologies),
        ...$evidenceValues,
    ]);

    return (int) $database->lastInsertId();
}

function updateAuthorizedProject(
    PDO $database,
    AuthorizedPortfolioContext $context,
    int $projectId,
    string $title,
    string $category,
    string $description,
    ?string $githubUrl,
    ?string $imagePath,
    array $technologies,
    ?array $evidenceFields = null,
): bool {
    if ($projectId < 1) {
        return false;
    }

    $evidenceValues = projectEvidenceStorageValues($evidenceFields);
    $technologyValue = projectTechnologiesToStorage($technologies);
    $existingStatement = $database->prepare(
        'SELECT projects.title, projects.category, projects.description, projects.github_url,
                projects.image_path, projects.technologies, projects.problem_statement,
                projects.personal_role, projects.measurable_outcome
         FROM projects
         JOIN portfolios ON portfolios.id = projects.portfolio_id
         WHERE projects.id = :resource_id
           AND projects.portfolio_id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         LIMIT 1'
    );
    $existingStatement->execute([
        'resource_id' => $projectId,
        'authorized_portfolio_id' => $context->portfolioId,
        'authorized_user_id' => $context->userId,
    ]);
    $existing = $existingStatement->fetch(PDO::FETCH_ASSOC);
    if ($existing === false) {
        return false;
    }
    $newValues = [
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'github_url' => $githubUrl,
        'image_path' => $imagePath,
        'technologies' => $technologyValue,
        ...$evidenceValues,
    ];
    $changed = false;
    foreach ($newValues as $field => $value) {
        if ($field === 'technologies') {
            $storedTechnologies = parseProjectTechnologiesStorage($existing['technologies'] ?? null);
            if ($storedTechnologies['storage_state'] !== 'valid' || $storedTechnologies['labels'] !== $technologies) {
                $changed = true;
            }
            continue;
        }
        if ($existing[$field] !== $value) {
            $changed = true;
            break;
        }
    }
    if (!$changed) {
        return true;
    }

    $evidenceAssignments = array_map(static fn (string $field): string => "{$field} = :{$field}", array_keys($evidenceValues));
    $timestampExpression = $database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? "strftime('%Y-%m-%dT%H:%M:%fZ', 'now')"
        : 'CURRENT_TIMESTAMP(6)';
    $statement = $database->prepare(
        'UPDATE projects
         SET title = :title, category = :category, description = :description,
             github_url = :github_url, image_path = :image_path, technologies = :technologies,
             updated_at = ' . $timestampExpression .
             ($evidenceAssignments === [] ? '' : ', ' . implode(', ', $evidenceAssignments)) . '
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute([
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'github_url' => $githubUrl,
        'image_path' => $imagePath,
        'technologies' => $technologyValue,
        ...$evidenceValues,
        'resource_id' => $projectId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);

    return $statement->rowCount() === 1;
}

function deleteAuthorizedProject(PDO $database, AuthorizedPortfolioContext $context, int $projectId): bool
{
    if ($projectId < 1) {
        return false;
    }

    $statement = $database->prepare(
        'DELETE FROM projects
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute([
        'resource_id' => $projectId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);

    return $statement->rowCount() === 1;
}

/** @return list<array<string, mixed>> */
function listAuthorizedProjectEvidence(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT id, problem_statement, personal_role, measurable_outcome
         FROM projects
         WHERE portfolio_id = :authorized_portfolio_id
         ORDER BY created_at DESC, id DESC'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, ?string> */
function projectEvidenceStorageValues(?array $evidenceFields): array
{
    if ($evidenceFields === null) {
        return [];
    }
    if (array_diff(array_keys($evidenceFields), evidenceTextFieldNames()) !== []) {
        throw new InvalidArgumentException('Project evidence contains an unsupported field.');
    }

    $storedValues = [];
    foreach ($evidenceFields as $field => $value) {
        $evaluation = evaluateEvidenceText($field, $value);
        if ($evaluation['storage_validity'] !== 'valid') {
            throw new InvalidArgumentException("Project evidence field {$field} is invalid.");
        }
        $storedValues[$field] = $evaluation['stored_value'];
    }

    return $storedValues;
}

/**
 * Managed project keys are generated per upload, but retirement stays
 * conservative if a legacy or manually repaired row references the same key.
 */
function authorizedProjectImageIsUnreferenced(PDO $database, AuthorizedPortfolioContext $context, ?string $imagePath): bool
{
    if ($imagePath === null || $imagePath === '') {
        return false;
    }

    $statement = $database->prepare(
        'SELECT 1
         FROM projects
         WHERE portfolio_id = :authorized_portfolio_id
           AND image_path = :image_path
         LIMIT 1'
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'image_path' => $imagePath,
    ]);

    return $statement->fetchColumn() === false;
}

/** @return list<array<string, mixed>> */
function listAuthorizedExperiences(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT id, experience_type, role_title, organization, location, start_month, end_month,
                is_current, description, created_at, updated_at
         FROM experiences
         WHERE portfolio_id = :authorized_portfolio_id
         ORDER BY start_month DESC, id DESC'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed>|null */
function findAuthorizedExperience(PDO $database, AuthorizedPortfolioContext $context, int $experienceId): ?array
{
    if ($experienceId < 1) {
        return null;
    }

    $statement = $database->prepare(
        'SELECT id, experience_type, role_title, organization, location, start_month, end_month,
                is_current, description, created_at, updated_at
         FROM experiences
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id
         LIMIT 1'
    );
    $statement->execute([
        'resource_id' => $experienceId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);
    $experience = $statement->fetch(PDO::FETCH_ASSOC);

    return $experience === false ? null : $experience;
}

/** @param array<string, mixed> $values */
function createAuthorizedExperience(PDO $database, AuthorizedPortfolioContext $context, array $values, ?string $referenceMonth = null): int
{
    $values = authorizedExperienceValues($values, $referenceMonth);
    $statement = $database->prepare(
        'INSERT INTO experiences (
            portfolio_id, experience_type, role_title, organization, location,
            start_month, end_month, is_current, description
         ) VALUES (
            :authorized_portfolio_id, :experience_type, :role_title, :organization, :location,
            :start_month, :end_month, :is_current, :description
         )'
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        ...$values,
    ]);

    return (int) $database->lastInsertId();
}

/** @param array<string, mixed> $values */
function updateAuthorizedExperience(PDO $database, AuthorizedPortfolioContext $context, int $experienceId, array $values, ?string $referenceMonth = null): bool
{
    if ($experienceId < 1) {
        return false;
    }

    $values = authorizedExperienceValues($values, $referenceMonth);
    $statement = $database->prepare(
        'UPDATE experiences
         SET experience_type = :experience_type,
             role_title = :role_title,
             organization = :organization,
             location = :location,
             start_month = :start_month,
             end_month = :end_month,
             is_current = :is_current,
             description = :description
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute([
        ...$values,
        'resource_id' => $experienceId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);

    return $statement->rowCount() === 1;
}

function deleteAuthorizedExperience(PDO $database, AuthorizedPortfolioContext $context, int $experienceId): bool
{
    if ($experienceId < 1) {
        return false;
    }

    $statement = $database->prepare(
        'DELETE FROM experiences
         WHERE id = :resource_id
           AND portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute([
        'resource_id' => $experienceId,
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
