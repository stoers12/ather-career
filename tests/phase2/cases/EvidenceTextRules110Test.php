<?php

declare(strict_types=1);

final class EvidenceTextRules110Test
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_text_evaluator.php';
        require_once PHASE2_REPOSITORY_ROOT . '/tests/phase2/support/evidence-text-110-fixtures.php';
        $report = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-text-110-calibration.json'), true, 512, JSON_THROW_ON_ERROR);
        $hashes = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-text-100-hashes.json'), true, 512, JSON_THROW_ON_ERROR);
        $expected = array_column($report['rows'], null, 'id');
        foreach (evidenceText110CalibrationFixtures() as $fixture) {
            $id = $fixture['id'];
            $old = evaluateEvidenceText($fixture['field'], $fixture['value'], '1.0.0');
            $live = evaluateEvidenceText($fixture['field'], $fixture['value'], '1.1.0');
            phase2AssertSame($hashes[$id], hash('sha256', json_encode($old, JSON_THROW_ON_ERROR)), "{$id}: frozen 1.0.0 bytes changed.");
            phase2AssertSame([$old['storage_validity'], $old['stored_value'], $old['analytical_text']], [$live['storage_validity'], $live['stored_value'], $live['analytical_text']], "{$id}: storage or normalization changed.");
            phase2AssertSame('1.1.0', $live['rule_version'], "{$id}: live rule version is wrong.");
            if ($fixture['group'] === 'storage only') continue;
            phase2AssertSame($expected[$id]['proposed'], $live['evidence_status'], "{$id}: calibrated classification changed.");
            phase2AssertSame($expected[$id]['reasons'], $live['reason_codes'], "{$id}: calibrated reasons changed.");
            foreach ($expected[$id]['counts'] as $key => $count) phase2AssertSame($count, $live[$key], "{$id}: calibrated {$key} changed.");
            if ($expected[$id]['group'] === 'synthetic padding (intended)') {
                phase2Assert(in_array('CHARACTER_RUN_SUSPECTED', $live['reason_codes'], true), "{$id}: intended padding catch was lost.");
            }
        }
        phase2AssertSame(0, $report['false_positives'], 'Calibration has natural false positives.');
        phase2AssertSame(0, $report['containment_mismatches'], 'Calibration has cross-field false positives or misses.');
        self::boundaries();
        self::ratioBoundaries();
        try {
            evaluateEvidenceText('personal_role', null, '2.0.0');
            throw new RuntimeException('An unsupported text rule version was accepted.');
        } catch (InvalidArgumentException) {
            // Version selection must fail closed, even for unavailable text.
        }
    }

    private static function boundaries(): void
    {
        foreach (EVIDENCE_TEXT_FIELD_POLICIES as $field => $policy) {
            foreach (['abcdefghijk', 'ابتجحخدذرزس'] as $alphabet) {
                foreach ([-1,0,1] as $offset) {
                    $text = self::runFreeText($alphabet, $policy['minimum_content_graphemes'] + $offset, $policy['minimum_useful_tokens']);
                    $result = evaluateEvidenceText($field, $text, '1.1.0');
                    phase2AssertSame($policy['minimum_content_graphemes'] + $offset, $result['content_graphemes'], "{$field}: run-free grapheme count is wrong.");
                    phase2AssertSame($offset < 0 ? 'needs_attention' : 'complete', $result['evidence_status'], "{$field}: run-free grapheme boundary changed.");
                    $text = self::runFreeText($alphabet, $policy['minimum_content_graphemes'] + 12, $policy['minimum_useful_tokens'] + $offset);
                    $result = evaluateEvidenceText($field, $text, '1.1.0');
                    phase2AssertSame($policy['minimum_useful_tokens'] + $offset, $result['useful_token_count'], "{$field}: run-free token count is wrong.");
                    phase2AssertSame($offset < 0 ? 'needs_attention' : 'complete', $result['evidence_status'], "{$field}: run-free token boundary changed.");
                    $distinct = $policy['minimum_distinct_tokens'] + $offset;
                    $tokens = [];
                    for ($i = 0; $i < $policy['minimum_useful_tokens']; ++$i) $tokens[] = self::runFreeText($alphabet, 12, 1, $i % $distinct);
                    $result = evaluateEvidenceText($field, implode(' ', $tokens), '1.1.0');
                    phase2AssertSame($distinct, $result['distinct_token_count'], "{$field}: run-free distinct count is wrong.");
                    phase2AssertSame($offset < 0 ? 'needs_attention' : 'complete', $result['evidence_status'], "{$field}: run-free distinct boundary changed.");
                }
            }
        }
    }

    public static function runFreeText(string $alphabet, int $graphemes, int $tokens, int $start = 0): string
    {
        $letters = preg_split('//u', $alphabet, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        for ($i = 0; $i < $tokens; ++$i) {
            $length = intdiv($graphemes, $tokens) + ($i < $graphemes % $tokens ? 1 : 0);
            $token = '';
            for ($j = 0; $j < $length; ++$j) $token .= $letters[($start + $i + $j) % count($letters)];
            $result[] = $token;
        }
        return implode(' ', $result);
    }

    private static function ratioBoundaries(): void
    {
        foreach ([[11,4,false], [12,4,true], [15,6,false], [20,8,false]] as [$total,$distinct,$fires]) {
            $tokens = [];
            for ($i = 0; $i < $total; ++$i) $tokens[] = self::runFreeText('abcdefghijk', 12, 1, $i % $distinct);
            $result = evaluateEvidenceText('personal_role', implode(' ', $tokens), '1.1.0');
            phase2AssertSame($fires, in_array('LOW_DISTINCT_TOKEN_RATIO', $result['reason_codes'], true), 'The distinct ratio token minimum or strict inequality changed.');
        }
        $result = evaluateEvidenceText('personal_role', implode(' ', array_merge(array_fill(0,9,'repeat'), ['first','second','third'])), '1.1.0');
        phase2Assert(in_array('LOW_DISTINCT_TOKEN_RATIO', $result['reason_codes'], true) && in_array('REPETITION_SUSPECTED', $result['reason_codes'], true), 'Repetition and distinct ratio must be independent.');
        foreach (['10000','2020-2024','coffee','keep'] as $value) {
            phase2AssertSame(evaluateEvidenceText('personal_role',$value,'1.0.0')['content_graphemes'], evaluateEvidenceText('personal_role',$value,'1.1.0')['content_graphemes'], 'Digits or two-letter runs were discounted.');
        }
        foreach (['زاد الأداء كثيــــــــراً جدا','زاد الأداء كثيراً جدا !!!!!!!!!','زاد الأداء كثيراً جدا 😀😀😀😀'] as $value) {
            $old = evaluateEvidenceText('measurable_outcome',$value,'1.0.0');
            $new = evaluateEvidenceText('measurable_outcome',$value,'1.1.0');
            phase2AssertSame([$old['content_graphemes'],$old['useful_token_count'],$old['reason_codes']], [$new['content_graphemes'],$new['useful_token_count'],$new['reason_codes']], 'Already harmless padding acquired a new rule.');
        }
    }
}
