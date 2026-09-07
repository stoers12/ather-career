<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';

httpRegisterExceptionBoundary('ready.php');
httpRequireMethod(['GET', 'HEAD']);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

try {
    $database = getDatabaseConnection();
    if ((int) $database->query('SELECT 1')->fetchColumn() !== 1) {
        throw new RuntimeException('Database readiness query returned an unexpected result.');
    }
} catch (Throwable $exception) {
    reportApplicationError($exception, 'ready.php', 'database_readiness');
    http_response_code(503);
    echo "UNAVAILABLE\n";
    exit;
}

echo "READY\n";
