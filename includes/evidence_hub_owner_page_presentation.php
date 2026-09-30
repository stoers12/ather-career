<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_owner_presentation.php';
require_once __DIR__ . '/evidence_hub_owner_page_model.php';

/** @param array<string, mixed> $contract @param array<string, mixed> $model */
function renderEvidenceHubOwnerPage(array $contract, array $model, array $actionTokens = [], string $feedback = '', array $undoFeedback = ['kind' => 'none']): void
{
    try {
        evidenceHubContractAssertFrozenV1($contract);
    } catch (EvidenceHubContractMappingException) {
        renderEvidenceHubOwnerSafeError();
        return;
    }
    $documentation = $contract['metrics']['documentation_coverage'];
    $technology = $contract['metrics']['technology_evidence_map'];
    $progress = $model['progress'];
    $projects = $model['projects'];
    $technologies = $model['technologies'];
    $technologyOverview = $technologies['overview'];
    $activityText = evidenceHubOwnerPageActivityText($progress['first_activity_at'], $progress['latest_activity_at']);
    $state = $contract['maturity']['state'];
    ?>
<div class="evidence-hub-presentation evidence-hub-page-body" dir="ltr">
    <?php if (($undoFeedback['kind'] ?? 'none') === 'saved' && is_string($undoFeedback['token'] ?? null)): ?>
        <section class="evidence-hub-save-panel" role="status" aria-live="polite"><div><strong>Evidence saved successfully</strong></div><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="undo_evidence"><input type="hidden" name="undo_token" value="<?php echo ownerEscapeHtml($undoFeedback['token']); ?>"><button type="submit">Undo last evidence update</button></form></section>
    <?php elseif (($undoFeedback['kind'] ?? 'none') === 'undone'): ?>
        <section class="evidence-hub-save-panel" role="status" aria-live="polite"><strong>Evidence update undone</strong><p>The previous Evidence values were restored safely.</p></section>
    <?php elseif (($undoFeedback['kind'] ?? 'none') === 'conflict'): ?>
        <p class="evidence-hub-feedback" role="alert">This Evidence changed after the saved update and can no longer be undone.</p>
    <?php elseif (($undoFeedback['kind'] ?? 'none') === 'unavailable'): ?>
        <p class="evidence-hub-feedback" role="alert">This Undo action is no longer available.</p>
    <?php elseif (($undoFeedback['kind'] ?? 'none') === 'saved_without_undo'): ?>
        <p class="evidence-hub-feedback" role="status">Project evidence saved.</p>
    <?php endif; ?>
    <header class="evidence-hub-hero"><div class="evidence-hub-hero-copy"><p class="evidence-hub-kicker"><?php echo evidenceHubOwnerIcon('spark'); ?> Evidence Hub</p><h1>Build a portfolio people can trust.</h1><p>See the evidence recorded in your projects, identify gaps, and choose a useful next step.</p></div><aside class="evidence-hub-readiness-panel" aria-label="General evidence state"><?php echo evidenceHubOwnerIcon(evidenceHubOwnerMaturityIcon($state)); ?><div><span>Current evidence state</span><strong><?php echo ownerEscapeHtml(evidenceHubOwnerPageReadinessLabel($state)); ?></strong><small><?php echo ownerEscapeHtml(evidenceHubOwnerPageReadinessDetail($state, (int) $progress['project_count'], (int) $progress['complete_project_count'])); ?></small><a href="#next-steps">View next steps</a></div></aside></header>
    <?php if ($feedback !== ''): ?><p class="evidence-hub-feedback" role="status" aria-live="polite" tabindex="-1" id="evidence-hub-action-feedback"><?php echo ownerEscapeHtml($feedback); ?></p><?php endif; ?>

    <section class="evidence-hub-section evidence-hub-recommendations" id="next-steps" aria-labelledby="evidence-hub-recommendations-title"><div class="evidence-hub-section-heading"><div><p>Next steps</p><h2 id="evidence-hub-recommendations-title">Recommended next steps</h2></div><span>Based on recorded evidence</span></div>
        <?php if ($state === 'zero'): ?><div class="evidence-hub-zero-path" aria-label="Three steps to begin"><article><span>1</span><h3>Add a project</h3><p>Record a project you want to explain.</p><a href="/owner_projects.php?add=1">Add project</a></article><article><span>2</span><h3>Describe your contribution</h3><p>Record the problem, your personal role, and a measurable outcome.</p><a href="/owner_projects.php">Manage projects</a></article><article><span>3</span><h3>Review the evidence</h3><p>Return here to see completed fields and remaining gaps.</p><a href="#project-evidence">View project evidence</a></article></div><?php endif; ?>
        <?php if ($contract['recommendations'] === []): ?><div class="evidence-hub-completion-panel"><div class="evidence-hub-completion-ring" role="img" aria-label="100% complete"><span>100%</span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m5 12 5 5L20 7"/></svg></div><div><h3>You’re all caught up</h3><p>All project Evidence is complete. Review it periodically to keep your portfolio current.</p><button class="evidence-hub-primary-cta" type="button" data-review-project-evidence>Review project evidence</button></div></div><?php else: ?><div class="evidence-hub-recommendation-grid evidence-hub-recommendation-grid--count-<?php echo min(3, count($contract['recommendations'])); ?>">
            <?php foreach (array_slice($contract['recommendations'], 0, 3) as $index => $recommendation): $action = evidenceHubOwnerRecommendationPresentationAction($recommendation['rule_id']); $context = $model['recommendations'][$index] ?? []; $tokens = $actionTokens[$index] ?? null; $href = is_string($context['edit_url'] ?? null) ? $context['edit_url'] : $action['href']; if ($recommendation['rule_id'] === 'complete_project_evidence' && is_string($context['edit_url'] ?? null)) { $action['description'] = 'Add the missing Problem, Personal role, or Measurable outcome for this project.'; $action['label'] = 'Edit evidence'; } ?>
            <article class="evidence-hub-recommendation-card" aria-label="Evidence Hub recommendation"><div class="evidence-hub-recommendation-copy"><span class="evidence-hub-recommendation-category"><?php echo ownerEscapeHtml($action['category']); ?></span><h3><?php echo ownerEscapeHtml($action['title']); ?></h3><p><?php echo ownerEscapeHtml($action['description']); ?></p><small><?php echo ownerEscapeHtml(is_string($context['project_title'] ?? null) ? 'Project: ' . $context['project_title'] : $action['context']); ?></small></div><div class="evidence-hub-recommendation-footer"><a class="evidence-hub-primary-cta" href="<?php echo ownerEscapeHtml($href); ?>"><?php echo ownerEscapeHtml($action['label']); ?> <?php echo evidenceHubOwnerIcon('arrow'); ?></a>
            <?php if ($recommendation['lifecycle_state'] === 'active' && is_array($tokens) && is_string($tokens['snooze'] ?? null) && is_string($tokens['dismiss'] ?? null)): ?><details class="evidence-hub-options"><summary>More options</summary><div><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="snooze"><input type="hidden" name="action_token" value="<?php echo ownerEscapeHtml($tokens['snooze']); ?>"><button type="submit">Snooze for 14 days</button></form><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="dismiss"><input type="hidden" name="action_token" value="<?php echo ownerEscapeHtml($tokens['dismiss']); ?>"><button class="evidence-hub-menu-dismiss" type="submit">Dismiss</button></form></div></details><?php endif; ?></div></article>
            <?php endforeach; ?></div><?php endif; ?>
    </section>

    <section class="evidence-hub-section" id="overview" aria-labelledby="evidence-hub-status-title"><div class="evidence-hub-section-heading"><div><p>Overview</p><h2 id="evidence-hub-status-title">Evidence overview</h2></div></div><div class="evidence-hub-status" aria-label="Portfolio evidence overview">
        <article class="evidence-hub-status-card"><h3>Documentation Coverage</h3><?php if ($documentation['coverage_bps'] !== null): ?><strong><?php echo (int) $documentation['coverage_bps'] / 100; ?>%</strong><progress aria-label="Documentation Coverage" value="<?php echo (int) $documentation['coverage_bps']; ?>" max="10000">Documentation coverage</progress><?php else: ?><strong>Unavailable</strong><?php endif; ?><span><?php echo ownerEscapeHtml(evidenceHubOwnerPageMetricState($documentation['status'])); ?></span><p><?php echo (int) $documentation['project_count'] === 0 ? 'Add a project and record its three evidence fields to begin coverage.' : ownerEscapeHtml(evidenceHubOwnerEvidenceFieldSummary((int) $documentation['complete_evidence_field_count'], (int) $documentation['expected_evidence_field_count'])); ?></p></article>
        <article class="evidence-hub-status-card"><h3>Technology Evidence Map</h3><strong><?php echo ownerEscapeHtml(evidenceHubOwnerCountPhrase($technologyOverview['mapped_technology_count'], 'mapped technology', 'mapped technologies')); ?></strong><span><?php echo ownerEscapeHtml(evidenceHubOwnerPageMetricState($technology['status'])); ?></span><p><?php echo ownerEscapeHtml(evidenceHubOwnerCountPhrase($technologyOverview['unmapped_technology_count'], 'unmapped technology', 'unmapped technologies')); ?> across <?php echo ownerEscapeHtml(evidenceHubOwnerCountPhrase($technologyOverview['unmapped_project_count'], 'project', 'projects')); ?>. Record technologies in projects and review unmapped entries below.</p></article>
        <article class="evidence-hub-status-card"><h3>Portfolio Progress</h3><strong><?php echo (int) $progress['complete_project_count']; ?> / <?php echo (int) $progress['project_count']; ?></strong><span><?php echo ownerEscapeHtml($progress['label']); ?></span><p>Projects with all three evidence fields complete out of eligible projects.</p></article>
    </div></section>

    <section class="evidence-hub-section evidence-hub-projects" id="project-evidence" aria-labelledby="evidence-hub-project-title"><div class="evidence-hub-section-heading"><div><p>Project evidence</p><h2 id="evidence-hub-project-title">Project evidence</h2></div><span>Newest updates first</span></div>
        <?php if ($projects === []): ?><div class="evidence-hub-empty"><strong>No projects yet</strong><p>Add a project to begin recording evidence.</p><a class="evidence-hub-primary-cta" href="/owner_projects.php?add=1">Add project</a></div><?php else: ?>
            <div class="evidence-hub-project-filters" role="group" aria-label="Filter projects"><button type="button" data-evidence-filter="all" aria-pressed="true">All</button><button type="button" data-evidence-filter="attention" aria-pressed="false">Needs attention</button><button type="button" data-evidence-filter="complete" aria-pressed="false">Complete</button></div>
            <div class="evidence-hub-carousel" data-evidence-carousel>
                <div class="evidence-hub-project-list" data-evidence-carousel-track>
                    <?php foreach ($projects as $project): ?>
                        <article id="project-<?php echo (int) $project['id']; ?>" class="evidence-hub-project-card" data-evidence-project-status="<?php echo $project['status'] === 'complete' ? 'complete' : 'attention'; ?>">
                            <details class="evidence-hub-project-disclosure"><summary><span class="evidence-hub-project-title" dir="auto"><?php echo ownerEscapeHtml($project['title']); ?></span><span class="evidence-hub-state evidence-hub-state--<?php echo ownerEscapeHtml($project['status']); ?>"><?php echo ownerEscapeHtml(evidenceHubOwnerPageFieldStatusLabel($project['status'])); ?></span><span><?php echo (int) $project['complete_count']; ?>/3 complete</span><span><?php echo $project['updated_at'] === null ? 'Updated date unavailable' : 'Updated ' . ownerEscapeHtml($project['updated_at']); ?></span></summary><div class="evidence-hub-project-fields"><?php foreach ($project['fields'] as $field): ?><div class="evidence-hub-field"><div><strong><?php echo ownerEscapeHtml($field['label']); ?></strong><span class="evidence-hub-state evidence-hub-state--<?php echo ownerEscapeHtml($field['status']); ?>"><?php echo ownerEscapeHtml(evidenceHubOwnerPageFieldStatusLabel($field['status'])); ?></span></div><?php if ($field['status'] !== 'complete'): ?><p><?php echo ownerEscapeHtml($field['reason']); ?></p><?php endif; ?></div><?php endforeach; ?></div></details>
                            <details class="evidence-hub-options evidence-hub-project-options" data-project-id="<?php echo (int) $project['id']; ?>"><summary id="project-<?php echo (int) $project['id']; ?>-options-trigger" aria-controls="project-<?php echo (int) $project['id']; ?>-options-panel" aria-expanded="false">More options</summary><div id="project-<?php echo (int) $project['id']; ?>-options-panel"><a href="/owner/projects/<?php echo (int) $project['id']; ?>/evidence"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m4 20 4.5-1 10.3-10.3a2.1 2.1 0 0 0-3-3L5.5 16 4 20Z"/><path d="m14.5 7 3 3"/></svg>Review &amp; edit evidence</a><a href="<?php echo ownerEscapeHtml($project['edit_url']); ?>">Edit project</a></div></details>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="evidence-hub-carousel-navigation" data-evidence-carousel-navigation hidden><button type="button" class="evidence-hub-carousel-arrow" data-evidence-carousel-previous aria-label="Previous project">&#8592;</button><span class="evidence-hub-carousel-counter" data-evidence-carousel-counter role="status" aria-live="polite"></span><button type="button" class="evidence-hub-carousel-arrow" data-evidence-carousel-next aria-label="Next project">&#8594;</button></div>
            </div>
            <div class="evidence-hub-empty evidence-hub-filter-empty" data-evidence-filter-empty hidden><strong>No projects match this filter</strong><p>Choose another filter to review project evidence.</p></div>
        <?php endif; ?>
    </section>

    <section class="evidence-hub-section evidence-hub-technology" id="technologies" aria-labelledby="evidence-hub-technology-title"><div class="evidence-hub-section-heading"><div><p>Technologies</p><h2 id="evidence-hub-technology-title">Technologies</h2></div><span>Projects using each technology</span></div><?php if ($technologies['mapped'] === [] && $technologies['unmapped'] === []): ?><div class="evidence-hub-empty"><strong>No technology evidence yet</strong><p>Add technologies to a project to see them mapped here.</p></div><?php endif; ?>
        <?php foreach ($technologies['mapped'] as $category => $entries): ?><div class="evidence-hub-technology-category"><h3><?php echo ownerEscapeHtml(ucfirst($category)); ?></h3><div class="evidence-hub-technology-pills"><?php foreach ($entries as $entry): ?><span class="evidence-hub-technology-pill"><bdi><?php echo ownerEscapeHtml($entry['label']); ?></bdi><small><?php echo (int) $entry['project_count']; ?> <?php echo $entry['project_count'] === 1 ? 'project' : 'projects'; ?></small></span><?php endforeach; ?></div></div><?php endforeach; ?>
        <?php if ($technologies['unmapped'] !== []): ?><div class="evidence-hub-technology-category evidence-hub-technology-unmapped"><h3>Unmapped technologies</h3><p>These entries do not match the current taxonomy. Review their project records.</p><div class="evidence-hub-technology-pills"><?php foreach ($technologies['unmapped'] as $entry): ?><span class="evidence-hub-technology-pill"><span><?php echo ownerEscapeHtml($entry['label']); ?></span><small><?php echo (int) $entry['project_count']; ?> <?php echo $entry['project_count'] === 1 ? 'project' : 'projects'; ?></small></span><?php endforeach; ?></div></div><?php endif; ?>
    </section>

    <section class="evidence-hub-section evidence-hub-progress" id="progress" aria-labelledby="evidence-hub-progress-title"><div class="evidence-hub-section-heading"><div><p>Progress</p><h2 id="evidence-hub-progress-title">Portfolio progress</h2></div></div><article class="evidence-hub-progress-card"><strong><?php echo ownerEscapeHtml($progress['label']); ?></strong><p><?php echo (int) $progress['complete_project_count']; ?> of <?php echo (int) $progress['project_count']; ?> eligible projects have all three evidence fields complete.</p><?php if ($activityText !== null): ?><p>Complete-project activity: <?php echo ownerEscapeHtml($activityText); ?>.</p><?php endif; ?><p><?php echo ownerEscapeHtml($progress['history_explanation']); ?></p></article></section>
</div>
    <?php
}

