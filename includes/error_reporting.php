<?php

function safeErrorContext(string $value): string
{
    $sanitized = preg_replace('/[^A-Za-z0-9_.:\/-]/', '_', $value);

    return substr($sanitized ?? 'unknown', 0, 80);
}

function reportApplicationError(Throwable $exception, string $route, string $action): string
{
    try {
        $errorId = bin2hex(random_bytes(6));
    } catch (Throwable $identifierError) {
        $errorId = substr(hash('sha256', microtime(true) . getmypid()), 0, 12);
    }

    error_log(sprintf(
        'application_error error_id=%s route=%s action=%s exception=%s',
        $errorId,
        safeErrorContext($route),
        safeErrorContext($action),
        safeErrorContext(get_class($exception))
    ));

    return $errorId;
}
