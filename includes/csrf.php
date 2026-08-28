<?php

function getCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) !== 64) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken(mixed $submittedToken): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function requireValidCsrfToken(mixed $submittedToken): void
{
    if (!isValidCsrfToken($submittedToken)) {
        http_response_code(403);
        exit('Invalid request.');
    }
}
