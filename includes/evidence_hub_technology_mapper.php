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

/** @param list<list<string>> $projectTechnologyLists
 * @param array{taxonomy_version: string, entries: list<array<string, mixed>>, aliases: array<string, array<string, mixed>>} $taxonomy
 * @return array{status: string, version: string, evidence_confidence: string, reason_codes: list<string>, mappings: list<array<string, mixed>>}
 */
function summarizeEvidenceHubTechnologies(array $projectTechnologyLists, array $taxonomy): array
{
    if ($projectTechnologyLists === []) {
        return [
            'status' => 'unavailable',
            'version' => '1.0.0',
            'evidence_confidence' => 'unavailable',
            'reason_codes' => ['NO_PROJECTS'],
            'mappings' => [],
        ];
    }

    $mappings = [];
    foreach ($projectTechnologyLists as $labels) {
        foreach ($labels as $label) {
            $mapping = mapEvidenceHubTechnologyLabel($label, $taxonomy);
            if ($mapping !== null) {
                $mappings[] = $mapping;
            }
        }
    }
    usort($mappings, static function (array $left, array $right): int {
        return [$left['mapping_state'], $left['canonical_id'] ?? '', $left['raw_label']] <=> [$right['mapping_state'], $right['canonical_id'] ?? '', $right['raw_label']];
    });
    if ($mappings === []) {
        return [
            'status' => 'unavailable',
            'version' => '1.0.0',
            'evidence_confidence' => 'unavailable',
            'reason_codes' => [],
            'mappings' => [],
        ];
    }
    $hasUnmapped = in_array('unmapped', array_column($mappings, 'mapping_state'), true);

    return [
        'status' => $hasUnmapped ? 'needs_attention' : 'ready',
        'version' => '1.0.0',
        'evidence_confidence' => $hasUnmapped ? 'low' : 'medium',
        'reason_codes' => $hasUnmapped ? ['TECHNOLOGY_UNMAPPED'] : ['TECHNOLOGY_MAPPED'],
        'mappings' => $mappings,
    ];
}
