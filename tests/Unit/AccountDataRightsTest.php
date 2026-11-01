<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/account_data_rights.php';
require_once dirname(__DIR__, 2) . '/includes/auth0_oidc.php';
require_once dirname(__DIR__, 2) . '/tests/Support/zip-reader.php';

final class AccountDataRightsTest extends TestCase
{
    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach (['OIDC_MANAGEMENT_DOMAIN', 'OIDC_MANAGEMENT_CLIENT_ID', 'OIDC_MANAGEMENT_CLIENT_SECRET'] as $name) {
            putenv($name);
        }
    }

    public function testTheZipHoldsTextAndFilesByteForByte(): void
    {
        $image = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($image, random_bytes(70000));
        $stream = fopen('php://memory', 'w+b');
        $zip = new ZipStreamWriter($stream, 1700000000);
        $zip->addFromString('data.json', '{"name":"مؤمن"}');
        $zip->addFromString('empty.txt', '');
        $zip->addFromPath('images/projects/a.webp', $image);
        $zip->finish();
        rewind($stream);
        $entries = testReadZipEntries((string) stream_get_contents($stream));
        self::assertSame(['data.json', 'empty.txt', 'images/projects/a.webp'], array_keys($entries));
        self::assertSame('{"name":"مؤمن"}', $entries['data.json']);
        self::assertSame('', $entries['empty.txt']);
        self::assertSame(file_get_contents($image), $entries['images/projects/a.webp']);
        unlink($image);
    }

    public function testTheZipRejectsUnsafeOrDuplicateNames(): void
    {
        foreach (['', '/etc/passwd', '../x', 'a/../../x', 'a\\b', "a\nb", 'dir/', 'images/a.webp'] as $name) {
            $zip = new ZipStreamWriter(fopen('php://memory', 'w+b'));
            $zip->addFromString('images/a.webp', 'first');
            try {
                $zip->addFromString($name, 'x');
                self::fail('Unsafe or duplicate ZIP name was accepted: ' . json_encode($name));
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRecentAuthenticationLastsTenMinutes(): void
    {
        $now = 1_800_000_000;
        $_SESSION = [INTERNAL_USER_SESSION_KEY => ['internal_user_id' => 7, 'authz_version' => 1, 'authenticated_at' => $now - 600, 'last_activity_at' => $now]];
        self::assertTrue(accountSessionIsRecentlyAuthenticated($now));
        self::assertSame($now, accountRecentAuthenticationExpiresAt());
        self::assertFalse(accountSessionIsRecentlyAuthenticated($now + 1));
        $_SESSION = [];
        self::assertFalse(accountSessionIsRecentlyAuthenticated($now));
        self::assertNull(accountRecentAuthenticationExpiresAt());
    }

    public function testManagementClientDeletesWithOneTokenAndAcceptsAnAlreadyDeletedUser(): void
    {
        $client = $this->client([
            new Response(200, [], '{"access_token":"m2m-token","token_type":"Bearer"}'),
            new Response(204),
            new Response(404, [], '{"statusCode":404}'),
        ]);
        $client->eraseUser('google-oauth2|1234');
        $client->eraseUser('auth0|gone');
        self::assertCount(3, $this->history);
        $token = $this->history[0]['request'];
        self::assertSame('POST', $token->getMethod());
        self::assertSame('https://ather.eu.auth0.com/oauth/token', (string) $token->getUri());
        self::assertSame([
            'grant_type' => 'client_credentials', 'client_id' => 'm2m-id', 'client_secret' => 'm2m-secret',
            'audience' => 'https://ather.eu.auth0.com/api/v2/',
        ], json_decode((string) $token->getBody(), true));
        $delete = $this->history[1]['request'];
        self::assertSame('DELETE', $delete->getMethod());
        self::assertSame('https://ather.eu.auth0.com/api/v2/users/google-oauth2%7C1234', (string) $delete->getUri());
        self::assertSame('Bearer m2m-token', $delete->getHeaderLine('Authorization'));
        self::assertSame('https://ather.eu.auth0.com/api/v2/users/auth0%7Cgone', (string) $this->history[2]['request']->getUri());
    }

    public function testManagementClientFailuresAreSafeToLog(): void
    {
        $cases = [
            'token_rejected_401' => [new Response(401, [], '{"error":"access_denied"}')],
            'token_malformed' => [new Response(200, [], '{"token":"x"}')],
            'delete_rejected_403' => [new Response(200, [], '{"access_token":"t"}'), new Response(403, [], '{"message":"Insufficient scope"}')],
            'delete_rejected_500' => [new Response(200, [], '{"access_token":"t"}'), new Response(500)],
        ];
        foreach ($cases as $reason => $responses) {
            try {
                $this->client($responses)->eraseUser('auth0|secret-subject');
                self::fail('Failure was not reported: ' . $reason);
            } catch (Auth0ManagementException $exception) {
                self::assertSame($reason, $exception->safeReason);
                self::assertStringNotContainsString('secret', $exception->getMessage());
            }
        }
        $this->expectException(Auth0ManagementException::class);
        $this->client([])->eraseUser("auth0|bad\nsubject");
    }

    public function testManagementConfigurationComesOnlyFromTheEnvironment(): void
    {
        self::assertNull(Auth0ManagementClient::fromEnvironment());
        putenv('OIDC_MANAGEMENT_DOMAIN=Ather.EU.auth0.com');
        try {
            Auth0ManagementClient::fromEnvironment();
            self::fail('A partial configuration was accepted.');
        } catch (Auth0ManagementException $exception) {
            self::assertSame('configuration_invalid', $exception->safeReason);
        }
        putenv('OIDC_MANAGEMENT_CLIENT_ID=id');
        putenv('OIDC_MANAGEMENT_CLIENT_SECRET=secret');
        self::assertInstanceOf(Auth0ManagementClient::class, Auth0ManagementClient::fromEnvironment());
        putenv('OIDC_MANAGEMENT_DOMAIN=https://ather.eu.auth0.com/');
        $this->expectException(Auth0ManagementException::class);
        Auth0ManagementClient::fromEnvironment();
    }

    public function testTheReadableCopyIsEscaped(): void
    {
        $html = renderAccountExportReadableCopy(['skills' => [['skill_name' => '<script>alert(1)</script>']], 'projects' => []], 'ar', '2026-11-01T00:00:00Z');
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('dir="rtl"', $html);
        self::assertStringContainsString('المهارات', $html);
        self::assertStringNotContainsString('المشاريع', $html);
    }

    public function testASignInReturnsOnlyToAnAllowListedPage(): void
    {
        $base = ['state' => 's', 'code_verifier' => 'v', 'nonce' => 'n', 'created_at' => time(), 'purpose' => AUTH0_PURPOSE_SIGN_IN];
        foreach (['/owner_settings.php#data' => '/owner_settings.php#data', '/owner_account_deletion.php' => '/owner_account_deletion.php', 'https://evil.example/' => null, null => null] as $stored => $expected) {
            $_SESSION = [AUTH0_AUTH_TRANSACTION_KEY => $base + ['return_to' => $stored === '' ? null : $stored]];
            self::assertSame($expected, consumeAuth0AuthorizationTransaction(['state' => 's', 'code' => 'c'])['return_to']);
        }
        $_SESSION = [AUTH0_AUTH_TRANSACTION_KEY => $base];
        self::assertNull(consumeAuth0AuthorizationTransaction(['state' => 's', 'code' => 'c'])['return_to']);
    }

    /** @param list<Response> $responses */
    private function client(array $responses): Auth0ManagementClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new Auth0ManagementClient('ather.eu.auth0.com', 'm2m-id', 'm2m-secret', new Client(['handler' => $stack]));
    }
}
