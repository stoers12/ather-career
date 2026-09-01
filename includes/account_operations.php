<?php

declare(strict_types=1);

require_once __DIR__ . '/security_events.php';

/** @return array{id: int, account_status: string, authz_version: int}|null */
function accountOperationalState(PDO $database, int $userId): ?array
{
    if ($userId < 1) return null;
    $statement = $database->prepare('SELECT id, account_status, authz_version FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? ['id' => (int) $row['id'], 'account_status' => (string) $row['account_status'], 'authz_version' => (int) $row['authz_version']] : null;
}

/** @return array{id: int, account_status: string, authz_version: int} */
function transitionAccountStatus(PDO $database, int $userId, string $targetStatus, ?callable $afterUpdate = null): array
{
    if ($userId < 1 || !in_array($targetStatus, ['active', 'disabled'], true)) {
        throw new InvalidArgumentException('Account transition target is invalid.');
    }
    $database->beginTransaction();
    try {
        $before = accountOperationalState($database, $userId);
        if ($before === null) throw new RuntimeException('User not found.');
        if ($before['account_status'] !== $targetStatus) {
            $update = $database->prepare(
                'UPDATE users
                 SET account_status = :target_status,
                     authz_version = authz_version + 1
                 WHERE id = :id AND account_status = :expected_status AND authz_version = :expected_version'
            );
            $update->execute([
                'target_status' => $targetStatus,
                'id' => $userId,
                'expected_status' => $before['account_status'],
                'expected_version' => $before['authz_version'],
            ]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Account state changed concurrently.');
            if ($afterUpdate !== null) $afterUpdate();
        }
        $after = accountOperationalState($database, $userId);
        if ($after === null || $after['account_status'] !== $targetStatus
            || ($before['account_status'] !== $targetStatus && $after['authz_version'] !== $before['authz_version'] + 1)) {
            throw new RuntimeException('Account transition verification failed.');
        }
        $database->commit();
        reportSecurityEvent('account_status_transition', 'success', ['internal_user_id' => $userId, 'reason' => $targetStatus]);
        return $after;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) $database->rollBack();
        reportSecurityEvent('account_status_transition', 'failed', ['internal_user_id' => $userId, 'reason' => 'transaction_failure']);
        throw $exception;
    }
}
