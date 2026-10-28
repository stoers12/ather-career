<?php

declare(strict_types=1);

namespace Ather\EvidenceHub\Text;

use InvalidArgumentException;
use LogicException;
use Normalizer;
use RuntimeException;

final class EvidenceTextEvaluator
{
    /** @return array{evidence_field: string, rule_version: string, storage_validity: string, evidence_status: string, content_graphemes: int, useful_token_count: int, distinct_token_count: int, dominant_token_count: int, reason_codes: list<string>, stored_value: ?string, analytical_text: ?string} */
    public static function evaluate(string $field, mixed $value, string $ruleVersion = EvidenceTextPolicy::LIVE_RULE_VERSION): array
    {
        if (!in_array($ruleVersion, EvidenceTextPolicy::SUPPORTED_RULE_VERSIONS, true)) {
            throw new InvalidArgumentException('Evidence Hub text rule version is unsupported.');
        }
        $result = self::evaluateV100($field, $value);
        if ($ruleVersion === EvidenceTextPolicy::RULE_VERSION) {
            return $result;
        }
        $result['rule_version'] = $ruleVersion;
        if ($result['evidence_status'] === 'unavailable') {
            return $result;
        }
        $raw = $result;
        $result['content_graphemes'] = self::effectiveContentGraphemeCount($result['analytical_text']);
        $tokens = self::usefulTokensForVersion($result['analytical_text'], $ruleVersion);
        $frequencies = array_count_values(array_map(self::tokenComparisonKey(...), $tokens));
        $result['useful_token_count'] = count($tokens);
        $result['distinct_token_count'] = count($frequencies);
        $result['dominant_token_count'] = $frequencies === [] ? 0 : max($frequencies);
        if (in_array('PLACEHOLDER_CONFIRMED', $result['reason_codes'], true)) {
            return $result;
        }
        $policy = EvidenceTextPolicy::fieldPolicy($field);
        $reasons = [];
        foreach ([
            'content_graphemes' => ['minimum_content_graphemes', 'GRAPHEME_THRESHOLD_NOT_MET'],
            'useful_token_count' => ['minimum_useful_tokens', 'USEFUL_TOKEN_THRESHOLD_NOT_MET'],
            'distinct_token_count' => ['minimum_distinct_tokens', 'DISTINCT_TOKEN_THRESHOLD_NOT_MET'],
        ] as $count => [$minimum, $reason]) {
            if ($result[$count] < $policy[$minimum]) {
                $reasons[] = $count !== 'distinct_token_count' && $raw[$count] >= $policy[$minimum] ? 'CHARACTER_RUN_SUSPECTED' : $reason;
            }
        }
        if (count($tokens) >= 5 && $result['dominant_token_count'] * 10000 >= count($tokens) * 6000) {
            $reasons[] = 'REPETITION_SUSPECTED';
        }
        if (count($tokens) >= 12 && count($frequencies) * 10000 < count($tokens) * 4000) {
            $reasons[] = 'LOW_DISTINCT_TOKEN_RATIO';
        }
        $result['evidence_status'] = $reasons === [] ? 'complete' : 'needs_attention';
        $result['reason_codes'] = array_values(array_unique($reasons ?: ['TEXT_COMPLETE']));
        return $result;
    }

