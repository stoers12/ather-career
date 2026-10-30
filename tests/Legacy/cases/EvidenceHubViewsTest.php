<?php

declare(strict_types=1);

/** B-07 (FE-07 B1/B2, docs/contracts/evidence-hub-views.md): evidence view modes and the complete skill-gap list. */
final class EvidenceHubViewsTest
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_model.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/project_evidence_view.php';
        self::viewModes();
        self::handler();
        self::skillGaps();
    }

    private static function viewModes(): void
    {
        phase2AssertSame(['mode' => 'read', 'part' => null], projectEvidenceViewFromQuery([]), 'The canonical GET must read.');
        phase2AssertSame(['mode' => 'read', 'part' => null], projectEvidenceViewFromQuery(['mode' => 'edit', 'part' => 'problem']), 'Only mode=write writes.');
        phase2AssertSame(['mode' => 'read', 'part' => null], projectEvidenceViewFromQuery(['mode' => ['write']]), 'A structured mode must read.');
        phase2AssertSame(['mode' => 'write', 'part' => null], projectEvidenceViewFromQuery(['mode' => 'write']), 'mode=write without a part.');
        phase2AssertSame(['mode' => 'write', 'part' => 'measurable_outcome'], projectEvidenceViewFromQuery(['mode' => 'write', 'part' => 'measurable_outcome']), 'A known part opens.');
        phase2AssertSame(['mode' => 'write', 'part' => null], projectEvidenceViewFromQuery(['mode' => 'write', 'part' => 'sources']), 'Sources is never a write part.');
        phase2AssertSame(['mode' => 'write', 'part' => null], projectEvidenceViewFromQuery(['mode' => 'write', 'part' => ['problem']]), 'A structured part is ignored.');
        phase2AssertSame(['mode' => 'write', 'part' => 'personal_role'], projectEvidenceViewAfterTextError(['measurable_outcome' => 'x', 'personal_role' => 'y']), 'A text error opens the first invalid part in display order.');
        phase2AssertSame(['mode' => 'write', 'part' => null], projectEvidenceViewAfterTextError([]), 'A text error without a field still writes.');
        phase2AssertSame(['read' => '/owner/projects/7/evidence', 'write' => '/owner/projects/7/evidence?mode=write', 'sources' => '/owner/projects/7/evidence#sources'], projectEvidenceViewUrls(7), 'View URLs changed.');
    }

    private static function handler(): void
    {
        $handler = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/handlers/owner_project_evidence.php');
        $order = [
            '$view = projectEvidenceViewFromQuery($_GET);',
            "if (\$action === 'add_source') {",
            "\$view = ['mode' => 'read', 'part' => null];",
            "httpRedirect('/owner/evidence-hub#project-' . \$projectId, 303);",
            '$view = projectEvidenceViewAfterTextError($fieldErrors);',
            'renderOwnerProjectEvidencePage($project, $values, $fieldErrors, $sources, $sourceValues, $sourceErrors, $view, ownerLocale());',
        ];
        $position = -1;
        foreach ($order as $step) {
            $next = strpos($handler, $step, $position + 1);
            phase2Assert($next !== false, "Evidence view step missing or out of order: {$step}");
            $position = $next;
        }
        // Source PRG lands on the read view; the text-save PRG is unchanged.
        phase2AssertSame(2, substr_count($handler, "httpRedirect('/owner/projects/' . \$projectId . '/evidence#sources', 303);"), 'Source actions must return to the read view.');
    }

    private static function skillGaps(): void
    {
        $database = (new ReflectionMethod(EvidenceHubContractV7Test::class, 'database'))->invoke(null);
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        phase2AssertSame([], listAuthorizedEvidenceHubSkillGaps($database, $context), 'No skills means no gaps.');

        $insert = $database->prepare("INSERT INTO skills (portfolio_id, skill_name, created_at) VALUES (?, ?, '2026-01-01')");
        foreach ([[10, 'Vue'], [10, 'React'], [10, 'react.js'], [10, 'html'], [10, 'Zzz unknown skill'], [20, 'Python']] as [$portfolio, $skill]) {
            $insert->execute([$portfolio, $skill]);
        }
        $gaps = listAuthorizedEvidenceHubSkillGaps($database, $context);
        phase2AssertSame(
            [['canonical_id' => 'tech.react', 'display_name' => 'React'], ['canonical_id' => 'tech.vue', 'display_name' => 'Vue.js']],
            $gaps,
            'Gaps must be canonical, deduplicated, sorted by name, exclude proven (HTML) and unknown skills, and stay in this Portfolio.',
        );

        // The list is independent of the three visible recommendations and of dispositions.
        $state = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, 1000, 'views-synthetic-hmac');
        $model = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, 'views-synthetic-hmac');
        phase2AssertSame($gaps, $model['skill_gaps'], 'The page model must carry the complete gap list.');
        foreach ($model['projects'] as $project) {
            phase2AssertSame(projectEvidenceViewUrls($project['id'])['write'], $project['evidence_write_url'], 'Each record needs its write URL.');
            phase2AssertSame(projectEvidenceViewUrls($project['id'])['read'], $project['evidence_read_url'], 'Each record needs its read URL.');
        }
        $encoded = json_encode($model['skill_gaps'], JSON_THROW_ON_ERROR);
        phase2Assert(!str_contains($encoded, 'react.js') && !str_contains($encoded, 'Zzz') && !str_contains($encoded, 'target'), 'Raw skill text or identities leaked into the gap list.');
    }
}
