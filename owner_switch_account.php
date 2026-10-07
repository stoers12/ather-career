<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth0_oidc.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/security_events.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_switch_account.php');
httpRequireMethod(['POST']);
if ($_GET !== [] || $_FILES !== [] || array_keys($_POST) !== ['csrf_token']
    || !is_string($_POST['csrf_token']) || $_POST['csrf_token'] === '') {
    httpAbortHtml(403, 'Invalid request.');
}
requireValidCsrfToken($_POST['csrf_token']);

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
    $authorization = beginAuth0Authorization($configuration, $discovery['authorization_endpoint'], 'select_account');
    header('Location: ' . $authorization['url'], true, 302);
    exit;
} catch (Auth0OidcException $exception) {
    if ($selectionStarted) {
        destroyOwnerSession();
    }
    reportSecurityEvent('oidc_account_selection', 'denied', ['reason' => $exception->safeReason]);
} catch (AuthorizationDeniedException) {
    httpAbortHtml(403, 'You are not authorized to access this resource.');
} catch (Throwable) {
    if ($selectionStarted) {
        destroyOwnerSession();
    }
    reportSecurityEvent('oidc_account_selection', 'denied', ['reason' => 'dependency_failure']);
}
http_response_code(503);
exit('Account selection is temporarily unavailable.');
