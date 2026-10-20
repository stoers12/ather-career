<?php
declare(strict_types=1);

require_once __DIR__.'/../tests/phase2/support/completion-http-fixtures.php';
require_once __DIR__.'/../tests/phase2/bootstrap.php';
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../includes/owner_session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../tests/phase2/support/completion-fixtures.php';
completionFixtureLoadCore(false);
require_once __DIR__.'/../includes/profile_writes.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

ob_start();
function compHttpAssert(bool $condition,string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function compHttpSnapshot(PDO $db): array
{
    $snapshot=[];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC)); sort($rows); $snapshot[$table]=hash('sha256',serialize($rows));
    }
    ksort($snapshot); return $snapshot;
}
function compHttpControl(array $control=[]): void
{
    global $root;
    file_put_contents($root.'/control.json',json_encode($control,JSON_THROW_ON_ERROR)); chmod($root.'/control.json',0600);
}
function compHttpStop(): void
{
    global $servers;
    foreach ($servers as $process) {
        if (!is_resource($process)) { continue; }
        proc_terminate($process);
        $stopped=false;
        for ($i=0;$i<100;$i++) { if (!proc_get_status($process)['running']) { $stopped=true; break; } usleep(20000); }
        if (!$stopped) { proc_terminate($process,9); }
        proc_close($process);
        compHttpAssert($stopped,'HTTP fixture required forced termination.');
    }
    $servers=[];
}
function compHttpStart(?string $gate,bool $synthetic=false,array $overrides=[],?string $sessionPath=null): void
{
    global $root,$servers,$ports;
    compHttpStop();
    for ($server=0;$server<2;$server++) {
        if(!isset($ports[$server])){
            $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$message);
            compHttpAssert(is_resource($socket),'Loopback port unavailable.');
            $address=stream_socket_get_name($socket,false); fclose($socket); $ports[$server]=(int)substr($address,strrpos($address,':')+1);
        }
        // Persisted browser clients must keep the same server address on restart.
        $port=$ports[$server];
        $env=array_intersect_key(getenv(),array_flip(['PATH','APP_ENV','ATHERCAR_TEST_MODE','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','ATHERCAR_TEST_DB_NAME']));
        $env+=['SESSION_COOKIE_SECURE'=>'0','ATHERCAR_REGISTRATION_ENABLED'=>'0','ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED'=>'0'];
        if ($gate!==null) { $env['ATHERCAR_ONBOARDING_COMPLETION_HTTP_ENABLED']=$gate; }
        $env=array_replace($env,$overrides);
        $p=proc_open([PHP_BINARY,'-d','session.save_path='.($sessionPath??$root.'/sessions'),'-d','display_errors=0','-d','log_errors=1',
            '-S','127.0.0.1:'.$port,'-t',$root.($synthetic?'/synthetic-public':'/http/public')],
            [0=>['pipe','r'],1=>['file',$root.'/private-http.log','a'],2=>['file',$root.'/private-http.log','a']],$pipes,null,$env);
        compHttpAssert(is_resource($p),'HTTP process unavailable.'); fclose($pipes[0]); $servers[]=$p;
        $ready=false;
        for($i=0;$i<80;$i++) {
            $s=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$message,0.1);
            if(is_resource($s)){fclose($s);$ready=true;break;} usleep(25000);
        }
        compHttpAssert($ready,'HTTP process not ready.');
    }
}
function compHttpBrowser(?int $id=null,?callable $mutate=null,int $server=0): Client
{
    global $ports,$secrets;
    $jar=new CookieJar();
    if ($id!==null) {
        session_id(''); startOwnerSession(); establishVerifiedInternalUserSession($id,1); getCsrfToken();
        if($mutate!==null){$mutate();}
        $sid=session_id(); $secrets[]=$sid; session_write_close();
        $jar=CookieJar::fromArray([OWNER_SESSION_NAME=>$sid],'127.0.0.1');
    }
    return new Client(['base_uri'=>'http://127.0.0.1:'.$ports[$server],'cookies'=>$jar,'http_errors'=>false,'allow_redirects'=>false,'timeout'=>10]);
}
function compHttpSession(Client $browser,callable $operation): mixed
{
    $cookie=$browser->getConfig('cookies')->getCookieByName(OWNER_SESSION_NAME);
    compHttpAssert($cookie!==null,'Fixture session cookie missing.');
    session_id($cookie->getValue()); startOwnerSession();
    try { return $operation(); } finally { session_write_close(); }
}
function compHttpRequest(Client $browser,string $method,int $expected,array $options=[],string $query='',bool $preserved=true): array
{
    global $database,$secrets;
    $before=compHttpSnapshot($database);
    $response=$browser->request($method,'/owner_onboarding_completion.php'.$query,$options);
    compHttpAssert($response->getStatusCode()===$expected,'HTTP status: expected '.$expected.', got '.$response->getStatusCode());
    compHttpAssert($response->getHeaderLine('Content-Type')==='application/json; charset=utf-8'
        && $response->getHeaderLine('Cache-Control')==='private, no-store' && !$response->hasHeader('Location')
        && !$response->hasHeader('Access-Control-Allow-Origin'),'Private response headers changed.');
    if($expected===405){compHttpAssert($response->getHeaderLine('Allow')==='GET, POST','Incorrect Allow.');}
    if($preserved){compHttpAssert($before===compHttpSnapshot($database),'Read/rejected/repeated request changed database.');}
    if($method==='HEAD'){return [];}
    $json=json_decode((string)$response->getBody(),true,16,JSON_THROW_ON_ERROR);
    compHttpAssert(($json['success']??null)===($expected===200),'Invalid response envelope.');
    if($expected!==200){
        compHttpAssert(array_keys($json)===['success','code','error'] && !preg_match('/SQLSTATE|PDO|Exception|SELECT |INSERT |password|PRIVATE_|SYNTHETIC|session_id/i',$json['error']),'Unsafe error response.');
    } else {
        if(isset($json['csrf_token'])){$secrets[]=$json['csrf_token'];}
        if(isset($json['review']['reviewToken'])){$secrets[]=$json['review']['reviewToken'];}
    }
    return $json;
}
function compHttpPost(Client $browser,array $input,int $status=200,bool $preserved=true): array
{
    $csrf=compHttpSession($browser,static fn()=>getCsrfToken());
    return compHttpRequest($browser,'POST',$status,['headers'=>['X-CSRF-Token'=>$csrf],'json'=>$input],'',$preserved);
}
function compHttpReview(Client $browser,int $version=3,int $status=200): array
{
    return compHttpPost($browser,['action'=>'review','expectedVersion'=>$version],$status);
}
function compHttpConcurrent(array $browsers,array $presentations,int $userId): array
{
    global $database,$root,$ports;
    foreach(glob($root.'/arrivals/*')?:[] as $file){unlink($file);}
    $database->beginTransaction(); $database->query('SELECT id FROM users WHERE id='.(int)$userId.' FOR UPDATE')->fetchColumn();
    $workers=[];
    try {
        $code='require $argv[1]; $i=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR); $c=new GuzzleHttp\\Client(["http_errors"=>false,"timeout"=>15]); $r=$c->post($i["url"],["headers"=>$i["headers"],"json"=>$i["input"]]); echo json_encode(["status"=>$r->getStatusCode(),"cache"=>$r->getHeaderLine("Cache-Control"),"body"=>json_decode((string)$r->getBody(),true,16,JSON_THROW_ON_ERROR)],JSON_THROW_ON_ERROR);';
        foreach($browsers as $index=>$browser){
            $p=proc_open([PHP_BINARY,'-r',$code,__DIR__.'/../vendor/autoload.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            compHttpAssert(is_resource($p),'HTTP race worker unavailable.');
            $workers[]=[$p,$pipes];
            $sid=$browser->getConfig('cookies')->getCookieByName(OWNER_SESSION_NAME)->getValue();
            fwrite($pipes[0],json_encode(['url'=>'http://127.0.0.1:'.$ports[$index].'/owner_onboarding_completion.php','headers'=>['Cookie'=>OWNER_SESSION_NAME.'='.$sid,'X-CSRF-Token'=>$presentations[$index]['csrf_token']],'input'=>array_diff_key($presentations[$index],['csrf_token'=>true])],JSON_THROW_ON_ERROR)); fclose($pipes[0]);
        }
        $arrived=false;
        for($i=0;$i<200;$i++){if(count(glob($root.'/arrivals/*')?:[])===2){$arrived=true;break;} usleep(25000);}
        compHttpAssert($arrived,'Both HTTP requests did not reach the real User-lock barrier.'); $database->commit();
        $results=[];
        foreach($workers as [$p,$pipes]){
            $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            compHttpAssert(proc_close($p)===0 && $err==='','HTTP race worker failed.');
            $results[]=json_decode($out,true,16,JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally {
        if($database->inTransaction()){$database->rollBack();}
        foreach($workers as [$p,$pipes]){foreach($pipes as $pipe){if(is_resource($pipe)){fclose($pipe);}} if(is_resource($p)){proc_terminate($p);proc_close($p);}}
    }
}

$servers=[];$users=[];$secrets=[];$environment=null;$initial=null;$exitCode=0;$phase='setup';
try {
    $environment=TestEnvironment::create(); $root=$environment->namespaceRoot.'/completion-http';
    mkdir($root,0700); mkdir($root.'/sessions',0700); mkdir($root.'/arrivals',0700); mkdir($root.'/http',0700); mkdir($root.'/http/public',0700);
    symlink(realpath(__DIR__.'/..'),$root.'/http/app'); copy(__DIR__.'/../public/owner_onboarding_completion.php',$root.'/http/public/owner_onboarding_completion.php');
    completionHttpFixtureCopies($root); compHttpControl();
    session_save_path($root.'/sessions'); session_name(OWNER_SESSION_NAME); ini_set('session.cache_limiter','');
    $database=getDatabaseConnection();
    compHttpAssert($database->query("SELECT name FROM schema_migrations WHERE version='019'")->fetchColumn()==='onboarding_completions','Full 019 migration required.');
    $initial=compHttpSnapshot($database);
    $new=static function(?array $fields=null) use (&$users,$database): int {
        $id=completionNewUser($database);$users[]=$id;
        completionSeed($database,$id,$fields??completionFields());session_write_close();ini_set('session.use_cookies','1');return $id;
    };
    $ready=static function():void{$s=currentInternalUserSession();recordOwnerReadinessObservation($s['internal_user_id'],$s['authz_version'],true,true);};
    $userA=$new();$userB=$new();$disabled=$new();$database->exec("UPDATE users SET account_status='disabled' WHERE id=$disabled");
    $phase='gate/method';
    foreach([null,'','0','true','TRUE','01',' 1','1 ','false'] as $gate){
        compHttpStart($gate,false,['DB_PORT'=>'invalid']);
        foreach(['GET','POST','DELETE'] as $method){compHttpRequest(compHttpBrowser(),$method,404);}
    }
    compHttpStart('1');
    foreach(['PUT','PATCH','DELETE','OPTIONS','HEAD'] as $method){compHttpRequest(compHttpBrowser(),$method,405);}
    foreach(['get','post'] as $method){
        $code='$_SERVER["REQUEST_METHOD"]=$argv[1]; putenv("ATHERCAR_ONBOARDING_COMPLETION_HTTP_ENABLED=1"); putenv("DB_PORT=invalid"); register_shutdown_function(static function():void{fwrite(STDERR,"STATUS=".http_response_code());}); require $argv[2];';
        $p=proc_open([PHP_BINARY,'-r',$code,$method,__DIR__.'/../owner_onboarding_completion.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes); fclose($pipes[0]);
        $body=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        compHttpAssert(proc_close($p)===0 && str_contains($err,'STATUS=405') && json_decode($body,true)['code']==='method_not_allowed','Lowercase method bypass.');
    }
    echo "PASS completion HTTP exact default-off gate and GET/POST method admission before session/database work\n";

    $phase='authorization';
    foreach(['GET','POST'] as $method){
        compHttpRequest(compHttpBrowser(),$method,401);
        foreach(['idle','absolute','revoked','malformed','future','missing-user'] as $case){
            $browser=compHttpBrowser($userA,static function()use($case):void{
                $s=&$_SESSION[INTERNAL_USER_SESSION_KEY];
                if($case==='idle'){$s['authenticated_at']-=2000;$s['last_activity_at']-=2000;}
                if($case==='absolute'){$s['authenticated_at']-=50000;}
                if($case==='revoked'){$s['authz_version']=2;}
                if($case==='malformed'){$s['internal_user_id']='invalid';}
                if($case==='future'){$s['last_activity_at']+=120;}
                if($case==='missing-user'){$s['internal_user_id']=4294967295;}
            });compHttpRequest($browser,$method,403);
        }compHttpRequest(compHttpBrowser($disabled),$method,403);
    }
    echo "PASS completion HTTP anonymous expired revoked disabled malformed and cross-account authorization\n";

    $phase='production null boundary';$a=compHttpBrowser($userA,$ready);$b=compHttpBrowser($userB);
    $get=compHttpRequest($a,'GET',200);
    compHttpAssert(array_keys($get)===['success','receipt','csrf_token'] && $get['receipt']===null,'Recovery projection changed.');
    compHttpAssert(compHttpReview($a,3,503)['code']==='prerequisites_unavailable','Production policy was not unavailable.');
    $fake=['action'=>'complete','expectedVersion'=>3,'operationId'=>'3:'.str_repeat('a',32),'reviewToken'=>str_repeat('b',64),'confirm_private_creation'=>true,'acknowledge_private_draft'=>true];
    compHttpAssert(compHttpPost($a,$fake,503)['code']==='prerequisites_unavailable','New production completion allowed.');
    compHttpRequest($b,'GET',200);compHttpReview($b,3,403);
    echo "PASS completion HTTP original null production policies block new work but not authorized receipt reads without readiness\n";

    $phase='transport';$csrf=$get['csrf_token'];$review=['action'=>'review','expectedVersion'=>3];$valid=json_encode($review,JSON_THROW_ON_ERROR);
    foreach([null,'wrong',123] as $token){$headers=['Content-Type'=>'application/json'];if($token!==null){$headers['X-CSRF-Token']=$token;}compHttpRequest($a,'POST',403,['headers'=>$headers,'body'=>$valid]);}
    foreach(['', 'null','[]','true','{','{"action":"review","expectedVersion":3.0}','{"action":"review","expectedVersion":3e0}','{"action":"review","expectedVersion":"3"}','{"action":"review","expectedVersion":true}','{"action":"review","expectedVersion":0}','{"action":"review","expectedVersion":2147483648}',
        '{"action":"review","expectedVersion":3,"expectedVersion":3}','{"action":"review","expectedVersion":3,"expected\\u0056ersion":3}',
        '{"action":"review","action":"complete","expectedVersion":3}',"{\"action\":\"review\",\"expectedVersion\":3,\"x\":\"\xff\"}"] as $raw){
        compHttpRequest($a,'POST',400,['headers'=>['X-CSRF-Token'=>$csrf,'Content-Type'=>'application/json'],'body'=>$raw]);
    }
    foreach(['user_id','portfolio_id','fields','documents','revision','recorded_at','authority','_method'] as $key){compHttpPost($a,$review+[$key=>'PRIVATE_OVERRIDE'],400);}
    foreach(['action','expectedVersion','operationId','reviewToken','confirm_private_creation','acknowledge_private_draft'] as $key){$input=$fake;unset($input[$key]);compHttpPost($a,$input,400);}
    foreach(['expectedVersion'=>'3','operationId'=>'03:'.str_repeat('a',32),'reviewToken'=>str_repeat('A',64),'confirm_private_creation'=>1,'acknowledge_private_draft'=>'true'] as $key=>$value){compHttpPost($a,array_replace($fake,[$key=>$value]),400);}
    foreach(['text/plain','application/json; charset=utf-16','application/json; profile=x'] as $type){compHttpRequest($a,'POST',415,['headers'=>['X-CSRF-Token'=>$csrf,'Content-Type'=>$type],'body'=>$valid]);}
    compHttpRequest($a,'POST',415,['headers'=>['X-CSRF-Token'=>$csrf,'Content-Type'=>'application/json','Content-Encoding'=>'gzip'],'body'=>$valid]);
    compHttpRequest($a,'POST',400,['headers'=>['X-CSRF-Token'=>$csrf],'form_params'=>['action'=>'review']]);
    compHttpRequest($a,'POST',400,['headers'=>['X-CSRF-Token'=>$csrf],'multipart'=>[['name'=>'upload','filename'=>'fixture.txt','contents'=>'PRIVATE_UPLOAD_SENTINEL']]]);
    compHttpRequest($a,'POST',413,['headers'=>['X-CSRF-Token'=>$csrf,'Content-Type'=>'application/json'],'body'=>str_pad($valid,4097)]);
    compHttpRequest($a,'POST',503,['headers'=>['X-CSRF-Token'=>$csrf,'Content-Type'=>'APPLICATION/JSON; CHARSET=UTF-8'],'body'=>str_pad($valid,4096)]);
    compHttpRequest($a,'POST',413,['headers'=>['X-CSRF-Token'=>$csrf,'Content-Type'=>'application/json','Transfer-Encoding'=>'chunked'],'body'=>str_pad($valid,4097)]);
    foreach(['GET','POST'] as $method){compHttpRequest($a,$method,400,[], '?user_id=1');compHttpRequest($a,$method,400,['headers'=>['X-HTTP-Method-Override'=>'GET']]);}
    compHttpRequest($a,'GET',400,['body'=>'{}']);
    echo "PASS completion HTTP CSRF bounded UTF-8 flat JSON exact types duplicate escaped keys overrides uploads media and encoding rejection\n";

    $phase='review';compHttpStart('1',true);$a=compHttpBrowser($userA,$ready);
    $r=compHttpReview($a)['review'];$r2=compHttpReview($a)['review'];
    compHttpAssert($r['fields']===compHttpSession($a,static fn()=>readOnboardingDraft($database,requireAuthenticatedUser($database)))['fields'],'Review saved fields differ.');
    compHttpAssert(count($r['deferred_fields'])===7 && str_contains($r['disposition'],'not added') && $r['expectedVersion']===3 && $r['reviewToken']!==$r2['reviewToken'],'Review disposition or replacement changed.');
    $intent=['action'=>'complete',...completionIntent($r)];compHttpPost($a,$intent,409);
    compHttpReview($a,2,409);compHttpReview($a,4,409);
    $noDraft=completionNewUser($database);$users[]=$noDraft;$nd=compHttpBrowser($noDraft,$ready);
    compHttpSession($nd,static function()use($database,$noDraft):void{recordOnboardingAttestations($database,requireAuthenticatedUser($database),completionFixtureBundle(),['age_18_or_older'=>true,'terms_accepted'=>true,'privacy_notice_acknowledged'=>true]);});
    compHttpReview($nd,1,409);
    $bad=compHttpBrowser($userA);compHttpReview($bad,3,403);
    $without=$new();$database->exec("DELETE FROM onboarding_attestations WHERE user_id=$without");compHttpReview(compHttpBrowser($without,$ready),3,403);
    compHttpControl(['version'=>'test-v2']);compHttpReview($a,3,403);compHttpControl();
    compHttpSession($a,static function()use($database,$userA):void{recordOnboardingAttestations($database,requireAuthenticatedUser($database),completionFixtureBundle('test-v2'),['age_18_or_older'=>true,'terms_accepted'=>true,'privacy_notice_acknowledged'=>true]);});
    compHttpControl(['version'=>'test-v2']);compHttpPost($a,['action'=>'complete',...completionIntent($r2)],409);compHttpControl();
    compHttpControl(['revoke_user'=>$userA]);compHttpPost($a,['action'=>'review','expectedVersion'=>3],403,false);compHttpControl();$database->exec("UPDATE users SET authz_version=1 WHERE id=$userA");$a=compHttpBrowser($userA,$ready);
    $changed=$new();$cb=compHttpBrowser($changed,$ready);$cr=compHttpReview($cb)['review'];
    compHttpSession($cb,static function()use($database):void{saveOnboardingDraft($database,requireAuthenticatedUser($database),3,'3:'.bin2hex(random_bytes(16)),'professional_snapshot',['headline'=>'Deliberate later saved headline']);});
    compHttpPost($cb,['action'=>'complete',...completionIntent($cr)],409);compHttpReview($cb,3,409);compHttpReview($cb,4);
    echo "PASS completion HTTP exact saved review disposition replacement stale drafts changed bundles readiness evidence and locked revocation\n";

    $phase='stored validation';$invalid=$new();$ib=compHttpBrowser($invalid,$ready);
    $payload=$database->query("SELECT payload FROM onboarding_drafts WHERE user_id=$invalid")->fetchColumn();
    foreach(['headline'=>str_repeat('h',200),'country_code'=>'US','slug_candidate'=>'admin'] as $key=>$value){$fields=json_decode($payload,true);$fields[$key]=$value;$database->prepare('UPDATE onboarding_drafts SET payload=? WHERE user_id=?')->execute([onboardingDraftEncode($fields),$invalid]);compHttpAssert(compHttpReview($ib,3,422)['code']==='saved_fields_invalid','Saved invalid field classified as input JSON.');}
    $database->exec("UPDATE onboarding_drafts SET payload='{\"unknown\":\"private\"}' WHERE user_id=$invalid");compHttpAssert(compHttpReview($ib,3,503)['code']==='temporarily_unavailable','Corrupt stored draft classified as malformed request.');
    $database->prepare('UPDATE onboarding_drafts SET payload=? WHERE user_id=?')->execute([$payload,$invalid]);
    echo "PASS completion HTTP saved-field correction and corrupt stored-data dependency errors remain separate from malformed JSON\n";

    $phase='complete';$review=compHttpReview($a)['review'];$intent=['action'=>'complete',...completionIntent($review)];$original=compHttpPost($a,$intent,200,false)['receipt'];
    compHttpAssert(array_keys($original)===['portfolio_id','profile_id','draft_version','initial_profile_version','contract_revision','catalog_revision','deferred_fields','completed_at'],'Receipt leaked raw records.');
    $owned=compHttpSession($a,static fn()=>requireOwnedPortfolioContext($database));$profile=loadAuthorizedPersonalInfo($database,$owned);
    compHttpAssert((int)$profile['profile_version']===1 && (int)$profile['public_contact_visible']===0 && $profile['full_name']===$review['fields']['display_name'],'Private mapping changed.');
    compHttpAssert((int)$database->query('SELECT is_published FROM portfolios WHERE id='.$original['portfolio_id'])->fetchColumn()===0,'Published on completion.');
    compHttpAssert(compHttpPost($a,$intent)['receipt']===$original,'Exact repeat rewrote receipt.');
    compHttpPost($a,array_replace($intent,['operationId'=>'3:'.bin2hex(random_bytes(16))]),409);compHttpReview($a,3,409);
    echo "PASS completion HTTP first atomic private creation original receipt projection exact retries and changed intent conflict\n";

    $phase='later production recovery';
    compHttpSession($a,static function()use($database,$owned,$original):void{saveOwnedProfilePatch($database,$owned,$original['profile_id'],1,['full_name'=>'Later Profile edit']);});
    compHttpStart('1',false);$later=compHttpBrowser($userA);$other=compHttpBrowser($userB);
    compHttpAssert(compHttpRequest($later,'GET',200)['receipt']===$original && compHttpPost($later,$intent)['receipt']===$original,'Null policy or later session blocked historical receipt.');
    compHttpAssert(compHttpRequest($other,'GET',200,['headers'=>['X-User-ID'=>(string)$userA]])['receipt']===null,'Cross-account receipt leak.');
    compHttpAssert(loadAuthorizedPersonalInfo($database,$owned)['full_name']==='Later Profile edit','Receipt rewrote later edit.');
    echo "PASS completion HTTP later-session recovery and exact retry under original null policies preserve later Profile edits and account boundaries\n";

    $phase='concurrent';compHttpStart('1',true);$race=$new();$c=compHttpBrowser($race,$ready,0);$d=compHttpBrowser($race,$ready,1);$rr=compHttpReview($c)['review'];
    $saved=compHttpSession($c,static fn()=>$_SESSION);compHttpSession($d,static function()use($saved):void{$_SESSION=$saved;});
    $raceIntent=['action'=>'complete',...completionIntent($rr)];$pc=compHttpRequest($c,'GET',200);$pd=compHttpRequest($d,'GET',200);
    compHttpControl(['barrier'=>true]);$results=compHttpConcurrent([$c,$d],[['csrf_token'=>$pc['csrf_token'],...$raceIntent],['csrf_token'=>$pd['csrf_token'],...$raceIntent]],$race);compHttpControl();
    foreach($results as $row){compHttpAssert($row['status']===200,'Concurrent completion failed.');}compHttpAssert($results[0]['body']===$results[1]['body'],'Concurrent exact requests differ.');
    echo "PASS completion HTTP genuine concurrent two-session confirmations serialize to one original private outcome\n";

    $phase='failure recovery';
    foreach([1,2,3] as $write){$id=$new();$fb=compHttpBrowser($id,$ready);$fr=compHttpReview($fb)['review'];compHttpControl(['fail_write'=>$write]);compHttpAssert(compHttpPost($fb,['action'=>'complete',...completionIntent($fr)],503)['code']==='temporarily_unavailable','Rollback dependency classification changed.');compHttpControl();compHttpAssert(compHttpRequest($fb,'GET',200)['receipt']===null,'Rollback left receipt.');}
    foreach(['before_commit','after_commit','lost_response'] as $fault){
        $id=$new();$fb=compHttpBrowser($id,$ready);$fr=compHttpReview($fb)['review'];$fi=['action'=>'complete',...completionIntent($fr)];compHttpControl(['fault'=>$fault]);
        if($fault==='lost_response'){$csrf=compHttpSession($fb,static fn()=>getCsrfToken());$resp=$fb->post('/owner_onboarding_completion.php',['headers'=>['X-CSRF-Token'=>$csrf],'json'=>$fi]);compHttpAssert((string)$resp->getBody()==='','Lost response fixture unexpectedly acknowledged.');}
        else{compHttpAssert(compHttpPost($fb,$fi,503,$fault==='before_commit')['code']==='outcome_uncertain','Possible commit not distinguished.');}
        compHttpControl();$receipt=compHttpRequest($fb,'GET',200)['receipt'];compHttpAssert(($receipt===null)===($fault==='before_commit'),'Explicit receipt read did not recover actual outcome.');
        if($receipt!==null){compHttpAssert(compHttpPost($fb,$fi)['receipt']===$receipt,'Recovered exact repeat changed outcome.');}
    }
    $database->exec('RENAME TABLE onboarding_completions TO comp_http_parked');try{compHttpRequest($a,'GET',503);}finally{$database->exec('RENAME TABLE comp_http_parked TO onboarding_completions');}
    $database->exec('RENAME TABLE users TO comp_http_users');try{compHttpRequest($a,'GET',503);}finally{$database->exec('RENAME TABLE comp_http_users TO users');}
    compHttpStart('1',true,['DB_PORT'=>'invalid']);compHttpRequest($a,'GET',503);
    compHttpStart('1',true,[],$root.'/missing-session-dir');compHttpRequest($a,'GET',503);
    echo "PASS completion HTTP SQL rollback each write uncertain COMMIT lost response explicit receipt recovery and dependency failures\n";

    compHttpStop();$log=file_get_contents($root.'/private-http.log');
    foreach(array_merge($secrets,['PRIVATE_OVERRIDE','PRIVATE_FAULT_SQL_SENTINEL','PRIVATE_UPLOAD_SENTINEL','Later Profile edit',getenv('DB_PASSWORD')]) as $secret){compHttpAssert(!str_contains($log,$secret),'Private HTTP log leaked a forbidden value.');}
    compHttpAssert(!preg_match('/SQLSTATE|PDOException|Stack trace|Uncaught|INSERT INTO|SELECT \\*/',$log),'HTTP log leaked exception/SQL.');
    echo "PASS completion HTTP private no-store projections and safe logs without request tokens bodies sessions or SQL\n";
} catch(Throwable $error) {
    $message=get_class($error)===RuntimeException::class?$error->getMessage():'Unexpected '.get_class($error).' at '.$phase.' source line '.$error->getLine();
    fwrite(STDERR,'FAIL completion HTTP: '.$message."\n");$exitCode=1;
} finally {
    compHttpStop();if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}
    if(isset($database)){completionCleanup($database,$users);if($initial!==null){compHttpAssert($initial===compHttpSnapshot($database),'Preexisting data changed or fixture rows remain.');}}
    if($environment instanceof TestEnvironment){$environment->tearDown();compHttpAssert($environment->wasTornDown(),'Private namespace cleanup failed.');}
}
if($exitCode===0){echo "PASS completion HTTP protected data preservation and process session source file database fixture cleanup\n";}
ob_end_flush();exit($exitCode);
