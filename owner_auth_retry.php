<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth0_oidc.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_auth_recovery.php';
require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/security_events.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_auth_retry.php');
httpRequireMethod(['POST']);
requireValidCsrfToken($_POST['csrf_token'] ?? null);
if ($_GET !== [] || array_keys($_POST) !== ['csrf_token']) {
    httpAbortHtml(403, 'Invalid request.');
}
if (currentInternalUserSession() !== null) {
    httpAbortHtml(403, 'This action requires a signed-out session.');
}

$selectionStarted = false;
try {
    $limit = consumeRateLimit('oidc_start', rateLimitClientIp(), OIDC_START_RATE_LIMIT_ATTEMPTS, OIDC_START_RATE_LIMIT_WINDOW_SECONDS);
    if (!$limit['allowed']) {
        reportSecurityEvent('rate_limit_denial', 'denied', ['scope' => 'oidc_start', 'reason' => 'threshold_exceeded']);
        header('Retry-After: ' . $limit['retry_after']);
        renderOwnerAuthRecoveryPage(false, 429);
    }

    $configuration = auth0ConfigurationFromEnvironment();
    $discovery = auth0Discovery($configuration);
    $selectionStarted = true;
    beginFreshOwnerAccountSelectionSession();
    $authorization = beginAuth0Authorization($configuration, $discovery['authorization_endpoint'], 'login');
    header('Location: ' . $authorization['url'], true, 302);
    exit;
} catch (Auth0OidcException $exception) {
    reportSecurityEvent('oidc_account_selection_retry', 'denied', ['reason' => $exception->safeReason]);
} catch (Throwable) {
    reportSecurityEvent('oidc_account_selection_retry', 'denied', ['reason' => 'dependency_failure']);
}
if ($selectionStarted) {
    beginFreshOwnerAccountSelectionSession();
}
renderOwnerAuthRecoveryPage(false, 503);
