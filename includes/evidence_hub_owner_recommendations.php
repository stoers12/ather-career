<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_configuration.php';
require_once __DIR__ . '/evidence_hub_owner_core.php';
require_once __DIR__ . '/evidence_hub_recommendation_dispositions.php';
require_once __DIR__ . '/evidence_hub_recommendations.php';

/**
 * Builds the complete internal current-candidate set from one authorized
 * Owner scope. The mapped contract remains the only presentation output;
 * resource associations are retained solely for a future protected action.
 *
 * @return array{contract: array<string, mixed>, current_candidates: list<array<string, mixed>>, resource_associations: array<string, array{project_refs: list<int>}>}
 */
function buildAuthorizedEvidenceHubOwnerRecommendationState(
    PDO $database,
    AuthorizedPortfolioContext $context,
    int $calculationTimeEpochSeconds,
    string $opaqueTargetHmacMaterial,
): array {
    $projects = loadAuthorizedEvidenceHubOwnerEvaluatedProjects($database, $context);
    $publicationState = loadAuthorizedEvidenceHubPublicationState($database, $context);
    $taxonomy = loadEvidenceHubTechnologyTaxonomy();
    $factsAndAssociations = buildEvidenceHubOwnerRecommendationFacts($context, $projects, $taxonomy, loadAuthorizedEvidenceHubRecommendationPublicationFacts($database, $context));
    $candidates = buildEvidenceHubRecommendationCandidates($factsAndAssociations['facts'], $opaqueTargetHmacMaterial);
    $resourceAssociations = evidenceHubOwnerRecommendationResourceAssociations(
        $candidates,
        $factsAndAssociations['target_associations'],
        $factsAndAssociations['facts']['tenant_scope_ref'],
        $opaqueTargetHmacMaterial,
    );
    $visible = filterEvidenceHubRecommendationCandidates(
        $candidates,
        listAuthorizedEvidenceHubRecommendationDispositions($database, $context),
        $calculationTimeEpochSeconds,
    );

    return [
        'contract' => mapEvidenceHubContractV1(buildEvidenceHubOwnerCoreFromEvaluatedProjects($projects, $publicationState), $visible),
        'current_candidates' => $candidates,
        'resource_associations' => $resourceAssociations,
    ];
}

/**
 * System-boundary convenience for a later protected route. The pure core
 * still receives decoded bytes as an explicit argument above.
 *
 * @return array{contract: array<string, mixed>, current_candidates: list<array<string, mixed>>, resource_associations: array<string, array{project_refs: list<int>}>}
 */
function buildConfiguredAuthorizedEvidenceHubOwnerRecommendationState(
    PDO $database,
    AuthorizedPortfolioContext $context,
    int $calculationTimeEpochSeconds,
): array {
    return buildAuthorizedEvidenceHubOwnerRecommendationState(
        $database,
        $context,
        $calculationTimeEpochSeconds,
        evidenceHubOpaqueTargetHmacKeyFromEnvironment(),
    );
}

/**
 * Recommendation keys are selectors only. This helper accepts only state
 * produced for one AuthorizedPortfolioContext, and searches all current
 * candidates before the visible cap or disposition suppression is applied.
 *
 * @param array{contract: array<string, mixed>, current_candidates: list<array<string, mixed>>, resource_associations: array<string, array{project_refs: list<int>}>} $state
 * @return array{candidate: array<string, mixed>, resource_associations: array{project_refs: list<int>}}|null
 */
function resolveAuthorizedEvidenceHubOwnerRecommendation(array $state, string $recommendationKey): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/D', $recommendationKey) !== 1) {
        return null;
    }
    foreach ($state['current_candidates'] as $candidate) {
        if ($candidate['recommendation_key'] !== $recommendationKey) {
            continue;
        }
        $association = $state['resource_associations'][$recommendationKey] ?? null;
        if (!is_array($association) || !array_key_exists('project_refs', $association)) {
            throw new RuntimeException('Evidence Hub current candidate association is invalid.');
        }

        return ['candidate' => $candidate, 'resource_associations' => $association];
    }

    return null;
}

/**
 * @param list<array<string, mixed>> $projects
 * @param array{taxonomy_version: string, entries: list<array<string, mixed>>, aliases: array<string, array<string, mixed>>} $taxonomy
 * @param array{portfolio_published: bool, publication_prerequisites_met: bool} $publicationFacts
 * @return array{facts: array<string, mixed>, target_associations: array<string, array{target_type: string, project_refs: list<int>}>}
 */
