<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/presentation.php';

/** Evidence sources (B-06, docs/contracts/evidence-sources.md): Owner-private links supporting a Project's evidence. */
const PROJECT_EVIDENCE_SOURCE_LIMIT = 10;
const PROJECT_EVIDENCE_SOURCE_TITLE_MAX_LENGTH = 120;
const PROJECT_EVIDENCE_SOURCE_URL_MAX_LENGTH = 500;
const PROJECT_EVIDENCE_SOURCE_NOTE_MAX_LENGTH = 300;

final class ProjectEvidenceSourceLimitException extends RuntimeException
{
}

/** @return list<array{id:int,title:string,url:string,note:?string}> */
function listAuthorizedProjectEvidenceSources(PDO $database, AuthorizedPortfolioContext $context, int $projectId): array
{
    $statement = $database->prepare(
        'SELECT project_evidence_sources.id, project_evidence_sources.title, project_evidence_sources.url, project_evidence_sources.note
         FROM project_evidence_sources
         JOIN projects ON projects.id = project_evidence_sources.project_id
         WHERE project_evidence_sources.project_id = :resource_id
           AND project_evidence_sources.portfolio_id = :authorized_portfolio_id
           AND projects.portfolio_id = :project_portfolio_id
         ORDER BY project_evidence_sources.id'
    );
    $statement->execute(['resource_id' => $projectId, 'authorized_portfolio_id' => $context->portfolioId, 'project_portfolio_id' => $context->portfolioId]);

    return array_map('projectEvidenceSourceFromRow', $statement->fetchAll(PDO::FETCH_ASSOC));
}

/** Every source of the Portfolio, grouped by Project id, in insertion order. @return array<int, list<array{id:int,title:string,url:string,note:?string}>> */
function listAuthorizedPortfolioEvidenceSources(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT project_evidence_sources.id, project_evidence_sources.project_id, project_evidence_sources.title, project_evidence_sources.url, project_evidence_sources.note
         FROM project_evidence_sources
         JOIN projects ON projects.id = project_evidence_sources.project_id
         WHERE project_evidence_sources.portfolio_id = :authorized_portfolio_id
           AND projects.portfolio_id = :project_portfolio_id
         ORDER BY project_evidence_sources.project_id, project_evidence_sources.id'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId, 'project_portfolio_id' => $context->portfolioId]);
    $grouped = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[(int) $row['project_id']][] = projectEvidenceSourceFromRow($row);
    }

    return $grouped;
}

/** @param array<string, mixed> $row @return array{id:int,title:string,url:string,note:?string} */
function projectEvidenceSourceFromRow(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'url' => (string) $row['url'],
        'note' => is_string($row['note'] ?? null) ? $row['note'] : null,
    ];
}

/**
 * Validates an add-source form. `values` echo the submission for re-rendering;
 * `source` is set only when there are no errors.
 *
 * @param array<string, mixed> $post
 * @return array{values: array{title:string,url:string,note:string}, errors: array<string,string>, source: ?array{title:string,url:string,note:?string}}
 */
function validateProjectEvidenceSourceSubmission(array $post): array
{
    $values = [];
    $errors = [];
    foreach (['title' => PROJECT_EVIDENCE_SOURCE_TITLE_MAX_LENGTH, 'url' => PROJECT_EVIDENCE_SOURCE_URL_MAX_LENGTH, 'note' => PROJECT_EVIDENCE_SOURCE_NOTE_MAX_LENGTH] as $field => $maximum) {
        $raw = $post[$field] ?? '';
        if (!is_string($raw)) {
            $values[$field] = '';
            $errors[$field] = 'Please enter text for this field.';
            continue;
        }
        $values[$field] = trim($raw);
        $length = utf8CharacterLength($values[$field]);
        if ($length === null) {
            $errors[$field] = 'Please enter valid text.';
        } elseif ($length > $maximum) {
            $errors[$field] = $field === 'note' ? 'Keep the note to 300 characters or fewer.' : ($field === 'title' ? 'Keep the title to 120 characters or fewer.' : 'Keep the link to 500 characters or fewer.');
        }
    }
    if (!isset($errors['title']) && $values['title'] === '') {
        $errors['title'] = 'Give the source a name.';
    }
    if (!isset($errors['url']) && ($values['url'] === '' || !isPublicWebsiteDestination($values['url']))) {
        $errors['url'] = 'Please enter a valid HTTP or HTTPS URL.';
    }

    return [
        'values' => $values,
        'errors' => $errors,
        'source' => $errors === [] ? ['title' => $values['title'], 'url' => $values['url'], 'note' => $values['note'] === '' ? null : $values['note']] : null,
    ];
}

/**
 * Adds a source inside the caller's transaction. The locked Project row makes the
 * limit hold across concurrent requests. Null when the Project is not the Owner's.
 *
 * @param array{title:string,url:string,note:?string} $source
 */
function addAuthorizedProjectEvidenceSource(PDO $database, AuthorizedPortfolioContext $context, int $projectId, array $source): ?int
{
    if (!$database->inTransaction()) {
        throw new LogicException('Adding an evidence source requires a transaction.');
    }
    $lock = $database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $project = $database->prepare('SELECT id FROM projects WHERE id = :resource_id AND portfolio_id = :authorized_portfolio_id LIMIT 1' . $lock);
    $project->execute(['resource_id' => $projectId, 'authorized_portfolio_id' => $context->portfolioId]);
    if ($project->fetchColumn() === false) {
        return null;
    }
    $count = $database->prepare('SELECT COUNT(*) FROM project_evidence_sources WHERE project_id = :resource_id AND portfolio_id = :authorized_portfolio_id');
    $count->execute(['resource_id' => $projectId, 'authorized_portfolio_id' => $context->portfolioId]);
    if ((int) $count->fetchColumn() >= PROJECT_EVIDENCE_SOURCE_LIMIT) {
        throw new ProjectEvidenceSourceLimitException('This project already has the maximum number of sources.');
    }
    $insert = $database->prepare(
        'INSERT INTO project_evidence_sources (portfolio_id, project_id, title, url, note)
         VALUES (:authorized_portfolio_id, :resource_id, :title, :url, :note)'
    );
    $insert->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'resource_id' => $projectId,
        'title' => $source['title'],
        'url' => $source['url'],
        'note' => $source['note'],
    ]);

    return (int) $database->lastInsertId();
}

function removeAuthorizedProjectEvidenceSource(PDO $database, AuthorizedPortfolioContext $context, int $projectId, int $sourceId): bool
{
    $statement = $database->prepare(
        'DELETE FROM project_evidence_sources
         WHERE id = :source_id AND project_id = :resource_id AND portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute(['source_id' => $sourceId, 'resource_id' => $projectId, 'authorized_portfolio_id' => $context->portfolioId]);

    return $statement->rowCount() === 1;
}
