<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth0_oidc.php';
require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/legal_documents.php';
require_once dirname(__DIR__) . '/includes/rate_limit.php';
require_once dirname(__DIR__) . '/includes/registration_invitations.php';
require_once dirname(__DIR__) . '/includes/security_events.php';

httpRegisterExceptionBoundary('owner_register.php');
if (!auth0RegistrationIsEnabled()) {
    httpAbortHtml(404, 'Registration is unavailable.');
}
httpRequireMethod(['POST']);
startOwnerSession();
$invitationRequired = auth0RegistrationRequiresInvitation();
$expectedFields = $invitationRequired ? ['csrf_token', 'invitation_code'] : ['csrf_token'];
$postedFields = array_keys($_POST);
sort($postedFields);
// accept_legal is optional until LEGAL_CONSENT_REQUIRED is switched on with the checkbox.
$legalConsent = ($_POST['accept_legal'] ?? null) === '1';
$allowedFields = [$expectedFields, [...$expectedFields, 'accept_legal']];
foreach ($allowedFields as &$fieldSet) {
    sort($fieldSet);
}
unset($fieldSet);
if ($_GET !== [] || $_FILES !== [] || !in_array($postedFields, $allowedFields, true)
    || (array_key_exists('accept_legal', $_POST) && !$legalConsent)) {
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
    if (LEGAL_CONSENT_REQUIRED && !$legalConsent) {
        httpRedirect('/signup.php?legal=required');
    }
    $invitationId = null;
    if ($invitationRequired) {
        $invitationLimit = consumeRateLimit('registration_invitation', rateLimitClientIp(), REGISTRATION_INVITATION_RATE_LIMIT_ATTEMPTS, REGISTRATION_INVITATION_RATE_LIMIT_WINDOW_SECONDS);
        if (!$invitationLimit['allowed']) {
            reportSecurityEvent('rate_limit_denial', 'denied', ['scope' => 'registration_invitation', 'reason' => 'threshold_exceeded']);
            header('Retry-After: ' . $invitationLimit['retry_after']);
            httpAbortHtml(429, 'Please try again later.');
        }
        $invitationId = findUsableRegistrationInvitation(getDatabaseConnection(), $_POST['invitation_code']);
        if ($invitationId === null) {
            // One outcome for malformed, unknown, used, revoked and expired codes.
            reportSecurityEvent('registration_invitation', 'denied', ['reason' => 'invitation_unusable']);
            httpRedirect('/signup.php?invitation=invalid');
        }
    }
    $configuration = auth0ConfigurationFromEnvironment();
    $discovery = auth0Discovery($configuration);
    beginFreshOwnerAccountSelectionSession();
    if ($invitationId !== null) {
        rememberRegistrationInvitation($invitationId);
    }
    if ($legalConsent) {
        rememberRegistrationLegalConsent();
    }
    $authorization = beginAuth0Authorization($configuration, $discovery['authorization_endpoint'], null, 'google', AUTH0_PURPOSE_REGISTRATION);
    header('Location: ' . $authorization['url'], true, 302);
    exit;
} catch (Throwable) {
    unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]);
    reportSecurityEvent('oidc_registration_start', 'denied', ['reason' => 'dependency_failure']);
    httpAbortHtml(503, 'Registration is temporarily unavailable.');
}
