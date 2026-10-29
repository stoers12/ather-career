<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../../includes/owner_session.php';
require_once __DIR__ . '/../../includes/auth0_oidc.php';
require_once __DIR__ . '/../../includes/csrf.php';

function logoutAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

/** No-follow HTTP to loopback only; never requests an Auth0 endpoint. */
function logoutRequest(int $port, string $method, string $path, string $cookie, array $fields = [], array $extraHeaders = []): array
{
    $context = stream_context_create(['http' => [
        'method' => $method, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 5,
        'header' => implode("\r\n", array_merge([
            'Cookie: ' . OWNER_SESSION_NAME . '=' . $cookie,
            'Content-Type: application/x-www-form-urlencoded',
        ], $extraHeaders)),
        'content' => $method === 'POST' ? http_build_query($fields) : '',
    ]]);
    $body = file_get_contents('http://127.0.0.1:' . $port . $path, false, $context);
    logoutAssert(is_string($body), 'Loopback logout request failed.');
    $headers = $http_response_header ?? [];
    preg_match('@^HTTP/\S+ ([0-9]{3})@', $headers[0] ?? '', $status);
    $result = ['status' => (int) ($status[1] ?? 0), 'body' => $body, 'headers' => []];
    foreach (array_slice($headers, 1) as $header) {
        if (str_contains($header, ':')) {
            [$name, $value] = explode(':', $header, 2);
            $result['headers'][strtolower($name)][] = trim($value);
        }
    }
    return $result;
}

function logoutSeed(bool $authenticated = true): array
{
    session_id('');
    startOwnerSession();
    if ($authenticated) {
        establishVerifiedInternalUserSession(101, 7);
        recordOwnerEmailVerificationAfterLogin(101, 7, true);
    }
    $csrf = getCsrfToken();
    $_SESSION[AUTH0_AUTH_TRANSACTION_KEY] = ['private_pending_logout_fixture' => true];
    $id = session_id();
    session_write_close();
    return [$id, $csrf];
}

function logoutStop(mixed &$process): void
{
    if (!is_resource($process)) { return; }
    proc_terminate($process);
    for ($i = 0; $i < 50 && proc_get_status($process)['running']; ++$i) { usleep(20000); }
    logoutAssert(!proc_get_status($process)['running'], 'Logout child server did not stop.');
    proc_close($process);
    $process = null;
}

