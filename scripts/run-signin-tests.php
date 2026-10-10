<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../includes/auth0_oidc.php';

$environment = TestEnvironment::create();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    phase2Assert($condition, $message);
    ++$checks;
};
$failed = false;
try {
    session_save_path($environment->namespaceRoot);
    session_name('ather_signin_contract_test');
    session_start();
    $configuration = new Auth0OidcConfiguration('https://issuer.example.test/', 'issuer.example.test', 'synthetic-client', 'synthetic-secret', 'https://app.example.test/owner_oidc_callback.php');
    $check(auth0SignInConnection(null) === null, 'Ordinary login must not force a connection.');
    foreach (['', 'microsoft', 'Google', 'google-oauth2', 'email&prompt=none', 'https://hostile.invalid/', [], ['google'], 1, true, new stdClass()] as $invalid) {
        try {
            auth0SignInConnection($invalid);
            throw new RuntimeException('Invalid method was accepted.');
        } catch (InvalidArgumentException) {
            ++$checks;
        }
    }
    foreach ([[null, null], ['google', 'google-oauth2'], ['email', 'Username-Password-Authentication']] as [$method, $connection]) {
        $_SESSION['test_marker'] = 'retained';
        $authorization = beginAuth0Authorization($configuration, 'https://issuer.example.test/authorize', null, $method);
        parse_str((string) parse_url($authorization['url'], PHP_URL_QUERY), $query);
        $transaction = $_SESSION[AUTH0_AUTH_TRANSACTION_KEY];
        $check(($query['connection'] ?? null) === $connection, 'Method must select only its allowlisted connection.');
        $check(!isset($query['prompt']) && !isset($query['screen_hint']), 'Ordinary method login must not request switching or registration.');
        $check($query['redirect_uri'] === $configuration->redirectUri && $query['scope'] === 'openid', 'Configured redirect and scope must remain fixed.');
        $check($query['state'] === $transaction['state'] && $query['nonce'] === $transaction['nonce'], 'State and nonce must bind to the server transaction.');
        $check($query['code_challenge_method'] === 'S256' && $query['code_challenge'] === auth0Base64Url(hash('sha256', $transaction['code_verifier'], true)), 'PKCE must bind to the server verifier.');
        $check(strlen($query['state']) >= 43 && strlen($query['nonce']) >= 43, 'State and nonce must retain their entropy.');
        $check($_SESSION['test_marker'] === 'retained', 'Ordinary login must not retire unrelated session state.');
        $consumed = consumeAuth0AuthorizationTransaction(['state' => $query['state'], 'code' => 'synthetic-code']);
        $check($consumed['nonce'] === $transaction['nonce'] && !isset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY]), 'Transaction must remain single-use.');
        try {
            consumeAuth0AuthorizationTransaction(['state' => $query['state'], 'code' => 'synthetic-code']);
            throw new RuntimeException('Replayed transaction was accepted.');
        } catch (Auth0OidcException) {
            ++$checks;
        }
    }
    $authorization = beginAuth0Authorization($configuration, 'https://issuer.example.test/authorize', 'login');
    parse_str((string) parse_url($authorization['url'], PHP_URL_QUERY), $query);
    $check($query['prompt'] === 'login' && !isset($query['connection']), 'Switch/retry prompt=login must remain compatible.');
    $previous = $_SESSION[AUTH0_AUTH_TRANSACTION_KEY];
    try {
        beginAuth0Authorization($configuration, 'https://issuer.example.test/authorize', null, 'microsoft');
        throw new RuntimeException('Unavailable method created a transaction.');
    } catch (InvalidArgumentException) {
        $check($_SESSION[AUTH0_AUTH_TRANSACTION_KEY] === $previous, 'Invalid methods must not mutate the existing transaction.');
    }
    foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        http_response_code(200);
        $_GET = ['lang' => $locale];
        ob_start();
        require __DIR__ . '/../signin.php';
        $html = (string) ob_get_clean();
        $check(str_contains($html, '<html lang="' . $locale . '" dir="' . $direction . '">'), 'Locale and direction must agree.');
        $check(str_contains($html, 'href="owner_login.php?method=google"'), 'Google must retain its existing OIDC route.');
        $check(str_contains($html, 'href="owner_login.php"'), 'Ordinary login must remain available.');
        $check(!str_contains($html, 'owner_login.php?method=email'), 'Unavailable email must not advertise a navigation route.');
        $check(preg_match('/<button[^>]*type="button"[^>]*disabled aria-describedby="email-unavailable"[^>]*>.*?' . preg_quote(signInCopy('email', $locale), '/') . '<\/button>/s', $html) === 1, 'Email must retain its label with native disabled semantics.');
        $emailNotice = $locale === 'ar' ? 'تسجيل الدخول بالبريد وكلمة المرور غير متاح حاليًا.' : 'Email and password sign-in is currently unavailable.';
        $check(str_contains($html, '<p class="af-method-note" id="email-unavailable">' . $emailNotice . '</p>'), 'Disabled email must have its approved associated explanation.');
        $check(str_contains($html, 'disabled aria-describedby="microsoft-unavailable"'), 'Microsoft must be visibly disabled.');
        $check(!str_contains($html, '<input') && !str_contains($html, 'data-go-screen') && !str_contains($html, 'af-toolbar'), 'No credentials or prototype navigation may be embedded.');
        $registrationNotice = $locale === 'ar' ? 'التسجيل العام غير متاح حاليًا؛ تسجيل الدخول للحسابات الحالية فقط.' : 'Public registration is currently unavailable. Sign-in is for existing accounts only.';
        $check(str_contains($html, '<p class="af-footnote">' . $registrationNotice . '</p>'), 'Registration must use the approved concise disclosure.');
        $check(!str_contains($html, 'signup.php') && !str_contains($html, 'screen_hint'), 'No fake signup route may be advertised.');
    }
    $_GET = ['lang' => ['en'], 'untrusted' => '<script>alert(1)</script>'];
    ob_start();
    require __DIR__ . '/../signin.php';
    $html = (string) ob_get_clean();
    $check(str_contains($html, '<html lang="ar" dir="rtl">') && !str_contains($html, 'alert(1)'), 'Structured locale input must fall back without reflection.');
} catch (Throwable $exception) {
    $failed = true;
    fwrite(STDERR, 'FAIL Sign In contract: ' . $exception->getMessage() . "\n");
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    $environment->tearDown();
}
if ($failed) {
    exit(1);
}
echo 'PASS Sign In contracts: ' . $checks . " checks; scoped teardown passed.\n";
