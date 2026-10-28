<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_text_normalization.php';

/** @return array{evidence_field: string, rule_version: string, storage_validity: string, evidence_status: string, content_graphemes: int, useful_token_count: int, distinct_token_count: int, dominant_token_count: int, reason_codes: list<string>, stored_value: ?string, analytical_text: ?string} */
function evaluateEvidenceText(string $field, mixed $value, string $ruleVersion = EVIDENCE_TEXT_LIVE_RULE_VERSION): array
{
    if (!in_array($ruleVersion, EVIDENCE_TEXT_SUPPORTED_RULE_VERSIONS, true)) {
        throw new InvalidArgumentException('Evidence Hub text rule version is unsupported.');
    }
    $result = evaluateEvidenceTextV100($field, $value);
    if ($ruleVersion === EVIDENCE_TEXT_RULE_VERSION) {
        return $result;
    }
    $result['rule_version'] = $ruleVersion;
    if ($result['evidence_status'] === 'unavailable') {
        return $result;
    }
    $raw = $result;
    $result['content_graphemes'] = evidenceTextEffectiveContentGraphemeCount($result['analytical_text']);
    $tokens = evidenceTextUsefulTokensForVersion($result['analytical_text'], $ruleVersion);
    $frequencies = array_count_values(array_map('evidenceTextTokenComparisonKey', $tokens));
    $result['useful_token_count'] = count($tokens);
    $result['distinct_token_count'] = count($frequencies);
    $result['dominant_token_count'] = $frequencies === [] ? 0 : max($frequencies);
    if (in_array('PLACEHOLDER_CONFIRMED', $result['reason_codes'], true)) {
        return $result;
    }
    $policy = evidenceTextFieldPolicy($field);
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
function evaluateEvidenceTextV100(string $field, mixed $value): array
{
    $policy = evidenceTextFieldPolicy($field);
    $normalized = normalizeEvidenceTextValue($field, $value);
    if ($normalized['storage_validity'] === 'invalid') {
        return evidenceTextEvaluationResult($field, 'invalid', 'unavailable', 0, 0, 0, 0, $normalized['reason_codes'], null, null);
    }
    if ($normalized['analytical_text'] === null || $normalized['analytical_text'] === '') {
        $reasonCodes = $value === null ? ['FIELD_NOT_AVAILABLE'] : $normalized['reason_codes'];
        return evidenceTextEvaluationResult($field, 'valid', 'unavailable', 0, 0, 0, 0, $reasonCodes, $normalized['stored_value'], $normalized['analytical_text']);
    }

    $contentGraphemes = evidenceTextContentGraphemeCount($normalized['analytical_text']);
    $tokens = evidenceTextUsefulTokens($normalized['analytical_text']);
    $comparisonKeys = array_map('evidenceTextTokenComparisonKey', $tokens);
    $tokenFrequencies = array_count_values($comparisonKeys);
    $dominantTokenCount = $tokenFrequencies === [] ? 0 : max($tokenFrequencies);
    $reasonCodes = [];

    if (in_array($normalized['comparison_text'], evidenceTextPlaceholderComparisonValues(), true)) {
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

    return evidenceTextEvaluationResult(
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
function evidenceTextEvaluationResult(string $field, string $storageValidity, string $evidenceStatus, int $contentGraphemes, int $usefulTokenCount, int $distinctTokenCount, int $dominantTokenCount, array $reasonCodes, ?string $storedValue, ?string $analyticalText): array
{
    return [
        'evidence_field' => $field,
        'rule_version' => EVIDENCE_TEXT_RULE_VERSION,
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

function evidenceTextContentGraphemeCount(string $analyticalText): int
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
function evidenceTextEffectiveContentGraphemeCount(string $analyticalText): int
{
    preg_match_all('/\X/u', $analyticalText, $matches);
    $count = 0;
    $graphemes = $matches[0];
    for ($i = 0; $i < count($graphemes); ++$i) {
        $grapheme = $graphemes[$i];
        $length = 1;
        if (preg_match('/\p{L}/u', $grapheme) === 1) {
            $key = evidenceTextLatinCaseFold($grapheme);
            while (isset($graphemes[$i + $length]) && evidenceTextLatinCaseFold($graphemes[$i + $length]) === $key) {
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
function evidenceTextUsefulTokensForVersion(string $text, string $ruleVersion): array
{
    $tokens = evidenceTextUsefulTokens($text);
    if ($ruleVersion === EVIDENCE_TEXT_RULE_VERSION) {
        return $tokens;
    }
    return array_values(array_filter($tokens, static function (string $token): bool {
        preg_match_all('/\X/u', evidenceTextLatinCaseFold($token), $matches);
        return !(count($matches[0]) >= 3 && count(array_unique($matches[0])) === 1 && preg_match('/\p{L}/u', $matches[0][0]) === 1);
    }));
}

/** @return list<string> */
function evidenceTextUsefulTokens(string $analyticalText): array
{
    $matches = [];
    $result = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}]*/u', $analyticalText, $matches);
    if ($result === false) {
        throw new RuntimeException('Evidence Hub could not tokenize analytical text.');
    }

    return $matches[0];
}

function evidenceTextTokenComparisonKey(string $token): string
{
    $decomposed = Normalizer::normalize($token, Normalizer::FORM_D);
    if (!is_string($decomposed)) {
        throw new RuntimeException('Evidence Hub could not decompose a token.');
    }
    $withoutMarks = preg_replace('/\p{M}+/u', '', $decomposed);
    if (!is_string($withoutMarks)) {
        throw new RuntimeException('Evidence Hub could not remove token combining marks.');
    }

    return evidenceTextLatinCaseFold($withoutMarks);
}

/** @return list<string> */
function evidenceTextPlaceholderComparisonValues(): array
{
    return array_map('evidenceTextPlaceholderComparisonValue', EVIDENCE_TEXT_PLACEHOLDERS);
}

function evidenceTextPlaceholderComparisonValue(string $placeholder): string
{
    $normalization = normalizeEvidenceTextValue('measurable_outcome', $placeholder);
    if ($normalization['comparison_text'] === null) {
        throw new LogicException('Evidence Hub placeholder vocabulary must be valid analytical text.');
    }

    return $normalization['comparison_text'];
}
