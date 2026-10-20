<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/^ather_career_test_[a-f0-9]{24}$/', (string) getenv('DB_NAME')) !== 1
    || getenv('DB_NAME') !== getenv('ATHERCAR_TEST_DB_NAME')) { exit(2); }
require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth0_oidc.php';
require_once __DIR__ . '/../includes/owner_session.php';
require_once __DIR__ . '/../includes/rate_limit.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

function routingAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function routingPort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($socket === false) { throw new RuntimeException('Loopback port unavailable.'); }
    $name = stream_socket_get_name($socket, false); fclose($socket);
    return (int) substr($name, strrpos($name, ':') + 1);
}

function routingProcess(array $command, array $environment, string $log): mixed
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, null, $environment);
    if (!is_resource($process)) { throw new RuntimeException('Isolated child process unavailable.'); }
    fclose($pipes[0]);
    return $process;
}

function routingStop(mixed &$process): void
{
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $process = null;
}

function routingStartApp(?string $switch): void
{
    global $appProcess, $serverEnvironment, $root, $appPort;
    routingStop($appProcess);
    $env = $serverEnvironment;
    if ($switch === null) { unset($env['ATHERCAR_REGISTRATION_ENABLED']); }
    else { $env['ATHERCAR_REGISTRATION_ENABLED'] = $switch; }
    $appProcess = routingProcess([PHP_BINARY, '-d', 'session.save_path=' . $root . '/sessions', '-d', 'openssl.cafile=' . $root . '/cert.pem',
        '-d', 'display_errors=0', '-S', '127.0.0.1:' . $appPort, '-t', $root . '/http/public', $root . '/router.php'], $env, $root . '/http-private.log');
    $ready = false;
    for ($i = 0; $i < 40; $i++) {
        try { $response = (new Client(['timeout' => 1, 'http_errors' => false]))->get('http://127.0.0.1:' . $appPort . '/signup.php'); $ready = $response->getStatusCode() === 200; }
        catch (Throwable) { $ready = false; }
        if ($ready) { break; }
        usleep(50000);
    }
    routingAssert($ready, 'Isolated application did not start.');
}

function routingBrowser(): Client
{
    global $appPort;
    return new Client(['base_uri' => 'http://127.0.0.1:' . $appPort, 'cookies' => new CookieJar(), 'allow_redirects' => false, 'http_errors' => false, 'timeout' => 12]);
}

function routingCookie(Client $browser): ?string
{
    $cookie = $browser->getConfig('cookies')->getCookieByName(OWNER_SESSION_NAME);
    return $cookie === null ? null : $cookie->getValue();
}

function routingSession(Client $browser, ?callable $mutate = null): array
{
    global $root;
    $id = routingCookie($browser);
    if ($id === null || !is_file($root . '/sessions/sess_' . $id)) { return []; }
    session_id($id);
    routingAssert(session_start(), 'Test session inspection failed.');
    if ($mutate !== null) { $mutate(); }
    $state = $_SESSION;
    session_write_close();
    return $state;
}

function routingCsrf(Client $browser): string
{
    $response = $browser->get('/signup.php?lang=en');
    routingAssert($response->getStatusCode() === 200 && preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', (string) $response->getBody(), $m) === 1, 'Enabled signup must expose a CSRF-protected Google form.');
    return $m[1];
}

function routingStart(Client $browser, bool $registration = true, bool $resetLimiter = true): array
{
    if ($resetLimiter) { clearRateLimit('oidc_start', '127.0.0.1'); }
    $old = routingCookie($browser);
    $response = $registration
        ? $browser->post('/owner_register.php', ['form_params' => ['csrf_token' => routingCsrf($browser)]])
        : $browser->get('/owner_login.php?method=google&purpose=registration');
    routingAssert($response->getStatusCode() === 302, 'Authorization start did not redirect.');
    parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $query);
    $state = routingSession($browser);
    $transaction = $state[AUTH0_AUTH_TRANSACTION_KEY] ?? [];
    routingAssert(($transaction['purpose'] ?? null) === ($registration ? AUTH0_PURPOSE_REGISTRATION : AUTH0_PURPOSE_SIGN_IN), 'Purpose did not come from the server entry point.');
    routingAssert(($query['connection'] ?? null) === 'google-oauth2' && ($query['scope'] ?? null) === 'openid email' && ($query['code_challenge_method'] ?? null) === 'S256', 'Google scope/connection/PKCE contract changed.');
    routingAssert(!isset($query['purpose']) && ($query['nonce'] ?? null) === ($transaction['nonce'] ?? null), 'Purpose leaked into provider input or nonce binding changed.');
    if ($registration && $old !== null) { routingAssert($old !== routingCookie($browser), 'Registration start did not rotate the anonymous session.'); }
    return $query;
}

