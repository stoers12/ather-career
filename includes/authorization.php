<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

/**
 * The safe result for an invalid internal session, current User, or ownership
 * lookup. Callers may render this as a private not-found/denied response.
 */
final class AuthorizationDeniedException extends RuntimeException
{
}

/**
 * A current, active User whose session authorization version was checked
 * against the authoritative users row for this request.
 */
final readonly class AuthenticatedUserContext
{
    private function __construct(
        public int $userId,
    ) {
    }

    public static function fromValidatedUser(int $userId): self
    {
        if ($userId < 1) {
            throw new LogicException('Validated User ID is invalid.');
        }

        return new self($userId);
    }
}

/**
 * The only tenant selector accepted by Phase 2 owner data access. It is
 * derived from the current, validated User and portfolios.owner_user_id.
 */
final readonly class AuthorizedPortfolioContext
{
    private function __construct(
        public int $userId,
        public int $portfolioId,
    ) {
    }

    public static function fromValidatedOwnership(AuthenticatedUserContext $user, int $portfolioId): self
    {
        if ($portfolioId < 1) {
            throw new LogicException('Validated Portfolio ID is invalid.');
        }

        return new self($user->userId, $portfolioId);
    }
}

function authorizationPositiveInteger(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }

    if (!is_string($value) || $value === '' || !ctype_digit($value)) {
        return null;
    }

    $integer = (int) $value;

    return $integer > 0 && (string) $integer === ltrim($value, '0') ? $integer : null;
}

/**
 * Reads the PHP session identity and validates it against the current users
 * row. No request, cookie, header, or caller-supplied identifier participates
 * in this decision.
 *
 * @throws AuthorizationDeniedException
 */
function requireAuthenticatedUser(PDO $database): AuthenticatedUserContext
{
    $session = currentInternalUserSession();
    if ($session === null) {
        throw new AuthorizationDeniedException('Authenticated User validation failed.');
    }

    try {
        $statement = $database->prepare(
            'SELECT id, account_status, authz_version
             FROM users
             WHERE id = :internal_user_id
             LIMIT 1'
        );
        $statement->execute(['internal_user_id' => $session['internal_user_id']]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        throw new AuthorizationDeniedException('Authenticated User validation failed.', 0, $exception);
    }

    $authzVersion = is_array($user) ? authorizationPositiveInteger($user['authz_version'] ?? null) : null;
    if (!is_array($user)
        || authorizationPositiveInteger($user['id'] ?? null) !== $session['internal_user_id']
        || ($user['account_status'] ?? null) !== 'active'
        || $authzVersion === null
        || $authzVersion !== $session['authz_version']) {
        throw new AuthorizationDeniedException('Authenticated User validation failed.');
    }

    return AuthenticatedUserContext::fromValidatedUser($session['internal_user_id']);
}

/**
 * Derives the sole owner Portfolio from the validated User. A valid User with
 * no portfolios row intentionally has no AuthorizedPortfolioContext.
 *
 * @throws AuthorizationDeniedException
 */
function requireOwnedPortfolioContext(PDO $database): AuthorizedPortfolioContext
{
    $user = requireAuthenticatedUser($database);

    try {
        $statement = $database->prepare(
            'SELECT id
             FROM portfolios
             WHERE owner_user_id = :internal_user_id
             LIMIT 1'
        );
        $statement->execute(['internal_user_id' => $user->userId]);
        $portfolioId = authorizationPositiveInteger($statement->fetchColumn());
    } catch (PDOException $exception) {
        throw new AuthorizationDeniedException('Portfolio authorization failed.', 0, $exception);
    }

    if ($portfolioId === null) {
        throw new AuthorizationDeniedException('Portfolio authorization failed.');
    }

    return AuthorizedPortfolioContext::fromValidatedOwnership($user, $portfolioId);
}
