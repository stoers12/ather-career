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
$browserEvidence=getenv('ATHERCAR_FINAL_PROFILE_BROWSER_EVIDENCE') ?: null;
$db = getDatabaseConnection(); $initial = profileTestSnapshot($db); $users = []; $portfolios = []; $secrets = []; $environment = null; $process = null; $exit = 0; $phase = 'setup'; $constraint = false;
try {
    profileTestAssert(is_file('/.dockerenv'), 'HTTP fixtures require disposable Docker.');
    $environment = TestEnvironment::create(); $root = $environment->namespaceRoot . '/profile-http'; mkdir($root); mkdir($root . '/sessions'); mkdir($root . '/public'); mkdir($root . '/limits');
    symlink(realpath(__DIR__ . '/../..'), $root . '/app'); foreach (glob(__DIR__.'/../../public/*.php') as $shim) { copy($shim,$root.'/public/'.basename($shim)); }
    foreach (['admin.css','style.css','owner_workspace.css','admin.js','owner_theme.js'] as $asset) { copy(__DIR__.'/../../'.$asset,$root.'/public/'.$asset); }
    mkdir($root.'/public/assets'); symlink(realpath(__DIR__.'/../../assets/fonts'),$root.'/public/assets/fonts');
    mkdir($root.'/public/assets/images'); copy(__DIR__.'/../../assets/images/ather-navbar-logo.png',$root.'/public/assets/images/ather-navbar-logo.png');
    $png = $root . '/synthetic.png'; profileTestPng($png);
    session_save_path($root . '/sessions'); session_name(OWNER_SESSION_NAME); ini_set('session.cache_limiter', '');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $message); $address = stream_socket_get_name($socket, false); fclose($socket); $port = $browserEvidence===null ? (int) substr($address, strrpos($address, ':') + 1) : 8082;
    $env = array_intersect_key(getenv(), array_flip(['PATH', 'APP_ENV', 'ATHERCAR_TEST_MODE', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'ATHERCAR_TEST_DB_NAME']));
    $env += ['SESSION_COOKIE_SECURE' => '0', 'ATHERCAR_REGISTRATION_ENABLED' => '0', 'ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED' => '0',
        'ATHERCAR_STORAGE_ROOT' => $environment->storageRoot, 'RATE_LIMIT_STATE_DIR' => $root . '/limits', 'TRUSTED_PROXY_CIDRS' => '', 'PUBLIC_BASE_URL'=>'https://profile-test.invalid'];
    $process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $root . '/sessions', '-d', 'display_errors=0', '-S', ($browserEvidence===null ? '127.0.0.1:' : '0.0.0.0:') . $port, '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', $root . '/private-http.log', 'a'], 2 => ['file', $root . '/private-http.log', 'a']], $pipes, null, $env);
    profileTestAssert(is_resource($process), 'HTTP server unavailable.'); fclose($pipes[0]);
    for ($i = 0; $i < 60; $i++) { $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $message, .1); if (is_resource($socket)) { fclose($socket); break; } usleep(50000); }
    for ($i = 0; $i < 2; $i++) {
        $db->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES ('https://profile-http.invalid/', ?, 'active', 1)")->execute([bin2hex(random_bytes(16))]);
        $users[] = (int) $db->lastInsertId(); $db->prepare('INSERT INTO portfolios (owner_user_id) VALUES (?)')->execute([$users[$i]]); $portfolios[] = (int) $db->lastInsertId(); journeyFixtureLegacyOwner($db, $users[$i]);
    }
    if ($browserEvidence!==null) {
        profileTestAssert(is_dir($browserEvidence) && !is_link($browserEvidence), 'Browser evidence directory unavailable.');
        $db->prepare("INSERT INTO personal_info (portfolio_id,full_name,professional_title,location,country_code,preferred_language,professional_status) VALUES (?,'Synthetic Profile Review','Original title','Original location','US','zz','retired_status')")->execute([$portfolios[0]]);
        $client=profileBrowser($users[0]); $cookie=$client->getConfig('cookies')->toArray()[0];
        file_put_contents($browserEvidence.'/private-profile-context.json',json_encode(['cookie'=>$cookie],JSON_THROW_ON_ERROR)); chmod($browserEvidence.'/private-profile-context.json',0600);
        $deadline=time()+600;
        while (!is_file($browserEvidence.'/stop-profile') && time()<$deadline) { usleep(100000); }
        profileTestAssert(is_file($browserEvidence.'/stop-profile'),'Browser runner did not signal cleanup.');
        echo "PASS final-profile browser fixture private logs/data/process/session cleanup pending final snapshot\n";
    } else {
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


    $phase='final fields';
    $exact="  تخصص <b>e\u{0301} 😀</b>  ";
    $values=['country_code'=>'JO','preferred_language'=>'ar','professional_status'=>'student','specialization'=>$exact];
    $form=profileForm($browserA); profilePost($browserA,$form+$values,303);
    $row=$db->query("SELECT * FROM personal_info WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    foreach ($values as $field=>$value) { profileTestAssert($row[$field]===$value,'HTTP changed exact final value.'); }
    $body=profileHttp($browserA,'GET',200);
    profileTestAssert(str_contains($body,htmlspecialchars($exact,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')) && preg_match('/value="JO" selected/',$body)===1,'Reload lost final values.');
    foreach (['country_code','preferred_language','professional_status','specialization'] as $field) {
        profilePost($browserA,profileForm($browserA)+[$field=>''],303);
        profileTestAssert($db->query("SELECT $field FROM personal_info WHERE id=$id")->fetchColumn()===null,'Explicit empty did not clear NULL.');
    }
    profilePost($browserA,profileForm($browserA)+$values,303);
    profilePost($browserA,profileForm($browserA)+['hero_headline'=>'Legacy headline unchanged semantics'],303);
    $row=$db->query("SELECT * FROM personal_info WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    foreach ($values as $field=>$value) { profileTestAssert($row[$field]===$value,'Omitted final field erased.'); }
    foreach ([['country_code'=>'jo'],['country_code'=>'US'],['preferred_language'=>'AR'],['professional_status'=>'independent'],['specialization'=>'   '],['specialization'=>"bad\n"],['specialization'=>"bad\xFF"],['specialization'=>str_repeat('😀',151)],['country_code'=>['JO']],['specialization'=>['text']]] as $bad) {
        $form=profileForm($browserA); $body=profilePost($browserA,$form+$bad,422);
        profileTestAssert(str_contains($body,'name="expected_profile_version" value="'.$form['expected_profile_version'].'"'),'Error lost original token.');
        if (is_string(reset($bad))) { profileTestAssert(str_contains($body,htmlspecialchars(reset($bad),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')),'Error lost submitted text.'); }
    }
    $old=profileForm($tab2); profilePost($browserA,profileForm($browserA)+['specialization'=>'Current private specialization'],303);
    $body=profilePost($tab2,$old+['country_code'=>'EG','preferred_language'=>'en','professional_status'=>'graduate','specialization'=>'Stale private specialization'],409);
    profileTestAssert(str_contains($body,'Stale private specialization') && str_contains($body,'value="EG" selected') && str_contains($body,'value="graduate" selected') && str_contains($body,'value="'.$old['expected_profile_version'].'"'),'Conflict lost final fields/original token.');
    // Test-only historical values: the form can retain them, not forge them on another row.
    $db->exec("UPDATE personal_info SET country_code='US',preferred_language='zz',professional_status='retired_status',profile_version=profile_version+1 WHERE id=$id");
    $body=profileHttp($browserA,'GET',200); profileTestAssert(str_contains($body,'Older saved choice'),'Retired choices silently coerced.');
    profilePost($browserA,profileForm($browserA)+['country_code'=>'US','preferred_language'=>'zz','professional_status'=>'retired_status','location'=>'Unrelated edit'],303);
    $row=$db->query("SELECT * FROM personal_info WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    profileTestAssert($row['country_code']==='US' && $row['preferred_language']==='zz' && $row['professional_status']==='retired_status','Unchanged retired values lost.');
    profilePost($browserA,profileForm($browserA)+['country_code'=>'JO','preferred_language'=>'en','professional_status'=>'employed','specialization'=>'Private projection sentinel'],303);
    $db->prepare('UPDATE portfolios SET public_slug=?,is_published=1 WHERE id=?')->execute(['final-profile-'.$portfolios[0],$portfolios[0]]);
    foreach (['p.php','p_projects.php'] as $route) {
        $response=$browserA->get('/'.$route.'?slug=final-profile-'.$portfolios[0]); $body=(string)$response->getBody();
        profileTestAssert($response->getStatusCode()===200,'Public fixture unavailable.');
        foreach ([...FINAL_PROFILE_FIELDS,'Private projection sentinel'] as $private) { profileTestAssert(!str_contains($body,$private),'Public HTML/JSON leaked final data.'); }
    }
    $response=$browserA->post('/p_contact.php?slug=final-profile-'.$portfolios[0],['form_params'=>['name'=>'','email'=>'','message'=>'']]);
    profileTestAssert($response->getStatusCode()===422 && str_contains((string)$response->getBody(),'New current name'),'Contact validation projection was not exercised.');
    foreach ([...FINAL_PROFILE_FIELDS,'Private projection sentinel'] as $private) { profileTestAssert(!str_contains((string)$response->getBody(),$private),'Contact output leaked final data.'); }
    echo "PASS final-profile real HTTP save/clear/omit/reload, exact Unicode, malformed/forged input, retired preservation, stale retention and public exclusion\n";

    $phase = 'patch and visibility'; $form = profileForm($browserA); profilePost($browserA, $form + ['hero_headline' => ''], 303);
    profileTestAssert($db->query("SELECT hero_headline FROM personal_info WHERE id=$id")->fetchColumn() === null && $db->query("SELECT location FROM personal_info WHERE id=$id")->fetchColumn() === 'Unrelated edit', 'Clear/omission semantics wrong.');
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

    $phase = 'authorization'; profilePost($browserB, profileForm($browserB) + ['full_name' => 'Other account'], 303); $otherForm = profileForm($browserB);
    $forged = profileForm($browserA); $forged['profile_id'] = $otherForm['profile_id']; profilePost($browserA, $forged + ['full_name' => 'Forbidden'], 404);
    profilePost($browserA,$forged+['country_code'=>'EG','preferred_language'=>'ar','professional_status'=>'other','specialization'=>'Forbidden final fields'],404);
    $forged = profileForm($browserA); $forged['csrf_token'] = $otherForm['csrf_token']; profilePost($browserA, $forged, 403);
    profilePost($browserA,$forged+['country_code'=>'EG','specialization'=>'Forbidden final fields'],403);
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
    $row=$db->query("SELECT country_code,preferred_language,professional_status,specialization FROM personal_info WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    profileTestAssert($row===['country_code'=>'JO','preferred_language'=>'en','professional_status'=>'employed','specialization'=>'Private projection sentinel'],'Text/photo/contact actions lost final fields.');
    }
    proc_terminate($process); proc_close($process); $process = null;
    $log = file_get_contents($root . '/private-http.log');
    foreach ([...$secrets, getenv('DB_PASSWORD'), 'New current name', 'Unsaved &lt;name&gt;'] as $secret) { profileTestAssert(!str_contains($log, $secret), 'Private input/token in HTTP log.'); }
    profileTestAssert(!preg_match('/SQLSTATE|Stack trace|Uncaught/', $log), 'Unsafe HTTP diagnostic.');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL final-profile HTTP at ' . $phase . ': ' . ($error instanceof PDOException ? 'PDO infrastructure/constraint error ' . ($error->errorInfo[1] ?? 'unknown') : $error->getMessage()) . "\n"); $exit = 1;
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    if ($constraint) { $db->exec('ALTER TABLE personal_info DROP CHECK chk_profile_http_fault'); }
    foreach ($portfolios as $portfolio) { $db->exec("DELETE FROM personal_info WHERE portfolio_id=$portfolio; DELETE FROM portfolios WHERE id=$portfolio"); }
    foreach ($users as $user) { $db->exec("DELETE FROM users WHERE id=$user"); }
    if ($browserEvidence!==null) { foreach (['private-profile-context.json','stop-profile'] as $file) { if (is_file($browserEvidence.'/'.$file)) { unlink($browserEvidence.'/'.$file); } } }
    if ($environment !== null) { $environment->tearDown(); }
    profileTestAssert($initial === profileTestSnapshot($db), 'HTTP fixtures changed preexisting data.');
}
if ($exit === 0) { echo "PASS final-profile HTTP private logs and complete process/session/file/database fixture cleanup\n"; }
ob_end_flush(); exit($exit);
