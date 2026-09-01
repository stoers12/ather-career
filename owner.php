<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/portfolio_scoped_data.php';
require_once __DIR__ . '/includes/owner_layout.php';

startOwnerSession();

$dashboard = ['project_count' => 0, 'skill_count' => 0, 'message_count' => 0, 'profile_count' => 0];
$profile = null;
$dashboardError = '';
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $dashboard = authorizedPortfolioDashboardAggregate($database, $context);
    $profile = loadAuthorizedPersonalInfo($database, $context);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner.php', 'owner_dashboard_load');
    http_response_code(503);
    $dashboardError = 'Some dashboard data is temporarily unavailable.';
}

$ownerName = is_array($profile) && trim((string) $profile['full_name']) !== '' ? trim((string) $profile['full_name']) : 'there';
$profileFields = ['full_name', 'professional_title', 'email', 'phone_primary', 'about_me', 'work_description', 'location', 'linkedin_url', 'github_url', 'profile_image_path'];
$completed = is_array($profile) ? count(array_filter($profileFields, static fn (string $field): bool => !empty($profile[$field]))) : 0;
$profileCompletion = is_array($profile)
    ? (int) round(($completed + ($dashboard['skill_count'] > 0 ? 1 : 0)) / (count($profileFields) + 1) * 100)
    : 0;

ownerLayoutStart('Owner Dashboard', 'dashboard');
?>
<div class="admin-page-header">
    <div class="admin-page-header-copy">
        <p class="admin-eyebrow">Overview</p>
        <h1 class="admin-page-title">Good morning, <bdi><?php echo ownerEscapeHtml(explode(' ', $ownerName)[0]); ?></bdi></h1>
        <p class="admin-page-description">Manage your private Portfolio workspace.</p>
    </div>
    <div class="admin-page-header-actions"><a class="button-primary" href="owner_preview.php">Private Preview</a></div>
</div>
<?php if ($dashboardError !== ''): ?><p class="status-message error" role="alert"><?php echo ownerEscapeHtml($dashboardError); ?></p><?php endif; ?>
<div class="stat-grid" aria-label="Portfolio summary">
    <article class="stat-card"><span class="stat-label">Projects</span><strong><?php echo $dashboard['project_count']; ?></strong><a href="owner_projects.php">Manage projects →</a></article>
    <article class="stat-card"><span class="stat-label">Skills</span><strong><?php echo $dashboard['skill_count']; ?></strong><a href="owner_profile.php#skills">Manage skills →</a></article>
    <article class="stat-card"><span class="stat-label">Messages</span><strong><?php echo $dashboard['message_count']; ?></strong><a href="owner_messages.php">Read messages →</a></article>
    <article class="stat-card"><span class="stat-label">Profile complete</span><strong><?php echo $profileCompletion; ?>%</strong><a href="owner_profile.php">Edit profile →</a></article>
</div>
<h2 class="section-heading">Quick actions</h2>
<div class="quick-actions-grid" aria-label="Quick actions">
    <a class="quick-action" href="owner_profile.php">Edit Profile <span aria-hidden="true">→</span></a>
    <a class="quick-action" href="owner_projects.php?add=1">+ Add Project</a>
    <a class="quick-action" href="owner_messages.php">View Messages <span aria-hidden="true">→</span></a>
    <a class="quick-action" href="owner_preview.php">Private Preview <span aria-hidden="true">→</span></a>
</div>
<?php ownerLayoutEnd();
