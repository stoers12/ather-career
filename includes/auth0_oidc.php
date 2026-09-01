<?php

declare(strict_types=1);

const AUTH0_AUTH_TRANSACTION_KEY = 'auth0_authorization_transaction';
const AUTH0_AUTH_TRANSACTION_TTL_SECONDS = 600;

final class Auth0OidcException extends RuntimeException
{
    public function __construct(public readonly string $safeReason)
    {
        parent::__construct('Auth0 authentication was denied.');
    }
}

final readonly class Auth0OidcConfiguration
{
    public function __construct(
        public string $issuer,
        public string $domain,
        public string $clientId,
        public string $clientSecret,
        public string $redirectUri,
    ) {
    }
}

final readonly class Auth0ValidatedIdentity
{
    public function __construct(public string $issuer, public string $subject)
    {
    }
}

function auth0Base64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function auth0RequiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || trim($value) === '') {
        throw new Auth0OidcException('configuration_missing');
    }

    return trim($value);
}

function auth0HttpsUrlParts(string $value): ?array
{
    $parts = parse_url($value);
    if (!is_array($parts)
        || ($parts['scheme'] ?? null) !== 'https'
        || !isset($parts['host'])
        || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])) {
        return null;
    }

    return $parts;
}

function auth0ConfigurationFromEnvironment(): Auth0OidcConfiguration
{
    $issuer = auth0RequiredEnvironment('EXPECTED_OIDC_ISSUER');
    $issuerParts = auth0HttpsUrlParts($issuer);
    if ($issuerParts === null || ($issuerParts['path'] ?? '/') !== '/') {
        throw new Auth0OidcException('issuer_configuration_invalid');
    }
    if (!str_ends_with($issuer, '/')) {
        throw new Auth0OidcException('issuer_configuration_invalid');
    }

    $redirectUri = auth0RequiredEnvironment('OIDC_REDIRECT_URI');
    if (auth0HttpsUrlParts($redirectUri) === null) {
        throw new Auth0OidcException('redirect_configuration_invalid');
    }

    $clientId = auth0RequiredEnvironment('OIDC_CLIENT_ID');
    $clientSecret = auth0RequiredEnvironment('OIDC_CLIENT_SECRET');
    if (strlen($clientId) > 255 || strlen($clientSecret) > 2048) {
        throw new Auth0OidcException('configuration_invalid');
    }

    return new Auth0OidcConfiguration($issuer, strtolower((string) $issuerParts['host']), $clientId, $clientSecret, $redirectUri);
}

/** @return list<string> */
function auth0ProductionConfigurationFailures(): array
{
    try {
        $configuration = auth0ConfigurationFromEnvironment();
    } catch (Auth0OidcException) {
        return ['Auth0 OIDC configuration is missing or invalid.'];
    }

    $values = [$configuration->issuer, $configuration->clientId, $configuration->clientSecret, $configuration->redirectUri];
    foreach ($values as $value) {
        if (preg_match('/replace|fixture|issuer\.test|localhost|\.test(?:[\/]|$)|example|(?:^|[-_])test(?:[-_]|$)/i', $value) === 1) {
            return ['test or placeholder Auth0 OIDC configuration is forbidden.'];
        }
    }

    return [];
}

/** @return array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string} */
function auth0Discovery(Auth0OidcConfiguration $configuration): array
{
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $client = new GuzzleHttp\Client(['timeout' => 5.0, 'connect_timeout' => 3.0, 'http_errors' => false]);
        $response = $client->get($configuration->issuer . '.well-known/openid-configuration');
        $payload = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        throw new Auth0OidcException('discovery_unavailable');
    }
    if ($response->getStatusCode() !== 200 || !is_array($payload) || !isset($payload['issuer']) || !hash_equals($configuration->issuer, (string) $payload['issuer'])) {
        throw new Auth0OidcException('discovery_invalid');
    }

    $metadata = [];
    foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $field) {
        $endpoint = $payload[$field] ?? null;
        $parts = is_string($endpoint) ? auth0HttpsUrlParts($endpoint) : null;
        if ($parts === null || strtolower((string) $parts['host']) !== $configuration->domain) {
            throw new Auth0OidcException('discovery_invalid');
        }
        $metadata[$field] = $endpoint;
    }

    return $metadata;
}

/** @return array{url: string, state: string, code_challenge: string} */
function beginAuth0Authorization(Auth0OidcConfiguration $configuration, string $authorizationEndpoint): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException('Auth0 authorization requires an active server-side session.');
    }
    $state = auth0Base64Url(random_bytes(32));
    $verifier = auth0Base64Url(random_bytes(64));
    $nonce = auth0Base64Url(random_bytes(32));
    $challenge = auth0Base64Url(hash('sha256', $verifier, true));
    $_SESSION[AUTH0_AUTH_TRANSACTION_KEY] = [
        'state' => $state,
        'code_verifier' => $verifier,
        'nonce' => $nonce,
        'created_at' => time(),
    ];
    $url = $authorizationEndpoint . '?' . http_build_query([
        'client_id' => $configuration->clientId,
        'response_type' => 'code',
        'redirect_uri' => $configuration->redirectUri,
        'scope' => 'openid',
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], '', '&', PHP_QUERY_RFC3986);

    return ['url' => $url, 'state' => $state, 'code_challenge' => $challenge];
}

