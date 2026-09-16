<?php

declare(strict_types=1);

final class EvidenceHubContractMappingException extends RuntimeException
{
}

/**
 * Maps validated Owner-core metrics and already-filtered R2 recommendation
 * output into the frozen private v1 envelope. It only selects documented
 * output fields and never recalculates recommendation eligibility or order.
 *
 * @param array<string, mixed> $core
 * @param list<array<string, mixed>> $recommendations
 * @return array<string, mixed>
 */
function mapEvidenceHubContractV1(array $core, array $recommendations): array
{
    $maturity = evidenceHubContractMapFields($core, ['maturity'], 'core');
    $documentation = evidenceHubContractMapFields($core, ['documentation_coverage'], 'core');
    $technology = evidenceHubContractMapFields($core, ['technology_evidence_map'], 'core');
    $progress = evidenceHubContractMapFields($core, ['portfolio_progress'], 'core');

    $technologyMetric = evidenceHubContractMapObject($technology['technology_evidence_map'], [
        'status', 'version', 'evidence_confidence', 'reason_codes', 'mappings', 'technology_occurrence_count', 'mapped_occurrence_count', 'unmapped_occurrence_count', 'distinct_technology_count', 'distinct_mapped_technology_count', 'distinct_unmapped_technology_count', 'invalid_technology_storage_project_count',
    ], 'technology_evidence_map');
    $technologyMetric['mappings'] = evidenceHubContractMapTechnologyMappings($technologyMetric['mappings']);

    $payload = [
        'contract_id' => 'evidence-hub-contract-v1',
        'schema_version' => '1.0.0',
        'maturity' => evidenceHubContractMapObject($maturity['maturity'], ['state', 'version'], 'maturity'),
        'metrics' => [
            'documentation_coverage' => evidenceHubContractMapObject($documentation['documentation_coverage'], [
                'status', 'version', 'project_count', 'expected_evidence_field_count', 'complete_evidence_field_count', 'coverage_bps', 'evidence_confidence', 'reason_codes',
            ], 'documentation_coverage'),
            'technology_evidence_map' => $technologyMetric,
            'portfolio_progress' => evidenceHubContractMapObject($progress['portfolio_progress'], [
                'status', 'version', 'project_count', 'projects_with_complete_evidence', 'first_project_recorded_at', 'latest_project_recorded_at', 'recorded_activity_span_days', 'portfolio_publication_state', 'trend_status', 'trend_direction', 'reason_codes',
            ], 'portfolio_progress'),
        ],
        'recommendations' => evidenceHubContractMapRecommendations($recommendations),
    ];
    evidenceHubContractAssertFrozenV1($payload);

    return $payload;
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
    evidenceHubContractAssertTechnologyMetric($technology);
    evidenceHubContractAssertPortfolioProgress($progress);
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

function evidenceHubContractAssertTechnologyMetric(mixed $metric): void
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
    foreach (['technology_occurrence_count', 'mapped_occurrence_count', 'unmapped_occurrence_count', 'distinct_technology_count', 'distinct_mapped_technology_count', 'distinct_unmapped_technology_count', 'invalid_technology_storage_project_count'] as $field) {
        if (!is_int($metric[$field] ?? null) || $metric[$field] < 0) {
            throw new EvidenceHubContractMappingException('Evidence Hub technology count is invalid.');
        }
    }
    foreach ($metric['mappings'] as $mapping) {
        if (!is_array($mapping) || array_is_list($mapping)) {
            throw new EvidenceHubContractMappingException('Evidence Hub technology mapping is invalid.');
        }
        $state = $mapping['mapping_state'] ?? null;
        if (!in_array($state, ['mapped', 'unmapped'], true)) {
            throw new EvidenceHubContractMappingException('Evidence Hub technology mapping state is invalid.');
        }
        if ($state === 'mapped' && (!is_string($mapping['canonical_id'] ?? null) || !is_string($mapping['canonical_key'] ?? null) || !is_string($mapping['display_name'] ?? null) || !is_string($mapping['category'] ?? null))) {
            throw new EvidenceHubContractMappingException('Evidence Hub mapped technology is invalid.');
        }
        if ($state === 'unmapped' && (($mapping['canonical_id'] ?? null) !== null || ($mapping['canonical_key'] ?? null) !== null || ($mapping['display_name'] ?? null) !== null || ($mapping['category'] ?? null) !== null)) {
            throw new EvidenceHubContractMappingException('Evidence Hub unmapped technology is invalid.');
        }
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
