<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/owner_session.php';

class OwnerReadinessDeniedException extends RuntimeException
{
}

function ownerAuthorizationDenied(): never
{
    httpAbortHtml(403, 'You are not authorized to access this resource.');
}

function ownerAuthenticationRequired(): never
{
    httpRedirect('/owner_login.php');
}

function requireOwnerAuthenticatedUser(PDO $database): AuthenticatedUserContext
{
    if (currentInternalUserSession() === null) {
        ownerAuthenticationRequired();
    }

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
            httpRedirect('/owner_onboarding.php');
        }

        return requireOwnedPortfolioContext($database);
    } catch (AuthorizationDeniedException) {
        ownerAuthorizationDenied();
    }
}

/**
 * Creates the only Portfolio for the currently authorized User after a
 * positive current-session readiness observation. The caller never supplies
 * an owner ID; the database unique constraint remains the race authority.
 */
function createOwnedPortfolio(PDO $database, AuthenticatedUserContext $user): ?int
{
    $currentUser = requireAuthenticatedUser($database);
    if ($currentUser->userId !== $user->userId) {
        throw new AuthorizationDeniedException('Portfolio creation identity mismatch.');
    }

    if (ownerHasPortfolio($database, $user)) {
        return null;
    }

    $observation = readAuthorizedOwnerReadinessObservation($database);
    if (($observation['email_verified'] ?? null) !== true) {
        throw new OwnerReadinessDeniedException('Current-session email verification is required.');
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
