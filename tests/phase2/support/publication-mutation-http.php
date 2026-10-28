<?php
declare(strict_types=1);
// Included by the bounded mutation suite after its synthetic cohort setup.
$app = realpath(__DIR__.'/../../..'); $privateApp = $root.'/http-app'; mkdir($privateApp,0700);
// Only database construction is instrumented. The real route, includes, policy
// resolvers and all application behavior remain byte-identical to this checkout.
foreach (['includes','vendor','data','public'] as $directory) {
    if (is_dir($app.'/'.$directory)) { symlink($app.'/'.$directory,$privateApp.'/'.$directory); }
}
symlink($app.'/public/assets',$privateApp.'/assets');
mkdir($privateApp.'/config',0700);
foreach (glob($app.'/config/*.php') as $file) { copy($file,$privateApp.'/config/'.basename($file)); }
foreach (glob($app.'/*.php') as $file) { copy($file,$privateApp.'/'.basename($file)); }
$config = file_get_contents($app.'/config/database.php');
completionAssert(substr_count($config,'new PDO(')===1,'Private PDO seam changed.');
$changed = str_replace('new PDO(', 'new PublicationHttpDatabase(', $config);
$changed .= "\nrequire_once ".var_export(__DIR__.'/publication-http-database.php',true).";\n";
file_put_contents($privateApp.'/config/database.php',$changed);
completionAssert(hash_file('sha256',$privateApp.'/owner_publication.php')===hash_file('sha256',$app.'/owner_publication.php'),'Route fixture differs from real route.');
mkdir($root.'/control',0700); file_put_contents($root.'/control/mode','');
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); $address=stream_socket_get_name($socket,false); fclose($socket);
$port=(int)substr($address,strrpos($address,':')+1);
$env=getenv(); $env['SESSION_COOKIE_SECURE']='0'; $env['PUBLIC_BASE_URL']='https://publication-test.invalid';
$env['RATE_LIMIT_STATE_DIR']=$root.'/limits'; $env['ATHERCAR_PUBLICATION_FIXTURE_CONTROL']=$root.'/control';
foreach (['REGISTRATION','ONBOARDING_DRAFT_HTTP','ONBOARDING_ATTESTATION_HTTP','ONBOARDING_COMPLETION_HTTP'] as $gate) { $env['ATHERCAR_'.$gate.'_ENABLED']='0'; }
$server=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/sessions','-d','display_errors=0','-S','127.0.0.1:'.$port,'-t',$privateApp],
    [0=>['pipe','r'],1=>['file',$root.'/private-http.log','a'],2=>['file',$root.'/private-http.log','a']],$pipes,null,$env);
completionAssert(is_resource($server),'HTTP server unavailable.'); fclose($pipes[0]);
$ready=false;
for ($i=0;$i<80;$i++) { $probe=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,.1); if (is_resource($probe)) { fclose($probe); $ready=true; break; } usleep(25000); }
completionAssert($ready,'HTTP server readiness failed.');

function publicationHttpSession(AuthorizedPortfolioContext $context, ?callable $alter = null): array
{
    global $port;
    if (session_status()===PHP_SESSION_ACTIVE) { session_write_close(); }
    ini_set('session.use_cookies','1');
    session_name(OWNER_SESSION_NAME); session_id(''); startOwnerSession();
    establishVerifiedInternalUserSession($context->userId,1); $csrf=getCsrfToken();
    if ($alter!==null) { $alter(); }
    $sid=session_id(); session_write_close();
    $client=new GuzzleHttp\Client(['base_uri'=>'http://127.0.0.1:'.$port,
        'cookies'=>GuzzleHttp\Cookie\CookieJar::fromArray([OWNER_SESSION_NAME=>$sid],'127.0.0.1'),
        'http_errors'=>false,'allow_redirects'=>false,'timeout'=>18]);
    return [$client,$csrf,$sid];
}

$anonymous=new GuzzleHttp\Client(['base_uri'=>'http://127.0.0.1:'.$port,'http_errors'=>false,'allow_redirects'=>false]);
$before=completionSnapshot($db); publicationHttp($anonymous,['action'=>'publish'],303,'/owner_login.php');
completionAssert($before===completionSnapshot($db),'Anonymous HTTP wrote data.');
foreach ([static function (): void { $_SESSION[INTERNAL_USER_SESSION_KEY]['authz_version']='invalid'; },
    static function (): void {
        $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at']=time()-INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS-60;
        $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at']=time()-INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS-1;
    }] as $alter) {
    [$client,$csrf]=publicationHttpSession($a,$alter); $before=completionSnapshot($db);
    // Malformed state has the existing login redirect; a well-shaped expired session is denied.
    $invalid=currentInternalUserSession()===null;
    publicationHttp($client,['action'=>'publish','csrf_token'=>$csrf],$invalid?303:403,$invalid?'/owner_login.php':null);
    completionAssert($before===completionSnapshot($db),'Invalid HTTP session wrote data.');
}
[$client,$csrf]=publicationHttpSession($bare); $before=completionSnapshot($db);
publicationHttp($client,['action'=>'publish','csrf_token'=>$csrf],303,'/owner_onboarding.php');
completionAssert($before===completionSnapshot($db),'Unproven HTTP cohort wrote data.');
foreach ([$a,$completed] as $context) {
    [$client,$csrf]=publicationHttpSession($context); $before=completionSnapshot($db);
    publicationHttp($client,['action'=>'publish','csrf_token'=>'invalid'],403);
    completionAssert($before===completionSnapshot($db),'CSRF denial wrote data.');
    publicationHttp($client,['action'=>'publish','csrf_token'=>$csrf,'portfolio_id'=>$b->portfolioId,'owner_user_id'=>$b->userId],303,'owner_publication.php?published=1');
    completionAssert(ownedPublicLifecycleState($db,$context)['is_published']===1 && ownedPublicLifecycleState($db,$b)['is_published']===0,'HTTP selector changed authority.');
    publicationHttp($client,['action'=>'unpublish','csrf_token'=>$csrf],303,'owner_publication.php?unpublished=1');
}
echo "PASS publication real HTTP absent invalid expired unproven CSRF completed/legacy fixed-resource publish/unpublish with gates off\n";

