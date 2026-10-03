<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_text_evaluator.php';

const EVIDENCE_HUB_CONTRACT_VERSION = '1.0.0';

final class EvidenceHubAggregationInvariantException extends RuntimeException
{
}

/**
 * @param array{project_ref: int, problem_statement: mixed, personal_role: mixed, measurable_outcome: mixed, recorded_at_epoch_seconds: int, technology_storage_state: string, technology_storage_reason_codes: list<string>, technologies: list<string>} $project
 * @return array<string, mixed>
 */
function evaluateEvidenceHubProjectDocumentation(array $project): array
{
    $evaluations = [];
    $complete = 0;
    foreach (evidenceTextFieldNames() as $field) {
        $evaluation = evaluateEvidenceText($field, $project[$field] ?? null);
        $evaluations[$field] = [
            'evidence_field' => $evaluation['evidence_field'],
            'rule_version' => $evaluation['rule_version'],
            'storage_validity' => $evaluation['storage_validity'],
            'evidence_status' => $evaluation['evidence_status'],
            'content_graphemes' => $evaluation['content_graphemes'],
            'useful_token_count' => $evaluation['useful_token_count'],
            'distinct_token_count' => $evaluation['distinct_token_count'],
            'dominant_token_count' => $evaluation['dominant_token_count'],
            'reason_codes' => $evaluation['reason_codes'],
        ];
        if ($evaluation['evidence_status'] === 'complete') {
            ++$complete;
        }
    }

    return [
        'project_ref' => $project['project_ref'],
        'recorded_at_epoch_seconds' => $project['recorded_at_epoch_seconds'],
        'technology_storage_state' => $project['technology_storage_state'],
        'technology_storage_reason_codes' => $project['technology_storage_reason_codes'],
        'technologies' => $project['technologies'],
        'field_evaluations' => $evaluations,
        'expected_evidence_fields' => 3,
        'complete_evidence_fields' => $complete,
        'project_has_complete_evidence' => $complete === 3,
    ];
}

/**
 * Counts are derived from the exact three frozen fields, never accepted from a
 * caller as authority. This prevents malformed internal facts from producing a
 * misleading Owner metric.
 *
 * @param array<string, mixed> $project
 * @return array{complete_evidence_fields: int, project_has_complete_evidence: bool, reason_codes: list<string>}
 */
function validateEvidenceHubProjectDocumentationFacts(array $project): array
{
    $evaluations = $project['field_evaluations'] ?? null;
    if (!is_array($evaluations) || array_is_list($evaluations)) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub project field evaluations are invalid.');
    }
    $fields = evidenceTextFieldNames();
    if (count($evaluations) !== count($fields) || array_diff(array_keys($evaluations), $fields) !== [] || array_diff($fields, array_keys($evaluations)) !== []) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub project evidence fields are incomplete.');
    }

    $complete = 0;
    $reasonCodes = [];
    foreach ($fields as $field) {
        $evaluation = $evaluations[$field] ?? null;
        if (!is_array($evaluation) || ($evaluation['evidence_field'] ?? null) !== $field) {
            throw new EvidenceHubAggregationInvariantException('Evidence Hub project evidence field identity is invalid.');
        }
        $state = $evaluation['evidence_status'] ?? null;
        if (!is_string($state) || !in_array($state, ['unavailable', 'needs_attention', 'complete'], true)) {
            throw new EvidenceHubAggregationInvariantException('Evidence Hub project evidence state is invalid.');
        }
        $fieldReasonCodes = $evaluation['reason_codes'] ?? null;
        if (!is_array($fieldReasonCodes) || !array_is_list($fieldReasonCodes)) {
            throw new EvidenceHubAggregationInvariantException('Evidence Hub project reason codes are invalid.');
        }
        foreach ($fieldReasonCodes as $reasonCode) {
            if (!is_string($reasonCode)) {
                throw new EvidenceHubAggregationInvariantException('Evidence Hub project reason code is invalid.');
            }
            $reasonCodes[$reasonCode] = true;
        }
        if ($state === 'complete') {
            ++$complete;
        }
    }

    if (($project['expected_evidence_fields'] ?? null) !== 3 || ($project['complete_evidence_fields'] ?? null) !== $complete || !is_bool($project['project_has_complete_evidence'] ?? null) || $project['project_has_complete_evidence'] !== ($complete === 3)) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub project evidence counts are inconsistent.');
    }
    ksort($reasonCodes, SORT_STRING);

    return [
        'complete_evidence_fields' => $complete,
        'project_has_complete_evidence' => $complete === 3,
        'reason_codes' => array_keys($reasonCodes),
    ];
}

/** @param list<array<string, mixed>> $projects
 * @return array{status: string, version: string, project_count: int, expected_evidence_field_count: int, complete_evidence_field_count: int, projects_with_complete_evidence: int, coverage_bps: int|null, evidence_confidence: string, reason_codes: list<string>}
 */