/** @return array{code_verifier: string, nonce: string} */
function consumeAuth0AuthorizationTransaction(array $query): array
{
    $transaction = $_SESSION[AUTH0_AUTH_TRANSACTION_KEY] ?? null;
    $state = $query['state'] ?? null;
    if (!is_array($transaction) || !is_string($state) || !isset($transaction['state'], $transaction['code_verifier'], $transaction['nonce'], $transaction['created_at'])
        || !is_string($transaction['state']) || !is_string($transaction['code_verifier']) || !is_string($transaction['nonce']) || !is_int($transaction['created_at'])) {
        unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]);
        throw new Auth0OidcException('transaction_missing');
    }
    if (!hash_equals($transaction['state'], $state)) {
        throw new Auth0OidcException('state_mismatch');
    }
    unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]);
    if ($transaction['created_at'] > time() + 60 || time() - $transaction['created_at'] > AUTH0_AUTH_TRANSACTION_TTL_SECONDS) {
        throw new Auth0OidcException('transaction_expired');
    }
    if (isset($query['error']) || !isset($query['code']) || !is_string($query['code']) || $query['code'] === '' || strlen($query['code']) > 4096) {
        throw new Auth0OidcException(isset($query['error']) ? 'provider_error' : 'code_missing');
    }

    return ['code_verifier' => $transaction['code_verifier'], 'nonce' => $transaction['nonce']];
}

function validateAuth0AuthorizationCode(Auth0OidcConfiguration $configuration, string $code, string $verifier, string $nonce): Auth0ValidatedIdentity
{
    require_once __DIR__ . '/../vendor/autoload.php';
    $discovery = auth0Discovery($configuration);
    try {
        $client = new GuzzleHttp\Client(['timeout' => 8.0, 'connect_timeout' => 3.0, 'http_errors' => false]);
        $response = $client->post($discovery['token_endpoint'], ['form_params' => [
            'grant_type' => 'authorization_code',
            'client_id' => $configuration->clientId,
            'client_secret' => $configuration->clientSecret,
            'redirect_uri' => $configuration->redirectUri,
            'code' => $code,
            'code_verifier' => $verifier,
        ]]);
        $payload = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        if ($response->getStatusCode() !== 200 || !is_array($payload) || !is_string($payload['id_token'] ?? null)) {
            throw new RuntimeException();
        }
        $sdkConfiguration = new Auth0\SDK\Configuration\SdkConfiguration([
            'strategy' => Auth0\SDK\Configuration\SdkConfiguration::STRATEGY_NONE,
            'domain' => $configuration->domain,
            'clientId' => $configuration->clientId,
            'clientSecret' => $configuration->clientSecret,
            'redirectUri' => $configuration->redirectUri,
            'tokenAlgorithm' => Auth0\SDK\Token::ALGO_RS256,
            'tokenJwksUri' => $discovery['jwks_uri'],
        ]);
        $token = new Auth0\SDK\Token($sdkConfiguration, $payload['id_token'], Auth0\SDK\Token::TYPE_ID_TOKEN);
        $token->verify()->validate($configuration->issuer, [$configuration->clientId], null, $nonce);
        $issuer = $token->getIssuer();
        $subject = $token->getSubject();
    } catch (Throwable) {
        throw new Auth0OidcException('token_validation_failed');
    }
    if (!is_string($issuer) || !hash_equals($configuration->issuer, $issuer)) {
        throw new Auth0OidcException('issuer_mismatch');
    }
    if (!is_string($subject) || $subject === '' || strlen($subject) > 255) {
        throw new Auth0OidcException('subject_missing');
    }

    return new Auth0ValidatedIdentity($issuer, $subject);
}

/** @param null|callable(Auth0OidcConfiguration, string, string, string): Auth0ValidatedIdentity $validator */
function completeAuth0Authorization(Auth0OidcConfiguration $configuration, array $query, ?callable $validator = null): Auth0ValidatedIdentity
{
    $transaction = consumeAuth0AuthorizationTransaction($query);
    $identity = ($validator ?? 'validateAuth0AuthorizationCode')($configuration, (string) $query['code'], $transaction['code_verifier'], $transaction['nonce']);
    if (!$identity instanceof Auth0ValidatedIdentity || !hash_equals($configuration->issuer, $identity->issuer) || $identity->subject === '' || strlen($identity->subject) > 255) {
        throw new Auth0OidcException('identity_invalid');
    }

    return $identity;
}