// Entry was authorized, then another connection commits revocation/ownership
// before the helper's first lock. The real HTTP route must safely return403.
foreach (['revocation','ownership'] as $change) {
    [$client,$csrf,$sid]=publicationHttpSession($fresh);
    file_put_contents($root.'/control/mode','barrier');
    $carrier=$root.'/private-request.json'; file_put_contents($carrier,json_encode(['port'=>$port,'session'=>$sid,'csrf'=>$csrf,'action'=>'publish'],JSON_THROW_ON_ERROR)); chmod($carrier,0600);
    $p=proc_open([PHP_BINARY,__DIR__.'/publication-http-client.php',$carrier],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes); fclose($pipes[0]);
    $workers[]=[$p,$pipes]; $deadline=microtime(true)+10;
    while (!is_file($root.'/control/admitted') && microtime(true)<$deadline) { usleep(10000); }
    completionAssert(is_file($root.'/control/admitted'),'Real HTTP admission barrier missing.');
    if ($change==='revocation') { $db->exec('UPDATE users SET authz_version=2 WHERE id='.$fresh->userId); }
    else { $db->exec('UPDATE portfolios SET owner_user_id='.$newOwner.' WHERE id='.$fresh->portfolioId); }
    $before=completionSnapshot($db); file_put_contents($root.'/control/release','go');
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    completionAssert(proc_close($p)===0 && $err==='' && json_decode($out,true)===['status'=>403,'location'=>''],'Changed HTTP authority did not safely deny.');
    completionAssert($before===completionSnapshot($db),'Changed authority HTTP wrote data.');
    $db->exec('UPDATE users SET authz_version=1 WHERE id='.$fresh->userId);
    $db->exec('UPDATE portfolios SET owner_user_id='.$fresh->userId.' WHERE id='.$fresh->portfolioId);
    unlink($root.'/control/admitted'); unlink($root.'/control/release'); unlink($carrier);
}
echo "PASS publication real admitted HTTP concurrent revocation and ownership change return403 without mutation\n";

foreach (['statement','after'] as $mode) {
    completionSession($fresh->userId); unpublishOwnedPortfolio($db,$fresh);
    [$client,$csrf]=publicationHttpSession($fresh); $before=completionSnapshot($db);
    file_put_contents($root.'/control/mode',$mode);
    $body=publicationHttp($client,['action'=>'publish','csrf_token'=>$csrf],503);
    if ($mode==='statement') { completionAssert($before===completionSnapshot($db),'HTTP rollback changed data.'); }
    else {
        completionAssert(str_contains($body,'may already be saved') && ownedPublicLifecycleState($db,$fresh)['is_published']===1,'HTTP uncertain result concealed or compensated commit.');
        // Explicit authorized GET uses the ordinary reader, without replaying POST.
        file_put_contents($root.'/control/mode','');
        $response=$client->get('/owner_publication.php?lang=en');
        completionAssert($response->getStatusCode()===200 && $response->getHeaderLine('Content-Language')==='en' && str_contains((string)$response->getBody(),'Your Portfolio is live'),'Authorized state recovery failed.');
    }
}
file_put_contents($root.'/control/mode','');
[$client,$csrf]=publicationHttpSession($b); $before=completionSnapshot($db);
$limited=false;
for ($i=0;$i<35;$i++) {
    $response=$client->post('/owner_publication.php',['form_params'=>['action'=>'unpublish','csrf_token'=>$csrf]]);
    if ($response->getStatusCode()===429) { $limited=true; completionAssert($response->hasHeader('Retry-After'),'Rate limit lost retry header.'); break; }
    completionAssert($response->getStatusCode()===303,'Unexpected rate-limit exercise result.');
}
completionAssert($limited && $before===completionSnapshot($db),'Limiter absent or idempotent requests changed data.');
echo "PASS publication real HTTP rollback uncertain COMMIT explicit authorized GET recovery and rate limit\n";
