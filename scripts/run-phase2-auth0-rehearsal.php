<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth0_identity.php';
require_once __DIR__ . '/../includes/csrf.php';
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

function auth0RehearsalAssertReason(callable $operation, string $expected, string $message): void
{
    try {
        $operation();
    } catch (Auth0OidcException $exception) {
        phase2AssertSame($expected, $exception->safeReason, $message);
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
    auth0RehearsalAssertReason(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'error' => 'access_denied', 'error_description' => 'synthetic private provider text'], $validator), 'authorization_denied', 'Valid provider denial was not classified safely.');
    auth0RehearsalAssertReason(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'error' => 'access_denied']), 'transaction_missing', 'Consumed denial was reusable.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    auth0RehearsalAssertReason(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'error' => 'server_error']), 'provider_error', 'Unexpected provider error was treated as cancellation.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    auth0RehearsalAssertReason(static fn () => completeAuth0Authorization($configuration, ['state' => $start['state'], 'error' => 'access_denied', 'code' => 'synthetic']), 'provider_error', 'Malformed denial with code was treated as cancellation.');
    $start = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize');
    auth0RehearsalAssertReason(static fn () => completeAuth0Authorization($configuration, ['state' => 'wrong', 'error' => 'access_denied']), 'state_mismatch', 'Invalid state was treated as cancellation.');
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
    $usersBeforeSelection = (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $bindingsBeforeSelection = (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn();
    $portfoliosBeforeSelection = (int) $database->query('SELECT COUNT(*) FROM portfolios')->fetchColumn();

    // Two independent browser sessions must keep separate local authority.
    $browserA = session_id();
    session_write_close();
    session_id('');
    startOwnerSession();
    establishVerifiedInternalUserSession($foreignUserId, 1);
    $browserB = session_id();
    phase2Assert($browserA !== $browserB, 'Two browsers shared a session.');
    session_write_close();
    session_id($browserA);
    startOwnerSession();
    phase2AssertSame($knownId, currentInternalUserSession()['internal_user_id'] ?? null, 'Original browser lost its Owner identity.');

    beginFreshOwnerAccountSelectionSession();
    $selectionSession = session_id();
    phase2Assert($selectionSession !== $browserA && $selectionSession !== $browserB, 'Account selection reused a browser session.');
    phase2AssertSame(null, currentInternalUserSession(), 'Wrong-account authority survived account selection.');
    $selection = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize', 'select_account');
    parse_str((string) parse_url($selection['url'], PHP_URL_QUERY), $selectionParameters);
    phase2AssertSame('select_account', $selectionParameters['prompt'] ?? null, 'Account chooser prompt was missing.');
    phase2AssertSame('S256', $selectionParameters['code_challenge_method'] ?? null, 'Account selection lost PKCE.');
    phase2AssertSame(null, $parameters['prompt'] ?? null, 'Ordinary login unexpectedly forced account selection.');
    try {
        beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize', 'login');
        throw new RuntimeException('Unapproved OIDC prompt was accepted.');
    } catch (InvalidArgumentException) {
        // The only optional prompt is the fixed account chooser.
    }
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => 'wrong', 'code' => 'code']), 'Selection accepted an old or mismatched state.');
    $selectedIdentity = completeAuth0Authorization($configuration, ['state' => $selection['state'], 'code' => 'code'],
        static fn (Auth0OidcConfiguration $config): Auth0ValidatedIdentity => new Auth0ValidatedIdentity($config->issuer, $foreignSubject));
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $selection['state'], 'code' => 'code']), 'Account selection replay was accepted.');
    $selectedUser = resolveAuth0InternalUser($database, $configuration, $selectedIdentity);
    phase2AssertSame($foreignUserId, $selectedUser['user_id'], 'Switch resolved the wrong binding.');
    establishVerifiedInternalUserSession($selectedUser['user_id'], $selectedUser['authz_version']);
    phase2Assert(session_id() !== $selectionSession, 'Selected-account callback did not rotate the session.');
    phase2AssertSame($foreignPortfolio, requireOwnedPortfolioContext($database)->portfolioId, 'Switched account reached the wrong Portfolio.');

    $selectedBrowserA = session_id();
    session_write_close();
    session_id($browserB);
    startOwnerSession();
    phase2AssertSame($foreignUserId, currentInternalUserSession()['internal_user_id'] ?? null, 'Switch in one browser changed the second browser.');
    phase2AssertSame($foreignPortfolio, requireOwnedPortfolioContext($database)->portfolioId, 'Second browser crossed Portfolio ownership.');
    session_write_close();
    session_id($selectedBrowserA);
    startOwnerSession();
    beginFreshOwnerAccountSelectionSession();
    $returnSelection = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize', 'select_account');
    $returnIdentity = completeAuth0Authorization($configuration, ['state' => $returnSelection['state'], 'code' => 'code'],
        static fn (Auth0OidcConfiguration $config): Auth0ValidatedIdentity => new Auth0ValidatedIdentity($config->issuer, $identity->subject));
    $returnedUser = resolveAuth0InternalUser($database, $configuration, $returnIdentity);
    establishVerifiedInternalUserSession($returnedUser['user_id'], $returnedUser['authz_version']);
    phase2AssertSame($knownPortfolio, requireOwnedPortfolioContext($database)->portfolioId, 'Selecting the original account did not restore its Portfolio.');

    beginFreshOwnerAccountSelectionSession();
    $cancelled = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize', 'select_account');
    auth0RehearsalAssertReason(static fn () => completeAuth0Authorization($configuration, ['state' => $cancelled['state'], 'error' => 'access_denied']), 'authorization_denied', 'Valid cancellation was not recognized.');
    auth0RehearsalAssertReason(static fn () => completeAuth0Authorization($configuration, ['state' => $cancelled['state'], 'code' => 'code']), 'transaction_missing', 'Cancelled transaction was reusable.');
    phase2AssertSame(null, currentInternalUserSession(), 'Cancellation restored old account authority.');
    $cancelledSession = session_id();
    beginFreshOwnerAccountSelectionSession();
    phase2Assert($cancelledSession !== session_id(), 'Cancellation recovery reused an anonymous session.');
    phase2AssertSame(null, currentInternalUserSession(), 'Cancellation recovery restored old authority.');
    $freshCsrf = getCsrfToken();
    phase2AssertSame(64, strlen($freshCsrf), 'Recovery did not create a fresh anonymous CSRF token.');
    $retry = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize', 'select_account');
    phase2Assert($retry['state'] !== $cancelled['state'], 'Retry reused the consumed OIDC state.');
    parse_str((string) parse_url($retry['url'], PHP_URL_QUERY), $retryParameters);
    phase2AssertSame('select_account', $retryParameters['prompt'] ?? null, 'Recovery retry lost the account chooser prompt.');
    auth0RehearsalAssertReason(static fn () => completeAuth0Authorization($configuration, ['state' => $retry['state'], 'error' => 'access_denied']), 'authorization_denied', 'Second cancellation did not terminate the retry safely.');
    phase2AssertSame(null, currentInternalUserSession(), 'Second cancellation restored Owner authority.');
    $expired = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize', 'select_account');
    $_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['created_at'] = time() - AUTH0_AUTH_TRANSACTION_TTL_SECONDS - 1;
    auth0RehearsalAssertDenied(static fn () => completeAuth0Authorization($configuration, ['state' => $expired['state'], 'code' => 'code']), 'Expired selection transaction was accepted.');
    $unknown = beginAuth0Authorization($configuration, 'https://test-tenant.us.auth0.com/authorize', 'select_account');
    $unknownIdentity = completeAuth0Authorization($configuration, ['state' => $unknown['state'], 'code' => 'code'],
        static fn (Auth0OidcConfiguration $config): Auth0ValidatedIdentity => new Auth0ValidatedIdentity($config->issuer, 'unknown-phase2b'));
    auth0RehearsalAssertDenied(static fn () => resolveAuth0InternalUser($database, $configuration, $unknownIdentity), 'Unknown selection identity created a User.');
    phase2AssertSame(null, currentInternalUserSession(), 'Unknown selection gained a session.');
    destroyOwnerSession();
    session_id($browserA);
    startOwnerSession();
    phase2AssertSame(null, currentInternalUserSession(), 'Back-button or retired session regained Owner authority.');
    destroyOwnerSession();
    session_id($browserB);
    startOwnerSession();
    phase2AssertSame($foreignUserId, currentInternalUserSession()['internal_user_id'] ?? null, 'Second browser was invalidated by first browser switch.');
    destroyOwnerSession();
    phase2AssertSame(null, currentInternalUserSession(), 'Owner logout retained local authority.');
    phase2AssertSame($usersBeforeSelection, (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'Account selection created or merged a User.');
    phase2AssertSame($bindingsBeforeSelection, (int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn(), 'Account selection linked an identity.');
    phase2AssertSame($portfoliosBeforeSelection, (int) $database->query('SELECT COUNT(*) FROM portfolios')->fetchColumn(), 'Account selection created or reassigned a Portfolio.');

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
