<?php

declare(strict_types=1);

final class EvidenceHubContractV8Test
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_recommendations.php';
        self::schema();
        self::mapper();
    }

    private static function schema(): void
    {
        $read = static fn (string $version): array => json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-contract-' . $version . '.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $v7 = $read('v7');
        $v8 = $read('v8');
        phase2AssertSame(str_replace('contract-v7', 'contract-v8', $v7['$id']), $v8['$id'], 'V8 schema ID is wrong.');
        phase2AssertSame('evidence-hub-contract-v8', $v8['properties']['contract_id']['const'], 'V8 contract ID is wrong.');
        phase2AssertSame('8.0.0', $v8['properties']['schema_version']['const'], 'V8 schema version is wrong.');
        phase2AssertSame(5, $v8['$defs']['recommendation']['properties']['priority_rank']['maximum'], 'V8 priority maximum is wrong.');
        $restored = $v8;
        $restored['$id'] = $v7['$id'];
        $restored['properties']['contract_id']['const'] = $v7['properties']['contract_id']['const'];
        $restored['properties']['schema_version']['const'] = $v7['properties']['schema_version']['const'];
        phase2AssertSame('add_skill_evidence', array_pop($restored['$defs']['recommendation']['properties']['rule_id']['enum']), 'V8 must append the skill rule ID.');
        phase2AssertSame('add_skill_evidence', array_pop($restored['$defs']['recommendation_target']['properties']['action_id']['enum']), 'V8 must append the skill action ID.');
        phase2AssertSame('SKILL_PROJECT_EVIDENCE_MISSING', array_pop($restored['$defs']['reason_code']['enum']), 'V8 must append the skill reason code.');
        $restored['$defs']['recommendation']['properties']['priority_rank']['maximum'] = $v7['$defs']['recommendation']['properties']['priority_rank']['maximum'];
        phase2AssertSame($v7, $restored, 'V8 changed something beyond the approved schema identity and skill rule additions.');
    }

    private static function mapper(): void
    {
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $adapted = buildEvidenceHubOwnerRecommendationFacts($context, [], loadEvidenceHubTechnologyTaxonomy('v5'), ['portfolio_published' => true, 'publication_prerequisites_met' => true], [], ['React']);
        $all = buildEvidenceHubRecommendationCandidates($adapted['facts'], 'contract-v8-synthetic-hmac');
        $skill = array_values(array_filter($all, static fn (array $candidate): bool => $candidate['rule_id'] === 'add_skill_evidence'));
        phase2AssertSame(1, count($skill), 'The v8 test needs one skill candidate.');
        $recommendations = filterEvidenceHubRecommendationCandidates($skill, [], 1000);
        $core = buildEvidenceHubOwnerCoreFromEvaluatedProjects([], 'not_configured', [], 0);
        $expectedDefinition = ['schema_version' => '8.0.0', 'taxonomy_version' => 'v5', 'owner_mappings' => true, 'evidence_detail' => true, 'skill_evidence' => true];
        phase2AssertSame($expectedDefinition, evidenceHubContractVersionDefinition('v8'), 'The v8 definition is wrong.');
        phase2AssertSame($expectedDefinition, evidenceHubContractVersionDefinition(EVIDENCE_HUB_OWNER_CONTRACT_VERSION), 'The live Owner definition must select v8.');
        $v8 = mapEvidenceHubContractV8($core, $recommendations);
        phase2AssertSame($v8, mapEvidenceHubOwnerContract($core, $recommendations), 'The live Owner mapper must publish v8.');
        phase2AssertSame('evidence-hub-contract-' . EVIDENCE_HUB_OWNER_CONTRACT_VERSION, $v8['contract_id'], 'The live Owner contract ID is wrong.');
        phase2AssertSame(['add_skill_evidence'], array_column($v8['recommendations'], 'rule_id'), 'V8 lost its skill recommendation.');
        phase2AssertSame([5, ['SKILL_PROJECT_EVIDENCE_MISSING']], [$v8['recommendations'][0]['priority_rank'], $v8['recommendations'][0]['reason_codes']], 'V8 skill metadata is wrong.');
        phase2Assert(!str_contains(json_encode($v8, JSON_THROW_ON_ERROR), 'React'), 'Raw skill text entered the contract.');
        foreach (['v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7'] as $version) {
            $definition = evidenceHubContractVersionDefinition($version);
            phase2AssertSame(false, $definition['skill_evidence'], "{$version} unexpectedly supports skill evidence.");
            // With no technology mappings, the same core is valid for every historical version.
            phase2AssertSame('evidence-hub-contract-' . $version, mapEvidenceHubContractVersion($core, [], $version)['contract_id'], "The {$version} control was not accepted.");
            try {
                mapEvidenceHubContractVersion($core, $recommendations, $version);
                throw new RuntimeException("{$version} accepted the new recommendation.");
            } catch (EvidenceHubContractMappingException $exception) {
                phase2AssertSame('This contract version does not support skill evidence recommendations.', $exception->getMessage(), "{$version} rejected for an unrelated reason.");
            }
        }
    }
}
