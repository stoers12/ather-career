<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/registration_invitations.php';

$usage = "Usage:\n"
    . "  php scripts/ops/registration-invitations.php create --count N [--days " . REGISTRATION_INVITATION_DEFAULT_DAYS . "] [--label TEXT]\n"
    . "  php scripts/ops/registration-invitations.php list\n"
    . "  php scripts/ops/registration-invitations.php revoke --id N\n";
$arguments = is_array($_SERVER['argv'] ?? null) ? array_values($_SERVER['argv']) : [];
$command = $arguments[1] ?? '';
// getopt() stops at the subcommand, so read "--name value" / "--name=value" pairs after it.
$options = [];
for ($index = 2; $index < count($arguments); $index++) {
    if (preg_match('/^--(count|days|label|id)(?:=(.*))?$/s', $arguments[$index], $match) !== 1) {
        fwrite(STDERR, $usage);
        exit(2);
    }
    $options[$match[1]] = array_key_exists(2, $match) ? $match[2] : ($arguments[++$index] ?? '');
}

try {
    $database = getDatabaseConnection();
    if ($command === 'create') {
        $count = filter_var($options['count'] ?? null, FILTER_VALIDATE_INT);
        $days = filter_var($options['days'] ?? REGISTRATION_INVITATION_DEFAULT_DAYS, FILTER_VALIDATE_INT);
        $label = isset($options['label']) && is_string($options['label']) ? $options['label'] : null;
        if ($count === false || $days === false) {
            fwrite(STDERR, $usage);
            exit(2);
        }
        $codes = createRegistrationInvitations($database, $count, $days, $label);
        fwrite(STDOUT, "Created " . count($codes) . " invitation(s), valid for {$days} day(s). They are shown only once:\n");
        foreach ($codes as $code) {
            fwrite(STDOUT, $code . "\n");
        }
    } elseif ($command === 'list') {
        foreach (listRegistrationInvitations($database) as $row) {
            fwrite(STDOUT, sprintf("#%d %-9s expires=%s used=%s label=%s\n", $row['id'], $row['state'], $row['expires_at'], $row['used_at'] ?? '-', $row['label'] ?? '-'));
        }
    } elseif ($command === 'revoke') {
        $id = filter_var($options['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            fwrite(STDERR, $usage);
            exit(2);
        }
        fwrite(STDOUT, revokeRegistrationInvitation($database, $id) ? "Revoked #{$id}.\n" : "#{$id} is unknown, already used or already revoked.\n");
    } else {
        fwrite(STDERR, $usage);
        exit(2);
    }
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n" . $usage);
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Invitation command failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
