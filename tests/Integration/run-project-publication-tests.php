<?php
declare(strict_types=1);
require_once __DIR__.'/../../tests/Support/profile-version-fixtures.php';
require_once __DIR__.'/../../tests/Support/completion-fixtures.php';
require_once __DIR__.'/../../tests/Support/publication-mutation-fixtures.php';
require_once __DIR__.'/../../includes/media_access.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/owner_session.php';
require_once __DIR__.'/../../vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

function projectPublicationContent(PDO $db): array
{
    $snapshot=[];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows=$db->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC); $encoded=[];
        foreach ($rows as $row) {
            if ($table==='schema_migrations' && $row['version']==='022') { continue; }
            if ($table==='projects') { unset($row['is_published']); }
            ksort($row); $encoded[]=serialize($row);
        }
        sort($encoded); $snapshot[$table]=$encoded;
    }
    ksort($snapshot); return $snapshot;
}
function projectPublicationReset(PDO $db): void
{
    $db->exec("DELETE FROM schema_migrations WHERE version='022'");
    $db->exec('ALTER TABLE projects DROP CHECK chk_projects_is_published, DROP COLUMN is_published');
}
function projectPublicationHttp(Client $client, string $method, string $uri, int $status, array $options=[]): string
{
    $response=$client->request($method,$uri,$options);
    completionAssert($response->getStatusCode()===$status,'Project HTTP '.$method.' '.$uri.' got '.$response->getStatusCode().' expected '.$status);
    completionAssert(str_contains($response->getHeaderLine('Cache-Control'),'no-store'),'Project HTTP lost no-store.');
    return (string)$response->getBody();
}
ob_start();
$db=getDatabaseConnection();
completionAssert(is_file('/.dockerenv') && $db->query("SELECT version FROM schema_migrations WHERE version='022'")->fetchColumn()==='022','Requires migrated disposable database.');
$initial=profileTestSnapshot($db); $originalFlags=$db->query('SELECT id,is_published FROM projects')->fetchAll(PDO::FETCH_ASSOC);
$originalLedger=$db->query("SELECT applied_at FROM schema_migrations WHERE version='022'")->fetchColumn();
$originalSeal=$db->query('SELECT * FROM owner_journey_cutover')->fetchAll(PDO::FETCH_ASSOC);
$environment=TestEnvironment::create(); $root=$environment->namespaceRoot; $users=[]; $projects=[]; $contexts=[]; $workers=[]; $server=null; $exit=0; $stage='setup';
$browserEvidence=getenv('ATHERCAR_PROJECT_PUBLICATION_BROWSER_EVIDENCE')?:null;
mkdir($root.'/sessions',0700); session_save_path($root.'/sessions'); session_start(['use_cookies'=>0,'cache_limiter'=>'']);
try {
    for ($i=0;$i<2;$i++) {
        $user=completionNewUser($db); $users[]=$user;
        $db->prepare('INSERT INTO portfolios(owner_user_id,public_slug,is_published) VALUES (?,?,?)')->execute([$user,'project-test-'.bin2hex(random_bytes(5)),$i===0?1:0]);
        $portfolio=(int)$db->lastInsertId(); journeyFixtureLegacyOwner($db,$user);
        $context=publicationContext($user,$portfolio); $contexts[]=$context;
        $db->prepare("INSERT INTO personal_info(portfolio_id,full_name) VALUES (?,'Synthetic project owner')")->execute([$portfolio]);
        $projects[]=createAuthorizedProject($db,$context,'Project '.($i===0?'Alpha':'Beta'),'Testing','Project description',null,null,[]);
    }
    [$a,$b]=$contexts; [$alpha,$beta]=$projects;
    $flag=static fn(int $id):int=>(int)$db->query('SELECT is_published FROM projects WHERE id='.$id)->fetchColumn();
    completionAssert($flag($alpha)===0 && $flag($beta)===0,'New project not private.');
    echo "PASS project-publication fresh migrated creation defaults private for published and private Portfolios\n";

    $stage='migration';
    foreach ([null,'after-project-publication-add','after-project-publication-backfill','after-project-publication-finalize'] as $fault) {
        $db->exec('UPDATE portfolios SET is_published=1 WHERE id='.$a->portfolioId);
        $db->exec('UPDATE portfolios SET is_published=0 WHERE id='.$b->portfolioId);
        projectPublicationReset($db); $content=projectPublicationContent($db);
        if ($fault!==null) { profileTestMigrate(false,$fault); }
        if ($fault==='after-project-publication-add') {
            // Initialized choices are never re-derived even in exact staging schema.
            $db->exec('UPDATE projects SET is_published=0 WHERE id='.$alpha);
        }
        profileTestMigrate(true);
        completionAssert(projectPublicationContent($db)===$content,'Migration changed preexisting named values/timestamps/ownership.');
        completionAssert($flag($alpha)===($fault==='after-project-publication-add'?0:1) && $flag($beta)===0,'Legacy transition did not preserve initialized choice.');
        $db->exec('UPDATE projects SET is_published=0 WHERE id='.$alpha);
        $db->exec('UPDATE projects SET is_published=1 WHERE id='.$beta);
        // Lost ledger acknowledgement must preserve later choices, even after parent changes.
        $db->exec("DELETE FROM schema_migrations WHERE version='022'");
        profileTestMigrate(true); $before=profileTestSnapshot($db); profileTestMigrate(true);
        completionAssert($flag($alpha)===0 && $flag($beta)===1 && $before===profileTestSnapshot($db),'Retry overwrote choices or ledger/data.');
    }
    $before=profileTestSnapshot($db);
    completionDenied($db,fn()=>$db->exec('UPDATE projects SET is_published=2 WHERE id='.$alpha),PDOException::class);
    $db->exec('ALTER TABLE projects ALTER CHECK chk_projects_is_published NOT ENFORCED');
    profileTestMigrate(false); $db->exec('ALTER TABLE projects ALTER CHECK chk_projects_is_published ENFORCED');
    $db->exec('ALTER TABLE projects MODIFY is_published TINYINT(1) NOT NULL DEFAULT 1');
    profileTestMigrate(false); $db->exec('ALTER TABLE projects MODIFY is_published TINYINT(1) NOT NULL DEFAULT 0');
    completionAssert($before===profileTestSnapshot($db),'Rejected schema modified row values.');
    echo "PASS project-publication populated upgrade repeat interrupted add/backfill/finalize lost-ledger recovery strict enforced boolean/default and exact protected-data preservation\n";

    $stage='authorization'; completionSession($a->userId,false);
    foreach ([null,true,'1','01',[],2,-1] as $bad) { completionDenied($db,fn()=>setOwnedProjectPublication($db,$a,$alpha,$bad),PublicLifecycleValidationException::class); }
    completionDenied($db,fn()=>setOwnedProjectPublication($db,$a,$beta,1),AuthorizationDeniedException::class);
    foreach (['revoked','disabled','expired','other'] as $mode) {
        completionSession($a->userId,false);
        if ($mode==='revoked') { $db->exec('UPDATE users SET authz_version=2 WHERE id='.$a->userId); }
        if ($mode==='disabled') { $db->exec("UPDATE users SET account_status='disabled' WHERE id=".$a->userId); }
        if ($mode==='expired') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at']=time()-INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS-60; $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at']=time()-INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS-1; }
        if ($mode==='other') { completionSession($b->userId,false); }
        completionDenied($db,fn()=>setOwnedProjectPublication($db,$a,$alpha,1),AuthorizationDeniedException::class);
        $db->exec("UPDATE users SET authz_version=1,account_status='active' WHERE id=".$a->userId);
    }
    completionSession($a->userId,false); $db->beginTransaction();
    $db->exec("UPDATE projects SET description='Caller work' WHERE id=$alpha");
    completionDenied($db,fn()=>setOwnedProjectPublication($db,$a,$alpha,1),LogicException::class);
    completionAssert($db->inTransaction(),'Helper touched ambient ownership.'); $db->rollBack();
    $transfer=completionNewUser($db); $users[]=$transfer;
    $db->exec('UPDATE portfolios SET owner_user_id='.$transfer.' WHERE id='.$a->portfolioId);
    completionDenied($db,fn()=>setOwnedProjectPublication($db,$a,$alpha,1),AuthorizationDeniedException::class);
    $db->exec('UPDATE portfolios SET owner_user_id='.$a->userId.' WHERE id='.$a->portfolioId);
    completionSession($a->userId,false); setOwnedProjectPublication($db,$a,$alpha,1); $before=profileTestSnapshot($db);
    setOwnedProjectPublication($db,$a,$alpha,1); completionAssert($before===profileTestSnapshot($db),'Same-value action churned timestamp.');
    $row=findAuthorizedProject($db,$a,$alpha);
    updateAuthorizedProject($db,$a,$alpha,$row['title'],$row['category'],'Updated description',null,null,['PHP']);
    completionAssert($flag($alpha)===1,'Content/image edit reset publication.');
    echo "PASS project-publication strict intent current-session ownership expired revoked disabled cross-account and ambient denial; same-value no-op and content preservation\n";

    $stage='uncertainty';
    foreach (['before','false','after','rollback','statement'] as $fault) {
        completionSession($a->userId,false); setOwnedProjectPublication($db,$a,$alpha,0);
        $connection=completionConnection(PublicationFixtureDatabase::class); $connection->fault=$fault; $caught=false;
        try { setOwnedProjectPublication($connection,$a,$alpha,1); }
        catch (Throwable $error) { $caught=$fault==='statement'?$error instanceof PDOException:$error instanceof PublicLifecycleWriteUncertainException; }
        completionAssert($caught && $flag($alpha)===($fault==='after'?1:0),'Commit outcome replayed, compensated or misclassified.');
    }
    echo "PASS project-publication real statement rollback and before/false/after/rollback-ack uncertainty preserve actual committed choice without compensation\n";

    $stage='concurrency'; completionSession($a->userId,false); setOwnedProjectPublication($db,$a,$alpha,0);
    $input=['user'=>$a->userId,'portfolio'=>$a->portfolioId,'action'=>'project','slug'=>['id'=>$alpha,'value'=>1],'pause'=>'after_portfolio','snapshot'=>true];
    $worker=publicationWorker($input); $workers[]=$worker; publicationBarrier($worker,'after_portfolio');
    publicationLockBlocked(completionConnection(),'UPDATE users SET authz_version=2 WHERE id=?',[$a->userId]);
    publicationLockBlocked(completionConnection(),'UPDATE portfolios SET is_published=0 WHERE id=?',[$a->portfolioId]);
    $next=publicationWorker(['user'=>$a->userId,'portfolio'=>$a->portfolioId,'action'=>'project','slug'=>['id'=>$alpha,'value'=>0],'pause'=>'before_user']); $workers[]=$next;
    publicationBarrier($next,'before_user'); publicationRelease($next); publicationRelease($worker);
    completionAssert(publicationFinish($worker)==='saved' && publicationFinish($next)==='saved' && $flag($alpha)===0,'Serialized explicit choices lost order.'); $workers=[];
    // Parent visibility and child choice share the same actual lock boundary.
    $worker=publicationWorker($input); $workers[]=$worker; publicationBarrier($worker,'after_portfolio');
    $next=publicationWorker(['user'=>$a->userId,'portfolio'=>$a->portfolioId,'action'=>'unpublish','pause'=>'before_user']); $workers[]=$next;
    publicationBarrier($next,'before_user'); publicationRelease($next); publicationRelease($worker);
    completionAssert(publicationFinish($worker)==='saved' && publicationFinish($next)==='saved' && $flag($alpha)===1,'Portfolio race changed project choice.'); $workers=[];
    completionSession($a->userId,false); publishOwnedPortfolio($db,$a);
    echo "PASS project-publication measured User/Portfolio locks and two-worker explicit choice plus Portfolio unpublish serialization\n";

    $stage='http and valid image';
    $key=copyFileToPrivateMedia(__DIR__.'/../../public/assets/images/ather-navbar-logo.png',$a->portfolioId,'projects',createManagedUploadFilename('project','png'));
    completionAssert($key!==null && generateProjectPresentationImage($key,$a->portfolioId)!==null,'Existing valid PNG fixture unavailable.');
    $db->prepare('UPDATE projects SET image_path=?,is_published=1 WHERE id=?')->execute([$key,$alpha]);
    $hidden=createAuthorizedProject($db,$a,'Hidden project sentinel','Testing','Hidden description sentinel',null,$key,[]); $projects[]=$hidden;
    require __DIR__.'/../../tests/Support/project-publication-http.php';
} catch (Throwable $error) {
    fwrite(STDERR,'FAIL project-publication '.$stage.' '.get_class($error).': '.$error->getMessage()."\n"); $exit=1;
} finally {
    foreach ($workers as [$process,$pipes]) { foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } } if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (session_status()===PHP_SESSION_ACTIVE) { session_write_close(); }
    foreach ($projects as $id) { $db->exec('DELETE FROM projects WHERE id='.$id); }
    completionCleanup($db,$users); $db->exec('DELETE FROM owner_journey_cutover');
    foreach ($originalSeal as $row) { $db->prepare('INSERT INTO owner_journey_cutover (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row)); }
    foreach ($originalFlags as $row) { $db->prepare('UPDATE projects SET is_published=? WHERE id=?')->execute([$row['is_published'],$row['id']]); }
    $db->prepare("UPDATE schema_migrations SET applied_at=? WHERE version='022'")->execute([$originalLedger]);
    if ($browserEvidence!==null) { foreach (['private-project-context.json','stop-project'] as $file) { if (is_file($browserEvidence.'/'.$file)) { unlink($browserEvidence.'/'.$file); } } }
    $environment->tearDown();
    if ($exit===0) { completionAssert($initial===profileTestSnapshot($db),'Project publication fixture changed existing rows/ledger.'); }
}
if ($exit===0) { echo "PASS project-publication full preexisting row ledger seal media/session/process fixture preservation and cleanup\n"; }
ob_end_flush(); exit($exit);
