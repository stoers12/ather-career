<?php
declare(strict_types=1);

require_once __DIR__.'/../tests/phase2/support/attestation-http-fixtures.php';
require_once __DIR__.'/../tests/phase2/bootstrap.php';
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../includes/owner_session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/onboarding_attestation_presentation.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

ob_start();
function attHttpAssert(bool $condition,string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function attHttpSnapshot(PDO $db): array
{
    $snapshot=[];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC)); sort($rows); $snapshot[$table]=hash('sha256',serialize($rows));
    }
    ksort($snapshot); return $snapshot;
}
function attHttpControl(array $control=[]): void
{
    global $root;
    file_put_contents($root.'/control.json',json_encode($control,JSON_THROW_ON_ERROR)); chmod($root.'/control.json',0600);
}
function attHttpStop(): void
{
    global $servers;
    foreach ($servers as $process) {
        if (!is_resource($process)) { continue; }
        proc_terminate($process);
        $stopped=false;
        for ($i=0;$i<100;$i++) { if (!proc_get_status($process)['running']) { $stopped=true; break; } usleep(20000); }
        if (!$stopped) { proc_terminate($process,9); }
        proc_close($process);
        attHttpAssert($stopped,'HTTP fixture required forced termination.');
    }
    $servers=[];
}
function attHttpStart(?string $gate,bool $synthetic=false,array $overrides=[],?string $sessionPath=null): void
{
    global $root,$servers,$ports;
    attHttpStop();
    for ($server=0;$server<2;$server++) {
        if(!isset($ports[$server])){
            $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$message);
            attHttpAssert(is_resource($socket),'Loopback port unavailable.');
            $address=stream_socket_get_name($socket,false); fclose($socket); $ports[$server]=(int)substr($address,strrpos($address,':')+1);
        }
        // Persisted browser clients must keep the same server address on restart.
        $port=$ports[$server];
        $env=array_intersect_key(getenv(),array_flip(['PATH','APP_ENV','ATHERCAR_TEST_MODE','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','ATHERCAR_TEST_DB_NAME']));
        $env+=['SESSION_COOKIE_SECURE'=>'0','ATHERCAR_REGISTRATION_ENABLED'=>'0','ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED'=>'0'];
        if ($gate!==null) { $env['ATHERCAR_ONBOARDING_ATTESTATION_HTTP_ENABLED']=$gate; }
        $env=array_replace($env,$overrides);
        $p=proc_open([PHP_BINARY,'-d','session.save_path='.($sessionPath??$root.'/sessions'),'-d','display_errors=0','-d','log_errors=1',
            '-S','127.0.0.1:'.$port,'-t',$root.($synthetic?'/synthetic-public':'/http/public')],
            [0=>['pipe','r'],1=>['file',$root.'/private-http.log','a'],2=>['file',$root.'/private-http.log','a']],$pipes,null,$env);
        attHttpAssert(is_resource($p),'HTTP process unavailable.'); fclose($pipes[0]); $servers[]=$p;
        $ready=false;
        for($i=0;$i<80;$i++) {
            $s=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$message,0.1);
            if(is_resource($s)){fclose($s);$ready=true;break;} usleep(25000);
        }
        attHttpAssert($ready,'HTTP process not ready.');
    }
}
function attHttpBrowser(?int $id=null,?callable $mutate=null,int $server=0): Client
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
function attHttpSession(Client $browser,callable $operation): mixed
{
    $cookie=$browser->getConfig('cookies')->getCookieByName(OWNER_SESSION_NAME);
    attHttpAssert($cookie!==null,'Fixture session cookie missing.');
    session_id($cookie->getValue()); startOwnerSession();
    try { return $operation(); } finally { session_write_close(); }
}
function attHttpRequest(Client $browser,string $method,int $expected,array $options=[],string $query='',bool $preserved=true): array
{
    global $database,$secrets;
    $before=attHttpSnapshot($database);
    $response=$browser->request($method,'/owner_onboarding_attestation.php'.$query,$options);
    attHttpAssert($response->getStatusCode()===$expected,'HTTP status: expected '.$expected.', got '.$response->getStatusCode());
    attHttpAssert($response->getHeaderLine('Content-Type')==='application/json; charset=utf-8'
        && $response->getHeaderLine('Cache-Control')==='private, no-store' && !$response->hasHeader('Location')
        && !$response->hasHeader('Access-Control-Allow-Origin'),'Private response headers changed.');
    if($expected===405){attHttpAssert($response->getHeaderLine('Allow')==='GET, POST','Incorrect Allow.');}
    if($preserved){attHttpAssert($before===attHttpSnapshot($database),'Read/rejected/repeated request changed database.');}
    if($method==='HEAD'){return [];}
    $json=json_decode((string)$response->getBody(),true,16,JSON_THROW_ON_ERROR);
    attHttpAssert(($json['success']??null)===($expected===200),'Invalid response envelope.');
    if($expected!==200){
        attHttpAssert(array_keys($json)===['success','code','error'] && !preg_match('/SQLSTATE|PDO|Exception|SELECT |INSERT |password|PRIVATE_|SYNTHETIC|session_id/i',$json['error']),'Unsafe error response.');
    } else {
        attHttpAssert(array_keys($json['evidence'])===['recorded','recorded_at'] && is_bool($json['evidence']['recorded']),'Evidence projection changed.');
        foreach(['csrf_token','presentation_token'] as $key){if(isset($json[$key])){$secrets[]=$json[$key];}}
    }
    return $json;
}
function attHttpInput(array $presentation): array
{
    return ['presentation_token'=>$presentation['presentation_token'],'age_18_or_older'=>true,'terms_accepted'=>true,'privacy_notice_acknowledged'=>true];
}
function attHttpPost(Client $browser,array $presentation,int $expected=200,?array $input=null,bool $preserved=false): array
{
    return attHttpRequest($browser,'POST',$expected,['headers'=>['X-CSRF-Token'=>$presentation['csrf_token']],'json'=>$input??attHttpInput($presentation)],'', $expected!==200 ? true : $preserved);
}
function attHttpConcurrent(array $browsers,array $presentations,int $userId): array
{
    global $database,$root,$ports;
    foreach(glob($root.'/arrivals/*')?:[] as $file){unlink($file);}
    $database->beginTransaction(); $database->query('SELECT id FROM users WHERE id='.(int)$userId.' FOR UPDATE')->fetchColumn();
    $workers=[];
    try {
        $code='require $argv[1]; $i=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR); $c=new GuzzleHttp\\Client(["http_errors"=>false,"timeout"=>15]); $r=$c->post($i["url"],["headers"=>$i["headers"],"json"=>$i["input"]]); echo json_encode(["status"=>$r->getStatusCode(),"cache"=>$r->getHeaderLine("Cache-Control"),"body"=>json_decode((string)$r->getBody(),true,16,JSON_THROW_ON_ERROR)],JSON_THROW_ON_ERROR);';
        foreach($browsers as $index=>$browser){
            $p=proc_open([PHP_BINARY,'-r',$code,__DIR__.'/../vendor/autoload.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            attHttpAssert(is_resource($p),'HTTP race worker unavailable.');
            $workers[]=[$p,$pipes];
            $sid=$browser->getConfig('cookies')->getCookieByName(OWNER_SESSION_NAME)->getValue();
            fwrite($pipes[0],json_encode(['url'=>'http://127.0.0.1:'.$ports[$index].'/owner_onboarding_attestation.php','headers'=>['Cookie'=>OWNER_SESSION_NAME.'='.$sid,'X-CSRF-Token'=>$presentations[$index]['csrf_token']],'input'=>attHttpInput($presentations[$index])],JSON_THROW_ON_ERROR)); fclose($pipes[0]);
        }
        $arrived=false;
        for($i=0;$i<200;$i++){if(count(glob($root.'/arrivals/*')?:[])===2){$arrived=true;break;} usleep(25000);}
        attHttpAssert($arrived,'Both HTTP requests did not reach the real User-lock barrier.'); $database->commit();
        $results=[];
        foreach($workers as [$p,$pipes]){
            $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            attHttpAssert(proc_close($p)===0 && $err==='','HTTP race worker failed.');
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
    $environment=TestEnvironment::create(); $root=$environment->namespaceRoot.'/attestation-http';
    mkdir($root,0700); mkdir($root.'/sessions',0700); mkdir($root.'/arrivals',0700); mkdir($root.'/http',0700); mkdir($root.'/http/public',0700);
    symlink(realpath(__DIR__.'/..'),$root.'/http/app'); copy(__DIR__.'/../public/owner_onboarding_attestation.php',$root.'/http/public/owner_onboarding_attestation.php');
    attestationHttpFixtureCopies($root); attHttpControl();
    session_save_path($root.'/sessions'); session_name(OWNER_SESSION_NAME); ini_set('session.cache_limiter','');
    $database=getDatabaseConnection();
    attHttpAssert($database->query("SELECT name FROM schema_migrations WHERE version='019'")->fetchColumn()==='onboarding_completions','Full 019 migration required.');
    $initial=attHttpSnapshot($database);
    for($i=0;$i<4;$i++){
        $database->prepare("INSERT INTO users(oidc_issuer,oidc_subject,account_status,authz_version) VALUES('https://attestation-http.invalid/',?,'active',1)")->execute([bin2hex(random_bytes(16))]); $users[]=(int)$database->lastInsertId();
    }
    [$userA,$userB,$raceUser,$disabled]=$users; $database->exec("UPDATE users SET account_status='disabled' WHERE id=$disabled");
    $phase='gate/method';
    foreach([null,'','0','true','TRUE','01',' 1','1 ','false'] as $gate){
        attHttpStart($gate,false,['DB_PORT'=>'invalid']);
        foreach(['GET','POST','DELETE'] as $method){attHttpRequest(attHttpBrowser(),$method,404);}
    }
    attHttpStart('1');
    foreach(['PUT','PATCH','DELETE','OPTIONS','HEAD'] as $method){attHttpRequest(attHttpBrowser(),$method,405);}
    foreach(['get','post'] as $method){
        $code='$_SERVER["REQUEST_METHOD"]=$argv[1]; putenv("ATHERCAR_ONBOARDING_ATTESTATION_HTTP_ENABLED=1"); putenv("DB_PORT=invalid"); register_shutdown_function(static function():void{fwrite(STDERR,"STATUS=".http_response_code());}); require $argv[2];';
        $p=proc_open([PHP_BINARY,'-r',$code,$method,__DIR__.'/../owner_onboarding_attestation.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes); fclose($pipes[0]);
        $body=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        attHttpAssert(proc_close($p)===0 && str_contains($err,'STATUS=405') && json_decode($body,true)['code']==='method_not_allowed','Lowercase method bypass.');
    }
    echo "PASS attestation HTTP exact default-off gate and method admission before session/database access\n";

    $phase='authorization';
    foreach(['GET','POST'] as $method){
        attHttpRequest(attHttpBrowser(),$method,401);
        foreach(['idle','absolute','revoked','malformed','future','missing-user'] as $case){
            $browser=attHttpBrowser($userA,static function()use($case):void{
                $s=&$_SESSION[INTERNAL_USER_SESSION_KEY];
                if($case==='idle'){$s['authenticated_at']-=2000;$s['last_activity_at']-=2000;}
                if($case==='absolute'){$s['authenticated_at']-=50000;}
                if($case==='revoked'){$s['authz_version']=2;}
                if($case==='malformed'){$s['internal_user_id']='invalid';}
                if($case==='future'){$s['last_activity_at']+=120;}
                if($case==='missing-user'){$s['internal_user_id']=4294967295;}
            });
            attHttpRequest($browser,$method,403);
        }
        attHttpRequest(attHttpBrowser($disabled),$method,403);
    }
    echo "PASS attestation HTTP anonymous, expired, revoked, disabled and malformed authorization\n";

    $a=attHttpBrowser($userA);
    $csrf=attHttpSession($a,static fn()=>getCsrfToken());
    $fake=['presentation_token'=>str_repeat('a',64),'csrf_token'=>$csrf];
    attHttpAssert(attHttpRequest($a,'GET',503)['code']==='policies_unavailable','Production null policy did not deny.');
    attHttpAssert(attHttpPost($a,$fake,503)['code']==='policies_unavailable','Production POST created or misclassified policies.');
    attHttpAssert(attHttpSession($a,static fn()=>!isset($_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY])),'Null policy left usable presentation.');
    echo "PASS attestation HTTP original production null policies deny GET/POST without token or evidence\n";

    $phase='documents'; attHttpStart('1',true); $a=attHttpBrowser($userA); $b=attHttpBrowser($userB);
    foreach(['age','terms','privacy'] as $kind){foreach(['missing','digest','language','empty','utf8','oversized','type'] as $bad){
        attHttpControl(['kind'=>$kind,'bad'=>$bad]); attHttpAssert(attHttpRequest($a,'GET',503)['code']==='policies_unavailable','Invalid document admitted.');
    }}
    foreach(['null_bundle','null_documents'] as $bad){attHttpControl([$bad=>true]);attHttpRequest($a,'GET',503);}
    foreach(['ar','en'] as $language){
        attHttpControl(['language'=>$language]);$p=attHttpRequest($a,'GET',200);
        attHttpAssert(array_keys($p)===['success','documents','evidence','csrf_token','presentation_token'] && $p['evidence']===['recorded'=>false,'recorded_at'=>null],'Presentation projection changed.');
        attHttpAssert(array_keys($p['documents'])===['age','terms','privacy'],'Fixed document set changed.');
        foreach($p['documents'] as $kind=>$doc){attHttpAssert($doc['language']===$language && $doc['digest']===hash('sha256',$doc['content']) && str_starts_with($doc['content'],'SYNTHETIC TEST ONLY'),'Document bytes/digest/language mismatch.');}
    }
    attHttpControl(['maximum'=>true]);$max=attHttpRequest($a,'GET',200);
    foreach($max['documents'] as $doc){attHttpAssert(strlen($doc['content'])===65536 && $doc['digest']===hash('sha256',$doc['content']),'Exact document byte boundary failed.');}
    attHttpControl();$p=attHttpRequest($a,'GET',200);$q=attHttpRequest($b,'GET',200);
    $session=attHttpSession($a,static fn()=>$_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY]);
    attHttpAssert(strlen(serialize($session))<2048 && !str_contains(serialize($session),'SYNTHETIC') && preg_match('/\A[a-f0-9]{64}\z/D',$p['presentation_token'])===1,'Unbounded/unsafe session presentation.');
    echo "PASS attestation HTTP immutable exact bytes/digests/languages, all-document availability and bounded private presentation\n";

    $phase='strict input';
    foreach([null,'','invalid',$q['csrf_token']] as $csrf){$headers=$csrf===null?[]:['X-CSRF-Token'=>$csrf];attHttpRequest($a,'POST',403,['headers'=>$headers,'json'=>attHttpInput($p)]);}
    foreach(['user_id','internal_user_id','portfolio_id','documents','document','bundle','revision','version','digest','language','recorded_at','timestamp','authority','readiness','csrf_token'] as $key){$i=attHttpInput($p);$i[$key]=$userB;attHttpPost($a,$p,400,$i);}
    foreach(['presentation_token','age_18_or_older','terms_accepted','privacy_notice_acknowledged'] as $key){
        $i=attHttpInput($p);unset($i[$key]);attHttpPost($a,$p,400,$i);
        foreach([null,false,0,1,'true','1',[],new stdClass()] as $value){$i=attHttpInput($p);$i[$key]=$value;attHttpPost($a,$p,400,$i);}
    }
    foreach(['',str_repeat('A',64),str_repeat('a',63),str_repeat('a',65)] as $token){$i=attHttpInput($p);$i['presentation_token']=$token;attHttpPost($a,$p,400,$i);}
    $valid=json_encode(attHttpInput($p),JSON_THROW_ON_ERROR);
    foreach(['','null','[]','{',$valid.'{}',"\xEF\xBB\xBF".$valid,'{"invalid":"' . "\xff" . '"}',
        str_replace('"terms_accepted":true','"terms_accepted":false,"terms_accepted":true',$valid),
        str_replace('"terms_accepted":true','"terms_accepted":true,"terms_\\u0061ccepted":true',$valid),
        str_replace('"age_18_or_older":true','"age_18_or_older":'.str_repeat('[',8).'true'.str_repeat(']',8),$valid)] as $raw){
        attHttpRequest($a,'POST',400,['headers'=>['X-CSRF-Token'=>$p['csrf_token'],'Content-Type'=>'application/json'],'body'=>$raw]);
    }
    foreach(['?user_id='.$userB,'?language=ar','?token=ignored'] as $query){attHttpRequest($a,'GET',400,[],$query);attHttpRequest($a,'POST',400,['headers'=>['X-CSRF-Token'=>$p['csrf_token']],'json'=>attHttpInput($p)],$query);}
    attHttpRequest($a,'GET',400,['body'=>'{}']);
    foreach(['text/plain','application/json; charset=utf-16','application/json; profile=x'] as $type){attHttpRequest($a,'POST',415,['headers'=>['X-CSRF-Token'=>$p['csrf_token'],'Content-Type'=>$type],'body'=>$valid]);}
    attHttpRequest($a,'POST',400,['headers'=>['X-CSRF-Token'=>$p['csrf_token']],'form_params'=>['age_18_or_older'=>'true']]);
    attHttpRequest($a,'POST',400,['headers'=>['X-CSRF-Token'=>$p['csrf_token']],'multipart'=>[['name'=>'upload','filename'=>'fixture.txt','contents'=>'PRIVATE_UPLOAD_SENTINEL']]]);
    attHttpRequest($a,'POST',415,['headers'=>['X-CSRF-Token'=>$p['csrf_token'],'Content-Type'=>'application/json','Content-Encoding'=>'gzip'],'body'=>$valid]);
    attHttpRequest($a,'POST',400,['headers'=>['X-CSRF-Token'=>$p['csrf_token'],'X-HTTP-Method-Override'=>'GET'],'json'=>attHttpInput($p)]);
    attHttpRequest($a,'POST',413,['headers'=>['X-CSRF-Token'=>$p['csrf_token'],'Content-Type'=>'application/json'],'body'=>str_repeat(' ',4097)]);
    $sent=false;$chunked=new GuzzleHttp\Psr7\PumpStream(static function()use(&$sent): string|false{if($sent){return false;}$sent=true;return str_repeat(' ',4097);});
    attHttpRequest($a,'POST',413,['headers'=>['X-CSRF-Token'=>$p['csrf_token'],'Content-Type'=>'application/json','Transfer-Encoding'=>'chunked'],'body'=>$chunked]);
    // Exactly 4096 valid bytes pass transport; no prior GET survived invalid GETs.
    $p=attHttpRequest($a,'GET',200);$valid=json_encode(attHttpInput($p),JSON_THROW_ON_ERROR);
    $bad=str_replace('"terms_accepted":true','"terms_accepted":false',$valid);
    attHttpRequest($a,'POST',400,['headers'=>['X-CSRF-Token'=>$p['csrf_token'],'Content-Type'=>'application/json'],'body'=>str_pad($bad,4096)]);
    echo "PASS attestation HTTP CSRF, uploads, encoding, size, strict JSON/booleans and authority override rejection\n";

    $phase='presentation binding';
    $old=$p;$p=attHttpRequest($a,'GET',200);attHttpAssert($old['presentation_token']!==$p['presentation_token'],'GET did not rotate presentation.');
    attHttpAssert(attHttpPost($a,$old,409)['code']==='presentation_stale','Old token not stale.');
    $p=attHttpRequest($a,'GET',200); $cross=attHttpInput($p);attHttpPost($b,$q,409,$cross);
    $sameUser=attHttpBrowser($userA);$other=attHttpRequest($sameUser,'GET',200);attHttpPost($sameUser,$other,409,attHttpInput($p));
    $saved=attHttpSession($a,static fn()=>$_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY]);
    attHttpSession($sameUser,static function()use($saved):void{$_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY]=$saved;});
    attHttpPost($sameUser,$other,409,attHttpInput($p));
    attHttpSession($b,static function()use($saved):void{$_SESSION[ONBOARDING_ATTESTATION_PRESENTATION_KEY]=$saved;});attHttpPost($b,$q,409,attHttpInput($p));
    attHttpControl(['version'=>'v2']);attHttpPost($a,$p,409);attHttpControl();
    $p=attHttpRequest($a,'GET',200);attHttpControl(['null_documents'=>true]);attHttpPost($a,$p,503);attHttpControl();attHttpPost($a,$p,409);
    foreach(['age','terms','privacy'] as $kind){
        $p=attHttpRequest($a,'GET',200);attHttpControl(['kind'=>$kind,'bad'=>'digest']);attHttpPost($a,$p,503);attHttpControl();attHttpPost($a,$p,409);
    }
    $p=attHttpRequest($a,'GET',200);$database->exec("UPDATE users SET authz_version=2 WHERE id=$userA");attHttpPost($a,$p,403);$database->exec("UPDATE users SET authz_version=1 WHERE id=$userA");
    $a=attHttpBrowser($userA);$p=attHttpRequest($a,'GET',200);
    attHttpControl(['revoke_user'=>$userA]);
    attHttpRequest($a,'POST',403,['headers'=>['X-CSRF-Token'=>$p['csrf_token']],'json'=>attHttpInput($p)],'',false);
    attHttpAssert((int)$database->query("SELECT COUNT(*) FROM onboarding_attestations WHERE user_id=$userA")->fetchColumn()===0,'Revocation under User lock wrote evidence.');
    $database->exec("UPDATE users SET authz_version=1 WHERE id=$userA");attHttpControl();$a=attHttpBrowser($userA);
    echo "PASS attestation HTTP fresh-review enforcement, session/account/bundle binding and locked authorization revalidation\n";

    $phase='explicit recording';$p=attHttpRequest($a,'GET',200);$before=attHttpSnapshot($database);
    $r=attHttpPost($a,$p);attHttpAssert($r['evidence']['recorded']===true && preg_match('/\A\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\.\d{6}\z/D',$r['evidence']['recorded_at'])===1,'Missing exact evidence/time.');
    usleep(20000);attHttpAssert(attHttpPost($a,$p,200,null,true)===$r,'Repeat changed original timestamp.');
    $escaped=str_replace('"terms_accepted"','"terms_\\u0061ccepted"',json_encode(attHttpInput($p),JSON_THROW_ON_ERROR));
    $padded=attHttpRequest($a,'POST',200,['headers'=>['X-CSRF-Token'=>$p['csrf_token'],'Content-Type'=>'APPLICATION/JSON; CHARSET=UTF-8'],'body'=>str_pad($escaped,4096)]);
    attHttpAssert($padded===$r,'Exact 4096-byte valid escaped envelope changed evidence.');
    attHttpAssert(attHttpRequest($a,'GET',200)['evidence']===$r['evidence'],'Evidence re-read differs.');
    $after=attHttpSnapshot($database);unset($before['onboarding_attestations'],$after['onboarding_attestations']);attHttpAssert($before===$after,'Attestation granted authority or changed Profile/draft/completion.');
    $b=attHttpBrowser($userB);$q=attHttpRequest($b,'GET',200);attHttpAssert($q['evidence']['recorded']===false,'Account boundary leaked evidence.');
    $identity=attHttpRequest($b,'GET',200,['headers'=>['X-User-ID'=>(string)$userA]]);attHttpAssert($identity['evidence']['recorded']===false,'Identity header selected another User.');
    echo "PASS attestation HTTP explicit exact-bundle recording, stable original timestamp and no account/profile/completion authority\n";

    $phase='concurrent recording';$c=attHttpBrowser($raceUser,null,0);$d=attHttpBrowser($raceUser,null,1);
    $pc=attHttpRequest($c,'GET',200);$pd=attHttpRequest($d,'GET',200);attHttpControl(['barrier'=>true]);
    $results=attHttpConcurrent([$c,$d],[$pc,$pd],$raceUser);attHttpControl();
    foreach($results as $result){attHttpAssert($result['status']===200 && $result['cache']==='private, no-store' && $result['body']['evidence']['recorded']===true,'Concurrent response failed.');}
    attHttpAssert($results[0]['body']===$results[1]['body'] && (int)$database->query("SELECT COUNT(*) FROM onboarding_attestations WHERE user_id=$raceUser")->fetchColumn()===1,'Concurrent exact evidence duplicated or timestamp changed.');
    echo "PASS attestation HTTP genuine concurrent two-session requests serialize at the User lock and return one original outcome\n";

    $phase='changed documents';$p=attHttpRequest($a,'GET',200);attHttpControl(['version'=>'v2','language'=>'ar']);attHttpPost($a,$p,409);
    $p=attHttpRequest($a,'GET',200);attHttpAssert($p['evidence']['recorded']===false,'Older evidence carried forward.');attHttpPost($a,$p);
    attHttpAssert((int)$database->query("SELECT COUNT(*) FROM onboarding_attestations WHERE user_id=$userA")->fetchColumn()===2,'Changed bundle did not preserve history.');
    attHttpControl();$p=attHttpRequest($a,'GET',200);attHttpAssert($p['evidence']===$r['evidence'],'Historical exact evidence lost.');
    echo "PASS attestation HTTP changed translation/version requires fresh explicit actions and preserves historical exact evidence\n";

    $phase='failure/recovery';attHttpControl(['version'=>'fault-v1']);$p=attHttpRequest($a,'GET',200);
    foreach(['before_insert','after_insert'] as $fault){attHttpControl(['version'=>'fault-v1','fault'=>$fault]);attHttpAssert(attHttpPost($a,$p,503)['code']==='temporarily_unavailable','Wrong dependency classification.');}
    attHttpControl(['version'=>'fault-v1','fault'=>'after_commit']);
    $uncertain=attHttpRequest($a,'POST',503,['headers'=>['X-CSRF-Token'=>$p['csrf_token']],'json'=>attHttpInput($p)],'',false);
    attHttpAssert($uncertain['code']==='temporarily_unavailable' && !preg_match('/nothing|not recorded|retry/i',$uncertain['error']),'Uncertain outcome falsely promised absence/retry.');
    attHttpControl(['version'=>'fault-v1']);$recovered=attHttpRequest($a,'GET',200);attHttpAssert($recovered['evidence']['recorded']===true,'Committed lost response not recoverable.');
    attHttpPost($a,$p,409);$recovered=attHttpRequest($a,'GET',200);attHttpAssert(attHttpPost($a,$recovered,200,null,true)['evidence']===$recovered['evidence'],'Explicit post-recovery repeat changed time.');
    $phase='real SQL failure';attHttpControl(['version'=>'sql-fault']);$p=attHttpRequest($a,'GET',200);
    $database->exec("ALTER TABLE onboarding_attestations ADD CONSTRAINT att_http_test_failure CHECK (terms_version <> 'sql-fault')");
    try {attHttpPost($a,$p,503);} finally {$database->exec('ALTER TABLE onboarding_attestations DROP CHECK att_http_test_failure');}
    $phase='missing evidence table';$database->exec('RENAME TABLE onboarding_attestations TO att_http_parked');
    try {attHttpRequest($a,'GET',503);} finally {$database->exec('RENAME TABLE att_http_parked TO onboarding_attestations');}
    $phase='authorization query dependency';$database->exec('RENAME TABLE users TO att_http_users');
    try {attHttpRequest($a,'GET',503);} finally {$database->exec('RENAME TABLE att_http_users TO users');}
    $phase='invalid connection configuration';attHttpStart('1',true,['DB_PORT'=>'invalid']);attHttpRequest($a,'GET',503);
    $phase='session storage unavailable';attHttpStart('1',true,[],$root.'/missing-session-dir');attHttpRequest($a,'GET',503);
    $phase='dependency recovery';attHttpStart('1',true);attHttpControl();$a=attHttpBrowser($userA);attHttpRequest($a,'GET',200);
    echo "PASS attestation HTTP real rollback, possible-commit failure, explicit evidence recovery and safe dependency/config/session failures\n";

    attHttpStop();$log=file_get_contents($root.'/private-http.log');
    foreach(array_merge($secrets,['SYNTHETIC TEST ONLY','PRIVATE_FAULT_SQL_SENTINEL','PRIVATE_UPLOAD_SENTINEL',getenv('DB_PASSWORD')]) as $secret){attHttpAssert(!str_contains($log,$secret),'Private HTTP log leaked a forbidden value.');}
    attHttpAssert(!preg_match('/SQLSTATE|PDOException|Stack trace|Uncaught|INSERT INTO|SELECT \*/',$log),'HTTP log leaked exception/SQL diagnostics.');
    echo "PASS attestation HTTP private no-store projections, bounded test-only source copies and safe logs\n";
} catch(Throwable $error) {
    $message=get_class($error)===RuntimeException::class ? $error->getMessage() : 'Unexpected '.get_class($error).' at '.$phase.' (source line '.$error->getLine().')';
    if($error instanceof PDOException){$message.=' [SQLSTATE '.$error->getCode().'; driver '.(int)($error->errorInfo[1]??0).']';}
    fwrite(STDERR,'FAIL attestation HTTP: '.$message."\n");$exitCode=1;
} finally {
    attHttpStop();if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}
    if(isset($database)){
        if($database->inTransaction()){$database->rollBack();}
        foreach($users as $id){$database->exec("DELETE FROM onboarding_attestations WHERE user_id=$id; DELETE FROM users WHERE id=$id");}
        if($initial!==null){attHttpAssert($initial===attHttpSnapshot($database),'Preexisting data changed or fixture rows remain.');}
    }
    if($environment instanceof TestEnvironment){$environment->tearDown();attHttpAssert($environment->wasTornDown(),'Private test namespace cleanup failed.');}
}
if($exitCode===0){echo "PASS attestation HTTP all preexisting data and private process/session/source/file/database fixture cleanup\n";}
ob_end_flush();exit($exitCode);
