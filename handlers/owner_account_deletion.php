<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/account_data_rights.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/error_reporting.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_dashboard_copy.php';
require_once dirname(__DIR__) . '/includes/owner_data_rights_presentation.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/owner_session.php';

// Account deletion (L-03; docs/contracts/account-data-rights.md).
// POST action=request schedules it from Settings; the owner is then signed out
// everywhere. GET shows a scheduled account its date and the cancel button;
// POST action=cancel cancels it.
startOwnerSession();
httpRegisterExceptionBoundary('owner_account_deletion.php');
httpRequireMethod(['GET', 'HEAD', 'POST']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $method === 'POST' ? ($_POST['action'] ?? null) : null;
if ($method === 'POST') {
    $keys = array_keys($_POST);
    sort($keys);
    $expected = match ($action) {
        'request' => ['action', 'confirmation', 'csrf_token'],
        'cancel' => ['action', 'csrf_token'],
        default => null,
    };
    if ($_GET !== [] || $_FILES !== [] || $expected === null || $keys !== $expected
        || !is_string($_POST['csrf_token']) || $_POST['csrf_token'] === '') {
        httpAbortHtml(403, 'Invalid request.');
    }
    requireValidCsrfToken($_POST['csrf_token']);
}

try {
    $database = getDatabaseConnection();
    if ($action === 'request') {
        // Only an owner with full workspace access reaches this, never one already scheduled.
        requireOwnerPortfolioContext($database);
        try {
            requestAccountDeletion($database, requireAuthenticatedUser($database), $_POST['confirmation']);
        } catch (AccountDataRightsException $exception) {
            httpRedirect('/owner_settings.php?data=' . ($exception->reason === 'reauthentication_required' ? 'reauthenticate' : 'confirmation') . '#data');
        }
        destroyOwnerSession();
        httpRedirect('/signin.php?account=deletion_scheduled');
    }

    $user = requireOwnerAuthenticatedUser($database);
    if ($action === 'cancel') {
        try {
            cancelAccountDeletion($database, $user);
        } catch (AccountDataRightsException) {
            // Not scheduled (or no longer cancellable): the journey decides where the owner belongs.
        }
        httpRedirect(ownerJourneyDestination($database, $user) === '/owner.php' ? '/owner.php?account=deletion_cancelled' : '/owner.php');
    }

    $schedule = accountDeletionSchedule($database, $user->userId);
    if ($schedule === null) {
        httpRedirect('/owner_settings.php#data');
    }
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_account_deletion.php', 'owner_account_deletion_' . ($action ?? 'load'));
    httpAbortHtml(503, 'Account deletion is temporarily unavailable.');
} catch (AuthorizationDeniedException) {
    ownerAuthorizationDenied();
}

$locale = ownerLocale();
ownerPersistLocale($locale);
httpSetHtmlResponse(200);
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
if ($method !== 'HEAD') {
    renderOwnerAccountDeletionPage(ownerAccountDeletionPageData($schedule, $locale, $_GET['data'] ?? null));
}
