<?php

declare(strict_types=1);

require_once __DIR__ . '/project_evidence_repository.php';

/**
 * Server view mode of /owner/projects/{id}/evidence (B-07 / FE-07 B1,
 * docs/contracts/evidence-hub-views.md). "read" shows the saved record and its
 * sources with no editable text, so a source action can never discard unsaved
 * text; "write" shows the one evidence text form, optionally opened at a part.
 *
 * @param array<string, mixed> $query
 * @return array{mode: 'read'|'write', part: ?string}
 */
function projectEvidenceViewFromQuery(array $query): array
{
    if (($query['mode'] ?? null) !== 'write') {
        return ['mode' => 'read', 'part' => null];
    }
    $part = $query['part'] ?? null;

    return ['mode' => 'write', 'part' => is_string($part) && array_key_exists($part, projectEvidenceLogicalToStorageFields()) ? $part : null];
}

/** A rejected text save re-renders "write" at its first invalid part. @param array<string, string> $fieldErrors @return array{mode: 'write', part: ?string} */
function projectEvidenceViewAfterTextError(array $fieldErrors): array
{
    foreach (array_keys(projectEvidenceLogicalToStorageFields()) as $part) {
        if (isset($fieldErrors[$part])) {
            return ['mode' => 'write', 'part' => $part];
        }
    }

    return ['mode' => 'write', 'part' => null];
}

/** @return array{read: string, write: string, sources: string} */
function projectEvidenceViewUrls(int $projectId): array
{
    $base = '/owner/projects/' . $projectId . '/evidence';

    return ['read' => $base, 'write' => $base . '?mode=write', 'sources' => $base . '#sources'];
}
