<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/onboarding_attestation_presentation.php';
require_once dirname(__DIR__) . '/includes/onboarding_attestation_http.php';
require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';

runtimeStartRequest('owner_onboarding_attestation.php');
set_exception_handler(static function (Throwable $error): never { onboardingAttestationHttpUnavailable(); });
set_error_handler(static function (int $severity): bool {
    if ((error_reporting() & $severity) === 0) { return false; }
    throw new ErrorException('Attestation request unavailable.');
});
header_register_callback(static function (): void { header('Cache-Control: private, no-store'); });
if (!onboardingAttestationHttpEnabled()) {
    onboardingAttestationHttpError(404, 'unavailable', 'Attestations are unavailable.');
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? null, ['GET', 'POST'], true)) {
    header('Allow: ' . httpAllowHeader(['GET', 'POST']));
    onboardingAttestationHttpError(405, 'method_not_allowed', 'Method not allowed.');
}

try {
    startOwnerSession();
    // Even an unsuccessful new GET retires the previous presentation.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { unset($_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY]); }
    if (!array_key_exists(INTERNAL_USER_SESSION_KEY, $_SESSION)) {
        onboardingAttestationHttpError(401, 'authentication_required', 'Authentication required.');
    }
    $database = getDatabaseConnection();
    $user = requireAuthenticatedUser($database);
    if (($_SERVER['QUERY_STRING'] ?? '') !== '' || $_FILES !== [] || $_POST !== []
        || isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        onboardingAttestationHttpError(400, 'invalid_input', 'Invalid attestation request.');
    }
    if (($_SERVER['HTTP_CONTENT_ENCODING'] ?? '') !== '') {
        onboardingAttestationHttpError(415, 'unsupported_media_type', 'Unsupported request encoding.');
    }
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST') {
        if (!isValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            onboardingAttestationHttpError(403, 'invalid_csrf', 'Invalid request token.');
        }
        if (preg_match('/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/iD', (string) ($_SERVER['CONTENT_TYPE'] ?? '')) !== 1) {
            onboardingAttestationHttpError(415, 'unsupported_media_type', 'Use UTF-8 application/json.');
        }
    }
    $length = $_SERVER['CONTENT_LENGTH'] ?? null;
    if ($length !== null && (!ctype_digit((string) $length) || (float) $length > ONBOARDING_ATTESTATION_HTTP_MAX_BYTES)) {
        onboardingAttestationHttpError(413, 'request_too_large', 'Attestation request is too large.');
    }
    $raw = file_get_contents('php://input', false, null, 0, ONBOARDING_ATTESTATION_HTTP_MAX_BYTES + 1);
    if (!is_string($raw)) { onboardingAttestationHttpUnavailable(); }
    if (strlen($raw) > ONBOARDING_ATTESTATION_HTTP_MAX_BYTES) {
        onboardingAttestationHttpError(413, 'request_too_large', 'Attestation request is too large.');
    }
    if ($method === 'GET' && $raw !== '') {
        onboardingAttestationHttpError(400, 'invalid_input', 'Invalid attestation request.');
    }
    try {
        $input = $method === 'POST' ? onboardingAttestationHttpInput($raw) : null;
    } catch (InvalidArgumentException | JsonException) {
        onboardingAttestationHttpError(400, 'invalid_input', 'Invalid attestation request.');
    }
    $presentation = resolveOnboardingAttestationPresentation();
    if ($presentation === null) {
        unset($_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY]);
        onboardingAttestationHttpError(503, 'policies_unavailable', 'Required documents are unavailable. Review again when they are available.');
    }
    $binding = onboardingAttestationPresentationBinding($user, $presentation['bundle']);
    if ($method === 'GET') {
        $record = readOnboardingAttestations($database, $user, $presentation['bundle']);
        $token = bin2hex(random_bytes(32));
        $csrf = getCsrfToken();
        $_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY] = ['binding' => $binding, 'token' => $token];
        httpJsonResponse(200, ['success' => true, 'documents' => $presentation['documents'],
            'evidence' => onboardingAttestationEvidenceStatus($record), 'csrf_token' => $csrf, 'presentation_token' => $token]);
    }
    $saved = $_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY] ?? null;
    if (!is_array($saved) || ($saved['binding'] ?? null) !== $binding || !is_string($saved['token'] ?? null)
        || !hash_equals($saved['token'], $input->presentation_token)) {
        unset($_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY]);
        onboardingAttestationHttpError(409, 'presentation_stale', 'Review the current documents again before submitting.');
    }
    $record = recordOnboardingAttestations($database, $user, $presentation['bundle'], [
        'age_18_or_older' => $input->age_18_or_older, 'terms_accepted' => $input->terms_accepted,
        'privacy_notice_acknowledged' => $input->privacy_notice_acknowledged,
    ]);
    httpJsonResponse(200, ['success' => true, 'evidence' => onboardingAttestationEvidenceStatus($record)]);
} catch (AuthorizationDeniedException $error) {
    if ($error->getPrevious() instanceof PDOException) { onboardingAttestationHttpUnavailable(); }
    onboardingAttestationHttpError(403, 'access_denied', 'Attestation access denied.');
} catch (Throwable) {
    onboardingAttestationHttpUnavailable();
}
