<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/auth0_oidc.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/security_events.php';

const OIDC_START_RATE_LIMIT_ATTEMPTS = 5;
const OIDC_START_RATE_LIMIT_WINDOW_SECONDS = 300;

startOwnerSession();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

try {
    $limit = consumeRateLimit('oidc_start', rateLimitClientIp(), OIDC_START_RATE_LIMIT_ATTEMPTS, OIDC_START_RATE_LIMIT_WINDOW_SECONDS);
    if (!$limit['allowed']) {
        reportSecurityEvent('rate_limit_denial', 'denied', ['scope' => 'oidc_start', 'reason' => 'threshold_exceeded']);
        http_response_code(429);
        header('Retry-After: ' . $limit['retry_after']);
        exit('Please try again later.');
    }
    $configuration = auth0ConfigurationFromEnvironment();
    $discovery = auth0Discovery($configuration);
    $authorization = beginAuth0Authorization($configuration, $discovery['authorization_endpoint']);
    header('Location: ' . $authorization['url'], true, 302);
    exit;
} catch (Auth0OidcException $exception) {
    reportSecurityEvent('oidc_start', 'denied', ['reason' => $exception->safeReason]);
    http_response_code(503);
    exit('Sign-in is temporarily unavailable.');
} catch (Throwable $exception) {
    reportSecurityEvent('oidc_start', 'denied', ['reason' => 'dependency_failure']);
    http_response_code(503);
    exit('Sign-in is temporarily unavailable.');
}
