<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1' || count($argv) !== 5) exit(2);
require_once __DIR__ . '/../includes/rate_limit.php';
try {
    $result = consumeRateLimit($argv[1], $argv[2], (int) $argv[3], (int) $argv[4]);
    echo $result['allowed'] ? "allowed\n" : "denied\n";
} catch (Throwable $exception) {
    echo 'failed:' . $exception->getMessage() . "\n";
}
