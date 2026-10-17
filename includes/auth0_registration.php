<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0_oidc.php';
require_once __DIR__ . '/identity_repository.php';

/**
 * Explicit registration core only. A future purpose-bound registration route
 * must supply an already validated identity; ordinary Sign In never calls this.
 * No session, email address, provider attribution, or Portfolio is persisted.
 *
 * @return array{user_id: int, authz_version: int}
 */
function registerAuth0InternalUser(PDO $database, Auth0OidcConfiguration $configuration, Auth0ValidatedIdentity $identity): array
{
    // Never commit or roll back work belonging to a caller.
    if ($database->inTransaction()) {
        throw new LogicException('Registration requires its own transaction.');
    }
    if ((int) $database->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
        throw new LogicException('Registration requires database exceptions.');
    }
    $parts = auth0HttpsUrlParts($configuration->issuer);
    if ($configuration->issuer === '' || strlen($configuration->issuer) > 2048
        || filter_var($configuration->issuer, FILTER_VALIDATE_URL) === false
        || $parts === null || ($parts['path'] ?? '/') !== '/' || !str_ends_with($configuration->issuer, '/')) {
        throw new IdentityRepositoryException('Registration issuer is invalid.');
    }
    if (!hash_equals($configuration->issuer, $identity->issuer)
        || $identity->subject === '' || strlen($identity->subject) > 255
        || preg_match('//u', $identity->subject) !== 1
        || preg_match('/[\x00-\x1f\x7f]/', $identity->subject) !== 0
        || preg_match('/(*UCP)\S/u', $identity->subject) !== 1) {
        throw new IdentityRepositoryException('Registration identity is invalid.');
    }
    if (!identityRepositoryHasBindingsTable($database)) {
        throw new IdentityRepositoryException('Registration identity foundation is unavailable.');
    }

    $existing = auth0RegistrationActiveBinding($database, $identity);
    if ($existing !== null) {
        return $existing;
    }
    if (auth0EmailVerifiedClassification($identity->emailVerifiedClaim, $identity->hasNonemptyEmail) !== true) {
        throw new IdentityRepositoryException('Registration readiness is insufficient.');
    }

    // No missing-row locking read: concurrent new registrations serialize on
    // users' existing unique subject index, without competing gap locks.
    $database->beginTransaction();
    try {
        $insert = $database->prepare('INSERT INTO users (oidc_issuer, oidc_subject) VALUES (:issuer, :subject)');
        $insert->execute(['issuer' => $identity->issuer, 'subject' => $identity->subject]);
        $userId = (int) $database->lastInsertId();
        $binding = $database->prepare(
            'INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject, is_primary)
             VALUES (:user_id, :issuer, :subject, 1)'
        );
        $binding->execute(['user_id' => $userId, 'issuer' => $identity->issuer, 'subject' => $identity->subject]);
        $database->commit();
        return ['user_id' => $userId, 'authz_version' => 1];
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        // Only the two identity uniqueness conflicts can be race winners.
        // All other database failures propagate unchanged, including deadlocks.
        if (!$exception instanceof PDOException
            || ($exception->errorInfo[0] ?? null) !== '23000'
            || (int) ($exception->errorInfo[1] ?? 0) !== 1062
            || preg_match("/for key '(?:(?:users|user_identities)\.)?(?:uq_users_oidc_subject|uq_user_identities_pair)'/", (string) ($exception->errorInfo[2] ?? '')) !== 1) {
            throw $exception;
        }
        // The failed transaction is gone: observe the committed winner anew.
        // A legacy collision or another issuer is never repaired or accepted.
        $winner = auth0RegistrationActiveBinding($database, $identity);
        if ($winner === null) {
            throw new IdentityRepositoryException('Registration identity conflicts with existing data.');
        }
        return $winner;
    }
}

/** @return array{user_id: int, authz_version: int}|null */
function auth0RegistrationActiveBinding(PDO $database, Auth0ValidatedIdentity $identity): ?array
{
    $user = findCompatibleIdentityUser($database, $identity->issuer, $identity->subject);
    if ($user === null) {
        return null;
    }
    if ($user['account_status'] !== 'active') {
        throw new IdentityRepositoryException('Registration account is unavailable.');
    }
    $primary = $database->prepare('SELECT oidc_issuer, oidc_subject FROM user_identities WHERE user_id = :user_id AND is_primary = 1');
    $primary->execute(['user_id' => $user['user_id']]);
    $bindings = $primary->fetchAll(PDO::FETCH_ASSOC);
    if (count($bindings) !== 1
        || !hash_equals($identity->issuer, (string) $bindings[0]['oidc_issuer'])
        || !hash_equals($identity->subject, (string) $bindings[0]['oidc_subject'])) {
        throw new IdentityRepositoryException('Registration primary binding is inconsistent.');
    }
    return ['user_id' => $user['user_id'], 'authz_version' => $user['authz_version']];
}
