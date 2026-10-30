<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/error_reporting.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/project_actions.php';
require_once dirname(__DIR__) . '/includes/portfolio_scoped_data.php';
require_once dirname(__DIR__) . '/includes/owner_layout.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_messages.php');
httpRequireMethod(['GET', 'HEAD', 'POST']);

$messages = [];
$selectedMessage = null;
$databaseError = '';
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $messageId = is_string($_POST['id'] ?? null) ? projectActionId($_POST['id']) : null;
        if (($_POST['action'] ?? null) !== 'mark_unread' || $messageId === null) {
            httpAbortHtml(400, 'Invalid request.');
        }
        if (!setAuthorizedMessageRead($database, $context, $messageId, false)) {
            httpAbortHtml(404, 'Message not found.');
        }
        httpRedirect('owner_messages.php');
    }
    $ownerName = (string) ((loadAuthorizedPersonalInfo($database, $context) ?: [])['full_name'] ?? '');
    if (isset($_GET['message'])) {
        $messageId = is_string($_GET['message']) ? projectActionId($_GET['message']) : null;
        $selectedMessage = $messageId === null ? null : findAuthorizedMessage($database, $context, $messageId);
        if ($selectedMessage === null) {
            http_response_code(404);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Opening a message reads it (owner decision, B-03). HEAD never writes.
            setAuthorizedMessageRead($database, $context, (int) $selectedMessage['id'], true);
        }
    }
    $messages = listAuthorizedMessages($database, $context);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_messages.php', 'owner_messages_load');
    http_response_code(503);
    $databaseError = 'Messages are temporarily unavailable.';
}

ownerLayoutStart('Messages', 'messages', true, ['account_name' => $ownerName ?? '']);
require_once dirname(__DIR__) . '/includes/owner_messages_presentation.php';
renderOwnerMessagesPage($messages, $selectedMessage, $databaseError, isset($_GET['message']));
ownerLayoutEnd();
