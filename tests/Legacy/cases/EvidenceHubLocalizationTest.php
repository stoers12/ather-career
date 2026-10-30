<?php

declare(strict_types=1);

final class EvidenceHubLocalizationTest
{
    private const ARABIC = '/[\x{0600}-\x{06FF}]/u';

    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_copy.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_layout.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_model.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_presentation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_project_evidence_presentation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendation_action_tokens.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_technology_review.php';
        self::copyIsComplete();
        self::arabicCountAgreement();
        self::localeIsExplicitAndFailsSafeToEnglish();
        self::pageFollowsLocale();
        self::shellFollowsLocale();
        self::editorFollowsLocale();
    }

    /** Every literal routed through the Evidence Hub copy layer has an Arabic translation. */
    private static function copyIsComplete(): void
    {
        $arabic = evidenceHubArabicCopy();
        $sources = ['includes/evidence_hub_owner_page_presentation.php', 'includes/owner_project_evidence_presentation.php', 'includes/owner_layout.php', 'includes/evidence_hub_owner_presentation.php', 'handlers/owner_project_evidence.php'];
        $literals = [];
        foreach ($sources as $source) {
            $contents = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $source);
            preg_match_all("/(?:\\\$(?:e|t|hubText)\\(|evidenceHubText\\()'((?:[^'\\\\]|\\\\.)*)'/", $contents, $matches);
            foreach ($matches[1] as $literal) {
                $literals[] = stripcslashes($literal);
            }
        }
        foreach (['add_first_project', 'complete_project_evidence', 'review_unmapped_technology', 'complete_portfolio_publication', 'add_skill_evidence', 'unknown_rule'] as $rule) {
            $action = evidenceHubOwnerRecommendationPresentationAction($rule);
            array_push($literals, $action['title'], $action['description'], $action['label'], $action['category'], $action['context']);
        }
        foreach (['FIELD_NOT_AVAILABLE', 'EMPTY_NORMALIZED_VALUE', 'PLACEHOLDER_CONFIRMED', 'GRAPHEME_THRESHOLD_NOT_MET', 'USEFUL_TOKEN_THRESHOLD_NOT_MET', 'DISTINCT_TOKEN_THRESHOLD_NOT_MET', 'REPETITION_SUSPECTED', 'CHARACTER_RUN_SUSPECTED', 'LOW_DISTINCT_TOKEN_RATIO', 'DUPLICATED_ACROSS_FIELDS', 'NON_SCALAR_INPUT', 'INVALID_UTF8', 'MAXIMUM_LENGTH_EXCEEDED', 'PROHIBITED_CONTROL_CHARACTER', 'ZERO_WIDTH_CHARACTER', 'PROHIBITED_DIRECTIONALITY_CHARACTER'] as $reason) {
            $literals[] = evidenceHubOwnerPageFieldReason(['reason_codes' => [$reason]], 'Problem');
        }
        $day = 86400;
        foreach ([[0, 0, []], [1, 0, []], [1, 1, [100]], [3, 2, [100, 200]], [3, 3, [100, 100 + 15 * $day, 100 + 31 * $day]]] as [$projects, $complete, $activity]) {
            $progress = evidenceHubOwnerPageProgress($projects, $complete, $activity);
            array_push($literals, $progress['label'], $progress['history_explanation']);
        }
        foreach (['database', 'framework', 'language', 'library', 'platform', 'runtime', 'stylesheet', 'technique', 'tool'] as $category) {
            $literals[] = ucfirst($category);
        }
        array_push($literals, ...EVIDENCE_HUB_ACTION_FEEDBACK_MESSAGES);
        foreach (['pending', 'withdrawn', 'accepted', 'rejected'] as $state) {
            $literals[] = evidenceHubOwnerTechnologyProposalStateLabel($state);
        }
        // Every technology proposal error a rejected request can carry.
        $taxonomy = loadEvidenceHubOwnerTaxonomy();
        foreach ([[null, null, ['note']], [str_repeat('x', 61), 'tool', str_repeat('y', 281)], ['React', 'tool', "bad\x07note"]] as [$label, $category, $note]) {
            array_push($literals, ...array_values(evidenceHubValidateTechnologyProposal($label, $category, $note, $taxonomy)['errors']));
        }
        $literals[] = 'A proposal for this technology is already pending.';
        foreach (evidenceWritingGuidanceFields() as $field) {
            array_push($literals, $field['label'], $field['guidance'], $field['example'], $field['starter']);
        }
        foreach (evidenceWritingHintRules() as $rule) {
            $literals[] = $rule['message'];
        }
        $missing = array_values(array_unique(array_filter($literals, static fn (string $literal): bool => $literal !== '' && !isset($arabic[$literal]))));
        phase2AssertSame([], $missing, 'Evidence Hub interface copy is missing Arabic translations.');
        foreach ($arabic as $english => $translation) {
            phase2Assert(preg_match(self::ARABIC, $translation) === 1 || preg_match('/^[0-9{}\/ %a-z]+$/', $translation) === 1, "Arabic copy for '{$english}' is not Arabic.");
            preg_match_all('/\{[a-z]+\}/', $english, $source);
            preg_match_all('/\{[a-z]+\}/', $translation, $target);
            $sourcePlaceholders = $source[0];
            $targetPlaceholders = $target[0];
            sort($sourcePlaceholders);
            sort($targetPlaceholders);
            phase2AssertSame($sourcePlaceholders, $targetPlaceholders, "Arabic copy for '{$english}' changes its placeholders.");
        }
    }

    private static function arabicCountAgreement(): void
    {
        $expected = [
            0 => '0 مشروع', 1 => 'مشروع واحد', 2 => 'مشروعان', 3 => '3 مشاريع', 10 => '10 مشاريع',
            11 => '11 مشروعًا', 99 => '99 مشروعًا', 100 => '100 مشروع', 102 => '102 مشروع', 103 => '103 مشاريع', 111 => '111 مشروعًا',
        ];
        foreach ($expected as $count => $phrase) {
            phase2AssertSame($phrase, evidenceHubCountPhrase($count, 'project', 'ar'), "Arabic agreement for {$count} projects is wrong.");
        }
        foreach ([0 => '0 projects', 1 => '1 project', 2 => '2 projects'] as $count => $phrase) {
            phase2AssertSame($phrase, evidenceHubCountPhrase($count, 'project', 'en'), "English agreement for {$count} projects changed.");
        }
        phase2AssertSame('تقنيتان غير مصنّفتين', evidenceHubCountPhrase(2, 'unmapped_technology', 'ar'), 'Arabic dual unmapped technology agreement is wrong.');
        foreach ([1, 2, 7] as $count) {
            phase2AssertSame(evidenceHubOwnerCountPhrase($count, 'mapped technology', 'mapped technologies'), evidenceHubCountPhrase($count, 'mapped_technology', 'en'), 'English counted phrase differs from the existing presentation grammar.');
        }
    }

    /** A future visitor-facing view passes 'en'; nothing else may produce Arabic. */
    private static function localeIsExplicitAndFailsSafeToEnglish(): void
    {
        foreach (['en', 'fr', '', 'AR', 'ar-SA', null, ['ar'], 1] as $candidate) {
            phase2AssertSame('en', evidenceHubLocale($candidate), 'An unsupported locale must render English.');
        }
        phase2AssertSame('ar', evidenceHubLocale('ar'), 'Arabic Owner locale is not recognized.');
        $saved = [$_GET, $_COOKIE];
        try {
            $_GET = ['lang' => 'ar'];
            $_COOKIE = ['ather_owner_locale' => 'ar'];
            phase2AssertSame('Recommended next steps', evidenceHubText('Recommended next steps', 'en'), 'Owner workspace preference leaked into an explicit English render.');
        } finally {
            [$_GET, $_COOKIE] = $saved;
        }
    }

    private static function pageFollowsLocale(): void
    {
        [$contract, $model] = self::partialPage();
        $default = self::capture(static fn () => renderEvidenceHubOwnerPage($contract, $model, [], 'Recommendation dismissed.'));
        $english = self::capture(static fn () => renderEvidenceHubOwnerPage($contract, $model, [], 'Recommendation dismissed.', ['kind' => 'none'], 'en'));
        $unsupported = self::capture(static fn () => renderEvidenceHubOwnerPage($contract, $model, [], 'Recommendation dismissed.', ['kind' => 'none'], 'fr'));
        $arabic = self::capture(static fn () => renderEvidenceHubOwnerPage($contract, $model, [], 'Recommendation dismissed.', ['kind' => 'none'], 'ar'));
        phase2AssertSame($default, $english, 'Explicit English differs from the default Evidence Hub page.');
        phase2AssertSame($english, $unsupported, 'An unsupported locale changed the Evidence Hub page.');
        phase2Assert(preg_match(self::ARABIC, $english) !== 1, 'English Evidence Hub page contains Arabic interface text.');
        phase2Assert(str_contains($english, 'dir="ltr"') && !str_contains($english, 'dir="rtl"'), 'English Evidence Hub page is not left-to-right.');
        foreach (['dir="rtl"', 'مركز الأدلة', 'الخطوات التالية المقترحة', 'تم تجاهل التوصية.', 'تقنية غير مصنّفة واحدة', 'صحّح الإملاء في مشروع:', 'اربطها بتقنية معروفة', 'ربطتها أنت', 'قيد المراجعة', 'أُطر العمل', 'مشروعان', 'مساهمتي', 'أضف وصفًا محددًا لهذا الدليل.', 'data-label-single="المشروع {position} من {total}"'] as $required) {
            phase2Assert(str_contains($arabic, $required) || str_contains($arabic, htmlspecialchars($required, ENT_QUOTES, 'UTF-8')), "Arabic Evidence Hub page is missing {$required}.");
        }
        foreach (['Recommended next steps', 'Needs attention', 'Project evidence', 'Add a specific account of this evidence.', 'Recommendation dismissed.'] as $english) {
            phase2Assert(!str_contains($arabic, '>' . $english . '<'), "Arabic Evidence Hub page still shows English interface text: {$english}.");
        }
        foreach (['Owner Synthetic Project', 'React', '<bdi dir="auto">Raect</bdi>', 'Owner Proposed Tool'] as $authored) {
            phase2Assert(str_contains($arabic, $authored), "Owner-authored content was altered by localization: {$authored}.");
        }
        phase2Assert(str_contains($arabic, 'data-evidence-carousel-track dir="ltr"'), 'Carousel geometry must stay left-to-right for its scroll arithmetic.');
    }

    private static function shellFollowsLocale(): void
    {
        $saved = [$_GET, $_COOKIE, $_SERVER['REQUEST_URI'] ?? null];
        try {
            $_GET = [];
            $_COOKIE = [];
            $_SERVER['REQUEST_URI'] = '/owner/evidence-hub';
            $english = self::capture(static function (): void { ownerLayoutStart('Evidence Hub', 'evidence_hub', true, ['locale' => 'en']); ownerLayoutEnd(); });
            $arabic = self::capture(static function (): void { ownerLayoutStart('Evidence Hub', 'evidence_hub', true, ['locale' => 'ar']); ownerLayoutEnd(); });
        } finally {
            [$_GET, $_COOKIE] = $saved;
            $_SERVER['REQUEST_URI'] = $saved[2];
        }
        phase2Assert(str_contains($english, '<html lang="en" dir="ltr">') && str_contains($english, '<title>Evidence Hub — My Portfolio</title>'), 'English Evidence Hub shell changed.');
        phase2Assert(str_contains($english, 'href="/owner/evidence-hub?lang=ar" lang="ar" hreflang="ar"'), 'English shell lacks the Arabic language control.');
        $withoutLanguageControl = preg_replace('/<a class="evidence-hub-language".*?<\/a>/s', '', $english);
        phase2Assert(is_string($withoutLanguageControl) && preg_match(self::ARABIC, preg_replace('/<form class="sidebar-logout-form" method="POST" action="\/owner_switch_account\.php".*?<\/form>/s', '', $withoutLanguageControl)) !== 1, 'English Evidence Hub shell shows Arabic outside the language control and existing account chooser.');
        foreach (['<html lang="ar" dir="rtl">', '<title>مركز الأدلة — بورتفوليو</title>', 'href="/owner/evidence-hub?lang=en" lang="en" hreflang="en"', '>English</a>', 'مساحة العمل', 'العودة إلى لوحة التحكم', 'data-label-expand="توسيع القائمة"', 'تبديل الحساب'] as $required) {
            phase2Assert(str_contains($arabic, $required), "Arabic Evidence Hub shell is missing {$required}.");
        }
        phase2Assert(!str_contains($arabic, 'owner-toolbar') && str_contains($arabic, 'evidence_hub.css?'), 'Arabic locale must keep the Evidence Hub shell and styles.');
    }

    private static function editorFollowsLocale(): void
    {
        $project = ['id' => 7, 'title' => 'Owner Synthetic Project', 'values' => []];
        $values = ['problem' => 'An owner wrote this in English.', 'personal_role' => '', 'measurable_outcome' => ''];
        $errors = ['personal_role' => 'Please enter text for this field.'];
        $arabic = self::capture(static fn () => renderOwnerProjectEvidenceForm($project, $values, $errors, null, 'ar'));
        $english = self::capture(static fn () => renderOwnerProjectEvidenceForm($project, $values, $errors));
        foreach (['dir="rtl"', 'تعديل أدلة المشروع', 'النتيجة', 'حفظ الأدلة', 'يُرجى إدخال نص في هذا الحقل.', '<h2>راجع الحقول المحددة</h2>', 'An owner wrote this in English.'] as $required) {
            phase2Assert(str_contains($arabic, $required), "Arabic evidence editor is missing {$required}.");
        }
        phase2Assert(!str_contains($arabic, 'Review the highlighted fields'), 'Arabic evidence editor shows the English error summary heading.');
        phase2Assert(str_contains($english, '<h2>Review the highlighted fields</h2>'), 'English evidence editor error summary changed.');
        phase2Assert(preg_match(self::ARABIC, $english) !== 1 && str_contains($english, 'Please enter text for this field.'), 'English evidence editor changed.');
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private static function partialPage(): array
    {
        $names = ['problem_statement', 'personal_role', 'measurable_outcome'];
        $evaluated = [];
        $recommendationProjects = [];
        $pageProjects = [];
        foreach ([1, 3] as $index => $completeFields) {
            $evaluations = [];
            $states = [];
            $pageFields = [];
            foreach ($names as $fieldIndex => $field) {
                $status = $fieldIndex < $completeFields ? 'complete' : 'unavailable';
                $evaluations[$field] = ['evidence_field' => $field, 'evidence_status' => $status, 'reason_codes' => $status === 'complete' ? [] : ['FIELD_NOT_AVAILABLE']];
                $states[$field] = $status;
                $pageFields[] = ['key' => $field, 'label' => ['Problem', 'Personal role', 'Measurable outcome'][$fieldIndex], 'status' => $status === 'complete' ? 'complete' : 'missing', 'reason' => $status === 'complete' ? '' : 'Add a specific account of this evidence.'];
            }
            $evaluated[] = ['project_ref' => $index + 1, 'recorded_at_epoch_seconds' => 1767225600, 'technology_storage_state' => 'valid', 'technology_storage_reason_codes' => [], 'technologies' => [], 'field_evaluations' => $evaluations, 'expected_evidence_fields' => 3, 'complete_evidence_fields' => $completeFields, 'project_has_complete_evidence' => $completeFields === 3];
            $recommendationProjects[] = ['target_identity' => 'localization-project-' . $index, 'field_completeness_states' => $states, 'reason_codes' => $completeFields === 3 ? [] : ['FIELD_NOT_AVAILABLE']];
            $pageProjects[] = ['id' => $index + 1, 'title' => 'Owner Synthetic Project', 'status' => $completeFields === 3 ? 'complete' : 'needs_attention', 'complete_count' => $completeFields, 'updated_at' => '2026-01-01', 'fields' => $pageFields, 'edit_url' => '/owner_projects.php?edit=' . ($index + 1)];
        }
        $core = buildEvidenceHubOwnerCoreFromEvaluatedProjects($evaluated, 'published');
        $recommendations = buildEvidenceHubRecommendations([
            'tenant_scope_ref' => 'localization-tenant',
            'portfolio_target_identity' => 'localization-portfolio',
            'projects' => $recommendationProjects,
            'technology_mappings' => [],
            'portfolio_publication' => ['portfolio_published' => true, 'publication_prerequisites_met' => false],
        ], [], 1767225600, 'localization-synthetic-hmac');
        $technologies = [
            'mapped' => ['framework' => [['label' => 'React', 'key' => 'react', 'project_count' => 2, 'owner_mapped' => true]]],
            'overview' => ['mapped_technology_count' => 1, 'unmapped_technology_count' => 1, 'unmapped_project_count' => 1],
        ];
        $taxonomy = loadEvidenceHubOwnerTaxonomy();
        $review = evidenceHubOwnerPageTechnologyReview(
            evidenceHubTechnologyReviewItems([['id' => 1, 'title' => 'Owner Synthetic Project', 'technology_storage' => ['storage_state' => 'valid', 'labels' => ['Raect']]]], $taxonomy, []),
            [['id' => 5, 'label_digest' => str_repeat('a', 64), 'proposed_label' => 'Owner Proposed Tool', 'category' => 'tool', 'note' => null, 'state' => 'pending']],
            $taxonomy,
            static fn (string $digest): string => 'localization-ref-' . substr($digest, 0, 8),
        );
        $model = ['projects' => $pageProjects, 'technologies' => $technologies, 'technology_review' => $review, 'progress' => evidenceHubOwnerPageProgress(2, 1, [1767225600]), 'recommendations' => array_fill(0, count($recommendations), [])];

        return [mapEvidenceHubContractV2($core, $recommendations), $model];
    }

    private static function capture(callable $render): string
    {
        ob_start();
        try {
            $render();
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }
}
