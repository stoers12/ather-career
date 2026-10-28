<?php

declare(strict_types=1);

namespace Ather\EvidenceHub\Text;

use InvalidArgumentException;

final class EvidenceTextPolicy
{
    public const RULE_VERSION = '1.0.0';
    public const LIVE_RULE_VERSION = '1.1.0';
    public const SUPPORTED_RULE_VERSIONS = ['1.0.0', '1.1.0'];

    public const FIELD_POLICIES = [
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

    public const PLACEHOLDERS = [
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
    public static function fieldPolicy(string $field): array
    {
        if (!array_key_exists($field, self::FIELD_POLICIES)) {
            throw new InvalidArgumentException('Evidence Hub field is not supported.');
        }

        return self::FIELD_POLICIES[$field];
    }

    /** @return list<string> */
    public static function fieldNames(): array
    {
        return array_keys(self::FIELD_POLICIES);
    }
}
