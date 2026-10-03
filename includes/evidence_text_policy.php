<?php

declare(strict_types=1);

const EVIDENCE_TEXT_RULE_VERSION = '1.0.0';

const EVIDENCE_TEXT_FIELD_POLICIES = [
    'problem_statement' => [
        'maximum_unicode_scalars' => 2000,
        'minimum_content_graphemes' => 60,
        'minimum_useful_tokens' => 8,
        'minimum_distinct_tokens' => 5,
    ],
    'personal_role' => [
        'maximum_unicode_scalars' => 1500,
        'minimum_content_graphemes' => 30,
        'minimum_useful_tokens' => 5,
        'minimum_distinct_tokens' => 4,
    ],
    'measurable_outcome' => [
        'maximum_unicode_scalars' => 1000,
        'minimum_content_graphemes' => 20,
        'minimum_useful_tokens' => 4,
        'minimum_distinct_tokens' => 3,
    ],
];

const EVIDENCE_TEXT_PLACEHOLDERS = [
    'lorem ipsum',
    'lorem ipsum dolor sit amet',
    'todo',
    'tbd',
    'coming soon',
    'test',
    'placeholder',
    'n/a',
    'لاحقاً',
    'لاحقا',
    'قريباً',
    'قريبا',
    'تجريبي',
    'اختبار',
    'غير متوفر',
];

/** @return array{maximum_unicode_scalars: int, minimum_content_graphemes: int, minimum_useful_tokens: int, minimum_distinct_tokens: int} */
function evidenceTextFieldPolicy(string $field): array
{
    if (!array_key_exists($field, EVIDENCE_TEXT_FIELD_POLICIES)) {
        throw new InvalidArgumentException('Evidence Hub field is not supported.');
    }

    return EVIDENCE_TEXT_FIELD_POLICIES[$field];
}

/** @return list<string> */
function evidenceTextFieldNames(): array
{
    return array_keys(EVIDENCE_TEXT_FIELD_POLICIES);
}
