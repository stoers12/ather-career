<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/scripts/dev/oidc_mock_core.php';
require_once dirname(__DIR__, 2) . '/includes/auth0_oidc.php';

final class DevOidcMockTest extends TestCase
{
    private const ENV = ['APP_ENV', 'ATHER_DEV_OIDC_MOCK', 'EXPECTED_OIDC_ISSUER', 'OIDC_CLIENT_ID', 'OIDC_CLIENT_SECRET', 'OIDC_REDIRECT_URI', 'PUBLIC_BASE_URL', 'DEV_OIDC_STATE_DIR'];

    private string $stateDirectory;
    /** @var array<string, string|false> the environment before the test, restored afterwards */
    private array $savedEnvironment = [];
    /** @var array{issuer: string, client_id: string, client_secret: string, redirect_uri: string, return_base: string, state_dir: string} */
    private array $configuration;

    protected function setUp(): void
    {
        $this->stateDirectory = sys_get_temp_dir() . '/dev-oidc-mock-test-' . bin2hex(random_bytes(6));
        mkdir($this->stateDirectory, 0700);
        foreach (self::ENV as $name) {
            $this->savedEnvironment[$name] = getenv($name);
        }
        foreach (self::composeEnvironment() + ['DEV_OIDC_STATE_DIR' => $this->stateDirectory] as $name => $value) {
            putenv($name . '=' . $value);
        }
        $this->configuration = devOidcMockConfiguration();
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnvironment as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
        foreach (glob($this->stateDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->stateDirectory);
    }

    public function testTheMockRunsOnlyWhenExplicitlyEnabledInDevelopment(): void
    {
        putenv('ATHER_DEV_OIDC_MOCK');
        self::assertFalse(devOidcMockEnabled());
        putenv('ATHER_DEV_OIDC_MOCK=1');
        self::assertTrue(devOidcMockEnabled());
        foreach (['production', 'test', ''] as $environment) {
            putenv('APP_ENV=' . $environment);
            self::assertFalse(devOidcMockEnabled(), "Enabled with APP_ENV={$environment}");
        }
    }

    public function testTheDevelopmentConfigurationCanNeverPassTheProductionCheck(): void
    {
        self::assertSame(['test or placeholder Auth0 OIDC configuration is forbidden.'], auth0ProductionConfigurationFailures());
        self::assertSame(['test or placeholder Auth0 OIDC configuration is forbidden.'], auth0ProductionConfigurationFailures(true));
    }

    public function testDiscoveryKeepsEveryEndpointOnTheIssuerHost(): void
    {
        $discovery = devOidcMockDiscovery($this->configuration['issuer']);
        self::assertSame($this->configuration['issuer'], $discovery['issuer']);
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $field) {
            self::assertStringStartsWith($this->configuration['issuer'], $discovery[$field]);
        }
    }