    /** Frozen baseline: retain its calculations, field order and values for historical contracts. */
    public static function evaluateV100(string $field, mixed $value): array
    {
        $policy = EvidenceTextPolicy::fieldPolicy($field);
        $normalized = EvidenceTextNormalizer::normalize($field, $value);
        if ($normalized['storage_validity'] === 'invalid') {
            return self::result($field, 'invalid', 'unavailable', 0, 0, 0, 0, $normalized['reason_codes'], null, null);
        }
        if ($normalized['analytical_text'] === null || $normalized['analytical_text'] === '') {
            $reasonCodes = $value === null ? ['FIELD_NOT_AVAILABLE'] : $normalized['reason_codes'];
            return self::result($field, 'valid', 'unavailable', 0, 0, 0, 0, $reasonCodes, $normalized['stored_value'], $normalized['analytical_text']);
        }

        $contentGraphemes = self::contentGraphemeCount($normalized['analytical_text']);
        $tokens = self::usefulTokens($normalized['analytical_text']);
        $comparisonKeys = array_map(self::tokenComparisonKey(...), $tokens);
        $tokenFrequencies = array_count_values($comparisonKeys);
        $dominantTokenCount = $tokenFrequencies === [] ? 0 : max($tokenFrequencies);
        $reasonCodes = [];

        if (in_array($normalized['comparison_text'], self::placeholderComparisonValues(), true)) {
            $reasonCodes[] = 'PLACEHOLDER_CONFIRMED';
        } else {
            if ($contentGraphemes < $policy['minimum_content_graphemes']) {
                $reasonCodes[] = 'GRAPHEME_THRESHOLD_NOT_MET';
            }
            if (count($tokens) < $policy['minimum_useful_tokens']) {
                $reasonCodes[] = 'USEFUL_TOKEN_THRESHOLD_NOT_MET';
            }
            if (count($tokenFrequencies) < $policy['minimum_distinct_tokens']) {
                $reasonCodes[] = 'DISTINCT_TOKEN_THRESHOLD_NOT_MET';
            }
            if (count($tokens) >= 5 && $dominantTokenCount * 10000 >= count($tokens) * 6000) {
                $reasonCodes[] = 'REPETITION_SUSPECTED';
            }
        }

        if ($reasonCodes === []) {
            $reasonCodes[] = 'TEXT_COMPLETE';
            $status = 'complete';
        } else {
            $status = 'needs_attention';
        }

        return self::result(
            $field,
            'valid',
            $status,
            $contentGraphemes,
            count($tokens),
            count($tokenFrequencies),
            $dominantTokenCount,
            $reasonCodes,
            $normalized['stored_value'],
            $normalized['analytical_text'],
        );
    }

    /** @return array{evidence_field: string, rule_version: string, storage_validity: string, evidence_status: string, content_graphemes: int, useful_token_count: int, distinct_token_count: int, dominant_token_count: int, reason_codes: list<string>, stored_value: ?string, analytical_text: ?string} */
    public static function result(string $field, string $storageValidity, string $evidenceStatus, int $contentGraphemes, int $usefulTokenCount, int $distinctTokenCount, int $dominantTokenCount, array $reasonCodes, ?string $storedValue, ?string $analyticalText): array
    {
        return [
            'evidence_field' => $field,
            'rule_version' => EvidenceTextPolicy::RULE_VERSION,
            'storage_validity' => $storageValidity,
            'evidence_status' => $evidenceStatus,
            'content_graphemes' => $contentGraphemes,
            'useful_token_count' => $usefulTokenCount,
            'distinct_token_count' => $distinctTokenCount,
            'dominant_token_count' => $dominantTokenCount,
            'reason_codes' => $reasonCodes,
            'stored_value' => $storedValue,
            'analytical_text' => $analyticalText,
        ];
    }

    public static function contentGraphemeCount(string $analyticalText): int
    {
        $matches = [];
        $result = preg_match_all('/\\X/u', $analyticalText, $matches);
        if ($result === false) {
            throw new RuntimeException('Evidence Hub could not segment extended grapheme clusters.');
        }

        $contentCount = 0;
        foreach ($matches[0] as $grapheme) {
            if (preg_match('/[\p{L}\p{N}]/u', $grapheme) === 1) {
                ++$contentCount;
            }
        }

        return $contentCount;
    }

    /** Counts used by 1.1.0 thresholds; a letter run of at least three counts once. */
    public static function effectiveContentGraphemeCount(string $analyticalText): int
    {
        preg_match_all('/\X/u', $analyticalText, $matches);
        $count = 0;
        $graphemes = $matches[0];
        for ($i = 0; $i < count($graphemes); ++$i) {
            $grapheme = $graphemes[$i];
            $length = 1;
            if (preg_match('/\p{L}/u', $grapheme) === 1) {
                $key = EvidenceTextNormalizer::latinCaseFold($grapheme);
                while (isset($graphemes[$i + $length]) && EvidenceTextNormalizer::latinCaseFold($graphemes[$i + $length]) === $key) {
                    ++$length;
                }
            }
            if (preg_match('/[\p{L}\p{N}]/u', $grapheme) === 1) {
                $count += $length >= 3 ? 1 : $length;
            }
            $i += $length - 1;
        }
        return $count;
    }

