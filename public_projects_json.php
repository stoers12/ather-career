<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/public_lifecycle.php';

httpRegisterExceptionBoundary('public_projects_json.php', true);

if (!httpMethodIsAllowed(['GET'])) {
    header('Allow: GET');
    httpJsonResponse(405, ['success' => false, 'projects' => [], 'error' => 'Method not allowed.']);
}

try {
    $database = getDatabaseConnection();
    $slug = normalizePublicSlug($_GET['slug'] ?? null);
    $context = $slug === null ? null : resolvePublicReadContext($database, $slug);
    if ($context === null) {
        httpJsonResponse(404, ['success' => false, 'projects' => [], 'error' => 'Portfolio not found.']);
    }

    httpJsonResponse(200, ['success' => true, 'projects' => listPublicProjectJsonPayload($database, $context, $slug)]);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'public_projects_json.php', 'public_projects_json_load');
    httpJsonResponse(503, ['success' => false, 'projects' => [], 'error' => 'Projects are temporarily unavailable.']);
}
