<?php
declare(strict_types=1);
require_once __DIR__ . '/../../tests/phase2/support/draft-fixtures.php';
require_once __DIR__ . '/../../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../../includes/owner_session.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__.'/../../tests/phase2/support/completion-browser-fixtures.php';
require_once __DIR__.'/../../includes/onboarding_attestations.php';
require_once __DIR__.'/../../tests/phase2/support/journey-routing-fixtures.php';
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
$environment=null; $users=[]; $servers=[]; $code=0; $createdSeal=false;
try {
    $db=getDatabaseConnection(); $initial=editorFixtureSnapshot($db);
    // Explicit empty synthetic cohort only; never capture or adopt database owners.
    if ((int)$db->query('SELECT COUNT(*) FROM owner_journey_cutover')->fetchColumn()===0) {
        $db->exec("INSERT INTO owner_journey_cutover VALUES (1,'owner-journey-cutover-v1',0,UNHEX(SHA2('[]',256)),UTC_TIMESTAMP(6))"); $createdSeal=true;
    }
    $environment=TestEnvironment::create(); $root=$environment->namespaceRoot.'/editor';
    mkdir($root,0700); mkdir($root.'/sessions',0700); mkdir($root.'/http',0700); mkdir($root.'/http/public',0700);
    symlink(realpath(__DIR__.'/../..'),$root.'/http/app');
    RouteContract::writeEntryScripts($root.'/http/public');
    symlink(realpath(__DIR__.'/../../public/assets'),$root.'/http/public/assets');
    session_save_path($root.'/sessions'); session_name(OWNER_SESSION_NAME); ini_set('session.cache_limiter','');
    foreach (range('a','p') as $name) {
        $db->prepare('INSERT INTO users (oidc_issuer,oidc_subject,account_status,authz_version) VALUES (?,?,?,1)')->execute(['https://editor.invalid/',bin2hex(random_bytes(16)),'active']);
        $users[$name]=(int)$db->lastInsertId();
    }
    $cookies=[];
    foreach ([...range('a','p'),'later','expired'] as $name) {
        session_id(''); startOwnerSession(); establishVerifiedInternalUserSession($users[in_array($name,['expired','later'],true)?'a':$name],1); getCsrfToken();
        recordOwnerReadinessObservation(currentInternalUserSession()['internal_user_id'],1,true,true);
        if (!in_array($name,['expired','later'],true)) {
            $fields=['display_name'=>'  Synthetic <b>name</b>  ','country_code'=>'JO','preferred_language'=>'en','professional_status'=>'other',
                'specialization'=>'تخصص é 😀','headline'=>'Exact saved headline','slug_candidate'=>'completion-browser-'.bin2hex(random_bytes(8)),
                'name_ar'=>'اسم محفوظ <img src=x>','name_en'=>'  Saved English  ','city'=>'Private city','university'=>'Private university',
                'graduation_year'=>'0000','biography'=>"  Saved\nbiography <script>window.reviewXss=1</script>  ",'phone'=>'Untouched private phone'];
            if($name==='i'){$fields['country_code']='US';$fields['headline']=str_repeat('h',200);$fields['slug_candidate']='admin';}
            if($name==='o'){unset($fields['name_en'],$fields['graduation_year']);}
            draftTestSeedHistorical($db,$users[$name],3,'profile_identity',['slug_candidate'=>$fields['slug_candidate']],$fields);
        }
        if ($name==='expired') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at']-=50000; }
        $cookies[$name]=session_id(); session_write_close();
    }
    $env=array_intersect_key(getenv(),array_flip(['PATH','APP_ENV','ATHERCAR_TEST_MODE','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','ATHERCAR_TEST_DB_NAME']));
    $env+=['SESSION_COOKIE_SECURE'=>'0','ATHERCAR_REGISTRATION_ENABLED'=>'0'];
    {
        mkdir($root.'/arrivals',0700);
        file_put_contents($root.'/control.json',json_encode(['browser'=>true,'language'=>'en','version'=>'v1','users'=>$users,'sessions'=>$cookies,'session_path'=>$root.'/sessions'],JSON_THROW_ON_ERROR)); chmod($root.'/control.json',0600);
        completionBrowserFixtureCopies($root);
        mkdir($root.'/journey-app',0700); journeyFixtureCopy($root.'/journey-app');
        $documents=[]; foreach (['age','terms','privacy'] as $kind) { $documents[$kind]=['language'=>'en','content'=>'SYNTHETIC ONLY pause capability: '.$kind]; }
        file_put_contents($root.'/documents.json',json_encode($documents,JSON_THROW_ON_ERROR)); chmod($root.'/documents.json',0600);
        // Reuse the existing guarded journey source for current entry destinations.
        foreach (['signin.php','signup.php','owner_login.php'] as $entry) { file_put_contents($root.'/http/public/'.$entry,'<?php require '.var_export($root.'/journey-app/'.$entry,true).';'); chmod($root.'/http/public/'.$entry,0600); }
        file_put_contents($root.'/http/public/owner_onboarding.php','<?php require '.var_export($root.'/journey-app/owner_onboarding.php',true).';'); chmod($root.'/http/public/owner_onboarding.php',0600);
        copy($root.'/synthetic-public/owner_onboarding_completion.php',$root.'/http/public/owner_onboarding_completion.php');
        copy($root.'/synthetic-public/owner_onboarding_attestation.php',$root.'/http/public/owner_onboarding_attestation.php');
        mkdir($root.'/http-null',0700); mkdir($root.'/http-null/public',0700);
        symlink(realpath(__DIR__.'/../..'),$root.'/http-null/app');
        RouteContract::writeEntryScripts($root.'/http-null/public');
        symlink(realpath(__DIR__.'/../../public/assets'),$root.'/http-null/public/assets');
    }
    foreach ([8080=>'1',8081=>'1',8082=>'1'] as $port=>$gate) {
        $childEnv=$env; $childEnv['ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED']=$gate;
        $childEnv['ATHERCAR_ONBOARDING_ATTESTATION_HTTP_ENABLED']=$port!==8082 ? '1' : '0';
        $childEnv['ATHERCAR_ONBOARDING_COMPLETION_HTTP_ENABLED']=$port!==8082?'1':'0';
        $documentRoot=$port!==8080 ? $root.'/http-null/public' : $root.'/http/public';
        $process=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/sessions','-d','display_errors=0','-S','0.0.0.0:'.$port,'-t',$documentRoot],[0=>['pipe','r'],1=>['file',$root.'/http.log','a'],2=>['file',$root.'/http.log','a']],$pipes,null,$childEnv);
        if (!is_resource($process)) { throw new RuntimeException('Private editor server unavailable'); } fclose($pipes[0]); $servers[]=$process;
    }
    $ready=false;
    for ($i=0;$i<100;$i++) { $socket=@stream_socket_client('tcp://127.0.0.1:8080',$errno,$message,.1); if (is_resource($socket)) { fclose($socket); $ready=true; break; } usleep(50000); }
    if (!$ready) { throw new RuntimeException('Editor server startup failed'); }
    file_put_contents($evidence.'/private-context.json',json_encode(['sessions'=>$cookies,'control'=>$root.'/control.json'],JSON_THROW_ON_ERROR));
    { chmod($evidence.'/private-context.json',0600); }
    // Browser process gets only synthetic cookies through a private runner file.
    // Never serve a fixture login route or expose this file via the document root.
    $deadline=time()+900;
    while (!is_file($evidence.'/stop') && time()<$deadline) { usleep(100000); }
    if (!is_file($evidence.'/stop')) { throw new RuntimeException('Browser runner did not signal cleanup'); }
    foreach($users as $id){
        $payload=json_decode($db->query('SELECT payload FROM onboarding_drafts WHERE user_id='.$id)->fetchColumn(),true,16,JSON_THROW_ON_ERROR);
        foreach(['city'=>'Private city','university'=>'Private university','phone'=>'Untouched private phone'] as $key=>$value){
            if(($payload[$key]??null)!==$value){throw new RuntimeException('Deferred draft field changed');}
        }
    }
    $row=$db->query('SELECT full_name FROM personal_info WHERE portfolio_id IN (SELECT id FROM portfolios WHERE owner_user_id='.$users['a'].')')->fetchColumn();
    if($row!=='Later authoritative Profile'){throw new RuntimeException('Later Profile edit lost or browser did not verify preservation');}
    $log=file_get_contents($root.'/http.log');
    foreach (array_merge(array_values($cookies),[getenv('DB_PASSWORD'),'Untouched private phone','SYNTHETIC TEST ONLY','PRIVATE_FAULT_SQL_SENTINEL']) as $secret) { if (str_contains($log,$secret)) { throw new RuntimeException('Private fixture log leak'); } }
    echo "PASS editor fixture unrepresented fields and private log boundaries\n";
} catch (Throwable) { fwrite(STDERR,"FAIL editor fixture infrastructure or preservation\n"); $code=1; }
finally {
    foreach ($servers as $process) { if (is_resource($process)) { proc_terminate($process); $stopped=false; for($i=0;$i<100;$i++){if(!proc_get_status($process)['running']){$stopped=true;break;}usleep(20000);} if(!$stopped){$code=1;proc_terminate($process,9);} proc_close($process); } }
    if (session_status()===PHP_SESSION_ACTIVE) { session_write_close(); }
    if (isset($db)) {
        foreach($users as $id){
            $db->exec('DELETE FROM onboarding_completions WHERE user_id='.$id);
            $db->exec('DELETE FROM personal_info WHERE portfolio_id IN (SELECT id FROM portfolios WHERE owner_user_id='.$id.')');
            $db->exec('DELETE FROM portfolios WHERE owner_user_id='.$id.'; DELETE FROM onboarding_attestations WHERE user_id='.$id.'; DELETE FROM onboarding_drafts WHERE user_id='.$id.'; DELETE FROM users WHERE id='.$id);
        }
        if ($createdSeal) { $db->exec('DELETE FROM owner_journey_cutover'); }
        if (isset($initial) && $initial!==editorFixtureSnapshot($db)) { $code=1; fwrite(STDERR,"FAIL editor preexisting data preservation\n"); }
    }
    foreach (['private-context.json','stop'] as $file) { if (is_file($evidence.'/'.$file)) { unlink($evidence.'/'.$file); } }
    if ($environment instanceof TestEnvironment) { $environment->tearDown(); if (!$environment->wasTornDown()) { $code=1; } }
}
if ($code===0) { echo "PASS editor fixture existing data and process/session cleanup\n"; }
if ($code===0) { echo "PASS completion browser fixture protected data and private source/session/file/process/database cleanup\n"; }
ob_end_flush(); exit($code);
