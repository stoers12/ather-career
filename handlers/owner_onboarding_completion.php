<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/onboarding_completion.php';
require_once dirname(__DIR__) . '/includes/onboarding_completion_http.php';
require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';

runtimeStartRequest('owner_onboarding_completion.php');
set_exception_handler(static function (Throwable $error): never { onboardingCompletionHttpUnavailable(); });
set_error_handler(static function (int $severity): bool {
    if ((error_reporting() & $severity) === 0) { return false; }
    throw new ErrorException('Completion request unavailable.');
});
header_register_callback(static function (): void { header('Cache-Control: private, no-store'); });
if (!onboardingCompletionHttpEnabled()) {
    onboardingCompletionHttpError(404, 'unavailable', 'Completion is unavailable.');
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? null, ['GET', 'POST'], true)) {
    header('Allow: ' . httpAllowHeader(['GET', 'POST']));
    onboardingCompletionHttpError(405, 'method_not_allowed', 'Method not allowed.');
}

try {
    startOwnerSession();
    if (!array_key_exists(INTERNAL_USER_SESSION_KEY, $_SESSION)) {
        onboardingCompletionHttpError(401, 'authentication_required', 'Authentication required.');
    }
    $database = getDatabaseConnection();
    $user = requireAuthenticatedUser($database);
    if (($_SERVER['QUERY_STRING'] ?? '') !== '' || $_FILES !== [] || $_POST !== []
        || isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        onboardingCompletionHttpError(400, 'invalid_input', 'Invalid completion request.');
    }
    if (($_SERVER['HTTP_CONTENT_ENCODING'] ?? '') !== '') {
        onboardingCompletionHttpError(415, 'unsupported_media_type', 'Unsupported request encoding.');
    }
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST') {
        if (!isValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            onboardingCompletionHttpError(403, 'invalid_csrf', 'Invalid request token.');
        }
        if (preg_match('/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/iD', (string) ($_SERVER['CONTENT_TYPE'] ?? '')) !== 1) {
            onboardingCompletionHttpError(415, 'unsupported_media_type', 'Use UTF-8 application/json.');
        }
    }
    $length = $_SERVER['CONTENT_LENGTH'] ?? null;
    if ($length !== null && (!ctype_digit((string) $length) || (float) $length > ONBOARDING_COMPLETION_HTTP_MAX_BYTES)) {
        onboardingCompletionHttpError(413, 'request_too_large', 'Completion request is too large.');
    }
    $raw = file_get_contents('php://input', false, null, 0, ONBOARDING_COMPLETION_HTTP_MAX_BYTES + 1);
    if (!is_string($raw)) { onboardingCompletionHttpUnavailable(); }
    if (strlen($raw) > ONBOARDING_COMPLETION_HTTP_MAX_BYTES) {
        onboardingCompletionHttpError(413, 'request_too_large', 'Completion request is too large.');
    }
    if ($method === 'GET' && $raw !== '') {
        onboardingCompletionHttpError(400, 'invalid_input', 'Invalid completion request.');
    }
    try {
        $input = $method === 'POST' ? onboardingCompletionHttpInput($raw) : null;
    } catch (InvalidArgumentException | JsonException) {
        onboardingCompletionHttpError(400, 'invalid_input', 'Invalid completion request.');
    }
    // Receipt recognition and all new-creation prerequisites belong to the core.
    // In particular, never put a policy/readiness/review check before recovery.
    if ($method === 'GET') {
        $receipt = readOnboardingCompletion($database, $user);
        httpJsonResponse(200, ['success' => true, 'receipt' => $receipt, 'csrf_token' => getCsrfToken()]);
    }
    if ($input->action === 'review') {
        $review = prepareOnboardingCompletionReview($database, $user, $input->expectedVersion);
        httpJsonResponse(200, ['success' => true, 'review' => $review]);
    }
    $intent = (array) $input;
    unset($intent['action']); // Validated dispatch only; preserve all five intent values.
    $receipt = completeOnboarding($database, $user, $intent);
    httpJsonResponse(200, ['success' => true, 'receipt' => $receipt]);
} catch (AuthorizationDeniedException $error) {
    if ($error->getPrevious() instanceof PDOException) { onboardingCompletionHttpUnavailable(); }
    onboardingCompletionHttpError(403, 'access_denied', 'Completion access denied.');
} catch (OnboardingCompletionConflictException) {
    onboardingCompletionHttpError(409, 'review_conflict', 'Read your completion receipt. If it is missing, load a fresh review before confirming.');
} catch (OnboardingCompletionUnavailableException) {
    onboardingCompletionHttpError(503, 'prerequisites_unavailable', 'Required documents are unavailable. You can still read your completion receipt.');
} catch (OnboardingCompletionUncertainException) {
    onboardingCompletionHttpError(503, 'outcome_uncertain', 'We could not confirm the result. Read your completion receipt before deciding what to do next.');
} catch (InvalidArgumentException | PublicLifecycleValidationException) {
    // Transport types were already validated. These are saved completion fields,
    // not malformed input JSON. Corrupt draft decoding remains a dependency error.
    onboardingCompletionHttpError(422, 'saved_fields_invalid', 'Correct the saved draft fields, then load a fresh review.');
} catch (Throwable) {
    onboardingCompletionHttpUnavailable();
}
