<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/account_data_rights.php';

// Permanent deletion of accounts whose 30-day grace period has ended (L-03).
// Run it daily from the server's scheduler; every step can be retried, so a
// failed account is simply tried again on the next run.
$usage = "Usage:\n"
    . "  php scripts/ops/purge-deleted-accounts.php run [--limit 25]\n"
    . "  php scripts/ops/purge-deleted-accounts.php list\n";
$arguments = is_array($_SERVER['argv'] ?? null) ? array_values($_SERVER['argv']) : [];
$command = $arguments[1] ?? '';
$limit = 25;
if (count($arguments) > 2) {
    $value = preg_match('/^--limit=(.*)$/', $arguments[2], $match) === 1 ? $match[1] : ($arguments[2] === '--limit' ? ($arguments[3] ?? '') : '');
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
    if ($command !== 'run' || $limit === false || count($arguments) > ($arguments[2] === '--limit' ? 4 : 3)) {
        fwrite(STDERR, $usage);
        exit(2);
    }
}

try {
    $database = getDatabaseConnection();
    if ($command === 'list') {
        $rows = $database->query(
            'SELECT id, deletion_requested_at, deletion_due_at, account_status FROM users
             WHERE deletion_requested_at IS NOT NULL ORDER BY deletion_due_at, id'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            fwrite(STDOUT, sprintf("user_id=%d requested=%s due=%s status=%s\n", $row['id'], $row['deletion_requested_at'], $row['deletion_due_at'], $row['account_status']));
        }
        fwrite(STDOUT, count($rows) . " scheduled.\n");
        exit(0);
    }
    if ($command !== 'run') {
        fwrite(STDERR, $usage);
        exit(2);
    }
    $eraser = Auth0ManagementClient::fromEnvironment();
    $issuer = getenv('EXPECTED_OIDC_ISSUER');
    if ($eraser === null || !is_string($issuer) || $issuer === '') {
        // Never delete local data while the Auth0 account would survive it.
        fwrite(STDERR, "Auth0 Management API is not configured (OIDC_MANAGEMENT_DOMAIN, OIDC_MANAGEMENT_CLIENT_ID, OIDC_MANAGEMENT_CLIENT_SECRET, EXPECTED_OIDC_ISSUER). Nothing was deleted.\n");
        exit(1);
    }
    $result = purgeDueAccountDeletions($database, $eraser, $issuer, $limit);
    fwrite(STDOUT, sprintf("Deleted %d account(s)%s. Failed %d%s.\n",
        count($result['deleted']), $result['deleted'] === [] ? '' : ': ' . implode(', ', $result['deleted']),
        count($result['failed']), $result['failed'] === [] ? '' : ': ' . implode(', ', $result['failed'])));
    exit($result['failed'] === [] ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Purge failed: ' . ($exception instanceof Auth0ManagementException ? $exception->safeReason : get_class($exception)) . "\n");
    exit(1);
}
