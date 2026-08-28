<?php

function applicationRequestIsHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
}

function applicationSessionCookieIsSecure(): bool
{
    $configured = getenv('SESSION_COOKIE_SECURE');
    if (is_string($configured) && $configured !== '') {
        $normalized = strtolower(trim($configured));
        if (in_array($normalized, ['1', 'true'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false'], true)) {
            return false;
        }
    }

    return applicationRequestIsHttps();
}

function startApplicationSession(?string $sessionName = null): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    if ($sessionName !== null) {
        session_name($sessionName);
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => applicationSessionCookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
