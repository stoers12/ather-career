<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/error_reporting.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/owner_dashboard_model.php';
require_once dirname(__DIR__) . '/includes/owner_dashboard_presentation.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner.php');
httpRequireMethod(['GET', 'HEAD']);
$locale = ownerLocale();
header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $locale);
$data = null;
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $data = ownerDashboardPageData($database, $context);
} catch (AuthorizationDeniedException) {
    ownerAuthorizationDenied();
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner.php', 'owner_dashboard_load');
    http_response_code(503);
    // A partially completed read must not be presented as a successful zero/private state.
    $data = null;
}

renderOwnerDashboard($data, $locale, $_GET['account'] ?? null);