function aggregateEvidenceHubDocumentationCoverage(array $projects): array
{
    if (!array_is_list($projects)) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub project facts must be a list.');
    }
    $projectCount = count($projects);
    if ($projectCount === 0) {
        return [
            'status' => 'unavailable',
            'version' => EVIDENCE_HUB_CONTRACT_VERSION,
            'project_count' => 0,
            'expected_evidence_field_count' => 0,
            'complete_evidence_field_count' => 0,
            'projects_with_complete_evidence' => 0,
            'coverage_bps' => null,
            'evidence_confidence' => 'unavailable',
            'reason_codes' => ['NO_PROJECTS'],
        ];
    }

    $expected = $projectCount * 3;
    $complete = 0;
    $completeProjects = 0;
    $reasonCodes = [];
    foreach ($projects as $project) {
        if (!is_array($project)) {
            throw new EvidenceHubAggregationInvariantException('Evidence Hub project facts must be arrays.');
        }
        $validated = validateEvidenceHubProjectDocumentationFacts($project);
        $complete += $validated['complete_evidence_fields'];
        if ($validated['project_has_complete_evidence']) {
            ++$completeProjects;
        }
        foreach ($validated['reason_codes'] as $reasonCode) {
            $reasonCodes[$reasonCode] = true;
        }
    }
    if ($expected !== $projectCount * 3 || $complete < 0 || $complete > $expected) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub coverage totals are invalid.');
    }
    $coverage = intdiv($complete * 10000 + intdiv($expected, 2), $expected);
    if ($coverage < 0 || $coverage > 10000) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub coverage basis points are invalid.');
    }
    ksort($reasonCodes, SORT_STRING);

    return [
        'status' => $coverage === 10000 ? 'ready' : 'needs_attention',
        'version' => EVIDENCE_HUB_CONTRACT_VERSION,
        'project_count' => $projectCount,
        'expected_evidence_field_count' => $expected,
        'complete_evidence_field_count' => $complete,
        'projects_with_complete_evidence' => $completeProjects,
        'coverage_bps' => $coverage,
        'evidence_confidence' => $coverage === 10000 ? 'high' : 'low',
        'reason_codes' => array_keys($reasonCodes),
    ];
}

/** @param list<array<string, mixed>> $projects */
function evidenceHubMaturityState(array $projects): string
{
    if (!array_is_list($projects)) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub project facts must be a list.');
    }
    if ($projects === []) {
        return 'zero';
    }
    foreach ($projects as $project) {
        if (!is_array($project)) {
            throw new EvidenceHubAggregationInvariantException('Evidence Hub project facts must be arrays.');
        }
        if (validateEvidenceHubProjectDocumentationFacts($project)['project_has_complete_evidence']) {
            return 'ready';
        }
    }

    return 'partial';
}

/** @param list<array<string, mixed>> $projects
 * @return array{status: string, version: string, project_count: int, projects_with_complete_evidence: int, first_project_recorded_at: string|null, latest_project_recorded_at: string|null, recorded_activity_span_days: int|null, portfolio_publication_state: string, trend_status: string, trend_direction: null, reason_codes: list<string>}
 */
function summarizeEvidenceHubPortfolioProgress(array $projects, string $publicationState): array
{
    if (!in_array($publicationState, ['published', 'unpublished', 'not_configured'], true)) {
        throw new InvalidArgumentException('Evidence Hub publication state is invalid.');
    }
    if (!array_is_list($projects)) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub project facts must be a list.');
    }
    if ($projects === []) {
        return [
            'status' => 'unavailable',
            'version' => EVIDENCE_HUB_CONTRACT_VERSION,
            'project_count' => 0,
            'projects_with_complete_evidence' => 0,
            'first_project_recorded_at' => null,
            'latest_project_recorded_at' => null,
            'recorded_activity_span_days' => null,
            'portfolio_publication_state' => $publicationState,
            'trend_status' => 'not_available',
            'trend_direction' => null,
            'reason_codes' => ['HISTORY_NOT_TRACKED'],
        ];
    }

    $epochs = [];
    $completeProjects = 0;
    foreach ($projects as $project) {
        if (!is_array($project)) {
            throw new EvidenceHubAggregationInvariantException('Evidence Hub project facts must be arrays.');
        }
        $validated = validateEvidenceHubProjectDocumentationFacts($project);
        $epoch = $project['recorded_at_epoch_seconds'] ?? null;
        if (!is_int($epoch) || $epoch < 0) {
            throw new EvidenceHubAggregationInvariantException('Evidence Hub project recorded instant is invalid.');
        }
        $epochs[] = $epoch;
        if ($validated['project_has_complete_evidence']) {
            ++$completeProjects;
        }
    }
    sort($epochs, SORT_NUMERIC);
    $firstEpoch = $epochs[0];
    $latestEpoch = $epochs[array_key_last($epochs)];
    if ($latestEpoch < $firstEpoch) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub project instant range is invalid.');
    }

    return [
        'status' => 'ready',
        'version' => EVIDENCE_HUB_CONTRACT_VERSION,
        'project_count' => count($projects),
        'projects_with_complete_evidence' => $completeProjects,
        'first_project_recorded_at' => evidenceHubUtcTimestamp($firstEpoch),
        'latest_project_recorded_at' => evidenceHubUtcTimestamp($latestEpoch),
        'recorded_activity_span_days' => evidenceHubRecordedActivitySpanDays($firstEpoch, $latestEpoch),
        'portfolio_publication_state' => $publicationState,
        'trend_status' => 'not_available',
        'trend_direction' => null,
        'reason_codes' => ['HISTORY_NOT_TRACKED'],
    ];
}

function evidenceHubUtcTimestamp(int $epochSeconds): string
{
    if ($epochSeconds < 0) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub project recorded instant is invalid.');
    }

    return (new DateTimeImmutable('@' . $epochSeconds))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
}

function evidenceHubRecordedActivitySpanDays(int $firstEpochSeconds, int $latestEpochSeconds): int
{
    if ($firstEpochSeconds < 0 || $latestEpochSeconds < $firstEpochSeconds) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub project instant range is invalid.');
    }

    return intdiv($latestEpochSeconds - $firstEpochSeconds, 86400);
}
