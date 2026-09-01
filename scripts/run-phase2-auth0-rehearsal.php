<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth0_identity.php';
require_once __DIR__ . '/../includes/owner_flow.php';
require_once __DIR__ . '/../includes/owner_session.php';
require_once __DIR__ . '/../includes/rate_limit.php';

function auth0RehearsalAssertDenied(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Auth0OidcException) {
        return;
    }
    throw new RuntimeException($message);
}

/** @return list<int> */
function auth0RehearsalConcurrentUsers(string $subject): array
{
    $processes = [];
    for ($index = 0; $index < 2; $index++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __DIR__ . '/run-phase2-auth0-identity-worker.php', $subject], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Auth0 identity worker could not start.');
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    $users = [];
    foreach ($processes as [$process, $pipes]) {
        $output = trim((string) stream_get_contents($pipes[1]));
        stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0 || !ctype_digit($output)) throw new RuntimeException('Auth0 identity worker failed.');
        $users[] = (int) $output;
    }
    return $users;
}

$environment = null;
try {
    $environment = TestEnvironment::create();
    putenv('RATE_LIMIT_STATE_DIR=' . $environment->storageRoot . '/rate-limit');
    $database = getDatabaseConnection();
    phase2AssertSame('public_lifecycle', $database->query("SELECT name FROM schema_migrations WHERE version = '005'")->fetchColumn(), 'Auth0 rehearsal requires Phase-2 migration 005.');
    $configuration = new Auth0OidcConfiguration('https://test-tenant.us.auth0.com/', 'test-tenant.us.auth0.com', 'test-client', 'test-secret', 'https://app.example.test/owner_oidc_callback.php');
    startOwnerSession();

    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    parse_str((string) parse_url($start['url'], PHP_URL_QUERY), $parameters);
    phase2AssertSame('code', $parameters['response_type'] ?? null, 'Auth0 start did not request an authorization code.');
    phase2AssertSame('S256', $parameters['code_challenge_method'] ?? null, 'Auth0 start did not request PKCE S256.');
    phase2AssertSame($start['state'], $parameters['state'] ?? null, 'Auth0 start state was not retained.');
    phase2AssertSame($start['code_challenge'], $parameters['code_challenge'] ?? null, 'Auth0 challenge was not retained.');
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => 'wrong', 'code' => 'code']), 'Mismatched state was accepted.');
    $_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['created_at'] = time() - AUTH0_AUTH_TRANSACTION_TTL_SECONDS - 1;
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'code']), 'Expired transaction was accepted.');

    $validator = static fn (Auth0OidcConfiguration $config, string $code, string $verifier, string $nonce): Auth0ValidatedIdentity => new Auth0ValidatedIdentity($config->issuer, 'auth0|new-subject');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    $identity = completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'code'], $validator);
    phase2AssertSame('auth0|new-subject', $identity->subject, 'Validated Auth0 subject changed.');
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'code'], $validator), 'Replayed callback was accepted.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'error' => 'access_denied'], $validator), 'Provider error was accepted.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'code'], static fn () => new Auth0ValidatedIdentity('https://other.us.auth0.com/', 'auth0|new')), 'Issuer mismatch was accepted.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'code'], static fn (Auth0OidcConfiguration $config) => new Auth0ValidatedIdentity($config->issuer, '')), 'Missing subject was accepted.');

    $created = resolveAuth0InternalUser($database, $configuration, $identity);
    phase2Assert(!ownerHasPortfolio($database, AuthenticatedUserContext::fromValidatedUser($created['user_id'])), 'New Auth0 User received an automatic Portfolio.');
    phase2AssertSame($created, resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, 'auth0|new-subject')), 'Known subject did not resolve to the existing User.');
    $concurrentSubject = 'auth0|race-' . bin2hex(random_bytes(6));
    $concurrentUsers = auth0RehearsalConcurrentUsers($concurrentSubject);
    phase2AssertSame(1, count(array_unique($concurrentUsers)), 'Concurrent first Auth0 login created duplicate Users.');

    $disabledSubject = 'auth0|disabled-' . bin2hex(random_bytes(6));
    $database->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:issuer, :subject, 'disabled', 4)")->execute(['issuer' => $configuration->issuer, 'subject' => $disabledSubject]);
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, $disabledSubject)), 'Disabled Auth0 User regained authority.');

    $oldSessionId = session_id();
    establishVerifiedInternalUserSession($created['user_id'], $created['authz_version']);
    phase2Assert(session_id() !== $oldSessionId && currentInternalUserSession() !== null, 'Auth0 session establishment did not rotate authority.');
    destroyOwnerSession();
    phase2AssertSame(null, currentInternalUserSession(), 'Owner logout retained local authority.');

    $_SERVER['REMOTE_ADDR'] = '198.51.100.99';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';
    clearRateLimit('oidc_start', rateLimitClientIp());
    $allowed = 0;
    for ($attempt = 0; $attempt < 6; $attempt++) if (consumeRateLimit('oidc_start', rateLimitClientIp(), 5, 300)['allowed']) $allowed++;
    phase2AssertSame(5, $allowed, 'OIDC start limiter was bypassed.');
    phase2AssertSame('198.51.100.99', rateLimitClientIp(), 'Forwarded header changed OIDC limiter authority.');
    echo "PASS T-AUTH0-PKCE-CALLBACK-IDENTITY-SESSION-LIMITER\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL Auth0 OIDC rehearsal: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) destroyOwnerSession();
    putenv('RATE_LIMIT_STATE_DIR');
    if ($environment instanceof TestEnvironment) $environment->tearDown();
}
