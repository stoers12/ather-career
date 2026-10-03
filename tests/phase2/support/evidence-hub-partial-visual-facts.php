<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/evidence_hub_owner_page_model.php';

/**
 * A single synthetic source for the eight-project Partial visual review.
 * Technology labels stay in this server-side fixture builder. Field states are
 * synthetic evaluator outputs; the shared aggregator validates their totals.
 *
 * @return array{projects: list<array<string, mixed>>, technologies: array<string, mixed>, progress: array<string, mixed>, documentation: array<string, mixed>, technology_metric: array<string, mixed>, analytics_progress: array<string, mixed>}
 */
function buildEvidenceHubPartialVisualFacts(): array
{
    $labels = ['problem' => 'Problem', 'personal_role' => 'Personal role', 'measurable_outcome' => 'Measurable outcome'];
    $projects = [];
    $evaluatedProjects = [];
    $technologyFacts = [];
    $completeActivity = [];

    for ($id = 8; $id >= 1; --$id) {
        $updatedAt = sprintf('2026-09-%02d', 10 + $id);
        $activityAt = (new DateTimeImmutable($updatedAt . 'T00:00:00Z'))->getTimestamp();
        $technologyLabels = $id <= 5 ? ['JavaScript'] : ($id >= 7 ? ['Synthetic unmapped technology'] : []);
        $technologyFact = ['storage_state' => 'valid', 'reason_codes' => [], 'labels' => $technologyLabels];
        $fields = [];
        $fieldEvaluations = [];
        $completeCount = 0;
        foreach ($labels as $key => $label) {
            $storageKey = $key === 'problem' ? 'problem_statement' : $key;
            $isComplete = $id === 1 || ($key === 'problem' && $id % 2 === 0);
            $evaluation = [
                'evidence_field' => $storageKey,
                'storage_validity' => 'valid',
                'evidence_status' => $isComplete ? 'complete' : 'unavailable',
                'reason_codes' => $isComplete ? ['TEXT_COMPLETE'] : ['FIELD_NOT_AVAILABLE'],
            ];
            $fieldEvaluations[$storageKey] = $evaluation;
            $status = evidenceHubOwnerPageFieldStatus($evaluation);
            if ($isComplete) {
                ++$completeCount;
            }
            $fields[] = [
                'key' => $key,
                'label' => $label,
                'status' => $status,
                'reason' => $status === 'complete' ? '' : evidenceHubOwnerPageFieldReason($evaluation, $label),
            ];
        }
        $evaluated = [
            'project_ref' => $id,
            'recorded_at_epoch_seconds' => $activityAt,
            'field_evaluations' => $fieldEvaluations,
            'expected_evidence_fields' => count($labels),
            'complete_evidence_fields' => $completeCount,
            'project_has_complete_evidence' => $completeCount === count($labels),
        ];
        if ($completeCount === 3) {
            $completeActivity[] = $activityAt;
        }
        $projects[] = [
            'id' => $id,
            'title' => $id === 8 ? 'Customer reporting platform' : 'Project ' . $id,
            'status' => $completeCount === 3 ? 'complete' : ($completeCount === 0 ? 'missing' : 'needs_attention'),
            'complete_count' => $completeCount,
            'updated_at' => $updatedAt,
            'fields' => $fields,
            'edit_url' => '/owner_projects.php?edit=' . $id,
        ];
        $evaluatedProjects[] = $evaluated;
        $technologyFacts[] = $technologyFact;
    }

    $completeProjectCount = count($completeActivity);
    return [
        'projects' => $projects,
        'technologies' => evidenceHubOwnerPageTechnologies($technologyFacts),
        'progress' => evidenceHubOwnerPageProgress(count($projects), $completeProjectCount, $completeActivity),
        'documentation' => aggregateEvidenceHubDocumentationCoverage($evaluatedProjects),
        'technology_metric' => summarizeEvidenceHubTechnologies($technologyFacts, loadEvidenceHubTechnologyTaxonomy()),
        'analytics_progress' => summarizeEvidenceHubPortfolioProgress($evaluatedProjects, 'unpublished'),
    ];
}
