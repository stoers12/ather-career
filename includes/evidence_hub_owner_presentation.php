<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_contract_mapper.php';
require_once __DIR__ . '/owner_layout.php';

/** @param array<string, mixed> $contract */
function renderEvidenceHubOwnerPresentation(array $contract, array $actionTokens = [], string $feedback = ''): void
{
    try {
        evidenceHubContractAssertFrozenV1($contract);
    } catch (EvidenceHubContractMappingException) {
        renderEvidenceHubOwnerSafeError();
        return;
    }
    $documentation = $contract['metrics']['documentation_coverage'];
    $technology = $contract['metrics']['technology_evidence_map'];
    $progress = $contract['metrics']['portfolio_progress'];
    $recommendationCount = count($contract['recommendations']);
    $technologyCount = count($technology['mappings']);
    ?>
<div class="evidence-hub-presentation evidence-hub-dashboard" dir="ltr">
    <header class="evidence-hub-hero">
        <div class="evidence-hub-hero-copy">
            <p class="evidence-hub-kicker"><?php echo evidenceHubOwnerIcon('spark'); ?> Evidence intelligence</p>
            <h1>Build a portfolio people can trust.</h1>
            <p>See what strengthens your work, what is missing, and which improvement matters next.</p>
        </div>
        <aside class="evidence-hub-readiness-panel" aria-label="Portfolio readiness"><?php echo evidenceHubOwnerIcon(evidenceHubOwnerMaturityIcon($contract['maturity']['state'])); ?><div><span>Portfolio readiness</span><strong><?php echo ownerEscapeHtml(evidenceHubOwnerMaturityLabel($contract['maturity']['state'])); ?></strong><small><?php echo ownerEscapeHtml(evidenceHubOwnerReadinessDetail($documentation['status'], $recommendationCount)); ?></small></div></aside>
    </header>
    <?php if ($feedback !== ''): ?><p class="evidence-hub-feedback" role="status" aria-live="polite" tabindex="-1" id="evidence-hub-action-feedback"><?php echo ownerEscapeHtml($feedback); ?></p><?php endif; ?>
    <section class="evidence-hub-section" aria-labelledby="evidence-hub-status-title">
        <div class="evidence-hub-section-heading"><div><h2 id="evidence-hub-status-title">Evidence overview</h2></div><span>Based on your current portfolio</span></div>
        <div class="evidence-hub-status" aria-label="Portfolio evidence overview">
            <article class="evidence-hub-status-card evidence-hub-status-card--maturity"><span class="evidence-hub-status-icon"><?php echo evidenceHubOwnerIcon(evidenceHubOwnerMaturityIcon($contract['maturity']['state'])); ?></span><div><span>Portfolio readiness</span><strong><?php echo ownerEscapeHtml(evidenceHubOwnerMaturityLabel($contract['maturity']['state'])); ?></strong><small><?php echo ownerEscapeHtml(evidenceHubOwnerStatusLabel($documentation['status'])); ?></small></div></article>
            <article class="evidence-hub-status-card evidence-hub-status-card--documentation"><span class="evidence-hub-status-icon"><?php echo evidenceHubOwnerIcon('document-check'); ?></span><div><span>Documentation quality</span><strong data-bidi-number dir="ltr"><?php echo $documentation['coverage_bps'] === null ? '—' : (int) $documentation['coverage_bps'] / 100; ?><?php echo $documentation['coverage_bps'] === null ? '' : '%'; ?></strong><small><?php echo evidenceHubOwnerEvidenceFieldSummary((int) $documentation['complete_evidence_field_count'], (int) $documentation['expected_evidence_field_count']); ?></small></div></article>
            <article class="evidence-hub-status-card evidence-hub-status-card--technology"><span class="evidence-hub-status-icon"><?php echo evidenceHubOwnerIcon('nodes'); ?></span><div><span>Technology evidence</span><strong data-bidi-number dir="ltr"><?php echo (int) $technology['mapped_occurrence_count']; ?></strong><small><?php echo evidenceHubOwnerCountPhrase((int) $technology['unmapped_occurrence_count'], 'technology needs review', 'technologies need review'); ?></small></div></article>
            <article class="evidence-hub-status-card evidence-hub-status-card--progress"><span class="evidence-hub-status-icon"><?php echo evidenceHubOwnerIcon('target'); ?></span><div><span>Portfolio progress</span><strong data-bidi-number dir="ltr"><?php echo (int) $progress['projects_with_complete_evidence']; ?> / <?php echo (int) $progress['project_count']; ?></strong><small><?php echo evidenceHubOwnerCountPhrase((int) $progress['projects_with_complete_evidence'], 'project with complete evidence', 'projects with complete evidence'); ?></small></div></article>
        </div>
    </section>
    <section class="evidence-hub-section evidence-hub-recommendations" aria-labelledby="evidence-hub-recommendations-title">
        <div class="evidence-hub-section-heading"><div><p>Next steps</p><h2 id="evidence-hub-recommendations-title">Recommended next steps</h2></div><span>Prioritized by impact on portfolio credibility.</span></div>
        <?php if ($contract['recommendations'] === []): ?><div class="evidence-hub-empty evidence-hub-empty--complete"><span class="evidence-hub-empty-icon"><?php echo evidenceHubOwnerIcon('check'); ?></span><strong>All recommendations reviewed</strong><span>Your evidence is in good shape. Keep your projects current as they evolve.</span></div>
        <?php else: ?><div class="evidence-hub-recommendation-grid">
            <?php foreach ($contract['recommendations'] as $index => $recommendation): ?><?php $action = evidenceHubOwnerRecommendationPresentationAction($recommendation['rule_id']); $tokens = $actionTokens[$index] ?? null; ?>
                <article class="evidence-hub-recommendation-card evidence-hub-recommendation-card--<?php echo ownerEscapeHtml($action['tone']); ?>" aria-label="Evidence Hub recommendation"><span class="evidence-hub-recommendation-accent" aria-hidden="true"></span><span class="evidence-hub-recommendation-icon"><?php echo evidenceHubOwnerIcon($action['icon']); ?></span><div class="evidence-hub-recommendation-copy"><div class="evidence-hub-recommendation-metadata"><span class="evidence-hub-recommendation-category"><?php echo ownerEscapeHtml($action['category']); ?></span><span class="evidence-hub-recommendation-context"><?php echo evidenceHubOwnerIcon($action['context_icon']); ?> <?php echo ownerEscapeHtml($action['context']); ?></span></div><h3><?php echo ownerEscapeHtml($action['title']); ?></h3><p><?php echo ownerEscapeHtml($action['description']); ?></p></div><div class="evidence-hub-recommendation-footer"><a class="evidence-hub-cta evidence-hub-primary-cta" href="<?php echo ownerEscapeHtml($action['href']); ?>"><span><?php echo ownerEscapeHtml($action['label']); ?></span><?php echo evidenceHubOwnerIcon('arrow'); ?></a>
                    <?php if ($recommendation['lifecycle_state'] === 'active' && is_array($tokens) && isset($tokens['snooze'], $tokens['dismiss']) && is_string($tokens['snooze']) && is_string($tokens['dismiss'])): ?><div class="evidence-hub-recommendation-actions" aria-label="Recommendation actions"><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="snooze"><input type="hidden" name="action_token" value="<?php echo ownerEscapeHtml($tokens['snooze']); ?>"><button class="evidence-hub-snooze" type="submit"><?php echo evidenceHubOwnerIcon('clock'); ?><span>Snooze for 14 days</span></button></form><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="dismiss"><input type="hidden" name="action_token" value="<?php echo ownerEscapeHtml($tokens['dismiss']); ?>"><button class="evidence-hub-dismiss" type="submit"><?php echo evidenceHubOwnerIcon('dismiss'); ?><span>Dismiss</span></button></form></div><?php endif; ?></div>
                </article>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </section>
    <section class="evidence-hub-section evidence-hub-documentation" aria-labelledby="evidence-hub-documentation-title">
        <div class="evidence-hub-section-heading"><div><p>Documentation</p><h2 id="evidence-hub-documentation-title">Documentation quality</h2></div><span>How complete your record is</span></div>
        <?php if ($documentation['project_count'] === 0): ?>
            <div class="evidence-hub-empty"><span class="evidence-hub-empty-icon"><?php echo evidenceHubOwnerIcon('folder-kanban'); ?></span><strong>No projects yet</strong><span>Add a project to begin building portfolio evidence.</span><a class="evidence-hub-empty-link" href="/owner_projects.php?add=1">Add project<?php echo evidenceHubOwnerIcon('arrow'); ?></a></div>
        <?php else: ?>
            <article class="evidence-hub-coverage-card"><div class="evidence-hub-coverage-kpi"><span class="evidence-hub-coverage-icon"><?php echo evidenceHubOwnerIcon('document-check'); ?></span><div><strong data-bidi-number dir="ltr"><?php echo $documentation['coverage_bps'] === null ? '—' : (int) $documentation['coverage_bps'] / 100; ?><?php echo $documentation['coverage_bps'] === null ? '' : '%'; ?></strong><span>Documentation coverage</span></div></div><div class="evidence-hub-coverage-detail"><div class="evidence-hub-progress-track"><progress value="<?php echo $documentation['coverage_bps'] === null ? 0 : (int) $documentation['coverage_bps']; ?>" max="10000">Documentation coverage</progress></div><p><strong><?php echo evidenceHubOwnerEvidenceFieldSummary((int) $documentation['complete_evidence_field_count'], (int) $documentation['expected_evidence_field_count']); ?></strong><span>Complete project evidence from Project Management.</span></p></div></article>
        <?php endif; ?>
    </section>
    <section class="evidence-hub-section evidence-hub-technology" aria-labelledby="evidence-hub-technology-title">
        <div class="evidence-hub-section-heading"><div><p>Technology</p><h2 id="evidence-hub-technology-title">Technology evidence</h2></div><span>How technology supports your projects</span></div>
        <?php if ($technology['mappings'] === []): ?>
            <div class="evidence-hub-empty"><span class="evidence-hub-empty-icon"><?php echo evidenceHubOwnerIcon('nodes'); ?></span><strong>No technology evidence yet</strong><span>Technology evidence appears when your projects are recorded.</span></div>
        <?php else: ?><div class="evidence-hub-technology-grid evidence-hub-technology-grid--count-<?php echo $technologyCount; ?>">
            <?php foreach ($technology['mappings'] as $mapping): ?><article class="evidence-hub-technology-card<?php echo $mapping['mapping_state'] === 'mapped' ? ' evidence-hub-technology-card--mapped' : ' evidence-hub-technology-card--review'; ?>"><span class="evidence-hub-technology-icon"><?php echo evidenceHubOwnerIcon($mapping['mapping_state'] === 'mapped' ? 'check' : 'alert'); ?></span><div><?php if ($mapping['mapping_state'] === 'mapped'): ?><strong dir="auto"><?php echo ownerEscapeHtml((string) $mapping['display_name']); ?></strong><span>Technology documented</span><?php else: ?><strong>Technology needs review</strong><span>Review this technology in Project Management.</span><?php endif; ?></div></article><?php endforeach; ?>
        </div><?php endif; ?>
    </section>
    <section class="evidence-hub-section evidence-hub-progress" aria-labelledby="evidence-hub-progress-title">
        <div class="evidence-hub-section-heading"><div><p>Progress</p><h2 id="evidence-hub-progress-title">Portfolio progress</h2></div><span>Publishing readiness</span></div>
        <article class="evidence-hub-progress-card"><span class="evidence-hub-progress-icon"><?php echo evidenceHubOwnerIcon('target'); ?></span><div><strong><?php echo ownerEscapeHtml(evidenceHubOwnerPublicationLabel($progress['portfolio_publication_state'])); ?></strong><span><bdi data-bidi-number dir="ltr"><?php echo (int) $progress['project_count']; ?></bdi> projects recorded, with <bdi data-bidi-number dir="ltr"><?php echo (int) $progress['projects_with_complete_evidence']; ?></bdi> complete.</span></div></article>
    </section>
</div>
    <?php
}

