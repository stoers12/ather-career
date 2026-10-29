<?php
declare(strict_types=1);
require_once __DIR__ . '/../../tests/phase2/support/profile-version-fixtures.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/owner_session.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/operational_security.php';
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
ob_start();
function profileBrowser(?int $id, ?callable $mutate = null): Client
{
    global $port, $secrets;
    $jar = new CookieJar();
    if ($id !== null) {
        session_id(''); startOwnerSession(); establishVerifiedInternalUserSession($id, 1);
        if ($mutate !== null) { $mutate(); }
        $secrets[] = getCsrfToken(); $sid = session_id(); $secrets[] = $sid; session_write_close();
        $jar = CookieJar::fromArray([OWNER_SESSION_NAME => $sid], '127.0.0.1');
    }
    return new Client(['base_uri' => 'http://127.0.0.1:' . $port, 'cookies' => $jar, 'http_errors' => false, 'allow_redirects' => false, 'timeout' => 15]);
}
function profileHttp(Client $browser, string $method, int $status, array $options = []): string
{
    global $db;
    $before = profileTestSnapshot($db); $response = $browser->request($method, '/owner_profile.php', $options); $body = (string) $response->getBody();
    profileTestAssert($response->getStatusCode() === $status, 'Unexpected profile HTTP status: wanted ' . $status . ', got ' . $response->getStatusCode());
    if ($status >= 400) { profileTestAssert($before === profileTestSnapshot($db), 'Rejected profile request mutated database.'); }
    profileTestAssert(!preg_match('/SQLSTATE|Stack trace|Uncaught|mysql:host=/', $body), 'Unsafe profile error body.');
    if ($status === 200 || $status === 409) { profileTestAssert(str_contains($response->getHeaderLine('Cache-Control'), 'no-store'), 'Private profile cache header missing.'); }
    return $body;
}
function profileForm(Client $browser): array
{
    $body = profileHttp($browser, 'GET', 200);
    profileTestAssert(preg_match('/name="expected_profile_version" value="([0-9]+)"/', $body, $v) === 1, 'Loaded version absent from form.');
    profileTestAssert(preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $body, $csrf) === 1, 'CSRF absent from form.');
    preg_match('/name="profile_id" value="([0-9]+)"/', $body, $id);
    preg_match_all('/name="expected_profile_version" value="([0-9]+)"/', $body, $all);
    profileTestAssert(count(array_unique($all[1])) === 1, 'Profile forms use mismatched loaded versions.');
    $fields = ['action' => 'save_profile', 'expected_profile_version' => $v[1], 'csrf_token' => $csrf[1]];
    if (isset($id[1])) { $fields['profile_id'] = $id[1]; }
    return $fields;
}
function profilePost(Client $browser, array $fields, int $status): string { return profileHttp($browser, 'POST', $status, ['form_params' => $fields]); }
function profileFiles(string $root): array
{
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) { $files[$file->getPathname()] = hash_file('sha256', $file->getPathname()); }
    }
    ksort($files); return $files;
}
function profileUpload(Client $browser, array $form, string $png, int $status): string
{
    $parts = [];
    foreach (array_replace($form, ['action' => 'upload_profile_image']) as $name => $value) { $parts[] = ['name' => $name, 'contents' => $value]; }
    $parts[] = ['name' => 'profile_image', 'contents' => fopen($png, 'r'), 'filename' => 'synthetic.png'];
    return profileHttp($browser, 'POST', $status, ['multipart' => $parts]);
}
$db = getDatabaseConnection(); $initial = profileTestSnapshot($db); $users = []; $portfolios = []; $secrets = []; $environment = null; $process = null; $exit = 0; $phase = 'setup'; $constraint = false;
try {
    profileTestAssert(is_file('/.dockerenv'), 'HTTP fixtures require disposable Docker.');
    $environment = TestEnvironment::create(); $root = $environment->namespaceRoot . '/profile-http'; mkdir($root); mkdir($root . '/sessions'); mkdir($root . '/public'); mkdir($root . '/limits');
    symlink(realpath(__DIR__ . '/../..'), $root . '/app'); file_put_contents($root . '/public/owner_profile.php', "<?php\n\nrequire dirname(__DIR__) . '/app/owner_profile.php';\n");
    foreach (['p.php','p_contact.php','p_projects.php','owner_preview.php'] as $shim) { copy(__DIR__ . '/../../public/' . $shim, $root . '/public/' . $shim); }
    $png = $root . '/synthetic.png'; profileTestPng($png);
    session_save_path($root . '/sessions'); session_name(OWNER_SESSION_NAME); ini_set('session.cache_limiter', '');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $message); $address = stream_socket_get_name($socket, false); fclose($socket); $port = (int) substr($address, strrpos($address, ':') + 1);
    $env = array_intersect_key(getenv(), array_flip(['PATH', 'APP_ENV', 'ATHERCAR_TEST_MODE', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'ATHERCAR_TEST_DB_NAME']));
    $env += ['SESSION_COOKIE_SECURE' => '0', 'ATHERCAR_REGISTRATION_ENABLED' => '0', 'ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED' => '0',
        'ATHERCAR_STORAGE_ROOT' => $environment->storageRoot, 'RATE_LIMIT_STATE_DIR' => $root . '/limits', 'TRUSTED_PROXY_CIDRS' => '', 'PUBLIC_BASE_URL'=>'https://profile-test.invalid'];
    $process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $root . '/sessions', '-d', 'display_errors=0', '-S', '127.0.0.1:' . $port, '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', $root . '/private-http.log', 'a'], 2 => ['file', $root . '/private-http.log', 'a']], $pipes, null, $env);
    profileTestAssert(is_resource($process), 'HTTP server unavailable.'); fclose($pipes[0]);
    for ($i = 0; $i < 60; $i++) { $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $message, .1); if (is_resource($socket)) { fclose($socket); break; } usleep(50000); }
    for ($i = 0; $i < 2; $i++) {
        $db->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES ('https://profile-http.invalid/', ?, 'active', 1)")->execute([bin2hex(random_bytes(16))]);
        $users[] = (int) $db->lastInsertId(); $db->prepare('INSERT INTO portfolios (owner_user_id) VALUES (?)')->execute([$users[$i]]); $portfolios[] = (int) $db->lastInsertId(); journeyFixtureLegacyOwner($db, $users[$i]);
    }
    [$a, $b] = $users; $browserA = profileBrowser($a); $browserB = profileBrowser($b); $tab2 = profileBrowser($a);
    $phase = 'form CAS'; $form = profileForm($browserA); profileTestAssert($form['expected_profile_version'] === '0', 'Absent profile version not zero.');
    profilePost($browserA, $form + ['full_name' => '  Saved Name  ', 'location' => 'Keep location'], 303);
    $form = profileForm($browserA); $old = profileForm($tab2); $id = (int) $form['profile_id'];
    profilePost($browserA, $form + ['full_name' => 'New current name'], 303);
    $typed = '  Unsaved <name>  '; $secrets[] = $typed;
    $body = profilePost($tab2, $old + ['full_name' => $typed, 'public_email_present' => '1', 'public_email_visible' => '1'], 409);
    profileTestAssert(str_contains($body, 'value="  Unsaved &lt;name&gt;  "') && str_contains($body, 'name="expected_profile_version" value="1"')
        && preg_match('/name="public_email_visible"[^>]* checked/', $body) === 1 && str_contains($body, 'Reload saved profile'), 'Conflict discarded input/choice/version or lacks explicit recovery.');
    profileTestAssert($db->query("SELECT full_name FROM personal_info WHERE id=$id")->fetchColumn() === 'New current name', 'Stale form overwrote current name.');
    $form = profileForm($browserA); profilePost($browserA, $form + ['full_name' => 'New current name'], 303);
    profileTestAssert((int) profileForm($browserA)['expected_profile_version'] === (int) $form['expected_profile_version'] + 1, 'HTTP same-value save did not increment.');
    echo "PASS profile real HTTP loaded tokens, two-tab conflict, retained text/visibility/original version, explicit recovery and same-value saves\n";

    $phase = 'patch and visibility'; $form = profileForm($browserA); profilePost($browserA, $form + ['hero_headline' => ''], 303);
    profileTestAssert($db->query("SELECT hero_headline FROM personal_info WHERE id=$id")->fetchColumn() === null && $db->query("SELECT location FROM personal_info WHERE id=$id")->fetchColumn() === 'Keep location', 'Clear/omission semantics wrong.');
    foreach ([['public_email_visible' => '1'], [], ['public_email_visible' => '0'], ['public_email_present' => '1'], ['public_email_present' => '1', 'public_email_visible' => '1']] as $input) {
        $before = (int) $db->query("SELECT public_email_visible FROM personal_info WHERE id=$id")->fetchColumn();
        profilePost($browserA, profileForm($browserA) + $input, 303);
        $expected = $input === [] ? $before : (int) (($input['public_email_visible'] ?? '0') === '1');
        profileTestAssert((int) $db->query("SELECT public_email_visible FROM personal_info WHERE id=$id")->fetchColumn() === $expected, 'Explicit visibility/preservation failed.');
    }
    foreach ([null, '', '01', '-1', '1.0', '2147483648', ['1']] as $token) {
        $input = profileForm($browserA); if ($token === null) { unset($input['expected_profile_version']); } else { $input['expected_profile_version'] = $token; }
        profilePost($browserA, $input + ['full_name' => 'Still here'], 409);
    }
    foreach ([['full_name' => ['invalid']], ['email' => 'invalid'], ['public_email_visible' => ['1']], ['public_email_present' => '0'], ['profile_image_path' => 'forged']] as $input) { profilePost($browserA, profileForm($browserA) + $input, 422); }
    $version = (int) profileForm($browserA)['expected_profile_version']; $db->exec("UPDATE personal_info SET profile_version=2147483647 WHERE id=$id");
    profilePost($browserA, profileForm($browserA), 409); $db->exec("UPDATE personal_info SET profile_version=$version WHERE id=$id");
    echo "PASS profile HTTP omitted fields, explicit NULL clears, deliberate visibility, invalid/missing tokens, malformed patches and exhaustion\n";

    $phase = 'per-type contact HTTP';
    profilePost($browserA, profileForm($browserA) + ['email'=>'contact-http@example.test','phone_primary'=>'+962 79 345 6789','phone_secondary'=>'+962 78 876 5432'], 303);
    $slug='contact-http-'.bin2hex(random_bytes(6));
    // Public-read fixture only; publication authorization is tested separately.
    $db->prepare('UPDATE portfolios SET public_slug=?,is_published=1,published_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$slug,$portfolios[0]]);
    foreach ([[0,0],[1,0],[0,1],[1,1]] as [$email,$phone]) {
        profilePost($browserA, profileForm($browserA) + ['public_email_visible'=>(string)$email,'public_phone_visible'=>(string)$phone],303);
        $loaded=profileHttp($browserA,'GET',200);
        foreach (['public_email_visible'=>$email,'public_phone_visible'=>$phone] as $field=>$value) {
            profileTestAssert((preg_match('/name="'.$field.'"[^>]* checked/',$loaded)===1)===(bool)$value,'HTTP reload lost independent checkbox.');
        }
        foreach ([['GET','/p.php?slug='.$slug,200],['POST','/p_contact.php?slug='.$slug,422]] as [$method,$route,$code]) {
            $response=$browserA->request($method,$route); $html=(string)$response->getBody();
            profileTestAssert($response->getStatusCode()===$code && str_contains($html,'contact-http@example.test')===(bool)$email
                && str_contains($html,'mailto:contact-http@example.test')===(bool)$email
                && str_contains($html,'+962 79 345 6789')===(bool)$phone && str_contains($html,'tel:+962793456789')===(bool)$phone
                && !str_contains($html,'+962 78 876 5432'),'Real public/contact-error route leaked hidden contacts.');
        }
        $response=$browserA->get('/p_projects.php?slug='.$slug); $json=(string)$response->getBody();
        profileTestAssert($response->getStatusCode()===200 && !str_contains($json,'contact-http@example.test') && !str_contains($json,'345 6789')
            && !str_contains($json,'email') && !str_contains($json,'phone'),'Contact appeared in public JSON allow-list.');
    }
    $preview=(string)$browserA->get('/owner_preview.php')->getBody();
    profileTestAssert(str_contains($preview,'contact-http@example.test') && str_contains($preview,'tel:+962793456789'),'Private HTTP preview lost saved contacts.');
    $form=profileForm($browserA); profilePost($browserA,$form+['public_email_present'=>'1'],303);
    $row=loadAuthorizedPersonalInfo($db,AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($a),$portfolios[0]));
    profileTestAssert((int)$row['public_email_visible']===0 && (int)$row['public_phone_visible']===1,'HTTP unchecked email hid phone.');
    profilePost($browserA,profileForm($browserA)+['public_phone_visible'=>'0'],303);
    profilePost($browserA,profileForm($browserA)+['professional_title'=>'Text preserves private contacts'],303);
    foreach ([['public_phone_visible'=>['1']],['public_phone_present'=>'0'],['public_email_visible'=>'on']] as $bad) { profilePost($browserA,profileForm($browserA)+['location'=>'Must not save']+$bad,422); }
    foreach ([['public_contact_present'=>'1'],['public_contact_visible'=>'0'],['public_contact_visible'=>'1']] as $oldInput) {
        $form=profileForm($browserA); $retained=profilePost($browserA,$form+['full_name'=>'Old aggregate retained text']+$oldInput,409);
        profileTestAssert(str_contains($retained,'value="Old aggregate retained text"') && str_contains($retained,'contact form is outdated')
            && str_contains($retained,'name="expected_profile_version" value="'.$form['expected_profile_version'].'"'),'Old aggregate form lost input or upgraded token.');
    }
    $before=profileTestSnapshot($db); $bad=profileForm($browserA); $bad['csrf_token']='invalid';
    profilePost($browserA,$bad+['public_email_visible'=>'1','public_phone_visible'=>'1'],403);
    profileTestAssert($before===profileTestSnapshot($db),'CSRF contact change wrote data.');
    echo "PASS contact-types real HTTP four-combination saves reloads public HTML mailto tel contact errors JSON and private preview; per-type off malformed old aggregate409 retention and CSRF\n";

    $phase = 'authorization'; profilePost($browserB, profileForm($browserB) + ['full_name' => 'Other account'], 303); $otherForm = profileForm($browserB);
    $forged = profileForm($browserA); $forged['profile_id'] = $otherForm['profile_id']; profilePost($browserA, $forged + ['full_name' => 'Forbidden'], 404);
    $forged = profileForm($browserA); $forged['csrf_token'] = $otherForm['csrf_token']; profilePost($browserA, $forged, 403);
    foreach (['idle', 'absolute', 'revoked'] as $case) {
        $client = profileBrowser($a, static function () use ($case): void {
            if ($case === 'idle') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] -= 2000; $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] -= 2000; }
            if ($case === 'absolute') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] -= 50000; }
            if ($case === 'revoked') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authz_version'] = 2; }
        }); profileHttp($client, 'GET', 403);
    }
    $client = profileBrowser($a); $db->exec("UPDATE users SET account_status='disabled' WHERE id=$a"); profileHttp($client, 'GET', 403); $db->exec("UPDATE users SET account_status='active' WHERE id=$a");
    profileHttp(profileBrowser(null), 'GET', 303);
    echo "PASS profile HTTP account isolation, CSRF, idle/absolute expiration, revocation and disabled authorization without new readiness gates\n";

    $phase = 'upload SQL rollback'; $form = profileForm($browserA); profileUpload($browserA, $form, $png, 303);
    $image = $db->query("SELECT profile_image_path FROM personal_info WHERE id=$id")->fetchColumn();
    putenv('ATHERCAR_STORAGE_ROOT=' . $environment->storageRoot);
    profileTestAssert(profileTestImageExists($image, $portfolios[0]), 'Uploaded image not retained after confirmed commit.');
    profilePost($browserA, $form + ['full_name' => 'Old form'], 409);
    profilePost($browserA, profileForm($browserA) + ['location' => 'New location'], 303);
    profileTestAssert($db->query("SELECT profile_image_path FROM personal_info WHERE id=$id")->fetchColumn() === $image, 'Text save replayed/erased image.');
    $remove = profileForm($browserA); $remove['action'] = 'remove_profile_image'; profilePost($browserA, $remove, 303);
    profileTestAssert(!profileTestImageExists($image, $portfolios[0]), 'Confirmed removal left retired original.');
    $faultPortfolio = $portfolios[0];
    $db->exec("ALTER TABLE personal_info ADD CONSTRAINT chk_profile_http_fault CHECK (portfolio_id <> $faultPortfolio OR profile_image_path IS NULL)"); $constraint = true;
    $before = profileFiles($environment->storageRoot); profileUpload($browserA, profileForm($browserA), $png, 503);
    profileTestAssert($before === profileFiles($environment->storageRoot), 'Confirmed SQL failure leaked staged media.');
    $db->exec('ALTER TABLE personal_info DROP CHECK chk_profile_http_fault'); $constraint = false;
    echo "PASS profile real multipart upload/remove, text preservation and confirmed SQL rollback media cleanup\n";
    $oldUpload = profileForm($browserA);
    profilePost($browserA, $oldUpload + ['location' => 'Newer than rejected upload'], 303);
    putenv('RATE_LIMIT_STATE_DIR=' . $root . '/limits');
    $limitContext = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($a), $portfolios[0]);
    for ($i = 0; $i <= OWNER_UPLOAD_RATE_LIMIT_ATTEMPTS; $i++) { if (!consumeOwnerUploadRateLimit($limitContext)['allowed']) { break; } }
    $body = profileUpload($browserA, $oldUpload, $png, 429);
    profileTestAssert(str_contains($body, 'name="expected_profile_version" value="' . $oldUpload['expected_profile_version'] . '"') && str_contains($body, 'Reload saved profile'), 'Rate-limit denial rebound old upload to a fresh version.');
    echo "PASS profile HTTP pre-handler upload rejection retains original token and explicit reload requirement\n";
    proc_terminate($process); proc_close($process); $process = null;
    $log = file_get_contents($root . '/private-http.log');
    foreach ([...$secrets, getenv('DB_PASSWORD'), 'New current name', 'Unsaved &lt;name&gt;'] as $secret) { profileTestAssert(!str_contains($log, $secret), 'Private input/token in HTTP log.'); }
    profileTestAssert(!preg_match('/SQLSTATE|Stack trace|Uncaught/', $log), 'Unsafe HTTP diagnostic.');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL profile HTTP at ' . $phase . ': ' . ($error instanceof PDOException ? 'PDO infrastructure/constraint error ' . ($error->errorInfo[1] ?? 'unknown') : $error->getMessage()) . "\n"); $exit = 1;
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    if ($constraint) { $db->exec('ALTER TABLE personal_info DROP CHECK chk_profile_http_fault'); }
    foreach ($portfolios as $portfolio) { $db->exec("DELETE FROM personal_info WHERE portfolio_id=$portfolio; DELETE FROM portfolios WHERE id=$portfolio"); }
    foreach ($users as $user) { $db->exec("DELETE FROM users WHERE id=$user"); }
    if ($environment !== null) { $environment->tearDown(); }
    profileTestAssert($initial === profileTestSnapshot($db), 'HTTP fixtures changed preexisting data.');
}
if ($exit === 0) { echo "PASS profile HTTP private logs and complete process/session/file/database fixture cleanup\n"; }
ob_end_flush(); exit($exit);
