<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth0_oidc.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/rate_limit.php';
require_once dirname(__DIR__) . '/includes/security_events.php';

// Confirm identity before export or deletion (L-03; docs/contracts/account-data-rights.md).
// Like account switching, it retires the current session and asks Auth0 for a
// fresh sign-in (prompt=login); the callback then returns to the fixed page.
startOwnerSession();
httpRegisterExceptionBoundary('owner_reauthenticate.php');
httpRequireMethod(['POST']);
$returnPages = ['data' => '/owner_settings.php#data', 'deletion' => '/owner_account_deletion.php'];
$keys = array_keys($_POST);
sort($keys);
if ($_GET !== [] || $_FILES !== [] || !in_array($keys, [['csrf_token'], ['csrf_token', 'return']], true)
    || !is_string($_POST['csrf_token']) || $_POST['csrf_token'] === ''
    || !isset($returnPages[$_POST['return'] ?? 'data'])) {
    httpAbortHtml(403, 'Invalid request.');
}
requireValidCsrfToken($_POST['csrf_token']);
$returnTo = $returnPages[$_POST['return'] ?? 'data'];

$selectionStarted = false;
try {
    requireOwnerAuthenticatedUser(getDatabaseConnection());
    $limit = consumeRateLimit('oidc_start', rateLimitClientIp(), OIDC_START_RATE_LIMIT_ATTEMPTS, OIDC_START_RATE_LIMIT_WINDOW_SECONDS);
    if (!$limit['allowed']) {
        reportSecurityEvent('rate_limit_denial', 'denied', ['scope' => 'oidc_start', 'reason' => 'threshold_exceeded']);
        http_response_code(429);
        header('Retry-After: ' . $limit['retry_after']);
        exit('Please try again later.');
    }

    $configuration = auth0ConfigurationFromEnvironment();
    $discovery = auth0Discovery($configuration);
    $selectionStarted = true;
    beginFreshOwnerAccountSelectionSession();
    $authorization = beginAuth0Authorization($configuration, $discovery['authorization_endpoint'], 'login', null, AUTH0_PURPOSE_SIGN_IN, $returnTo);
    header('Location: ' . $authorization['url'], true, 302);
    exit;
} catch (Auth0OidcException $exception) {
    if ($selectionStarted) {
        destroyOwnerSession();
    }
    reportSecurityEvent('oidc_reauthentication', 'denied', ['reason' => $exception->safeReason]);
} catch (Throwable) {
    if ($selectionStarted) {
        destroyOwnerSession();
    }
    reportSecurityEvent('oidc_reauthentication', 'denied', ['reason' => 'dependency_failure']);
}
http_response_code(503);
exit('Sign-in is temporarily unavailable.');
