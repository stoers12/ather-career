<?php

require_once __DIR__ . '/session.php';

function adminSessionIsSecureRequest(): bool
{
    return applicationSessionCookieIsSecure();
}

function startAdminSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    startApplicationSession('portfolio_admin_session');
}

function isAdminAuthenticated(): bool
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdminAuthentication(): void
{
    if (!isAdminAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function destroyAdminSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => adminSessionIsSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();
}
