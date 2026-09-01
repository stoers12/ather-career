<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/public_lifecycle.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'projects' => [], 'error' => 'Method not allowed.']);
    exit;
}

try {
    $database = getDatabaseConnection();
    $context = resolvePublicReadContext($database, $_GET['slug'] ?? null);
    if ($context === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'projects' => [], 'error' => 'Portfolio not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'projects' => listPublicProjects($database, $context)], JSON_UNESCAPED_SLASHES);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'public_projects_json.php', 'public_projects_json_load');
    http_response_code(503);
    echo json_encode(['success' => false, 'projects' => [], 'error' => 'Projects are temporarily unavailable.']);
}
