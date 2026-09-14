<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_text_policy.php';

final class EvidenceTextRuntimeUnavailableException extends RuntimeException
{
}

function evidenceTextUnicodeRuntimeIsAvailable(): bool
{
    return class_exists('Normalizer')
        && function_exists('grapheme_strlen')
        && function_exists('mb_strtolower');
}

function requireEvidenceTextUnicodeRuntime(): void
{
    if (!evidenceTextUnicodeRuntimeIsAvailable()) {
        throw new EvidenceTextRuntimeUnavailableException(
            'Evidence Hub text evaluation requires PHP ext-intl Normalizer, grapheme support, and mbstring.'
        );
    }
}

/** @return array{storage_validity: string, stored_value: ?string, analytical_text: ?string, comparison_text: ?string, reason_codes: list<string>} */
function normalizeEvidenceTextValue(string $field, mixed $value): array
{
    $policy = evidenceTextFieldPolicy($field);
    if ($value === null) {
        return evidenceTextUnavailableNormalization(null);
    }
    if (!is_string($value)) {
        return evidenceTextInvalidNormalization('NON_SCALAR_INPUT');
    }
    if (preg_match('//u', $value) !== 1) {
        return evidenceTextInvalidNormalization('INVALID_UTF8');
    }
    if (evidenceTextUnicodeScalarLength($value) > $policy['maximum_unicode_scalars']) {
        return evidenceTextInvalidNormalization('MAXIMUM_LENGTH_EXCEEDED');
    }

    $prohibitedReason = evidenceTextProhibitedCharacterReason($value);
    if ($prohibitedReason !== null) {
        return evidenceTextInvalidNormalization($prohibitedReason);
    }

    requireEvidenceTextUnicodeRuntime();
    $nfc = Normalizer::normalize($value, Normalizer::FORM_C);
    if (!is_string($nfc)) {
        throw new RuntimeException('Evidence Hub could not normalize valid UTF-8 text to NFC.');
    }

    $whitespaceNormalized = preg_replace('/[\p{Z}\t\r\n]+/u', ' ', $nfc);
    if (!is_string($whitespaceNormalized)) {
        throw new RuntimeException('Evidence Hub could not normalize Unicode whitespace.');
    }
    $whitespaceNormalized = trim($whitespaceNormalized, ' ');
    $analyticalText = preg_replace('/[\x{0640}\x{200C}\x{200D}]/u', '', $whitespaceNormalized);
    if (!is_string($analyticalText)) {
        throw new RuntimeException('Evidence Hub could not prepare analytical text.');
    }

    if ($analyticalText === '') {
        return evidenceTextUnavailableNormalization($value, $analyticalText);
    }

    $comparisonText = evidenceTextLatinCaseFold($analyticalText);
    return [
        'storage_validity' => 'valid',
        'stored_value' => $value,
        'analytical_text' => $analyticalText,
        'comparison_text' => $comparisonText,
        'reason_codes' => [],
    ];
}

function evidenceTextUnicodeScalarLength(string $value): int
{
    $count = preg_match_all('/[\s\S]/u', $value);
    if ($count === false) {
        throw new RuntimeException('Evidence Hub could not count Unicode scalar values.');
    }

    return $count;
}

function evidenceTextProhibitedCharacterReason(string $value): ?string
{
    if (preg_match('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', $value) === 1) {
        return 'PROHIBITED_CONTROL_CHARACTER';
    }
    if (preg_match('/[\x{200B}\x{2060}\x{FEFF}]/u', $value) === 1) {
        return 'ZERO_WIDTH_CHARACTER';
    }
    if (preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $value) === 1) {
        return 'PROHIBITED_DIRECTIONALITY_CHARACTER';
    }

    return null;
}

function evidenceTextLatinCaseFold(string $value): string
{
    $folded = preg_replace_callback(
        '/[\p{Latin}\p{M}]+/u',
        static fn (array $match): string => mb_strtolower($match[0], 'UTF-8'),
        $value,
    );
    if (!is_string($folded)) {
        throw new RuntimeException('Evidence Hub could not case-fold Latin comparison text.');
    }

    $nfc = Normalizer::normalize($folded, Normalizer::FORM_C);
    if (!is_string($nfc)) {
        throw new RuntimeException('Evidence Hub could not normalize comparison text to NFC.');
    }

    return $nfc;
}

/** @return array{storage_validity: string, stored_value: ?string, analytical_text: ?string, comparison_text: ?string, reason_codes: list<string>} */
function evidenceTextInvalidNormalization(string $reasonCode): array
{
    return [
        'storage_validity' => 'invalid',
        'stored_value' => null,
        'analytical_text' => null,
        'comparison_text' => null,
        'reason_codes' => [$reasonCode],
    ];
}

/** @return array{storage_validity: string, stored_value: ?string, analytical_text: ?string, comparison_text: ?string, reason_codes: list<string>} */
function evidenceTextUnavailableNormalization(?string $storedValue, ?string $analyticalText = null): array
{
    return [
        'storage_validity' => 'valid',
        'stored_value' => $storedValue,
        'analytical_text' => $analyticalText,
        'comparison_text' => $analyticalText,
        'reason_codes' => ['EMPTY_NORMALIZED_VALUE'],
    ];
}
