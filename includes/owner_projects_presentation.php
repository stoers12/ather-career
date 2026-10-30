<?php

declare(strict_types=1);

// Presentation receives only the existing handler contract; no additional reads.
require_once __DIR__ . '/owner_projects_components.php';
/** @param list<array<string, mixed>> $projects @param array<string, mixed> $editingProject
 * @param list<string> $formErrors @param array<string, string> $fieldErrors */
function renderOwnerProjectsPage(array $projects, array $editingProject, bool $portfolioPublished, bool $showProjectForm, string $formMode, string $pageMessage, string $databaseError, array $formErrors, array $fieldErrors): void
{
$text = static fn (string $en, string $ar): string => ownerEscapeHtml(ownerLocale() === 'ar' ? $ar : $en);
$publishedCount = count(array_filter($projects, static fn (array $project): bool => (int) $project['is_published'] === 1));
$steps = [1 => ['Project introduction', 'التعريف بالمشروع'], 2 => ['Technologies and links', 'التقنيات والروابط'], 3 => ['Review and visibility', 'المراجعة والظهور']];
?>
<div class="owner-projects">
<?php if (!$showProjectForm): ?>
<header class="admin-page-header">
    <div class="admin-page-header-copy"><p class="admin-eyebrow"><?php echo $text('Your work', 'أعمالك'); ?> / <?php echo count($projects); ?> <?php echo $text('projects', 'مشاريع'); ?></p><h1><?php echo $text('Projects', 'المشاريع'); ?></h1><p class="admin-page-description"><?php echo $text('Work that deserves to be seen. Arrange it, and develop how you present it.', 'أعمال تستحق أن تُرى. رتّبها، وطوّر طريقة عرضها.'); ?></p></div>
    <a class="button-primary" href="owner_projects.php?add=1"><?php echo evidenceHubOwnerIcon('plus'); ?> <?php echo $text('New project', 'مشروع جديد'); ?></a>
</header>
<?php else: ?>
<div class="owner-project-editor-top"><a href="owner_projects.php"><?php echo $text('Back to Projects', 'العودة إلى المشاريع'); ?></a><?php if ($formMode !== 'edit'): ?><span><?php echo $text('New draft', 'مسودة جديدة'); ?></span><?php endif; ?></div>
<header class="admin-page-header"><div><p class="admin-eyebrow"><?php echo $text('Project studio', 'استوديو المشاريع'); ?></p><h1><?php echo $formMode === 'edit' ? $text('Develop how you present your project.', 'طوّر طريقة عرض مشروعك.') : $text('Give your work a place to be seen.', 'امنح عملك مساحة ليُرى.'); ?></h1><p class="admin-page-description"><?php echo $text('A focused space to make your work clear and discoverable.', 'مساحة مركّزة لتحويل عملك إلى مشروع واضح وقابل للاستكشاف.'); ?></p></div></header>
<?php endif; ?>
<?php ownerRenderFormFeedback($pageMessage, $databaseError === '' ? $formErrors : [$databaseError], $fieldErrors); ?>
<?php if ($showProjectForm): ?>
<nav class="owner-project-steps" aria-label="<?php echo $text('Project editor steps', 'خطوات تحرير المشروع'); ?>">
<?php foreach ($steps as $number => [$en, $ar]): ?><a href="#project-step-<?php echo $number; ?>" data-project-step-link="<?php echo $number; ?>"><span aria-hidden="true">0<?php echo $number; ?></span><?php echo $text($en, $ar); ?></a><?php endforeach; ?>
</nav>
<div class="owner-project-editor">
<form class="project-form owner-project-form" method="POST" action="owner_projects.php" enctype="multipart/form-data" data-owner-form>
    <input type="hidden" name="action" value="<?php echo $formMode === 'edit' ? 'update' : 'add'; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
    <?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $editingProject['id']; ?>"><?php endif; ?>
    <section id="project-step-1" data-project-step="1" aria-labelledby="project-step-title-1" tabindex="-1">
        <h2 id="project-step-title-1"><?php echo $text(...$steps[1]); ?></h2><p class="muted"><?php echo $text('Introduce your project clearly.', 'عرّف بمشروعك بوضوح.'); ?></p>
        <div class="owner-project-fields">
        <?php ownerProjectsField('title', ownerLocale() === 'ar' ? 'اسم المشروع' : 'Project name', 'text', $editingProject, $fieldErrors, true, PROJECT_TITLE_MAX_LENGTH, $text('A clear name that represents your work.', 'اسم واضح يميّز العمل.')); ?>
        <?php ownerProjectsField('category', ownerLocale() === 'ar' ? 'المجال' : 'Category', 'text', $editingProject, $fieldErrors, true, PROJECT_CATEGORY_MAX_LENGTH, $text('For example: data analysis or web development.', 'مثل: تحليل البيانات أو تطوير الويب.')); ?>
        <?php ownerProjectsField('description', ownerLocale() === 'ar' ? 'ماذا يفعل هذا المشروع؟' : 'What does this project do?', 'textarea', $editingProject, $fieldErrors, true, null, $text('Explain the idea and the need it addresses directly.', 'اشرح الفكرة والحاجة التي يعالجها بجمل مباشرة.')); ?>
        </div>
    </section>
    <section id="project-step-2" data-project-step="2" aria-labelledby="project-step-title-2" tabindex="-1">
        <h2 id="project-step-title-2"><?php echo $text(...$steps[2]); ?></h2><p class="muted"><?php echo $text('The tools and links behind your work.', 'الأدوات والروابط التي تقف وراء عملك.'); ?></p>
        <div class="owner-project-fields">
        <?php ownerProjectsField('technologies', ownerText('Technologies'), 'textarea', $editingProject, $fieldErrors, false, null, ownerEscapeHtml(ownerText('One technology per line · Up to')) . ' ' . PROJECT_TECHNOLOGIES_MAXIMUM); ?>
        <?php ownerProjectsField('live_url', ownerLocale() === 'ar' ? 'رابط العرض' : 'Live project URL', 'url', $editingProject, $fieldErrors, false, 500, $text('A demo, report, or public page visitors can explore.', 'عرض حي، تقرير، أو صفحة عامة يمكن للزائر استكشافها.')); ?>
        <?php ownerProjectsField('github_url', ownerText('GitHub URL'), 'url', $editingProject, $fieldErrors, false, PROJECT_GITHUB_URL_MAX_LENGTH, ownerEscapeHtml(ownerText('Leave blank to hide the public GitHub link.'))); ?>
        <div class="form-field"><label for="project_image"><?php echo ownerEscapeHtml(ownerText('Project Image')); ?> <em><?php echo $text('Optional', 'اختياري'); ?></em></label>
        <input type="file" id="project_image" name="project_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-project-image-input data-project-image-max-bytes="<?php echo PROJECT_IMAGE_MAX_BYTES; ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'project_image', 'project-image-help project-image-status'); ?>>
        <small id="project-image-help" class="form-hint"><?php echo ownerEscapeHtml(ownerText('Recommended: 1200 × 675 px (16:9)')); ?><br><?php echo ownerEscapeHtml(ownerText('JPG, PNG or WebP · Max')); ?> <?php echo projectImageMaximumMegabytes(); ?> MB<br><?php echo ownerEscapeHtml(ownerText('High-resolution images are optimized automatically.')); ?></small><small id="project-image-status" class="file-selection-status" role="status" aria-live="polite"></small>
        <?php ownerRenderFieldError($fieldErrors, 'project_image'); ?></div>
        <?php if ($formMode === 'edit' && (string) ($editingProject['image_path'] ?? '') !== ''): ?><div class="current-project-image"><img class="admin-preview" src="owner_media.php?type=project&amp;id=<?php echo (int) $editingProject['id']; ?>" alt="<?php echo ownerEscapeHtml((string) $editingProject['title']); ?>"><label class="checkbox-field"><input type="checkbox" name="remove_image" value="1"><span><?php echo ownerEscapeHtml(ownerText('Remove current image')); ?></span></label></div><?php endif; ?>
        </div>
    </section>
    <section id="project-step-3" data-project-step="3" aria-labelledby="project-step-title-3" tabindex="-1">
        <h2 id="project-step-title-3"><?php echo $text(...$steps[3]); ?></h2><p class="muted"><?php echo $text('Review what visitors will see after saving.', 'راجع ما سيراه الزوار بعد الحفظ.'); ?></p>
        <dl class="owner-project-review">
        <?php foreach (['title' => ['Title', 'اسم المشروع'], 'category' => ['Category', 'المجال'], 'description' => ['Description', 'الوصف'], 'technologies' => ['Technologies', 'التقنيات'], 'live_url' => ['Live project URL', 'رابط العرض'], 'github_url' => ['GitHub URL', 'رابط GitHub']] as $key => [$en, $ar]): ?><div><dt><?php echo $text($en, $ar); ?></dt><dd data-project-review="<?php echo $key; ?>" dir="auto"><?php echo ownerEscapeHtml((string) ($editingProject[$key] ?? '')); ?></dd></div><?php endforeach; ?>
        </dl>
        <div class="publication-status"><strong><?php echo (int) ($editingProject['is_published'] ?? 0) === 1 ? $text('Selected for publication', 'محدد للنشر') : $text('Draft', 'مسودة'); ?></strong><p><?php echo $text('Save project content here. Manage its publication separately from the Projects list.', 'احفظ محتوى المشروع هنا. يمكنك إدارة ظهوره بشكل مستقل من قائمة المشاريع.'); ?></p><?php if (!$portfolioPublished): ?><p><?php echo $text('Your portfolio is private. Visitors cannot see this project.', 'البورتفوليو خاص. لا يمكن للزوار رؤية هذا المشروع.'); ?> <a href="owner_publication.php"><?php echo $text('Publishing settings', 'إعدادات النشر'); ?></a></p><?php endif; ?></div>
    </section>
    <footer class="form-actions"><a class="owner-project-cancel" href="owner_projects.php"><?php echo ownerEscapeHtml(ownerText('Cancel')); ?></a><button type="submit" class="button-secondary" data-pending-label="<?php echo $text('Saving project…', 'جارٍ حفظ المشروع…'); ?>"><?php echo $text('Save project', 'حفظ المشروع'); ?></button><button type="button" class="button-secondary" data-project-previous hidden><?php echo $text('Previous', 'السابق'); ?></button><button type="button" class="button-primary" data-project-next hidden><?php echo $text('Next', 'التالي'); ?></button></footer>
