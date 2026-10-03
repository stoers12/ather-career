<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_taxonomy.php';

/** @param array{taxonomy_version: string, entries: list<array<string, mixed>>, aliases: array<string, array<string, mixed>>} $taxonomy
 * @return array{raw_label: string, mapping_state: string, taxonomy_version: string, canonical_id: string|null, canonical_key: string|null, display_name: string|null, category: string|null}|null
 */
function mapEvidenceHubTechnologyLabel(string $rawLabel, array $taxonomy): ?array
{
    $normalized = normalizeEvidenceHubTechnologyLabel($rawLabel);
    if ($normalized === null) {
        return null;
    }
    $entry = $taxonomy['aliases'][$normalized] ?? null;
    if (!is_array($entry)) {
        return [
            'raw_label' => $rawLabel,
            'mapping_state' => 'unmapped',
            'taxonomy_version' => $taxonomy['taxonomy_version'],
            'canonical_id' => null,
            'canonical_key' => null,
            'display_name' => null,
            'category' => null,
        ];
    }

    return [
        'raw_label' => $rawLabel,
        'mapping_state' => 'mapped',
        'taxonomy_version' => $taxonomy['taxonomy_version'],
        'canonical_id' => $entry['technology_id'],
        'canonical_key' => $entry['canonical_key'],
        'display_name' => $entry['display_name'],
        'category' => $entry['category'],
    ];
}

/**
 * @param list<array{storage_state: string, reason_codes: list<string>, labels: list<string>}> $projectTechnologyFacts
 * @param array{taxonomy_version: string, entries: list<array<string, mixed>>, aliases: array<string, array<string, mixed>>} $taxonomy
 * @return array<string, mixed>
 */
