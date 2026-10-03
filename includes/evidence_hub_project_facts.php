<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/project_technologies.php';
require_once __DIR__ . '/public_lifecycle.php';

/** @return list<array{project_ref: int, problem_statement: mixed, personal_role: mixed, measurable_outcome: mixed, recorded_at_epoch_seconds: int, technology_storage_state: string, technology_storage_reason_codes: list<string>, technologies: list<string>}> */
function loadAuthorizedEvidenceHubProjectFacts(PDO $database, AuthorizedPortfolioContext $context): array
{
    $recordedAtEpochExpression = evidenceHubProjectRecordedAtEpochExpression($database);
    $statement = $database->prepare(
        'SELECT projects.id, projects.problem_statement, projects.personal_role, projects.measurable_outcome,
                ' . $recordedAtEpochExpression . ' AS recorded_at_epoch_seconds, projects.technologies
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
        $recordedAtEpochSeconds = evidenceHubProjectRecordedAtEpochSeconds($record['recorded_at_epoch_seconds'] ?? null);
        if ($projectRef === null || $recordedAtEpochSeconds === null) {
            throw new RuntimeException('Evidence Hub project facts are invalid.');
        }
        $technologyStorage = parseProjectTechnologiesStorage($record['technologies'] ?? null);
        $facts[] = [
            'project_ref' => $projectRef,
            'problem_statement' => $record['problem_statement'] ?? null,
            'personal_role' => $record['personal_role'] ?? null,
            'measurable_outcome' => $record['measurable_outcome'] ?? null,
            'recorded_at_epoch_seconds' => $recordedAtEpochSeconds,
            'technology_storage_state' => $technologyStorage['storage_state'],
            'technology_storage_reason_codes' => $technologyStorage['reason_codes'],
            'technologies' => $technologyStorage['labels'],
        ];
    }

    return $facts;
}

function evidenceHubProjectRecordedAtEpochExpression(PDO $database): string
{
    return match ($database->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        // MySQL TIMESTAMP retains an absolute instant; UNIX_TIMESTAMP preserves it
        // regardless of the session timezone used to display the column.
        'mysql' => 'UNIX_TIMESTAMP(projects.created_at)',
        // The isolated SQLite harness must make its instant explicit too; it
        // cannot rely on SQLite's interpretation of a timezone-less string.
        'sqlite' => "CASE WHEN projects.created_at GLOB '*Z' OR projects.created_at GLOB '*[+-][0-9][0-9]:[0-9][0-9]' THEN CAST(strftime('%s', projects.created_at) AS INTEGER) ELSE NULL END",
        default => throw new RuntimeException('Evidence Hub project timestamp storage is unsupported.'),
    };
}

function evidenceHubProjectRecordedAtEpochSeconds(mixed $value): ?int
{
    if (is_int($value)) {
        return $value >= 0 ? $value : null;
    }
    if (!is_string($value) || preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,6})?$/D', $value) !== 1) {
        return null;
    }
    // MySQL TIMESTAMP(6) yields a decimal epoch; retain whole seconds without float rounding.
    $seconds = explode('.', $value, 2)[0];
    if (strlen($seconds) > strlen((string) PHP_INT_MAX) || (strlen($seconds) === strlen((string) PHP_INT_MAX) && $seconds > (string) PHP_INT_MAX)) {
        return null;
    }

    return (int) $seconds;
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

/** @return array{portfolio_published: bool, publication_prerequisites_met: bool} */
function loadAuthorizedEvidenceHubRecommendationPublicationFacts(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT portfolios.public_slug, portfolios.is_published, personal_info.full_name
         FROM portfolios
         LEFT JOIN personal_info ON personal_info.portfolio_id = portfolios.id
         WHERE portfolios.id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
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

    $publicSlug = is_string($record['public_slug'] ?? null) ? $record['public_slug'] : null;
    $normalizedSlug = normalizePublicSlug($publicSlug);
    $fullName = $record['full_name'] ?? null;

    return [
        'portfolio_published' => (int) ($record['is_published'] ?? 0) === 1,
        'publication_prerequisites_met' => $normalizedSlug !== null
            && $normalizedSlug === $publicSlug
            && !in_array($normalizedSlug, PUBLIC_SLUG_RESERVED, true)
            && is_string($fullName)
            && trim($fullName) !== '',
    ];
}