</form>
<aside class="owner-project-preview" data-project-preview hidden aria-label="<?php echo $text('Live card preview', 'معاينة بطاقة المشروع'); ?>"><p class="muted"><?php echo $text('Your project card in the portfolio', 'بطاقة مشروعك في البورتفوليو'); ?></p><article class="owner-project-card">
    <?php ownerProjectsCover($editingProject); ?>
    <div class="owner-project-card-copy"><p class="owner-project-category" data-project-preview-category dir="auto"></p><h3 data-project-preview-title dir="auto" data-empty="<?php echo $text('Project name', 'اسم المشروع'); ?>"></h3><p class="owner-project-description" data-project-preview-description dir="auto"></p><div class="owner-project-tags" dir="ltr" data-project-preview-technologies></div></div>
    </article><p class="form-hint"><?php echo $text('This is a preview that updates as you type. Saving updates the portfolio.', 'هذه معاينة تتغير أثناء الكتابة. الحفظ يحدّث البورتفوليو.'); ?></p></aside>
</div>
<?php else: ?>
<div class="owner-project-tools" data-project-tools hidden>
    <label class="search-field" for="project-search"><span class="visually-hidden"><?php echo $text('Search projects or technologies', 'ابحث عن مشروع أو تقنية'); ?></span><input type="search" id="project-search" placeholder="<?php echo $text('Search projects or technologies', 'ابحث عن مشروع أو تقنية'); ?>"></label>
    <div class="owner-project-filters" role="group" aria-label="<?php echo $text('Publication filter', 'تصفية الظهور'); ?>"><?php foreach (['all' => ['All', 'الكل', count($projects)], 'published' => ['Published', 'منشور', $publishedCount], 'draft' => ['Draft', 'مسودة', count($projects) - $publishedCount]] as $key => [$en, $ar, $count]): ?><button type="button" data-project-filter="<?php echo $key; ?>" aria-pressed="<?php echo $key === 'all' ? 'true' : 'false'; ?>"><?php echo $text($en, $ar); ?> <span data-project-count="<?php echo $key; ?>"><?php echo $count; ?></span></button><?php endforeach; ?></div>
    <div class="owner-project-views" role="group" aria-label="<?php echo $text('Project view', 'طريقة عرض المشاريع'); ?>"><button type="button" data-project-view="grid" aria-pressed="true" aria-label="<?php echo $text('Grid', 'شبكة'); ?>"><?php echo evidenceHubOwnerIcon('blocks'); ?></button><button type="button" data-project-view="list" aria-pressed="false" aria-label="<?php echo $text('List', 'قائمة'); ?>"><?php echo evidenceHubOwnerIcon('menu'); ?></button></div>
