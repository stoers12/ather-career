<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_project_facts.php';
require_once __DIR__ . '/evidence_hub_aggregation.php';
require_once __DIR__ . '/evidence_hub_technology_mapper.php';

/** @return array{documentation_coverage: array<string, mixed>, maturity: array{state: string, version: string}, portfolio_progress: array<string, mixed>, technology_evidence_map: array<string, mixed>} */
function buildAuthorizedEvidenceHubOwnerCore(PDO $database, AuthorizedPortfolioContext $context): array
{
    $rawProjects = loadAuthorizedEvidenceHubProjectFacts($database, $context);
    $projects = array_map('evaluateEvidenceHubProjectDocumentation', $rawProjects);
    $coverage = aggregateEvidenceHubDocumentationCoverage($projects);
    $taxonomy = loadEvidenceHubTechnologyTaxonomy();

    return [
        'documentation_coverage' => $coverage,
        'maturity' => ['state' => evidenceHubMaturityState($projects), 'version' => EVIDENCE_HUB_CONTRACT_VERSION],
        'portfolio_progress' => summarizeEvidenceHubPortfolioProgress($projects, loadAuthorizedEvidenceHubPublicationState($database, $context)),
        'technology_evidence_map' => summarizeEvidenceHubTechnologies(array_column($projects, 'technologies'), $taxonomy),
    ];
}
