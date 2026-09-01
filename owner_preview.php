<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/portfolio_scoped_data.php';
require_once __DIR__ . '/includes/owner_layout.php';

startOwnerSession();

$profile = null;
$skills = [];
$projects = [];
$previewError = '';
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $profile = loadAuthorizedPersonalInfo($database, $context);
    $skills = listAuthorizedSkills($database, $context);
    $projects = listAuthorizedProjects($database, $context);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_preview.php', 'owner_preview_load');
    http_response_code(503);
    $previewError = 'Private preview is temporarily unavailable.';
}

$name = is_array($profile) && $profile['full_name'] !== '' ? (string) $profile['full_name'] : 'Your Portfolio';
ownerLayoutStart('Private Preview', '');
?>
<div class="admin-page-header"><div class="admin-page-header-copy"><p class="admin-eyebrow">Private Preview</p><h1 class="admin-page-title"><?php echo ownerEscapeHtml($name); ?></h1><p class="admin-page-description">This is visible only through your authenticated owner session. It does not publish your Portfolio.</p></div><div class="admin-page-header-actions"><a class="button-secondary" href="owner.php">Back to dashboard</a></div></div>
<?php if ($previewError !== ''): ?><p class="status-message error" role="alert"><?php echo ownerEscapeHtml($previewError); ?></p><?php endif; ?>
<?php if (is_array($profile) && (string) ($profile['profile_image_path'] ?? '') !== ''): ?><img src="owner_media.php?type=profile" alt="<?php echo ownerEscapeHtml($name); ?>"><?php endif; ?><h2>About</h2><p><?php echo ownerEscapeHtml(is_array($profile) ? ((string) ($profile['about_me'] ?: $profile['work_description'])) : 'Add profile information to preview it here.'); ?></p>
<h2>Skills</h2><div class="skills-chips"><?php foreach ($skills as $skill): ?><span><?php echo ownerEscapeHtml((string) $skill['skill_name']); ?></span><?php endforeach; ?></div>
<h2 id="projects">Projects</h2><?php if ($projects === []): ?><p>No projects yet.</p><?php endif; ?><div class="admin-project-grid"><?php foreach ($projects as $project): ?><article><?php if ((string) ($project['image_path'] ?? '') !== ''): ?><img src="owner_media.php?type=project&amp;id=<?php echo (int) $project['id']; ?>" alt="<?php echo ownerEscapeHtml((string) $project['title']); ?>"><?php endif; ?><h3><?php echo ownerEscapeHtml((string) $project['title']); ?></h3><p><?php echo ownerEscapeHtml((string) $project['category']); ?></p><p><?php echo ownerEscapeHtml((string) $project['description']); ?></p></article><?php endforeach; ?></div>
<?php ownerLayoutEnd();
