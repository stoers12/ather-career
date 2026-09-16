<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_contract_mapper.php';
require_once __DIR__ . '/owner_layout.php';

/** @param array<string, mixed> $contract */
function renderEvidenceHubOwnerPresentation(array $contract): void
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
    ?>
<div class="admin-page-header">
    <div class="admin-page-header-copy">
        <p class="admin-eyebrow">Portfolio insight</p>
        <h1 class="admin-page-title">Evidence Hub</h1>
        <p class="admin-page-description">Review the current documentation, technology, and publication evidence for this private Portfolio.</p>
    </div>
</div>

<section aria-labelledby="evidence-hub-summary-title">
    <h2 id="evidence-hub-summary-title" class="section-heading">Portfolio evidence summary</h2>
    <div class="stat-grid" aria-label="Evidence Hub summary metrics">
        <article class="stat-card"><span class="stat-label">Maturity</span><strong><?php echo ownerEscapeHtml(evidenceHubOwnerMaturityLabel($contract['maturity']['state'])); ?></strong><span><?php echo ownerEscapeHtml(evidenceHubOwnerStatusLabel($documentation['status'])); ?></span></article>
        <article class="stat-card"><span class="stat-label">Documentation coverage</span><strong><?php echo $documentation['coverage_bps'] === null ? 'Not available' : (int) $documentation['coverage_bps'] . ' / 10000'; ?></strong><span><?php echo (int) $documentation['complete_evidence_field_count']; ?> of <?php echo (int) $documentation['expected_evidence_field_count']; ?> evidence fields complete</span></article>
        <article class="stat-card"><span class="stat-label">Technology map</span><strong><?php echo (int) $technology['mapped_occurrence_count']; ?> mapped</strong><span><?php echo (int) $technology['unmapped_occurrence_count']; ?> need review</span></article>
        <article class="stat-card"><span class="stat-label">Portfolio progress</span><strong><?php echo (int) $progress['projects_with_complete_evidence']; ?> complete</strong><span><?php echo (int) $progress['project_count']; ?> projects recorded</span></article>
    </div>
    <?php if ($documentation['project_count'] === 0): ?>
        <div class="empty-state admin-empty"><strong>No projects yet</strong><span>Add a project to begin building Portfolio evidence.</span></div>
    <?php endif; ?>
</section>

<section aria-labelledby="evidence-hub-technology-title">
    <h2 id="evidence-hub-technology-title" class="section-heading">Technology evidence map</h2>
    <?php if ($technology['mappings'] === []): ?>
        <div class="empty-state admin-empty"><strong>No technology evidence yet</strong><span>Technology evidence will appear after projects are recorded.</span></div>
    <?php else: ?>
        <div class="admin-project-grid">
            <?php foreach ($technology['mappings'] as $mapping): ?>
                <article>
                    <?php if ($mapping['mapping_state'] === 'mapped'): ?>
                        <h3><?php echo ownerEscapeHtml((string) $mapping['display_name']); ?></h3>
                        <p>Mapped technology evidence</p>
                    <?php else: ?>
                        <h3>Unmapped technology needs review</h3>
                        <p>Review the technology entries in your authorized project management screen.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section aria-labelledby="evidence-hub-progress-title">
    <h2 id="evidence-hub-progress-title" class="section-heading">Portfolio progress</h2>
    <article>
        <h3><?php echo ownerEscapeHtml(evidenceHubOwnerPublicationLabel($progress['portfolio_publication_state'])); ?></h3>
        <p><?php echo (int) $progress['project_count']; ?> projects are recorded, with <?php echo (int) $progress['projects_with_complete_evidence']; ?> currently complete.</p>
    </article>
</section>

<section aria-labelledby="evidence-hub-recommendations-title">
    <h2 id="evidence-hub-recommendations-title" class="section-heading">Current actions</h2>
    <?php if ($contract['recommendations'] === []): ?>
        <div class="empty-state admin-empty"><strong>No current actions</strong><span>Your current evidence does not need an action right now.</span></div>
    <?php else: ?>
        <div class="admin-project-grid">
            <?php foreach ($contract['recommendations'] as $recommendation): ?>
                <?php $action = evidenceHubOwnerRecommendationPresentationAction($recommendation['rule_id']); ?>
                <article aria-label="Evidence Hub recommendation">
                    <h3><?php echo ownerEscapeHtml($action['title']); ?></h3>
                    <p><?php echo ownerEscapeHtml($action['description']); ?></p>
                    <a class="button-secondary" href="<?php echo ownerEscapeHtml($action['href']); ?>"><?php echo ownerEscapeHtml($action['label']); ?></a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
    <?php
}

function renderEvidenceHubOwnerSafeError(): void
{
    ?>
<div class="admin-page-header"><div class="admin-page-header-copy"><p class="admin-eyebrow">Portfolio insight</p><h1 class="admin-page-title">Evidence Hub</h1></div></div>
<section aria-labelledby="evidence-hub-error-title"><div class="empty-state admin-empty" role="alert"><strong id="evidence-hub-error-title">Evidence Hub is temporarily unavailable.</strong><span>Please try again later.</span></div></section>
    <?php
}

/** @return array{title: string, description: string, label: string, href: string} */
function evidenceHubOwnerRecommendationPresentationAction(string $ruleId): array
{
    return match ($ruleId) {
        'add_first_project' => ['title' => 'Add your first project', 'description' => 'Record a project to begin building Portfolio evidence.', 'label' => 'Open project management', 'href' => '/owner_projects.php?add=1'],
        'complete_project_evidence' => ['title' => 'Complete project evidence', 'description' => 'Review project documentation in the authorized project management screen.', 'label' => 'Open project management', 'href' => '/owner_projects.php'],
        'review_unmapped_technology' => ['title' => 'Review technology evidence', 'description' => 'Review technology entries in the authorized project management screen.', 'label' => 'Open project management', 'href' => '/owner_projects.php'],
        'complete_portfolio_publication' => ['title' => 'Complete Portfolio publication', 'description' => 'Review your private publication settings before publishing.', 'label' => 'Open publication settings', 'href' => '/owner_publication.php'],
        default => ['title' => 'Review Portfolio evidence', 'description' => 'Review your private Portfolio workspace.', 'label' => 'Open project management', 'href' => '/owner_projects.php'],
    };
}

function evidenceHubOwnerMaturityLabel(string $state): string
{
    return match ($state) {
        'zero' => 'Getting started',
        'partial' => 'In progress',
        'ready' => 'Ready',
        default => 'Not available',
    };
}

function evidenceHubOwnerStatusLabel(string $status): string
{
    return match ($status) {
        'ready' => 'Current evidence is ready',
        'needs_attention' => 'Evidence needs attention',
        default => 'Evidence is not available yet',
    };
}

function evidenceHubOwnerPublicationLabel(string $state): string
{
    return match ($state) {
        'published' => 'Portfolio is published',
        'unpublished' => 'Portfolio is not published',
        default => 'Publication is not configured',
    };
}
