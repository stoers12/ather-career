<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/account_data_rights.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/error_reporting.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_dashboard_copy.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/rate_limit.php';

// "Download my data" (L-03; docs/contracts/account-data-rights.md). Also open
// to an account scheduled for deletion, so it can still take its data.
startOwnerSession();
httpRegisterExceptionBoundary('owner_data_export.php');
httpRequireMethod(['POST']);
if ($_GET !== [] || $_FILES !== [] || array_keys($_POST) !== ['csrf_token']
    || !is_string($_POST['csrf_token']) || $_POST['csrf_token'] === '') {
    httpAbortHtml(403, 'Invalid request.');
}
requireValidCsrfToken($_POST['csrf_token']);

try {
    $database = getDatabaseConnection();
    $user = requireOwnerAuthenticatedUser($database);
    $returnPage = accountDeletionSchedule($database, $user->userId) === null ? '/owner_settings.php' : '/owner_account_deletion.php';
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_data_export.php', 'owner_data_export_load');
    httpAbortHtml(503, 'Your data cannot be exported right now.');
}
if (!accountSessionIsRecentlyAuthenticated()) {
    httpRedirect($returnPage . '?data=reauthenticate' . ($returnPage === '/owner_settings.php' ? '#data' : ''));
}
$limit = consumeRateLimit('data_export', 'user-' . $user->userId, ACCOUNT_EXPORT_RATE_LIMIT_ATTEMPTS, ACCOUNT_EXPORT_RATE_LIMIT_WINDOW_SECONDS);
if (!$limit['allowed']) {
    reportSecurityEvent('rate_limit_denial', 'denied', ['scope' => 'data_export', 'reason' => 'threshold_exceeded']);
    http_response_code(429);
    header('Retry-After: ' . $limit['retry_after']);
    exit('Please try again later.');
}

// The session is not needed while the archive streams; release its lock.
session_write_close();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="ather-data-' . gmdate('Y-m-d') . '.zip"');
header('Cache-Control: private, no-store');
$output = fopen('php://output', 'wb');
try {
    if ($output === false) {
        throw new RuntimeException('Export output is unavailable.');
    }
    writeAccountExport($database, $user->userId, ownerLocale(), $output);
    reportSecurityEvent('account_data_export', 'success', ['internal_user_id' => $user->userId]);
} catch (Throwable $exception) {
    // Headers are already sent: the download ends incomplete and the archive is unreadable.
    reportApplicationError($exception, 'owner_data_export.php', 'owner_data_export_write');
    reportSecurityEvent('account_data_export', 'failed', ['internal_user_id' => $user->userId]);
}
exit;
