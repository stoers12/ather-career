<?php

function adminSessionIsSecureRequest(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
}

function startAdminSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_name('portfolio_admin_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => adminSessionIsSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
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
