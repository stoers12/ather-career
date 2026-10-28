<?php

declare(strict_types=1);

// Compatibility wrappers: the implementation lives in src/EvidenceHub/Text.

require_once __DIR__ . '/evidence_text_policy.php';

use Ather\EvidenceHub\Text\EvidenceTextNormalizer;

function evidenceTextUnicodeRuntimeIsAvailable(): bool
{
    return EvidenceTextNormalizer::unicodeRuntimeIsAvailable();
}

function requireEvidenceTextUnicodeRuntime(): void
{
    EvidenceTextNormalizer::requireUnicodeRuntime();
}

/** @return array{storage_validity: string, stored_value: ?string, analytical_text: ?string, comparison_text: ?string, reason_codes: list<string>} */
function normalizeEvidenceTextValue(string $field, mixed $value): array
{
    return EvidenceTextNormalizer::normalize($field, $value);
}

function evidenceTextUnicodeScalarLength(string $value): int
{
    return EvidenceTextNormalizer::unicodeScalarLength($value);
}

function evidenceTextProhibitedCharacterReason(string $value): ?string
{
    return EvidenceTextNormalizer::prohibitedCharacterReason($value);
}

function evidenceTextLatinCaseFold(string $value): string
{
    return EvidenceTextNormalizer::latinCaseFold($value);
}

/** @return array{storage_validity: string, stored_value: ?string, analytical_text: ?string, comparison_text: ?string, reason_codes: list<string>} */
function evidenceTextInvalidNormalization(string $reasonCode): array
{
    return EvidenceTextNormalizer::invalid($reasonCode);
}

/** @return array{storage_validity: string, stored_value: ?string, analytical_text: ?string, comparison_text: ?string, reason_codes: list<string>} */
function evidenceTextUnavailableNormalization(?string $storedValue, ?string $analyticalText = null): array
{
    return EvidenceTextNormalizer::unavailable($storedValue, $analyticalText);
}
