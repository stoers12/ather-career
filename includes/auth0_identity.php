<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0_oidc.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/identity_repository.php';
require_once __DIR__ . '/security_events.php';

/** @return array{user_id: int, authz_version: int} */
function resolveAuth0InternalUser(PDO $database, Auth0OidcConfiguration $configuration, Auth0ValidatedIdentity $identity): array
{
    if (!hash_equals($configuration->issuer, $identity->issuer)) {
        throw new Auth0OidcException('issuer_mismatch');
    }
    // Cutover requires the verified 014 table. The repository's pre-014
    // compatibility fallback is deliberately unavailable to this callback.
    if (!identityRepositoryHasBindingsTable($database)) {
        throw new Auth0OidcException('identity_binding_invalid');
    }
    try {
        $user = findCompatibleIdentityUser($database, $identity->issuer, $identity->subject);
    } catch (IdentityRepositoryException) {
        reportSecurityEvent('oidc_identity_resolution', 'denied', ['reason' => 'binding_conflict']);
        throw new Auth0OidcException('identity_binding_invalid');
    }
    if ($user === null) {
        throw new Auth0OidcException('identity_binding_invalid');
    }
    $userId = authorizationPositiveInteger($user['user_id'] ?? null);
    $authzVersion = authorizationPositiveInteger($user['authz_version'] ?? null);
    if (($user['account_status'] ?? null) !== 'active' || $userId === null || $authzVersion === null) {
        throw new Auth0OidcException('account_denied');
    }

    return ['user_id' => $userId, 'authz_version' => $authzVersion];
}
