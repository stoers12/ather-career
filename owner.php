<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/portfolio_scoped_data.php';
require_once __DIR__ . '/includes/public_lifecycle.php';
require_once __DIR__ . '/includes/owner_publication_presentation.php';
require_once __DIR__ . '/includes/owner_dashboard_copy.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner.php');
httpRequireMethod(['GET', 'HEAD']);
$locale = ownerLocale();
header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $locale);
$dashboard = $profile = $publication = null;
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $dashboard = authorizedPortfolioDashboardAggregate($database, $context);
    $profile = loadAuthorizedPersonalInfo($database, $context);
    $publication = ownedPublicLifecycleState($database, $context);
} catch (AuthorizationDeniedException) {
    ownerAuthorizationDenied();
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner.php', 'owner_dashboard_load');
    http_response_code(503);
    // A partially completed read must not be presented as a successful zero/private state.
    $dashboard = $profile = $publication = null;
}

$copy = static fn (string $key): string => ownerDashboardCopy($key, $locale);
$ownerName = is_array($profile) ? trim((string) ($profile['full_name'] ?? '')) : '';
$firstName = $ownerName === '' ? '' : (preg_split('/\s+/u', $ownerName)[0] ?? '');
$viewState = is_array($publication) ? ownerPublicationViewState($publication) : null;
$projectState = $dashboard === null ? 'unavailable' : ($dashboard['project_count'] === 0 ? 'first' : 'next');
$stats = [
    ['project_count', 'projects', 'manage_projects', '/owner_projects.php', 'folder'],
    ['skill_count', 'skills', 'manage_skills', '/owner_profile.php#skills', 'settings'],
    ['message_count', 'messages', 'view_messages', '/owner_messages.php', 'mail'],
];
ownerLayoutStart($copy('title'), 'dashboard', true, ['locale' => $locale, 'account_name' => $ownerName]);
?>
<header class="dashboard-header">
    <div class="dashboard-heading">
        <p class="dashboard-eyebrow"><?php echo $copy('title'); ?></p>
        <h1><?php echo $copy('welcome'); ?><?php if ($firstName !== ''): ?><?php echo $locale === 'ar' ? '، ' : ', '; ?><bdi><?php echo ownerEscapeHtml($firstName); ?></bdi><?php endif; ?></h1>
        <p><?php echo $copy('subtitle'); ?></p>
    </div>
</header>
<div class="dashboard-visibility<?php echo $viewState === 'published' ? ' dashboard-visibility--live' : ''; ?>">
    <div><span class="dashboard-status"><?php echo evidenceHubOwnerIcon($viewState === null ? 'alert' : ($viewState === 'published' ? 'eye' : 'lock-keyhole')); ?><?php echo $copy($viewState ?? 'visibility_unavailable'); ?></span><p><?php echo $copy($viewState === null ? 'unavailable_description' : $viewState . '_description'); ?></p></div>
    <a href="/owner_publication.php"><?php echo $copy('publication'); ?><?php echo evidenceHubOwnerIcon('arrow'); ?></a>
</div>
<div class="dashboard-project-panel" data-project-state="<?php echo $projectState; ?>"<?php echo $dashboard === null ? ' role="alert"' : ''; ?>>
    <div class="dashboard-project-copy">
        <h2><?php echo $copy($projectState === 'unavailable' ? 'unavailable_title' : $projectState . '_project'); ?></h2>
        <p><?php echo $copy($projectState === 'unavailable' ? 'unavailable_description' : $projectState . '_description'); ?></p>
        <a class="dashboard-add" href="<?php echo $dashboard === null ? '/owner.php?lang=' . $locale : '/owner_projects.php?add=1'; ?>"><?php if ($dashboard !== null) echo evidenceHubOwnerIcon('plus'); ?><?php echo $copy($dashboard === null ? 'reload' : 'add_project'); ?></a>
    </div>
    <?php if ($projectState === 'first'): ?><img class="dashboard-project-illustration" src="/assets/images/dashboard-projects.svg" alt="" width="360" height="260"><?php endif; ?>
</div>
<div class="dashboard-summary" aria-label="<?php echo $copy('summary'); ?>">
    <?php foreach ($stats as [$field, $label, $action, $href, $icon]): ?>
    <div class="dashboard-stat"><div class="dashboard-stat-top"><span class="dashboard-stat-icon"><?php echo evidenceHubOwnerIcon($icon); ?></span><div><h2><?php echo $copy($label); ?></h2><strong data-dashboard-count="<?php echo $field; ?>"<?php echo $dashboard === null ? ' aria-label="' . $copy('unavailable') . '"' : ''; ?>><?php echo $dashboard === null ? '—' : (int) $dashboard[$field]; ?></strong></div></div><a href="<?php echo $href; ?>"><?php echo $copy($action); ?><?php echo evidenceHubOwnerIcon('arrow'); ?></a></div>
    <?php endforeach; ?>
</div>
<h2 class="dashboard-other-title"><?php echo $copy('other_actions'); ?></h2>
<div class="dashboard-other-actions">
    <?php foreach ([['edit_profile', '/owner_profile.php', 'user'], ['add_experience', '/owner_experiences.php?add=1', 'briefcase']] as [$label, $href, $icon]): ?>
    <a href="<?php echo $href; ?>"><?php echo evidenceHubOwnerIcon($icon); ?><span><?php echo $copy($label); ?></span><?php echo evidenceHubOwnerIcon('arrow'); ?></a>
    <?php endforeach; ?>
</div>
<?php ownerLayoutEnd();
