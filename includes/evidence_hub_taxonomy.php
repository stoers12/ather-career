<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_text_normalization.php';

const EVIDENCE_HUB_TAXONOMY_V1_ENTRY_COUNT = 17;
const EVIDENCE_HUB_TAXONOMY_V2_ENTRY_COUNT = 31;
const EVIDENCE_HUB_TAXONOMY_V3_ENTRY_COUNT = 37;
const EVIDENCE_HUB_TAXONOMY_V4_ENTRY_COUNT = 38;
const EVIDENCE_HUB_TAXONOMY_V1_CATEGORIES = ['database', 'framework', 'language', 'library', 'runtime', 'tool'];
const EVIDENCE_HUB_TAXONOMY_V2_CATEGORIES = ['database', 'framework', 'language', 'library', 'platform', 'runtime', 'stylesheet', 'technique', 'tool'];
const EVIDENCE_HUB_TAXONOMY_V3_CATEGORIES = EVIDENCE_HUB_TAXONOMY_V2_CATEGORIES;
const EVIDENCE_HUB_TAXONOMY_V4_CATEGORIES = EVIDENCE_HUB_TAXONOMY_V3_CATEGORIES;

final class EvidenceHubTaxonomyException extends RuntimeException
{
}

/** @return array{taxonomy_version: string, entries: list<array<string, mixed>>, aliases: array<string, array<string, mixed>>} */
function loadEvidenceHubTechnologyTaxonomy(string $version = 'v1'): array
{
    if (!in_array($version, ['v1', 'v2', 'v3', 'v4'], true)) {
        throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy version is unsupported.');
    }
    $contents = file_get_contents(dirname(__DIR__) . '/contracts/evidence-hub-taxonomy-' . $version . '.json');
    if (!is_string($contents)) {
        throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy is unavailable.');
    }

    return parseEvidenceHubTechnologyTaxonomy($contents, $version);
}

