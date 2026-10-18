<?php

declare(strict_types=1);

const PROFILE_MAX_VERSION = 2147483647;

final class ProfileVersionConflictException extends RuntimeException {}
final class ProfileWriteUncertainException extends RuntimeException {}

/** HTML form tokens are canonical decimal strings, not coerced scalars. */
function submittedProfileVersion(mixed $value): ?int
{
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', $value) === 1
        && (int) $value <= PROFILE_MAX_VERSION ? (int) $value : null;
}

/** Own the database outcome before making any media compensation decision.
 * Rollback confirms pre-commit failure only. Once COMMIT is attempted, a later
 * rollback acknowledgement cannot establish that the earlier COMMIT did not land.
 */
function runProfileWriteTransaction(PDO $database, callable $operation): mixed
{
    if ($database->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION || $database->inTransaction()) {
        throw new LogicException('Profile writes require exception mode and an owned transaction.');
    }
    if (!$database->beginTransaction()) { throw new RuntimeException('Profile write could not start.'); }
    $commitAttempted = false;
    try {
        $result = $operation();
        $commitAttempted = true;
        if (!$database->commit()) { throw new RuntimeException('Profile commit was not acknowledged.'); }
        return $result;
    } catch (Throwable $exception) {
        $rolledBack = false;
        try {
            $rolledBack = $database->inTransaction() && $database->rollBack();
        } catch (Throwable) {}
        if (!$commitAttempted && $rolledBack) { throw $exception; }
        throw new ProfileWriteUncertainException('Profile write outcome is uncertain; reload before another change.', 0, $exception);
    }
}
