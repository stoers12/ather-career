<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/runtime_readiness.php';

httpRegisterExceptionBoundary('ready.php');
httpRequireMethod(['GET', 'HEAD']);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

try {
    $failureReason = runtimeReadinessFailureReason();
    if ($failureReason !== null) {
        runtimeSetRequestOutcome('dependency_unavailable', $failureReason);
        throw new RuntimeException('Runtime readiness dependency is unavailable.');
    }
} catch (Throwable $exception) {
    reportApplicationError($exception, 'ready.php', 'runtime_readiness');
    http_response_code(503);
    echo "UNAVAILABLE\n";
    exit;
}

echo "READY\n";
