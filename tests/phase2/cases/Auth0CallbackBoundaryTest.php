<?php

declare(strict_types=1);

final class Auth0CallbackBoundaryTest
{
    public static function run(TestEnvironment $environment): void
    {
        $root = PHASE2_REPOSITORY_ROOT . '/';
        $start = (string) file_get_contents($root . 'owner_login.php');
        $callback = (string) file_get_contents($root . 'owner_oidc_callback.php');
        $identity = (string) file_get_contents($root . 'includes/auth0_identity.php');
        $oidc = (string) file_get_contents($root . 'includes/auth0_oidc.php');
        $session = (string) file_get_contents($root . 'includes/session.php');
        $owner = (string) file_get_contents($root . 'includes/owner_flow.php');
        $logout = (string) file_get_contents($root . 'owner_logout.php');

        phase2Assert(str_contains($start, 'beginAuth0Authorization') && str_contains($start, "consumeRateLimit('oidc_start'"), 'Owner login authorization and rate limit changed.');
        phase2Assert(str_contains($oidc, "'state' => " . '$state') && str_contains($oidc, "'nonce' => " . '$nonce')
            && str_contains($oidc, "'code_challenge_method' => 'S256'")
            && str_contains($oidc, 'hash_equals($transaction[\'state\'], $state)')
            && str_contains($oidc, 'unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY])')
            && str_contains($oidc, '$token->verify();') && str_contains($oidc, '$token->validate('), 'OIDC state, nonce, PKCE, validation, or replay boundary changed.');
        phase2Assert(str_contains($callback, 'completeAuth0Authorization')
            && str_contains($callback, 'resolveAuth0InternalUser')
            && str_contains($callback, 'establishVerifiedInternalUserSession')
            && str_contains($callback, 'beginFreshOwnerAccountSelectionSession()')
            && str_contains($callback, 'renderOwnerAuthRecoveryPage(')
            && str_contains($callback, 'ownerHasPortfolio'), 'Callback sequencing or denial cleanup changed.');
        phase2Assert(str_contains($identity, 'hash_equals($configuration->issuer, $identity->issuer)')
            && str_contains($identity, 'findCompatibleIdentityUser($database, $identity->issuer, $identity->subject)')
            && str_contains($identity, "'account_status'] ?? null) !== 'active'"), 'Callback binding or active-account check changed.');
        phase2Assert(str_contains($session, 'session_regenerate_id(true)')
            && str_contains($session, "'httponly' => true")
            && str_contains($session, "'samesite' => 'Lax'")
            && str_contains($session, 'applicationSessionCookieIsSecure()'), 'Session rotation or cookie properties changed.');
        phase2Assert(str_contains($owner, 'requireOwnedPortfolioContext($database)')
            && str_contains($owner, "httpRedirect('/owner_login.php')")
            && str_contains($logout, 'requireValidCsrfToken')
            && str_contains($logout, "httpRedirect('owner_login.php')"), 'Owner authorization, logout, or anonymous redirect changed.');
    }
}
