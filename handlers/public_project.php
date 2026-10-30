<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/error_reporting.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/public_project_page.php';
require_once dirname(__DIR__) . '/includes/public_project_presentation.php';

httpRegisterExceptionBoundary('public_project.php');
httpRequireMethod(['GET', 'HEAD']);
header('Cache-Control: no-store');

try {
    $page = publicProjectPageData(getDatabaseConnection(), $_GET['slug'] ?? null, $_GET['id'] ?? null);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'public_project.php', 'public_project_load');
    httpSetHtmlResponse(503);
    httpRenderStatusPage('Project unavailable', 'Project temporarily unavailable.');
    exit;
}
if ($page === null) {
    httpSetHtmlResponse(404);
    httpRenderStatusPage('Project not found', 'Project not found.');
    exit;
}

renderPublicProjectPage($page);
