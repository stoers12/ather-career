<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Deletes a person's sign-in account at the identity provider when their
 * account is purged (L-03). Deleting an account that is already gone succeeds.
 */
interface AccountIdentityEraser
{
    /** @throws Auth0ManagementException */
    public function eraseUser(string $subject): void;
}

/** A failure that is safe to log: it never carries a secret, token or subject. */
final class Auth0ManagementException extends RuntimeException
{
    public function __construct(public readonly string $safeReason)
    {
        parent::__construct('Auth0 Management API: ' . $safeReason);
    }
}

/**
 * The Auth0 Management API through a machine-to-machine application that is
 * granted only delete:users. OIDC_MANAGEMENT_DOMAIN is the tenant's canonical
 * domain (for example ather.eu.auth0.com), not a custom sign-in domain, because
 * the Management API audience is always the canonical one.
 */
final class Auth0ManagementClient implements AccountIdentityEraser
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly string $domain,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly ClientInterface $http,
    ) {
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/', $domain) !== 1
            || $clientId === '' || $clientSecret === '') {
            throw new Auth0ManagementException('configuration_invalid');
        }
    }

    /** Null when the Management API is not configured at all. */
    public static function fromEnvironment(?ClientInterface $http = null): ?self
    {
        $values = [];
        foreach (['OIDC_MANAGEMENT_DOMAIN', 'OIDC_MANAGEMENT_CLIENT_ID', 'OIDC_MANAGEMENT_CLIENT_SECRET'] as $name) {
            $value = getenv($name);
            $values[] = is_string($value) ? trim($value) : '';
        }
        if ($values === ['', '', '']) {
            return null;
        }

        return new self(strtolower($values[0]), $values[1], $values[2], $http ?? new Client());
    }

    public function eraseUser(string $subject): void
    {
        if ($subject === '' || strlen($subject) > 255 || preg_match('/[\x00-\x1F\x7F]/', $subject) === 1) {
            throw new Auth0ManagementException('subject_invalid');
        }
        $status = $this->send('DELETE', '/api/v2/users/' . rawurlencode($subject), ['headers' => ['Authorization' => 'Bearer ' . $this->token()]])->getStatusCode();
        // 404: the account is already gone, which is the state we want.
        if ($status !== 204 && $status !== 200 && $status !== 404) {
            throw new Auth0ManagementException('delete_rejected_' . $status);
        }
    }

    private function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }
        $response = $this->send('POST', '/oauth/token', ['json' => [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'audience' => 'https://' . $this->domain . '/api/v2/',
        ]]);
        if ($response->getStatusCode() !== 200) {
            throw new Auth0ManagementException('token_rejected_' . $response->getStatusCode());
        }
        try {
            $payload = json_decode((string) $response->getBody(), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new Auth0ManagementException('token_malformed');
        }
        if (!is_array($payload) || !is_string($payload['access_token'] ?? null) || $payload['access_token'] === '') {
            throw new Auth0ManagementException('token_malformed');
        }

        return $this->accessToken = $payload['access_token'];
    }

    /** @param array<string, mixed> $options */
    private function send(string $method, string $path, array $options): \Psr\Http\Message\ResponseInterface
    {
        try {
            return $this->http->request($method, 'https://' . $this->domain . $path, $options + [
                'http_errors' => false,
                'allow_redirects' => false,
                'connect_timeout' => 5,
                'timeout' => 15,
            ]);
        } catch (GuzzleException) {
            throw new Auth0ManagementException('unreachable');
        }
    }
}
