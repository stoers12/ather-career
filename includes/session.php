<?php

const INTERNAL_USER_SESSION_KEY = 'internal_user_session';

function applicationRequestIsHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
}

function configuredSessionCookieIsSecure(): ?bool
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

    return null;
}

function applicationSessionCookieIsSecure(): bool
{
    $configured = configuredSessionCookieIsSecure();
    if ($configured !== null) {
        return $configured;
    }

    return applicationRequestIsHttps();
}

function startApplicationSession(?string $sessionName = null): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
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

/**
 * Establishes the future Phase 2 internal-user session after a caller has
 * already validated an external identity and resolved its durable User record.
 * This boundary deliberately has no Portfolio, slug, email, or provider-token
 * input. P2J-02/P2J-03 will supply current account-state validation and owner
 * authorization before protected Portfolio operations are allowed.
 */
function establishVerifiedInternalUserSession(int $internalUserId, int $observedAuthzVersion): void
{
    if ($internalUserId < 1 || $observedAuthzVersion < 1) {
        throw new InvalidArgumentException('Internal user session identity is invalid.');
    }

    startApplicationSession();

    // Do not carry pre-authentication request/session values into the new
    // authenticated session. This also removes any client-originated tenant
    // selection values that a future caller might otherwise inspect wrongly.
    $_SESSION = [];
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Could not regenerate the authenticated session identifier.');
    }

    $_SESSION[INTERNAL_USER_SESSION_KEY] = [
        'internal_user_id' => $internalUserId,
        'authz_version' => $observedAuthzVersion,
        'authenticated_at' => time(),
    ];
}

/**
 * Returns only well-formed trusted identity state. This does not authorize a
 * Portfolio operation and does not substitute for the later current-User
 * account-status/authz-version lookup.
 *
 * @return array{internal_user_id: int, authz_version: int, authenticated_at: int}|null
 */
function currentInternalUserSession(): ?array
{
    $state = $_SESSION[INTERNAL_USER_SESSION_KEY] ?? null;
    if (!is_array($state)
        || count($state) !== 3
        || !array_key_exists('internal_user_id', $state)
        || !array_key_exists('authz_version', $state)
        || !array_key_exists('authenticated_at', $state)
        || !is_int($state['internal_user_id'])
        || !is_int($state['authz_version'])
        || !is_int($state['authenticated_at'])
        || $state['internal_user_id'] < 1
        || $state['authz_version'] < 1
        || $state['authenticated_at'] < 1) {
        return null;
    }

    return [
        'internal_user_id' => $state['internal_user_id'],
        'authz_version' => $state['authz_version'],
        'authenticated_at' => $state['authenticated_at'],
    ];
}

/**
 * Ends the complete future internal-user session. V1 admin logout remains on
 * its existing session path until the approved authentication cutover.
 */
function destroyInternalUserSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => applicationSessionCookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();
}
