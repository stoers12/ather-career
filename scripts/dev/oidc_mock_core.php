<?php

declare(strict_types=1);

/*
 * Development-only OpenID Connect provider that stands in for Auth0 (L-15).
 *
 * The production Auth0 tenant keeps only production callback URLs, so local
 * development signs in here instead: a chooser page issues an authorization
 * code, and the token endpoint returns an RS256 id_token that the unchanged
 * application validates exactly as it validates Auth0's. It also answers the
 * two Management API calls the account purge makes (L-03).
 *
 * Safety: devOidcMockEnabled() refuses unless APP_ENV=development and
 * ATHER_DEV_OIDC_MOCK=1. The client id contains "-test-", which
 * auth0ProductionConfigurationFailures() rejects, so the mock's configuration
 * can never pass the production security check.
 */

const DEV_OIDC_MOCK_USERS = [
    'dev|owner' => ['label' => 'Owner with the sample portfolio', 'email' => 'owner@dev.invalid'],
    'google-oauth2|dev-newcomer' => ['label' => 'New person (registration with an invitation code)', 'email' => 'newcomer@dev.invalid'],
    'dev|second' => ['label' => 'Second account (disable it to test a disabled account)', 'email' => 'second@dev.invalid'],
];
const DEV_OIDC_MOCK_CODE_TTL_SECONDS = 300;
const DEV_OIDC_MOCK_TOKEN_TTL_SECONDS = 600;

final class DevOidcMockException extends RuntimeException
{
}

/** @return array{issuer: string, client_id: string, client_secret: string, redirect_uri: string, return_base: string, state_dir: string} */
function devOidcMockConfiguration(): array
{
    $values = [];
    foreach (['EXPECTED_OIDC_ISSUER', 'OIDC_CLIENT_ID', 'OIDC_CLIENT_SECRET', 'OIDC_REDIRECT_URI', 'PUBLIC_BASE_URL', 'DEV_OIDC_STATE_DIR'] as $name) {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            throw new DevOidcMockException("{$name} is required.");
        }
        $values[] = $value;
    }
    if (preg_match('#^https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?/$#', $values[0]) !== 1) {
        throw new DevOidcMockException('EXPECTED_OIDC_ISSUER must be an https origin ending in a slash.');
    }

    return ['issuer' => $values[0], 'client_id' => $values[1], 'client_secret' => $values[2], 'redirect_uri' => $values[3],
        'return_base' => rtrim($values[4], '/'), 'state_dir' => rtrim($values[5], '/')];
}

function devOidcMockEnabled(): bool
{
    return getenv('APP_ENV') === 'development' && getenv('ATHER_DEV_OIDC_MOCK') === '1';
}

function devOidcBase64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/** The signing key and its self-signed certificate; the Auth0 SDK reads keys only from x5c. */
function devOidcMockSigningKey(string $stateDirectory): array
{
    $keyPath = $stateDirectory . '/signing-key.pem';
    $certificatePath = $stateDirectory . '/signing-cert.pem';
    if (!is_file($keyPath) || !is_file($certificatePath)) {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $request = $key === false ? false : openssl_csr_new(['commonName' => 'Ather development OIDC mock'], $key, ['digest_alg' => 'sha256']);
        $certificate = $request === false ? false : openssl_csr_sign($request, null, $key, 3650, ['digest_alg' => 'sha256']);
        if ($certificate === false || !openssl_pkey_export($key, $keyPem) || !openssl_x509_export($certificate, $certificatePem)) {
            throw new DevOidcMockException('Signing key generation failed.');
        }
        file_put_contents($keyPath, $keyPem, LOCK_EX);
        file_put_contents($certificatePath, $certificatePem, LOCK_EX);
        chmod($keyPath, 0600);
    }
    $certificatePem = (string) file_get_contents($certificatePath);

    return [
        'private' => (string) file_get_contents($keyPath),
        'kid' => substr(hash('sha256', $certificatePem), 0, 16),
        'x5c' => (string) preg_replace('/-----[^-]+-----|\s/', '', $certificatePem),
    ];
}

