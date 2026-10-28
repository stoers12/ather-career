<?php

declare(strict_types=1);

// A loopback-only HTTPS discovery responder for the disposable CI web container.
if (PHP_SAPI !== 'cli'
    || getenv('ATHERCAR_CI_OIDC_MOCK') !== '1'
    || getenv('APP_ENV') !== 'production'
    || getenv('EXPECTED_OIDC_ISSUER') !== 'https://127.0.0.1:9443/'
    || !in_array(getenv('ATHERCAR_CI_OIDC_INVALID_METADATA'), [false, '1'], true)
    || ($argv[1] ?? null) !== '/tmp/ather-career-ci-oidc') {
    fwrite(STDERR, "CI OIDC mock configuration refused.\n");
    exit(1);
}

$directory = $argv[1];
if (!is_dir($directory) || is_link($directory)
    || !is_file($directory . '/cert.pem') || !is_file($directory . '/key.pem')) {
    fwrite(STDERR, "CI OIDC mock TLS material unavailable.\n");
    exit(1);
}
$context = stream_context_create(['ssl' => [
    'local_cert' => $directory . '/cert.pem',
    'local_pk' => $directory . '/key.pem',
    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
]]);
$server = @stream_socket_server('tls://127.0.0.1:9443', $errorCode, $errorMessage, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, "CI OIDC mock could not bind loopback TLS.\n");
    exit(1);
}
file_put_contents($directory . '/mock.pid', (string) getmypid(), LOCK_EX);
$issuer = 'https://127.0.0.1:9443/';
$body = json_encode([
    'issuer' => getenv('ATHERCAR_CI_OIDC_INVALID_METADATA') === '1' ? 'https://wrong-ci.invalid/' : $issuer,
    'authorization_endpoint' => $issuer . 'authorize',
    'token_endpoint' => $issuer . 'token',
    'jwks_uri' => $issuer . 'jwks',
], JSON_THROW_ON_ERROR);
$deadline = hrtime(true) + 300_000_000_000;
while (hrtime(true) < $deadline) {
    $acceptWarning = null;
    set_error_handler(static function (int $severity, string $message) use (&$acceptWarning): bool {
        $acceptWarning = $message;
        return true;
    });
    try {
        $client = stream_socket_accept($server, 2);
    } finally {
        restore_error_handler();
    }
    if ($client === false) {
        if (is_string($acceptWarning) && stripos($acceptWarning, 'timed out') !== false) {
            continue;
        }
        // The workflow deliberately presents an untrusted CA; its rejected TLS
        // handshake must not take down the discovery listener.
        if (is_string($acceptWarning) && stripos($acceptWarning, 'crypto') !== false) {
            continue;
        }
        fwrite(STDERR, "CI OIDC mock accept failed.\n");
        fclose($server);
        exit(1);
    }
    stream_set_timeout($client, 2);
    $request = fgets($client, 513);
    $host = null;
    $validHeaders = is_string($request) && strlen($request) < 512;
    $bytes = 0;
    while ($validHeaders && ($line = fgets($client, 1025)) !== false) {
        $bytes += strlen($line);
        if ($bytes > 8192 || strlen($line) > 1024) {
            $validHeaders = false;
            break;
        }
        if ($line === "\r\n") {
            break;
        }
        if (stripos($line, 'Host:') === 0) {
            if ($host !== null) {
                $validHeaders = false;
                break;
            }
            $host = trim(substr($line, 5));
        }
    }
    $allowed = $validHeaders && $request === "GET /.well-known/openid-configuration HTTP/1.1\r\n"
        && $host === '127.0.0.1:9443';
    $status = $allowed ? '200 OK' : '404 Not Found';
    $payload = $allowed ? $body : '';
    $response = "HTTP/1.1 {$status}\r\nContent-Type: application/json\r\nCache-Control: no-store\r\nConnection: close\r\nContent-Length: " . strlen($payload) . "\r\n\r\n" . $payload;
    fwrite($client, $response);
    fclose($client);
}
fclose($server);
