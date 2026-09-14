<?php

declare(strict_types=1);

final class EvidenceTextEvaluationTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_text_evaluator.php';

        if (!evidenceTextUnicodeRuntimeIsAvailable()) {
            throw new RuntimeException('Evidence Golden fixture binding requires ext-intl Normalizer, grapheme support, and mbstring.');
        }

        phase2AssertSame('1.0.0', EVIDENCE_TEXT_RULE_VERSION, 'Frozen text rule version changed.');
        self::goldenFixtureBindings();
        self::thresholdBoundaries();
        self::storageAndUnicodePolicy();
        self::placeholdersAndRepetition();
        self::acceptedLimitation();
    }

    private static function goldenFixtureBindings(): void
    {
        $fixtures = self::loadGoldenFixtures();
        phase2AssertSame([], $fixtures['text_fixture_execution']['descriptive_text_cases'] ?? null, 'All TEXT fixtures must be executable.');
        $profiles = $fixtures['text_generator_profiles'] ?? null;
        phase2Assert(is_array($profiles), 'Golden text generator profiles are missing.');

        foreach ($fixtures['threshold_boundary_matrix'] ?? [] as $matrix) {
            $matrixId = self::fixtureId($matrix);
            phase2AssertSame('executable', $matrix['execution'] ?? null, "{$matrixId} must be executable.");
            $profileName = $matrix['generator_profile'] ?? null;
            phase2Assert(is_string($profileName) && isset($profiles[$profileName]), "{$matrixId} has no generator profile.");
            foreach ($matrix['cases'] ?? [] as $case) {
                $case['field'] = $matrix['field'] ?? null;
                $case['generator_profile'] = $profileName;
                self::assertGoldenFixture($case, $profiles);
            }
        }

        $evaluations = [];
        foreach ($fixtures['text_policy_cases'] ?? [] as $case) {
            $result = self::assertGoldenFixture($case, $profiles);
            $evaluations[self::fixtureId($case)] = $result;
        }
        foreach ($fixtures['repetition_cases'] ?? [] as $case) {
            self::assertGoldenFixture($case, $profiles);
        }

        $equivalent = $evaluations['TEXT-NFC-DECOMPOSED'] ?? null;
        $composed = $evaluations['TEXT-NFC-COMPOSED'] ?? null;
        phase2Assert(is_array($equivalent) && is_array($composed), 'NFC Golden fixtures are missing.');
        phase2AssertSame(
            [$composed['content_graphemes'], $composed['useful_token_count'], $composed['distinct_token_count']],
            [$equivalent['content_graphemes'], $equivalent['useful_token_count'], $equivalent['distinct_token_count']],
            'NFC Golden fixture facts diverged.',
        );
    }

    /** @return array<string, mixed> */
    private static function loadGoldenFixtures(): array
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-hub-golden-fixtures.json');
        phase2Assert(is_string($contents), 'Golden text fixtures are unreadable.');
        $fixtures = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        phase2Assert(is_array($fixtures) && !array_is_list($fixtures), 'Golden text fixtures must decode to an object.');

        return $fixtures;
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $profiles @return array<string, mixed> */
    private static function assertGoldenFixture(array $fixture, array $profiles): array
    {
        $id = self::fixtureId($fixture);
        $field = $fixture['field'] ?? null;
        $expected = $fixture['expected'] ?? null;
        $calculated = $fixture['calculated'] ?? [];
        phase2Assert(is_string($field), "{$id} has no evidence field.");
        phase2Assert(is_array($expected), "{$id} has no expected result.");
        phase2Assert(is_array($calculated), "{$id} calculated facts are malformed.");

        $input = self::materializeGoldenInput($fixture, $profiles);
        $result = evaluateEvidenceText($field, $input);

        phase2AssertSame($expected['storage_validity'] ?? null, $result['storage_validity'], "{$id} storage_validity mismatch.");
        phase2AssertSame($expected['evidence_status'] ?? null, $result['evidence_status'], "{$id} evidence_status mismatch.");
        self::assertExactReasonCodes($id, $expected['reason_codes'] ?? null, $result['reason_codes']);

        foreach ([
            'content_graphemes' => 'content_graphemes',
            'useful_tokens' => 'useful_token_count',
            'distinct_tokens' => 'distinct_token_count',
            'dominant_token_count' => 'dominant_token_count',
        ] as $fixtureKey => $resultKey) {
            if (array_key_exists($fixtureKey, $calculated)) {
                phase2AssertSame($calculated[$fixtureKey], $result[$resultKey], "{$id} {$fixtureKey} mismatch.");
            }
        }
        if (array_key_exists('dominant_token_bps', $calculated)) {
            phase2Assert($result['useful_token_count'] > 0, "{$id} has a dominant-token BPS without tokens.");
            phase2AssertSame(
                $calculated['dominant_token_bps'],
                intdiv($result['dominant_token_count'] * 10000, $result['useful_token_count']),
                "{$id} dominant_token_bps mismatch.",
            );
        }
        if (isset($fixture['analytical_copy'])) {
            phase2AssertSame($fixture['analytical_copy'], $result['analytical_text'], "{$id} analytical_copy mismatch.");
        }

        return $result;
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $profiles */
    private static function materializeGoldenInput(array $fixture, array $profiles): mixed
    {
        if (array_key_exists('input', $fixture)) {
            return $fixture['input'];
        }
        if (($fixture['input_encoding'] ?? null) === 'base64_invalid_utf8') {
            $input = base64_decode((string) ($fixture['input_base64'] ?? ''), true);
            phase2Assert(is_string($input), self::fixtureId($fixture) . ' invalid UTF-8 bytes are not valid base64.');
            return $input;
        }

        $specification = $fixture['input_spec'] ?? null;
        phase2Assert(is_array($specification), self::fixtureId($fixture) . ' has no executable input.');
        return match ($specification['kind'] ?? null) {
            'calibrated_token_stream', 'balanced_token_stream' => self::materializeBoundaryTokenStream($fixture, $specification, $profiles),
            'repeat_scalar' => self::materializeRepeatedScalar($fixture, $specification),
            'dominant_unique_token_stream' => self::materializeDominantTokenStream($fixture, $specification),
            default => throw new RuntimeException(self::fixtureId($fixture) . ' has an unknown input generator.'),
        };
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $specification @param array<string, mixed> $profiles */
    private static function materializeBoundaryTokenStream(array $fixture, array $specification, array $profiles): string
    {
        $id = self::fixtureId($fixture);
        $profileName = $fixture['generator_profile'] ?? null;
        $profile = is_string($profileName) ? ($profiles[$profileName] ?? null) : null;
        phase2Assert(is_array($profile), "{$id} has no usable generator profile.");
        $alphabet = $profile['alphabet'] ?? null;
        $separator = $profile['token_separator'] ?? null;
        phase2Assert(is_array($alphabet) && is_string($separator), "{$id} generator profile is malformed.");

        $contentGraphemes = self::positiveInteger($specification['content_graphemes'] ?? null, "{$id} content_graphemes");
        $usefulTokens = self::positiveInteger($specification['useful_tokens'] ?? null, "{$id} useful_tokens");
        $distinctTokens = self::positiveInteger($specification['distinct_tokens'] ?? null, "{$id} distinct_tokens");
        phase2Assert($contentGraphemes >= $usefulTokens && $usefulTokens >= $distinctTokens && $distinctTokens >= 2, "{$id} boundary generator facts are impossible.");
        phase2Assert(count($alphabet) >= $distinctTokens, "{$id} generator alphabet is too small.");
        foreach ($alphabet as $symbol) {
            phase2Assert(is_string($symbol) && self::independentUnicodeScalarCount($symbol) === 1, "{$id} generator alphabet contains a non-scalar symbol.");
        }

        $tokens = [str_repeat((string) $alphabet[0], $contentGraphemes - $usefulTokens + 1)];
        for ($index = 1; $index < $usefulTokens; ++$index) {
            $tokens[] = (string) $alphabet[1 + (($index - 1) % ($distinctTokens - 1))];
        }
        $input = implode($separator, $tokens);
        phase2AssertSame($contentGraphemes, array_sum(array_map(self::independentUnicodeScalarCount(...), $tokens)), "{$id} generator content count mismatch.");
        phase2AssertSame($usefulTokens, count($tokens), "{$id} generator useful-token count mismatch.");
        phase2AssertSame($distinctTokens, count(array_unique($tokens)), "{$id} generator distinct-token count mismatch.");

        return $input;
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $specification */
    private static function materializeRepeatedScalar(array $fixture, array $specification): string
    {
        $id = self::fixtureId($fixture);
        $scalar = $specification['scalar'] ?? null;
        phase2Assert(is_string($scalar) && self::independentUnicodeScalarCount($scalar) === 1, "{$id} repeat_scalar must be one Unicode scalar.");
        $count = self::positiveInteger($specification['count'] ?? null, "{$id} scalar count");
        $input = str_repeat($scalar, $count);
        phase2AssertSame($count, self::independentUnicodeScalarCount($input), "{$id} generated scalar count mismatch.");
        phase2AssertSame($specification['sha256'] ?? null, hash('sha256', $input), "{$id} generated scalar hash mismatch.");

        return $input;
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $specification */
    private static function materializeDominantTokenStream(array $fixture, array $specification): string
    {
        $id = self::fixtureId($fixture);
        $dominantToken = $specification['dominant_token'] ?? null;
        $prefix = $specification['other_token_prefix'] ?? null;
        phase2Assert(is_string($dominantToken) && preg_match('/^[a-z]+$/', $dominantToken) === 1, "{$id} dominant token is malformed.");
        phase2Assert(is_string($prefix) && preg_match('/^[a-z]+$/', $prefix) === 1, "{$id} other-token prefix is malformed.");
        $dominantCount = self::positiveInteger($specification['dominant_count'] ?? null, "{$id} dominant count");
        $otherCount = self::positiveInteger($specification['other_unique_token_count'] ?? null, "{$id} other count");
        $tokens = array_fill(0, $dominantCount, $dominantToken);
        for ($index = 1; $index <= $otherCount; ++$index) {
            $tokens[] = $prefix . $index;
        }
        $calculated = $fixture['calculated'] ?? [];
        phase2AssertSame($dominantCount + $otherCount, $calculated['useful_tokens'] ?? null, "{$id} generator useful-token specification mismatch.");
        phase2AssertSame($otherCount + 1, $calculated['distinct_tokens'] ?? null, "{$id} generator distinct-token specification mismatch.");
        phase2AssertSame($dominantCount, $calculated['dominant_token_count'] ?? null, "{$id} generator dominant-token specification mismatch.");
        phase2AssertSame(intdiv($dominantCount * 10000, count($tokens)), $calculated['dominant_token_bps'] ?? null, "{$id} generator dominant-token BPS mismatch.");

        return implode(' ', $tokens);
    }

    /** @param list<string>|mixed $expected @param list<string> $actual */
    private static function assertExactReasonCodes(string $fixtureId, mixed $expected, array $actual): void
    {
        phase2Assert(is_array($expected) && array_is_list($expected), "{$fixtureId} reason_codes are malformed.");
        phase2AssertSame(count($expected), count(array_unique($expected)), "{$fixtureId} declares duplicate reason codes.");
        phase2AssertSame($expected, $actual, "{$fixtureId} reason_codes mismatch.");
    }

    /** @param array<string, mixed> $fixture */
    private static function fixtureId(array $fixture): string
    {
        $id = $fixture['id'] ?? null;
        phase2Assert(is_string($id) && $id !== '', 'Golden fixture has no ID.');
        return $id;
    }

    private static function positiveInteger(mixed $value, string $label): int
    {
        phase2Assert(is_int($value) && $value > 0, "{$label} must be a positive integer.");
        return $value;
    }

    private static function independentUnicodeScalarCount(string $value): int
    {
        $count = preg_match_all('/[\s\S]/u', $value);
        if ($count === false) {
            throw new RuntimeException('Golden generator produced invalid UTF-8.');
        }

        return $count;
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