function evidenceHubOwnerPageReadinessLabel(string $state): string { return match ($state) {'zero' => 'Zero', 'partial' => 'Partial', 'ready' => 'Ready', default => 'Unavailable'}; }
function evidenceHubOwnerPageReadinessDetail(string $state, int $projectCount, int $completeProjectCount): string
{
    if ($state === 'zero' || $projectCount === 0) {
        return 'No project evidence is recorded yet.';
    }
    return match ($state) {
        'partial' => 'No project has all three evidence fields complete yet. Continue recording the missing fields below.',
        'ready' => $completeProjectCount === $projectCount
            ? 'Every eligible project has all three evidence fields complete. Keep them current.'
            : 'At least one project has all three evidence fields complete. Review the remaining projects below.',
        default => 'Evidence state is unavailable.',
    };
}
function evidenceHubOwnerPageMetricState(string $state): string { return match ($state) {'ready' => 'Complete', 'needs_attention' => 'Needs attention', default => 'Unavailable'}; }
function evidenceHubOwnerPageFieldStatusLabel(string $status): string { return match ($status) {'complete' => 'Complete', 'needs_attention' => 'Needs attention', 'invalid' => 'Invalid', 'missing' => 'Missing', default => 'Unavailable'}; }
function evidenceHubOwnerPageActivityText(?string $first, ?string $latest): ?string
{
    if ($first === null || $latest === null) {
        return null;
    }
    return $first === $latest ? $first : $first . ' to ' . $latest;
}
