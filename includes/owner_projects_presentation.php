<?php

declare(strict_types=1);

// Presentation receives only the existing handler contract; no additional reads.
require_once __DIR__ . '/owner_projects_components.php';
/** @param list<array<string, mixed>> $projects @param array<string, mixed> $editingProject
 * @param list<string> $formErrors @param array<string, string> $fieldErrors */
function renderOwnerProjectsPage(array $projects, array $editingProject, bool $portfolioPublished, bool $showProjectForm, string $formMode, string $pageMessage, string $databaseError, array $formErrors, array $fieldErrors): void
{
$locale = ownerLocale();
$copy = static fn (string $key): string => ownerEscapeHtml(ownerProjectsCopy($key, $locale));
$publishedCount = count(array_filter($projects, static fn (array $project): bool => (int) $project['is_published'] === 1));
$steps = [1 => 'step_intro', 2 => 'step_tools', 3 => 'step_review'];
?>
<div class="owner-projects">
<?php if (!$showProjectForm): ?>
<header class="admin-page-header">
    <div class="admin-page-header-copy"><p class="admin-eyebrow"><?php echo $copy('eyebrow'); ?> / <?php echo count($projects); ?> <?php echo $copy('project_count'); ?></p><h1><?php echo $copy('title'); ?></h1><p class="admin-page-description"><?php echo $copy('subtitle'); ?></p></div>
    <a class="button-primary" href="owner_projects.php?add=1"><?php echo evidenceHubOwnerIcon('plus'); ?> <?php echo $copy('new_project'); ?></a>
</header>
<?php else: ?>
<div class="owner-project-editor-top"><a href="owner_projects.php"><?php echo $copy('back'); ?></a><?php if ($formMode !== 'edit'): ?><span><?php echo $copy('new_draft'); ?></span><?php endif; ?></div>
<header class="admin-page-header"><div><p class="admin-eyebrow"><?php echo $copy('studio'); ?></p><h1><?php echo $formMode === 'edit' ? $copy('edit_title') : $copy('add_title'); ?></h1><p class="admin-page-description"><?php echo $copy('editor_subtitle'); ?></p></div></header>
<?php endif; ?>
<?php ownerRenderFormFeedback($pageMessage, $databaseError === '' ? $formErrors : [$databaseError], $fieldErrors); ?>
<?php if ($showProjectForm): ?>
<nav class="owner-project-steps" aria-label="<?php echo $copy('steps'); ?>">
<?php foreach ($steps as $number => $key): ?><a href="#project-step-<?php echo $number; ?>" data-project-step-link="<?php echo $number; ?>"><span aria-hidden="true">0<?php echo $number; ?></span><?php echo $copy($key); ?></a><?php endforeach; ?>
</nav>
<div class="owner-project-editor">
<form class="project-form owner-project-form" method="POST" action="owner_projects.php" enctype="multipart/form-data" data-owner-form>
    <input type="hidden" name="action" value="<?php echo $formMode === 'edit' ? 'update' : 'add'; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
    <?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $editingProject['id']; ?>"><?php endif; ?>
    <section id="project-step-1" data-project-step="1" aria-labelledby="project-step-title-1" tabindex="-1">
        <h2 id="project-step-title-1"><?php echo $copy($steps[1]); ?></h2><p class="muted"><?php echo $copy('intro'); ?></p>
        <div class="owner-project-fields">
        <?php ownerProjectsField('title', ownerProjectsCopy('project_name', $locale), 'text', $editingProject, $fieldErrors, true, PROJECT_TITLE_MAX_LENGTH, $copy('hint_title')); ?>
        <?php ownerProjectsField('category', ownerProjectsCopy('category', $locale), 'text', $editingProject, $fieldErrors, true, PROJECT_CATEGORY_MAX_LENGTH, $copy('hint_category')); ?>
        <?php ownerProjectsField('description', ownerProjectsCopy('description_question', $locale), 'textarea', $editingProject, $fieldErrors, true, null, $copy('hint_description')); ?>
        </div>
    </section>
    <section id="project-step-2" data-project-step="2" aria-labelledby="project-step-title-2" tabindex="-1">
        <h2 id="project-step-title-2"><?php echo $copy($steps[2]); ?></h2><p class="muted"><?php echo $copy('tools_intro'); ?></p>
        <div class="owner-project-fields">
        <?php ownerProjectsField('technologies', ownerText('Technologies'), 'textarea', $editingProject, $fieldErrors, false, null, ownerEscapeHtml(ownerText('One technology per line · Up to')) . ' ' . PROJECT_TECHNOLOGIES_MAXIMUM); ?>
        <?php ownerProjectsField('live_url', ownerProjectsCopy('live_url', $locale), 'url', $editingProject, $fieldErrors, false, PROJECT_LIVE_URL_MAX_LENGTH, $copy('hint_live_url')); ?>
        <?php ownerProjectsField('github_url', ownerText('GitHub URL'), 'url', $editingProject, $fieldErrors, false, PROJECT_GITHUB_URL_MAX_LENGTH, ownerEscapeHtml(ownerText('Leave blank to hide the public GitHub link.'))); ?>
        <div class="form-field"><label id="project-image-label" for="project_image"><?php echo ownerEscapeHtml(ownerText('Project Image')); ?> <em><?php echo $copy('optional'); ?></em></label>
        <div class="owner-project-file-picker">
        <input type="file" id="project_image" name="project_image" aria-labelledby="project-image-label" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-project-image-input data-project-image-max-bytes="<?php echo PROJECT_IMAGE_MAX_BYTES; ?>" data-project-image-selected="<?php echo $copy('image_selected'); ?>" data-project-image-too-large="<?php echo $copy('image_too_large'); ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'project_image', 'project-image-help project-image-status'); ?>>
        <label class="button-secondary owner-project-file-trigger" for="project_image"><?php echo $copy('choose_image'); ?></label>
        </div>
        <small id="project-image-help" class="form-hint"><?php echo ownerEscapeHtml(ownerText('Recommended: 1200 × 675 px (16:9)')); ?><br><?php echo ownerEscapeHtml(ownerText('JPG, PNG or WebP · Max')); ?> <?php echo projectImageMaximumMegabytes(); ?> MB<br><?php echo ownerEscapeHtml(ownerText('High-resolution images are optimized automatically.')); ?></small><small id="project-image-status" class="file-selection-status" role="status" aria-live="polite"></small>
        <?php ownerRenderFieldError($fieldErrors, 'project_image'); ?></div>
        <?php if ($formMode === 'edit' && (string) ($editingProject['image_path'] ?? '') !== ''): ?><div class="current-project-image"><img class="admin-preview" src="owner_media.php?type=project&amp;id=<?php echo (int) $editingProject['id']; ?>" alt="<?php echo ownerEscapeHtml((string) $editingProject['title']); ?>"><label class="checkbox-field"><input type="checkbox" name="remove_image" value="1"><span><?php echo ownerEscapeHtml(ownerText('Remove current image')); ?></span></label></div><?php endif; ?>
        </div>
    </section>
    <section id="project-step-3" data-project-step="3" aria-labelledby="project-step-title-3" tabindex="-1">
        <h2 id="project-step-title-3"><?php echo $copy($steps[3]); ?></h2><p class="muted"><?php echo $copy('review_intro'); ?></p>
        <dl class="owner-project-review">
        <?php foreach (['title' => 'review_title', 'category' => 'category', 'description' => 'description', 'technologies' => 'technologies', 'live_url' => 'live_url', 'github_url' => 'github_url'] as $key => $label): ?><div><dt><?php echo $copy($label); ?></dt><dd data-project-review="<?php echo $key; ?>" dir="auto"><?php echo ownerEscapeHtml((string) ($editingProject[$key] ?? '')); ?></dd></div><?php endforeach; ?>
        </dl>
        <div class="publication-status"><strong><?php echo (int) ($editingProject['is_published'] ?? 0) === 1 ? $copy('selected') : $copy('draft'); ?></strong><p><?php echo $copy('publication_help'); ?></p><?php if (!$portfolioPublished): ?><p><?php echo $copy('private_help'); ?> <a href="owner_publication.php"><?php echo $copy('publishing'); ?></a></p><?php endif; ?></div>
    </section>
    <footer class="form-actions"><a class="owner-project-cancel" href="owner_projects.php"><?php echo ownerEscapeHtml(ownerText('Cancel')); ?></a><button type="submit" class="button-secondary" data-pending-label="<?php echo $copy('saving'); ?>"><?php echo $copy('save'); ?></button><button type="button" class="button-secondary" data-project-previous hidden><?php echo $copy('previous'); ?></button><button type="button" class="button-primary" data-project-next hidden><?php echo $copy('next'); ?></button></footer>
</form>
<aside class="owner-project-preview" data-project-preview hidden aria-label="<?php echo $copy('preview'); ?>"><p class="muted"><?php echo $copy('preview_card'); ?></p><article class="owner-project-card">
    <?php ownerProjectsCover($editingProject); ?>
    <div class="owner-project-card-copy"><p class="owner-project-category" data-project-preview-category dir="auto"></p><h3 data-project-preview-title dir="auto" data-empty="<?php echo $copy('project_name'); ?>"></h3><p class="owner-project-description" data-project-preview-description dir="auto"></p><div class="owner-project-tags" dir="ltr" data-project-preview-technologies></div></div>
    </article><p class="form-hint"><?php echo $copy('preview_help'); ?></p></aside>
</div>
<?php else: ?>
<div class="owner-project-tools" data-project-tools hidden>
    <label class="search-field" for="project-search"><span class="visually-hidden"><?php echo $copy('search'); ?></span><input type="search" id="project-search" placeholder="<?php echo $copy('search'); ?>"></label>
    <div class="owner-project-filters" role="group" aria-label="<?php echo $copy('publication_filter'); ?>"><?php foreach (['all' => count($projects), 'published' => $publishedCount, 'draft' => count($projects) - $publishedCount] as $key => $count): ?><button type="button" data-project-filter="<?php echo $key; ?>" aria-pressed="<?php echo $key === 'all' ? 'true' : 'false'; ?>"><?php echo $copy($key); ?> <span data-project-count="<?php echo $key; ?>"><?php echo $count; ?></span></button><?php endforeach; ?></div>
    <div class="owner-project-views" role="group" aria-label="<?php echo $copy('view'); ?>"><button type="button" data-project-view="grid" aria-pressed="true" aria-label="<?php echo $copy('grid'); ?>"><?php echo evidenceHubOwnerIcon('blocks'); ?></button><button type="button" data-project-view="list" aria-pressed="false" aria-label="<?php echo $copy('list'); ?>"><?php echo evidenceHubOwnerIcon('menu'); ?></button></div>
</div>
<p class="visually-hidden" data-project-results role="status" aria-live="polite" data-label="<?php echo $copy('matching'); ?>" hidden></p>
<?php if ($projects === []): ?><div class="empty-state admin-empty"><strong><?php echo ownerEscapeHtml(ownerText('No projects yet')); ?></strong><span><?php echo ownerEscapeHtml(ownerText('Add a project to start building your showcase.')); ?></span><a class="button-secondary" href="owner_projects.php?add=1"><?php echo ownerEscapeHtml(ownerText('Add your first project')); ?></a></div><?php endif; ?>
<div class="owner-project-grid" data-project-grid>
<?php foreach ($projects as $project): ?>
<article class="owner-project-card" data-project-card data-search="<?php echo ownerEscapeHtml(implode(" ", [(string) $project['title'], (string) $project['category'], ...($project['technologies'] ?? [])])); ?>" data-publication="<?php echo (int) $project['is_published'] === 1 ? 'published' : 'draft'; ?>">
    <?php ownerProjectsCover($project); ?>
    <div class="owner-project-card-copy"><h2 dir="auto"><?php echo ownerEscapeHtml((string) $project['title']); ?></h2><p class="owner-project-category" dir="auto"><?php echo ownerEscapeHtml((string) $project['category']); ?></p><?php ownerProjectsTags($project['technologies'] ?? []); ?></div>
    <footer class="owner-project-card-footer">
    <details class="owner-project-publication"><summary><span class="owner-project-state"><?php echo (int) $project['is_published'] === 1 ? ($portfolioPublished ? $copy('published') : $copy('selected_private')) : $copy('draft'); ?></span></summary><div class="owner-project-publication-body"><?php renderProjectPublicationControl($project, $portfolioPublished); ?></div></details>
    <a class="owner-project-edit" href="owner_projects.php?edit=<?php echo (int) $project['id']; ?>"><?php echo evidenceHubOwnerIcon('pencil'); ?><?php echo ownerEscapeHtml(ownerText('Edit')); ?></a>
    <details class="owner-project-menu"><summary aria-label="<?php echo $copy('options'); ?>: <?php echo ownerEscapeHtml((string) $project['title']); ?>"><span aria-hidden="true">•••</span></summary><div class="owner-project-menu-body">
        <a href="/owner/projects/<?php echo (int) $project['id']; ?>/evidence"><?php echo $copy('evidence'); ?></a><a href="/owner_preview.php?project=<?php echo (int) $project['id']; ?>"><?php echo $copy('case_study'); ?></a>
        <form method="POST" action="owner_projects.php" data-owner-form data-confirm="<?php echo ownerEscapeHtml('Delete ‘' . (string) $project['title'] . '’? This action cannot be undone.'); ?>" data-confirm-title="<?php echo ownerEscapeHtml(ownerText('Delete project?')); ?>" data-confirm-action="<?php echo ownerEscapeHtml(ownerText('Delete project')); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="id" value="<?php echo (int) $project['id']; ?>"><button type="submit" class="button-danger" data-pending-label="<?php echo ownerEscapeHtml(ownerText('Deleting project…')); ?>"><?php echo ownerEscapeHtml(ownerText('Delete')); ?></button></form>
    </div></details>
    </footer>
</article>
<?php endforeach; ?>
</div>
<div class="empty-state" data-project-no-results hidden><strong><?php echo $copy('no_matches'); ?></strong><p><?php echo $copy('search_help'); ?></p><button class="button-secondary" type="button" data-project-reset><?php echo $copy('show_all'); ?></button></div>
<p class="owner-project-private-note"><?php echo evidenceHubOwnerIcon('lock-keyhole'); ?><?php echo $copy('private_note'); ?></p>
<?php endif; ?>
</div>
<?php
}