function renderEvidenceHubOwnerSafeError(): void
{
    ?><div class="evidence-hub-presentation" dir="ltr"><header class="evidence-hub-hero"><div class="evidence-hub-hero-copy"><p class="evidence-hub-kicker"><?php echo evidenceHubOwnerIcon('spark'); ?> Owner workspace</p><h1>Evidence Hub</h1></div></header><section class="evidence-hub-section"><div class="evidence-hub-empty" role="alert"><span class="evidence-hub-empty-icon"><?php echo evidenceHubOwnerIcon('alert'); ?></span><strong>Evidence Hub is temporarily unavailable.</strong><span>Please try again shortly.</span></div></section></div><?php
}

/** @return array{title: string, description: string, label: string, href: string, category: string, context: string, context_icon: string, icon: string, tone: string} */
function evidenceHubOwnerRecommendationPresentationAction(string $ruleId): array
{
    return match ($ruleId) {
        'add_first_project' => ['title' => 'Add your first project', 'description' => 'Add a project to begin building portfolio evidence.', 'label' => 'Add project', 'href' => '/owner_projects.php?add=1', 'category' => 'Portfolio foundation', 'context' => 'Project evidence', 'context_icon' => 'folder-kanban', 'icon' => 'folder-kanban', 'tone' => 'foundation'],
        'complete_project_evidence' => ['title' => 'Complete project evidence', 'description' => 'Add the missing project documentation in Project Management.', 'label' => 'Manage projects', 'href' => '/owner_projects.php', 'category' => 'Documentation', 'context' => 'Project evidence', 'context_icon' => 'document-check', 'icon' => 'document-check', 'tone' => 'documentation'],
        'review_unmapped_technology' => ['title' => 'Review technology evidence', 'description' => 'Connect this technology to the project that demonstrates it.', 'label' => 'Review technology', 'href' => '/owner_projects.php', 'category' => 'Technology evidence', 'context' => 'Technology mapping', 'context_icon' => 'nodes', 'icon' => 'nodes', 'tone' => 'technology'],
        'complete_portfolio_publication' => ['title' => 'Prepare your portfolio for publishing', 'description' => 'Review your private publishing settings before making the portfolio public.', 'label' => 'Review publishing', 'href' => '/owner_publication.php', 'category' => 'Publishing readiness', 'context' => 'Publishing readiness', 'context_icon' => 'target', 'icon' => 'target', 'tone' => 'publication'],
        default => ['title' => 'Review portfolio evidence', 'description' => 'Review your Owner workspace for the next useful improvement.', 'label' => 'Manage projects', 'href' => '/owner_projects.php', 'category' => 'Recommended next step', 'context' => 'Project evidence', 'context_icon' => 'target', 'icon' => 'spark', 'tone' => 'foundation'],
    };
}

