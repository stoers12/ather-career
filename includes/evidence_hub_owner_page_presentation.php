<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_owner_presentation.php';
require_once __DIR__ . '/evidence_hub_owner_page_model.php';
require_once __DIR__ . '/evidence_hub_copy.php';

/** @param array<string, mixed> $contract @param array<string, mixed> $model */
function renderEvidenceHubOwnerPage(array $contract, array $model, array $actionTokens = [], string $feedback = '', array $undoFeedback = ['kind' => 'none'], string $locale = 'en', ?array $technologyForm = null): void
{
    $locale = evidenceHubLocale($locale);
    try {
        evidenceHubContractAssertFrozenV1($contract);
    } catch (EvidenceHubContractMappingException) {
        renderEvidenceHubOwnerSafeError($locale);
        return;
    }
    $t = static fn (string $english, array $replacements = []): string => evidenceHubText($english, $locale, $replacements);
    $e = static fn (string $english, array $replacements = []): string => ownerEscapeHtml(evidenceHubText($english, $locale, $replacements));
    $documentation = $contract['metrics']['documentation_coverage'];
    $technology = $contract['metrics']['technology_evidence_map'];
    $progress = $model['progress'];
    $projects = $model['projects'];
    $technologies = $model['technologies'];
    $technologyOverview = $technologies['overview'];
    $activityText = evidenceHubOwnerPageActivityText($progress['first_activity_at'], $progress['latest_activity_at'], $locale);
    $state = $contract['maturity']['state'];
    ?>
<div class="evidence-hub-presentation evidence-hub-page-body" dir="<?php echo evidenceHubDirection($locale); ?>">
    <?php if (($undoFeedback['kind'] ?? 'none') === 'saved' && is_string($undoFeedback['token'] ?? null)): ?>
        <section class="evidence-hub-save-panel" role="status" aria-live="polite"><div><strong><?php echo $e('Evidence saved successfully'); ?></strong></div><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="undo_evidence"><input type="hidden" name="undo_token" value="<?php echo ownerEscapeHtml($undoFeedback['token']); ?>"><button type="submit"><?php echo $e('Undo last evidence update'); ?></button></form></section>
    <?php elseif (($undoFeedback['kind'] ?? 'none') === 'undone'): ?>
        <section class="evidence-hub-save-panel" role="status" aria-live="polite"><strong><?php echo $e('Evidence update undone'); ?></strong><p><?php echo $e('The previous Evidence values were restored safely.'); ?></p></section>
    <?php elseif (($undoFeedback['kind'] ?? 'none') === 'conflict'): ?>
        <p class="evidence-hub-feedback" role="alert"><?php echo $e('This Evidence changed after the saved update and can no longer be undone.'); ?></p>
    <?php elseif (($undoFeedback['kind'] ?? 'none') === 'unavailable'): ?>
        <p class="evidence-hub-feedback" role="alert"><?php echo $e('This Undo action is no longer available.'); ?></p>
    <?php elseif (($undoFeedback['kind'] ?? 'none') === 'saved_without_undo'): ?>
        <p class="evidence-hub-feedback" role="status"><?php echo $e('Project evidence saved.'); ?></p>
    <?php endif; ?>
    <header class="evidence-hub-hero"><div class="evidence-hub-hero-copy"><p class="evidence-hub-kicker"><?php echo evidenceHubOwnerIcon('spark'); ?> <?php echo $e('Evidence Hub'); ?><?php if ($locale === 'ar'): ?> · <?php echo $e('Career insights'); ?><?php endif; ?></p><h1><?php echo $e('Build a portfolio people can trust.'); ?></h1><p><?php echo $e('See the evidence recorded in your projects, identify gaps, and choose a useful next step.'); ?></p></div><aside class="evidence-hub-readiness-panel" aria-label="<?php echo $e('General evidence state'); ?>"><?php echo evidenceHubOwnerIcon(evidenceHubOwnerMaturityIcon($state)); ?><div><span><?php echo $e('Current evidence state'); ?></span><strong><?php echo ownerEscapeHtml(evidenceHubOwnerPageReadinessLabel($state, $locale)); ?></strong><small><?php echo ownerEscapeHtml(evidenceHubOwnerPageReadinessDetail($state, (int) $progress['project_count'], (int) $progress['complete_project_count'], $locale)); ?></small><a href="#next-steps"><?php echo $e('View next steps'); ?></a></div></aside></header>
    <?php if ($feedback !== ''): ?><p class="evidence-hub-feedback" role="status" aria-live="polite" tabindex="-1" id="evidence-hub-action-feedback"><?php echo $e($feedback); ?></p><?php endif; ?>

    <section class="evidence-hub-section evidence-hub-recommendations" id="next-steps" aria-labelledby="evidence-hub-recommendations-title"><div class="evidence-hub-section-heading"><div><p><?php echo $e('Next steps'); ?></p><h2 id="evidence-hub-recommendations-title"><?php echo $e('Recommended next steps'); ?></h2></div><span><?php echo $e('Based on recorded evidence'); ?></span></div>
        <?php if ($state === 'zero'): ?><div class="evidence-hub-zero-path" aria-label="<?php echo $e('Three steps to begin'); ?>"><article><span>1</span><h3><?php echo $e('Add a project'); ?></h3><p><?php echo $e('Record a project you want to explain.'); ?></p><a href="/owner_projects.php?add=1"><?php echo $e('Add project'); ?></a></article><article><span>2</span><h3><?php echo $e('Describe your contribution'); ?></h3><p><?php echo $e('Record the problem, your personal role, and a measurable outcome.'); ?></p><a href="/owner_projects.php"><?php echo $e('Manage projects'); ?></a></article><article><span>3</span><h3><?php echo $e('Review the evidence'); ?></h3><p><?php echo $e('Return here to see completed fields and remaining gaps.'); ?></p><a href="#project-evidence"><?php echo $e('View project evidence'); ?></a></article></div><?php endif; ?>
        <?php if ($contract['recommendations'] === []): ?><div class="evidence-hub-completion-panel"><div class="evidence-hub-completion-ring" role="img" aria-label="<?php echo $e('100% complete'); ?>"><span>100%</span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m5 12 5 5L20 7"/></svg></div><div><h3><?php echo $e('You’re all caught up'); ?></h3><p><?php echo $e('All project Evidence is complete. Review it periodically to keep your portfolio current.'); ?></p><button class="evidence-hub-primary-cta" type="button" data-review-project-evidence><?php echo $e('Review project evidence'); ?></button></div></div><?php else: ?><div class="evidence-hub-recommendation-grid evidence-hub-recommendation-grid--count-<?php echo min(3, count($contract['recommendations'])); ?>">
            <?php foreach (array_slice($contract['recommendations'], 0, 3) as $index => $recommendation): $action = evidenceHubOwnerRecommendationPresentationAction($recommendation['rule_id']); $context = $model['recommendations'][$index] ?? []; $tokens = $actionTokens[$index] ?? null; $href = is_string($context['edit_url'] ?? null) ? $context['edit_url'] : $action['href']; if ($recommendation['rule_id'] === 'complete_project_evidence' && is_string($context['edit_url'] ?? null)) { $action['description'] = 'Add the missing Problem, Personal role, or Measurable outcome for this project.'; $action['label'] = 'Edit evidence'; } ?>
            <article class="evidence-hub-recommendation-card" aria-label="<?php echo $e('Evidence Hub recommendation'); ?>"><div class="evidence-hub-recommendation-copy"><span class="evidence-hub-recommendation-category"><?php echo $e($action['category']); ?></span><h3><?php echo $e($action['title']); ?></h3><p><?php echo $e($action['description']); ?></p><small><?php echo is_string($context['project_title'] ?? null) ? $e('Project: {title}', ['{title}' => $context['project_title']]) : $e($action['context']); ?></small></div><div class="evidence-hub-recommendation-footer"><a class="evidence-hub-primary-cta" href="<?php echo ownerEscapeHtml($href); ?>"><?php echo $e($action['label']); ?> <?php echo evidenceHubOwnerIcon('arrow'); ?></a>
            <?php if ($recommendation['lifecycle_state'] === 'active' && is_array($tokens) && is_string($tokens['snooze'] ?? null) && is_string($tokens['dismiss'] ?? null)): ?><details class="evidence-hub-options"><summary><?php echo $e('More options'); ?></summary><div><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="snooze"><input type="hidden" name="action_token" value="<?php echo ownerEscapeHtml($tokens['snooze']); ?>"><button type="submit"><?php echo $e('Snooze for 14 days'); ?></button></form><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="dismiss"><input type="hidden" name="action_token" value="<?php echo ownerEscapeHtml($tokens['dismiss']); ?>"><button class="evidence-hub-menu-dismiss" type="submit"><?php echo $e('Dismiss'); ?></button></form></div></details><?php endif; ?></div></article>
            <?php endforeach; ?></div><?php endif; ?>
    </section>

    <section class="evidence-hub-section" id="overview" aria-labelledby="evidence-hub-status-title"><div class="evidence-hub-section-heading"><div><p><?php echo $e('Overview'); ?></p><h2 id="evidence-hub-status-title"><?php echo $e('Evidence overview'); ?></h2></div></div><div class="evidence-hub-status" aria-label="<?php echo $e('Portfolio evidence overview'); ?>">
        <article class="evidence-hub-status-card"><h3><?php echo $e('Documentation Coverage'); ?></h3><?php if ($documentation['coverage_bps'] !== null): ?><strong><?php echo (int) $documentation['coverage_bps'] / 100; ?>%</strong><progress aria-label="<?php echo $e('Documentation Coverage'); ?>" value="<?php echo (int) $documentation['coverage_bps']; ?>" max="10000"><?php echo $e('Documentation Coverage'); ?></progress><?php else: ?><strong><?php echo $e('Unavailable'); ?></strong><?php endif; ?><span><?php echo ownerEscapeHtml(evidenceHubOwnerPageMetricState($documentation['status'], $locale)); ?></span><p><?php echo (int) $documentation['project_count'] === 0 ? $e('Add a project and record its three evidence fields to begin coverage.') : ownerEscapeHtml(evidenceHubOwnerPageFieldSummary((int) $documentation['complete_evidence_field_count'], (int) $documentation['expected_evidence_field_count'], $locale)); ?></p></article>
        <article class="evidence-hub-status-card"><h3><?php echo $e('Technology Evidence Map'); ?></h3><strong><?php echo ownerEscapeHtml(evidenceHubCountPhrase($technologyOverview['mapped_technology_count'], 'mapped_technology', $locale)); ?></strong><span><?php echo ownerEscapeHtml(evidenceHubOwnerPageMetricState($technology['status'], $locale)); ?></span><p><?php echo $e('{technologies} across {projects}. Record technologies in projects and review unmapped entries below.', ['{technologies}' => evidenceHubCountPhrase($technologyOverview['unmapped_technology_count'], 'unmapped_technology', $locale), '{projects}' => evidenceHubCountPhrase($technologyOverview['unmapped_project_count'], 'project', $locale)]); ?></p></article>
        <article class="evidence-hub-status-card"><h3><?php echo $e('Portfolio Progress'); ?></h3><strong><?php echo (int) $progress['complete_project_count']; ?> / <?php echo (int) $progress['project_count']; ?></strong><span><?php echo $e($progress['label']); ?></span><p><?php echo $e('Projects with all three evidence fields complete out of eligible projects.'); ?></p></article>
    </div></section>

    <section class="evidence-hub-section evidence-hub-projects" id="project-evidence" aria-labelledby="evidence-hub-project-title"><div class="evidence-hub-section-heading"><div><p><?php echo $e('Project evidence'); ?></p><h2 id="evidence-hub-project-title"><?php echo $e('Project evidence'); ?></h2></div><span><?php echo $e('Newest updates first'); ?></span></div>
        <?php if ($projects === []): ?><div class="evidence-hub-empty"><strong><?php echo $e('No projects yet'); ?></strong><p><?php echo $e('Add a project to begin recording evidence.'); ?></p><a class="evidence-hub-primary-cta" href="/owner_projects.php?add=1"><?php echo $e('Add project'); ?></a></div><?php else: ?>
            <div class="evidence-hub-project-filters" role="group" aria-label="<?php echo $e('Filter projects'); ?>"><button type="button" data-evidence-filter="all" aria-pressed="true"><?php echo $e('All'); ?></button><button type="button" data-evidence-filter="attention" aria-pressed="false"><?php echo $e('Needs attention'); ?></button><button type="button" data-evidence-filter="complete" aria-pressed="false"><?php echo $e('Complete'); ?></button></div>
            <div class="evidence-hub-carousel" data-evidence-carousel data-label-single="<?php echo $e('Project {position} of {total}'); ?>" data-label-range="<?php echo $e('Showing projects {first}–{last} of {total}'); ?>">
                <div class="evidence-hub-project-list" data-evidence-carousel-track dir="ltr">
                    <?php foreach ($projects as $project): ?>
                        <article id="project-<?php echo (int) $project['id']; ?>" class="evidence-hub-project-card" data-evidence-project-status="<?php echo $project['status'] === 'complete' ? 'complete' : 'attention'; ?>" dir="<?php echo evidenceHubDirection($locale); ?>">
                            <details class="evidence-hub-project-disclosure"><summary><span class="evidence-hub-project-title" dir="auto"><?php echo ownerEscapeHtml($project['title']); ?></span><span class="evidence-hub-state evidence-hub-state--<?php echo ownerEscapeHtml($project['status']); ?>"><?php echo ownerEscapeHtml(evidenceHubOwnerPageFieldStatusLabel($project['status'], $locale)); ?></span><span><?php echo $e('{count}/3 complete', ['{count}' => (int) $project['complete_count']]); ?></span><span><?php echo $project['updated_at'] === null ? $e('Updated date unavailable') : $e('Updated {date}', ['{date}' => $project['updated_at']]); ?></span></summary><div class="evidence-hub-project-fields"><?php foreach ($project['fields'] as $field): ?><div class="evidence-hub-field"><div><strong><?php echo $e($field['label']); ?></strong><span class="evidence-hub-state evidence-hub-state--<?php echo ownerEscapeHtml($field['status']); ?>"><?php echo ownerEscapeHtml(evidenceHubOwnerPageFieldStatusLabel($field['status'], $locale)); ?></span></div><?php if ($field['status'] !== 'complete'): ?><p><?php echo $e($field['reason']); ?></p><?php endif; ?></div><?php endforeach; ?></div></details>
                            <details class="evidence-hub-options evidence-hub-project-options" data-project-id="<?php echo (int) $project['id']; ?>"><summary id="project-<?php echo (int) $project['id']; ?>-options-trigger" aria-controls="project-<?php echo (int) $project['id']; ?>-options-panel" aria-expanded="false"><?php echo $e('More options'); ?></summary><div id="project-<?php echo (int) $project['id']; ?>-options-panel"><a href="/owner/projects/<?php echo (int) $project['id']; ?>/evidence"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m4 20 4.5-1 10.3-10.3a2.1 2.1 0 0 0-3-3L5.5 16 4 20Z"/><path d="m14.5 7 3 3"/></svg><?php echo $e('Review & edit evidence'); ?></a><a href="<?php echo ownerEscapeHtml($project['edit_url']); ?>"><?php echo $e('Edit project'); ?></a></div></details>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="evidence-hub-carousel-navigation" data-evidence-carousel-navigation dir="ltr" hidden><button type="button" class="evidence-hub-carousel-arrow" data-evidence-carousel-previous aria-label="<?php echo $e('Previous project'); ?>">&#8592;</button><span class="evidence-hub-carousel-counter" data-evidence-carousel-counter role="status" aria-live="polite"></span><button type="button" class="evidence-hub-carousel-arrow" data-evidence-carousel-next aria-label="<?php echo $e('Next project'); ?>">&#8594;</button></div>
            </div>
            <div class="evidence-hub-empty evidence-hub-filter-empty" data-evidence-filter-empty hidden><strong><?php echo $e('No projects match this filter'); ?></strong><p><?php echo $e('Choose another filter to review project evidence.'); ?></p></div>
        <?php endif; ?>
    </section>

    <section class="evidence-hub-section evidence-hub-technology" id="technologies" aria-labelledby="evidence-hub-technology-title"><div class="evidence-hub-section-heading"><div><p><?php echo $e('Technologies'); ?></p><h2 id="evidence-hub-technology-title"><?php echo $e('Technologies'); ?></h2></div><span><?php echo $e('Projects using each technology'); ?></span></div><?php if ($technologies['mapped'] === [] && $model['technology_review']['unmapped'] === []): ?><div class="evidence-hub-empty"><strong><?php echo $e('No technology evidence yet'); ?></strong><p><?php echo $e('Add technologies to a project to see them mapped here.'); ?></p></div><?php endif; ?>
        <?php foreach ($technologies['mapped'] as $category => $entries): ?><div class="evidence-hub-technology-category"><h3><?php echo $e(ucfirst($category)); ?></h3><div class="evidence-hub-technology-pills"><?php foreach ($entries as $entry): ?><span class="evidence-hub-technology-pill"><bdi><?php echo ownerEscapeHtml($entry['label']); ?></bdi><small><?php echo ownerEscapeHtml(evidenceHubCountPhrase((int) $entry['project_count'], 'project', $locale)); ?></small><?php if ($entry['owner_mapped'] ?? false): ?><small class="evidence-hub-technology-owner-tag"><?php echo $e('Mapped by you'); ?></small><?php endif; ?></span><?php endforeach; ?></div></div><?php endforeach; ?>
        <?php renderEvidenceHubOwnerTechnologyReview($model['technology_review'], $locale, $technologyForm); ?>
    </section>

    <section class="evidence-hub-section evidence-hub-progress" id="progress" aria-labelledby="evidence-hub-progress-title"><div class="evidence-hub-section-heading"><div><p><?php echo $e('Progress'); ?></p><h2 id="evidence-hub-progress-title"><?php echo $e('Portfolio progress'); ?></h2></div></div><article class="evidence-hub-progress-card"><strong><?php echo $e($progress['label']); ?></strong>
        <?php if ($progress['published_project_count'] !== null && $progress['project_count'] > 0): ?>
            <p><?php echo $e('Public projects: {published} of {total}.', ['{published}' => (int) $progress['published_project_count'], '{total}' => (int) $progress['project_count']]); ?></p>
            <?php if ($progress['published_project_count'] > 0 && in_array($progress['portfolio_publication_state'] ?? null, ['unpublished', 'not_configured'], true)): ?>
                <p><?php echo $e('Public projects appear to visitors only after the portfolio is published.'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <p><?php echo $e('{complete} of {total} eligible projects have all three evidence fields complete.', ['{complete}' => (int) $progress['complete_project_count'], '{total}' => (int) $progress['project_count']]); ?></p><?php if ($activityText !== null): ?><p><?php echo $e('Complete-project activity: {range}.', ['{range}' => $activityText]); ?></p><?php endif; ?><p><?php echo $e($progress['history_explanation']); ?></p></article></section>
</div>
    <?php
}

/**
 * Owner technology review: resolve each unmapped label by fixing it in a
 * project, mapping it to a known technology, or proposing it; undo an own
 * mapping; follow proposals. Every control is a plain form, so it works
 * without JavaScript. Owner labels are shown isolated, in their own direction.
 *
 * @param array<string, mixed> $review @param array{ref: string, values: array<string, mixed>, errors: array<string, string>}|null $form
 */
function renderEvidenceHubOwnerTechnologyReview(array $review, string $locale, ?array $form = null): void
{
    $e = static fn (string $english, array $replacements = []): string => ownerEscapeHtml(evidenceHubText($english, $locale, $replacements));
    $csrf = ownerEscapeHtml(getCsrfToken());
    $separator = evidenceHubLocale($locale) === 'ar' ? '، ' : ', ';
    $arrow = evidenceHubLocale($locale) === 'ar' ? '←' : '→';
    $categoryOptions = static function (string $selected) use ($review, $e): string {
        $html = '<option value="">' . $e('Choose a category') . '</option>';
        foreach ($review['categories'] as $category) {
            $html .= '<option value="' . ownerEscapeHtml($category) . '"' . ($category === $selected ? ' selected' : '') . '>' . $e(ucfirst($category)) . '</option>';
        }
        return $html;
    };
    ?>
    <?php if ($review['unmapped'] !== []): ?><div class="evidence-hub-technology-category evidence-hub-technology-unmapped"><h3><?php echo $e('Unmapped technologies'); ?></h3><p><?php echo $e('These entries do not match the technology list. Fix a spelling in the project, map the entry to a known technology, or propose it.'); ?></p>
        <div class="evidence-hub-technology-review">
        <?php foreach ($review['unmapped'] as $index => $item): $id = 'technology-review-' . ($index + 1); $errors = ($form['ref'] ?? null) === $item['ref'] ? array_map(static fn (string $error): string => evidenceHubText($error, $locale), $form['errors']) : []; $values = $errors === [] ? [] : $form['values']; ?>
            <article class="evidence-hub-technology-review-item" id="<?php echo $id; ?>" aria-labelledby="<?php echo $id; ?>-label">
                <h4 id="<?php echo $id; ?>-label"><bdi dir="auto"><?php echo ownerEscapeHtml($item['raw_label']); ?></bdi> <small><?php echo ownerEscapeHtml(evidenceHubCountPhrase(count($item['projects']), 'project', $locale)); ?></small></h4>
                <p class="evidence-hub-technology-review-projects"><?php echo $e('Fix the spelling in a project:'); ?> <?php foreach ($item['projects'] as $position => $project): ?><?php echo $position > 0 ? $separator : ''; ?><a href="<?php echo ownerEscapeHtml($project['edit_url']); ?>" dir="auto"><?php echo ownerEscapeHtml($project['title']); ?></a><?php endforeach; ?></p>
                <form class="evidence-hub-technology-map-form" method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="map_technology"><input type="hidden" name="technology_ref" value="<?php echo ownerEscapeHtml($item['ref']); ?>">
                    <label for="<?php echo $id; ?>-map"><?php echo $e('Map to a known technology'); ?></label>
                    <div><select id="<?php echo $id; ?>-map" name="canonical_id" required><option value=""><?php echo $e('Choose a technology'); ?></option><?php foreach ($review['mapping_options'] as $category => $options): ?><optgroup label="<?php echo $e(ucfirst($category)); ?>"><?php foreach ($options as $option): ?><option value="<?php echo ownerEscapeHtml($option['id']); ?>"><?php echo ownerEscapeHtml($option['label']); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select><button type="submit"><?php echo $e('Map technology'); ?></button></div>
                </form>
                <?php if ($item['has_pending_proposal']): ?><p class="evidence-hub-technology-review-note"><?php echo $e('Your proposal for this technology is pending.'); ?></p><?php else: ?>
                <details class="evidence-hub-technology-propose"<?php echo $errors === [] ? '' : ' open'; ?>><summary><?php echo $e('Not in the list? Propose it'); ?></summary>
                    <form method="POST" action="/owner/evidence-hub" novalidate><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="propose_technology"><input type="hidden" name="technology_ref" value="<?php echo ownerEscapeHtml($item['ref']); ?>">
                        <?php if ($errors !== []): ?><p class="field-error" role="alert"><?php echo $e('Review the highlighted fields'); ?></p><?php endif; ?>
                        <label for="<?php echo $id; ?>-name"><?php echo $e('Technology name'); ?></label><input id="<?php echo $id; ?>-name" name="proposed_label" maxlength="60" dir="auto" value="<?php echo ownerEscapeHtml((string) ($values['proposed_label'] ?? $item['raw_label'])); ?>"<?php echo ownerFieldAccessibilityAttributes($errors, 'proposed_label'); ?>><?php ownerRenderFieldError($errors, 'proposed_label'); ?>
                        <label for="<?php echo $id; ?>-category"><?php echo $e('Category'); ?></label><select id="<?php echo $id; ?>-category" name="category"<?php echo ownerFieldAccessibilityAttributes($errors, 'category'); ?>><?php echo $categoryOptions((string) ($values['category'] ?? '')); ?></select><?php ownerRenderFieldError($errors, 'category'); ?>
                        <label for="<?php echo $id; ?>-note"><?php echo $e('Note (optional)'); ?></label><textarea id="<?php echo $id; ?>-note" name="note" rows="2" maxlength="280" dir="auto"<?php echo ownerFieldAccessibilityAttributes($errors, 'note'); ?>><?php echo ownerEscapeHtml((string) ($values['note'] ?? '')); ?></textarea><?php ownerRenderFieldError($errors, 'note'); ?>
                        <button type="submit"><?php echo $e('Send proposal'); ?></button>
                    </form>
                </details><?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div></div><?php endif; ?>
    <?php if ($review['owner_mapped'] !== []): ?><div class="evidence-hub-technology-category evidence-hub-technology-owner-mapped"><h3><?php echo $e('Technologies you mapped'); ?></h3><ul class="evidence-hub-technology-review-list">
        <?php foreach ($review['owner_mapped'] as $item): ?><li><span><bdi dir="auto"><?php echo ownerEscapeHtml($item['raw_label']); ?></bdi> <?php echo $arrow; ?> <bdi><?php echo ownerEscapeHtml((string) $item['display_name']); ?></bdi></span><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="unmap_technology"><input type="hidden" name="technology_ref" value="<?php echo ownerEscapeHtml($item['ref']); ?>"><button type="submit"><?php echo $e('Remove mapping'); ?></button></form></li><?php endforeach; ?>
    </ul></div><?php endif; ?>
    <?php if ($review['proposals'] !== []): ?><div class="evidence-hub-technology-category evidence-hub-technology-proposals"><h3><?php echo $e('Your technology proposals'); ?></h3><ul class="evidence-hub-technology-review-list">
        <?php foreach ($review['proposals'] as $proposal): ?><li><span><bdi dir="auto"><?php echo ownerEscapeHtml($proposal['proposed_label']); ?></bdi> · <?php echo $e(ucfirst($proposal['category'])); ?> <span class="evidence-hub-state evidence-hub-state--proposal-<?php echo ownerEscapeHtml($proposal['state']); ?>"><?php echo $e(evidenceHubOwnerTechnologyProposalStateLabel($proposal['state'])); ?></span></span><?php if ($proposal['state'] === 'pending'): ?><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="withdraw_technology_proposal"><input type="hidden" name="proposal_id" value="<?php echo (int) $proposal['id']; ?>"><button type="submit"><?php echo $e('Withdraw proposal'); ?></button></form><?php endif; ?></li><?php endforeach; ?>
    </ul></div><?php endif; ?>
    <?php
}

function evidenceHubOwnerTechnologyProposalStateLabel(string $state): string
{
    return match ($state) {'pending' => 'Pending review', 'withdrawn' => 'Withdrawn', 'accepted' => 'Accepted', 'rejected' => 'Not accepted', default => 'Unavailable'};
}

function evidenceHubOwnerPageReadinessLabel(string $state, string $locale = 'en'): string { return evidenceHubText(match ($state) {'zero' => 'Zero', 'partial' => 'Partial', 'ready' => 'Ready', default => 'Unavailable'}, $locale); }
function evidenceHubOwnerPageReadinessDetail(string $state, int $projectCount, int $completeProjectCount, string $locale = 'en'): string
{
    if ($state === 'zero' || $projectCount === 0) {
        return evidenceHubText('No project evidence is recorded yet.', $locale);
    }
    return evidenceHubText(match ($state) {
        'partial' => 'No project has all three evidence fields complete yet. Continue recording the missing fields below.',
        'ready' => $completeProjectCount === $projectCount
            ? 'Every eligible project has all three evidence fields complete. Keep them current.'
            : 'At least one project has all three evidence fields complete. Review the remaining projects below.',
        default => 'Evidence state is unavailable.',
    }, $locale);
}
function evidenceHubOwnerPageMetricState(string $state, string $locale = 'en'): string { return evidenceHubText(match ($state) {'ready' => 'Complete', 'needs_attention' => 'Needs attention', default => 'Unavailable'}, $locale); }
function evidenceHubOwnerPageFieldStatusLabel(string $status, string $locale = 'en'): string { return evidenceHubText(match ($status) {'complete' => 'Complete', 'needs_attention' => 'Needs attention', 'invalid' => 'Invalid', 'missing' => 'Missing', default => 'Unavailable'}, $locale); }
function evidenceHubOwnerPageFieldSummary(int $complete, int $expected, string $locale = 'en'): string
{
    return evidenceHubLocale($locale) === 'ar'
        ? evidenceHubText('{complete} of {expected} evidence fields completed', $locale, ['{complete}' => $complete, '{expected}' => $expected])
        : evidenceHubOwnerEvidenceFieldSummary($complete, $expected);
}
function evidenceHubOwnerPageActivityText(?string $first, ?string $latest, string $locale = 'en'): ?string
{
    if ($first === null || $latest === null) {
        return null;
    }
    return $first === $latest ? $first : evidenceHubText('{first} to {latest}', $locale, ['{first}' => $first, '{latest}' => $latest]);
}
