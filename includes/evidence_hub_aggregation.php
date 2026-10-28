<?php

declare(strict_types=1);

// Compatibility wrappers: the implementation lives in src/EvidenceHub/Aggregation.

require_once __DIR__ . '/evidence_text_evaluator.php';

use Ather\EvidenceHub\Aggregation\EvidenceHubAggregation;

const EVIDENCE_HUB_CONTRACT_VERSION = EvidenceHubAggregation::CONTRACT_VERSION;
const EVIDENCE_HUB_RECOMMENDED_OBSERVATIONS = EvidenceHubAggregation::RECOMMENDED_OBSERVATIONS;

function evidenceHubHalfUpBasisPoints(int $numerator, int $denominator): int
{
    return EvidenceHubAggregation::halfUpBasisPoints($numerator, $denominator);
}

/** @return array{observations: int, recommended_observations: int, basis_codes: list<string>} */
function evidenceHubConfidenceDetail(int $projectCount, ?int $coverageBps): array
{
    return EvidenceHubAggregation::confidenceDetail($projectCount, $coverageBps);
}

function evidenceHubProgressStage(int $projectCount, int $completeProjectCount, bool $trendReady): string
{
    return EvidenceHubAggregation::progressStage($projectCount, $completeProjectCount, $trendReady);
}

/**
 * @param array{project_ref: int, problem_statement: mixed, personal_role: mixed, measurable_outcome: mixed, recorded_at_epoch_seconds: int, technology_storage_state: string, technology_storage_reason_codes: list<string>, technologies: list<string>} $project
 * @return array<string, mixed>
 */
function evaluateEvidenceHubProjectDocumentation(array $project, string $ruleVersion = EVIDENCE_TEXT_RULE_VERSION): array
{
    return EvidenceHubAggregation::evaluateProjectDocumentation($project, $ruleVersion);
}

/**
 * @param array<string, mixed> $project
 * @return array{complete_evidence_fields: int, project_has_complete_evidence: bool, reason_codes: list<string>}
 */
function validateEvidenceHubProjectDocumentationFacts(array $project): array
{
    return EvidenceHubAggregation::validateProjectDocumentationFacts($project);
}

/** @param list<array<string, mixed>> $projects
 * @return array{status: string, version: string, project_count: int, expected_evidence_field_count: int, complete_evidence_field_count: int, projects_with_complete_evidence: int, coverage_bps: int|null, evidence_confidence: string, evidence_confidence_detail: array{observations: int, recommended_observations: int, basis_codes: list<string>}, reason_codes: list<string>}
 */
function aggregateEvidenceHubDocumentationCoverage(array $projects): array
{
    return EvidenceHubAggregation::aggregateDocumentationCoverage($projects);
}

/** @param list<array<string, mixed>> $projects */
function evidenceHubMaturityState(array $projects): string
{
    return EvidenceHubAggregation::maturityState($projects);
}

/** @param list<array<string, mixed>> $projects
 * @return array{status: string, version: string, project_count: int, projects_with_complete_evidence: int, stage: string, published_project_count: int|null, first_project_recorded_at: string|null, latest_project_recorded_at: string|null, recorded_activity_span_days: int|null, portfolio_publication_state: string, trend_status: string, trend_direction: null, reason_codes: list<string>}
 */
function summarizeEvidenceHubPortfolioProgress(array $projects, string $publicationState, ?int $publishedProjectCount = null): array
{
    return EvidenceHubAggregation::summarizePortfolioProgress($projects, $publicationState, $publishedProjectCount);
}

function evidenceHubUtcTimestamp(int $epochSeconds): string
{
    return EvidenceHubAggregation::utcTimestamp($epochSeconds);
}

function evidenceHubRecordedActivitySpanDays(int $firstEpochSeconds, int $latestEpochSeconds): int
{
    return EvidenceHubAggregation::recordedActivitySpanDays($firstEpochSeconds, $latestEpochSeconds);
}
