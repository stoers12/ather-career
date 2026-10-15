<?php

declare(strict_types=1);

// Local PHP built-in-server router. Nothing else in the checkout is served.
$root = dirname(__DIR__, 3);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!is_string($path) || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    http_response_code(404);
    exit;
}

if ($path === '/onboarding-preview.php') {
    define('ATHER_ONBOARDING_PREVIEW', true);
    require __DIR__ . '/onboarding-preview.php';
    return;
}

$assets = [
    '/landing.js' => ['landing.js', 'text/javascript; charset=utf-8'],
    '/onboarding.js' => ['onboarding.js', 'text/javascript; charset=utf-8'],
    '/onboarding.css' => ['onboarding.css', 'text/css; charset=utf-8'],
    '/assets/fonts/noto-sans-arabic-400.ttf' => ['assets/fonts/noto-sans-arabic-400.ttf', 'font/ttf'],
    '/assets/fonts/noto-sans-arabic-500.ttf' => ['assets/fonts/noto-sans-arabic-500.ttf', 'font/ttf'],
];
if ($path === '/favicon.ico') {
    http_response_code(204);
    return;
}
if (!isset($assets[$path])) {
    http_response_code(404);
    exit;
}

[$relativePath, $type] = $assets[$path];
header('Content-Type: ' . $type);
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    readfile($root . '/' . $relativePath);
}
