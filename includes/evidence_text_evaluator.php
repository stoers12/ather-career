<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_text_normalization.php';

/** @return array{evidence_field: string, rule_version: string, storage_validity: string, evidence_status: string, content_graphemes: int, useful_token_count: int, distinct_token_count: int, dominant_token_count: int, reason_codes: list<string>, stored_value: ?string, analytical_text: ?string} */
function evaluateEvidenceText(string $field, mixed $value): array
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
