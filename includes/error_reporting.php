<?php

require_once __DIR__ . '/security_events.php';

function safeErrorContext(string $value): string
{
    $sanitized = preg_replace('/[^A-Za-z0-9_.:\/-]/', '_', $value);

    return substr($sanitized ?? 'unknown', 0, 80);
}

function safePdoErrorCode(PDOException $exception): ?string
{
    $candidate = $exception->errorInfo[0] ?? $exception->getCode();
    if (!is_string($candidate) && !is_int($candidate)) {
        return null;
    }

    $normalized = strtoupper((string) $candidate);

    return preg_match('/^[A-Z0-9]{2,5}$/', $normalized) === 1 ? $normalized : null;
}

function pdoErrorCategory(PDOException $exception): string
{
    $code = safePdoErrorCode($exception);
    if ($code !== null) {
        if (str_starts_with($code, '08')) {
            return 'database_connection';
        }
        if (str_starts_with($code, '23') || str_starts_with($code, '22')) {
            return 'database_constraint';
        }
        if (str_starts_with($code, '42')) {
            return 'database_schema';
        }
    }

    $driverCode = $exception->errorInfo[1] ?? null;
    if (!is_int($driverCode) && !ctype_digit((string) $driverCode)) {
        return 'database';
    }

    return match ((int) $driverCode) {
        1044, 1045 => 'database_authentication',
        2002, 2003, 2006, 2013 => 'database_connection',
        1054, 1146 => 'database_schema',
        1062, 1451, 1452 => 'database_constraint',
        default => 'database',
    };
}

function applicationErrorCategory(Throwable $exception, string $safeAction): string
{
    if ($exception instanceof PDOException) {
        return pdoErrorCategory($exception);
    }

    if (get_class($exception) === 'DatabaseConfigurationException') {
        return 'database_configuration';
    }

    if (str_contains($safeAction, 'rate_limit')) {
        return 'rate_limit_storage';
    }

    if (str_contains($safeAction, 'image') || str_contains($safeAction, 'cleanup') || str_contains($safeAction, 'path_rejected')) {
        return 'filesystem';
    }

    return $exception instanceof RuntimeException ? 'runtime' : 'unknown';
}

function reportApplicationError(Throwable $exception, string $route, string $action): string
{
    try {
        $errorId = bin2hex(random_bytes(6));
    } catch (Throwable $identifierError) {
        $errorId = substr(hash('sha256', microtime(true) . getmypid()), 0, 12);
    }

    $safeRoute = safeErrorContext($route);
    $safeAction = safeErrorContext($action);
    $safeCode = $exception instanceof PDOException ? safePdoErrorCode($exception) : null;

    error_log(sprintf(
        'application_error error_id=%s route=%s action=%s exception=%s category=%s code=%s',
        $errorId,
        $safeRoute,
        $safeAction,
        safeErrorContext(get_class($exception)),
        applicationErrorCategory($exception, $safeAction),
        $safeCode ?? 'none'
    ));

    return $errorId;
}
