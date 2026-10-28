<?php
declare(strict_types=1);
require_once __DIR__ . '/../../tests/phase2/support/draft-fixtures.php';
require_once __DIR__ . '/../../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../../includes/owner_session.php';
require_once __DIR__ . '/../../includes/csrf.php';
$attestationBrowser = ($argv[1] ?? null) === '--attestations';
if ($attestationBrowser) { require_once __DIR__.'/../../tests/phase2/support/attestation-http-fixtures.php'; }
ob_start();
$evidence = getenv('ATHERCAR_EDITOR_EVIDENCE_DIR');
if (!is_file('/.dockerenv') || !is_string($evidence) || !is_dir($evidence) || is_link($evidence)) { exit(2); }
function editorFixtureSnapshot(PDO $db): array {
    $result=[];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows=array_map('serialize', $db->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC)); sort($rows); $result[$table]=hash('sha256',serialize($rows));
    }
    return $result;
}
$environment=null; $users=[]; $servers=[]; $code=0;
try {
    $db=getDatabaseConnection(); $initial=editorFixtureSnapshot($db);
    $environment=TestEnvironment::create(); $root=$environment->namespaceRoot.'/editor';
    mkdir($root,0700); mkdir($root.'/sessions',0700); mkdir($root.'/http',0700); mkdir($root.'/http/public',0700);
    symlink(realpath(__DIR__.'/../..'),$root.'/http/app');
    foreach (glob(__DIR__.'/../../public/*.php') as $shim) { copy($shim,$root.'/http/public/'.basename($shim)); }
    foreach (['landing.js','onboarding_editor.js','onboarding.css','style.css','admin.css','admin.js','owner_theme.js'] as $asset) { copy(__DIR__.'/../../'.$asset,$root.'/http/public/'.$asset); }
    mkdir($root.'/http/public/assets',0700); symlink(realpath(__DIR__.'/../../assets/fonts'),$root.'/http/public/assets/fonts');
    session_save_path($root.'/sessions'); session_name(OWNER_SESSION_NAME); ini_set('session.cache_limiter','');
    foreach (['a','b'] as $name) {
        $db->prepare('INSERT INTO users (oidc_issuer,oidc_subject,account_status,authz_version) VALUES (?,?,?,1)')->execute(['https://editor.invalid/',bin2hex(random_bytes(16)),'active']);
        $users[$name]=(int)$db->lastInsertId();
    }
    $cookies=[];
    foreach (['a','b','expired'] as $name) {
        session_id(''); startOwnerSession(); establishVerifiedInternalUserSession($users[$name==='b'?'b':'a'],1); getCsrfToken();
        if ($name==='a') {
            $context=requireAuthenticatedUser($db);
            draftTestSeedHistorical($db,$context->userId,3,'profile_identity',['slug_candidate'=>'candidate-only','phone'=>'Untouched private phone'],[
                'display_name'=>'Synthetic Demo','country_code'=>'US','preferred_language'=>'fr-CA','name_ar'=>'Untouched Arabic','city'=>'Untouched city',
                'professional_status'=>'Research <b>candidate</b>','headline'=>'Synthetic headline','university'=>'Untouched university',
                'slug_candidate'=>'candidate-only','phone'=>'Untouched private phone',
            ]);
        }
        if ($name==='expired') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at']-=50000; }
        $cookies[$name]=session_id(); session_write_close();
    }
    $env=array_intersect_key(getenv(),array_flip(['PATH','APP_ENV','ATHERCAR_TEST_MODE','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','ATHERCAR_TEST_DB_NAME']));
    $env+=['SESSION_COOKIE_SECURE'=>'0','ATHERCAR_REGISTRATION_ENABLED'=>'0'];
    if ($attestationBrowser) {
        mkdir($root.'/arrivals',0700);
        file_put_contents($root.'/control.json',json_encode(['browser'=>true,'language'=>'en','version'=>'v1'],JSON_THROW_ON_ERROR)); chmod($root.'/control.json',0600);
        attestationHttpFixtureCopies($root);
        copy($root.'/synthetic-public/owner_onboarding_attestation.php',$root.'/http/public/owner_onboarding_attestation.php');
        mkdir($root.'/http-null',0700); mkdir($root.'/http-null/public',0700);
        symlink(realpath(__DIR__.'/../..'),$root.'/http-null/app');
        foreach (glob(__DIR__.'/../../public/*.php') as $shim) { copy($shim,$root.'/http-null/public/'.basename($shim)); }
        foreach (['landing.js','onboarding_editor.js','onboarding.css','style.css'] as $asset) { copy(__DIR__.'/../../'.$asset,$root.'/http-null/public/'.$asset); }
        mkdir($root.'/http-null/public/assets',0700); symlink(realpath(__DIR__.'/../../assets/fonts'),$root.'/http-null/public/assets/fonts');
    }
    foreach (($attestationBrowser ? [8080=>'1',8081=>'1',8082=>'1'] : [8080=>'1',8081=>'0']) as $port=>$gate) {
        $childEnv=$env; $childEnv['ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED']=$gate;
        $childEnv['ATHERCAR_ONBOARDING_ATTESTATION_HTTP_ENABLED']=$attestationBrowser && $port!==8082 ? '1' : '0';
        $documentRoot=$attestationBrowser && $port!==8080 ? $root.'/http-null/public' : $root.'/http/public';
        $process=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/sessions','-d','display_errors=0','-S','0.0.0.0:'.$port,'-t',$documentRoot],[0=>['pipe','r'],1=>['file',$root.'/http.log','a'],2=>['file',$root.'/http.log','a']],$pipes,null,$childEnv);
        if (!is_resource($process)) { throw new RuntimeException('Private editor server unavailable'); } fclose($pipes[0]); $servers[]=$process;
    }
    $ready=false;
    for ($i=0;$i<100;$i++) { $socket=@stream_socket_client('tcp://127.0.0.1:8080',$errno,$message,.1); if (is_resource($socket)) { fclose($socket); $ready=true; break; } usleep(50000); }
    if (!$ready) { throw new RuntimeException('Editor server startup failed'); }
    file_put_contents($evidence.'/private-context.json',json_encode(['sessions'=>$cookies]+($attestationBrowser ? ['control'=>$root.'/control.json'] : []),JSON_THROW_ON_ERROR));
    if ($attestationBrowser) { chmod($evidence.'/private-context.json',0600); }
    // Browser process gets only synthetic cookies through a private runner file.
    // Never serve a fixture login route or expose this file via the document root.
    $deadline=time()+900;
    while (!is_file($evidence.'/stop') && time()<$deadline) { usleep(100000); }
    if (!is_file($evidence.'/stop')) { throw new RuntimeException('Browser runner did not signal cleanup'); }
    $payload=json_decode($db->query('SELECT payload FROM onboarding_drafts WHERE user_id='.$users['a'])->fetchColumn(),true,16,JSON_THROW_ON_ERROR);
    foreach (['name_ar'=>'Untouched Arabic','city'=>'Untouched city','university'=>'Untouched university','phone'=>'Untouched private phone'] as $key=>$value) {
        if (($payload[$key]??null)!==$value) { throw new RuntimeException('Unrepresented draft field changed'); }
    }
    $log=file_get_contents($root.'/http.log');
    foreach (array_merge(array_values($cookies),[getenv('DB_PASSWORD'),'Untouched private phone','SYNTHETIC TEST ONLY','PRIVATE_FAULT_SQL_SENTINEL']) as $secret) { if (str_contains($log,$secret)) { throw new RuntimeException('Private fixture log leak'); } }
    echo "PASS editor fixture unrepresented fields and private log boundaries\n";
} catch (Throwable) { fwrite(STDERR,"FAIL editor fixture infrastructure or preservation\n"); $code=1; }
finally {
    foreach ($servers as $process) { if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
    if (session_status()===PHP_SESSION_ACTIVE) { session_write_close(); }
    if (isset($db)) {
        foreach ($users as $id) { if ($attestationBrowser) { $db->exec('DELETE FROM onboarding_attestations WHERE user_id='.$id); } $db->exec('DELETE FROM onboarding_drafts WHERE user_id='.$id); $db->exec('DELETE FROM users WHERE id='.$id); }
        if (isset($initial) && $initial!==editorFixtureSnapshot($db)) { $code=1; fwrite(STDERR,"FAIL editor preexisting data preservation\n"); }
    }
    foreach (['private-context.json','stop'] as $file) { if (is_file($evidence.'/'.$file)) { unlink($evidence.'/'.$file); } }
    if ($environment instanceof TestEnvironment) { $environment->tearDown(); if (!$environment->wasTornDown()) { $code=1; } }
}
if ($code===0) { echo "PASS editor fixture existing data and process/session cleanup\n"; }
if ($code===0 && $attestationBrowser) { echo "PASS attestation browser fixture protected data and private source/session/file/process/database cleanup\n"; }
ob_end_flush(); exit($code);
