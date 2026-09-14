<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_text_evaluator.php';

const EVIDENCE_HUB_CONTRACT_VERSION = '1.0.0';

/** @param array{project_ref: int, problem_statement: mixed, personal_role: mixed, measurable_outcome: mixed, created_at: string, technologies: list<string>} $project
 * @return array{project_ref: int, created_at: string, technologies: list<string>, field_evaluations: array<string, array<string, mixed>>, expected_evidence_fields: int, complete_evidence_fields: int, project_has_complete_evidence: bool}
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
        'created_at' => $project['created_at'],
        'technologies' => $project['technologies'],
        'field_evaluations' => $evaluations,
        'expected_evidence_fields' => 3,
        'complete_evidence_fields' => $complete,
        'project_has_complete_evidence' => $complete === 3,
    ];
}

/** @param list<array<string, mixed>> $projects
 * @return array{status: string, version: string, project_count: int, expected_evidence_field_count: int, complete_evidence_field_count: int, projects_with_complete_evidence: int, coverage_bps: int|null, evidence_confidence: string, reason_codes: list<string>}
 */
function aggregateEvidenceHubDocumentationCoverage(array $projects): array
{
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
        $complete += (int) $project['complete_evidence_fields'];
        if ($project['project_has_complete_evidence'] === true) {
            ++$completeProjects;
        }
        foreach ($project['field_evaluations'] as $evaluation) {
            foreach ($evaluation['reason_codes'] as $reasonCode) {
                $reasonCodes[$reasonCode] = true;
            }
        }
    }
    $coverage = intdiv($complete * 10000 + intdiv($expected, 2), $expected);
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
    if ($projects === []) {
        return 'zero';
    }
    foreach ($projects as $project) {
        if ($project['project_has_complete_evidence'] === true) {
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
    $recorded = array_map(static fn (array $project): DateTimeImmutable => evidenceHubRecordedAt($project['created_at']), $projects);
    usort($recorded, static fn (DateTimeImmutable $left, DateTimeImmutable $right): int => $left <=> $right);
    $first = $recorded[0];
    $latest = $recorded[array_key_last($recorded)];
    $completeProjects = count(array_filter($projects, static fn (array $project): bool => $project['project_has_complete_evidence'] === true));

    return [
        'status' => 'ready',
        'version' => EVIDENCE_HUB_CONTRACT_VERSION,
        'project_count' => count($projects),
        'projects_with_complete_evidence' => $completeProjects,
        'first_project_recorded_at' => $first->format('Y-m-d\TH:i:s\Z'),
        'latest_project_recorded_at' => $latest->format('Y-m-d\TH:i:s\Z'),
        'recorded_activity_span_days' => intdiv($latest->getTimestamp() - $first->getTimestamp(), 86400),
        'portfolio_publication_state' => $publicationState,
        'trend_status' => 'not_available',
        'trend_direction' => null,
        'reason_codes' => ['HISTORY_NOT_TRACKED'],
    ];
}

function evidenceHubRecordedAt(mixed $timestamp): DateTimeImmutable
{
    if (!is_string($timestamp)) {
        throw new RuntimeException('Evidence Hub project timestamp is invalid.');
    }
    $utc = new DateTimeZone('UTC');
    $recorded = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $timestamp, $utc);
    if ($recorded === false || DateTimeImmutable::getLastErrors() !== false) {
        $recorded = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $timestamp, $utc);
    }
    if ($recorded === false || DateTimeImmutable::getLastErrors() !== false) {
        throw new RuntimeException('Evidence Hub project timestamp is invalid.');
    }

    return $recorded;
}
