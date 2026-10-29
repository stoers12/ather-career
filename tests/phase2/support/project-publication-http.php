<?php
declare(strict_types=1);
// Guarded disposable suite only. Reuse the publication suite's private PDO fault
// constructor; application routes/includes/policy resolvers are otherwise exact.
$app=realpath(__DIR__.'/../../..'); $privateApp=$root.'/app'; mkdir($privateApp,0700); mkdir($privateApp.'/config',0700); mkdir($root.'/control',0700);
foreach (['includes','vendor','data','public'] as $directory) { symlink($app.'/'.$directory,$privateApp.'/'.$directory); }
$documentRoot=dirname($privateApp).'/public'; mkdir($documentRoot,0700);
RouteContract::writeEntryScripts($documentRoot);
symlink($app.'/public/assets',$documentRoot.'/assets');
// These fixtures also request the former root-handler URLs.
$routes=require PHASE2_REPOSITORY_ROOT.'/config/routes.php';
foreach ($routes['scripts'] as $entry=>$handler) {
    if ($entry!==basename($handler)) { completionAssert(copy($documentRoot.'/'.$entry,$documentRoot.'/'.basename($handler)),'Legacy fixture entry unavailable.'); }
}
mkdir($privateApp.'/handlers',0700);
foreach (glob($app.'/handlers/*.php') as $file) { copy($file,$privateApp.'/handlers/'.basename($file)); }
foreach (glob($app.'/config/*.php') as $file) { copy($file,$privateApp.'/config/'.basename($file)); }
$configuration=file_get_contents($app.'/config/database.php'); completionAssert(substr_count($configuration,'new PDO(')===1,'PDO fixture seam changed.');
file_put_contents($privateApp.'/config/database.php',str_replace('new PDO(','new PublicationHttpDatabase(',$configuration)."\nrequire_once ".var_export(__DIR__.'/publication-http-database.php',true).";\n");
file_put_contents($root.'/control/mode','');
file_put_contents($root.'/router.php', <<<'PHP'
<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (preg_match('~^/p/([a-z0-9-]+)(?:/(projects\.json|media/project/([0-9]+)))?/?$~',$path,$m)) {
    $_GET['slug']=$m[1]; $file='handlers/public_portfolio.php';
    if (($m[2]??'')==='projects.json') { $file='handlers/public_projects_json.php'; }
    elseif (isset($m[3])) { $file='handlers/public_media.php'; $_GET['type']='project'; $_GET['id']=$m[3]; }
    require __DIR__.'/app/'.$file; return true;
}
return false;
PHP);
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); $port=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1); fclose($socket);
if ($browserEvidence!==null) { $port=8082; }
$env=getenv(); $env['PUBLIC_BASE_URL']='http://127.0.0.1:'.$port; $env['SESSION_COOKIE_SECURE']='0'; $env['ATHERCAR_PUBLICATION_FIXTURE_CONTROL']=$root.'/control';
$server=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/sessions','-S',($browserEvidence===null?'127.0.0.1':'0.0.0.0').':'.$port,'-t',$documentRoot,$root.'/router.php'],
    [0=>['file','/dev/null','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$privateApp,$env);
completionAssert(is_resource($server),'Project HTTP server unavailable.');
$base='http://127.0.0.1:'.$port;
for ($i=0;$i<100;$i++) { $probe=@fsockopen('127.0.0.1',$port); if ($probe) { fclose($probe); break; } usleep(20000); }
if (session_status()===PHP_SESSION_ACTIVE) { session_write_close(); }
ini_set('session.use_cookies','1'); session_name(OWNER_SESSION_NAME); session_id(''); startOwnerSession(); establishVerifiedInternalUserSession($a->userId,1);
$csrf=getCsrfToken(); $cookie=['Name'=>session_name(),'Value'=>session_id()]; session_write_close();
$jar=new GuzzleHttp\Cookie\CookieJar(false,[new GuzzleHttp\Cookie\SetCookie([...$cookie,'Domain'=>'127.0.0.1','Path'=>'/'])]);
$owner=new GuzzleHttp\Client(['base_uri'=>$base,'cookies'=>$jar,'allow_redirects'=>false,'http_errors'=>false,'timeout'=>15]);
$public=new GuzzleHttp\Client(['base_uri'=>$base,'allow_redirects'=>false,'http_errors'=>false,'timeout'=>15]);
$slug=$db->query('SELECT public_slug FROM portfolios WHERE id='.$a->portfolioId)->fetchColumn();
$uri='/p/'.$slug;
$form=['action'=>'set_project_publication','csrf_token'=>$csrf,'id'=>(string)$alpha,'is_published'=>'0'];
$html=projectPublicationHttp($owner,'GET','/owner_projects.php?lang=en',200);
completionAssert(str_contains($html,'<html lang="en"') && str_contains($html,'project-publication-form') && str_contains($html,'Hide project') && str_contains($html,'Show in public Portfolio'),'Project controls missing.');
$checkPublic=static function (bool $visible) use ($public,$uri,$alpha,$hidden): void {
    $html=projectPublicationHttp($public,'GET',$uri,200);
    $json=json_decode(projectPublicationHttp($public,'GET',$uri.'/projects.json',200),true,32,JSON_THROW_ON_ERROR);
    completionAssert(str_contains($html,'Project Alpha')===$visible && !str_contains($html,'Hidden project sentinel'),'HTML exposed unselected project.');
    completionAssert(count($json['projects'])===($visible?1:0),'JSON mixed project count wrong.');
    completionAssert(!str_contains(json_encode($json),'Hidden description sentinel') && !str_contains(json_encode($json),'image_path'),'JSON leaked hidden content/key.');
    projectPublicationHttp($public,'GET',$uri.'/media/project/'.$alpha,$visible?200:404);
    projectPublicationHttp($public,'HEAD',$uri.'/media/project/'.$alpha,$visible?200:404);
    projectPublicationHttp($public,'GET',$uri.'/media/project/'.$hidden,404);
};
$before=profileTestSnapshot($db); $checkPublic(true); completionAssert($before===profileTestSnapshot($db),'Public GET wrote database.');
// A contact-validation error re-renders the same filtered project collection.
$error=projectPublicationHttp($public,'POST','/public_contact.php?slug='.$slug,422,['form_params'=>['name'=>'','email'=>'invalid','message'=>'']]);
completionAssert(str_contains($error,'Project Alpha') && !str_contains($error,'Hidden project sentinel'),'Contact error bypassed project filtering.');
projectPublicationHttp($owner,'GET','/owner_media.php?type=project&id='.$hidden,200);
projectPublicationHttp($owner,'GET','/owner_preview.php',200);
$upload=['action'=>'update','csrf_token'=>$csrf,'id'=>(string)$alpha,'title'=>'Project Alpha','category'=>'Testing','description'=>'Image edit keeps publication','github_url'=>'','technologies'=>'PHP'];
$parts=[]; foreach ($upload as $name=>$value) { $parts[]=['name'=>$name,'contents'=>$value]; }
$parts[]=['name'=>'project_image','contents'=>fopen($app.'/public/assets/images/ather-navbar-logo.png','rb'),'filename'=>'existing-valid.png'];
projectPublicationHttp($owner,'POST','/owner_projects.php',303,['multipart'=>$parts]);
completionAssert($flag($alpha)===1,'Image edit reset project publication.'); $checkPublic(true);
echo "PASS project-publication actual HTML JSON contact-error and existing valid PNG public GET/HEAD200 versus private404; authorized owner preview/media preserved\n";

foreach (['01','true','',null,[],2] as $bad) {
    $invalid=$form; $invalid['is_published']=$bad; $before=profileTestSnapshot($db);
    projectPublicationHttp($owner,'POST','/owner_projects.php',422,['form_params'=>$invalid]);
    completionAssert($before===profileTestSnapshot($db),'Invalid HTTP wrote data.');
}
foreach ([['id'=>(string)$beta],['portfolio_id'=>(string)$b->portfolioId],['csrf_token'=>'bad']] as $change) {
    $before=profileTestSnapshot($db); projectPublicationHttp($owner,'POST','/owner_projects.php',isset($change['id'])||isset($change['csrf_token'])?403:422,['form_params'=>array_replace($form,$change)]);
    completionAssert($before===profileTestSnapshot($db),'Unauthorized HTTP wrote data.');
}
$before=profileTestSnapshot($db); projectPublicationHttp($owner,'GET','/owner_projects.php?action=set_project_publication&id='.$alpha.'&is_published=0',200);
completionAssert($before===profileTestSnapshot($db),'GET published a project.');
projectPublicationHttp($owner,'POST','/owner_projects.php',303,['form_params'=>$form]); $checkPublic(false);
$form['is_published']='1'; projectPublicationHttp($owner,'POST','/owner_projects.php',303,['form_params'=>$form]); $checkPublic(true);
completionSession($a->userId,false); unpublishOwnedPortfolio($db,$a); completionAssert($flag($alpha)===1 && $flag($hidden)===0,'Portfolio hide changed choices.');
projectPublicationHttp($public,'GET',$uri,404); projectPublicationHttp($public,'GET',$uri.'/media/project/'.$alpha,404);
$stale=PublicReadContext::fromPublishedPortfolio($a->portfolioId);
completionAssert(listPublicProjects($db,$stale)===[] && publicMediaDescriptor($db,$stale,'project',$alpha)===null,'Stale public context bypassed parent publication.');
publishOwnedPortfolio($db,$a); $checkPublic(true);
$db->exec("UPDATE users SET account_status='disabled' WHERE id=".$a->userId); projectPublicationHttp($public,'GET',$uri.'/projects.json',404); projectPublicationHttp($public,'GET',$uri.'/media/project/'.$alpha,404);
completionAssert(listPublicProjects($db,$stale)===[] && publicMediaDescriptor($db,$stale,'project',$alpha)===null,'Stale public context bypassed disabled account.');
$db->exec("UPDATE users SET account_status='active' WHERE id=".$a->userId);
echo "PASS project-publication HTTP malformed CSRF cross-owner selector and GET denials; hide/republish retains mixed choices and disabled owner cannot expose media\n";

$form['is_published']='0'; projectPublicationHttp($owner,'POST','/owner_projects.php',303,['form_params'=>$form]);
file_put_contents($root.'/control/mode','after'); $form['is_published']='1';
$body=projectPublicationHttp($owner,'POST','/owner_projects.php',503,['form_params'=>$form]); file_put_contents($root.'/control/mode','');
completionAssert($flag($alpha)===1 && str_contains($body,'may already be saved'),'HTTP lost COMMIT was compensated/replayed/misreported.');
$checkPublic(true); projectPublicationHttp($owner,'GET','/owner_projects.php',200);
echo "PASS project-publication real HTTP lost-COMMIT503 retains committed choice and explicit owner recovery without replay\n";

if ($browserEvidence!==null) {
    completionAssert(is_dir($browserEvidence) && ($browserEvidence==='/browser' || $browserEvidence==='/evidence'),'Unsafe browser evidence directory.');
    $carrier=$browserEvidence.'/private-project-context.json';
    file_put_contents($carrier,json_encode(['cookie'=>$cookie,'slug'=>$slug,'alpha'=>$alpha,'hidden'=>$hidden],JSON_THROW_ON_ERROR)); chmod($carrier,0600);
    $deadline=microtime(true)+240;
    while (!is_file($browserEvidence.'/stop-project') && microtime(true)<$deadline) { usleep(100000); }
    completionAssert(is_file($browserEvidence.'/stop-project'),'Project browser timed out.'); unlink($carrier);
}
