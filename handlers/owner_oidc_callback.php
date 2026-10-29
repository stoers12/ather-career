<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth0_identity.php';
require_once dirname(__DIR__) . '/includes/auth0_registration.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/owner_auth_recovery.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/security_events.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_oidc_callback.php');
httpRequireMethod(['GET']);

// A callback must not implicitly replace an existing local identity. Explicit
// account switching retires that session before starting its sign-in purpose.
if (array_key_exists(INTERNAL_USER_SESSION_KEY, $_SESSION)) {
    unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]);
    httpAbortHtml(403, 'Use the account-switch or logout option before signing in with another account.');
}

function resetFailedOwnerCallbackSession(): void
{
    try {
        if (session_status() !== PHP_SESSION_ACTIVE) { startOwnerSession(); }
        beginFreshOwnerAccountSelectionSession();
    } catch (Throwable) {
        // A broken store must not leave in-memory authentication authority.
        $_SESSION = [];
        try { destroyOwnerSession(); } catch (Throwable) { session_abort(); }
    }
}

// This hint selects error copy only. Dispatch uses the validated, consumed
// purpose returned below, never this pre-validation value or request input.
$registrationResponse = ($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['purpose'] ?? null) === AUTH0_PURPOSE_REGISTRATION;
$registrationAttempted = false;
try {
    $configuration = auth0ConfigurationFromEnvironment();
    $completed = completeAuth0AuthorizationWithPurpose($configuration, $_GET);
    $identity = $completed->identity;
    $database = getDatabaseConnection();
    if ($completed->purpose === AUTH0_PURPOSE_REGISTRATION) {
        $registrationAttempted = true;
        $user = registerAuth0InternalUser($database, $configuration, $identity);
    } else {
        $user = resolveAuth0InternalUser($database, $configuration, $identity);
    }
    establishVerifiedInternalUserSession($user['user_id'], $user['authz_version']);
    recordOwnerEmailVerificationAfterLogin($user['user_id'], $user['authz_version'], auth0EmailVerifiedClassification($identity->emailVerifiedClaim, $identity->hasNonemptyEmail));
    $destination = ownerJourneyDestination($database, requireAuthenticatedUser($database));
    $sessionWriteFailed = false;
    set_error_handler(static function () use (&$sessionWriteFailed): bool { $sessionWriteFailed = true; return true; });
    try { $sessionStored = session_write_close(); } finally { restore_error_handler(); }
    if (!$sessionStored || $sessionWriteFailed) {
        throw new RuntimeException('Could not persist the authenticated session.');
    }
    httpRedirect($destination);
} catch (Auth0OidcException $exception) {
    reportSecurityEvent('oidc_callback', 'denied', ['reason' => $exception->safeReason]);
    resetFailedOwnerCallbackSession();
    if ($registrationResponse) { renderOwnerRegistrationRecoveryPage(false, 403); }
    renderOwnerAuthRecoveryPage($exception->safeReason === 'authorization_denied');
} catch (IdentityRepositoryException) {
    reportSecurityEvent('oidc_callback', 'denied', ['reason' => 'registration_denied']);
    resetFailedOwnerCallbackSession();
    renderOwnerRegistrationRecoveryPage($registrationAttempted, 403);
} catch (Throwable) {
    reportSecurityEvent('oidc_callback', 'denied', ['reason' => 'dependency_failure']);
    resetFailedOwnerCallbackSession();
    if ($registrationResponse) { renderOwnerRegistrationRecoveryPage($registrationAttempted, 503); }
    renderOwnerAuthRecoveryPage(false);
}
