<?php
declare(strict_types=1);
require_once __DIR__.'/../tests/phase2/support/journey-routing-fixtures.php';
require_once __DIR__.'/../tests/phase2/support/completion-fixtures.php';
completionFixtureLoadCore(true);
require_once __DIR__.'/../tests/phase2/bootstrap.php';
require_once __DIR__.'/../includes/owner_flow.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/profile_writes.php';
require_once __DIR__.'/../database/migrate.php';
require_once __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

final class JourneyCaptureCommitFault extends PDO
{
    public bool $after=false;
    public function commit(): bool { if ($this->after) { parent::commit(); } throw new PDOException('Synthetic capture acknowledgement failure'); }
}

ob_start();
$db=getDatabaseConnection(); $initial=completionSnapshot($db); $users=[]; $servers=[]; $secrets=[]; $exit=0; $stage='migration';
$root=sys_get_temp_dir().'/journey-routing-'.bin2hex(random_bytes(12)); mkdir($root,0700); mkdir($root.'/sessions',0700); mkdir($root.'/private-app',0700);
$sealBefore=$db->query('SELECT * FROM owner_journey_cutover')->fetchAll(PDO::FETCH_ASSOC);
$legacyBefore=$db->query('SELECT id,legacy_owner_user_id FROM portfolios ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$ledger=$db->query("SELECT applied_at FROM schema_migrations WHERE version='020'")->fetchColumn();
session_save_path($root.'/sessions'); session_name(OWNER_SESSION_NAME); ini_set('session.use_cookies','1'); ini_set('session.cache_limiter','');
putenv('SESSION_COOKIE_SECURE=0');
function journeyAssert(bool $value,string $message): void { if (!$value) { throw new RuntimeException($message); } }
function journeySession(int $id,?callable $mutate=null): array
{
    global $secrets;
    if (session_status()===PHP_SESSION_ACTIVE) { session_write_close(); }
    ini_set('session.use_cookies','1');
    session_id(''); startOwnerSession(); establishVerifiedInternalUserSession($id,1); $csrf=getCsrfToken();
    if ($mutate!==null) { $mutate(); }
    $sid=session_id(); $secrets[]=$sid; $secrets[]=$csrf; session_write_close();
    return [CookieJar::fromArray([OWNER_SESSION_NAME=>$sid],'127.0.0.1'),$csrf];
}
function journeyServer(string $docroot,array $flags): int
{
    global $servers,$root;
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); $address=stream_socket_get_name($socket,false); fclose($socket); $port=(int)substr($address,strrpos($address,':')+1);
    $env=getenv(); $env['SESSION_COOKIE_SECURE']='0'; $env['PUBLIC_BASE_URL']='https://journey-test.invalid'; $env['RATE_LIMIT_STATE_DIR']=$root.'/limits';
    foreach (['DRAFT','ATTESTATION','COMPLETION'] as $index=>$gate) { $env['ATHERCAR_ONBOARDING_'.$gate.'_HTTP_ENABLED']=$flags[$index]; }
    $router=$root.'/router-'.$port.'.php';
    file_put_contents($router,'<?php if (preg_match("@^/owner/projects/[1-9][0-9]*/evidence$@D",explode("?",$_SERVER["REQUEST_URI"],2)[0])) { require '.var_export($docroot.'/owner_project_evidence.php',true).'; } else { return false; }'); chmod($router,0600);
    $process=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/sessions','-d','display_errors=0','-S','127.0.0.1:'.$port,'-t',$docroot,$router],[0=>['pipe','r'],1=>['file',$root.'/private-http.log','a'],2=>['file',$root.'/private-http.log','a']],$pipes,null,$env);
    journeyAssert(is_resource($process),'Private HTTP server unavailable'); fclose($pipes[0]); $servers[]=$process;
    for ($i=0;$i<60;$i++) { $s=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,.1); if (is_resource($s)) { fclose($s); return $port; } usleep(50000); }
    throw new RuntimeException('Private HTTP server readiness failed');
}
function journeyBrowser(int $port,?CookieJar $jar=null): Client { return new Client(['base_uri'=>'http://127.0.0.1:'.$port,'cookies'=>$jar??new CookieJar(),'http_errors'=>false,'allow_redirects'=>false,'timeout'=>8]); }
function journeyRequest(Client $client,string $method,string $path,int $status,?string $location=null,array $options=[]): string
{
    global $db;
    $before=completionSnapshot($db); $r=$client->request($method,$path,$options);
    journeyAssert($r->getStatusCode()===$status,'Unexpected '.$method.' '.$path.' status '.$r->getStatusCode().' expected '.$status);
    journeyAssert(($location===null && !$r->hasHeader('Location')) || $r->getHeaderLine('Location')===$location,'Unsafe/unexpected destination');
    journeyAssert(str_contains($r->getHeaderLine('Cache-Control'),'no-store'),'Private no-store header missing');
    journeyAssert($before===completionSnapshot($db),'Routing/read/rejected mutation changed database');
    return (string)$r->getBody();
}
function journeyNew(): int { global $db,$users; $id=completionNewUser($db); $users[]=$id; return $id; }
function journeyCaptureWorker(?string $failure=null): bool
{
    $env=getenv(); unset($env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER']); if ($failure!==null) { $env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER']=$failure; }
    $p=proc_open([PHP_BINARY,__DIR__.'/../database/capture-owner-journey-cutover.php','--confirm-exclusive-maintenance','--database='.getenv('DB_NAME')],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    journeyAssert(is_resource($p),'Capture worker unavailable'); fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); return proc_close($p)===0;
}
try {
    journeyAssert($ledger!==false,'Requires 020 migrated disposable database');
    // No real cohort: preserve this disposable fixture metadata around DDL tests.
    $db->exec('DELETE FROM owner_journey_cutover; UPDATE portfolios SET legacy_owner_user_id=NULL');
    $db->exec("DELETE FROM schema_migrations WHERE version='020'; DROP TABLE owner_journey_cutover; ALTER TABLE portfolios DROP COLUMN legacy_owner_user_id");
    completionMigrate(false,'after-legacy-owner-column'); completionMigrate(false,'after-owner-cutover-table'); completionMigrate(true); completionMigrate(true);
    journeyAssert((int)$db->query('SELECT COUNT(*) FROM owner_journey_cutover')->fetchColumn()===0,'Startup captured legacy set');
    $db->exec('ALTER TABLE owner_journey_cutover ALTER CHECK chk_owner_journey_singleton NOT ENFORCED'); completionMigrate(false);
    $db->exec('ALTER TABLE owner_journey_cutover ALTER CHECK chk_owner_journey_singleton ENFORCED');
    $db->prepare("UPDATE schema_migrations SET applied_at=? WHERE version='020'")->execute([$ledger]);
    echo "PASS journey additive populated/partial/repeated migration and enforced singleton without automatic capture\n";

    $stage='capture'; $legacy=journeyNew(); $db->prepare('INSERT INTO portfolios(owner_user_id) VALUES (?)')->execute([$legacy]); $legacyPortfolio=(int)$db->lastInsertId();
    $db->prepare('INSERT INTO personal_info(portfolio_id,full_name) VALUES (?,?)')->execute([$legacyPortfolio,'Legacy professional']);
    foreach (['after-owner-cutover-capture','after-owner-cutover-seal'] as $failure) {
        $before=completionSnapshot($db); journeyAssert(!journeyCaptureWorker($failure),'Injected capture failure succeeded'); journeyAssert($before===completionSnapshot($db),'Capture rollback changed protected rows');
    }
    $db->exec('UPDATE portfolios SET legacy_owner_user_id=owner_user_id WHERE id='.$legacyPortfolio);
    journeyAssert(!journeyCaptureWorker(),'Partial state silently adopted'); $db->exec('UPDATE portfolios SET legacy_owner_user_id=NULL WHERE id='.$legacyPortfolio);
    foreach ([false,true] as $afterCommit) {
        $fault=completionConnection(JourneyCaptureCommitFault::class); $fault->after=$afterCommit; $before=completionSnapshot($db); $uncertain=false;
        try { captureLegacyOwnerCutover($fault); } catch (RuntimeException $error) { $uncertain=str_contains($error->getMessage(),'uncertain'); }
        journeyAssert($uncertain,'Unacknowledged capture commit not classified uncertain');
        if (!$afterCommit) { journeyAssert($before===completionSnapshot($db),'Pre-commit failure changed capture'); }
        else { journeyAssert((int)$db->query('SELECT COUNT(*) FROM owner_journey_cutover')->fetchColumn()===1,'Committed seal was compensated/deleted'); }
    }
    journeyAssert(journeyCaptureWorker(),'Explicit frozen recovery failed');
    $frozen=$db->query('SELECT * FROM owner_journey_cutover')->fetch(PDO::FETCH_ASSOC);
    $bare=journeyNew(); $db->prepare('INSERT INTO portfolios(owner_user_id) VALUES (?)')->execute([$bare]); $barePortfolio=(int)$db->lastInsertId();
    $incomplete=journeyNew();
    journeyAssert(journeyCaptureWorker(),'Frozen retry failed'); completionMigrate(true);
    journeyAssert($frozen===$db->query('SELECT * FROM owner_journey_cutover')->fetch(PDO::FETCH_ASSOC),'Retry/startup changed seal');
    journeyAssert($db->query('SELECT legacy_owner_user_id FROM portfolios WHERE id='.$barePortfolio)->fetchColumn()===null,'Retry adopted new bare Portfolio');
    echo "PASS journey exclusive capture rollback uncertain COMMIT partial-state rejection frozen retry and post-cutover exclusion\n";

    $stage='HTTP'; journeyFixtureCopy($root.'/private-app');
    $docs=[]; foreach (['age','terms','privacy'] as $kind) { $docs[$kind]=['language'=>'en','content'=>'SYNTHETIC ONLY: '.$kind]; }
    file_put_contents($root.'/documents.json',json_encode($docs,JSON_THROW_ON_ERROR)); chmod($root.'/documents.json',0600);
    $original=journeyServer(realpath(__DIR__.'/..'),['1','1','1']);
    $enabled=journeyServer($root.'/private-app',['1','1','1']);
    [$jar,$csrf]=journeySession($legacy,static function (): void { recordOwnerReadinessObservation(currentInternalUserSession()['internal_user_id'],1,false,true); });
    $client=journeyBrowser($original,$jar);
    journeyRequest($client,'GET','/owner.php',200); journeyRequest($client,'GET','/owner_onboarding.php',303,'/owner.php'); journeyRequest($client,'HEAD','/owner_onboarding.php',303,'/owner.php');
    journeyAssert((int)$db->query('SELECT COUNT(*) FROM onboarding_completions WHERE user_id='.$legacy)->fetchColumn()===0,'Legacy owner received fabricated receipt');
    echo "PASS journey grandfathered Dashboard and legacy GET/HEAD without readiness receipt or available policies\n";

    [$jar,$csrf]=journeySession($incomplete); $null=journeyBrowser($original,$jar); $on=journeyBrowser($enabled,$jar);
    journeyRequest($null,'GET','/owner.php',303,'/owner_onboarding.php');
    $body=journeyRequest($null,'GET','/owner_onboarding.php',503);
    journeyAssert(str_contains($body,'Use another account') && str_contains($body,'Logout') && !str_contains($body,'Create Portfolio'),'Holding lost recovery or exposed legacy creation');
    journeyRequest($null,'HEAD','/owner_onboarding.php',503);
    journeyRequest($on,'GET','/owner_onboarding.php',303,'/owner_onboarding_editor.php'); journeyRequest($on,'GET','/owner_onboarding_editor.php',200);
    foreach ([['0','1','1'],['1','0','1'],['1','1','0'],['true','1','1'],['1','01','1'],['1','1',' 1']] as $flags) {
        $port=journeyServer($root.'/private-app',$flags); $c=journeyBrowser($port,$jar); journeyRequest($c,'GET','/owner.php',303,'/owner_onboarding.php'); journeyRequest($c,'GET','/owner_onboarding.php',503);
    }
    echo "PASS journey incomplete fixed editor destination strict capability combinations production null holding and no redirect loop\n";

    [$jar,$csrf]=journeySession($bare,static function (): void { $_SESSION['completed']=true; $_SESSION['grandfathered']=true; recordOwnerReadinessObservation(currentInternalUserSession()['internal_user_id'],1,true,true); }); $b=journeyBrowser($enabled,$jar);
    foreach (['owner.php','owner_profile.php','owner_projects.php','owner_publication.php','owner_experiences.php','owner_messages.php','owner_preview.php','owner_media.php','owner_evidence_hub.php'] as $route) {
        journeyRequest($b,'GET','/'.$route,303,'/owner_onboarding_editor.php');
        if (!in_array($route,['owner.php','owner_preview.php','owner_media.php','owner_messages.php'],true)) { journeyRequest($b,'POST','/'.$route,303,'/owner_onboarding_editor.php',['form_params'=>['csrf_token'=>$csrf,'action'=>'publish','owner_user_id'=>$legacy,'portfolio_id'=>$legacyPortfolio,'full_name'=>'Forged']]); }
    }
    journeyRequest($b,'GET','/owner_project_evidence.php',404);
    journeyRequest($b,'GET','/owner/projects/1/evidence',303,'/owner_onboarding_editor.php');
    journeyRequest($b,'POST','/owner/projects/1/evidence',303,'/owner_onboarding_editor.php',['form_params'=>['csrf_token'=>$csrf,'problem'=>'Forged']]);
    journeyRequest($b,'GET','/owner_onboarding.php',303,'/owner_onboarding_editor.php');
    foreach ([$client,$on,$b,journeyBrowser($original)] as $c) { journeyRequest($c,'POST','/owner_onboarding_editor.php',405,null,['form_params'=>['csrf_token'=>$csrf,'email_verified'=>'true','owner_user_id'=>$legacy]]); }
    $context=completionSession($bare); $before=completionSnapshot($db);
    try { requireOwnedPortfolioContext($db); throw new RuntimeException('Bare owner context accepted'); } catch (AuthorizationDeniedException) {}
    try { createOwnedPortfolio($db,$context); throw new RuntimeException('Retired helper accepted'); } catch (OwnerReadinessDeniedException) {}
    journeyAssert($before===completionSnapshot($db),'Direct bypass changed database');
    echo "PASS journey post-cutover bare Portfolio denied across all Owner boundaries and legacy POST/helper bypasses\n";

    $stage='completion'; $completed=journeyNew(); $context=completionSeed($db,$completed,completionFields()); $review=prepareOnboardingCompletionReview($db,$context,3); $intent=completionIntent($review); $receipt=completeOnboarding($db,$context,$intent);
    $owned=requireOwnedPortfolioContext($db); saveOwnedProfilePatch($db,$owned,$receipt['profile_id'],1,['full_name'=>'Later authoritative Profile']);
    $GLOBALS['completion_fixture_bundle']=null;
    [$jar,$csrf]=journeySession($completed); $c=journeyBrowser($original,$jar);
    $body=journeyRequest($c,'GET','/owner.php',200); journeyAssert(str_contains($body,'Later'),'Dashboard did not load current Profile');
    journeyRequest($c,'GET','/owner_onboarding.php',303,'/owner.php');
    $json=json_decode(journeyRequest($c,'GET','/owner_onboarding_completion.php',200),true,16,JSON_THROW_ON_ERROR); journeyAssert($json['receipt']===$receipt,'Original receipt recovery changed');
    journeyRequest($c,'POST','/owner_profile.php',403,null,['form_params'=>['csrf_token'=>'invalid','action'=>'save_profile','full_name'=>'Bad']]);
    echo "PASS journey completed owner later Profile authority and policy-independent later-session receipt recovery with CSRF preserved\n";

    $stage='binding'; $db->prepare('UPDATE onboarding_completions SET portfolio_id=? WHERE user_id=?')->execute([$barePortfolio,$completed]);
    journeyRequest($c,'GET','/owner.php',303,'/owner_onboarding.php');
    $db->prepare('UPDATE onboarding_completions SET portfolio_id=? WHERE user_id=?')->execute([$receipt['portfolio_id'],$completed]);
    $legacyProfile=(int)$db->query('SELECT id FROM personal_info WHERE portfolio_id='.$legacyPortfolio)->fetchColumn();
    $db->prepare('UPDATE onboarding_completions SET profile_id=? WHERE user_id=?')->execute([$legacyProfile,$completed]);
    journeyRequest($c,'GET','/owner.php',303,'/owner_onboarding.php');
    $db->prepare('UPDATE onboarding_completions SET profile_id=? WHERE user_id=?')->execute([$receipt['profile_id'],$completed]);
    $db->prepare('UPDATE portfolios SET owner_user_id=? WHERE id=?')->execute([$incomplete,$legacyPortfolio]);
    [$jar,$csrf]=journeySession($incomplete); journeyRequest(journeyBrowser($enabled,$jar),'GET','/owner.php',303,'/owner_onboarding_editor.php');
    $db->prepare('UPDATE portfolios SET owner_user_id=? WHERE id=?')->execute([$legacy,$legacyPortfolio]);
    echo "PASS journey completion ownership/Profile binding and legacy original-User binding reject cross-account reassignment\n";

    $stage='authorization';
    foreach (['expired','revoked','disabled','context'] as $mode) {
        [$jar,$csrf]=journeySession($completed,$mode==='expired' ? static function (): void { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at']-=43201; } : null);
        if ($mode==='revoked') { $db->exec('UPDATE users SET authz_version=2 WHERE id='.$completed); }
        if ($mode==='disabled') { $db->exec("UPDATE users SET account_status='disabled' WHERE id=$completed"); }
        if ($mode==='context') { $jar=journeySession($bare)[0]; }
        journeyRequest(journeyBrowser($enabled,$jar),'GET','/owner.php',$mode==='context' ? 303 : 403,$mode==='context' ? '/owner_onboarding_editor.php' : null);
        $db->exec("UPDATE users SET authz_version=1,account_status='active' WHERE id=$completed");
    }
    journeyRequest(journeyBrowser($enabled),'GET','/owner.php',303,'/owner_login.php');
    [$jar,$csrf]=journeySession($incomplete); $c=journeyBrowser($original,$jar);
    journeyRequest($c,'POST','/owner_switch_account.php',403,null,['form_params'=>['csrf_token'=>'wrong']]);
    journeyRequest($c,'POST','/owner_logout.php',303,'owner_login.php',['form_params'=>['csrf_token'=>$csrf]]);
    journeyRequest($c,'GET','/owner.php',303,'/owner_login.php');
    echo "PASS journey anonymous expired revoked disabled switched-account and logout boundaries\n";

    $stage='restore'; $sealed=completionSnapshot($db); journeyAssert(journeyCaptureWorker(),'Restored frozen verification failed'); completionMigrate(true); completionMigrate(true); journeyAssert($sealed===completionSnapshot($db),'Restored startup or capture retry expanded/changed evidence');
    echo "PASS journey sealed restored/repeated migration preserves nonempty completion Profile draft attestation and excluded bare Portfolio\n";
} catch (Throwable $error) { $exit=1; fwrite(STDERR,'FAIL journey '.$stage.': '.$error->getMessage()."\n"); }
finally {
    if (session_status()===PHP_SESSION_ACTIVE) { session_write_close(); }
    foreach ($servers as $server) { if (is_resource($server)) { proc_terminate($server); proc_close($server); } }
    try {
        $log=is_file($root.'/private-http.log') ? file_get_contents($root.'/private-http.log') : '';
        foreach ($secrets as $secret) { journeyAssert(!str_contains($log,$secret),'Private session/CSRF leaked to HTTP log'); }
        completionCleanup($db,$users);
        $db->exec('DELETE FROM owner_journey_cutover; UPDATE portfolios SET legacy_owner_user_id=NULL');
        foreach ($legacyBefore as $row) { $db->prepare('UPDATE portfolios SET legacy_owner_user_id=? WHERE id=?')->execute([$row['legacy_owner_user_id'],$row['id']]); }
        foreach ($sealBefore as $row) { $db->prepare('INSERT INTO owner_journey_cutover VALUES (?,?,?,?,?)')->execute(array_values($row)); }
        journeyAssert($initial===completionSnapshot($db),'Protected database snapshot changed after cleanup');
        journeyFixtureRemove($root);
        journeyAssert(!file_exists($root),'Private fixture source/session/log cleanup failed');
        echo "PASS journey protected data and private source process session log file database fixture cleanup\n";
    } catch (Throwable $error) { $exit=1; fwrite(STDERR,'FAIL journey cleanup: '.$error->getMessage()."\n"); }
}
ob_end_flush(); exit($exit);
