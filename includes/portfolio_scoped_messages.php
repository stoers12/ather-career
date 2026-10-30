<?php

declare(strict_types=1);

// Owner-scoped contact message data access. Loaded by portfolio_scoped_data.php.
// read_at is NULL until the Owner opens the message (migration 025).

/** @return list<array<string, mixed>> */
function listAuthorizedMessages(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT id, name, email, message, created_at, read_at
         FROM messages
         WHERE recipient_portfolio_id = :authorized_portfolio_id
         ORDER BY created_at DESC, id DESC'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed>|null */
function findAuthorizedMessage(PDO $database, AuthorizedPortfolioContext $context, int $messageId): ?array
{
    if ($messageId < 1) {
        return null;
    }

    $statement = $database->prepare(
        'SELECT id, name, email, message, created_at, read_at
         FROM messages
         WHERE id = :resource_id
           AND recipient_portfolio_id = :authorized_portfolio_id
         LIMIT 1'
    );
    $statement->execute([
        'resource_id' => $messageId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);
    $message = $statement->fetch(PDO::FETCH_ASSOC);

    return $message === false ? null : $message;
}

/**
 * Marks one of this Owner's messages read or unread. Marking read again keeps
 * the first opening time. False when the message is not this Owner's.
 */
function setAuthorizedMessageRead(PDO $database, AuthorizedPortfolioContext $context, int $messageId, bool $read): bool
{
    // rowCount() counts changed rows only, so ownership is checked before the update.
    if (findAuthorizedMessage($database, $context, $messageId) === null) {
        return false;
    }
    $statement = $database->prepare(
        'UPDATE messages SET read_at = ' . ($read ? 'COALESCE(read_at, CURRENT_TIMESTAMP)' : 'NULL') . '
         WHERE id = :resource_id
           AND recipient_portfolio_id = :authorized_portfolio_id'
    );
    $statement->execute([
        'resource_id' => $messageId,
        'authorized_portfolio_id' => $context->portfolioId,
    ]);

    return true;
}

function countAuthorizedUnreadMessages(PDO $database, AuthorizedPortfolioContext $context): int
{
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM messages
         WHERE recipient_portfolio_id = :authorized_portfolio_id
           AND read_at IS NULL'
    );
    $statement->execute(['authorized_portfolio_id' => $context->portfolioId]);

    return (int) $statement->fetchColumn();
}
