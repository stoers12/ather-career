<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/error_reporting.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/portfolio_scoped_data.php';
require_once dirname(__DIR__) . '/includes/portfolio_presentation.php';
require_once dirname(__DIR__) . '/includes/public_lifecycle.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_preview.php');
httpRequireMethod(['GET', 'HEAD']);

// ?project={id}: preview of one case study page, published or draft (B-05).
if (array_key_exists('project', $_GET)) {
    require_once dirname(__DIR__) . '/includes/owner_preview_project.php';
    require_once dirname(__DIR__) . '/includes/public_project_presentation.php';
    try {
        $database = getDatabaseConnection();
        $page = ownerPreviewProjectPageData($database, requireOwnerPortfolioContext($database), $_GET['project']);
    } catch (PDOException | DatabaseConfigurationException $exception) {
        reportApplicationError($exception, 'owner_preview.php', 'owner_preview_project_load');
        httpSetHtmlResponse(503);
        httpRenderStatusPage('Preview unavailable', 'Private preview is temporarily unavailable.');
        exit;
    }
    if ($page === null) {
        httpSetHtmlResponse(404);
        httpRenderStatusPage('Project not found', 'Project not found.');
        exit;
    }
    renderPublicProjectPage($page);
    exit;
}

$profile = null;
$skills = [];
$projects = [];
$experiences = [];
$previewError = '';
$portfolioPublished = null;
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $portfolioPublished = ownedPublicLifecycleState($database, $context)['is_published'] === 1;
    $profile = loadAuthorizedPersonalInfo($database, $context);
    $skills = listAuthorizedSkills($database, $context);
    $projects = listAuthorizedProjects($database, $context);
    $experiences = listAuthorizedExperiences($database, $context);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_preview.php', 'owner_preview_load');
    http_response_code(503);
    $previewError = 'Private preview is temporarily unavailable.';
}

renderPortfolioPresentation(is_array($profile) ? $profile : [], $skills, $projects, [
    'preview' => true,
    'preview_error' => $previewError,
    'profile_media_url' => is_array($profile) && (string) ($profile['profile_image_path'] ?? '') !== '' ? '/owner_media.php?type=profile' : '',
    'project_media_url' => static fn (int $projectId): string => "/owner_media.php?type=project&id={$projectId}",
    // B-08: case study preview links and the real portfolio state (null when unavailable).
    'project_case_url' => static fn (int $projectId): string => "/owner_preview.php?project={$projectId}",
    'portfolio_published' => $portfolioPublished,
    'experiences' => $experiences,
]);
