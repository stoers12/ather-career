<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/portfolio_scoped_data.php';
require_once __DIR__ . '/includes/portfolio_presentation.php';

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

renderPortfolioPresentation(is_array($profile) ? $profile : [], $skills, $projects, [
    'preview' => true,
    'preview_error' => $previewError,
    'profile_media_url' => is_array($profile) && (string) ($profile['profile_image_path'] ?? '') !== '' ? '/owner_media.php?type=profile' : '',
    'project_media_url' => static fn (int $projectId): string => "/owner_media.php?type=project&id={$projectId}",
]);
