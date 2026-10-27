<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_technology_mapper.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/evidence_text_normalization.php';
require_once __DIR__ . '/project_technologies.php';

/*
 * Owner technology review: the project labels an Owner can still act on
 * (unmapped, or mapped by them), the opaque form references that select them,
 * the mapping choices, and technology proposal validation.
 *
 * Pure: callers supply facts already scoped to one AuthorizedPortfolioContext.
 * A form never carries a label or digest; it carries a review-only HMAC
 * reference (evidenceHubTechnologyReviewRef), resolved again on the server.
 */

const EVIDENCE_HUB_TECHNOLOGY_PROPOSAL_NOTE_MAXIMUM = 280;

/**
 * Keyed by normalized label digest: unmapped labels first, then Owner-mapped,
 * each by normalized label. The raw label shown is the first spelling in
 * byte order, so the choice is stable whatever order projects arrive in.
 *
 * @param list<array{id: int, title: string, technology_storage: array{storage_state: string, labels: list<string>}}> $projects
 * @param array<string, string> $ownerMappings
 * @return array<string, array{digest: string, raw_label: string, mapping_state: string, canonical_id: ?string, display_name: ?string, category: ?string, projects: list<array{id: int, title: string}>}>
 */
function evidenceHubTechnologyReviewItems(array $projects, array $taxonomy, array $ownerMappings): array
{
    $items = [];
    foreach ($projects as $project) {
        if ($project['technology_storage']['storage_state'] !== 'valid') {
            continue;
        }
        foreach ($project['technology_storage']['labels'] as $label) {
            $mapping = mapEvidenceHubTechnologyLabel($label, $taxonomy, $ownerMappings);
            $normalized = normalizeEvidenceHubTechnologyLabel($label);
            if ($mapping === null || $normalized === null || $mapping['mapping_state'] === 'mapped') {
                continue;
            }
            $digest = evidenceHubTechnologyLabelDigest($normalized);
            $item = $items[$digest] ?? [
                'digest' => $digest,
                'sort_key' => [$mapping['mapping_state'] === 'unmapped' ? 0 : 1, $normalized],
                'raw_label' => $label,
                'mapping_state' => $mapping['mapping_state'],
                'canonical_id' => $mapping['canonical_id'],
                'display_name' => $mapping['display_name'],
                'category' => $mapping['category'],
                'projects' => [],
            ];
            if (strcmp($label, $item['raw_label']) < 0) {
                $item['raw_label'] = $label;
            }
            $item['projects'][$project['id']] = ['id' => $project['id'], 'title' => $project['title']];
            $items[$digest] = $item;
        }
    }
    uasort($items, static fn (array $left, array $right): int => $left['sort_key'] <=> $right['sort_key']);
    foreach ($items as &$item) {
        ksort($item['projects'], SORT_NUMERIC);
        $item['projects'] = array_values($item['projects']);
        unset($item['sort_key']);
    }
    unset($item);

    return $items;
}

/**
 * A form reference for one label of one Portfolio. It is domain-separated
 * from recommendation target references, which stay server-side because
 * recommendation keys derive from them.
 */
function evidenceHubTechnologyReviewRef(string $opaqueTargetHmacMaterial, AuthorizedPortfolioContext $context, string $digest): string
{
    if ($opaqueTargetHmacMaterial === '') {
        throw new InvalidArgumentException('Evidence Hub technology review requires HMAC material.');
    }
    $mac = hash_hmac('sha256', 'evidence_hub_technology_review_ref_v1|' . $context->portfolioId . '|' . $digest, $opaqueTargetHmacMaterial, true);

    return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
}

/**
 * A stale, forged or foreign reference resolves to nothing: references are
 * bound to this Portfolio and to labels its projects still contain.
 *
 * @param array<string, array<string, mixed>> $items
 * @return array<string, mixed>|null
 */
function evidenceHubResolveTechnologyReviewItem(array $items, string $opaqueTargetHmacMaterial, AuthorizedPortfolioContext $context, mixed $reference): ?array
{
    if (!is_string($reference) || $reference === '') {
        return null;
    }
    foreach ($items as $item) {
        if (hash_equals(evidenceHubTechnologyReviewRef($opaqueTargetHmacMaterial, $context, $item['digest']), $reference)) {
            return $item;
        }
    }

    return null;
}

