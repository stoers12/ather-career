<?php

declare(strict_types=1);

// Compatibility wrappers: the implementation lives in src/EvidenceHub/Text.

require_once __DIR__ . '/evidence_text_normalization.php';

use Ather\EvidenceHub\Text\EvidenceTextEvaluator;

/** @return array{evidence_field: string, rule_version: string, storage_validity: string, evidence_status: string, content_graphemes: int, useful_token_count: int, distinct_token_count: int, dominant_token_count: int, reason_codes: list<string>, stored_value: ?string, analytical_text: ?string} */
function evaluateEvidenceText(string $field, mixed $value, string $ruleVersion = EVIDENCE_TEXT_LIVE_RULE_VERSION): array
{
    return EvidenceTextEvaluator::evaluate($field, $value, $ruleVersion);
}

/** Frozen baseline: retain its calculations, field order and values for historical contracts. */
function evaluateEvidenceTextV100(string $field, mixed $value): array
{
    return EvidenceTextEvaluator::evaluateV100($field, $value);
}

/** @return array{evidence_field: string, rule_version: string, storage_validity: string, evidence_status: string, content_graphemes: int, useful_token_count: int, distinct_token_count: int, dominant_token_count: int, reason_codes: list<string>, stored_value: ?string, analytical_text: ?string} */
function evidenceTextEvaluationResult(string $field, string $storageValidity, string $evidenceStatus, int $contentGraphemes, int $usefulTokenCount, int $distinctTokenCount, int $dominantTokenCount, array $reasonCodes, ?string $storedValue, ?string $analyticalText): array
{
    return EvidenceTextEvaluator::result($field, $storageValidity, $evidenceStatus, $contentGraphemes, $usefulTokenCount, $distinctTokenCount, $dominantTokenCount, $reasonCodes, $storedValue, $analyticalText);
}

function evidenceTextContentGraphemeCount(string $analyticalText): int
{
    return EvidenceTextEvaluator::contentGraphemeCount($analyticalText);
}

/** Counts used by 1.1.0 thresholds; a letter run of at least three counts once. */
function evidenceTextEffectiveContentGraphemeCount(string $analyticalText): int
{
    return EvidenceTextEvaluator::effectiveContentGraphemeCount($analyticalText);
}

/** @return list<string> */
function evidenceTextUsefulTokensForVersion(string $text, string $ruleVersion): array
{
    return EvidenceTextEvaluator::usefulTokensForVersion($text, $ruleVersion);
}

/** @return list<string> */
function evidenceTextUsefulTokens(string $analyticalText): array
{
    return EvidenceTextEvaluator::usefulTokens($analyticalText);
}

function evidenceTextTokenComparisonKey(string $token): string
{
    return EvidenceTextEvaluator::tokenComparisonKey($token);
}

/** @return list<string> */
function evidenceTextPlaceholderComparisonValues(): array
{
    return EvidenceTextEvaluator::placeholderComparisonValues();
}

function evidenceTextPlaceholderComparisonValue(string $placeholder): string
{
    return EvidenceTextEvaluator::placeholderComparisonValue($placeholder);
}

/**
 * @param array<string, mixed> $values
 * @return array<string, array<string, mixed>>
 */
function evaluateEvidenceProjectTexts(array $values, string $ruleVersion = EVIDENCE_TEXT_LIVE_RULE_VERSION): array
{
    return EvidenceTextEvaluator::evaluateProjectTexts($values, $ruleVersion);
}