/** @return array<string, mixed> */
function devOidcMockDiscovery(string $issuer): array
{
    return ['issuer' => $issuer, 'authorization_endpoint' => $issuer . 'authorize', 'token_endpoint' => $issuer . 'token',
        'jwks_uri' => $issuer . 'jwks', 'response_types_supported' => ['code'], 'id_token_signing_alg_values_supported' => ['RS256']];
}

/** @param array{private: string, kid: string, x5c: string} $key */
function devOidcMockJwks(array $key): array
{
    $details = openssl_pkey_get_details(openssl_pkey_get_private($key['private']));
    if (!is_array($details) || !isset($details['rsa'])) {
        throw new DevOidcMockException('Signing key is unreadable.');
    }

    return ['keys' => [['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $key['kid'],
        'n' => devOidcBase64Url($details['rsa']['n']), 'e' => devOidcBase64Url($details['rsa']['e']), 'x5c' => [$key['x5c']]]]];
}

/**
 * Validates an authorization request; returns the parameters the chooser page keeps.
 *
 * @param array<string, mixed> $query
 * @return array{state: string, nonce: string, code_challenge: string}
 */
function devOidcMockAuthorizationRequest(array $configuration, array $query): array
{
    if (($query['response_type'] ?? null) !== 'code' || ($query['client_id'] ?? null) !== $configuration['client_id']
        || ($query['redirect_uri'] ?? null) !== $configuration['redirect_uri'] || ($query['code_challenge_method'] ?? null) !== 'S256') {
        throw new DevOidcMockException('The authorization request does not match the configured client.');
    }
    foreach (['state', 'nonce', 'code_challenge'] as $field) {
        if (!is_string($query[$field] ?? null) || $query[$field] === '' || strlen($query[$field]) > 512) {
            throw new DevOidcMockException("The authorization request has no valid {$field}.");
        }
    }

    return ['state' => $query['state'], 'nonce' => $query['nonce'], 'code_challenge' => $query['code_challenge']];
}

function devOidcMockSubject(mixed $chosen, mixed $custom): string
{
    $subject = is_string($custom) && trim($custom) !== '' ? trim($custom) : (is_string($chosen) ? $chosen : '');
    if (preg_match('/^[A-Za-z0-9._-]{1,40}\|[A-Za-z0-9._-]{1,120}$/', $subject) !== 1) {
        throw new DevOidcMockException('Choose a listed user or type a subject such as dev|alice.');
    }

    return $subject;
}

/** Stores a single-use code bound to the request; returns it. */
function devOidcMockIssueCode(array $configuration, array $request, string $subject, ?int $now = null): string
{
    $code = devOidcBase64Url(random_bytes(32));
    $record = ['subject' => $subject, 'nonce' => $request['nonce'], 'code_challenge' => $request['code_challenge'], 'expires_at' => ($now ?? time()) + DEV_OIDC_MOCK_CODE_TTL_SECONDS];
    file_put_contents($configuration['state_dir'] . '/code-' . hash('sha256', $code) . '.json', json_encode($record, JSON_THROW_ON_ERROR), LOCK_EX);

    return $code;
}

/**
 * The token endpoint: authorization_code for sign-in, client_credentials for
 * the Management API. Errors use OAuth error codes.
 *
 * @param array<string, mixed> $form
 * @return array{status: int, body: array<string, mixed>}
 */
