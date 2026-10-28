<?php

declare(strict_types=1);

// Owner-scoped project, project evidence and project media data access. Loaded by portfolio_scoped_data.php.

/** @return list<array<string, mixed>> */
function listAuthorizedProjects(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT id, title, category, description, github_url, image_path, technologies, created_at, is_published
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
        'SELECT id, title, category, description, github_url, image_path, technologies, created_at, is_published
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
            'INSERT INTO projects (portfolio_id, title, category, description, github_url, image_path, technologies, is_published)
             VALUES (:authorized_portfolio_id, :title, :category, :description, :github_url, :image_path, :technologies, 0)'
        );
    } else {
        $evidenceColumns = array_keys($evidenceValues);
        $columns = ['portfolio_id', 'title', 'category', 'description', 'github_url', 'image_path', 'technologies', 'is_published', ...$evidenceColumns];
        $parameters = [':authorized_portfolio_id', ':title', ':category', ':description', ':github_url', ':image_path', ':technologies', '0', ...array_map(static fn (string $field): string => ':' . $field, $evidenceColumns)];
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
