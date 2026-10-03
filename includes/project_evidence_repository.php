<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/evidence_text_evaluator.php';

/** @return array{problem:string,personal_role:string,measurable_outcome:string} */
function projectEvidenceLogicalToStorageFields(): array
{
    return [
        'problem' => 'problem_statement',
        'personal_role' => 'personal_role',
        'measurable_outcome' => 'measurable_outcome',
    ];
}

/** @param array<string, mixed> $record @return array{problem:mixed,personal_role:mixed,measurable_outcome:mixed} */
function projectEvidenceLogicalValuesFromStorage(array $record): array
{
    $values = [];
    foreach (projectEvidenceLogicalToStorageFields() as $logical => $storage) {
        $values[$logical] = $record[$storage] ?? null;
    }
    return $values;
}

/** @param array<string, mixed> $record @return array{problem:array<string,mixed>,personal_role:array<string,mixed>,measurable_outcome:array<string,mixed>} */
function projectEvidenceLogicalEvaluationsFromStorage(array $record): array
{
    return projectEvidenceLogicalEvaluations(projectEvidenceLogicalValuesFromStorage($record));
}

/** @param array<string, mixed> $values @return array{problem:array<string,mixed>,personal_role:array<string,mixed>,measurable_outcome:array<string,mixed>} */
function projectEvidenceLogicalEvaluations(array $values): array
{
    $evaluations = [];
    foreach (projectEvidenceLogicalToStorageFields() as $logical => $storage) {
        $evaluations[$logical] = evaluateEvidenceText($storage, $values[$logical]);
    }
    return $evaluations;
}

/** @return array{id:int,title:string,values:array<string,mixed>,updated_at:?string}|null */
function findAuthorizedProjectEvidenceForEdit(PDO $database, AuthorizedPortfolioContext $context, int $projectId, bool $forUpdate = false): ?array
{
    if ($projectId < 1) {
        return null;
    }
    $lock = $forUpdate && $database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $statement = $database->prepare(
        'SELECT projects.id, projects.title, projects.problem_statement,
                projects.personal_role, projects.measurable_outcome, projects.updated_at
         FROM projects
         JOIN portfolios ON portfolios.id = projects.portfolio_id
         WHERE projects.id = :resource_id
           AND projects.portfolio_id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         LIMIT 1' . $lock
    );
    $statement->execute([
        'resource_id' => $projectId,
        'authorized_portfolio_id' => $context->portfolioId,
        'authorized_user_id' => $context->userId,
    ]);
    $record = $statement->fetch(PDO::FETCH_ASSOC);
    if ($record === false) {
        return null;
    }
    $id = authorizationPositiveInteger($record['id'] ?? null);
    if ($id === null || !is_string($record['title'] ?? null)) {
        throw new RuntimeException('Project evidence record is invalid.');
    }
    $updatedAt = $record['updated_at'] ?? null;
    if ($updatedAt !== null && !is_string($updatedAt)) {
        throw new RuntimeException('Project evidence timestamp is invalid.');
    }
    return ['id' => $id, 'title' => $record['title'], 'values' => projectEvidenceLogicalValuesFromStorage($record), 'updated_at' => $updatedAt];
}

/**
 * The caller owns the transaction. The three validated fields are saved in one
 * statement, and an unchanged normalized value set leaves updated_at intact.
 *
 * @param array{problem:string,personal_role:string,measurable_outcome:string} $values
 */
function saveAuthorizedProjectEvidence(PDO $database, AuthorizedPortfolioContext $context, int $projectId, array $values): bool
{
    if (array_keys($values) !== array_keys(projectEvidenceLogicalToStorageFields())) {
        throw new InvalidArgumentException('Project evidence field set is invalid.');
    }
    $existing = findAuthorizedProjectEvidenceForEdit($database, $context, $projectId, true);
    if ($existing === null) {
        return false;
    }
    $stored = [];
    $changed = false;
    foreach (projectEvidenceLogicalToStorageFields() as $logical => $column) {
        $next = evaluateEvidenceText($column, $values[$logical]);
        $previous = evaluateEvidenceText($column, $existing['values'][$logical]);
        if ($next['storage_validity'] !== 'valid') {
            throw new InvalidArgumentException('Project evidence field is invalid.');
        }
        $stored[$logical] = $next['stored_value'];
        if ($next['analytical_text'] !== $previous['analytical_text']) {
            $changed = true;
        }
    }
    if (!$changed) {
        return true;
    }
    $timestamp = $database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? "strftime('%Y-%m-%dT%H:%M:%fZ', 'now')" : 'CURRENT_TIMESTAMP(6)';
    $statement = $database->prepare(
        'UPDATE projects
         SET problem_statement = :problem, personal_role = :personal_role,
             measurable_outcome = :measurable_outcome, updated_at = ' . $timestamp . '
         WHERE id = :resource_id AND portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute([
        ...$stored,
        'resource_id' => $projectId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);
    return $statement->rowCount() === 1;
}
