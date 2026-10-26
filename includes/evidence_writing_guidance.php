<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_copy.php';

/*
 * Owner writing aids for the project evidence editor: per-field guidance,
 * a worked example, a sentence starter and non-blocking hints.
 *
 * Presentation only. Nothing here feeds the frozen v1 text rules
 * (evidence_text_policy.php), their reason codes or the Evidence Hub
 * contract; a hint never blocks saving and never changes completeness.
 * Copy is English source text, localized through evidenceHubText(); a
 * starter's slots are localized with it.
 *
 * Invariants, enforced by EvidenceWritingGuidanceTest in both locales:
 * - every example is complete evidence under the frozen rules and raises no hint;
 * - every starter, saved unedited, is not complete evidence and raises the
 *   unfilled-slot hint, so a starter can never pass as the Owner's evidence.
 */

/**
 * Keyed by logical editor field, in display order.
 *
 * @return array<string, array{storage_field: string, anchor: string, label: string, guidance: string, example: string, starter: string}>
 */
function evidenceWritingGuidanceFields(): array
{
    return [
        'problem' => [
            'storage_field' => 'problem_statement',
            'anchor' => 'problem',
            'label' => 'Problem',
            'guidance' => 'Describe the specific problem, who it affected, and why it mattered.',
            'example' => 'Manual weekly reporting took the operations team six hours and delayed decisions every Monday.',
            'starter' => '[Who] had [problem], which mattered because [impact].',
        ],
        'personal_role' => [
            'storage_field' => 'personal_role',
            'anchor' => 'personal-role',
            'label' => 'Personal role',
            'guidance' => 'Explain what you personally owned, decided, or implemented.',
            'example' => 'I designed the data pipeline and implemented its validation rules.',
            'starter' => 'I [action] the [part] by [how].',
        ],
        'measurable_outcome' => [
            'storage_field' => 'measurable_outcome',
            'anchor' => 'measurable-outcome',
            'label' => 'Measurable outcome',
            'guidance' => 'State the result using a number, comparison, or observable change.',
            'example' => 'Cut weekly reporting time from 6 hours to 1 hour.',
            'starter' => '[Metric]: [before] → [after]',
        ],
    ];
}

/**
 * Hint rules. 'when' is 'present' (show while the pattern matches) or
 * 'absent' (show while it does not); 'fields' null applies to every field.
 * A null pattern is derived from the field's starter slots, so only a slot
 * the Owner has not replaced matches, never brackets they wrote themselves.
 *
 * @return array<string, array{fields: ?list<string>, pattern: ?string, when: string, message: string}>
 */
function evidenceWritingHintRules(): array
{
    return [
        'unfilled_slot' => [
            'fields' => null,
            'pattern' => null,
            'when' => 'present',
            'message' => 'Replace each [bracketed] part with details from your project.',
        ],
        'outcome_without_number' => [
            'fields' => ['measurable_outcome'],
            'pattern' => '\p{Nd}',
            'when' => 'absent',
            'message' => 'No number yet. If you have one, add a count, percentage, or before-and-after value.',
        ],
    ];
}

/**
 * Hints for one field with patterns resolved for the locale. A pattern is
 * shared verbatim by PHP (/…/u) and the editor script (new RegExp(…, 'u')),
 * so it stays within the syntax both accept.
 *
 * @return array<string, array{pattern: string, when: string, message: string}>
 */
function evidenceWritingHintsForField(string $field, string $locale): array
{
    $hints = [];
    foreach (evidenceWritingHintRules() as $id => $rule) {
        if ($rule['fields'] !== null && !in_array($field, $rule['fields'], true)) {
            continue;
        }
        $hints[$id] = [
            'pattern' => $rule['pattern'] ?? evidenceWritingStarterSlotPattern($field, $locale),
            'when' => $rule['when'],
            'message' => $rule['message'],
        ];
    }

    return $hints;
}

/** Slots are letters, marks and spaces only, so they need no escaping beyond their brackets. */
function evidenceWritingStarterSlotPattern(string $field, string $locale): string
{
    $starter = evidenceHubText(evidenceWritingGuidanceFields()[$field]['starter'] ?? throw new InvalidArgumentException('Evidence writing field is not supported.'), $locale);
    preg_match_all('/\[([\p{L}\p{M} ]+)\]/u', $starter, $slots);

    return implode('|', array_map(static fn (string $slot): string => '\[' . $slot . '\]', $slots[1]));
}

/**
 * Hints that apply to the current value. Empty or non-text values raise none:
 * the field status already explains missing evidence.
 *
 * @return list<string>
 */
function evidenceWritingActiveHints(string $field, mixed $value, string $locale = 'en'): array
{
    if (!is_string($value) || trim($value) === '' || preg_match('//u', $value) !== 1) {
        return [];
    }

    $active = [];
    foreach (evidenceWritingHintsForField($field, $locale) as $id => $rule) {
        $matches = preg_match('/' . $rule['pattern'] . '/u', $value) === 1;
        if ($matches === ($rule['when'] === 'present')) {
            $active[] = $id;
        }
    }

    return $active;
}
