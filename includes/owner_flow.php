<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/owner_session.php';

function ownerAuthorizationDenied(): never
{
    http_response_code(403);
    exit('Owner authentication required.');
}

function requireOwnerAuthenticatedUser(PDO $database): AuthenticatedUserContext
{
    try {
        return requireAuthenticatedUser($database);
    } catch (AuthorizationDeniedException) {
        ownerAuthorizationDenied();
    }
}

function ownerHasPortfolio(PDO $database, AuthenticatedUserContext $user): bool
{
    try {
        $statement = $database->prepare(
            'SELECT id
             FROM portfolios
             WHERE owner_user_id = :internal_user_id
             LIMIT 1'
        );
        $statement->execute(['internal_user_id' => $user->userId]);

        return authorizationPositiveInteger($statement->fetchColumn()) !== null;
    } catch (PDOException $exception) {
        throw new AuthorizationDeniedException('Portfolio authorization failed.', 0, $exception);
    }
}

function requireOwnerPortfolioContext(PDO $database): AuthorizedPortfolioContext
{
    $user = requireOwnerAuthenticatedUser($database);

    try {
        if (!ownerHasPortfolio($database, $user)) {
            header('Location: owner_onboarding.php', true, 303);
            exit;
        }

        return requireOwnedPortfolioContext($database);
    } catch (AuthorizationDeniedException) {
        ownerAuthorizationDenied();
    }
}

/**
 * Creates the only Portfolio for an already-validated User. The caller never
 * supplies an owner ID; the database unique constraint remains the race
 * authority when two valid requests arrive together.
 */
function createOwnedPortfolio(PDO $database, AuthenticatedUserContext $user): ?int
{
    if (ownerHasPortfolio($database, $user)) {
        return null;
    }

    try {
        $statement = $database->prepare(
            'INSERT INTO portfolios (owner_user_id)
             VALUES (:owner_user_id)'
        );
        $statement->execute(['owner_user_id' => $user->userId]);

        return (int) $database->lastInsertId();
    } catch (PDOException $exception) {
        $driverCode = $exception->errorInfo[1] ?? null;
        if (safePdoErrorCode($exception) === '23000'
            && (is_int($driverCode) || ctype_digit((string) $driverCode))
            && (int) $driverCode === 1062) {
            return null;
        }

        throw $exception;
    }
}
