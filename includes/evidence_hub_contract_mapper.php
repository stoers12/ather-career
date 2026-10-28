<?php

declare(strict_types=1);

final class EvidenceHubContractMappingException extends RuntimeException
{
}

/**
 * Maps validated Owner-core metrics and already-filtered R2 recommendation
 * output into a selected private contract envelope. It only selects documented
 * output fields and never recalculates recommendation eligibility or order.
 *
 * @param array<string, mixed> $core
 * @param list<array<string, mixed>> $recommendations
 * @return array<string, mixed>
 */
function mapEvidenceHubContractV1(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, 'v1');
}

function mapEvidenceHubContractV2(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, 'v2');
}

function mapEvidenceHubContractV3(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, 'v3');
}

function mapEvidenceHubContractV4(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, 'v4');
}

function mapEvidenceHubContractV5(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, 'v5');
}

function mapEvidenceHubContractV6(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, 'v6');
}

function mapEvidenceHubContractV7(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, 'v7');
}

function mapEvidenceHubContractV8(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, 'v8');
}

function mapEvidenceHubContractV9(array $core, array $recommendations): array
{
    return mapEvidenceHubContractVersion($core, $recommendations, 'v9');
}

/**
 * Each contract version names the taxonomy it publishes. A contract and a
 * taxonomy version move independently: v5 adds Owner mappings over taxonomy v4.
 */
const EVIDENCE_HUB_CONTRACT_VERSIONS = [
    'v1' => ['schema_version' => '1.0.0', 'taxonomy_version' => 'v1', 'owner_mappings' => false, 'evidence_detail' => false, 'skill_evidence' => false, 'text_rule_version' => '1.0.0'],
    'v2' => ['schema_version' => '2.0.0', 'taxonomy_version' => 'v2', 'owner_mappings' => false, 'evidence_detail' => false, 'skill_evidence' => false, 'text_rule_version' => '1.0.0'],
    'v3' => ['schema_version' => '3.0.0', 'taxonomy_version' => 'v3', 'owner_mappings' => false, 'evidence_detail' => false, 'skill_evidence' => false, 'text_rule_version' => '1.0.0'],
    'v4' => ['schema_version' => '4.0.0', 'taxonomy_version' => 'v4', 'owner_mappings' => false, 'evidence_detail' => false, 'skill_evidence' => false, 'text_rule_version' => '1.0.0'],
    'v5' => ['schema_version' => '5.0.0', 'taxonomy_version' => 'v4', 'owner_mappings' => true, 'evidence_detail' => false, 'skill_evidence' => false, 'text_rule_version' => '1.0.0'],
    'v6' => ['schema_version' => '6.0.0', 'taxonomy_version' => 'v5', 'owner_mappings' => true, 'evidence_detail' => false, 'skill_evidence' => false, 'text_rule_version' => '1.0.0'],
    'v7' => ['schema_version' => '7.0.0', 'taxonomy_version' => 'v5', 'owner_mappings' => true, 'evidence_detail' => true, 'skill_evidence' => false, 'text_rule_version' => '1.0.0'],
    'v8' => ['schema_version' => '8.0.0', 'taxonomy_version' => 'v5', 'owner_mappings' => true, 'evidence_detail' => true, 'skill_evidence' => true, 'text_rule_version' => '1.0.0'],
    'v9' => ['schema_version' => '9.0.0', 'taxonomy_version' => 'v5', 'owner_mappings' => true, 'evidence_detail' => true, 'skill_evidence' => true, 'text_rule_version' => '1.1.0'],
];

/** @return array{schema_version: string, taxonomy_version: string, owner_mappings: bool, evidence_detail: bool, skill_evidence: bool, text_rule_version: string} */
function evidenceHubContractVersionDefinition(string $version): array
{
    return EVIDENCE_HUB_CONTRACT_VERSIONS[$version] ?? throw new EvidenceHubContractMappingException('Evidence Hub contract version is unsupported.');
}

