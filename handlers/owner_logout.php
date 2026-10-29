<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/auth0_logout.php';

startOwnerSession();

httpRegisterExceptionBoundary('owner_logout.php');
httpRequireMethod(['POST']);

if ($_GET !== [] || $_FILES !== [] || array_keys($_POST) !== ['csrf_token']) {
    httpAbortHtml(403, 'Invalid request.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);
// Retire authentication, readiness, CSRF and pending transactions before any
// remote configuration work. A retirement failure must not claim local success.
destroyOwnerSession();

try {
    $logoutUrl = auth0LogoutUrl();
} catch (Throwable) {
    renderOwnerPartialLogout();
}

// This initiates browser logout; neither this redirect nor the return page is
// evidence that Auth0 completed it. The return page never starts login itself.
httpSetHtmlResponse(303);
header('Cache-Control: private, no-store');
header('Location: ' . $logoutUrl, true, 303);
exit;
