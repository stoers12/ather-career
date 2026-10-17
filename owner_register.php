<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth0_oidc.php';
require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/security_events.php';

httpRegisterExceptionBoundary('owner_register.php');
if (!auth0RegistrationIsEnabled()) {
    httpAbortHtml(404, 'Registration is unavailable.');
}
httpRequireMethod(['POST']);
startOwnerSession();
if ($_GET !== [] || $_FILES !== [] || array_keys($_POST) !== ['csrf_token']) {
    httpAbortHtml(403, 'Invalid request.');
}
requireValidCsrfToken($_POST['csrf_token']);
if (array_key_exists(INTERNAL_USER_SESSION_KEY, $_SESSION)) {
    httpAbortHtml(403, 'Use the account-switch or logout option before starting registration.');
}
unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]);
try {
    $limit = consumeRateLimit('oidc_start', rateLimitClientIp(), OIDC_START_RATE_LIMIT_ATTEMPTS, OIDC_START_RATE_LIMIT_WINDOW_SECONDS);
    if (!$limit['allowed']) {
        reportSecurityEvent('rate_limit_denial', 'denied', ['scope' => 'oidc_start', 'reason' => 'threshold_exceeded']);
        header('Retry-After: ' . $limit['retry_after']);
        httpAbortHtml(429, 'Please try again later.');
    }
    $configuration = auth0ConfigurationFromEnvironment();
    $discovery = auth0Discovery($configuration);
    beginFreshOwnerAccountSelectionSession();
    $authorization = beginAuth0Authorization($configuration, $discovery['authorization_endpoint'], null, 'google', AUTH0_PURPOSE_REGISTRATION);
    header('Location: ' . $authorization['url'], true, 302);
    exit;
} catch (Throwable) {
    unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]);
    reportSecurityEvent('oidc_registration_start', 'denied', ['reason' => 'dependency_failure']);
    httpAbortHtml(503, 'Registration is temporarily unavailable.');
}
