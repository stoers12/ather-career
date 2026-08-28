<?php
require_once __DIR__ . '/includes/admin_session.php';
require_once __DIR__ . '/includes/csrf.php';

startAdminSession();
requireAdminAuthentication();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';

$projectCount = 0;
$skillCount = 0;
$messageCount = 0;
$profileCompletion = 0;
$ownerName = 'there';
$dashboardError = '';
try {
    $database = getDatabaseConnection();
    $projectCount = (int) $database->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    $skillCount = (int) $database->query('SELECT COUNT(*) FROM skills')->fetchColumn();
    $messageCount = (int) $database->query('SELECT COUNT(*) FROM messages')->fetchColumn();
    $profile = $database->query('SELECT full_name, professional_title, email, phone_primary, about_me, work_description, location, linkedin_url, github_url, profile_image_path FROM personal_info ORDER BY id ASC LIMIT 1')->fetch();
    if ($profile !== false) {
        $ownerName = trim((string) $profile['full_name']) !== '' ? trim((string) $profile['full_name']) : 'there';
        $profileFields = ['full_name', 'professional_title', 'email', 'phone_primary', 'about_me', 'work_description', 'location', 'linkedin_url', 'github_url', 'profile_image_path'];
        $completedFields = count(array_filter($profileFields, static fn ($field) => !empty($profile[$field])));
        $profileCompletion = (int) round(($completedFields + ($skillCount > 0 ? 1 : 0)) / (count($profileFields) + 1) * 100);
    }
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'admin.php', 'dashboard_load');
    $dashboardError = 'Some dashboard data is temporarily unavailable.';
}
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - My Portfolio</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js" defer></script>
</head>
<body>
    <div class="admin-layout">
        <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
        <main class="admin-content">
            <section>
                <div class="admin-page-header">
                    <div class="admin-page-header-copy">
                        <p class="admin-eyebrow">Overview</p>
                        <h1 class="admin-page-title">Good morning, <bdi><?php echo htmlspecialchars(explode(' ', $ownerName)[0], ENT_QUOTES, 'UTF-8'); ?></bdi></h1>
                        <p class="admin-page-description">Manage and update your portfolio from one place.</p>
                    </div>
                    <div class="admin-page-header-actions">
                        <a class="button-primary" href="index.php" target="_blank" rel="noopener">View Portfolio ↗</a>
                    </div>
                </div>
                <?php if ($dashboardError !== ''): ?><p class="status-message error" role="alert"><?php echo htmlspecialchars($dashboardError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <div class="stat-grid" aria-label="Portfolio summary">
                    <article class="stat-card"><span class="stat-label">Projects</span><strong><?php echo $projectCount; ?></strong><a href="projects.php">Manage projects →</a></article>
                    <article class="stat-card"><span class="stat-label">Skills</span><strong><?php echo $skillCount; ?></strong><a href="personal_info.php#skills">Manage skills →</a></article>
                    <article class="stat-card"><span class="stat-label">Messages</span><strong><?php echo $messageCount; ?></strong><a href="messages.php">Read messages →</a></article>
                    <article class="stat-card"><span class="stat-label">Profile complete</span><strong><?php echo $profileCompletion; ?>%</strong><?php if ($profileCompletion === 100): ?><a href="index.php" target="_blank" rel="noopener">View profile →</a><?php else: ?><a href="personal_info.php">Complete profile →</a><?php endif; ?></article>
                </div>
                <h2 class="section-heading">Quick actions</h2>
                <div class="quick-actions-grid" aria-label="Quick actions">
                    <a class="quick-action" href="personal_info.php">Edit Profile <span aria-hidden="true">→</span></a>
                    <a class="quick-action" href="projects.php?add=1">+ Add Project</a>
                    <a class="quick-action" href="messages.php">View Messages <span aria-hidden="true">→</span></a>
                    <a class="quick-action" href="index.php" target="_blank" rel="noopener">Preview Portfolio <span aria-hidden="true">↗</span></a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
