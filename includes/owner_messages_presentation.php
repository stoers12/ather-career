<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_messages_copy.php';

/** @param list<array<string, mixed>> $messages @param array<string, mixed>|null $selectedMessage */
function renderOwnerMessagesPage(array $messages, ?array $selectedMessage, string $databaseError, bool $selectionRequested): void
{
    $locale = ownerLocale();
    $copy = static fn (string $key): string => ownerEscapeHtml(ownerMessagesCopy($key, $locale));
    ?>
    <div class="owner-messages"><header class="admin-page-header"><div class="admin-page-header-copy"><h1><?php echo $copy('title'); ?></h1><p class="admin-page-description"><?php echo $copy('subtitle'); ?></p></div></header>
    <?php ownerRenderFormFeedback('', array_filter([$databaseError, $selectionRequested && $selectedMessage === null && $databaseError === '' ? 'Message not found.' : ''])); ?>
    <?php if ($messages === [] && $databaseError === ''): ?><div class="empty-state"><strong><?php echo $copy('empty_title'); ?></strong><p><?php echo $copy('empty_hint'); ?></p></div><?php elseif ($messages !== []): ?>
    <div class="owner-inbox">
        <nav class="owner-message-list" id="message-list" aria-label="<?php echo $copy('list'); ?>">
        <?php foreach ($messages as $message): $selected = $selectedMessage !== null && (int) $message['id'] === (int) $selectedMessage['id']; $unread = $message['read_at'] === null; ?>
            <a class="owner-message-item" href="owner_messages.php?message=<?php echo (int) $message['id']; ?>" data-message-state="<?php echo $unread ? 'unread' : 'read'; ?>"<?php echo $selected ? ' aria-current="true"' : ''; ?>><span class="owner-message-state"><?php if ($unread): ?><span class="owner-message-dot" aria-hidden="true"></span><?php endif; ?><?php echo $copy($unread ? 'unread' : 'read'); ?></span><strong dir="auto"><?php echo ownerEscapeHtml((string) $message['name']); ?></strong><span class="owner-message-email" dir="auto"><?php echo ownerEscapeHtml((string) $message['email']); ?></span><span class="owner-message-excerpt" dir="auto"><?php echo ownerEscapeHtml((string) $message['message']); ?></span></a>
        <?php endforeach; ?>
        </nav>
        <?php if ($selectedMessage !== null): ?>
        <?php $mailto = 'mailto:' . rawurlencode((string) $selectedMessage['email']) . '?subject=' . rawurlencode(ownerMessagesCopy('reply_subject', $locale)); ?>
        <article class="owner-message-detail" aria-labelledby="selected-message-title">
            <div class="owner-message-heading"><span><?php echo $copy('message'); ?></span><a class="button-link" href="owner_messages.php#message-list"><?php echo $copy('back'); ?></a></div>
            <h2 id="selected-message-title" dir="auto"><?php echo ownerEscapeHtml((string) $selectedMessage['name']); ?></h2>
            <p class="owner-message-email" dir="auto"><?php echo ownerEscapeHtml((string) $selectedMessage['email']); ?></p>
            <p class="owner-message-body" dir="auto"><?php echo ownerEscapeHtml((string) $selectedMessage['message']); ?></p>
            <footer class="owner-message-actions"><a class="button-primary" href="<?php echo ownerEscapeHtml($mailto); ?>"><?php echo evidenceHubOwnerIcon('owner-mail'); ?><?php echo $copy('reply'); ?></a><form method="POST" action="owner_messages.php" data-owner-form><input type="hidden" name="action" value="mark_unread"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="id" value="<?php echo (int) $selectedMessage['id']; ?>"><button type="submit" class="button-secondary" data-pending-label="<?php echo $copy('marking_unread'); ?>"><?php echo $copy('mark_unread'); ?></button></form></footer>
        </article>
        <?php else: ?><div class="owner-message-placeholder"><span><?php echo evidenceHubOwnerIcon('owner-mail'); ?></span><h2><?php echo $copy('choose_title'); ?></h2><p><?php echo $copy('choose_hint'); ?></p></div><?php endif; ?>
    </div>
    <?php endif; ?>
    </div>
    <?php
}
