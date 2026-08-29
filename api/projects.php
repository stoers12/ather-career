<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/error_reporting.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode([
        'success' => false,
        'projects' => [],
        'error' => 'Method not allowed.',
    ]);
    exit;
}

try {
    $database = getDatabaseConnection();
    $projectStatement = $database->prepare(
        'SELECT id, title, category, description, github_url, image_path, created_at
         FROM projects
         ORDER BY created_at ASC, id ASC'
    );
    $projectStatement->execute();

    echo json_encode([
        'success' => true,
        'projects' => $projectStatement->fetchAll(),
    ], JSON_UNESCAPED_SLASHES);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'api/projects.php', 'projects_list');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'projects' => [],
        'error' => 'Projects are temporarily unavailable.',
    ]);
}