function devOidcMockToken(array $configuration, array $form, ?int $now = null): array
{
    $now ??= time();
    if (!hash_equals($configuration['client_id'], (string) ($form['client_id'] ?? '')) || !hash_equals($configuration['client_secret'], (string) ($form['client_secret'] ?? ''))) {
        return ['status' => 401, 'body' => ['error' => 'invalid_client']];
    }
    if (($form['grant_type'] ?? null) === 'client_credentials') {
        if (($form['audience'] ?? null) !== devOidcMockManagementAudience($configuration)) {
            return ['status' => 403, 'body' => ['error' => 'access_denied']];
        }
        $token = devOidcBase64Url(random_bytes(32));
        file_put_contents($configuration['state_dir'] . '/management-token-' . hash('sha256', $token), (string) ($now + DEV_OIDC_MOCK_TOKEN_TTL_SECONDS), LOCK_EX);

        return ['status' => 200, 'body' => ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => DEV_OIDC_MOCK_TOKEN_TTL_SECONDS, 'scope' => 'delete:users']];
    }
    if (($form['grant_type'] ?? null) !== 'authorization_code' || ($form['redirect_uri'] ?? null) !== $configuration['redirect_uri']
        || !is_string($form['code'] ?? null) || !is_string($form['code_verifier'] ?? null)) {
        return ['status' => 400, 'body' => ['error' => 'invalid_request']];
    }
    $path = $configuration['state_dir'] . '/code-' . hash('sha256', $form['code']) . '.json';
    $record = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (is_file($path)) {
        unlink($path);
    }
    if (!is_array($record) || $record['expires_at'] < $now
        || !hash_equals($record['code_challenge'], devOidcBase64Url(hash('sha256', $form['code_verifier'], true)))) {
        return ['status' => 400, 'body' => ['error' => 'invalid_grant']];
    }
    $key = devOidcMockSigningKey($configuration['state_dir']);
    // Registration requires a verified email, as Google sign-in provides; a typed
    // subject gets a derived address on the reserved .invalid domain.
    $email = DEV_OIDC_MOCK_USERS[$record['subject']]['email']
        ?? trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($record['subject'])), '-') . '@dev.invalid';
    $claims = ['iss' => $configuration['issuer'], 'sub' => $record['subject'], 'aud' => $configuration['client_id'],
        'iat' => $now, 'exp' => $now + DEV_OIDC_MOCK_TOKEN_TTL_SECONDS, 'nonce' => $record['nonce'],
        'email' => $email, 'email_verified' => true];

    return ['status' => 200, 'body' => ['id_token' => devOidcMockSign($key, $claims), 'token_type' => 'Bearer', 'expires_in' => DEV_OIDC_MOCK_TOKEN_TTL_SECONDS]];
}

/** @param array{private: string, kid: string, x5c: string} $key @param array<string, mixed> $claims */
function devOidcMockSign(array $key, array $claims): string
{
    $input = devOidcBase64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $key['kid']], JSON_THROW_ON_ERROR))
        . '.' . devOidcBase64Url(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    if (!openssl_sign($input, $signature, $key['private'], OPENSSL_ALGO_SHA256)) {
        throw new DevOidcMockException('Token signing failed.');
    }

    return $input . '.' . devOidcBase64Url($signature);
}

/** The Management API audience the purge client requests: https://{host}/api/v2/ without the issuer's port. */
function devOidcMockManagementAudience(array $configuration): string
{
    return 'https://' . (string) parse_url($configuration['issuer'], PHP_URL_HOST) . '/api/v2/';
}

/** DELETE /api/v2/users/{id}: records the deletion so a developer can see the purge worked. */
function devOidcMockDeleteUser(array $configuration, string $authorization, string $encodedUserId, ?int $now = null): int
{
    $token = preg_match('/^Bearer ([A-Za-z0-9_-]{20,})$/', $authorization, $match) === 1 ? $match[1] : null;
    $path = $token === null ? null : $configuration['state_dir'] . '/management-token-' . hash('sha256', $token);
    if ($path === null || !is_file($path) || (int) file_get_contents($path) < ($now ?? time())) {
        return 401;
    }
    $userId = rawurldecode($encodedUserId);
    if (preg_match('/^[A-Za-z0-9._-]{1,40}\|[A-Za-z0-9._-]{1,120}$/', $userId) !== 1) {
        return 400;
    }
    file_put_contents($configuration['state_dir'] . '/deleted-users.log', gmdate('c') . ' ' . $userId . "\n", FILE_APPEND | LOCK_EX);

    return 204;
}

/** Logout returns only to this application, never to an arbitrary URL. */
function devOidcMockLogoutTarget(array $configuration, mixed $returnTo): string
{
    $base = $configuration['return_base'];

    return is_string($returnTo) && ($returnTo === $base || str_starts_with($returnTo, $base . '/')) ? $returnTo : $base . '/signin.php';
}
