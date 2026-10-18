<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/onboarding_draft_http.php';
require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/authorization.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/onboarding_presentation.php';
require_once __DIR__ . '/config/database.php';
runtimeStartRequest('owner_onboarding_editor.php');
set_exception_handler(static function (Throwable $error): never { httpAbortHtml(503, 'Draft editor is temporarily unavailable.'); });
set_error_handler(static function (int $severity): bool {
    if ((error_reporting() & $severity) === 0) { return false; }
    throw new ErrorException('Draft editor unavailable.');
});
header_register_callback(static function (): void { header('Cache-Control: private, no-store'); });
if (!onboardingDraftHttpEnabled()) { httpAbortHtml(404, 'Draft editor is unavailable.'); }
httpRequireMethod(['GET']);
try {
    startOwnerSession();
    if (!array_key_exists(INTERNAL_USER_SESSION_KEY, $_SESSION)) { httpAbortHtml(401, 'Authentication required.'); }
    requireAuthenticatedUser(getDatabaseConnection());
    foreach ($_GET as $key => $value) {
        if (($key !== 'lang' && $key !== 'step') || !is_string($value)) { httpAbortHtml(400, 'Invalid editor request.'); }
    }
    if (isset($_GET['lang']) && !in_array($_GET['lang'], ['ar', 'en'], true)) { httpAbortHtml(400, 'Invalid editor language.'); }
    if (isset($_GET['step']) && !in_array($_GET['step'], ['1', '2', '3', '4', '5'], true)) { httpAbortHtml(400, 'Invalid editor section.'); }
    $locale = $_GET['lang'] ?? 'ar';
    $step = isset($_GET['step']) ? (int) $_GET['step'] : 2;
    renderOnboardingPresentation($locale, $step, true, getCsrfToken());
} catch (AuthorizationDeniedException $error) {
    if ($error->getPrevious() instanceof PDOException) { httpAbortHtml(503, 'Draft editor is temporarily unavailable.'); }
    httpAbortHtml(403, 'Draft access denied.');
} catch (Throwable) { httpAbortHtml(503, 'Draft editor is temporarily unavailable.'); }
