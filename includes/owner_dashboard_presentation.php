<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_layout.php';

/**
 * Render only the B-01 contract; a failed read never looks like an empty account.
 * @param array{
 *     owner_name: string, first_name: string,
 *     overview: array{total: int, published: int, drafts: int, needs_writing: int,
 *         recent: list<array{id: int, title: string, category: string, published: bool, written_parts: int}>},
 *     next_step: string, publication_state: string, public_url: ?string
 * }|null $data
 */
function renderOwnerDashboard(?array $data, string $locale): void
{
    $copy = static fn (string $key): string => ownerDashboardCopy($key, $locale);
    $ownerName = $data['owner_name'] ?? '';
    $firstName = $data['first_name'] ?? '';
    $overview = $data['overview'] ?? null;
    $viewState = $data['publication_state'] ?? null;
    $publicUrl = $data['public_url'] ?? null;
    $lang = '?lang=' . $locale;
    $services = [
        ['01', 'user-round-pen', 'profile', '/owner_profile.php' . $lang],
        ['02', 'panels-top-left', 'projects', '/owner_projects.php' . $lang],
        ['03', 'book-open-check', 'evidence', '/owner/evidence-hub'],
    ];
    $nextStep = $data['next_step'] ?? null;
    $nextTargets = [
        'first_project' => '/owner_projects.php?add=1',
        'write_evidence' => '/owner/evidence-hub',
        'publish_drafts' => '/owner_projects.php' . $lang,
        'preview' => '/owner_preview.php',
    ];
    // Two-letter mark for a project tile, like the reference's thumbnails.
    $mark = static fn (string $title): string => mb_strtoupper(implode('', array_map(
        static fn (string $word): string => mb_substr($word, 0, 1, 'UTF-8'),
        array_slice(preg_split('/\s+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY) ?: ['—'], 0, 2)
    )), 'UTF-8');
    ownerLayoutStart($copy('title'), 'dashboard', true, ['locale' => $locale, 'account_name' => $ownerName]);
?>
<header class="dashboard-header">
    <div class="dashboard-heading">
        <span class="dashboard-eyebrow"><?php echo $copy('home_eyebrow'); ?></span>
        <h1><?php echo $copy('welcome'); ?><?php if ($firstName !== ''): ?><?php echo $locale === 'ar' ? '، ' : ', '; ?><bdi><?php echo ownerEscapeHtml($firstName); ?></bdi><?php endif; ?></h1>
        <p><?php echo $copy('home_subtitle'); ?></p>
    </div>
    <?php if ($overview !== null): ?><a class="dashboard-button" href="/owner_projects.php?add=1"><?php echo evidenceHubOwnerIcon('plus'); ?><?php echo $copy('add_project'); ?></a><?php endif; ?>
</header>
<?php if ($overview === null): ?>
<div class="dashboard-unavailable" role="alert">
    <h2><?php echo $copy('unavailable_title'); ?></h2>
    <p><?php echo $copy('unavailable_description'); ?></p>
    <a class="dashboard-button dashboard-primary" href="/owner.php<?php echo $lang; ?>"><?php echo $copy('reload'); ?></a>
</div>
<?php else: ?>
<div class="dashboard-services-heading">
    <h2><?php echo $copy('services_title'); ?></h2>
    <details class="dashboard-guide">
        <summary><?php echo $copy('guide'); ?><?php echo evidenceHubOwnerIcon('circle-help'); ?></summary>
        <div class="dashboard-guide-content">
            <h3><?php echo $copy('guide_title'); ?></h3>
            <ol><li><?php echo $copy('guide_1'); ?></li><li><?php echo $copy('guide_2'); ?></li><li><?php echo $copy('guide_3'); ?></li></ol>
            <a class="dashboard-button dashboard-primary" href="/owner_profile.php<?php echo $lang; ?>"><?php echo $copy('guide_cta'); ?></a>
        </div>
    </details>
</div>
<section class="dashboard-services" aria-label="<?php echo $copy('services_label'); ?>">
    <?php foreach ($services as [$number, $icon, $key, $href]): ?>
    <article class="dashboard-service">
        <div class="dashboard-service-top"><span class="dashboard-service-icon"><?php echo evidenceHubOwnerIcon($icon); ?></span><span class="dashboard-service-no" aria-hidden="true"><?php echo $number; ?></span></div>
        <span class="dashboard-service-label"><?php echo $copy('svc_' . $key . '_label'); ?></span>
        <h3><?php echo $copy('svc_' . $key . '_title'); ?></h3>
        <p><?php echo $copy('svc_' . $key . '_text'); ?></p>
        <a class="dashboard-link" href="<?php echo $href; ?>"><?php echo $copy('svc_' . $key . '_cta'); ?><?php echo evidenceHubOwnerIcon('arrow-left'); ?></a>
    </article>
    <?php endforeach; ?>
</section>
<section class="dashboard-next" data-next-step="<?php echo $nextStep; ?>">
    <div>
        <span class="dashboard-eyebrow"><?php echo $copy('next_eyebrow'); ?></span>
        <h2><?php echo $copy('next_' . $nextStep . '_title'); ?></h2>
        <p><?php echo sprintf($copy('next_' . $nextStep . '_text'), ...match ($nextStep) {
            'write_evidence' => [$overview['needs_writing'], $overview['total']],
            'publish_drafts' => [$overview['drafts'], $overview['total']],
            'preview' => [$overview['published'], $overview['total']],
            default => [],
        }); ?></p>
    </div>
    <a class="dashboard-button dashboard-primary" href="<?php echo $nextTargets[$nextStep]; ?>"><?php echo $copy('next_' . $nextStep . '_cta'); ?><?php echo evidenceHubOwnerIcon('arrow-up-left'); ?></a>
</section>
<div class="dashboard-lower">
    <section class="dashboard-recent" aria-labelledby="dashboard-recent-title">
        <div class="dashboard-panel-head"><h2 id="dashboard-recent-title"><?php echo $copy('recent_title'); ?></h2><a class="dashboard-link" href="/owner_projects.php<?php echo $lang; ?>"><?php echo $copy('all_projects'); ?><?php echo evidenceHubOwnerIcon('arrow-left'); ?></a></div>
        <?php if ($overview['recent'] === []): ?>
        <div class="dashboard-empty"><h3><?php echo $copy('empty_title'); ?></h3><p><?php echo $copy('empty_text'); ?></p></div>
        <?php else: ?>
        <div class="dashboard-recent-header" aria-hidden="true"><span><?php echo $copy('col_project'); ?></span><span><?php echo $copy('col_visibility'); ?></span><span></span></div>
        <ul class="dashboard-recent-list">
            <?php foreach ($overview['recent'] as $index => $project): ?>
            <li class="dashboard-recent-row">
                <div class="dashboard-recent-main">
                    <span class="dashboard-thumb<?php echo $index % 2 === 1 ? ' gold' : ''; ?>" aria-hidden="true"><span><?php echo ownerEscapeHtml($mark($project['title'])); ?></span></span>
                    <div><h3 dir="auto"><?php echo ownerEscapeHtml($project['title']); ?></h3><p dir="auto"><?php echo ownerEscapeHtml($project['category']); ?></p></div>
                </div>
                <span class="dashboard-status<?php echo $project['published'] ? '' : ' dashboard-status-muted'; ?>"><?php echo evidenceHubOwnerIcon($project['published'] ? 'check' : 'file-pen-line'); ?><?php echo $copy($project['published'] ? 'status_published' : 'status_draft'); ?></span>
                <a class="dashboard-icon-button" href="/owner_projects.php?edit=<?php echo (int) $project['id']; ?>&amp;lang=<?php echo $locale; ?>" aria-label="<?php echo ownerEscapeHtml(sprintf($copy('edit_named'), $project['title'])); ?>"><?php echo evidenceHubOwnerIcon('pencil'); ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
    <aside class="dashboard-sharing">
        <span class="dashboard-service-icon"><?php echo evidenceHubOwnerIcon('globe'); ?></span>
        <h3><?php echo $copy('sharing_title'); ?></h3>
        <p><?php echo $copy('sharing_text'); ?></p>
        <div class="dashboard-sharing-status<?php echo $viewState === 'published' ? '' : ' dashboard-sharing-status--private'; ?>"><?php echo evidenceHubOwnerIcon($viewState === 'published' ? 'circle-check' : 'eye-off'); ?><?php if ($publicUrl !== null): ?><a href="<?php echo ownerEscapeHtml($publicUrl); ?>"><?php echo $copy('portfolio_published'); ?></a><?php else: ?><span><?php echo $copy($viewState); ?></span><?php endif; ?></div>
        <a class="dashboard-link" href="/owner_publication.php<?php echo $lang; ?>"><?php echo $copy('publication'); ?><?php echo evidenceHubOwnerIcon('arrow-left'); ?></a>
    </aside>
</div>
<?php endif; ?>
<?php ownerLayoutEnd();
}
