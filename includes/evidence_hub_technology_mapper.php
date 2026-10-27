<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_taxonomy.php';

/**
 * Taxonomy aliases always win; an Owner mapping only resolves a label the
 * taxonomy does not know. A mapping whose canonical entry is absent from the
 * supplied taxonomy is ignored, so the label stays unmapped.
 *
 * @param array{taxonomy_version: string, entries: list<array<string, mixed>>, aliases: array<string, array<string, mixed>>} $taxonomy
 * @param array<string, string> $ownerMappings normalized label digest => canonical technology ID
 * @return array{raw_label: string, mapping_state: string, taxonomy_version: string, canonical_id: string|null, canonical_key: string|null, display_name: string|null, category: string|null}|null
 */
function mapEvidenceHubTechnologyLabel(string $rawLabel, array $taxonomy, array $ownerMappings = []): ?array
{
    $normalized = normalizeEvidenceHubTechnologyLabel($rawLabel);
    if ($normalized === null) {
        return null;
    }
    $entry = $taxonomy['aliases'][$normalized] ?? null;
    if (is_array($entry)) {
        return evidenceHubTechnologyMapping($rawLabel, 'mapped', $taxonomy['taxonomy_version'], $entry);
    }
    $ownerCanonicalId = $ownerMappings[evidenceHubTechnologyLabelDigest($normalized)] ?? null;
    $ownerEntry = is_string($ownerCanonicalId) ? evidenceHubTaxonomyEntryById($taxonomy, $ownerCanonicalId) : null;
    if ($ownerEntry !== null) {
        return evidenceHubTechnologyMapping($rawLabel, 'owner_mapped', $taxonomy['taxonomy_version'], $ownerEntry);
    }

    return evidenceHubTechnologyMapping($rawLabel, 'unmapped', $taxonomy['taxonomy_version'], null);
}

/** @param array<string, mixed>|null $entry
 * @return array{raw_label: string, mapping_state: string, taxonomy_version: string, canonical_id: string|null, canonical_key: string|null, display_name: string|null, category: string|null}
 */
function evidenceHubTechnologyMapping(string $rawLabel, string $state, string $taxonomyVersion, ?array $entry): array
{
    return [
        'raw_label' => $rawLabel,
        'mapping_state' => $state,
        'taxonomy_version' => $taxonomyVersion,
        'canonical_id' => $entry['technology_id'] ?? null,
        'canonical_key' => $entry['canonical_key'] ?? null,
        'display_name' => $entry['display_name'] ?? null,
        'category' => $entry['category'] ?? null,
    ];
}

/** The stable private identity of a normalized label; it never leaves the server. */
function evidenceHubTechnologyLabelDigest(string $normalizedLabel): string
{
    return hash('sha256', $normalizedLabel);
}

/** @param array{entries: list<array<string, mixed>>} $taxonomy @return array<string, mixed>|null */
function evidenceHubTaxonomyEntryById(array $taxonomy, string $technologyId): ?array
{
    foreach ($taxonomy['entries'] as $entry) {
        if (($entry['technology_id'] ?? null) === $technologyId) {
            return $entry;
        }
    }

    return null;
}

/**
 * @param list<array{storage_state: string, reason_codes: list<string>, labels: list<string>}> $projectTechnologyFacts
 * @param array{taxonomy_version: string, entries: list<array<string, mixed>>, aliases: array<string, array<string, mixed>>} $taxonomy
 * @param array<string, string> $ownerMappings normalized label digest => canonical technology ID
 * @return array<string, mixed>
 */
function summarizeEvidenceHubTechnologies(array $projectTechnologyFacts, array $taxonomy, array $ownerMappings = []): array
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
            $mapping = mapEvidenceHubTechnologyLabel($label, $taxonomy, $ownerMappings);
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
    $ownerMappedOccurrences = 0;
    $unmappedOccurrences = 0;
    // A canonical technology is one distinct technology however it was recognized.
    $distinctMapped = [];
    $distinctUnmapped = [];
    foreach ($mappings as $mapping) {
        if ($mapping['mapping_state'] === 'mapped') {
            ++$mappedOccurrences;
            $distinctMapped[$mapping['canonical_id']] = true;
            continue;
        }
        if ($mapping['mapping_state'] === 'owner_mapped') {
            ++$ownerMappedOccurrences;
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
    if ($occurrences !== $mappedOccurrences + $ownerMappedOccurrences + $unmappedOccurrences || $distinctCount !== $distinctMappedCount + $distinctUnmappedCount) {
        throw new EvidenceHubAggregationInvariantException('Evidence Hub technology summary counts are inconsistent.');
    }

    $reasonCodes = [];
    if ($mappedOccurrences > 0) {
        $reasonCodes[] = 'TECHNOLOGY_MAPPED';
    }
    if ($ownerMappedOccurrences > 0) {
        $reasonCodes[] = 'TECHNOLOGY_OWNER_MAPPED';
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
        return evidenceHubTechnologySummary('needs_attention', 'low', $reasonCodes, $mappings, $occurrences, $mappedOccurrences, $unmappedOccurrences, $distinctCount, $distinctMappedCount, $distinctUnmappedCount, $invalidStorageProjects, $ownerMappedOccurrences);
    }

    return evidenceHubTechnologySummary('ready', 'medium', $reasonCodes, $mappings, $occurrences, $mappedOccurrences, $unmappedOccurrences, $distinctCount, $distinctMappedCount, $distinctUnmappedCount, $invalidStorageProjects, $ownerMappedOccurrences);
}

/**
 * owner_mapped_occurrence_count is published from contract v5 only; earlier
 * contracts select their frozen field lists and cannot carry Owner mappings.
 *
 * @param list<string> $reasonCodes
 * @param list<array<string, mixed>> $mappings
 * @return array<string, mixed>
 */
function evidenceHubTechnologySummary(string $status, string $confidence, array $reasonCodes, array $mappings, int $occurrences, int $mappedOccurrences, int $unmappedOccurrences, int $distinctCount, int $distinctMappedCount, int $distinctUnmappedCount, int $invalidStorageProjects, int $ownerMappedOccurrences = 0): array
{
    return [
        'status' => $status,
        'version' => EVIDENCE_HUB_CONTRACT_VERSION,
        'evidence_confidence' => $confidence,
        'reason_codes' => $reasonCodes,
        'mappings' => $mappings,
        'technology_occurrence_count' => $occurrences,
        'mapped_occurrence_count' => $mappedOccurrences,
        'owner_mapped_occurrence_count' => $ownerMappedOccurrences,
        'unmapped_occurrence_count' => $unmappedOccurrences,
        'distinct_technology_count' => $distinctCount,
        'distinct_mapped_technology_count' => $distinctMappedCount,
        'distinct_unmapped_technology_count' => $distinctUnmappedCount,
        'invalid_technology_storage_project_count' => $invalidStorageProjects,
        'mapping_coverage_bps' => $occurrences === 0 ? null : evidenceHubHalfUpBasisPoints($mappedOccurrences + $ownerMappedOccurrences, $occurrences),
    ];
}
