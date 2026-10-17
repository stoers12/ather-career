<?php

declare(strict_types=1);

// Only the private, loopback test issuer serves these locally signed tokens.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/^ather_career_test_[a-f0-9]{24}$/', (string) getenv('DB_NAME')) !== 1
    || count($argv) !== 3 || !ctype_digit($argv[2]) || !is_file($argv[1] . '/mock-marker')) { exit(2); }
$root = $argv[1];
$issuer = 'https://registration-routing.test/';
$context = stream_context_create(['ssl' => ['local_cert' => $root . '/cert.pem', 'local_pk' => $root . '/tls-key.pem']]);
$server = stream_socket_server('tls://127.0.0.1:' . $argv[2], $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) { exit(2); }
$private = openssl_pkey_get_private(file_get_contents($root . '/signing-key.pem'));
$details = openssl_pkey_get_details($private);
$signingCertificate = preg_replace('/-----[^-]+-----|\s/', '', file_get_contents($root . '/signing-cert.pem'));
$b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
$deadline = time() + 300;
while (time() < $deadline) {
    $client = @stream_socket_accept($server, 1);
    if ($client === false) { continue; }
    stream_set_timeout($client, 3);
    $request = fgets($client, 2049);
    $length = 0;
    while (($line = fgets($client, 4097)) !== false && $line !== "\r\n") {
        if (stripos($line, 'Content-Length:') === 0) { $length = (int) trim(substr($line, 15)); }
    }
    if ($length < 0 || $length > 16384) { fclose($client); continue; }
    $body = '';
    while (strlen($body) < $length && !feof($client)) { $body .= (string) fread($client, $length - strlen($body)); }
    $status = '200 OK';
    if ($request === "GET /.well-known/openid-configuration HTTP/1.1\r\n") {
        $reply = ['issuer' => $issuer, 'authorization_endpoint' => $issuer . 'authorize', 'token_endpoint' => $issuer . 'token', 'jwks_uri' => $issuer . 'jwks'];
    } elseif ($request === "GET /jwks HTTP/1.1\r\n") {
        $reply = ['keys' => [['kty' => 'RSA', 'kid' => 'registration-test-key', 'use' => 'sig', 'alg' => 'RS256', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e']), 'x5c' => [$signingCertificate]]]];
    } elseif ($request === "POST /token HTTP/1.1\r\n") {
        $count = (int) @file_get_contents($root . '/token-count');
        file_put_contents($root . '/token-count', (string) ($count + 1));
        parse_str($body, $form);
        $code = $form['code'] ?? '';
        $fixturePath = $root . '/code-' . hash('sha256', is_string($code) ? $code : '') . '.json';
        $fixture = is_file($fixturePath) ? json_decode(file_get_contents($fixturePath), true, 32, JSON_THROW_ON_ERROR) : null;
        if (!is_array($fixture) || ($form['grant_type'] ?? null) !== 'authorization_code'
            || ($form['client_id'] ?? null) !== getenv('OIDC_CLIENT_ID') || ($form['client_secret'] ?? null) !== getenv('OIDC_CLIENT_SECRET')
            || ($form['redirect_uri'] ?? null) !== getenv('OIDC_REDIRECT_URI')
            || !is_string($form['code_verifier'] ?? null)
            || !hash_equals($fixture['challenge'], $b64(hash('sha256', $form['code_verifier'], true)))) {
            $status = '400 Bad Request'; $reply = ['error' => 'invalid_grant'];
        } else {
            unlink($fixturePath);
            $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'registration-test-key'], JSON_THROW_ON_ERROR));
            $payload = $b64(json_encode($fixture['claims'], JSON_THROW_ON_ERROR));
            openssl_sign($header . '.' . $payload, $signature, $private, OPENSSL_ALGO_SHA256);
            if ($fixture['bad_signature'] ?? false) { $signature = str_repeat('x', strlen($signature)); }
            $reply = ['id_token' => $header . '.' . $payload . '.' . $b64($signature), 'token_type' => 'Bearer'];
        }
    } else { $status = '404 Not Found'; $reply = ['error' => 'not_found']; }
    $json = json_encode($reply, JSON_THROW_ON_ERROR);
    fwrite($client, "HTTP/1.1 {$status}\r\nContent-Type: application/json\r\nCache-Control: no-store\r\nConnection: close\r\nContent-Length: " . strlen($json) . "\r\n\r\n" . $json);
    fclose($client);
}
fclose($server);
