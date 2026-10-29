<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/onboarding_drafts.php';
require_once dirname(__DIR__) . '/includes/onboarding_draft_http.php';
require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';

runtimeStartRequest('owner_onboarding_draft.php');
set_exception_handler(static function (Throwable $error): never { onboardingDraftHttpUnavailable(); });
// Session/filesystem warnings may include paths or session identifiers. Convert
// active warnings to a safe exception before PHP's default logger sees them.
set_error_handler(static function (int $severity): bool {
    if ((error_reporting() & $severity) === 0) { return false; }
    throw new ErrorException('Draft request unavailable.');
});
// The common JSON helper and PHP session cache limiter may both set caching;
// enforce this private route's final policy after those headers are prepared.
header_register_callback(static function (): void { header('Cache-Control: private, no-store'); });
if (!onboardingDraftHttpEnabled()) {
    onboardingDraftHttpError(404, 'unavailable', 'Draft is unavailable.');
}
// HTTP method tokens are case-sensitive. Use the same exact token for method
// admission and POST CSRF validation; never normalize one without the other.
if (!in_array($_SERVER['REQUEST_METHOD'] ?? null, ['GET', 'POST'], true)) {
    header('Allow: ' . httpAllowHeader(['GET', 'POST']));
    onboardingDraftHttpError(405, 'method_not_allowed', 'Method not allowed.');
}

try {
    startOwnerSession();
    if (!array_key_exists(INTERNAL_USER_SESSION_KEY, $_SESSION)) {
        onboardingDraftHttpError(401, 'authentication_required', 'Authentication required.');
    }
    $database = getDatabaseConnection();
    $user = requireAuthenticatedUser($database);
    if (($_SERVER['QUERY_STRING'] ?? '') !== '' || $_FILES !== []
        || isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        onboardingDraftHttpError(400, 'invalid_input', 'Invalid draft request.');
    }
    if (($_SERVER['HTTP_CONTENT_ENCODING'] ?? '') !== '') {
        onboardingDraftHttpError(415, 'unsupported_media_type', 'Unsupported request encoding.');
    }
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST') {
        if (!isValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            onboardingDraftHttpError(403, 'invalid_csrf', 'Invalid request token.');
        }
        if (preg_match('/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/iD', (string) ($_SERVER['CONTENT_TYPE'] ?? '')) !== 1) {
            onboardingDraftHttpError(415, 'unsupported_media_type', 'Use UTF-8 application/json.');
        }
    }
    if ($_POST !== []) { onboardingDraftHttpError(400, 'invalid_input', 'Invalid draft request.'); }
    $length = $_SERVER['CONTENT_LENGTH'] ?? null;
    if ($length !== null && (!ctype_digit((string) $length) || (float) $length > ONBOARDING_DRAFT_HTTP_MAX_BYTES)) {
        onboardingDraftHttpError(413, 'request_too_large', 'Draft request is too large.');
    }
    $raw = file_get_contents('php://input', false, null, 0, ONBOARDING_DRAFT_HTTP_MAX_BYTES + 1);
    if (!is_string($raw)) { onboardingDraftHttpUnavailable(); }
    if (strlen($raw) > ONBOARDING_DRAFT_HTTP_MAX_BYTES) {
        onboardingDraftHttpError(413, 'request_too_large', 'Draft request is too large.');
    }
    if ($method === 'GET') {
        if ($raw !== '') { onboardingDraftHttpError(400, 'invalid_input', 'Invalid draft request.'); }
        httpJsonResponse(200, ['success' => true, 'draft' => readOnboardingDraft($database, $user), 'csrf_token' => getCsrfToken()]);
    }
    $input = onboardingDraftHttpInput($raw);
    $draft = saveOnboardingDraft($database, $user, $input->expectedVersion, $input->operationId, $input->section, (array) $input->fields);
    httpJsonResponse(200, ['success' => true, 'draft' => $draft]);
} catch (AuthorizationDeniedException $error) {
    if ($error->getPrevious() instanceof PDOException) { onboardingDraftHttpUnavailable(); }
    onboardingDraftHttpError(403, 'access_denied', 'Draft access denied.');
} catch (OnboardingDraftConflictException) {
    onboardingDraftHttpError(409, 'draft_conflict', 'Draft changed. Resume before saving again.');
} catch (InvalidArgumentException | JsonException) {
    onboardingDraftHttpError(400, 'invalid_input', 'Invalid draft request.');
} catch (Throwable) {
    onboardingDraftHttpUnavailable();
}
