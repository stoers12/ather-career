<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/account_operations.php';

$options = getopt('', ['user-id:', 'status:', 'confirm-user-id:']);
$userId = filter_var($options['user-id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$confirmation = filter_var($options['confirm-user-id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$status = $options['status'] ?? null;
if ($userId === false || $confirmation !== $userId || !is_string($status) || !in_array($status, ['active', 'disabled'], true)) {
    fwrite(STDERR, "Usage: php scripts/set-account-status.php --user-id N --status active|disabled --confirm-user-id N\n");
    exit(2);
}

try {
    $database = getDatabaseConnection();
    $before = accountOperationalState($database, $userId);
    if ($before === null) throw new RuntimeException('User not found.');
    fwrite(STDOUT, "Current: user_id={$before['id']} status={$before['account_status']} authz_version={$before['authz_version']}\n");
    $after = transitionAccountStatus($database, $userId, $status);
    fwrite(STDOUT, "Result: user_id={$after['id']} status={$after['account_status']} authz_version={$after['authz_version']}\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Account transition failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
