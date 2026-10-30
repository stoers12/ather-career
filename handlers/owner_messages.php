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
?>
<div class="admin-page-header"><div class="admin-page-header-copy"><p class="admin-eyebrow"><?php echo ownerEscapeHtml(ownerText('Inbox')); ?></p><h1 class="admin-page-title"><?php echo ownerEscapeHtml(ownerText('Contact Messages')); ?></h1><p class="admin-page-description"><?php echo ownerEscapeHtml(ownerText('Messages for your current Portfolio.')); ?></p></div><div class="admin-page-header-actions"><span class="count-pill"><?php echo count($messages); ?> <?php echo count($messages) === 1 ? ownerText('message') : ownerText('messages'); ?></span></div></div>
<?php ownerRenderFormFeedback('', array_filter([
    $databaseError,
    isset($_GET['message']) && $selectedMessage === null && $databaseError === '' ? 'Message not found.' : '',
])); ?>
<?php if ($selectedMessage !== null): ?>
    <article class="message-card" aria-labelledby="selected-message-title">
        <div class="section-heading-row"><h2 id="selected-message-title"><?php echo ownerEscapeHtml((string) $selectedMessage['name']); ?></h2><a class="button-link" href="owner_messages.php"><?php echo ownerEscapeHtml(ownerText('Back to messages')); ?></a></div>
        <p class="message-email"><?php echo ownerEscapeHtml((string) $selectedMessage['email']); ?></p>
        <p class="message-body"><?php echo ownerEscapeHtml((string) $selectedMessage['message']); ?></p>
        <form method="POST" action="owner_messages.php" data-owner-form><input type="hidden" name="action" value="mark_unread"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="id" value="<?php echo (int) $selectedMessage['id']; ?>"><button type="submit" class="button-secondary" data-pending-label="<?php echo ownerEscapeHtml(ownerText('Marking as unread…')); ?>"><?php echo ownerEscapeHtml(ownerText('Mark as unread')); ?></button></form>
    </article>
<?php endif; ?>
<?php if ($messages === [] && $databaseError === ''): ?><div class="empty-state admin-empty"><strong><?php echo ownerEscapeHtml(ownerText('Your inbox is clear')); ?></strong><span><?php echo ownerEscapeHtml(ownerText('New contact messages will appear here.')); ?></span></div><?php endif; ?>
<div class="message-list"><?php foreach ($messages as $message): ?><?php if ($selectedMessage !== null && (int) $message['id'] === (int) $selectedMessage['id']) { continue; } ?><article class="message-card" data-message-state="<?php echo $message['read_at'] === null ? 'unread' : 'read'; ?>"><h2><a href="owner_messages.php?message=<?php echo (int) $message['id']; ?>"><?php echo ownerEscapeHtml((string) $message['name']); ?></a><?php if ($message['read_at'] === null): ?> <span class="count-pill"><?php echo ownerEscapeHtml(ownerText('New')); ?></span><?php endif; ?></h2><p class="message-email"><?php echo ownerEscapeHtml((string) $message['email']); ?></p><p class="message-body"><?php echo ownerEscapeHtml((string) $message['message']); ?></p></article><?php endforeach; ?></div>
<?php ownerLayoutEnd();
