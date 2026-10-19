<?php

declare(strict_types=1);

// Existing CLI-only, exact disposable-database and test-mode guard. No HTTP
// authentication fixture or bypass is reachable from production code.
require_once __DIR__ . '/../tests/phase2/support/draft-fixtures.php';
require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/owner_session.php';
require_once __DIR__ . '/../includes/csrf.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

ob_start();
function draftRouteAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
function draftRouteSnapshot(PDO $database): array
{
    $snapshot = [];
    foreach ($database->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows = array_map('serialize', $database->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC));
        sort($rows); $snapshot[$table] = hash('sha256', serialize($rows));
    }
    return $snapshot;
}
function draftRouteStop(): void
{
    global $process;
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $process = null;
}
function draftRouteStart(?string $gate, array $overrides = [], ?string $sessionPath = null): void
{
    global $process, $root, $port;
    draftRouteStop();
    // Deliberately exclude root credentials and unrelated authentication config.
    $env = array_intersect_key(getenv(), array_flip(['PATH', 'APP_ENV', 'ATHERCAR_TEST_MODE', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'ATHERCAR_TEST_DB_NAME']));
    $env += ['ATHERCAR_REGISTRATION_ENABLED' => '0', 'SESSION_COOKIE_SECURE' => '0'];
    if ($gate !== null) { $env['ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED'] = $gate; }
    $env = array_replace($env, $overrides);
    $process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . ($sessionPath ?? $root . '/sessions'), '-d', 'display_errors=0',
        '-d', 'log_errors=1', '-S', '127.0.0.1:' . $port, '-t', $root . '/http/public'],
        [0 => ['pipe', 'r'], 1 => ['file', $root . '/private-http.log', 'a'], 2 => ['file', $root . '/private-http.log', 'a']], $pipes, null, $env);
    draftRouteAssert(is_resource($process), 'Private HTTP process unavailable.'); fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $message, 0.1);
        if (is_resource($socket)) { fclose($socket); $ready = true; break; }
        usleep(50000);
    }
    draftRouteAssert($ready, 'Private HTTP server did not become ready.');
}
function draftRouteBrowser(?int $id = null, ?callable $mutate = null): Client
{
    global $root, $port, $privateTokens;
    $jar = new CookieJar();
    if ($id !== null) {
        session_id(''); startOwnerSession(); establishVerifiedInternalUserSession($id, 1);
        $privateTokens[] = getCsrfToken();
        if ($mutate !== null) { $mutate(); }
        $sid = session_id(); session_write_close();
        $jar = CookieJar::fromArray([OWNER_SESSION_NAME => $sid], '127.0.0.1');
        $privateTokens[] = $sid;
    }
    return new Client(['base_uri' => 'http://127.0.0.1:' . $port, 'cookies' => $jar, 'http_errors' => false, 'allow_redirects' => false, 'timeout' => 8]);
}
/** Every non-success assertion also proves whole-database preservation. */
function draftRouteRequest(Client $browser, string $method, int $expected, array $options = [], string $query = ''): array
{
    global $database;
    $before = draftRouteSnapshot($database);
    $response = $browser->request($method, '/owner_onboarding_draft.php' . $query, $options);
    draftRouteAssert($response->getStatusCode() === $expected, 'Unexpected draft HTTP status: expected ' . $expected . ', got ' . $response->getStatusCode());
    draftRouteAssert(strtolower($response->getHeaderLine('Content-Type')) === 'application/json; charset=utf-8'
        && $response->getHeaderLine('Cache-Control') === 'private, no-store'
        && !$response->hasHeader('Location') && !$response->hasHeader('Access-Control-Allow-Origin'), 'Private JSON response headers changed.');
    if ($expected === 405) { draftRouteAssert($response->getHeaderLine('Allow') === 'GET, POST', 'Incorrect method Allow header.'); }
    if ($expected !== 200) { draftRouteAssert($before === draftRouteSnapshot($database), 'Rejected HTTP request mutated data.'); }
    if ($method === 'HEAD') { return []; }
    $json = json_decode((string) $response->getBody(), true, 16, JSON_THROW_ON_ERROR);
    draftRouteAssert(is_array($json) && ($json['success'] ?? null) === ($expected === 200), 'JSON success envelope changed.');
    if ($expected !== 200) {
        draftRouteAssert(array_keys($json) === ['success', 'code', 'error'] && is_string($json['code']) && is_string($json['error'])
            && !preg_match('/SQLSTATE|PDO|Exception|SELECT |INSERT |UPDATE |password|digest/i', $json['error']), 'Unsafe error envelope.');
    } else {
        draftRouteAssert(array_keys($json['draft']) === ['version', 'fields', 'last_edited_section', 'created_at', 'updated_at'], 'Private core projection changed.');
    }
    return $json;
}
function draftRouteSave(Client $browser, string $csrf, int $expected, array $input): array
{
    return draftRouteRequest($browser, 'POST', $expected, ['headers' => ['X-CSRF-Token' => $csrf], 'json' => $input]);
}
function draftRouteInput(int $version, array $fields = [], string $section = 'public_identity'): array
{
    return ['expectedVersion' => $version, 'operationId' => draftTestOperation($version), 'section' => $section, 'fields' => (object) $fields];
}