function buildEvidenceHubOwnerRecommendationFacts(
    AuthorizedPortfolioContext $context,
    array $projects,
    array $taxonomy,
    array $publicationFacts,
): array {
    $projectFacts = [];
    $targetAssociations = [];
    foreach ($projects as $project) {
        $projectRef = $project['project_ref'] ?? null;
        if (!is_int($projectRef) || $projectRef < 1) {
            throw new RuntimeException('Evidence Hub evaluated project reference is invalid.');
        }
        $documentation = validateEvidenceHubProjectDocumentationFacts($project);
        $targetIdentity = evidenceHubOwnerInternalTargetIdentity('project', $context->portfolioId . ':' . $projectRef);
        $projectFacts[] = [
            'target_identity' => $targetIdentity,
            'field_completeness_states' => evidenceHubOwnerProjectCompletenessStates($project),
            'reason_codes' => $documentation['reason_codes'],
        ];
        $targetAssociations[$targetIdentity] = ['target_type' => 'project', 'project_refs' => [$projectRef]];
    }

    $technologyFacts = [];
    foreach ($projects as $project) {
        $projectRef = $project['project_ref'];
        if ($project['technology_storage_state'] !== 'valid') {
            continue;
        }
        foreach ($project['technologies'] as $label) {
            $mapping = mapEvidenceHubTechnologyLabel($label, $taxonomy);
            if ($mapping === null || $mapping['mapping_state'] !== 'unmapped') {
                continue;
            }
            $normalized = normalizeEvidenceHubTechnologyLabel($label);
            if ($normalized === null) {
                continue;
            }
            $digest = hash('sha256', $normalized);
            $targetIdentity = evidenceHubOwnerInternalTargetIdentity('technology', $digest);
            if (!isset($technologyFacts[$targetIdentity])) {
                $technologyFacts[$targetIdentity] = [
                    'target_identity' => $targetIdentity,
                    'mapping_state' => 'unmapped',
                    'normalized_unmapped_label_digest' => $digest,
                ];
                $targetAssociations[$targetIdentity] = ['target_type' => 'technology', 'project_refs' => []];
            }
            $targetAssociations[$targetIdentity]['project_refs'][$projectRef] = $projectRef;
        }
    }
    foreach ($targetAssociations as &$association) {
        $association['project_refs'] = array_values($association['project_refs']);
        sort($association['project_refs'], SORT_NUMERIC);
    }
    unset($association);
    ksort($technologyFacts, SORT_STRING);

    $targetAssociations['hub'] = ['target_type' => 'hub', 'project_refs' => []];
    $portfolioTargetIdentity = evidenceHubOwnerInternalTargetIdentity('portfolio', (string) $context->portfolioId);
    $targetAssociations[$portfolioTargetIdentity] = ['target_type' => 'portfolio', 'project_refs' => []];

    return [
        'facts' => [
            'tenant_scope_ref' => evidenceHubOwnerInternalTargetIdentity('tenant', (string) $context->portfolioId),
            'portfolio_target_identity' => $portfolioTargetIdentity,
            'projects' => $projectFacts,
            'technology_mappings' => array_values($technologyFacts),
            'portfolio_publication' => $publicationFacts,
        ],
        'target_associations' => $targetAssociations,
    ];
}

/** @param array<string, mixed> $project
 * @return array{problem_statement: string, personal_role: string, measurable_outcome: string}
 */
function evidenceHubOwnerProjectCompletenessStates(array $project): array
{
    $states = [];
    foreach (['problem_statement', 'personal_role', 'measurable_outcome'] as $field) {
        $state = $project['field_evaluations'][$field]['evidence_status'] ?? null;
        if (!is_string($state) || !in_array($state, ['unavailable', 'needs_attention', 'complete'], true)) {
            throw new RuntimeException('Evidence Hub evaluated project evidence state is invalid.');
        }
        $states[$field] = $state;
    }

    return $states;
}

function evidenceHubOwnerInternalTargetIdentity(string $type, string $identity): string
{
    return hash('sha256', 'evidence_hub_owner_target_identity_v1|' . $type . '|' . $identity);
}

/**
 * @param list<array<string, mixed>> $candidates
 * @param array<string, array{target_type: string, project_refs: list<int>}> $targetAssociations
 * @return array<string, array{project_refs: list<int>}>
 */
function evidenceHubOwnerRecommendationResourceAssociations(
    array $candidates,
    array $targetAssociations,
    string $tenantScopeRef,
    string $opaqueTargetHmacMaterial,
): array
{
    $byTargetRef = [];
    foreach ($targetAssociations as $targetIdentity => $association) {
        $byTargetRef[evidenceHubOpaqueTargetRef(
            $opaqueTargetHmacMaterial,
            $tenantScopeRef,
            $association['target_type'],
            $targetIdentity,
        )] = ['project_refs' => $association['project_refs']];
    }
    $associations = [];
    foreach ($candidates as $candidate) {
        $targetRef = $candidate['target']['opaque_target_ref'] ?? null;
        if (!is_string($targetRef) || !isset($byTargetRef[$targetRef])) {
            throw new RuntimeException('Evidence Hub current candidate target is invalid.');
        }
        $associations[$candidate['recommendation_key']] = $byTargetRef[$targetRef];
    }

    return $associations;
}