/** @param array<string, mixed> $core @param list<array<string, mixed>> $recommendations @return array<string, mixed> */
function mapEvidenceHubContractVersion(array $core, array $recommendations, string $version): array
{
    $definition = evidenceHubContractVersionDefinition($version);
    evidenceHubContractAssertTextRules($core, $recommendations, $definition['text_rule_version']);
    if (!$definition['skill_evidence']) {
        foreach ($recommendations as $recommendation) {
            if (is_array($recommendation) && ($recommendation['rule_id'] ?? null) === 'add_skill_evidence') {
                throw new EvidenceHubContractMappingException('This contract version does not support skill evidence recommendations.');
            }
        }
    }
    $maturity = evidenceHubContractMapFields($core, ['maturity'], 'core');
    $documentation = evidenceHubContractMapFields($core, ['documentation_coverage'], 'core');
    $technology = evidenceHubContractMapFields($core, ['technology_evidence_map'], 'core');
    $progress = evidenceHubContractMapFields($core, ['portfolio_progress'], 'core');

    $technologyFields = ['status', 'version', 'evidence_confidence', 'reason_codes', 'mappings', 'technology_occurrence_count', 'mapped_occurrence_count', 'unmapped_occurrence_count', 'distinct_technology_count', 'distinct_mapped_technology_count', 'distinct_unmapped_technology_count', 'invalid_technology_storage_project_count'];
    if ($definition['owner_mappings']) {
        array_splice($technologyFields, 7, 0, ['owner_mapped_occurrence_count']);
    }
    $documentationFields = ['status', 'version', 'project_count', 'expected_evidence_field_count', 'complete_evidence_field_count', 'coverage_bps', 'evidence_confidence', 'reason_codes'];
    $progressFields = ['status', 'version', 'project_count', 'projects_with_complete_evidence', 'first_project_recorded_at', 'latest_project_recorded_at', 'recorded_activity_span_days', 'portfolio_publication_state', 'trend_status', 'trend_direction', 'reason_codes'];
    if ($definition['evidence_detail']) {
        array_splice($documentationFields, 7, 0, ['evidence_confidence_detail']);
        array_splice($progressFields, 4, 0, ['stage', 'published_project_count']);
        $technologyFields[] = 'mapping_coverage_bps';
    }
    $technologyMetric = evidenceHubContractMapObject($technology['technology_evidence_map'], $technologyFields, 'technology_evidence_map');
    $technologyMetric['mappings'] = evidenceHubContractMapTechnologyMappings($technologyMetric['mappings']);

    $payload = [
        'contract_id' => 'evidence-hub-contract-' . $version,
        'schema_version' => $definition['schema_version'],
        'maturity' => evidenceHubContractMapObject($maturity['maturity'], ['state', 'version'], 'maturity'),
        'metrics' => [
            'documentation_coverage' => evidenceHubContractMapObject($documentation['documentation_coverage'], $documentationFields, 'documentation_coverage'),
            'technology_evidence_map' => $technologyMetric,
            'portfolio_progress' => evidenceHubContractMapObject($progress['portfolio_progress'], $progressFields, 'portfolio_progress'),
        ],
        'recommendations' => evidenceHubContractMapRecommendations($recommendations),
    ];
    evidenceHubContractAssertFrozenV1($payload);
    if ($version !== 'v1') {
        $categories = $definition['taxonomy_version'] === 'v2' ? EVIDENCE_HUB_TAXONOMY_V2_CATEGORIES : EVIDENCE_HUB_TAXONOMY_V4_CATEGORIES;
        foreach ($technologyMetric['mappings'] as $mapping) {
            if ($mapping['taxonomy_version'] !== $definition['taxonomy_version']
                || ($mapping['mapping_state'] !== 'unmapped' && !in_array($mapping['category'], $categories, true))) {
                throw new EvidenceHubContractMappingException("Evidence Hub {$version} technology mapping is invalid.");
            }
        }
    }

    return $payload;
}

/** Whether a contract, identified by its contract ID, may publish Owner-mapped technologies. */
function evidenceHubContractAllowsOwnerMappings(mixed $contractId): bool
{
    foreach (EVIDENCE_HUB_CONTRACT_VERSIONS as $version => $definition) {
        if ($contractId === 'evidence-hub-contract-' . $version) {
            return $definition['owner_mappings'];
        }
    }

    return false;
}

/** @param mixed $mappings
 * @return list<array<string, mixed>>
 */
function evidenceHubContractMapTechnologyMappings(mixed $mappings): array
{
    if (!is_array($mappings) || !array_is_list($mappings)) {
        throw new EvidenceHubContractMappingException('Evidence Hub contract mapper requires technology mappings.');
    }
    $mapped = [];
    foreach ($mappings as $mapping) {
        $mapped[] = evidenceHubContractMapObject($mapping, [
            'raw_label', 'mapping_state', 'taxonomy_version', 'canonical_id', 'canonical_key', 'display_name', 'category',
        ], 'technology_mapping');
    }

    return $mapped;
}

