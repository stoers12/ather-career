<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ownership_backfill.php';

function ownershipBackfillFailure(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function testOnlyBackfillFailurePointAllowed(?string $failurePoint): bool
{
    if ($failurePoint === null) {
        return true;
    }

    return getenv('APP_ENV') === 'test' && getenv('ATHERCAR_TEST_MODE') === '1';
}

function parseOwnershipBackfillArguments(array $arguments): array
{
    $issuer = null;
    $subject = null;
    $testFailurePoint = null;

    for ($index = 1; $index < count($arguments); $index++) {
        $argument = $arguments[$index];
        if ($argument === '--issuer' || $argument === '--subject' || $argument === '--test-fail-after') {
            if (!isset($arguments[$index + 1])) {
                ownershipBackfillFailure("Missing value for {$argument}.");
            }
            $value = $arguments[++$index];
            if ($argument === '--issuer') {
                $issuer = $value;
            } elseif ($argument === '--subject') {
                $subject = $value;
            } else {
                $testFailurePoint = $value;
            }
            continue;
        }

        ownershipBackfillFailure('Usage: php database/backfill-v1-ownership.php --issuer <exact-issuer> --subject <exact-subject> [--test-fail-after <point>]');
    }

    if (!is_string($issuer) || !is_string($subject)) {
        ownershipBackfillFailure('Usage: php database/backfill-v1-ownership.php --issuer <exact-issuer> --subject <exact-subject> [--test-fail-after <point>]');
    }
    if (!testOnlyBackfillFailurePointAllowed($testFailurePoint)) {
        ownershipBackfillFailure('Test-only ownership backfill failure injection is unavailable outside the explicit test environment.');
    }

    return [$issuer, $subject, $testFailurePoint];
}

try {
    [$issuer, $subject, $testFailurePoint] = parseOwnershipBackfillArguments($argv);
    $result = OwnershipBackfill::execute(getDatabaseConnection(), $issuer, $subject, $testFailurePoint);
    fwrite(STDOUT, "Ownership backfill verified for User {$result['user_id']} and Portfolio {$result['portfolio_id']}.\n");
} catch (Throwable $exception) {
    ownershipBackfillFailure('Ownership backfill stopped: ' . $exception->getMessage());
}