/** Current, non-deprecated entries an Owner may map to. */
function evidenceHubTechnologyMappingTargetIsValid(array $taxonomy, mixed $canonicalId): bool
{
    if (!is_string($canonicalId)) {
        return false;
    }
    $entry = evidenceHubTaxonomyEntryById($taxonomy, $canonicalId);

    return $entry !== null && $entry['deprecated'] === false && $entry['merged'] === false;
}

/**
 * Mapping choices grouped by category in taxonomy order, then by display name.
 *
 * @return array<string, list<array{id: string, label: string}>>
 */
function evidenceHubTechnologyMappingOptions(array $taxonomy): array
{
    $options = array_fill_keys($taxonomy['category_order'], []);
    foreach ($taxonomy['entries'] as $entry) {
        if (evidenceHubTechnologyMappingTargetIsValid($taxonomy, $entry['technology_id'])) {
            $options[$entry['category']][] = ['id' => $entry['technology_id'], 'label' => $entry['display_name']];
        }
    }
    foreach ($options as &$entries) {
        usort($entries, static fn (array $left, array $right): int => [$left['label'], $left['id']] <=> [$right['label'], $right['id']]);
    }
    unset($entries);

    return array_filter($options, static fn (array $entries): bool => $entries !== []);
}

/**
 * Field errors are English copy keys, localized by the presenter.
 *
 * @return array{values: array{proposed_label: string, category: string, note: ?string}, errors: array<string, string>}
 */
function evidenceHubValidateTechnologyProposal(mixed $label, mixed $category, mixed $note, array $taxonomy): array
{
    $errors = [];
    $displayLabel = is_string($label) ? evidenceHubTechnologyProposalText($label) : null;
    $normalized = $displayLabel === null ? null : normalizeEvidenceHubTechnologyLabel($displayLabel);
    if ($displayLabel === null || $normalized === null) {
        $errors['proposed_label'] = 'Enter the technology name.';
    } elseif (evidenceTextUnicodeScalarLength($displayLabel) > PROJECT_TECHNOLOGY_MAX_LENGTH) {
        $errors['proposed_label'] = 'Use 60 characters or fewer for the technology name.';
    } elseif (isset($taxonomy['aliases'][$normalized])) {
        $errors['proposed_label'] = 'This technology is already in the list. Map it instead.';
    }

    if (!is_string($category) || !in_array($category, $taxonomy['category_order'], true)) {
        $errors['category'] = 'Choose a category.';
    }

    $normalizedNote = null;
    if (is_string($note) && trim($note) !== '') {
        $normalizedNote = evidenceHubTechnologyProposalText($note, true);
        if ($normalizedNote === null) {
            $errors['note'] = 'Remove unsupported characters from the note.';
        } elseif (evidenceTextUnicodeScalarLength($normalizedNote) > EVIDENCE_HUB_TECHNOLOGY_PROPOSAL_NOTE_MAXIMUM) {
            $errors['note'] = 'Use 280 characters or fewer for the note.';
        }
    } elseif ($note !== null && !is_string($note)) {
        $errors['note'] = 'Remove unsupported characters from the note.';
    }

    return [
        'values' => [
            'proposed_label' => is_string($displayLabel) ? $displayLabel : (is_string($label) ? $label : ''),
            'category' => is_string($category) ? $category : '',
            'note' => $normalizedNote,
        ],
        'errors' => $errors,
    ];
}

/** NFC with prohibited controls rejected; a label keeps one line, a note keeps its line breaks. */
function evidenceHubTechnologyProposalText(string $value, bool $multiline = false): ?string
{
    if (preg_match('//u', $value) !== 1 || evidenceTextProhibitedCharacterReason($value) !== null) {
        return null;
    }
    requireEvidenceTextUnicodeRuntime();
    $nfc = Normalizer::normalize($value, Normalizer::FORM_C);
    if (!is_string($nfc)) {
        return null;
    }
    $collapsed = $multiline ? preg_replace('/[\p{Z}\t]+/u', ' ', str_replace("\r\n", "\n", $nfc)) : preg_replace('/[\p{Z}\t\r\n]+/u', ' ', $nfc);

    return is_string($collapsed) && trim($collapsed) !== '' ? trim($collapsed) : null;
}
