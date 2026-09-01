<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/error_reporting.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

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

http_response_code(404);
echo json_encode([
    'success' => false,
    'projects' => [],
    'error' => 'Portfolio not found.',
]);