/** @param array<string, mixed> $input @param list<string> $fields
 * @return array<string, mixed>
 */
function evidenceHubContractMapFields(array $input, array $fields, string $path): array
{
    $mapped = [];
    foreach ($fields as $field) {
        if (!array_key_exists($field, $input)) {
            throw new EvidenceHubContractMappingException("Evidence Hub contract mapper requires {$path}.{$field}.");
        }
        $mapped[$field] = $input[$field];
    }

    return $mapped;
}

/** @param mixed $input @param list<string> $fields
 * @return array<string, mixed>
 */
function evidenceHubContractMapObject(mixed $input, array $fields, string $path): array
{
    if (!is_array($input) || array_is_list($input)) {
        throw new EvidenceHubContractMappingException("Evidence Hub contract mapper requires object {$path}.");
    }

    return evidenceHubContractMapFields($input, $fields, $path);
}

/** @param list<array<string, mixed>> $recommendations
 * @return list<array<string, mixed>>
 */
function evidenceHubContractMapRecommendations(array $recommendations): array
{
    if (!array_is_list($recommendations) || count($recommendations) > 3) {
        throw new EvidenceHubContractMappingException('Evidence Hub contract mapper requires at most three ordered recommendations.');
    }
    $mapped = [];
    foreach ($recommendations as $index => $recommendation) {
        $mappedRecommendation = evidenceHubContractMapObject($recommendation, [
            'rule_id', 'rule_version', 'recommendation_key', 'evidence_fingerprint', 'lifecycle_state', 'priority_rank', 'display_order', 'reason_codes', 'target', 'snoozed_until',
        ], 'recommendation');
        $mappedRecommendation['target'] = evidenceHubContractMapObject($mappedRecommendation['target'], ['route', 'action_id', 'opaque_target_ref'], 'recommendation.target');
        if ($mappedRecommendation['lifecycle_state'] !== 'active'
            || $mappedRecommendation['snoozed_until'] !== null
            || $mappedRecommendation['display_order'] !== $index + 1
        ) {
            throw new EvidenceHubContractMappingException('Evidence Hub contract mapper received a non-visible recommendation.');
        }
        $mapped[] = $mappedRecommendation;
    }

    return $mapped;
}

/** @param array<string, mixed> $payload */
function evidenceHubContractAssertFrozenV1(array $payload): void
{
    $maturity = $payload['maturity'];
    $documentation = $payload['metrics']['documentation_coverage'];
    $technology = $payload['metrics']['technology_evidence_map'];
    $progress = $payload['metrics']['portfolio_progress'];
    if (!is_array($maturity) || !in_array($maturity['state'] ?? null, ['zero', 'partial', 'ready'], true) || ($maturity['version'] ?? null) !== '1.0.0') {
        throw new EvidenceHubContractMappingException('Evidence Hub contract maturity is invalid.');
    }
    evidenceHubContractAssertDocumentationCoverage($documentation);
    evidenceHubContractAssertTechnologyMetric($technology, evidenceHubContractAllowsOwnerMappings($payload['contract_id'] ?? null));
    evidenceHubContractAssertPortfolioProgress($progress);
    if (evidenceHubContractHasEvidenceDetail($payload['contract_id'] ?? null)) {
        evidenceHubContractAssertEvidenceDetail($documentation, $technology, $progress);
    }
    if ($maturity['state'] === 'ready' && $documentation['project_count'] < 1) {
        throw new EvidenceHubContractMappingException('Evidence Hub ready maturity requires a project.');
    }
}

function evidenceHubContractAssertDocumentationCoverage(mixed $metric): void
{
    if (!is_array($metric)
        || !in_array($metric['status'] ?? null, ['unavailable', 'needs_attention', 'ready'], true)
        || ($metric['version'] ?? null) !== '1.0.0'
        || !is_int($metric['project_count'] ?? null) || $metric['project_count'] < 0
        || !is_int($metric['expected_evidence_field_count'] ?? null) || $metric['expected_evidence_field_count'] < 0
        || !is_int($metric['complete_evidence_field_count'] ?? null) || $metric['complete_evidence_field_count'] < 0
        || !in_array($metric['evidence_confidence'] ?? null, ['unavailable', 'low', 'medium', 'high'], true)
        || !is_array($metric['reason_codes'] ?? null) || !array_is_list($metric['reason_codes'])
    ) {
        throw new EvidenceHubContractMappingException('Evidence Hub documentation metric is invalid.');
    }
    $coverage = $metric['coverage_bps'] ?? null;
    if ($metric['status'] === 'unavailable') {
        if ($metric['project_count'] !== 0 || $metric['expected_evidence_field_count'] !== 0 || $metric['complete_evidence_field_count'] !== 0 || $coverage !== null) {
            throw new EvidenceHubContractMappingException('Evidence Hub unavailable documentation metric is invalid.');
        }
        return;
    }
    if ($metric['project_count'] < 1 || !is_int($coverage) || $coverage < 0 || $coverage > 10000) {
        throw new EvidenceHubContractMappingException('Evidence Hub documentation coverage is invalid.');
    }
}

