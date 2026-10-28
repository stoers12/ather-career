<?php

declare(strict_types=1);

// Owner-scoped experience data access. Loaded by portfolio_scoped_data.php.

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