function routingIssue(array $start, string $subject, array $overrides = [], bool $badSignature = false): string
{
    global $root, $issuer;
    $claims = array_replace(['iss' => $issuer, 'sub' => $subject, 'aud' => 'registration-routing-client', 'iat' => time() - 5,
        'exp' => time() + 300, 'nonce' => $start['nonce'], 'email' => 'synthetic@example.invalid', 'email_verified' => true], $overrides);
    foreach ($claims as $key => $value) { if ($value === '__omit__') { unset($claims[$key]); } }
    $code = bin2hex(random_bytes(20));
    file_put_contents($root . '/code-' . hash('sha256', $code) . '.json', json_encode(['claims' => $claims, 'challenge' => $start['code_challenge'], 'bad_signature' => $badSignature], JSON_THROW_ON_ERROR));
    return $code;
}

function routingCallback(Client $browser, array $start, string $subject, array $claims = [], array $query = [], bool $badSignature = false): ResponseInterface
{
    return $browser->get('/owner_oidc_callback.php', ['query' => array_replace(['state' => $start['state'], 'code' => routingIssue($start, $subject, $claims, $badSignature)], $query)]);
}

function routingRows(PDO $database, bool $protectedOnly = false): array
{
    $result = [];
    foreach ($database->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if ($protectedOnly && in_array($table, ['users', 'user_identities'], true)) { continue; }
        $rows = $database->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
        $encoded = array_map('serialize', $rows); sort($encoded);
        $result[$table] = hash('sha256', serialize($encoded));
    }
    return $result;
}

function routingDenied(PDO $database, Client $browser, callable $request, array $statuses = [403]): ResponseInterface
{
    $before = routingRows($database);
    $response = $request();
    routingAssert(in_array($response->getStatusCode(), $statuses, true), 'Unsafe route request was not denied with its expected status.');
    routingAssert($before === routingRows($database), 'Denied route request mutated database rows.');
    $state = routingSession($browser);
    routingAssert(!array_key_exists(INTERNAL_USER_SESSION_KEY, $state) && !array_key_exists(OWNER_READINESS_SESSION_KEY, $state), 'Denied callback retained authentication/readiness.');
    return $response;
}

function routingFixture(PDO $database, string $issuer, string $subject, string $status = 'active', bool $binding = true, ?string $bindingSubject = null): int
{
    $database->prepare('INSERT INTO users (oidc_issuer, oidc_subject, account_status) VALUES (?, ?, ?)')->execute([$issuer, $subject, $status]);
    $id = (int) $database->lastInsertId();
    if ($binding) { $database->prepare('INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject) VALUES (?, ?, ?)')->execute([$id, $issuer, $bindingSubject ?? $subject]); }
    return $id;
}

function routingSaveUi(string $html, string $file): void
{
    $output = getenv('ATHERCAR_TEST_UI_OUTPUT_DIR');
    if (!is_string($output) || $output === '') { return; }
    routingAssert(is_dir($output) && !is_link($output), 'UI output directory unavailable.');
    $html = preg_replace('/(name="csrf_token" value=")[^"]*"/', '$1[REDACTED]"', $html);
    $css = file_get_contents(__DIR__ . '/../signin.css') . file_get_contents(__DIR__ . '/../signup.css');
    $html = str_replace('</head>', '<style>' . $css . '</style></head>', $html);
    file_put_contents($output . '/' . $file . '.html', $html);
}

