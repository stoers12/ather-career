<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_project_facts.php';
require_once __DIR__ . '/evidence_hub_aggregation.php';
require_once __DIR__ . '/evidence_hub_technology_mapper.php';
require_once __DIR__ . '/evidence_hub_contract_mapper.php';
require_once __DIR__ . '/evidence_hub_technology_review_repository.php';

use Ather\EvidenceHub\Aggregation\EvidenceHubAggregationInvariantException;

/** The single contract version the private Owner Evidence Hub publishes. */
const EVIDENCE_HUB_OWNER_CONTRACT_VERSION = 'v9';

/** @return array{taxonomy_version: string, entries: list<array<string, mixed>>, aliases: array<string, array<string, mixed>>, category_order: list<string>} */
function loadEvidenceHubOwnerTaxonomy(): array
{
    return loadEvidenceHubTechnologyTaxonomy(evidenceHubContractVersionDefinition(EVIDENCE_HUB_OWNER_CONTRACT_VERSION)['taxonomy_version']);
}

/** @param array<string, mixed> $core @param list<array<string, mixed>> $recommendations @return array<string, mixed> */
function mapEvidenceHubOwnerContract(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, EVIDENCE_HUB_OWNER_CONTRACT_VERSION);
}

/** @return array{documentation_coverage: array<string, mixed>, maturity: array{state: string, version: string}, portfolio_progress: array<string, mixed>, technology_evidence_map: array<string, mixed>} */
function buildAuthorizedEvidenceHubOwnerCore(PDO $database, AuthorizedPortfolioContext $context, string $contractVersion = EVIDENCE_HUB_OWNER_CONTRACT_VERSION): array
{
    return buildEvidenceHubOwnerCoreFromEvaluatedProjects(
        loadAuthorizedEvidenceHubOwnerEvaluatedProjects($database, $context, $contractVersion),
        loadAuthorizedEvidenceHubPublicationState($database, $context),
        loadAuthorizedTechnologyOwnerMappings($database, $context),
        loadAuthorizedEvidenceHubPublishedProjectCount($database, $context),
        $contractVersion,
    );
}

/** @return list<array<string, mixed>> */
function loadAuthorizedEvidenceHubOwnerEvaluatedProjects(PDO $database, AuthorizedPortfolioContext $context, string $contractVersion = EVIDENCE_HUB_OWNER_CONTRACT_VERSION): array
{
    $ruleVersion = evidenceHubContractVersionDefinition($contractVersion)['text_rule_version'];
    return array_map(static fn (array $project): array => evaluateEvidenceHubProjectDocumentation($project, $ruleVersion), loadAuthorizedEvidenceHubProjectFacts($database, $context));
}

/** @param list<array<string, mixed>> $projects @param array<string, string> $ownerMappings
 * @return array{documentation_coverage: array<string, mixed>, maturity: array{state: string, version: string}, portfolio_progress: array<string, mixed>, technology_evidence_map: array<string, mixed>}
 */
function buildEvidenceHubOwnerCoreFromEvaluatedProjects(array $projects, string $publicationState, array $ownerMappings = [], ?int $publishedProjectCount = null, ?string $contractVersion = null): array
{
    $coverage = aggregateEvidenceHubDocumentationCoverage($projects);
    $definition = evidenceHubContractVersionDefinition($contractVersion ?? EVIDENCE_HUB_OWNER_CONTRACT_VERSION);
    $taxonomy = loadEvidenceHubTechnologyTaxonomy($definition['taxonomy_version']);
    if ($contractVersion !== null) {
        foreach ($projects as $project) foreach ($project['field_evaluations'] as $evaluation) {
            if (($evaluation['rule_version'] ?? null) !== $definition['text_rule_version']) {
                throw new EvidenceHubAggregationInvariantException('Evidence Hub project text rule version does not match its contract.');
            }
        }
    }

    $core = [
        'documentation_coverage' => $coverage,
        'maturity' => ['state' => evidenceHubMaturityState($projects), 'version' => EVIDENCE_HUB_CONTRACT_VERSION],
        'portfolio_progress' => summarizeEvidenceHubPortfolioProgress($projects, $publicationState, $publishedProjectCount),
        'technology_evidence_map' => summarizeEvidenceHubTechnologies(array_map(static fn (array $project): array => [
            'storage_state' => $project['technology_storage_state'],
            'reason_codes' => $project['technology_storage_reason_codes'],
            'labels' => $project['technologies'],
        ], $projects), $taxonomy, $ownerMappings),
    ];
    if ($contractVersion !== null) $core['text_rule_version'] = $definition['text_rule_version'];
    return $core;
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
    string $contractVersion = EVIDENCE_HUB_OWNER_CONTRACT_VERSION,
): array {
    return mapEvidenceHubContractVersion(buildAuthorizedEvidenceHubOwnerCore($database, $context, $contractVersion), $recommendations, $contractVersion);
}
