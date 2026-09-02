<?php

require_once __DIR__ . '/session.php';

const LEGACY_ADMIN_AUTH_ENABLED_ENV = 'LEGACY_ADMIN_AUTH_ENABLED';

function adminSessionIsSecureRequest(): bool
{
    return applicationSessionCookieIsSecure();
}

function legacyAdminAuthorityEnabled(): bool
{
    $configured = getenv(LEGACY_ADMIN_AUTH_ENABLED_ENV);
    if (!is_string($configured) || trim($configured) === '') {
        return true;
    }

    return in_array(strtolower(trim($configured)), ['1', 'true'], true);
}

function legacyAdminAuthorityConfigurationIsValid(): bool
{
    $configured = getenv(LEGACY_ADMIN_AUTH_ENABLED_ENV);

    return !is_string($configured)
        || trim($configured) === ''
        || in_array(strtolower(trim($configured)), ['0', '1', 'false', 'true'], true);
}

function denyRetiredLegacyAdminAuthority(): never
{
    if (session_status() === PHP_SESSION_NONE) {
        startApplicationSession('portfolio_admin_session');
    }
    if (session_status() === PHP_SESSION_ACTIVE && session_name() === 'portfolio_admin_session') {
        destroyAdminSession();
    }

    http_response_code(404);
    exit;
}

function startAdminSession(): void
{
    if (!legacyAdminAuthorityEnabled()) {
        denyRetiredLegacyAdminAuthority();
    }
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
