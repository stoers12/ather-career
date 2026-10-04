<?php

declare(strict_types=1);

/**
 * Phase 1 read-only compatibility boundary. Callback cutover, registration,
 * linking, and mutation of identity bindings belong to later phases.
 */
final class IdentityRepositoryException extends RuntimeException
{
}

function identityRepositoryHasBindingsTable(PDO $database): bool
{
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    $statement->execute(['table_name' => 'user_identities']);
    return (int) $statement->fetchColumn() === 1;
}

/** @return array{user_id: int, account_status: string, authz_version: int}|null */
function findCompatibleIdentityUser(PDO $database, string $issuer, string $subject): ?array
{
    if ($issuer === '' || strlen($issuer) > 2048 || $subject === '' || strlen($subject) > 255) {
        throw new IdentityRepositoryException('Identity input is invalid.');
    }
    if (identityRepositoryHasBindingsTable($database)) {
        $lookup = $database->prepare(
            'SELECT users.id, users.oidc_issuer AS legacy_issuer,
                    users.oidc_subject AS legacy_subject, users.account_status,
                    users.authz_version, user_identities.is_primary
             FROM user_identities
             JOIN users ON users.id = user_identities.user_id
             WHERE user_identities.oidc_issuer = :issuer
               AND user_identities.oidc_subject = :subject
             LIMIT 1'
        );
        $lookup->execute(['issuer' => $issuer, 'subject' => $subject]);
        $row = $lookup->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if ((int) $row['is_primary'] !== 1
            || !hash_equals((string) $row['legacy_issuer'], $issuer)
            || !hash_equals((string) $row['legacy_subject'], $subject)) {
            throw new IdentityRepositoryException('Identity binding is inconsistent.');
        }
    } else {
        $lookup = $database->prepare(
            'SELECT id, oidc_issuer AS legacy_issuer, account_status, authz_version
             FROM users WHERE oidc_subject = :subject LIMIT 1'
        );
        $lookup->execute(['subject' => $subject]);
        $row = $lookup->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if (!hash_equals((string) $row['legacy_issuer'], $issuer)) {
            return null;
        }
    }
    $userId = (int) $row['id'];
    $authzVersion = (int) $row['authz_version'];
    if ($userId < 1 || $authzVersion < 1 || !in_array($row['account_status'], ['active', 'disabled'], true)) {
        throw new IdentityRepositoryException('Identity user state is invalid.');
    }
    return ['user_id' => $userId, 'account_status' => $row['account_status'], 'authz_version' => $authzVersion];
}