function evidenceHubOwnerCountPhrase(int $count, string $singular, string $plural): string
{
    return $count . ' ' . ($count === 1 ? $singular : $plural);
}

function evidenceHubOwnerEvidenceFieldSummary(int $complete, int $expected): string
{
    return $complete . ' of ' . $expected . ' evidence field' . ($expected === 1 ? '' : 's') . ' completed';
}

function evidenceHubOwnerReadinessDetail(string $documentationStatus, int $recommendationCount): string
{
    if ($recommendationCount > 0) {
        return evidenceHubOwnerCountPhrase($recommendationCount, 'recommended next step available', 'recommended next steps available');
    }
    return evidenceHubOwnerStatusLabel($documentationStatus);
}

function evidenceHubOwnerMaturityLabel(string $state): string { return match ($state) {'zero' => 'Getting started', 'partial' => 'In progress', 'ready' => 'Ready', default => 'Unavailable'}; }
function evidenceHubOwnerMaturityIcon(string $state): string { return match ($state) {'ready' => 'check', 'partial' => 'clock', 'zero' => 'spark', default => 'alert'}; }
function evidenceHubOwnerStatusLabel(string $status): string { return match ($status) {'ready' => 'Your evidence is in good shape', 'needs_attention' => 'Evidence needs attention', default => 'Evidence is not available yet'}; }
function evidenceHubOwnerPublicationLabel(string $state): string { return match ($state) {'published' => 'Portfolio published', 'unpublished' => 'Portfolio not published', default => 'Publishing setup incomplete'}; }
