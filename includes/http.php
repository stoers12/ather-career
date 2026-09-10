<?php

declare(strict_types=1);

require_once __DIR__ . '/error_reporting.php';

/** @param list<string> $allowedMethods */
function httpAllowHeader(array $allowedMethods): string
{
    $methods = [];
    foreach ($allowedMethods as $method) {
        $normalized = strtoupper($method);
        if (preg_match('/^[A-Z]+$/', $normalized) !== 1) {
            throw new InvalidArgumentException('HTTP method contract is invalid.');
        }
        if (!in_array($normalized, $methods, true)) {
            $methods[] = $normalized;
        }
    }

    if ($methods === []) {
        throw new InvalidArgumentException('HTTP method contract is empty.');
    }

    return implode(', ', $methods);
}

/** @param list<string> $allowedMethods */
function httpMethodIsAllowed(array $allowedMethods): bool
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    return is_string($method) && in_array(strtoupper($method), explode(', ', httpAllowHeader($allowedMethods)), true);
}

function httpSetHtmlResponse(int $status): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
}

/** @param list<string> $messages */
function httpRenderStatusPage(string $title, string $heading, array $messages = []): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
        . $safeTitle
        . '</title></head><body><main id="request-main"><h1>'
        . $safeHeading
        . '</h1>';

    if (count($messages) === 1) {
        echo '<p>' . htmlspecialchars($messages[0], ENT_QUOTES, 'UTF-8') . '</p>';
    } elseif ($messages !== []) {
        echo '<ul>';
        foreach ($messages as $message) {
            echo '<li>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul>';
    }

    echo '</main></body></html>';
}

function httpAbortHtml(int $status, string $message): never
{
    httpSetHtmlResponse($status);
    httpRenderStatusPage('Request unavailable', 'Request unavailable.', [$message]);
    exit;
}

/** @param list<string> $allowedMethods */
function httpRequireMethod(array $allowedMethods): void
{
    if (httpMethodIsAllowed($allowedMethods)) {
        return;
    }

    http_response_code(405);
    header('Allow: ' . httpAllowHeader($allowedMethods));
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('Method not allowed.');
}

function httpRedirectTargetIsSafe(string $target): bool
{
    if ($target === '' || str_starts_with($target, '//') || str_contains($target, "\r") || str_contains($target, "\n")) {
        return false;
    }

    return preg_match(
        '@^(?:/[A-Za-z0-9._~/%-]+|[A-Za-z0-9._-]+\.php)(?:\?[A-Za-z0-9._~%=&-]+)?(?:#[A-Za-z0-9._~%-]+)?$@',
        $target,
    ) === 1;
}

function httpRedirect(string $target, int $status = 303): never
{
    if (!in_array($status, [302, 303], true) || !httpRedirectTargetIsSafe($target)) {
        throw new InvalidArgumentException('HTTP redirect target is invalid.');
    }

    header('Location: ' . $target, true, $status);
    exit;
}

/** @param array<string, mixed> $payload */
function httpJsonResponse(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    try {
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        reportApplicationError($exception, 'http.php', 'json_response_encode');
        http_response_code(500);
        echo '{"success":false,"error":"Request unavailable."}';
    }
    exit;
}

function httpRegisterExceptionBoundary(string $route, bool $json = false): void
{
    runtimeStartRequest($route);
    set_exception_handler(static function (Throwable $exception) use ($route, $json): never {
        reportApplicationError($exception, $route, 'unhandled_request');
        if ($json) {
            httpJsonResponse(500, ['success' => false, 'projects' => [], 'error' => 'Request unavailable.']);
        }
        httpAbortHtml(500, 'Something went wrong. Please try again later.');
    });
}
