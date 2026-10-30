<?php

declare(strict_types=1);

/** Contract v10 (B-06): evidence source counts and the add_evidence_source rule. */
final class EvidenceHubContractV10Test
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_recommendations.php';
        self::schema();
        self::definitions();
        self::live();
        self::facts();
        self::historicalVersions();
        self::invariants();
    }

    private static function schema(): void
    {
        $read = static fn (string $version): array => json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-contract-' . $version . '.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $v9 = $read('v9');
        $v10 = $read('v10');
        phase2AssertSame(str_replace('contract-v9', 'contract-v10', $v9['$id']), $v10['$id'], 'V10 schema ID is wrong.');
        phase2AssertSame('evidence-hub-contract-v10', $v10['properties']['contract_id']['const'], 'V10 contract ID is wrong.');
        phase2AssertSame('10.0.0', $v10['properties']['schema_version']['const'], 'V10 schema version is wrong.');
        $restored = $v10;
        $restored['$id'] = $v9['$id'];
        $restored['properties']['contract_id']['const'] = $v9['properties']['contract_id']['const'];
        $restored['properties']['schema_version']['const'] = $v9['properties']['schema_version']['const'];
        $defs = &$restored['$defs'];
        phase2AssertSame('add_evidence_source', array_pop($defs['recommendation']['properties']['rule_id']['enum']), 'V10 must append the source rule ID.');
        phase2AssertSame('add_evidence_source', array_pop($defs['recommendation_target']['properties']['action_id']['enum']), 'V10 must append the source action ID.');
        phase2AssertSame('PROJECT_EVIDENCE_SOURCE_MISSING', array_pop($defs['reason_code']['enum']), 'V10 must append the source reason code.');
        phase2AssertSame(6, $defs['recommendation']['properties']['priority_rank']['maximum'], 'V10 priority maximum is wrong.');
        $defs['recommendation']['properties']['priority_rank']['maximum'] = 5;
        $documentation = &$defs['documentation_coverage'];
        phase2AssertSame(['projects_with_sources', 'source_count'], array_splice($documentation['required'], -2), 'V10 must require both source counts.');
        foreach (['projects_with_sources', 'source_count'] as $field) {
            phase2AssertSame(['type' => 'integer', 'minimum' => 0], $documentation['properties'][$field], "V10 {$field} is not a non-negative integer.");
            phase2AssertSame(['const' => 0], $documentation['allOf'][0]['then']['properties'][$field], "V10 {$field} must be zero without projects.");
            unset($documentation['properties'][$field], $documentation['allOf'][0]['then']['properties'][$field]);
        }
        unset($documentation, $defs);
        phase2AssertSame($v9, $restored, 'V10 changed something beyond the approved schema identity and evidence source additions.');
    }

    private static function definitions(): void
    {
        phase2AssertSame(
            ['schema_version' => '10.0.0', 'taxonomy_version' => 'v5', 'owner_mappings' => true, 'evidence_detail' => true, 'skill_evidence' => true, 'text_rule_version' => '1.1.0', 'evidence_sources' => true],
            evidenceHubContractVersionDefinition('v10'),
            'The v10 definition is wrong.',
        );
        foreach (['v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8', 'v9'] as $version) {
            phase2Assert(!array_key_exists('evidence_sources', evidenceHubContractVersionDefinition($version)), "{$version} definition changed.");
            phase2AssertSame(false, evidenceHubContractSupportsEvidenceSources($version), "{$version} unexpectedly supports evidence sources.");
        }
        phase2AssertSame('v10', EVIDENCE_HUB_OWNER_CONTRACT_VERSION, 'The live contract must be v10.');
    }

    private static function live(): void
    {
        $database = self::database();
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);

        $core = buildAuthorizedEvidenceHubOwnerCore($database, $context);
        phase2AssertSame(['projects_with_sources' => 1, 'source_count' => 2], $core['evidence_sources'], 'Owner core counted another Portfolio\'s sources or miscounted.');
        $payload = mapEvidenceHubOwnerContract($core, []);
        phase2AssertSame(mapEvidenceHubContractV10($core, []), $payload, 'The live mapper must select v10.');
        $documentation = $payload['metrics']['documentation_coverage'];
        phase2AssertSame([1, 2], [$documentation['projects_with_sources'], $documentation['source_count']], 'V10 documentation lacks the source counts.');
        phase2AssertSame(10000, $documentation['coverage_bps'], 'Sources changed documentation coverage.');
        phase2Assert(!array_key_exists('source_count', mapEvidenceHubContractV9($core, [])['metrics']['documentation_coverage']), 'V9 gained source counts.');
        phase2Assert(!str_contains(json_encode($payload, JSON_THROW_ON_ERROR), 'example.test'), 'A source URL entered the contract.');

        $state = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, 1000, 'contract-v10-synthetic-hmac');
        $sourceCandidates = array_values(array_filter($state['current_candidates'], static fn (array $candidate): bool => $candidate['rule_id'] === 'add_evidence_source'));
        phase2AssertSame(2, count($sourceCandidates), 'Complete projects without sources must each get one source recommendation.');
        $projects = [];
        foreach ($sourceCandidates as $candidate) {
            phase2AssertSame([6, ['PROJECT_EVIDENCE_SOURCE_MISSING'], 'add_evidence_source'], [$candidate['priority_rank'], $candidate['reason_codes'], $candidate['target']['action_id']], 'Source recommendation metadata is wrong.');
            $projects = [...$projects, ...$state['resource_associations'][$candidate['recommendation_key']]['project_refs']];
        }
        sort($projects);
        phase2AssertSame([2, 3], $projects, 'The source recommendation targeted the wrong projects.');
        phase2AssertSame('evidence-hub-contract-v10', $state['contract']['contract_id'], 'The recommendation state did not use v10.');

        // An incomplete project gets complete_project_evidence, never the source nudge.
        $database->exec("UPDATE projects SET measurable_outcome = NULL WHERE id = 2");
        $state = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, 1000, 'contract-v10-synthetic-hmac');
        $refs = [];
        foreach ($state['current_candidates'] as $candidate) {
            if ($candidate['rule_id'] === 'add_evidence_source') {
                $refs = [...$refs, ...$state['resource_associations'][$candidate['recommendation_key']]['project_refs']];
            }
        }
        phase2AssertSame([3], $refs, 'An incomplete project received the source recommendation.');
    }

    private static function facts(): void
    {
        $database = self::database();
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $projects = loadAuthorizedEvidenceHubOwnerEvaluatedProjects($database, $context);
        $taxonomy = loadEvidenceHubOwnerTaxonomy();
        $publication = ['portfolio_published' => false, 'publication_prerequisites_met' => false];

        $withoutCounts = buildEvidenceHubOwnerRecommendationFacts($context, $projects, $taxonomy, $publication);
        phase2Assert(!array_key_exists('project_sources', $withoutCounts['facts']), 'Facts without counts must keep the historical shape.');
        phase2AssertSame([], array_filter(buildEvidenceHubRecommendationCandidates($withoutCounts['facts'], 'hmac'), static fn (array $candidate): bool => $candidate['rule_id'] === 'add_evidence_source'), 'Historical facts produced a source recommendation.');

        $facts = buildEvidenceHubOwnerRecommendationFacts($context, $projects, $taxonomy, $publication, [], [], [1 => 2])['facts'];
        phase2AssertSame(3, count($facts['project_sources']), 'Every project needs a source count.');
        $targets = array_column($facts['project_sources'], 'target_identity');
        $sorted = $targets;
        sort($sorted, SORT_STRING);
        phase2AssertSame($sorted, $targets, 'Source counts must be sorted by target identity.');

        $broken = [
            'a missing project' => array_slice($facts['project_sources'], 1),
            'an unsorted list' => array_reverse($facts['project_sources']),
            'a count above the limit' => [['target_identity' => $targets[0], 'source_count' => 11], ...array_slice($facts['project_sources'], 1)],
            'an extra key' => [['target_identity' => $targets[0], 'source_count' => 0, 'url' => 'https://example.test'], ...array_slice($facts['project_sources'], 1)],
            'a string count' => [['target_identity' => $targets[0], 'source_count' => '0'], ...array_slice($facts['project_sources'], 1)],
        ];
        foreach ($broken as $label => $sources) {
            try {
                buildEvidenceHubRecommendationCandidates(['project_sources' => $sources] + $facts, 'hmac');
                throw new RuntimeException("Recommendation facts accepted {$label}.");
            } catch (EvidenceHubRecommendationInvariantException) {
                // Rejected by the facts validator.
            }
        }
    }

    private static function historicalVersions(): void
    {
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $database = self::database();
        $state = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, 1000, 'contract-v10-synthetic-hmac');
        $source = array_values(array_filter($state['current_candidates'], static fn (array $candidate): bool => $candidate['rule_id'] === 'add_evidence_source'));
        $recommendations = filterEvidenceHubRecommendationCandidates([$source[0]], [], 1000);
        $core = buildEvidenceHubOwnerCoreFromEvaluatedProjects([], 'not_configured', [], 0);
        foreach (['v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8', 'v9'] as $version) {
            try {
                mapEvidenceHubContractVersion($core, $recommendations, $version);
                throw new RuntimeException("{$version} accepted the source recommendation.");
            } catch (EvidenceHubContractMappingException $exception) {
                phase2AssertSame('This contract version does not support evidence source recommendations.', $exception->getMessage(), "{$version} rejected for an unrelated reason.");
            }
        }
        $withSources = buildEvidenceHubOwnerCoreFromEvaluatedProjects([], 'not_configured', [], 0, null, []);
        phase2AssertSame(['add_evidence_source'], array_column(mapEvidenceHubContractV10($withSources, $recommendations)['recommendations'], 'rule_id'), 'V10 rejected its own recommendation.');
        try {
            mapEvidenceHubContractV10($core, []);
            throw new RuntimeException('V10 accepted a core without source counts.');
        } catch (EvidenceHubContractMappingException) {
            // A v10 payload is never published without its counts.
        }
    }

    private static function invariants(): void
    {
        $documentation = ['project_count' => 3];
        foreach ([
            [4, 4], [1, 0], [0, 1], [2, 1], [1, 11], [-1, 0], ['1', 1],
        ] as [$projects, $count]) {
            try {
                evidenceHubContractAssertEvidenceSources(['projects_with_sources' => $projects, 'source_count' => $count], $documentation);
                throw new RuntimeException("Source counts {$projects}/{$count} were accepted.");
            } catch (EvidenceHubContractMappingException) {
                // Inconsistent counts never reach a payload.
            }
        }
        evidenceHubContractAssertEvidenceSources(['projects_with_sources' => 3, 'source_count' => 30], $documentation);
        evidenceHubContractAssertEvidenceSources(['projects_with_sources' => 0, 'source_count' => 0], $documentation);
    }

    /** The v7 fixture (portfolio 10: three complete projects) plus sources: two on project 1 and one on another Portfolio's project. */
    private static function database(): PDO
    {
        $database = (new ReflectionMethod(EvidenceHubContractV7Test::class, 'database'))->invoke(null);
        $database->exec("INSERT INTO project_evidence_sources (portfolio_id, project_id, title, url, note) VALUES
            (10, 1, 'Report', 'https://example.test/report', NULL),
            (10, 1, 'Demo', 'https://example.test/demo', 'Live demo'),
            (20, 4, 'Foreign', 'https://example.test/foreign', NULL)");

        return $database;
    }
}
