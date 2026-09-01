<?php

declare(strict_types=1);

require_once PHASE2_REPOSITORY_ROOT . '/includes/session.php';

final class IdentitySessionContractTest
{
    public static function run(TestEnvironment $environment): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            destroyInternalUserSession();
        }

        session_name('phase2_identity_session_contract');
        startApplicationSession();
        phase2AssertSame('1', (string) ini_get('session.use_strict_mode'), 'Session strict mode must be enabled.');
        phase2AssertSame('1', (string) ini_get('session.use_only_cookies'), 'Session IDs must be cookie-only.');
        phase2Assert(session_get_cookie_params()['httponly'] === true, 'Session cookies must be HttpOnly.');

        $preAuthenticationId = session_id();
        $_SESSION['portfolio_id'] = 1002;
        $_SESSION['slug'] = 'portfolio-b';
        $_SESSION['email'] = 'attacker@example.test';

        establishVerifiedInternalUserSession(101, 7);
        $authenticatedId = session_id();
        phase2Assert($authenticatedId !== '' && $authenticatedId !== $preAuthenticationId, 'Authentication must regenerate the session identifier.');

        $state = currentInternalUserSession();
        phase2Assert(is_array($state), 'Authenticated internal-user state is missing.');
        phase2AssertSame(101, $state['internal_user_id'], 'Internal User ID is not preserved.');
        phase2AssertSame(7, $state['authz_version'], 'Observed authz version is not preserved.');
        phase2Assert($state['authenticated_at'] > 0, 'Authentication time is missing.');
        phase2AssertSame(['internal_user_id', 'authz_version', 'authenticated_at'], array_keys($state), 'Session identity state contains non-identity authority.');
        phase2Assert(!isset($_SESSION['portfolio_id'], $_SESSION['slug'], $_SESSION['email']), 'Pre-authentication client values survived the authentication transition.');

        $_SESSION[INTERNAL_USER_SESSION_KEY] = ['internal_user_id' => '101', 'authz_version' => 7, 'authenticated_at' => time()];
        phase2AssertSame(null, currentInternalUserSession(), 'Malformed internal User ID must fail closed.');
        $_SESSION[INTERNAL_USER_SESSION_KEY] = ['internal_user_id' => 101, 'authz_version' => 0, 'authenticated_at' => time()];
        phase2AssertSame(null, currentInternalUserSession(), 'Missing or invalid authz version must fail closed.');
        $_SESSION[INTERNAL_USER_SESSION_KEY] = ['internal_user_id' => 101, 'authz_version' => 7];
        phase2AssertSame(null, currentInternalUserSession(), 'Incomplete identity state must fail closed.');

        establishVerifiedInternalUserSession(101, 7);
        session_write_close();
        session_id($preAuthenticationId);
        startApplicationSession();
        phase2Assert(session_id() !== $preAuthenticationId, 'A deleted pre-authentication session ID must not be reused.');
        phase2AssertSame(null, currentInternalUserSession(), 'A pre-authentication session ID regained authenticated state.');

        destroyInternalUserSession();
        phase2AssertSame(PHP_SESSION_NONE, session_status(), 'Internal logout must destroy the session.');
        phase2AssertSame(null, currentInternalUserSession(), 'Internal logout must remove authenticated state.');

        $source = file_get_contents(PHASE2_REPOSITORY_ROOT . '/includes/session.php');
        phase2Assert(is_string($source), 'Session implementation is unreadable.');
        phase2Assert(!str_contains($source, '$_GET') && !str_contains($source, '$_POST') && !str_contains($source, '$_REQUEST'), 'Session identity must not accept URL or request identity input.');
        phase2Assert(!str_contains($source, 'localStorage') && !str_contains($source, 'sessionStorage'), 'Session identity must not use browser storage.');
    }
}
