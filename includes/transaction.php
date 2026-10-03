<?php

declare(strict_types=1);

/**
 * Runs one application use case atomically. Call this at a request/use-case
 * boundary, never from a low-level query helper. An ambient transaction is
 * deliberately reused rather than nested; this keeps callers that already
 * own a unit of work in control of its rollback.
 *
 * @template T
 * @param callable(): T $operation
 * @return T
 */
function runDatabaseTransaction(PDO $database, callable $operation): mixed
{
    $ownsTransaction = !$database->inTransaction();
    if ($ownsTransaction && !$database->beginTransaction()) {
        throw new RuntimeException('The requested change could not be started.');
    }

    try {
        $result = $operation();
        if ($ownsTransaction && !$database->commit()) {
            throw new RuntimeException('The requested change could not be completed.');
        }

        return $result;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $database->inTransaction()) {
            try {
                $database->rollBack();
            } catch (Throwable) {
                // Preserve the original safe failure path. PDO will not reuse
                // a connection that cannot be rolled back safely.
            }
        }

        throw $exception;
    }
}