$environment = null; $process = null; $exit = 0; $passes = []; $privateValues = [];
try {
    $environment = TestEnvironment::create();
    $root = $environment->namespaceRoot;
    mkdir($root . '/sessions', 0700);
    mkdir($root . '/public', 0700);
    symlink(dirname(__DIR__, 2), $root . '/app');
    RouteContract::writeEntryScripts($root . '/public');
    session_save_path($root . '/sessions');
    ini_set('session.cache_limiter', '');
    putenv('SESSION_COOKIE_SECURE=0');
    $settings = [
        'APP_ENV' => 'test', 'ATHERCAR_TEST_MODE' => '1', 'SESSION_COOKIE_SECURE' => '0',
        'EXPECTED_OIDC_ISSUER' => 'https://logout-provider.invalid/',
        'OIDC_CLIENT_ID' => 'logout-test-client', 'OIDC_CLIENT_SECRET' => 'private-logout-config-sentinel',
        'OIDC_REDIRECT_URI' => 'https://ather-logout.invalid/owner_oidc_callback.php',
        'PUBLIC_BASE_URL' => 'https://ather-logout.invalid',
    ];
    $privateValues[] = $settings['OIDC_CLIENT_SECRET'];
    $start = static function (array $overrides = []) use (&$process, $root, $settings): int {
        logoutStop($process);
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        logoutAssert(is_resource($socket), 'Logout loopback port unavailable.');
        $address = stream_socket_get_name($socket, false); fclose($socket);
        $port = (int) substr($address, strrpos($address, ':') + 1);
        // Explicit synthetic environment; no inherited tenant credentials/DB.
        $env = array_merge(array_intersect_key(getenv(), array_flip(['PATH', 'SystemRoot', 'TEMP', 'TMP'])), $settings, $overrides);
        $process = proc_open([
            PHP_BINARY, '-d', 'session.save_path=' . $root . '/sessions', '-d', 'display_errors=0',
            '-S', '127.0.0.1:' . $port, '-t', $root . '/public',
        ], [0 => ['pipe', 'r'], 1 => ['file', $root . '/private-http.log', 'a'], 2 => ['file', $root . '/private-http.log', 'a']], $pipes, null, $env);
        logoutAssert(is_resource($process), 'Logout child server unavailable.'); fclose($pipes[0]);
        for ($i = 0; $i < 100; ++$i) {
            $probe = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 0.1);
            if (is_resource($probe)) { fclose($probe); return $port; }
            usleep(20000);
        }
        throw new RuntimeException('Logout child readiness failed.');
    };
    $seed = static function (bool $authenticated = true) use (&$privateValues): array {
        $seed = logoutSeed($authenticated);
        array_push($privateValues, ...$seed);
        return $seed;
    };
    $assertRetired = static function (array $response, string $id) use ($root): void {
        clearstatcache();
        logoutAssert(!is_file($root . '/sessions/sess_' . $id), 'Logout retained the authenticated/readiness/pending session file.');
        $cookies = implode(';', $response['headers']['set-cookie'] ?? []);
        logoutAssert(str_contains($cookies, OWNER_SESSION_NAME . '=') && str_contains(strtolower($cookies), 'max-age=0'), 'Logout did not expire the local cookie.');
        logoutAssert(str_contains(implode(',', $response['headers']['cache-control'] ?? []), 'no-store'), 'Logout response is cacheable.');
    };

    $port = $start();
    [$id, $csrf] = $seed();
    $sessionBefore = file_get_contents($root . '/sessions/sess_' . $id);
    logoutAssert(str_contains($sessionBefore, INTERNAL_USER_SESSION_KEY) && str_contains($sessionBefore, OWNER_READINESS_SESSION_KEY), 'Logout fixture lacks actual authentication/readiness state.');
    foreach (['GET', 'HEAD', 'PUT', 'OPTIONS'] as $method) {
        $r = logoutRequest($port, $method, '/owner_logout.php', $id);
        logoutAssert($r['status'] === 405 && ($r['headers']['allow'][0] ?? null) === 'POST' && !isset($r['headers']['location']), 'Logout method boundary failed.');
    }
    foreach ([[], ['csrf_token' => 'wrong'], ['csrf_token' => ['malformed']]] as $fields) {
        $r = logoutRequest($port, 'POST', '/owner_logout.php', $id, $fields);
        logoutAssert($r['status'] === 403 && !isset($r['headers']['location']), 'Logout CSRF boundary failed.');
    }
    foreach ([['/owner_logout.php?returnTo=https://hostile.invalid', ['csrf_token' => $csrf]], ['/owner_logout.php', ['csrf_token' => $csrf, 'logout_endpoint' => 'https://hostile.invalid']]] as [$path, $fields]) {
        logoutAssert(logoutRequest($port, 'POST', $path, $id, $fields)['status'] === 403, 'Logout accepted request-controlled configuration.');
    }
    logoutAssert(file_get_contents($root . '/sessions/sess_' . $id) === $sessionBefore, 'Rejected logout changed local authority/readiness.');
    $passes[] = 'PASS logout real HTTP POST-only CSRF and strict request admission preserve rejected sessions';

    $r = logoutRequest($port, 'POST', '/owner_logout.php', $id, ['csrf_token' => $csrf], ['Host: hostile.invalid', 'X-Forwarded-Host: hostile.invalid', 'X-Forwarded-Proto: http']);
    $assertRetired($r, $id);
    $target = $r['headers']['location'][0] ?? '';
    parse_str((string) parse_url($target, PHP_URL_QUERY), $parameters);
    logoutAssert($r['status'] === 303 && count($r['headers']['location']) === 1
        && parse_url($target, PHP_URL_SCHEME) === 'https' && parse_url($target, PHP_URL_HOST) === 'logout-provider.invalid'
        && parse_url($target, PHP_URL_PATH) === '/v2/logout'
        && $parameters === ['client_id' => 'logout-test-client', 'returnTo' => 'https://ather-logout.invalid/signin.php'], 'Logout endpoint or exact non-federated parameter boundary failed.');
    logoutAssert(!str_contains($target, $settings['OIDC_CLIENT_SECRET']), 'Logout leaked private configuration.');
    $r = logoutRequest($port, 'GET', '/signin.php', $id);
    logoutAssert($r['status'] === 200 && !isset($r['headers']['location']) && !str_contains($r['body'], 'data-authenticated-entry='), 'Old cookie revived local authority or automatically started authentication.');
    $passes[] = 'PASS logout fixed trusted Auth0 endpoint client and Sign In return no federated token or request Host input';
    $passes[] = 'PASS logout destroys authentication readiness pending transaction and cookie; old-cookie Sign In remains anonymous';
    [$id, $csrf] = $seed(false);
    $r = logoutRequest($port, 'POST', '/owner_logout.php', $id, ['csrf_token' => $csrf]);
    logoutAssert($r['status'] === 303, 'Anonymous CSRF-authorized logout cannot retire an existing SSO session.'); $assertRetired($r, $id);

    foreach ([
        ['EXPECTED_OIDC_ISSUER' => ''], ['EXPECTED_OIDC_ISSUER' => 'http://logout-provider.invalid/'],
        ['EXPECTED_OIDC_ISSUER' => 'https://logout-provider.invalid/?endpoint=hostile'],
        ['EXPECTED_OIDC_ISSUER' => 'https://user:pass@logout-provider.invalid/'],
        ['EXPECTED_OIDC_ISSUER' => "https://logout-provider.invalid\r\nInjected/"],
        ['OIDC_CLIENT_ID' => ''], ['OIDC_CLIENT_SECRET' => ''], ['OIDC_REDIRECT_URI' => 'invalid'],
        ['PUBLIC_BASE_URL' => ''], ['PUBLIC_BASE_URL' => 'https://ather-logout.invalid/hostile'],
        ['PUBLIC_BASE_URL' => 'https://ather-logout.invalid?returnTo=hostile'],
        ['PUBLIC_BASE_URL' => 'http://external.invalid'], ['APP_ENV' => 'production', 'PUBLIC_BASE_URL' => 'http://localhost'],
    ] as $invalid) {
        $port = $start($invalid); [$id, $csrf] = $seed();
        $r = logoutRequest($port, 'POST', '/owner_logout.php', $id, ['csrf_token' => $csrf]);
        $assertRetired($r, $id);
        logoutAssert($r['status'] === 503 && !isset($r['headers']['location'])
            && str_contains($r['body'], 'You are signed out of Ather')
            && str_contains($r['body'], 'Auth0 sign-out could not be started')
            && str_contains($r['body'], 'انتهت جلسة أثر') && str_contains($r['body'], 'href="/signin.php"')
            && !str_contains($r['body'], $settings['OIDC_CLIENT_SECRET']) && !str_contains($r['body'], $csrf), 'Partial logout falsely claims remote success or leaks configuration.');
    }
    $passes[] = 'PASS logout missing invalid issuer client callback and public origin give private honest partial503 after retirement';

    logoutStop($process);
    $log = file_get_contents($root . '/private-http.log');
    foreach ($privateValues as $value) { logoutAssert(!str_contains($log, $value), 'Private logout value appeared in child logs.'); }
    $passes[] = 'PASS logout private HTTP logs exclude session CSRF and secret values';
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL logout ' . $error->getMessage() . "\n"); $exit = 1;
} finally {
    logoutStop($process);
    if ($environment !== null) {
        $environment->tearDown();
        logoutAssert($environment->wasTornDown(), 'Logout test namespace cleanup failed.');
    }
}
if ($exit === 0) {
    foreach ($passes as $pass) { echo $pass . "\n"; }
    echo "PASS logout complete private session log process and fixture cleanup; no provider request performed\n";
}
exit($exit);
