<?php

declare(strict_types=1);

final class EvidenceTextEvaluationTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_text_evaluator.php';

        if (!evidenceTextUnicodeRuntimeIsAvailable()) {
            self::assertRuntimeFailsClosed();
            return;
        }

        phase2AssertSame('1.0.0', EVIDENCE_TEXT_RULE_VERSION, 'Frozen text rule version changed.');
        self::thresholdBoundaries();
        self::storageAndUnicodePolicy();
        self::placeholdersAndRepetition();
        self::acceptedLimitation();
    }

    private static function assertRuntimeFailsClosed(): void
    {
        try {
            evaluateEvidenceText('problem_statement', 'valid text');
        } catch (EvidenceTextRuntimeUnavailableException $exception) {
            phase2Assert(str_contains($exception->getMessage(), 'ext-intl Normalizer'), 'Unicode runtime failure is not actionable.');
            return;
        }

        throw new RuntimeException('Evidence text evaluation did not fail closed without ext-intl.');
    }

    private static function thresholdBoundaries(): void
    {
        foreach ([
            'problem_statement' => [60, 8, 5],
            'personal_role' => [30, 5, 4],
            'measurable_outcome' => [20, 4, 3],
        ] as $field => [$graphemes, $tokens, $distinct]) {
            foreach (['en' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], 'ar' => ['ا', 'ب', 'ج', 'د', 'ه', 'و', 'ز', 'ح']] as $language => $alphabet) {
                foreach ([-1, 0, 1] as $offset) {
                    $result = evaluateEvidenceText($field, self::graphemeBoundaryText($alphabet, $graphemes + $offset, $tokens));
                    phase2AssertSame('1.0.0', $result['rule_version'], 'Evaluator result rule version changed.');
                    phase2AssertSame($graphemes + $offset, $result['content_graphemes'], "{$field}/{$language} grapheme boundary is unstable.");
                    phase2AssertSame($offset < 0, in_array('GRAPHEME_THRESHOLD_NOT_MET', $result['reason_codes'], true), "{$field}/{$language} grapheme reason is incorrect.");
                }
                foreach ([-1, 0, 1] as $offset) {
                    $result = evaluateEvidenceText($field, self::graphemeBoundaryText($alphabet, $graphemes + 12, $tokens + $offset));
                    phase2AssertSame($tokens + $offset, $result['useful_token_count'], "{$field}/{$language} useful-token boundary is unstable.");
                    phase2AssertSame($offset < 0, in_array('USEFUL_TOKEN_THRESHOLD_NOT_MET', $result['reason_codes'], true), "{$field}/{$language} useful-token reason is incorrect.");
                }
                foreach ([-1, 0, 1] as $offset) {
                    $result = evaluateEvidenceText($field, self::distinctBoundaryText($alphabet, max($tokens, $distinct + 3), $distinct + $offset));
                    phase2AssertSame($distinct + $offset, $result['distinct_token_count'], "{$field}/{$language} distinct-token boundary is unstable.");
                    phase2AssertSame($offset < 0, in_array('DISTINCT_TOKEN_THRESHOLD_NOT_MET', $result['reason_codes'], true), "{$field}/{$language} distinct-token reason is incorrect.");
                }
            }
        }

        foreach (['problem_statement' => 2000, 'personal_role' => 1500, 'measurable_outcome' => 1000] as $field => $maximum) {
            phase2AssertSame('valid', evaluateEvidenceText($field, str_repeat('a', $maximum))['storage_validity'], "{$field} maximum scalar input was rejected.");
            self::assertReason(evaluateEvidenceText($field, str_repeat('a', $maximum + 1)), 'MAXIMUM_LENGTH_EXCEEDED');
        }
    }

    private static function storageAndUnicodePolicy(): void
    {
        $nonScalar = evaluateEvidenceText('problem_statement', ['not a scalar']);
        phase2AssertSame('unavailable', $nonScalar['evidence_status'], 'Malformed storage must not appear as evidence attention.');
        self::assertReason($nonScalar, 'NON_SCALAR_INPUT');
        self::assertReason(evaluateEvidenceText('problem_statement', "\xC3\x28"), 'INVALID_UTF8');
        phase2AssertSame('unavailable', evaluateEvidenceText('problem_statement', null)['evidence_status'], 'Null optional evidence is not unavailable.');
        $empty = evaluateEvidenceText('problem_statement', " \t\r\n ");
        phase2AssertSame('unavailable', $empty['evidence_status'], 'Normalized-empty evidence is not unavailable.');
        self::assertReason($empty, 'EMPTY_NORMALIZED_VALUE');

        $composed = evaluateEvidenceText('measurable_outcome', 'Café 24 API users completed updates safely today');
        $decomposed = evaluateEvidenceText('measurable_outcome', "Cafe\u{0301} 24 API users completed updates safely today");
        phase2AssertSame([$composed['content_graphemes'], $composed['useful_token_count'], $composed['distinct_token_count']], [$decomposed['content_graphemes'], $decomposed['useful_token_count'], $decomposed['distinct_token_count']], 'NFC-equivalent inputs changed analytical facts.');
        phase2AssertSame('مرحبا', evaluateEvidenceText('personal_role', 'مــــرحبا')['analytical_text'], 'Tatweel inflated analytical text.');
        phase2AssertSame(evidenceTextTokenComparisonKey('كَتَبَ'), evidenceTextTokenComparisonKey('كتب'), 'Arabic diacritics changed token identity.');
        phase2AssertSame(3, evaluateEvidenceText('measurable_outcome', '١٢٣')['content_graphemes'], 'Arabic digits must count as content.');
        phase2AssertSame(3, evaluateEvidenceText('measurable_outcome', '123')['content_graphemes'], 'Latin digits must count as content.');
        phase2AssertSame(0, evaluateEvidenceText('measurable_outcome', '😀 !!!')['content_graphemes'], 'Emoji or punctuation counted as content.');
        phase2AssertSame('alpha beta gamma', evaluateEvidenceText('measurable_outcome', " alpha\tbeta\r\ngamma ")['analytical_text'], 'Permitted whitespace did not normalize deterministically.');

        $zwnj = evaluateEvidenceText('measurable_outcome', "می\u{200C}شود");
        phase2AssertSame("می\u{200C}شود", $zwnj['stored_value'], 'ZWNJ stored text was changed.');
        phase2AssertSame('میشود', $zwnj['analytical_text'], 'ZWNJ was not excluded from analytical evidence.');
        $zwj = evaluateEvidenceText('measurable_outcome', "a\u{200D}b");
        phase2AssertSame('ab', $zwj['analytical_text'], 'ZWJ was not excluded from analytical evidence.');
        foreach (["a\u{200B}b" => 'ZERO_WIDTH_CHARACTER', "a\u{2060}b" => 'ZERO_WIDTH_CHARACTER', "a\u{FEFF}b" => 'ZERO_WIDTH_CHARACTER', "a\x01b" => 'PROHIBITED_CONTROL_CHARACTER', "a\u{0085}b" => 'PROHIBITED_CONTROL_CHARACTER', "a\u{202E}b" => 'PROHIBITED_DIRECTIONALITY_CHARACTER', "a\u{2066}b" => 'PROHIBITED_DIRECTIONALITY_CHARACTER'] as $value => $reason) {
            self::assertReason(evaluateEvidenceText('measurable_outcome', $value), $reason);
        }
    }

    private static function placeholdersAndRepetition(): void
    {
        $placeholder = evaluateEvidenceText('problem_statement', "  TEST\t");
        phase2AssertSame('valid', $placeholder['storage_validity'], 'Placeholder storage must stay valid.');
        self::assertReason($placeholder, 'PLACEHOLDER_CONFIRMED');
        phase2Assert(!in_array('PLACEHOLDER_CONFIRMED', evaluateEvidenceText('problem_statement', 'test project documentation with useful details')['reason_codes'], true), 'Placeholder matching became a substring match.');
        self::assertReason(evaluateEvidenceText('personal_role', 'لاحقاً'), 'PLACEHOLDER_CONFIRMED');

        foreach ([[59, 100, false], [3, 5, true], [61, 100, true]] as [$dominant, $total, $expected]) {
            $result = evaluateEvidenceText('problem_statement', self::repetitionText('echo', 'other', $dominant, $total));
            phase2AssertSame($expected, in_array('REPETITION_SUSPECTED', $result['reason_codes'], true), "{$dominant}/{$total} repetition boundary is incorrect.");
        }
        self::assertReason(evaluateEvidenceText('personal_role', 'مشروع مشروع مشروع خطة تنفيذ'), 'REPETITION_SUSPECTED');
        self::assertReason(evaluateEvidenceText('personal_role', 'Build BUILD build plan delivery'), 'REPETITION_SUSPECTED');

        phase2AssertSame('complete', evaluateEvidenceText('measurable_outcome', 'رفعت سرعة التحميل 35 بالمئة وخفضت أخطاء الطلبات خلال أسبوعين')['evidence_status'], 'Concise quantified Arabic outcome must pass.');
        phase2AssertSame('complete', evaluateEvidenceText('measurable_outcome', 'Improved page speed by 35 percent and reduced request failures')['evidence_status'], 'Concise quantified English outcome must pass.');
    }

    private static function acceptedLimitation(): void
    {
        $nonsense = 'blorf quax zindle mivora plenum drask velora quinset ramblex torvi nexora';
        phase2AssertSame('complete', evaluateEvidenceText('problem_statement', $nonsense)['evidence_status'], 'Documented diverse-nonsense limitation changed unexpectedly.');
    }

    /** @param list<string> $alphabet */
    private static function graphemeBoundaryText(array $alphabet, int $contentGraphemes, int $tokenCount): string
    {
        if ($contentGraphemes < $tokenCount || $tokenCount < 1) {
            throw new LogicException('Synthetic threshold text is invalid.');
        }
        $tokens = [str_repeat($alphabet[0], $contentGraphemes - $tokenCount + 1)];
        for ($index = 1; $index < $tokenCount; ++$index) {
            $tokens[] = $alphabet[$index % count($alphabet)];
        }
        return implode(' ', $tokens);
    }

    /** @param list<string> $alphabet */
    private static function distinctBoundaryText(array $alphabet, int $tokenCount, int $distinctTokenCount): string
    {
        $tokens = [];
        for ($index = 0; $index < $tokenCount; ++$index) {
            $letter = $alphabet[$index % $distinctTokenCount];
            $tokens[] = str_repeat($letter, 12);
        }
        return implode(' ', $tokens);
    }

    private static function repetitionText(string $dominantToken, string $otherToken, int $dominantCount, int $totalCount): string
    {
        return implode(' ', [...array_fill(0, $dominantCount, $dominantToken), ...array_fill(0, $totalCount - $dominantCount, $otherToken)]);
    }

    /** @param array<string, mixed> $result */
    private static function assertReason(array $result, string $reason): void
    {
        phase2Assert(in_array($reason, $result['reason_codes'], true), "Expected {$reason}.");
    }
}