$environment = null; $appProcess = null; $mockProcess = null; $exitCode = 0; $parkedPortfolio = false; $originalHosts = null;
try {
    routingAssert(is_file('/.dockerenv'), 'Route/token tests require a disposable Docker process.');
    $originalHosts = file_get_contents('/etc/hosts');
    routingAssert(is_string($originalHosts) && file_put_contents('/etc/hosts', $originalHosts . "\n127.0.0.1 registration-routing.test\n", LOCK_EX) !== false, 'Private test hostname unavailable.');
    $environment = TestEnvironment::create();
    $root = $environment->namespaceRoot . '/registration-routing';
    mkdir($root, 0700); mkdir($root . '/sessions', 0700); mkdir($root . '/rate', 0700); mkdir($root . '/http', 0700); mkdir($root . '/http/public', 0700);
    file_put_contents($root . '/mock-marker', 'private-loopback-test');
    symlink(realpath(__DIR__ . '/..'), $root . '/http/app');
    foreach (glob(__DIR__ . '/../public/*.php') as $shim) { copy($shim, $root . '/http/public/' . basename($shim)); }
    $router = <<<'PHP'
<?php
// Test-only middleware can make session storage fail; production never reads these files.
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/owner_oidc_callback.php' && (is_file(__DIR__ . '/fail-regenerate') || is_file(__DIR__ . '/fail-write'))) {
    $failRegenerate = is_file(__DIR__ . '/fail-regenerate');
    $failWrite = is_file(__DIR__ . '/fail-write');
    session_set_save_handler(new class($failRegenerate, $failWrite) extends SessionHandler {
        private bool $failed = false;
        public function __construct(private bool $regenerate, private bool $writeFailure) {}
        public function create_sid(): string {
            if ($this->regenerate && !$this->failed) { $this->failed = true; return ''; }
            return parent::create_sid();
        }
        public function write(string $id, string $data): bool {
            if ($this->writeFailure && str_contains($data, 'internal_user_session')) { return false; }
            return parent::write($id, $data);
        }
    }, true);
}
return false;
PHP;
    file_put_contents($root . '/router.php', $router);
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem); file_put_contents($root . '/signing-key.pem', $pem); chmod($root . '/signing-key.pem', 0600);
    $csr = openssl_csr_new(['commonName' => 'registration-routing-signing'], $key, ['digest_alg' => 'sha256']);
    $signingCertificate = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
    openssl_x509_export($signingCertificate, $signingPem); file_put_contents($root . '/signing-cert.pem', $signingPem);
    $certCommand = ['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-sha256', '-nodes', '-days', '1', '-keyout', $root . '/tls-key.pem', '-out', $root . '/cert.pem', '-subj', '/CN=registration-routing-test', '-addext', 'subjectAltName=DNS:registration-routing.test'];
    $cert = routingProcess($certCommand, getenv(), $root . '/certificate-private.log');
    routingAssert(proc_close($cert) === 0, 'Test TLS certificate generation failed.'); chmod($root . '/tls-key.pem', 0600);
    $appPort = routingPort(); $issuerPort = 443; $issuer = 'https://registration-routing.test/';
    $serverEnvironment = array_intersect_key(getenv(), array_flip(['PATH', 'APP_ENV', 'ATHERCAR_TEST_MODE', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'ATHERCAR_TEST_DB_NAME']));
    $serverEnvironment += ['EXPECTED_OIDC_ISSUER' => $issuer, 'OIDC_CLIENT_ID' => 'registration-routing-client', 'OIDC_CLIENT_SECRET' => bin2hex(random_bytes(24)), 'OIDC_REDIRECT_URI' => 'https://app.invalid/owner_oidc_callback.php', 'SESSION_COOKIE_SECURE' => '0', 'RATE_LIMIT_STATE_DIR' => $root . '/rate', 'TMPDIR' => $root];
    putenv('RATE_LIMIT_STATE_DIR=' . $root . '/rate');
    session_save_path($root . '/sessions'); session_name(OWNER_SESSION_NAME); ini_set('session.use_cookies', '0'); ini_set('session.cache_limiter', ''); ini_set('session.use_strict_mode', '0');
    $mockProcess = routingProcess([PHP_BINARY, __DIR__ . '/../tests/phase2/support/registration-oidc-mock.php', $root, (string) $issuerPort], $serverEnvironment, $root . '/issuer-private.log');
    $ready = false;
    for ($i = 0; $i < 40; $i++) {
        try { $r = (new Client(['verify' => $root . '/cert.pem', 'timeout' => 1]))->get($issuer . '.well-known/openid-configuration'); $ready = $r->getStatusCode() === 200; } catch (Throwable) { $ready = false; }
        if ($ready) { break; } usleep(50000);
    }
    routingAssert($ready, 'Verified loopback TLS issuer unavailable.');
    $database = getDatabaseConnection();
    routingAssert($database->query("SELECT name FROM schema_migrations WHERE version='014'")->fetchColumn() === 'identity_foundation', 'Migrated identity foundation required.');
    $prefix = 'routing-' . $environment->runId . '-';
    $owner = routingFixture($database, $issuer, $prefix . 'owner');
    $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (?)')->execute([$owner]);
    journeyFixtureLegacyOwner($database, $owner);
    routingFixture($database, $issuer, $prefix . 'disabled', 'disabled');
    routingFixture($database, $issuer, $prefix . 'legacy-only', 'active', false);
    routingFixture($database, 'https://other.invalid/', $prefix . 'issuer-collision');
    routingFixture($database, $issuer, $prefix . 'legacy-subject', 'active', true, $prefix . 'binding-mismatch');
    $protected = routingRows($database, true);
    $existingUsers = $database->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $existingBindings = $database->query('SELECT * FROM user_identities ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

    routingStartApp(null);
    $browser = routingBrowser();
    foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
        $response = $browser->get('/signup.php?lang=' . $locale); $html = (string) $response->getBody();
        routingAssert(str_contains($html, 'lang="' . $locale . '" dir="' . $direction . '"') && !str_contains($html, '<form') && !str_contains($html, '<input'), 'Default-off signup changed or exposes registration.');
        routingSaveUi($html, 'signup-off-' . $locale);
    }
    routingDenied($database, $browser, static fn () => $browser->post('/owner_register.php', ['form_params' => ['csrf_token' => 'invalid']]), [404]);
    routingDenied($database, $browser, static fn () => $browser->get('/owner_register.php'), [404]);
    foreach (['true', 'yes', '0', ''] as $flag) { putenv('ATHERCAR_REGISTRATION_ENABLED=' . $flag); routingAssert(!auth0RegistrationIsEnabled(), 'Malformed switch enabled registration.'); }
    putenv('ATHERCAR_REGISTRATION_ENABLED');
    echo "PASS routing default-off GET/POST starts and AR/EN signup; strict switch values\n";

    routingStartApp('1'); $browser = routingBrowser();
    foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
        $response = $browser->get('/signup.php?lang=' . $locale); $html = (string) $response->getBody();
        routingAssert(str_contains($html, 'lang="' . $locale . '" dir="' . $direction . '"') && substr_count($html, '<form') === 1 && str_contains($html, 'action="/owner_register.php" method="POST"') && substr_count($html, 'type="submit"') === 1, 'Enabled signup did not expose only the Google registration form.');
        routingAssert(str_contains($html, 'disabled aria-describedby="registration-unavailable microsoft-unavailable"') && str_contains($html, 'disabled aria-describedby="registration-unavailable email-unavailable"'), 'Other registration methods became available.');
        routingSaveUi($html, 'signup-on-' . $locale);
    }
    $token = routingCsrf($browser);
    routingDenied($database, $browser, static fn () => $browser->get('/owner_register.php'), [405]);
    routingDenied($database, $browser, static fn () => $browser->post('/owner_register.php', ['form_params' => ['csrf_token' => 'wrong']]));
    foreach (['purpose' => 'registration', 'method' => 'email', 'connection' => 'google-oauth2', 'user_id' => '1', 'email_verified' => 'true'] as $field => $value) {
        routingDenied($database, $browser, static fn () => $browser->post('/owner_register.php', ['form_params' => ['csrf_token' => $token, $field => $value]]));
    }
    routingDenied($database, $browser, static fn () => $browser->post('/owner_register.php?purpose=registration', ['form_params' => ['csrf_token' => $token]]));
    echo "PASS routing enabled POST-only Google start, CSRF and query/POST input allow-list\n";

    $start = routingStart($browser); $oldId = routingCookie($browser);
    $beforeUsers = (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $completedStart = $start; $completedCode = routingIssue($start, $prefix . 'new');
    $response = $browser->get('/owner_oidc_callback.php', ['query' => ['state' => $start['state'], 'code' => $completedCode, 'purpose' => 'sign-in', 'connection' => 'email']]);
    routingAssert($response->getStatusCode() === 303 && $response->getHeaderLine('Location') === '/owner_onboarding.php', 'Valid registration did not use its fixed unavailable destination, status ' . $response->getStatusCode());
    $state = routingSession($browser); $newId = $state[INTERNAL_USER_SESSION_KEY]['internal_user_id'] ?? null;
    routingAssert(is_int($newId) && $newId > 0 && routingCookie($browser) !== $oldId && !is_file($root . '/sessions/sess_' . $oldId), 'Registration session did not rotate and retire anonymous authority.');
    routingAssert(($state[OWNER_READINESS_SESSION_KEY]['email_verified'] ?? null) === true && !isset($state[AUTH0_AUTH_TRANSACTION_KEY]), 'Registration readiness or transaction cleanup failed.');
    routingAssert((int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn() === $beforeUsers + 1, 'Registration did not create one User.');
    routingAssert(($state[OWNER_READINESS_SESSION_KEY]['internal_user_id'] ?? null) === $newId
        && ($state[OWNER_READINESS_SESSION_KEY]['authz_version'] ?? null) === ($state[INTERNAL_USER_SESSION_KEY]['authz_version'] ?? null)
        && !str_contains(serialize($state), 'synthetic@example.invalid') && !str_contains(serialize($state), $prefix . 'new'), 'Trusted readiness binding or session privacy changed.');
    $replayBrowser = routingBrowser();
    routingDenied($database, $replayBrowser, static fn () => $replayBrowser->get('/owner_oidc_callback.php', ['query' => ['state' => $completedStart['state'], 'code' => $completedCode, 'purpose' => 'registration']]));
    $before = routingRows($database);
    $onboarding = (string) $browser->get('/owner_onboarding.php')->getBody();
    routingAssert(preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $onboarding, $m) === 1, 'Authenticated account controls need CSRF.');
    $csrf = $m[1];
    $request = $browser->post('/owner_register.php', ['form_params' => ['csrf_token' => $csrf]]);
    routingAssert($request->getStatusCode() === 403 && (routingSession($browser)[INTERNAL_USER_SESSION_KEY]['internal_user_id'] ?? null) === $newId && $before === routingRows($database), 'Authenticated start implicitly switched/linked accounts.');
    $request = $browser->get('/owner_oidc_callback.php?state=forged&code=forged&purpose=registration');
    routingAssert($request->getStatusCode() === 403 && (routingSession($browser)[INTERNAL_USER_SESSION_KEY]['internal_user_id'] ?? null) === $newId, 'Authenticated callback replaced current identity.');
    $browser = routingBrowser(); $start = routingStart($browser);
    $response = routingCallback($browser, $start, $prefix . 'new');
    routingAssert($response->getStatusCode() === 303 && (routingSession($browser)[INTERNAL_USER_SESSION_KEY]['internal_user_id'] ?? null) === $newId && $before === routingRows($database), 'Fresh explicit registration did not resolve the original binding.');
    echo "PASS routing signed-token registration, repeat resolution, session rotation/readiness and authenticated-session refusal\n";

    $browser = routingBrowser(); $start = routingStart($browser, false);
    routingDenied($database, $browser, static fn () => routingCallback($browser, $start, 'google-oauth2|' . $prefix . 'unknown-signin', ['purpose' => 'registration'], ['purpose' => 'registration', 'email_verified' => 'true']));
    $browser = routingBrowser(); $start = routingStart($browser, false);
    routingDenied($database, $browser, static fn () => $browser->post('/owner_oidc_callback.php?state=' . $start['state'], ['form_params' => ['code' => routingIssue($start, $prefix . 'post-callback'), 'purpose' => 'registration']]), [405]);
    echo "PASS routing unknown ordinary Sign In cannot provision via query/POST purpose, claim, connection or subject-prefix tampering\n";

    foreach (['missing', 'missing-purpose', 'malformed-purpose', 'unknown-purpose', 'expired', 'future', 'mismatch', 'cancelled', 'pkce', 'missing-state'] as $mode) {
        $browser = routingBrowser(); $start = routingStart($browser);
        routingSession($browser, static function () use ($mode): void {
            if ($mode === 'missing') { unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]); }
            if ($mode === 'missing-purpose') { unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['purpose']); }
            if ($mode === 'malformed-purpose') { $_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['purpose'] = ['registration']; }
            if ($mode === 'unknown-purpose') { $_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['purpose'] = 'register'; }
            if ($mode === 'expired') { $_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['created_at'] = time() - AUTH0_AUTH_TRANSACTION_TTL_SECONDS - 1; }
            if ($mode === 'future') { $_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['created_at'] = time() + 120; }
            if ($mode === 'pkce') { $_SESSION[AUTH0_AUTH_TRANSACTION_KEY]['code_verifier'] = str_repeat('x', 86); }
        });
        if ($mode === 'cancelled') { $operation = static fn () => $browser->get('/owner_oidc_callback.php', ['query' => ['state' => $start['state'], 'error' => 'access_denied']]); }
        elseif ($mode === 'missing-state') { $operation = static fn () => $browser->get('/owner_oidc_callback.php?code=synthetic&purpose=registration'); }
        else { $operation = static fn () => routingCallback($browser, $start, $prefix . 'transaction-' . $mode, [], $mode === 'mismatch' ? ['state' => 'wrong'] : []); }
        routingDenied($database, $browser, $operation);
        routingAssert(!isset(routingSession($browser)[AUTH0_AUTH_TRANSACTION_KEY]), 'Failed callback left a reusable transaction.');
        routingDenied($database, $browser, static fn () => routingCallback($browser, $start, $prefix . 'replay-' . $mode));
    }
    echo "PASS routing missing/malformed purpose, transaction absence/expiry/state/cancellation/PKCE and replay cleanup\n";

    foreach ([['iss' => 'https://wrong.invalid/'], ['aud' => 'wrong-client'], ['nonce' => 'wrong'], ['exp' => time() - 3600], ['bad-signature' => true]] as $i => $claims) {
        $badSignature = isset($claims['bad-signature']); unset($claims['bad-signature']);
        $browser = routingBrowser(); $start = routingStart($browser);
        routingDenied($database, $browser, static fn () => routingCallback($browser, $start, $prefix . 'bad-token-' . $i, $claims, [], $badSignature));
        $expectedReason = ['issuer_failed', 'audience_failed', 'nonce_failed', 'expiry_failed', 'jwt_signature_failed'][$i];
        routingAssert(str_contains(file_get_contents($root . '/http-private.log'), '"reason":"' . $expectedReason . '"'), 'Actual SDK rejection reason was not observed.');
    }
    foreach ([['email_verified' => false], ['email_verified' => '__omit__'], ['email_verified' => 'true'], ['email' => '__omit__'], ['email' => '   ']] as $i => $claims) {
        $browser = routingBrowser(); $start = routingStart($browser);
        routingDenied($database, $browser, static fn () => routingCallback($browser, $start, $prefix . 'readiness-' . $i, $claims));
    }
    foreach (['disabled', 'legacy-only', 'issuer-collision', 'binding-mismatch'] as $suffix) {
        $browser = routingBrowser(); $start = routingStart($browser);
        routingDenied($database, $browser, static fn () => routingCallback($browser, $start, $prefix . $suffix));
    }
    echo "PASS routing actual SDK signature/issuer/audience/nonce/expiry validation, strict new-identity readiness and disabled/conflicting accounts\n";

    $browser = routingBrowser(); $start = routingStart($browser); $count = (int) @file_get_contents($root . '/token-count');
    routingStartApp(null);
    routingDenied($database, $browser, static fn () => routingCallback($browser, $start, $prefix . 'disabled-pending'));
    routingAssert((int) @file_get_contents($root . '/token-count') === $count, 'Disabled pending callback exchanged a token.');
    routingAssert(!isset(routingSession($browser)[AUTH0_AUTH_TRANSACTION_KEY]), 'Disabled pending callback remained reusable.');
    $unknownOff = routingBrowser(); $unknownStart = routingStart($unknownOff, false);
    routingDenied($database, $unknownOff, static fn () => routingCallback($unknownOff, $unknownStart, $prefix . 'unknown-off', [], ['purpose' => 'registration']));
    $browser = routingBrowser(); $start = routingStart($browser, false);
    $response = routingCallback($browser, $start, $prefix . 'owner', ['email_verified' => false]);
    routingAssert($response->getStatusCode() === 303 && $response->getHeaderLine('Location') === '/owner.php' && (routingSession($browser)[INTERNAL_USER_SESSION_KEY]['internal_user_id'] ?? null) === $owner, 'Existing owner Sign In changed when registration disabled.');
    routingAssert($browser->get('/owner.php')->getStatusCode() === 200, 'Existing owner lost access with nonpositive readiness.');
    $token = routingSession($browser)['csrf_token']; clearRateLimit('oidc_start', '127.0.0.1');
    $response = $browser->post('/owner_switch_account.php', ['form_params' => ['csrf_token' => $token]]);
    routingAssert($response->getStatusCode() === 302 && (routingSession($browser)[AUTH0_AUTH_TRANSACTION_KEY]['purpose'] ?? null) === AUTH0_PURPOSE_SIGN_IN && !isset(routingSession($browser)[INTERNAL_USER_SESSION_KEY]), 'Account switching did not retire identity and retain sign-in purpose.');
    parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $switchStart);
    $browser->get('/owner_oidc_callback.php', ['query' => ['state' => $switchStart['state'], 'error' => 'access_denied']]);
    $token = routingSession($browser)['csrf_token']; clearRateLimit('oidc_start', '127.0.0.1');
    $response = $browser->post('/owner_auth_retry.php', ['form_params' => ['csrf_token' => $token]]);
    routingAssert($response->getStatusCode() === 302 && (routingSession($browser)[AUTH0_AUTH_TRANSACTION_KEY]['purpose'] ?? null) === AUTH0_PURPOSE_SIGN_IN, 'Recovery retry escalated purpose.');
    echo "PASS routing switch-off pending callback stops before token exchange; existing-owner access, account switching and retry retain sign-in purpose\n";

    routingStartApp('1');
    foreach (['regenerate', 'write', 'post-commit-query'] as $mode) {
        $browser = routingBrowser(); $start = routingStart($browser); $subject = $prefix . 'failure-' . $mode;
        $before = (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($mode === 'post-commit-query') { $database->exec('RENAME TABLE portfolios TO routing_parked_portfolios'); $parkedPortfolio = true; }
        else { file_put_contents($root . '/fail-' . $mode, 'private-test-fault'); }
        try { $response = routingCallback($browser, $start, $subject); }
        finally {
            if ($parkedPortfolio) { $database->exec('RENAME TABLE routing_parked_portfolios TO portfolios'); $parkedPortfolio = false; }
            if ($mode !== 'post-commit-query') { unlink($root . '/fail-' . $mode); }
        }
        routingAssert($response->getStatusCode() === 503, 'Post-commit/session failure did not return unavailable.');
        $body = (string) $response->getBody();
        routingAssert(str_contains($body, 'An account may already exist.') && !str_contains($body, 'No data was changed') && !str_contains($body, 'لم تتغير أي بيانات'), 'Committed registration received a false no-data-changed message.');
        routingAssert((int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn() === $before + 1, 'Committed User was deleted or duplicated during failure recovery.');
        routingAssert(!isset(routingSession($browser)[INTERNAL_USER_SESSION_KEY]) && !isset(routingSession($browser)[OWNER_READINESS_SESSION_KEY]), 'Session failure cleanup retained authenticated authority.');
        $start = routingStart($browser); $response = routingCallback($browser, $start, $subject);
        routingAssert($response->getStatusCode() === 303 && (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn() === $before + 1, 'Later explicit registration did not resolve the committed binding.');
    }
    echo "PASS routing post-commit database/session failures are honest, clear authentication and permit safe explicit retry without deletion\n";

    $browser = routingBrowser(); clearRateLimit('oidc_start', '127.0.0.1');
    for ($i = 0; $i < OIDC_START_RATE_LIMIT_ATTEMPTS; $i++) { routingStart($browser, true, false); }
    $response = $browser->post('/owner_register.php', ['form_params' => ['csrf_token' => routingCsrf($browser)]]);
    routingAssert($response->getStatusCode() === 429 && (int) $response->getHeaderLine('Retry-After') > 0 && !isset(routingSession($browser)[AUTH0_AUTH_TRANSACTION_KEY]), 'Registration limiter failed or retained pending authorization.');
    routingAssert($browser->get('/owner_login.php')->getStatusCode() === 429, 'Registration bypassed the shared OIDC start limiter.');
    echo "PASS routing shared start rate limit and denied-attempt transaction cleanup\n";

    foreach ($existingUsers as $row) { routingAssert($database->query('SELECT * FROM users WHERE id=' . (int) $row['id'])->fetch(PDO::FETCH_ASSOC) === $row, 'Preexisting User changed.'); }
    foreach ($existingBindings as $row) { routingAssert($database->query('SELECT * FROM user_identities WHERE id=' . (int) $row['id'])->fetch(PDO::FETCH_ASSOC) === $row, 'Preexisting identity binding changed.'); }
    routingAssert($protected === routingRows($database, true), 'Registration routing changed Portfolio, ownership, content, consent or migration rows.');
    routingAssert((int) $database->query('SELECT COUNT(*) FROM user_identities WHERE oidc_subject LIKE ' . $database->quote($prefix . '%') . ' AND (provider_name IS NOT NULL OR connection_name IS NOT NULL)')->fetchColumn() === 0, 'Provider metadata was inferred.');
    echo "PASS routing all preexisting Users/bindings and protected ownership/content unchanged; no automatic Portfolio or provider metadata\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL registration routing: ' . ($exception instanceof PDOException ? 'database operation failed, driver ' . (int) ($exception->errorInfo[1] ?? 0) : (get_class($exception) === RuntimeException::class ? $exception->getMessage() : get_class($exception))) . ' [line ' . $exception->getLine() . "]\n");
    if (isset($root) && is_file($root . '/http-private.log')) {
        preg_match_all('/"reason":"([a-z0-9_]+)"/', file_get_contents($root . '/http-private.log'), $reasons);
        fwrite(STDERR, 'Sanitized route reasons: ' . implode(',', array_unique($reasons[1])) . PHP_EOL);
        fwrite(STDERR, 'Token requests: ' . (int) @file_get_contents($root . '/token-count') . PHP_EOL);
    }
    $exitCode = 1;
} finally {
    $cleanupFailed = false;
    try { if ($parkedPortfolio && isset($database)) { $database->exec('RENAME TABLE routing_parked_portfolios TO portfolios'); } }
    catch (Throwable) { $cleanupFailed = true; }
    try { routingStop($appProcess); } catch (Throwable) { $cleanupFailed = true; }
    try { routingStop($mockProcess); } catch (Throwable) { $cleanupFailed = true; }
    if (is_string($originalHosts) && file_put_contents('/etc/hosts', $originalHosts, LOCK_EX) === false) { $cleanupFailed = true; }
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    putenv('RATE_LIMIT_STATE_DIR');
    try { if ($environment instanceof TestEnvironment) { $environment->tearDown(); } }
    catch (Throwable) { $cleanupFailed = true; }
    if ($cleanupFailed) { fwrite(STDERR, "FAIL registration routing cleanup\n"); $exitCode = 1; }
    else { echo "PASS routing private session/key/log/process/hostname cleanup\n"; }
}
exit($exitCode);