    /** @return list<string> */
    public static function usefulTokensForVersion(string $text, string $ruleVersion): array
    {
        $tokens = self::usefulTokens($text);
        if ($ruleVersion === EvidenceTextPolicy::RULE_VERSION) {
            return $tokens;
        }
        return array_values(array_filter($tokens, static function (string $token): bool {
            preg_match_all('/\X/u', EvidenceTextNormalizer::latinCaseFold($token), $matches);
            return !(count($matches[0]) >= 3 && count(array_unique($matches[0])) === 1 && preg_match('/\p{L}/u', $matches[0][0]) === 1);
        }));
    }

    /** @return list<string> */
    public static function usefulTokens(string $analyticalText): array
    {
        $matches = [];
        $result = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}]*/u', $analyticalText, $matches);
        if ($result === false) {
            throw new RuntimeException('Evidence Hub could not tokenize analytical text.');
        }

        return $matches[0];
    }

    public static function tokenComparisonKey(string $token): string
    {
        $decomposed = Normalizer::normalize($token, Normalizer::FORM_D);
        if (!is_string($decomposed)) {
            throw new RuntimeException('Evidence Hub could not decompose a token.');
        }
        $withoutMarks = preg_replace('/\p{M}+/u', '', $decomposed);
        if (!is_string($withoutMarks)) {
            throw new RuntimeException('Evidence Hub could not remove token combining marks.');
        }

        return EvidenceTextNormalizer::latinCaseFold($withoutMarks);
    }

    /** @return list<string> */
    public static function placeholderComparisonValues(): array
    {
        return array_map(self::placeholderComparisonValue(...), EvidenceTextPolicy::PLACEHOLDERS);
    }

    public static function placeholderComparisonValue(string $placeholder): string
    {
        $normalization = EvidenceTextNormalizer::normalize('measurable_outcome', $placeholder);
        if ($normalization['comparison_text'] === null) {
            throw new LogicException('Evidence Hub placeholder vocabulary must be valid analytical text.');
        }

        return $normalization['comparison_text'];
    }

    /**
     * Compare only these three fields of one project. Shingles and source identities
     * stay local: callers receive the usual evaluations with status/reason changes.
     *
     * @param array<string, mixed> $values
     * @return array<string, array<string, mixed>>
     */
    public static function evaluateProjectTexts(array $values, string $ruleVersion = EvidenceTextPolicy::LIVE_RULE_VERSION): array
    {
        $evaluations = [];
        foreach (EvidenceTextPolicy::fieldNames() as $field) {
            $evaluations[$field] = self::evaluate($field, $values[$field] ?? null, $ruleVersion);
        }
        if ($ruleVersion === EvidenceTextPolicy::RULE_VERSION) {
            return $evaluations;
        }
        $shingles = [];
        $comparisons = [];
        foreach ($evaluations as $field => $evaluation) {
            $comparisons[$field] = $evaluation['analytical_text'] === null ? null : EvidenceTextNormalizer::latinCaseFold($evaluation['analytical_text']);
            $keys = array_map(self::tokenComparisonKey(...), self::usefulTokensForVersion($evaluation['analytical_text'] ?? '', $ruleVersion));
            $shingles[$field] = [];
            for ($i = 0; $i + 2 < count($keys); ++$i) {
                $shingles[$field][json_encode(array_slice($keys, $i, 3), JSON_THROW_ON_ERROR)] = true;
            }
        }
        $original = $evaluations;
        foreach (EvidenceTextPolicy::fieldNames() as $i => $field) {
            if ($original[$field]['evidence_status'] !== 'complete') continue;
            foreach (EvidenceTextPolicy::fieldNames() as $j => $other) {
                if ($i === $j) continue;
                $exact = $comparisons[$field] !== null && $comparisons[$field] !== '' && $comparisons[$field] === $comparisons[$other];
                $shared = count(array_intersect_key($shingles[$field], $shingles[$other]));
                $contained = count($shingles[$field]) >= 3 && $shared * 10000 >= count($shingles[$field]) * 8000;
                $mutual = $exact || (count($shingles[$other]) >= 3 && $shared * 10000 >= count($shingles[$other]) * 8000);
                if (($exact || $contained) && (!$mutual || $i > $j)) {
                    $evaluations[$field]['evidence_status'] = 'needs_attention';
                    $evaluations[$field]['reason_codes'] = ['DUPLICATED_ACROSS_FIELDS'];
                    break;
                }
            }
        }
        return $evaluations;
    }
}
