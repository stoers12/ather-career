<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_project_facts.php';
require_once __DIR__ . '/evidence_hub_aggregation.php';
require_once __DIR__ . '/evidence_hub_technology_mapper.php';
require_once __DIR__ . '/evidence_hub_contract_mapper.php';

/** @return array{documentation_coverage: array<string, mixed>, maturity: array{state: string, version: string}, portfolio_progress: array<string, mixed>, technology_evidence_map: array<string, mixed>} */
function buildAuthorizedEvidenceHubOwnerCore(PDO $database, AuthorizedPortfolioContext $context): array
{
    return buildEvidenceHubOwnerCoreFromEvaluatedProjects(
        loadAuthorizedEvidenceHubOwnerEvaluatedProjects($database, $context),
        loadAuthorizedEvidenceHubPublicationState($database, $context),
    );
}

/** @return list<array<string, mixed>> */
function loadAuthorizedEvidenceHubOwnerEvaluatedProjects(PDO $database, AuthorizedPortfolioContext $context): array
{
    return array_map('evaluateEvidenceHubProjectDocumentation', loadAuthorizedEvidenceHubProjectFacts($database, $context));
}

/** @param list<array<string, mixed>> $projects
 * @return array{documentation_coverage: array<string, mixed>, maturity: array{state: string, version: string}, portfolio_progress: array<string, mixed>, technology_evidence_map: array<string, mixed>}
 */
function buildEvidenceHubOwnerCoreFromEvaluatedProjects(array $projects, string $publicationState): array
{
    $coverage = aggregateEvidenceHubDocumentationCoverage($projects);
    $taxonomy = loadEvidenceHubTechnologyTaxonomy('v2');

    return [
        'documentation_coverage' => $coverage,
        'maturity' => ['state' => evidenceHubMaturityState($projects), 'version' => EVIDENCE_HUB_CONTRACT_VERSION],
        'portfolio_progress' => summarizeEvidenceHubPortfolioProgress($projects, $publicationState),
        'technology_evidence_map' => summarizeEvidenceHubTechnologies(array_map(static fn (array $project): array => [
            'storage_state' => $project['technology_storage_state'],
            'reason_codes' => $project['technology_storage_reason_codes'],
            'labels' => $project['technologies'],
        ], $projects), $taxonomy),
    ];
}

/**
 * Maps only the current R2 recommendation output supplied by trusted Owner
 * core code. The existing authorized context remains the sole data scope.
 *
 * @param list<array<string, mixed>> $recommendations
 * @return array<string, mixed>
 */
function buildAuthorizedEvidenceHubOwnerContract(
    PDO $database,
    AuthorizedPortfolioContext $context,
    array $recommendations,
): array {
    return mapEvidenceHubContractV2(buildAuthorizedEvidenceHubOwnerCore($database, $context), $recommendations);
}
