<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/project_actions.php';
require_once __DIR__ . '/includes/portfolio_scoped_data.php';
require_once __DIR__ . '/includes/owner_layout.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_messages.php');
httpRequireMethod(['GET', 'HEAD']);

$messages = [];
$selectedMessage = null;
$databaseError = '';
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    if (isset($_GET['message'])) {
        $messageId = is_string($_GET['message']) ? projectActionId($_GET['message']) : null;
        $selectedMessage = $messageId === null ? null : findAuthorizedMessage($database, $context, $messageId);
        if ($selectedMessage === null) {
            http_response_code(404);
        }
    }
    $messages = listAuthorizedMessages($database, $context);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_messages.php', 'owner_messages_load');
    http_response_code(503);
    $databaseError = 'Messages are temporarily unavailable.';
}

ownerLayoutStart('Messages', 'messages');
?>
<div class="admin-page-header"><div class="admin-page-header-copy"><p class="admin-eyebrow">Inbox</p><h1 class="admin-page-title">Contact Messages</h1><p class="admin-page-description">Messages for your current Portfolio.</p></div><div class="admin-page-header-actions"><span class="count-pill"><?php echo count($messages); ?> <?php echo count($messages) === 1 ? 'message' : 'messages'; ?></span></div></div>
<?php ownerRenderFormFeedback('', array_filter([
    $databaseError,
    isset($_GET['message']) && $selectedMessage === null && $databaseError === '' ? 'Message not found.' : '',
])); ?>
<?php if ($selectedMessage !== null): ?>
    <article class="message-card" aria-labelledby="selected-message-title">
        <div class="section-heading-row"><h2 id="selected-message-title"><?php echo ownerEscapeHtml((string) $selectedMessage['name']); ?></h2><a class="button-link" href="owner_messages.php">Back to messages</a></div>
        <p class="message-email"><?php echo ownerEscapeHtml((string) $selectedMessage['email']); ?></p>
        <p class="message-body"><?php echo ownerEscapeHtml((string) $selectedMessage['message']); ?></p>
    </article>
<?php endif; ?>
<?php if ($messages === [] && $databaseError === ''): ?><div class="empty-state admin-empty"><strong>Your inbox is clear</strong><span>New contact messages will appear here.</span></div><?php endif; ?>
<div class="message-list"><?php foreach ($messages as $message): ?><?php if ($selectedMessage !== null && (int) $message['id'] === (int) $selectedMessage['id']) { continue; } ?><article class="message-card"><h2><a href="owner_messages.php?message=<?php echo (int) $message['id']; ?>"><?php echo ownerEscapeHtml((string) $message['name']); ?></a></h2><p class="message-email"><?php echo ownerEscapeHtml((string) $message['email']); ?></p><p class="message-body"><?php echo ownerEscapeHtml((string) $message['message']); ?></p></article><?php endforeach; ?></div>
<?php ownerLayoutEnd();
