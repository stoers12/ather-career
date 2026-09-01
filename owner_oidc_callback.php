<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth0_identity.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/security_events.php';

startOwnerSession();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

try {
    $configuration = auth0ConfigurationFromEnvironment();
    $identity = completeAuth0Authorization($configuration, $_GET);
    $user = resolveAuth0InternalUser(getDatabaseConnection(), $configuration, $identity);
    establishVerifiedInternalUserSession($user['user_id'], $user['authz_version']);
    $database = getDatabaseConnection();
    header('Location: ' . (ownerHasPortfolio($database, AuthenticatedUserContext::fromValidatedUser($user['user_id'])) ? 'owner.php' : 'owner_onboarding.php'), true, 303);
    exit;
} catch (Auth0OidcException $exception) {
    destroyInternalUserSession();
    reportSecurityEvent('oidc_callback', 'denied', ['reason' => $exception->safeReason]);
} catch (Throwable) {
    destroyInternalUserSession();
    reportSecurityEvent('oidc_callback', 'denied', ['reason' => 'dependency_failure']);
}

http_response_code(403);
exit('Authentication could not be completed.');
