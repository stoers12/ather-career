<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0_oidc.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/error_reporting.php';

/** @return array{user_id: int, authz_version: int} */
function resolveAuth0InternalUser(PDO $database, Auth0OidcConfiguration $configuration, Auth0ValidatedIdentity $identity): array
{
    if (!hash_equals($configuration->issuer, $identity->issuer)) {
        throw new Auth0OidcException('issuer_mismatch');
    }
    $lookup = $database->prepare('SELECT id, oidc_issuer, account_status, authz_version FROM users WHERE oidc_subject = :subject LIMIT 1');
    $lookup->execute(['subject' => $identity->subject]);
    $user = $lookup->fetch(PDO::FETCH_ASSOC);
    if ($user === false) {
        try {
            $create = $database->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:issuer, :subject, 'active', 1)");
            $create->execute(['issuer' => $configuration->issuer, 'subject' => $identity->subject]);
        } catch (PDOException $exception) {
            $code = $exception->errorInfo[1] ?? null;
            if (safePdoErrorCode($exception) !== '23000' || (int) $code !== 1062) throw $exception;
        }
        $lookup->execute(['subject' => $identity->subject]);
        $user = $lookup->fetch(PDO::FETCH_ASSOC);
    }
    if (!is_array($user) || !hash_equals($configuration->issuer, (string) ($user['oidc_issuer'] ?? ''))) {
        throw new Auth0OidcException('identity_binding_invalid');
    }
    $userId = authorizationPositiveInteger($user['id'] ?? null);
    $authzVersion = authorizationPositiveInteger($user['authz_version'] ?? null);
    if (($user['account_status'] ?? null) !== 'active' || $userId === null || $authzVersion === null) {
        throw new Auth0OidcException('account_denied');
    }

    return ['user_id' => $userId, 'authz_version' => $authzVersion];
}
