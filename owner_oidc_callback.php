<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth0_identity.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/owner_auth_recovery.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/security_events.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_oidc_callback.php');
httpRequireMethod(['GET']);

try {
    $configuration = auth0ConfigurationFromEnvironment();
    $identity = completeAuth0Authorization($configuration, $_GET);
    $user = resolveAuth0InternalUser(getDatabaseConnection(), $configuration, $identity);
    establishVerifiedInternalUserSession($user['user_id'], $user['authz_version']);
    $database = getDatabaseConnection();
    httpRedirect(ownerHasPortfolio($database, AuthenticatedUserContext::fromValidatedUser($user['user_id'])) ? 'owner.php' : 'owner_onboarding.php');
} catch (Auth0OidcException $exception) {
    reportSecurityEvent('oidc_callback', 'denied', ['reason' => $exception->safeReason]);
    beginFreshOwnerAccountSelectionSession();
    renderOwnerAuthRecoveryPage($exception->safeReason === 'authorization_denied');
} catch (Throwable) {
    reportSecurityEvent('oidc_callback', 'denied', ['reason' => 'dependency_failure']);
    beginFreshOwnerAccountSelectionSession();
    renderOwnerAuthRecoveryPage(false);
}
