<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth0_identity.php';
require_once __DIR__ . '/../includes/owner_flow.php';
require_once __DIR__ . '/../includes/owner_session.php';
require_once __DIR__ . '/../includes/portfolio_scoped_data.php';
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
    phase2AssertSame('project_technologies', $database->query("SELECT name FROM schema_migrations WHERE version = '006'")->fetchColumn(), 'Auth0 rehearsal requires Phase-2 migration 006.');
    $configuration = new Auth0OidcConfiguration('https://test-tenant.us.auth0.com/', 'test-tenant.us.auth0.com', 'test-client', 'test-secret', 'https://app.example.test/owner_oidc_callback.php');
    startOwnerSession();

    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    parse_str((string) parse_url($start['url'], PHP_URL_QUERY), $parameters);
    phase2AssertSame('code', $parameters['response_type'] ?? null, 'Auth0 start did not request an authorization code.');
    phase2AssertSame('S256', $parameters['code_challenge_method'] ?? null, 'Auth0 start did not request PKCE S256.');
    phase2AssertSame($start['state'], $parameters['state'] ?? null, 'Auth0 start state was not retained.');
    phase2AssertSame($start['code_challenge'], $parameters['code_challenge'] ?? null, 'Auth0 challenge was not retained.');
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => 'wrong', 'code' => 'code']), 'Mismatched state was accepted.');
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['code' => 'code']), 'Missing state was accepted.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['nonce']);
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'code']), 'Missing nonce was accepted.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    $_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['nonce'] = [];
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'code']), 'Invalid nonce was accepted.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['code_verifier']);
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'code']), 'Missing PKCE verifier was accepted.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    $_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['code_verifier'] = [];
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'code']), 'Invalid PKCE verifier was accepted.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
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

    phase2Assert(identityRepositoryHasBindingsTable($database), 'Auth0 cutover requires Migration 014.');
    $beforeUsers = (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $beforeIdentities = (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn();
    $beforePortfolios = (int) $database->query('SELECT COUNT(*) FROM portfolios')->fetchColumn();
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, $identity), 'Unknown identity created an account.');
    phase2AssertSame($beforeUsers, (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'Unknown identity changed Users.');
    phase2AssertSame($beforeIdentities, (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn(), 'Unknown identity created or linked an identity.');
    phase2AssertSame($beforePortfolios, (int) $database->query('SELECT COUNT(*) FROM portfolios')->fetchColumn(), 'Unknown identity created a Portfolio.');
    $database->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:issuer, :subject, 'active', 1)")
        ->execute(['issuer' => $configuration->issuer, 'subject' => $identity->subject]);
    $knownId = (int) $database->lastInsertId();
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, $identity), 'Legacy-only identity authenticated without a verified binding.');
    $database->prepare('INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject) VALUES (:user_id, :issuer, :subject)')
        ->execute(['user_id' => $knownId, 'issuer' => $configuration->issuer, 'subject' => $identity->subject]);
    $resolved = resolveAuth0InternalUser($database, $configuration, $identity);
    phase2AssertSame($knownId, $resolved['user_id'], 'Exact binding did not resolve its original User.');
    phase2Assert(!ownerHasPortfolio($database, AuthenticatedUserContext::fromValidatedUser($resolved['user_id'])), 'Identity resolution granted a Portfolio.');
    $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:user_id)')->execute(['user_id' => $knownId]);
    $knownPortfolio = (int) $database->lastInsertId();
    $syntheticEmail = 'matching-profile@phase2a.invalid';
    $database->prepare('INSERT INTO personal_info (portfolio_id, full_name, email) VALUES (:portfolio_id, :name, :email)')
        ->execute(['portfolio_id' => $knownPortfolio, 'name' => 'Synthetic Owner', 'email' => $syntheticEmail]);
    phase2AssertSame($syntheticEmail, $database->query('SELECT email FROM personal_info WHERE portfolio_id = ' . $knownPortfolio)->fetchColumn(), 'Matching-email fixture was not established.');
    $foreignSubject = 'auth0|foreign-' . bin2hex(random_bytes(6));
    $database->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:issuer, :subject, 'active', 1)")
        ->execute(['issuer' => $configuration->issuer, 'subject' => $foreignSubject]);
    $foreignUserId = (int) $database->lastInsertId();
    $database->prepare('INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject) VALUES (:user_id, :issuer, :subject)')
        ->execute(['user_id' => $foreignUserId, 'issuer' => $configuration->issuer, 'subject' => $foreignSubject]);
    $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:user_id)')->execute(['user_id' => $foreignUserId]);
    $foreignPortfolio = (int) $database->lastInsertId();
    $database->prepare('INSERT INTO personal_info (portfolio_id, full_name) VALUES (:portfolio_id, :name)')
        ->execute(['portfolio_id' => $foreignPortfolio, 'name' => 'Synthetic Foreign Owner']);
    $foreignProfileId = (int) $database->lastInsertId();
    phase2AssertSame($foreignUserId, resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, $foreignSubject))['user_id'], 'Foreign binding resolved to the wrong User.');
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, $syntheticEmail)), 'Matching profile email authenticated without an identity binding.');
    phase2AssertSame($resolved, resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, $identity->subject)), 'Known binding did not resolve consistently.');
    $concurrentUsers = auth0RehearsalConcurrentUsers($identity->subject);
    phase2AssertSame([$knownId, $knownId], $concurrentUsers, 'Concurrent read-only identity lookups did not resolve the same User.');
    $otherIssuer = new Auth0OidcConfiguration('https://other-tenant.invalid/', 'other-tenant.invalid', 'test-client', 'test-secret', $configuration->redirectUri);
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $otherIssuer, new Auth0ValidatedIdentity($otherIssuer->issuer, $identity->subject)), 'Known subject under a different issuer authenticated.');
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, 'unknown-subject')), 'Unknown subject authenticated.');
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, str_repeat('x', 256))), 'Oversized subject authenticated.');
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, '')), 'Empty subject authenticated.');
    $oversizedIssuer = new Auth0OidcConfiguration('https://' . str_repeat('x', 2049), 'invalid', 'test-client', 'test-secret', $configuration->redirectUri);
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $oversizedIssuer, new Auth0ValidatedIdentity($oversizedIssuer->issuer, $identity->subject)), 'Oversized issuer authenticated.');
    $database->prepare('UPDATE user_identities SET oidc_subject = :subject WHERE user_id = :user_id')
        ->execute(['subject' => 'synthetic-conflict', 'user_id' => $knownId]);
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, 'synthetic-conflict')), 'Binding that conflicts with legacy columns authenticated.');
    $database->prepare('UPDATE user_identities SET oidc_subject = :subject WHERE user_id = :user_id')
        ->execute(['subject' => $identity->subject, 'user_id' => $knownId]);

    $disabledSubject = 'auth0|disabled-' . bin2hex(random_bytes(6));
    $database->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:issuer, :subject, 'disabled', 4)")->execute(['issuer' => $configuration->issuer, 'subject' => $disabledSubject]);
    $database->prepare('INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject) VALUES (:user_id, :issuer, :subject)')
        ->execute(['user_id' => (int) $database->lastInsertId(), 'issuer' => $configuration->issuer, 'subject' => $disabledSubject]);
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($configuration->issuer, $disabledSubject)), 'Disabled Auth0 User regained authority.');

    $oldSessionId = session_id();
    $cookie = session_get_cookie_params();
    phase2Assert(($cookie['httponly'] ?? null) === true && ($cookie['samesite'] ?? null) === 'Lax'
        && ($cookie['secure'] ?? null) === applicationSessionCookieIsSecure(), 'Secure session cookie properties changed.');
    establishVerifiedInternalUserSession($resolved['user_id'], $resolved['authz_version']);
    phase2Assert(session_id() !== $oldSessionId && currentInternalUserSession() !== null, 'Auth0 session establishment did not rotate authority.');
    $_GET['portfolio_id'] = (string) $foreignPortfolio;
    $ownerContext = requireOwnedPortfolioContext($database);
    phase2AssertSame($knownId, $ownerContext->userId, 'Callback-resolved session changed Owner identity.');
    phase2AssertSame($knownPortfolio, $ownerContext->portfolioId, 'A forged Portfolio selector crossed Owner boundaries.');
    phase2AssertSame(null, findAuthorizedPersonalInfo($database, $ownerContext, $foreignProfileId), 'Callback-resolved Owner read another Portfolio profile.');
    phase2AssertSame($syntheticEmail, loadAuthorizedPersonalInfo($database, $ownerContext)['email'] ?? null, 'Callback-resolved Owner lost access to the owned profile.');
    unset($_GET['portfolio_id']);
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
