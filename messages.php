<?php
require_once __DIR__ . '/includes/admin_session.php';
require_once __DIR__ . '/includes/csrf.php';

startAdminSession();
requireAdminAuthentication();

require_once __DIR__ . '/config/database.php';

function escapeMessageHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatMessageTimestamp(string $value): string
{
    try {
        return (new DateTimeImmutable($value))->format('M j, Y · g:i A');
    } catch (Exception $exception) {
        return $value;
    }
}

$messages = [];
$databaseError = '';
$activePage = 'messages';

try {
    $database = getDatabaseConnection();
    $messageStatement = $database->prepare(
        'SELECT name, email, message, created_at
         FROM messages
         ORDER BY created_at DESC, id DESC'
    );
    $messageStatement->execute();
    $messages = $messageStatement->fetchAll();
} catch (PDOException $exception) {
    $databaseError = 'Messages are temporarily unavailable.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - My Portfolio</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js" defer></script>
</head>
<body>
    <div class="admin-layout">
        <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
        <main class="admin-content">
        <section id="contact">
            <div class="admin-page-header">
                <div class="admin-page-header-copy">
                    <p class="admin-eyebrow">Inbox</p>
                    <h1 class="admin-page-title">Contact Messages</h1>
                    <p class="admin-page-description">Messages received from the public portfolio contact form.</p>
                </div>
                <div class="admin-page-header-actions">
                    <span class="count-pill"><?php echo count($messages); ?> <?php echo count($messages) === 1 ? 'message' : 'messages'; ?></span>
                </div>
            </div>
            <?php if ($databaseError !== ''): ?><p class="status-message error" role="alert"><?php echo escapeMessageHtml($databaseError); ?></p><?php endif; ?>

            <?php if ($messages === [] && $databaseError === ''): ?><div class="empty-state admin-empty"><strong>Your inbox is clear</strong><span>New contact messages will appear here.</span><a class="button-link" href="index.php#contact" target="_blank" rel="noopener">Preview Portfolio</a></div><?php endif; ?>
            <div class="message-list"><?php foreach ($messages as $message): ?>
                <article class="message-card">
                    <h2 dir="auto"><?php echo escapeMessageHtml($message['name']); ?></h2>
                    <p class="message-email"><a dir="ltr" href="mailto:<?php echo escapeMessageHtml($message['email']); ?>"><?php echo escapeMessageHtml($message['email']); ?></a></p>
                    <p class="message-body" dir="auto"><?php echo escapeMessageHtml($message['message']); ?></p>
                    <p class="message-meta"><time datetime="<?php echo escapeMessageHtml($message['created_at']); ?>"><?php echo escapeMessageHtml(formatMessageTimestamp($message['created_at'])); ?></time></p>
                </article>
            <?php endforeach; ?></div>
        </section>
        </main>
    </div>
</body>
</html>