</div>
<p class="visually-hidden" data-project-results role="status" aria-live="polite" data-label="<?php echo $text('Matching projects:', 'المشاريع المطابقة:'); ?>" hidden></p>
<?php if ($projects === []): ?><div class="empty-state admin-empty"><strong><?php echo ownerEscapeHtml(ownerText('No projects yet')); ?></strong><span><?php echo ownerEscapeHtml(ownerText('Add a project to start building your showcase.')); ?></span><a class="button-secondary" href="owner_projects.php?add=1"><?php echo ownerEscapeHtml(ownerText('Add your first project')); ?></a></div><?php endif; ?>
<div class="owner-project-grid" data-project-grid>
<?php foreach ($projects as $project): ?>
<article class="owner-project-card" data-project-card data-search="<?php echo ownerEscapeHtml(implode(" ", [(string) $project['title'], (string) $project['category'], ...($project['technologies'] ?? [])])); ?>" data-publication="<?php echo (int) $project['is_published'] === 1 ? 'published' : 'draft'; ?>">
    <?php ownerProjectsCover($project); ?>
    <div class="owner-project-card-copy"><h2 dir="auto"><?php echo ownerEscapeHtml((string) $project['title']); ?></h2><p class="owner-project-category" dir="auto"><?php echo ownerEscapeHtml((string) $project['category']); ?></p><?php ownerProjectsTags($project['technologies'] ?? []); ?></div>
    <footer class="owner-project-card-footer">
    <details class="owner-project-publication"><summary><span class="owner-project-state"><?php echo (int) $project['is_published'] === 1 ? ($portfolioPublished ? $text('Published', 'منشور') : $text('Selected · portfolio private', 'محدد · البورتفوليو خاص')) : $text('Draft', 'مسودة'); ?></span></summary><div class="owner-project-publication-body"><?php renderProjectPublicationControl($project, $portfolioPublished); ?></div></details>
    <a class="owner-project-edit" href="owner_projects.php?edit=<?php echo (int) $project['id']; ?>"><?php echo evidenceHubOwnerIcon('pencil'); ?><?php echo ownerEscapeHtml(ownerText('Edit')); ?></a>
    <details class="owner-project-menu"><summary aria-label="<?php echo $text('Project options', 'خيارات المشروع'); ?>: <?php echo ownerEscapeHtml((string) $project['title']); ?>"><span aria-hidden="true">•••</span></summary><div class="owner-project-menu-body">
        <a href="/owner/projects/<?php echo (int) $project['id']; ?>/evidence"><?php echo $text('Evidence', 'الأدلة'); ?></a><a href="/owner_preview.php?project=<?php echo (int) $project['id']; ?>"><?php echo $text('Case study preview', 'معاينة دراسة الحالة'); ?></a>
        <form method="POST" action="owner_projects.php" data-owner-form data-confirm="<?php echo ownerEscapeHtml('Delete ‘' . (string) $project['title'] . '’? This action cannot be undone.'); ?>" data-confirm-title="<?php echo ownerEscapeHtml(ownerText('Delete project?')); ?>" data-confirm-action="<?php echo ownerEscapeHtml(ownerText('Delete project')); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="id" value="<?php echo (int) $project['id']; ?>"><button type="submit" class="button-danger" data-pending-label="<?php echo ownerEscapeHtml(ownerText('Deleting project…')); ?>"><?php echo ownerEscapeHtml(ownerText('Delete')); ?></button></form>
    </div></details>
    </footer>
</article>
<?php endforeach; ?>
</div>
<div class="empty-state" data-project-no-results hidden><strong><?php echo $text('No matching projects', 'لا توجد مشاريع مطابقة'); ?></strong><p><?php echo $text('Try another search or show all projects.', 'جرّب كلمة بحث أخرى أو اعرض جميع المشاريع.'); ?></p><button class="button-secondary" type="button" data-project-reset><?php echo $text('Show all projects', 'عرض جميع المشاريع'); ?></button></div>
<p class="owner-project-private-note"><?php echo evidenceHubOwnerIcon('lock-keyhole'); ?><?php echo $text('Your evidence is private, even when the project is published.', 'أدلتك خاصة بك، حتى عندما يكون المشروع منشورًا'); ?></p>
<?php endif; ?>
</div>
<?php
}