function summarizeEvidenceHubTechnologies(array $projectTechnologyFacts, array $taxonomy): array
{
    if (!array_is_list($projectTechnologyFacts)) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub technology storage facts must be a list.');
    }
    if ($projectTechnologyFacts === []) {
        return evidenceHubTechnologySummary('unavailable', 'unavailable', ['NO_PROJECTS'], [], 0, 0, 0, 0, 0, 0, 0);
    }

    $mappings = [];
    $invalidStorageProjects = 0;
    foreach ($projectTechnologyFacts as $fact) {
        if (!is_array($fact) || !array_key_exists('storage_state', $fact) || !array_key_exists('reason_codes', $fact) || !array_key_exists('labels', $fact)) {
            throw new EvidenceHubAggregationInvariantException('Evidence Hub technology storage facts are invalid.');
        }
        if ($fact['storage_state'] === 'invalid') {
            if ($fact['reason_codes'] !== ['TECHNOLOGY_STORAGE_INVALID'] || $fact['labels'] !== []) {
                throw new EvidenceHubAggregationInvariantException('Evidence Hub invalid technology storage facts are inconsistent.');
            }
            ++$invalidStorageProjects;
            continue;
        }
        if ($fact['storage_state'] !== 'valid' || $fact['reason_codes'] !== [] || !is_array($fact['labels']) || !array_is_list($fact['labels'])) {
            throw new EvidenceHubAggregationInvariantException('Evidence Hub technology storage facts are invalid.');
        }
        foreach ($fact['labels'] as $label) {
            if (!is_string($label)) {
                throw new EvidenceHubAggregationInvariantException('Evidence Hub technology label is invalid.');
            }
            $mapping = mapEvidenceHubTechnologyLabel($label, $taxonomy);
            if ($mapping !== null) {
                $mappings[] = $mapping;
            }
        }
    }

    usort($mappings, static function (array $left, array $right): int {
        $leftNormalized = normalizeEvidenceHubTechnologyLabel($left['raw_label']);
        $rightNormalized = normalizeEvidenceHubTechnologyLabel($right['raw_label']);

        return [$left['mapping_state'], $left['canonical_id'] ?? '', $leftNormalized ?? '', $left['raw_label']] <=> [$right['mapping_state'], $right['canonical_id'] ?? '', $rightNormalized ?? '', $right['raw_label']];
    });

    $mappedOccurrences = 0;
    $unmappedOccurrences = 0;
    $distinctMapped = [];
    $distinctUnmapped = [];
    foreach ($mappings as $mapping) {
        if ($mapping['mapping_state'] === 'mapped') {
            ++$mappedOccurrences;
            $distinctMapped[$mapping['canonical_id']] = true;
            continue;
        }
        if ($mapping['mapping_state'] === 'unmapped') {
            ++$unmappedOccurrences;
            $normalized = normalizeEvidenceHubTechnologyLabel($mapping['raw_label']);
            if ($normalized === null) {
                throw new EvidenceHubAggregationInvariantException('Evidence Hub unmapped technology label is invalid.');
            }
            $distinctUnmapped[$normalized] = true;
            continue;
        }
        throw new EvidenceHubAggregationInvariantException('Evidence Hub technology mapping state is invalid.');
    }

    $occurrences = count($mappings);
    $distinctMappedCount = count($distinctMapped);
    $distinctUnmappedCount = count($distinctUnmapped);
    $distinctCount = $distinctMappedCount + $distinctUnmappedCount;
    if ($occurrences !== $mappedOccurrences + $unmappedOccurrences || $distinctCount !== $distinctMappedCount + $distinctUnmappedCount) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub technology summary counts are inconsistent.');
    }

    $reasonCodes = [];
    if ($mappedOccurrences > 0) {
        $reasonCodes[] = 'TECHNOLOGY_MAPPED';
    }
    if ($unmappedOccurrences > 0) {
        $reasonCodes[] = 'TECHNOLOGY_UNMAPPED';
    }
    if ($invalidStorageProjects > 0) {
        $reasonCodes[] = 'TECHNOLOGY_STORAGE_INVALID';
    }
    sort($reasonCodes, SORT_STRING);
    if ($occurrences === 0 && $invalidStorageProjects === 0) {
        return evidenceHubTechnologySummary('unavailable', 'unavailable', [], $mappings, 0, 0, 0, 0, 0, 0, 0);
    }
    if ($unmappedOccurrences > 0 || $invalidStorageProjects > 0) {
        return evidenceHubTechnologySummary('needs_attention', 'low', $reasonCodes, $mappings, $occurrences, $mappedOccurrences, $unmappedOccurrences, $distinctCount, $distinctMappedCount, $distinctUnmappedCount, $invalidStorageProjects);
    }

    return evidenceHubTechnologySummary('ready', 'medium', $reasonCodes, $mappings, $occurrences, $mappedOccurrences, $unmappedOccurrences, $distinctCount, $distinctMappedCount, $distinctUnmappedCount, $invalidStorageProjects);
}

/** @param list<string> $reasonCodes
 * @param list<array<string, mixed>> $mappings
 * @return array<string, mixed>
 */
function evidenceHubTechnologySummary(string $status, string $confidence, array $reasonCodes, array $mappings, int $occurrences, int $mappedOccurrences, int $unmappedOccurrences, int $distinctCount, int $distinctMappedCount, int $distinctUnmappedCount, int $invalidStorageProjects): array
{
    return [
        'status' => $status,
        'version' => EVIDENCE_HUB_CONTRACT_VERSION,
        'evidence_confidence' => $confidence,
        'reason_codes' => $reasonCodes,
        'mappings' => $mappings,
        'technology_occurrence_count' => $occurrences,
        'mapped_occurrence_count' => $mappedOccurrences,
        'unmapped_occurrence_count' => $unmappedOccurrences,
        'distinct_technology_count' => $distinctCount,
        'distinct_mapped_technology_count' => $distinctMappedCount,
        'distinct_unmapped_technology_count' => $distinctUnmappedCount,
        'invalid_technology_storage_project_count' => $invalidStorageProjects,
    ];
}