function evidenceHubContractAssertTechnologyMetric(mixed $metric, bool $ownerMappings = false): void
{
    if (!is_array($metric)
        || !in_array($metric['status'] ?? null, ['unavailable', 'needs_attention', 'ready'], true)
        || ($metric['version'] ?? null) !== '1.0.0'
        || !in_array($metric['evidence_confidence'] ?? null, ['unavailable', 'low', 'medium', 'high'], true)
        || !is_array($metric['reason_codes'] ?? null) || !array_is_list($metric['reason_codes'])
        || !is_array($metric['mappings'] ?? null) || !array_is_list($metric['mappings'])
    ) {
        throw new EvidenceHubContractMappingException('Evidence Hub technology metric is invalid.');
    }
    $countFields = ['technology_occurrence_count', 'mapped_occurrence_count', 'unmapped_occurrence_count', 'distinct_technology_count', 'distinct_mapped_technology_count', 'distinct_unmapped_technology_count', 'invalid_technology_storage_project_count'];
    if ($ownerMappings) {
        $countFields[] = 'owner_mapped_occurrence_count';
    }
    foreach ($countFields as $field) {
        if (!is_int($metric[$field] ?? null) || $metric[$field] < 0) {
            throw new EvidenceHubContractMappingException('Evidence Hub technology count is invalid.');
        }
    }
    $states = $ownerMappings ? ['mapped', 'owner_mapped', 'unmapped'] : ['mapped', 'unmapped'];
    $stateCounts = array_fill_keys($states, 0);
    foreach ($metric['mappings'] as $mapping) {
        if (!is_array($mapping) || array_is_list($mapping)) {
            throw new EvidenceHubContractMappingException('Evidence Hub technology mapping is invalid.');
        }
        $state = $mapping['mapping_state'] ?? null;
        if (!in_array($state, $states, true)) {
            throw new EvidenceHubContractMappingException('Evidence Hub technology mapping state is invalid.');
        }
        ++$stateCounts[$state];
        if ($state !== 'unmapped' && (!is_string($mapping['canonical_id'] ?? null) || !is_string($mapping['canonical_key'] ?? null) || !is_string($mapping['display_name'] ?? null) || !is_string($mapping['category'] ?? null))) {
            throw new EvidenceHubContractMappingException('Evidence Hub mapped technology is invalid.');
        }
        if ($state === 'unmapped' && (($mapping['canonical_id'] ?? null) !== null || ($mapping['canonical_key'] ?? null) !== null || ($mapping['display_name'] ?? null) !== null || ($mapping['category'] ?? null) !== null)) {
            throw new EvidenceHubContractMappingException('Evidence Hub unmapped technology is invalid.');
        }
    }
    // From v5 every occurrence is accounted for by exactly one mapping state.
    if ($ownerMappings && (
        $stateCounts['mapped'] !== $metric['mapped_occurrence_count']
        || $stateCounts['owner_mapped'] !== $metric['owner_mapped_occurrence_count']
        || $stateCounts['unmapped'] !== $metric['unmapped_occurrence_count']
        || array_sum($stateCounts) !== $metric['technology_occurrence_count']
    )) {
        throw new EvidenceHubContractMappingException('Evidence Hub technology counts do not match their mappings.');
    }
}

