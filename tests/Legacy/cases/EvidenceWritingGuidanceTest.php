<?php

declare(strict_types=1);

final class EvidenceWritingGuidanceTest
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_text_evaluator.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_copy.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_writing_guidance.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_project_evidence_presentation.php';

        if (!evidenceTextUnicodeRuntimeIsAvailable()) {
            throw new RuntimeException('Evidence writing guidance tests require ext-intl Normalizer, grapheme support, and mbstring.');
        }

        self::fieldsMatchTheEditorContract();
        self::examplesAreCompleteEvidence();
        self::startersNeverPassAsEvidence();
        self::hintRules();
        self::hintPatternsArePortable();
        self::frozenTextRulesStayIndependent();
        self::editorRendersWritingAids();
    }

    private static function fieldsMatchTheEditorContract(): void
    {
        $fields = evidenceWritingGuidanceFields();
        phase2AssertSame(projectEvidenceLogicalToStorageFields(), array_map(static fn (array $field): string => $field['storage_field'], $fields), 'Writing guidance fields must match the editor logical-to-storage mapping in order.');
        phase2AssertSame(['problem', 'personal-role', 'measurable-outcome'], array_values(array_column($fields, 'anchor')), 'Editor fragment anchors changed.');
    }

    /** Following our own example must never leave the Owner with an incomplete field. */
    private static function examplesAreCompleteEvidence(): void
    {
        foreach (evidenceWritingGuidanceFields() as $key => $field) {
            foreach (['en', 'ar'] as $locale) {
                $example = evidenceHubText($field['example'], $locale);
                $evaluation = evaluateEvidenceText($field['storage_field'], $example);
                phase2AssertSame('complete', $evaluation['evidence_status'], "The {$locale} {$key} example is not complete evidence under the frozen rules.");
                phase2AssertSame([], evidenceWritingActiveHints($key, $example, $locale), "The {$locale} {$key} example raises a writing hint.");
            }
        }
    }

    /** An unedited starter is scaffolding, not the Owner's evidence. */
    private static function startersNeverPassAsEvidence(): void
    {
        foreach (evidenceWritingGuidanceFields() as $key => $field) {
            foreach (['en', 'ar'] as $locale) {
                $starter = evidenceHubText($field['starter'], $locale);
                $evaluation = evaluateEvidenceText($field['storage_field'], $starter);
                phase2AssertSame('valid', $evaluation['storage_validity'], "The {$locale} {$key} starter is not storable text.");
                phase2Assert($evaluation['evidence_status'] !== 'complete', "The {$locale} {$key} starter passes as complete evidence.");
                phase2Assert(in_array('unfilled_slot', evidenceWritingActiveHints($key, $starter, $locale), true), "The {$locale} {$key} starter does not raise the unfilled-slot hint.");
                phase2Assert(preg_match_all('/\[[^\[\]]+\]/u', $starter) === substr_count(evidenceWritingStarterSlotPattern($key, $locale), '|') + 1, "The {$locale} {$key} starter has a slot outside letters, marks and spaces.");
            }
        }
    }

    private static function hintRules(): void
    {
        foreach (['Cut response time by 40%', 'Cut time from 6 to 1 hour', 'وفّرنا ٣ ساعات أسبوعيًا', 'خفّضنا الأخطاء إلى ۵ فقط'] as $measured) {
            phase2AssertSame([], evidenceWritingActiveHints('measurable_outcome', $measured), "A measured outcome raised a hint: {$measured}");
        }
        foreach (['Improved performance a lot', 'تحسّن الأداء كثيرًا'] as $unmeasured) {
            phase2AssertSame(['outcome_without_number'], evidenceWritingActiveHints('measurable_outcome', $unmeasured), "An outcome without digits did not raise the number hint: {$unmeasured}");
        }
        phase2AssertSame([], evidenceWritingActiveHints('problem', 'Reports were slow and nobody trusted them'), 'The number hint must apply to the measurable outcome only.');
        phase2AssertSame(['unfilled_slot'], evidenceWritingActiveHints('personal_role', 'I built the [part] for the team'), 'A remaining slot did not raise the unfilled-slot hint.');
        phase2AssertSame(['unfilled_slot'], evidenceWritingActiveHints('personal_role', 'دوري: بنيت [أي جزء] كاملًا', 'ar'), 'A remaining Arabic slot did not raise the unfilled-slot hint.');
        phase2AssertSame([], evidenceWritingActiveHints('personal_role', 'I indexed list[0], wrote [] literals and a [Draft] label in Python'), "Brackets the Owner wrote are not starter slots.");
        foreach ([null, '', '   ', ['text'], 42, "\xC3\x28"] as $empty) {
            phase2AssertSame([], evidenceWritingActiveHints('measurable_outcome', $empty), 'Empty, non-text, or invalid UTF-8 values must raise no hint.');
        }
    }

    /** The editor script compiles the same patterns with new RegExp(pattern, 'u'). */
    private static function hintPatternsArePortable(): void
    {
        foreach (array_keys(evidenceWritingGuidanceFields()) as $field) {
            foreach (['en', 'ar'] as $locale) {
                foreach (evidenceWritingHintsForField($field, $locale) as $id => $rule) {
                    phase2Assert($rule['pattern'] !== '' && preg_match('/' . $rule['pattern'] . '/u', '') !== false, "Hint pattern {$id} does not compile in PHP.");
                    phase2Assert(preg_match('/^(?:\\\\[pP]\{[A-Za-z]+\}|\\\\[\[\]]|[\p{L}\p{M} |])+$/u', $rule['pattern']) === 1, "Hint pattern {$id} uses syntax outside the PHP/JavaScript common subset.");
                    phase2Assert(in_array($rule['when'], ['present', 'absent'], true), "Hint {$id} has an unknown display condition.");
                }
            }
        }
        $script = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/public/assets/js/evidence_hub.js');
        phase2Assert(str_contains($script, "new RegExp(pattern, 'u')") && str_contains($script, 'data-hint-pattern'), 'The editor script must compile the server-rendered hint patterns.');
    }

    /** Writing aids are presentation; the frozen v1 analytics never depend on them. */
    private static function frozenTextRulesStayIndependent(): void
    {
        phase2AssertSame('1.0.0', EVIDENCE_TEXT_RULE_VERSION, 'Writing guidance must not change the frozen text rule version.');
        foreach (glob(PHASE2_REPOSITORY_ROOT . '/includes/{evidence_text_*,evidence_hub_aggregation,evidence_hub_contract_mapper,evidence_hub_recommendations}.php', GLOB_BRACE) ?: [] as $analytics) {
            phase2Assert(!str_contains((string) file_get_contents($analytics), 'evidence_writing_guidance'), basename($analytics) . ' must not depend on presentation-only writing guidance.');
        }
    }

    private static function editorRendersWritingAids(): void
    {
        $project = ['id' => 7, 'title' => 'Owner Synthetic Project', 'values' => []];
        $values = ['problem' => '', 'personal_role' => 'I built the [part] alone', 'measurable_outcome' => 'Improved reporting speed'];
        $english = self::capture(static fn () => renderOwnerProjectEvidenceForm($project, $values));
        $arabic = self::capture(static fn () => renderOwnerProjectEvidenceForm($project, $values, [], null, 'ar'));

        phase2Assert(preg_match_all('/<button type="button" data-evidence-insert-starter data-starter="[^"]+" hidden>/', $english) === 3, 'Every field needs a starter control that carries its text and stays hidden until the editor script enhances it.');
        phase2Assert(str_contains($english, '<bdi>[Metric]: [before] → [after]</bdi>'), 'The starter must be readable without JavaScript.');
        phase2Assert(str_contains($english, 'Example: Cut weekly reporting time from 6 hours to 1 hour.'), 'The measured example is not rendered.');
        phase2Assert(str_contains($english, 'aria-describedby="evidence-help-measurable-outcome evidence-hints-measurable-outcome"'), 'The textarea is not described by its guidance and hints.');
        phase2Assert(preg_match('/data-evidence-writing-hint="outcome_without_number"[^>]*data-hint-when="absent">/', $english) === 1, 'The server did not show the number hint for an unmeasured outcome.');
        phase2Assert(preg_match('/id="personal-role".*?data-evidence-writing-hint="unfilled_slot"[^>]*data-hint-when="present">.*?<\/section>/s', $english) === 1, 'The server did not show the unfilled-slot hint for a remaining slot.');
        phase2Assert(preg_match('/id="problem".*?data-evidence-writing-hint="unfilled_slot"[^>]*hidden>.*?<\/section>/s', $english) === 1, 'An empty field must render its hints hidden.');
        phase2AssertSame(1, substr_count($english, 'data-evidence-writing-hint="outcome_without_number"'), 'The number hint must render for the measurable outcome only.');

        foreach (['بداية مقترحة', 'استخدم هذه البداية', '[المقياس]: [قبل] ← [بعد]', 'لا يوجد رقم بعد.', 'مثال: خفّضت وقت إعداد التقرير الأسبوعي'] as $required) {
            phase2Assert(str_contains($arabic, $required), "Arabic evidence editor is missing {$required}.");
        }
        phase2Assert(preg_match('/id="personal-role".*?data-evidence-writing-hint="unfilled_slot"[^>]*hidden>.*?<\/section>/s', $arabic) === 1, 'Arabic slots are resolved per locale, so an English slot must not raise the Arabic hint.');
        phase2Assert(preg_match('/[\x{0600}-\x{06FF}]/u', $english) !== 1, 'English evidence editor contains Arabic interface text.');
    }

    private static function capture(callable $render): string
    {
        ob_start();
        try {
            $render();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