    public function testAuthorizationRequestsMustMatchTheConfiguredClient(): void
    {
        $valid = $this->authorizationQuery('verifier-' . str_repeat('a', 43));
        self::assertSame('state-1', devOidcMockAuthorizationRequest($this->configuration, $valid)['state']);
        foreach ([['client_id' => 'other'], ['redirect_uri' => 'https://evil.example/cb'], ['code_challenge_method' => 'plain'], ['response_type' => 'token'], ['nonce' => '']] as $change) {
            try {
                devOidcMockAuthorizationRequest($this->configuration, $change + $valid);
                self::fail('Accepted ' . json_encode($change));
            } catch (DevOidcMockException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testACodeBecomesOneSignedIdTokenForTheChosenSubject(): void
    {
        $verifier = 'verifier-' . str_repeat('b', 43);
        $request = devOidcMockAuthorizationRequest($this->configuration, $this->authorizationQuery($verifier));
        $code = devOidcMockIssueCode($this->configuration, $request, devOidcMockSubject('dev|owner', ''));

        self::assertSame('invalid_grant', devOidcMockToken($this->configuration, $this->tokenForm($code, 'wrong-verifier-' . str_repeat('c', 40)))['body']['error']);
        $code = devOidcMockIssueCode($this->configuration, $request, 'dev|owner');
        $result = devOidcMockToken($this->configuration, $this->tokenForm($code, $verifier));
        self::assertSame(200, $result['status']);
        self::assertSame('invalid_grant', devOidcMockToken($this->configuration, $this->tokenForm($code, $verifier))['body']['error'], 'A code was usable twice.');

        [$header, $payload, $signature] = explode('.', $result['body']['id_token']);
        $jwks = devOidcMockJwks(devOidcMockSigningKey($this->stateDirectory));
        $certificate = "-----BEGIN CERTIFICATE-----\n" . chunk_split($jwks['keys'][0]['x5c'][0], 64, "\n") . "-----END CERTIFICATE-----\n";
        self::assertSame(1, openssl_verify($header . '.' . $payload, base64_decode(strtr($signature, '-_', '+/')), $certificate, OPENSSL_ALGO_SHA256));
        self::assertSame($jwks['keys'][0]['kid'], json_decode(base64_decode(strtr($header, '-_', '+/')), true)['kid']);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        self::assertSame([$this->configuration['issuer'], 'dev|owner', $this->configuration['client_id'], 'nonce-1', true],
            [$claims['iss'], $claims['sub'], $claims['aud'], $claims['nonce'], $claims['email_verified']]);

        $code = devOidcMockIssueCode($this->configuration, $request, devOidcMockSubject(null, 'google-oauth2|Dev.Alice'));
        $typed = explode('.', devOidcMockToken($this->configuration, $this->tokenForm($code, $verifier))['body']['id_token'])[1];
        self::assertSame('google-oauth2-dev-alice@dev.invalid', json_decode(base64_decode(strtr($typed, '-_', '+/')), true)['email'], 'A typed subject cannot register without an email.');
    }

    public function testTheTokenEndpointRejectsAWrongClientOrRedirect(): void
    {
        $verifier = 'verifier-' . str_repeat('d', 43);
        $code = devOidcMockIssueCode($this->configuration, devOidcMockAuthorizationRequest($this->configuration, $this->authorizationQuery($verifier)), 'dev|owner');
        self::assertSame(401, devOidcMockToken($this->configuration, ['client_secret' => 'wrong'] + $this->tokenForm($code, $verifier))['status']);
        self::assertSame(400, devOidcMockToken($this->configuration, ['redirect_uri' => 'https://evil.example/cb'] + $this->tokenForm($code, $verifier))['status']);
    }

    public function testTheManagementApiNeedsItsTokenAndRecordsEachDeletion(): void
    {
        $credentials = ['grant_type' => 'client_credentials', 'client_id' => $this->configuration['client_id'], 'client_secret' => $this->configuration['client_secret']];
        self::assertSame(403, devOidcMockToken($this->configuration, $credentials + ['audience' => 'https://elsewhere/api/v2/'])['status']);
        $token = devOidcMockToken($this->configuration, $credentials + ['audience' => devOidcMockManagementAudience($this->configuration)])['body']['access_token'];

        self::assertSame(401, devOidcMockDeleteUser($this->configuration, '', rawurlencode('dev|second')));
        self::assertSame(401, devOidcMockDeleteUser($this->configuration, 'Bearer ' . str_repeat('x', 30), rawurlencode('dev|second')));
        self::assertSame(400, devOidcMockDeleteUser($this->configuration, 'Bearer ' . $token, rawurlencode('no-provider')));
        self::assertSame(204, devOidcMockDeleteUser($this->configuration, 'Bearer ' . $token, rawurlencode('dev|second')));
        self::assertStringEndsWith(" dev|second\n", (string) file_get_contents($this->stateDirectory . '/deleted-users.log'));
    }

    public function testSubjectsAndLogoutTargetsAreRestricted(): void
    {
        self::assertSame('dev|alice', devOidcMockSubject('dev|owner', ' dev|alice '));
        foreach (['', 'no-provider', "dev|a\nb", 'dev|' . str_repeat('a', 121)] as $subject) {
            try {
                devOidcMockSubject(null, $subject);
                self::fail('Accepted subject ' . json_encode($subject));
            } catch (DevOidcMockException) {
                self::addToAssertionCount(1);
            }
        }
        $base = $this->configuration['return_base'];
        self::assertSame($base . '/signin.php?x=1', devOidcMockLogoutTarget($this->configuration, $base . '/signin.php?x=1'));
        foreach (['https://evil.example/', $base . '.evil.example/', null] as $target) {
            self::assertSame($base . '/signin.php', devOidcMockLogoutTarget($this->configuration, $target));
        }
    }

    /** The exact values docker-compose.dev-auth.yml gives the application and the mock. */
    private static function composeEnvironment(): array
    {
        $compose = (string) file_get_contents(dirname(__DIR__, 2) . '/docker-compose.dev-auth.yml');
        $values = ['APP_ENV' => 'development', 'ATHER_DEV_OIDC_MOCK' => '1'];
        foreach (['PUBLIC_BASE_URL', 'EXPECTED_OIDC_ISSUER', 'OIDC_CLIENT_ID', 'OIDC_CLIENT_SECRET', 'OIDC_REDIRECT_URI'] as $name) {
            self::assertSame(1, preg_match('/^  ' . $name . ': (\S+)$/m', $compose, $match), "{$name} missing from the compose file");
            $values[$name] = $match[1];
        }

        return $values;
    }

    /** @return array<string, string> */
    private function authorizationQuery(string $verifier): array
    {
        return ['response_type' => 'code', 'client_id' => $this->configuration['client_id'], 'redirect_uri' => $this->configuration['redirect_uri'],
            'code_challenge_method' => 'S256', 'code_challenge' => devOidcBase64Url(hash('sha256', $verifier, true)), 'state' => 'state-1', 'nonce' => 'nonce-1'];
    }

    /** @return array<string, string> */
    private function tokenForm(string $code, string $verifier): array
    {
        return ['grant_type' => 'authorization_code', 'client_id' => $this->configuration['client_id'], 'client_secret' => $this->configuration['client_secret'],
            'redirect_uri' => $this->configuration['redirect_uri'], 'code' => $code, 'code_verifier' => $verifier];
    }
}
