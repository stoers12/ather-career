<?php

declare(strict_types=1);

final class EvidenceHubContractV9Test
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_recommendations.php';
        self::schema();
        self::authorizedVersions();
        self::recommendations();
    }

    private static function schema(): void
    {
        $read = static fn (string $version): array => json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-contract-' . $version . '.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $old = $read('v8');
        $new = $read('v9');
        phase2AssertSame(str_replace('contract-v8','contract-v9',$old['$id']), $new['$id'], 'V9 schema ID is wrong.');
        phase2AssertSame('evidence-hub-contract-v9',$new['properties']['contract_id']['const'],'V9 contract ID is wrong.');
        phase2AssertSame('9.0.0',$new['properties']['schema_version']['const'],'V9 schema version is wrong.');
        phase2AssertSame('1.1.0',$new['$defs']['field_evaluation']['properties']['rule_version']['const'],'V9 must reject a 1.0.0 field.');
        $restored = $new;
        $restored['$id'] = $old['$id'];
        $restored['properties']['contract_id']['const'] = $old['properties']['contract_id']['const'];
        $restored['properties']['schema_version']['const'] = $old['properties']['schema_version']['const'];
        $restored['$defs']['field_evaluation']['properties']['rule_version']['const'] = '1.0.0';
        $restoreEnums = static function (array &$node) use (&$restoreEnums): void {
            if (isset($node['enum']) && in_array('REPETITION_SUSPECTED',$node['enum'],true)) {
                phase2AssertSame(['CHARACTER_RUN_SUSPECTED','LOW_DISTINCT_TOKEN_RATIO','DUPLICATED_ACROSS_FIELDS'], array_splice($node['enum'],-3), 'V9 must append exactly its three text reasons.');
            }
            foreach ($node as &$child) if (is_array($child)) $restoreEnums($child);
        };
        $restoreEnums($restored);
        phase2AssertSame($old,$restored,'V9 changed more than its identity, field version and three reason codes.');
        foreach (['v1','v2','v3','v4','v5','v6','v7','v8'] as $version) {
            phase2AssertSame('1.0.0',evidenceHubContractVersionDefinition($version)['text_rule_version'],'A historical contract stopped selecting baseline text rules.');
        }
        phase2AssertSame(EVIDENCE_TEXT_LIVE_RULE_VERSION,evidenceHubContractVersionDefinition(EVIDENCE_HUB_OWNER_CONTRACT_VERSION)['text_rule_version'],'The live contract and editor text versions differ.');
    }

    private static function authorizedVersions(): void
    {
        $database = (new ReflectionMethod(EvidenceHubContractV7Test::class,'database'))->invoke(null);
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1),10);
        foreach (['v1','v2','v3','v4','v5','v6','v7','v8','v9'] as $version) {
            $payload = buildAuthorizedEvidenceHubOwnerContract($database,$context,[],$version);
            phase2AssertSame('evidence-hub-contract-'.$version,$payload['contract_id'],'The authorized builder did not select the requested contract.');
            phase2AssertSame(10000,$payload['metrics']['documentation_coverage']['coverage_bps'],'Natural evidence changed completeness between contract versions.');
            foreach (loadAuthorizedEvidenceHubOwnerEvaluatedProjects($database,$context,$version) as $project) foreach ($project['field_evaluations'] as $evaluation) {
                phase2AssertSame(evidenceHubContractVersionDefinition($version)['text_rule_version'],$evaluation['rule_version'],'Aggregation did not receive its explicit text version.');
            }
        }
        $core = buildAuthorizedEvidenceHubOwnerCore($database,$context);
        phase2AssertSame(mapEvidenceHubContractV9($core,[]),mapEvidenceHubOwnerContract($core,[]),'The live mapper must select v9.');
        phase2AssertSame('evidence-hub-contract-'.EVIDENCE_HUB_OWNER_CONTRACT_VERSION,mapEvidenceHubOwnerContract($core,[])['contract_id'],'Live identity is not selected centrally.');
        self::reject(static fn (): array => mapEvidenceHubContractV8($core,[]),'V8 accepted a 1.1.0 core.');
        $baseline = buildAuthorizedEvidenceHubOwnerCore($database,$context,'v8');
        self::reject(static fn (): array => mapEvidenceHubContractV9($baseline,[]),'V9 accepted a 1.0.0 core.');
        foreach (['CHARACTER_RUN_SUSPECTED','LOW_DISTINCT_TOKEN_RATIO','DUPLICATED_ACROSS_FIELDS'] as $reason) {
            $bad = $baseline;
            $bad['documentation_coverage']['reason_codes'][] = $reason;
            foreach (['v1','v2','v3','v4','v5','v6','v7','v8'] as $version) self::reject(static fn (): array => mapEvidenceHubContractVersion($bad,[],$version),'A historical mapper accepted a new reason.');
        }
    }

    public static function payloadFixture(): array
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_recommendations.php';
        $project = ['project_ref' => 1,'recorded_at_epoch_seconds' => 1767225600,'technology_storage_state' => 'valid','technology_storage_reason_codes' => [],'technologies' => ['React'],
            'problem_statement' => 'Readers could not locate available books because inconsistent catalogue labels made the search experience confusing.',
            'personal_role' => 'I designed keyboard navigation and tested catalogue requests with librarians.',
            'measurable_outcome' => 'زاد الأداء كثيراً جداااااااااااااا'];
        $evaluated = evaluateEvidenceHubProjectDocumentation($project,'1.1.0');
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1),10);
        $adapted = buildEvidenceHubOwnerRecommendationFacts($context,[$evaluated],loadEvidenceHubTechnologyTaxonomy('v5'),['portfolio_published' => true,'publication_prerequisites_met' => true]);
        $recommendations = buildEvidenceHubRecommendations($adapted['facts'],[],1767225600,'text-v9-synthetic-hmac');
        $core = buildEvidenceHubOwnerCoreFromEvaluatedProjects([$evaluated],'published',[],1,'v9');
        return mapEvidenceHubContractV9($core,$recommendations);
    }

    private static function recommendations(): void
    {
        $payload = self::payloadFixture();
        phase2AssertSame(['complete_project_evidence'],array_column($payload['recommendations'],'rule_id'),'Padded evidence did not produce a v9 recommendation.');
        phase2Assert(in_array('CHARACTER_RUN_SUSPECTED',$payload['recommendations'][0]['reason_codes'],true),'The project recommendation lost the new field reason.');
        phase2AssertSame('1.0.0',EVIDENCE_HUB_RECOMMENDATION_RULE_VERSION,'The shared recommendation version must not change.');
        $database = (new ReflectionMethod(EvidenceHubContractV7Test::class,'database'))->invoke(null);
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1),10);
        $taxonomy = loadEvidenceHubTechnologyTaxonomy('v5');
        $build = static function (string $version) use ($database,$context,$taxonomy): array {
            $projects = loadAuthorizedEvidenceHubOwnerEvaluatedProjects($database,$context,$version);
            $facts = buildEvidenceHubOwnerRecommendationFacts($context,$projects,$taxonomy,['portfolio_published' => false,'publication_prerequisites_met' => true]);
            return buildEvidenceHubRecommendationCandidates($facts['facts'],'text-version-stability-hmac');
        };
        phase2AssertSame($build('v8'),$build('v9'),'Unchanged evidence changed recommendation keys or fingerprints.');
        $database->prepare('UPDATE projects SET measurable_outcome=:value WHERE id=1 AND portfolio_id=10')->execute(['value' => 'زاد الأداء كثيراً جداااااااااااااا']);
        $old = $build('v8'); $new = $build('v9');
        phase2Assert(!in_array('complete_project_evidence',array_column($old,'rule_id'),true),'The historical v8 rule changed on padded evidence.');
        phase2Assert(in_array('complete_project_evidence',array_column($new,'rule_id'),true),'V9 did not detect padded evidence.');
        foreach ($old as $candidate) {
            $same = array_values(array_filter($new,static fn (array $item): bool => $item['recommendation_key'] === $candidate['recommendation_key']));
            phase2AssertSame([$candidate],$same,'An unrelated recommendation changed after the new text rules.');
        }
    }

    private static function reject(callable $probe, string $message): void
    {
        try { $probe(); } catch (EvidenceHubContractMappingException) { return; }
        throw new RuntimeException($message);
    }
}