function evidenceHubContractAssertPortfolioProgress(mixed $metric): void
{
    if (!is_array($metric)
        || !in_array($metric['status'] ?? null, ['unavailable', 'ready'], true)
        || ($metric['version'] ?? null) !== '1.0.0'
        || !is_int($metric['project_count'] ?? null) || $metric['project_count'] < 0
        || !is_int($metric['projects_with_complete_evidence'] ?? null) || $metric['projects_with_complete_evidence'] < 0
        || !in_array($metric['portfolio_publication_state'] ?? null, ['published', 'unpublished', 'not_configured'], true)
        || ($metric['trend_status'] ?? null) !== 'not_available'
        || ($metric['trend_direction'] ?? null) !== null
        || !is_array($metric['reason_codes'] ?? null) || !array_is_list($metric['reason_codes'])
    ) {
        throw new EvidenceHubContractMappingException('Evidence Hub portfolio progress metric is invalid.');
    }
    if ($metric['status'] === 'unavailable'
        && ($metric['project_count'] !== 0 || ($metric['first_project_recorded_at'] ?? null) !== null || ($metric['latest_project_recorded_at'] ?? null) !== null || ($metric['recorded_activity_span_days'] ?? null) !== null)
    ) {
        throw new EvidenceHubContractMappingException('Evidence Hub unavailable portfolio progress is invalid.');
    }
}

function evidenceHubContractHasEvidenceDetail(mixed $contractId): bool
{
    foreach (EVIDENCE_HUB_CONTRACT_VERSIONS as $version => $definition) {
        if ($contractId === 'evidence-hub-contract-' . $version) {
            return $definition['evidence_detail'];
        }
    }

    return false;
}

/** @param array<string, mixed> $documentation @param array<string, mixed> $technology @param array<string, mixed> $progress */
function evidenceHubContractAssertEvidenceDetail(array $documentation, array $technology, array $progress): void
{
    if ($progress['projects_with_complete_evidence'] > $progress['project_count']
        || ($progress['stage'] ?? null) !== evidenceHubProgressStage($progress['project_count'], $progress['projects_with_complete_evidence'], false)
        || !is_int($progress['published_project_count'] ?? null)
        || $progress['published_project_count'] < 0 || $progress['published_project_count'] > $progress['project_count']) {
        throw new EvidenceHubContractMappingException('Evidence Hub evidence-detail progress is invalid.');
    }
    $expectedCoverage = $technology['technology_occurrence_count'] === 0 ? null : evidenceHubHalfUpBasisPoints(
        $technology['mapped_occurrence_count'] + $technology['owner_mapped_occurrence_count'],
        $technology['technology_occurrence_count'],
    );
    if (!array_key_exists('mapping_coverage_bps', $technology) || $technology['mapping_coverage_bps'] !== $expectedCoverage) {
        throw new EvidenceHubContractMappingException('Evidence Hub mapping coverage is invalid.');
    }
    $detail = $documentation['evidence_confidence_detail'] ?? null;
    $keys = ['observations', 'recommended_observations', 'basis_codes'];
    if (!is_array($detail) || count($detail) !== count($keys) || array_diff(array_keys($detail), $keys) !== []
        || ($detail['observations'] ?? null) !== $documentation['project_count']
        || ($detail['recommended_observations'] ?? null) !== EVIDENCE_HUB_RECOMMENDED_OBSERVATIONS
        || ($detail['basis_codes'] ?? null) !== evidenceHubConfidenceDetail($documentation['project_count'], $documentation['coverage_bps'])['basis_codes']) {
        throw new EvidenceHubContractMappingException('Evidence Hub confidence detail is invalid.');
    }
}

/** Internal text version and controlled reasons must agree with the chosen contract. */
function evidenceHubContractAssertTextRules(array $core, array $recommendations, string $ruleVersion): void
{
    if (isset($core['text_rule_version']) && $core['text_rule_version'] !== $ruleVersion) {
        throw new EvidenceHubContractMappingException('Evidence Hub core text rule version does not match its contract.');
    }
    $walk = static function (array $node) use (&$walk, $ruleVersion): void {
        if (isset($node['evidence_field']) && ($node['rule_version'] ?? null) !== $ruleVersion) {
            throw new EvidenceHubContractMappingException('Evidence Hub field text rule version does not match its contract.');
        }
        foreach ($node as $key => $value) {
            if ($key === 'reason_codes' && is_array($value) && $ruleVersion === '1.0.0'
                && array_intersect($value, ['CHARACTER_RUN_SUSPECTED', 'LOW_DISTINCT_TOKEN_RATIO', 'DUPLICATED_ACROSS_FIELDS']) !== []) {
                throw new EvidenceHubContractMappingException('Evidence Hub historical contract does not support this text reason.');
            }
            if (is_array($value)) $walk($value);
        }
    };
    $walk($core);
    $walk($recommendations);
}
