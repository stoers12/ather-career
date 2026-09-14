<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/project_technologies.php';

/** @return list<array{project_ref: int, problem_statement: mixed, personal_role: mixed, measurable_outcome: mixed, created_at: string, technologies: list<string>}> */
function loadAuthorizedEvidenceHubProjectFacts(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT projects.id, projects.problem_statement, projects.personal_role, projects.measurable_outcome,
                projects.created_at, projects.technologies
         FROM projects
         JOIN portfolios ON portfolios.id = projects.portfolio_id
         WHERE projects.portfolio_id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         ORDER BY projects.created_at ASC, projects.id ASC'
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'authorized_user_id' => $context->userId,
    ]);

    $facts = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $projectRef = authorizationPositiveInteger($record['id'] ?? null);
        if ($projectRef === null || !is_string($record['created_at'] ?? null)) {
            throw new RuntimeException('Evidence Hub project facts are invalid.');
        }
        $facts[] = [
            'project_ref' => $projectRef,
            'problem_statement' => $record['problem_statement'] ?? null,
            'personal_role' => $record['personal_role'] ?? null,
            'measurable_outcome' => $record['measurable_outcome'] ?? null,
            'created_at' => $record['created_at'],
            'technologies' => projectTechnologiesFromStorage($record['technologies'] ?? null),
        ];
    }

    return $facts;
}

function loadAuthorizedEvidenceHubPublicationState(PDO $database, AuthorizedPortfolioContext $context): string
{
    $statement = $database->prepare(
        'SELECT public_slug, is_published
         FROM portfolios
         WHERE id = :authorized_portfolio_id
           AND owner_user_id = :authorized_user_id
         LIMIT 1'
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'authorized_user_id' => $context->userId,
    ]);
    $record = $statement->fetch(PDO::FETCH_ASSOC);
    if ($record === false) {
        throw new AuthorizationDeniedException('Portfolio authorization failed.');
    }
    if (!is_string($record['public_slug'] ?? null) || trim($record['public_slug']) === '') {
        return 'not_configured';
    }

    return (int) ($record['is_published'] ?? 0) === 1 ? 'published' : 'unpublished';
}