/** @return array{taxonomy_version: string, entries: list<array<string, mixed>>, aliases: array<string, array<string, mixed>>} */
function parseEvidenceHubTechnologyTaxonomy(string $contents, string $version = 'v1'): array
{
    try {
        $taxonomy = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy is malformed.', 0, $exception);
    }
    if (!is_array($taxonomy)) {
        throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy is malformed.');
    }

    if (!in_array($version, ['v1', 'v2', 'v3', 'v4'], true)) {
        throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy version is unsupported.');
    }
    evidenceHubTaxonomyClosedKeys($taxonomy, $version === 'v1'
        ? ['taxonomy_id', 'taxonomy_version', 'match_policy', 'entries']
        : ['taxonomy_id', 'taxonomy_version', 'match_policy', 'entries', 'category_order']);
    $categories = $version === 'v1' ? EVIDENCE_HUB_TAXONOMY_V1_CATEGORIES : EVIDENCE_HUB_TAXONOMY_V3_CATEGORIES;
    $expectedCount = match ($version) {
        'v1' => EVIDENCE_HUB_TAXONOMY_V1_ENTRY_COUNT,
        'v2' => EVIDENCE_HUB_TAXONOMY_V2_ENTRY_COUNT,
        'v3' => EVIDENCE_HUB_TAXONOMY_V3_ENTRY_COUNT,
        'v4' => EVIDENCE_HUB_TAXONOMY_V4_ENTRY_COUNT,
    };
    if (($taxonomy['taxonomy_id'] ?? null) !== 'evidence-hub-technology-taxonomy'
        || ($taxonomy['taxonomy_version'] ?? null) !== $version
        || ($taxonomy['match_policy'] ?? null) !== 'normalized_exact_nfc_latin_casefold'
        || !is_array($taxonomy['entries'] ?? null)
        || !array_is_list($taxonomy['entries'])
        || count($taxonomy['entries']) !== $expectedCount
        || ($version !== 'v1' && ($taxonomy['category_order'] ?? null) !== $categories)) {
        throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy is unsupported.');
    }

    $entries = [];
    $aliases = [];
    $ids = [];
    $keys = [];
    $names = [];
    foreach ($taxonomy['entries'] as $entry) {
        if (!is_array($entry)) {
            throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy entry is invalid.');
        }
        evidenceHubTaxonomyClosedKeys($entry, ['technology_id', 'canonical_key', 'display_name', 'category', 'aliases', 'deprecated', 'merged', 'replaced_by_id']);
        $id = $entry['technology_id'] ?? null;
        $key = $entry['canonical_key'] ?? null;
        $name = $entry['display_name'] ?? null;
        $category = $entry['category'] ?? null;
        $entryAliases = $entry['aliases'] ?? null;
        if (!is_string($id) || preg_match('/^tech\.[a-z0-9-]+$/', $id) !== 1
            || !is_string($key) || preg_match('/^[a-z0-9-]+$/', $key) !== 1
            || !is_string($name) || $name === ''
            || !is_string($category) || !in_array($category, $categories, true)
            || !is_array($entryAliases) || !array_is_list($entryAliases) || $entryAliases === []
            || !is_bool($entry['deprecated'] ?? null) || !is_bool($entry['merged'] ?? null)
            || (!is_null($entry['replaced_by_id'] ?? null) && (!is_string($entry['replaced_by_id']) || preg_match('/^tech\.[a-z0-9-]+$/', $entry['replaced_by_id']) !== 1))) {
            throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy entry is invalid.');
        }
        if (($entry['deprecated'] || $entry['merged']) && $entry['replaced_by_id'] === null) {
            throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy replacement metadata is invalid.');
        }
        if (!$entry['deprecated'] && !$entry['merged'] && $entry['replaced_by_id'] !== null) {
            throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy replacement metadata is invalid.');
        }
        if (isset($ids[$id]) || isset($keys[$key])) {
            throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy contains duplicate canonical entries.');
        }
        $nameKey = normalizeEvidenceHubTechnologyLabel($name);
        if ($nameKey === null || isset($names[$nameKey])) {
            throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy contains duplicate canonical names.');
        }
        $ids[$id] = true;
        $keys[$key] = true;
        $names[$nameKey] = true;
        foreach ($entryAliases as $alias) {
            if (!is_string($alias)) {
                throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy alias is invalid.');
            }
            $aliasKey = normalizeEvidenceHubTechnologyLabel($alias);
            if ($aliasKey === null || isset($aliases[$aliasKey])) {
                throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy contains an alias collision.');
            }
            $aliases[$aliasKey] = $entry;
        }
        $entries[] = $entry;
    }

    foreach ($entries as $entry) {
        $replacement = $entry['replaced_by_id'];
        if ($replacement !== null && !isset($ids[$replacement])) {
            throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy replacement target is invalid.');
        }
    }

    return ['taxonomy_version' => $version, 'category_order' => $categories, 'entries' => $entries, 'aliases' => $aliases];
}

function normalizeEvidenceHubTechnologyLabel(string $label): ?string
{
    if (preg_match('//u', $label) !== 1) {
        throw new EvidenceHubTaxonomyException('Evidence Hub technology label is not valid UTF-8.');
    }
    requireEvidenceTextUnicodeRuntime();
    $nfc = Normalizer::normalize($label, Normalizer::FORM_C);
    if (!is_string($nfc)) {
        throw new EvidenceHubTaxonomyException('Evidence Hub technology label could not be normalized.');
    }
    $whitespace = preg_replace('/[\p{Z}\t\r\n]+/u', ' ', $nfc);
    if (!is_string($whitespace)) {
        throw new EvidenceHubTaxonomyException('Evidence Hub technology label could not be normalized.');
    }
    $normalized = trim($whitespace, ' ');

    return $normalized === '' ? null : evidenceTextLatinCaseFold($normalized);
}

/** @param array<string, mixed> $value @param list<string> $allowed */
function evidenceHubTaxonomyClosedKeys(array $value, array $allowed): void
{
    if (array_diff(array_keys($value), $allowed) !== [] || array_diff($allowed, array_keys($value)) !== []) {
        throw new EvidenceHubTaxonomyException('Evidence Hub taxonomy object shape is invalid.');
    }
}