$environment = null; $process = null; $users = []; $privateTokens = []; $exitCode = 0; $initial = null; $phase = 'setup';
try {
    draftRouteAssert(is_file('/.dockerenv'), 'Real HTTP route tests require disposable Docker.');
    $environment = TestEnvironment::create();
    $root = $environment->namespaceRoot . '/draft-http';
    mkdir($root, 0700); mkdir($root . '/sessions', 0700); mkdir($root . '/http', 0700); mkdir($root . '/http/public', 0700);
    symlink(realpath(__DIR__ . '/..'), $root . '/http/app');
    copy(__DIR__ . '/../public/owner_onboarding_draft.php', $root . '/http/public/owner_onboarding_draft.php');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $message);
    draftRouteAssert(is_resource($socket), 'Loopback port unavailable.');
    $address = stream_socket_get_name($socket, false); fclose($socket); $port = (int) substr($address, strrpos($address, ':') + 1);
    session_save_path($root . '/sessions'); session_name(OWNER_SESSION_NAME); ini_set('session.cache_limiter', '');
    $database = getDatabaseConnection();
    draftRouteAssert($database->query("SELECT name FROM schema_migrations WHERE version='016'")->fetchColumn() === 'onboarding_drafts', 'Migration 016 required.');
    $initial = draftRouteSnapshot($database);
    for ($index = 0; $index < 4; $index++) {
        $database->prepare('INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (?, ?, ?, 1)')->execute(['https://draft-http.invalid/', bin2hex(random_bytes(16)), 'active']);
        $users[] = (int) $database->lastInsertId();
    }
    [$userA, $userB, $disabled, $catalogUser] = $users;
    $database->exec("UPDATE users SET account_status='disabled' WHERE id=$disabled");
    foreach ([null, '', '0', 'true', 'TRUE', '01', ' 1', '1 ', 'false'] as $gate) {
        draftRouteStart($gate, ['DB_PORT' => 'invalid']);
        foreach (['GET', 'POST', 'DELETE'] as $method) { draftRouteRequest(draftRouteBrowser(), $method, 404); }
    }
    draftRouteStart('1');
    foreach (['PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'] as $method) { draftRouteRequest(draftRouteBrowser(), $method, 405); }
    // Some web servers reject lowercase methods before dispatching PHP. Also
    // exercise the real handler directly to prove its CSRF branch cannot be
    // bypassed by the common method helper's case normalization.
    foreach (['get', 'post'] as $method) {
        $source = '$_SERVER["REQUEST_METHOD"]=$argv[1]; putenv("ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED=1"); putenv("DB_PORT=invalid"); register_shutdown_function(static function (): void { fwrite(STDERR, "STATUS=".http_response_code()); }); require $argv[2];';
        $child = proc_open([PHP_BINARY, '-r', $source, $method, __DIR__ . '/../owner_onboarding_draft.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        draftRouteAssert(is_resource($child), 'Case-sensitive method test process unavailable.'); fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]); $diagnostic = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        draftRouteAssert(proc_close($child) === 0 && str_contains($diagnostic, 'STATUS=405') && json_decode($body, true, 8, JSON_THROW_ON_ERROR)['code'] === 'method_not_allowed', 'Lowercase method bypassed the exact HTTP method guard.');
    }
    echo "PASS draft HTTP strict default-off gate and unsupported methods without database access\n";

    foreach (['GET', 'POST'] as $method) {
        draftRouteRequest(draftRouteBrowser(), $method, 401);
        foreach (['idle', 'absolute', 'revoked', 'malformed', 'future', 'missing-user'] as $case) {
            $browser = draftRouteBrowser($userA, static function () use ($case): void {
                $state =& $_SESSION[INTERNAL_USER_SESSION_KEY];
                if ($case === 'idle') { $state['authenticated_at'] -= 2000; $state['last_activity_at'] -= 2000; }
                if ($case === 'absolute') { $state['authenticated_at'] -= 50000; }
                if ($case === 'revoked') { $state['authz_version'] = 2; }
                if ($case === 'malformed') { $state['internal_user_id'] = 'invalid'; }
                if ($case === 'future') { $state['last_activity_at'] += 120; }
                if ($case === 'missing-user') { $state['internal_user_id'] = 4294967295; }
            });
            draftRouteRequest($browser, $method, 403);
        }
        draftRouteRequest(draftRouteBrowser($disabled), $method, 403);
    }
    // Revoke an already persisted session via the authoritative users row.
    $revoked = draftRouteBrowser($userA); $database->exec("UPDATE users SET authz_version=2 WHERE id=$userA");
    draftRouteRequest($revoked, 'GET', 403); draftRouteRequest(draftRouteBrowser($userA), 'POST', 403);
    $database->exec("UPDATE users SET authz_version=1 WHERE id=$userA");
    echo "PASS draft HTTP missing, expired, revoked, disabled, malformed and mismatched sessions\n";

    $a = draftRouteBrowser($userA); $b = draftRouteBrowser($userB);
    $empty = ['version' => 0, 'fields' => [], 'last_edited_section' => null, 'created_at' => null, 'updated_at' => null];
    $before = draftRouteSnapshot($database); $resume = draftRouteRequest($a, 'GET', 200);
    draftRouteAssert($resume['draft'] === $empty && $before === draftRouteSnapshot($database), 'Empty resume created or inferred draft data.');
    $csrf = $resume['csrf_token']; $privateTokens[] = $csrf;
    draftRouteAssert(is_string($csrf) && preg_match('/\A[a-f0-9]{64}\z/D', $csrf) === 1, 'Resume CSRF token invalid.');
    $csrfB = draftRouteRequest($b, 'GET', 200)['csrf_token'];
    foreach ([null, '', 'invalid', $csrfB] as $token) {
        $headers = $token === null ? [] : ['X-CSRF-Token' => $token];
        draftRouteRequest($a, 'POST', 403, ['headers' => $headers, 'json' => draftRouteInput(0)]);
    }
    foreach (['user_id', 'internal_user_id', 'portfolio_id', 'readiness', 'completed', 'csrf_token'] as $key) {
        $input = draftRouteInput(0); $input[$key] = $userB; draftRouteSave($a, $csrf, 400, $input);
    }
    foreach (['?user_id=' . $userB, '?section=public_identity', '?expectedVersion=0', '?csrf_token=invalid', '?user_id=1&user_id=2'] as $query) {
        draftRouteRequest($a, 'GET', 400, [], $query);
        draftRouteRequest($a, 'POST', 400, ['headers' => ['X-CSRF-Token' => $csrf], 'json' => draftRouteInput(0)], $query);
    }
    draftRouteRequest($a, 'GET', 400, ['body' => '{}']);
    draftRouteRequest($a, 'POST', 400, ['headers' => ['X-CSRF-Token' => $csrf, 'X-HTTP-Method-Override' => 'GET'], 'json' => draftRouteInput(0)]);
    echo "PASS draft HTTP empty resume, session CSRF and selector/authority tampering rejection\n";

    $sentinel = 'PRIVATE_DRAFT_HTTP_' . bin2hex(random_bytes(8));
    $first = draftRouteInput(0, ['city' => $sentinel, 'name_ar' => '  مُؤمِن  ', 'country_code' => 'JO']);
    $saved = draftRouteSave($a, $csrf, 200, $first)['draft'];
    draftRouteAssert($saved['version'] === 1 && $saved['fields'] === ['city' => $sentinel, 'country_code' => 'JO', 'name_ar' => '  مُؤمِن  '], 'Exact partial save changed private text.');
    draftRouteAssert(draftRouteSave($a, $csrf, 200, $first)['draft'] === $saved, 'Identical retry changed result/time/version.');
    $reordered = $first; $reordered['fields'] = (object) array_reverse((array) $first['fields'], true);
    draftRouteAssert(draftRouteSave($a, $csrf, 200, $reordered)['draft'] === $saved, 'Reordered retry changed result.');
    $changed = $first; $changed['fields'] = (object) ['city' => 'Changed']; draftRouteSave($a, $csrf, 409, $changed);
    $changed = $first; $changed['section'] = 'professional_snapshot'; $changed['fields'] = (object) []; draftRouteSave($a, $csrf, 409, $changed);
    draftRouteSave($a, $csrf, 409, draftRouteInput(0, ['city' => 'Stale']));
    $saved = draftRouteSave($a, $csrf, 200, draftRouteInput(1, ['biography' => "Exact\n{\"fields\": null}", 'headline' => 'Candidate'], 'professional_snapshot'))['draft'];
    $saved = draftRouteSave($a, $csrf, 200, draftRouteInput(2, ['city' => null]))['draft'];
    draftRouteAssert($saved['version'] === 3 && !isset($saved['fields']['city']) && $saved['fields']['name_ar'] === '  مُؤمِن  '
        && $saved['fields']['headline'] === 'Candidate' && $saved['fields']['country_code'] === 'JO', 'Clear or omitted/other-section preservation failed.');
    draftRouteAssert(draftRouteRequest($a, 'GET', 200)['draft'] === $saved, 'HTTP resume changed result.');
    draftRouteSave($a, $csrf, 409, $first);
    $reused = $first; $reused['expectedVersion'] = 3; draftRouteSave($a, $csrf, 400, $reused);
    $sameFields = $saved['fields'];
    $saved = draftRouteSave($a, $csrf, 200, draftRouteInput(3))['draft'];
    draftRouteAssert($saved['version'] === 4 && $saved['fields'] === $sameFields, 'Empty section patch changed fields or failed to advance once.');
    echo "PASS draft HTTP partial save, exact resume, clear/omission, stale conflicts and bounded exact retries\n";

    foreach (['0', 0.0, true, null, -1, 2147483648, [], (object) []] as $version) {
        $input = draftRouteInput(3); $input['expectedVersion'] = $version;
        // Preserve a JSON floating-point value for the 0.0 case.
        draftRouteRequest($a, 'POST', 400, ['headers' => ['X-CSRF-Token' => $csrf, 'Content-Type' => 'application/json'], 'body' => json_encode($input, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)]);
    }
    foreach (['expectedVersion', 'operationId', 'section', 'fields'] as $key) { $input = draftRouteInput(3); unset($input[$key]); draftRouteSave($a, $csrf, 400, $input); }
    foreach ([null, 1, true, [], (object) []] as $value) {
        foreach (['operationId', 'section'] as $key) { $input = draftRouteInput(3); $input[$key] = $value; draftRouteSave($a, $csrf, 400, $input); }
    }
    foreach ([[], ['array'], null, true, 'text', 1] as $fields) { $input = draftRouteInput(3); $input['fields'] = $fields; draftRouteSave($a, $csrf, 400, $input); }
    foreach ([['city' => 1], ['city' => false], ['city' => []], ['city' => (object) []], ['city' => ''], ['city' => str_repeat('x', 101)], ['headline' => 'wrong section'], ['user_id' => $userB], ['is_published' => true], ['slug_candidate' => 'wrong section'], ['city' => "nul\0"]] as $fields) {
        draftRouteSave($a, $csrf, 400, draftRouteInput(3, $fields));
    }
    draftRouteSave($a, $csrf, 400, draftRouteInput(3, [], 'confirmation'));
    foreach (['', '3:ABC', draftTestOperation(2), str_repeat('a', 44)] as $op) { $input = draftRouteInput(3); $input['operationId'] = $op; draftRouteSave($a, $csrf, 400, $input); }
    $validRaw = json_encode(draftRouteInput(3), JSON_THROW_ON_ERROR);
    foreach (['', 'null', '[]', '{', $validRaw . '{}', "\xEF\xBB\xBF" . $validRaw, "{\"fields\":\"\xFF\"}",
        str_replace('"fields":{}', '"fields":{"city":"A","c\\u0069ty":"B"}', $validRaw),
        str_replace('"expectedVersion":3', '"expectedVersion":2,"expectedVersion":3', $validRaw),
        str_replace('"fields":{}', '"fields":{"city":' . str_repeat('[', 10) . '0' . str_repeat(']', 10) . '}', $validRaw)] as $raw) {
        draftRouteRequest($a, 'POST', 400, ['headers' => ['X-CSRF-Token' => $csrf, 'Content-Type' => 'application/json'], 'body' => $raw]);
    }
    foreach (['text/plain', 'application/json; charset=utf-16', 'application/x-www-form-urlencoded', 'multipart/form-data', 'application/json; profile=unknown'] as $type) {
        draftRouteRequest($a, 'POST', 415, ['headers' => ['X-CSRF-Token' => $csrf, 'Content-Type' => $type], 'body' => $validRaw]);
    }
    draftRouteRequest($a, 'POST', 415, ['headers' => ['X-CSRF-Token' => $csrf, 'Content-Encoding' => 'gzip', 'Content-Type' => 'application/json'], 'body' => $validRaw]);
    draftRouteRequest($a, 'POST', 413, ['headers' => ['X-CSRF-Token' => $csrf, 'Content-Type' => 'application/json'], 'body' => str_repeat(' ', 32769)]);
    echo "PASS draft HTTP strict types, JSON ambiguity, UTF-8, size, section and encoding contracts\n";

    draftRouteAssert(draftRouteRequest($b, 'GET', 200)['draft'] === $empty, 'User B received User A draft.');
    $savedB = draftRouteSave($b, $csrfB, 200, draftRouteInput(0, ['city' => 'Only B']))['draft'];
    draftRouteAssert(draftRouteRequest($a, 'GET', 200)['draft'] === $saved && draftRouteRequest($b, 'GET', 200)['draft'] === $savedB, 'Current session isolation failed.');
    draftRouteAssert(draftRouteRequest($a, 'GET', 200, ['headers' => ['X-User-ID' => (string) $userB]])['draft'] === $saved, 'Untrusted identity header selected another User.');
    echo "PASS draft HTTP current-User isolation without Portfolio, consent or completion authority\n";

    // Real UPDATE failure with database-scoped DDL. MySQL trigger creation can
    // require global privileges under binary logging; never grant those here.
    $phase = 'temporary check';
    $faultInput = draftRouteInput(4, ['city' => $sentinel]);
    $database->exec('ALTER TABLE onboarding_drafts ADD CONSTRAINT draft_http_test_failure CHECK (last_operation_id <> ' . $database->quote($faultInput['operationId']) . ')');
    try { draftRouteSave($a, $csrf, 503, $faultInput); }
    finally { $database->exec('ALTER TABLE onboarding_drafts DROP CHECK draft_http_test_failure'); }
    $phase = 'draft table failure';
    $database->exec('RENAME TABLE onboarding_drafts TO draft_http_test_parked');
    try { draftRouteRequest($a, 'GET', 503); draftRouteSave($a, $csrf, 503, draftRouteInput(3)); }
    finally { $database->exec('RENAME TABLE draft_http_test_parked TO onboarding_drafts'); }
    $phase = 'authentication query failure';
    $database->exec('RENAME TABLE users TO draft_http_test_users');
    try { draftRouteRequest($a, 'GET', 503); }
    finally { $database->exec('RENAME TABLE draft_http_test_users TO users'); }
    $phase = 'configuration failure';
    draftRouteStart('1', ['DB_PORT' => 'invalid']); draftRouteRequest($a, 'GET', 503);
    draftRouteStart('1', ['DB_NAME' => 'ather_career_test_unavailable']); draftRouteRequest($a, 'GET', 503);
    $phase = 'session storage failure';
    draftRouteStart('1', [], $root . '/missing-sessions');
    draftRouteRequest($a, 'GET', 503); draftRouteRequest($a, 'POST', 503);
    draftRouteStart('1');
    $phase = 'corrupt stored draft';
    $payload = $database->query("SELECT payload FROM onboarding_drafts WHERE user_id=$userA")->fetchColumn();
    $database->prepare('UPDATE onboarding_drafts SET payload=? WHERE user_id=?')->execute(['{"unknown":"private"}', $userA]);
    try { draftRouteRequest($a, 'GET', 503); }
    finally { $database->prepare('UPDATE onboarding_drafts SET payload=? WHERE user_id=?')->execute([$payload, $userA]); }
    draftRouteAssert(draftRouteRequest($a, 'GET', 200)['draft'] === $saved, 'Temporary failure changed stored result.');

    $phase='catalog historical receipt';
    $historicalFields=['country_code'=>'US','preferred_language'=>'fr-CA','professional_status'=>'Research <b>candidate</b>',
        'specialization'=>'  تخصص é  ','headline'=>str_repeat('h',200),'phone'=>'Untouched historical phone'];
    $receipt=draftTestSeedHistorical($database,$catalogUser,3,'public_identity',
        array_intersect_key($historicalFields,array_flip(['country_code','preferred_language'])),$historicalFields);
    $legacyBrowser=draftRouteBrowser($catalogUser); $resume=draftRouteRequest($legacyBrowser,'GET',200);
    $legacy=$resume['draft']; $legacyCsrf=$resume['csrf_token'];
    draftRouteAssert($legacy['fields']===onboardingDraftDecode(onboardingDraftEncode($historicalFields)), 'Historical resume coerced values.');
    $receipt['fields']=(object)array_reverse($receipt['fields'],true);
    draftRouteAssert(draftRouteSave($legacyBrowser,$legacyCsrf,200,$receipt)['draft']===$legacy, 'Historical exact retry changed result/time/version.');
    $changed=$receipt; $changed['fields']=(object)['country_code'=>'YY']; draftRouteSave($legacyBrowser,$legacyCsrf,409,$changed);
    $changed['fields']=(object)['country_code'=>['bad']]; draftRouteSave($legacyBrowser,$legacyCsrf,400,$changed);
    draftRouteAssert(draftRouteSave($legacyBrowser,$csrf,403,$receipt)['code']==='invalid_csrf', 'Receipt bypassed session CSRF.');
    $database->exec("UPDATE users SET authz_version=2 WHERE id=$catalogUser");
    draftRouteAssert(draftRouteSave($legacyBrowser,$legacyCsrf,403,$receipt)['code']==='access_denied', 'Receipt bypassed current authorization.');
    $database->exec("UPDATE users SET authz_version=1 WHERE id=$catalogUser");
    $legacyBrowser=draftRouteBrowser($catalogUser); $legacyCsrf=draftRouteRequest($legacyBrowser,'GET',200)['csrf_token'];
    foreach (['country_code'=>['US','CA','ZZ','XK','UK','jo','JO '], 'preferred_language'=>['fr-CA','AR','en-US'],
        'professional_status'=>['early','experienced','independent','Research sabbatical'],
        'specialization'=>['',' ',"bad\0text","bad\ntext",str_repeat('😀',151)]] as $field=>$badValues) {
        $section=in_array($field,['country_code','preferred_language'],true)?'public_identity':'professional_snapshot';
        foreach (array_merge($badValues,[false,1,[],new stdClass()]) as $bad) {
            draftRouteSave($legacyBrowser,$legacyCsrf,400,draftRouteInput(3,[$field=>$bad],$section));
        }
    }
    echo "PASS draft HTTP current Profile catalog and specialization rejection, exact historical receipts, misuse and authorization boundaries\n";
    $current=draftRouteSave($legacyBrowser,$legacyCsrf,200,draftRouteInput(3,['display_name'=>'Only this edit']))['draft'];
    foreach ($historicalFields as $field=>$value) { draftRouteAssert($current['fields'][$field]===$value, 'Unrelated HTTP save changed historical fields.'); }
    draftRouteSave($legacyBrowser,$legacyCsrf,409,$receipt);
    $current=draftRouteSave($legacyBrowser,$legacyCsrf,200,draftRouteInput(4,['country_code'=>'QA','preferred_language'=>'ar']))['draft'];
    draftRouteAssert($current['fields']['country_code']==='QA' && $current['fields']['preferred_language']==='ar', 'Catalog replacement failed.');
    $current=draftRouteSave($legacyBrowser,$legacyCsrf,200,draftRouteInput(5,['country_code'=>null,'preferred_language'=>null]))['draft'];
    draftRouteAssert(!isset($current['fields']['country_code']) && !isset($current['fields']['preferred_language']), 'Explicit catalog null clear failed.');
    $current=draftRouteSave($legacyBrowser,$legacyCsrf,200,draftRouteInput(6,['professional_status'=>'employed','specialization'=>str_repeat('😀',150)],'professional_snapshot'))['draft'];
    draftRouteAssert($current['fields']['specialization']===str_repeat('😀',150), 'HTTP 150-code-point/600-byte boundary rejected.');
    $current=draftRouteSave($legacyBrowser,$legacyCsrf,200,draftRouteInput(7,['professional_status'=>null,'specialization'=>null],'professional_snapshot'))['draft'];
    draftRouteAssert(!isset($current['fields']['professional_status']) && !isset($current['fields']['specialization'])
        && $current['fields']['phone']===$historicalFields['phone'] && $current['fields']['headline']===$historicalFields['headline'], 'HTTP clearing/optional/headline preservation failed.');
    draftRouteAssert(draftRouteRequest($legacyBrowser,'GET',200)['draft']===$current, 'Historical replacement reload failed.');
    $privateTokens[]=$receipt['operationId'];
    echo "PASS draft HTTP historical omission, replacement, null clearing, Unicode boundary, incomplete reload and no profile writes\n";

    draftRouteStop();
    $log = file_get_contents($root . '/private-http.log');
    foreach (array_merge($privateTokens, [$sentinel, 'Only B', 'Candidate', 'مُؤمِن', $first['operationId'], $faultInput['operationId'], getenv('DB_PASSWORD')]) as $secret) {
        draftRouteAssert(!str_contains($log, $secret), 'Private HTTP log contains a forbidden value.');
    }
    draftRouteAssert(!preg_match('/SQLSTATE|PDOException|Stack trace|Uncaught|last_operation_digest/', $log), 'HTTP log contains exception or SQL diagnostics.');
    echo "PASS draft HTTP private cache headers and safe database/config/corrupt-data failures without private logs\n";
} catch (Throwable $error) {
    // Assertion diagnostics never contain bodies, credentials or private values.
    $message = get_class($error) === RuntimeException::class ? $error->getMessage() : 'Unexpected test infrastructure failure at ' . $phase . '.';
    fwrite(STDERR, 'FAIL draft HTTP: ' . $message . "\n"); $exitCode = 1;
} finally {
    draftRouteStop();
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    if (isset($database)) {
        foreach ($users as $id) { $database->exec("DELETE FROM onboarding_drafts WHERE user_id=$id"); $database->exec("DELETE FROM users WHERE id=$id"); }
        if ($initial !== null) { draftRouteAssert($initial === draftRouteSnapshot($database), 'Preexisting data changed or private HTTP fixtures remain.'); }
    }
    if ($environment instanceof TestEnvironment) {
        $environment->tearDown(); draftRouteAssert($environment->wasTornDown(), 'Private HTTP namespace cleanup failed.');
    }
}
if ($exitCode === 0) { echo "PASS draft HTTP preexisting data preservation and process/session/fixture cleanup\n"; }
ob_end_flush(); exit($exitCode);
