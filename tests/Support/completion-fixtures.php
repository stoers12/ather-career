<?php
declare(strict_types=1);

// Synthetic policies exist only in an explicitly loaded, guarded CLI fixture.
// Production files/resolver accept no injectable bundle, env fake or test switch.
if (PHP_SAPI!=='cli' || !is_file('/.dockerenv') || getenv('APP_ENV')!=='test' || getenv('ATHERCAR_TEST_MODE')!=='1'
    || preg_match('/\Aather_career_test_[a-f0-9]{24}\z/D',(string)getenv('DB_NAME'))!==1
    || getenv('DB_NAME')!==getenv('ATHERCAR_TEST_DB_NAME')) { exit(2); }

function completionFixtureLoadCore(bool $synthetic): void
{
    $includes=realpath(__DIR__.'/../../includes');
    require_once __DIR__.'/../../config/database.php';
    if (!$synthetic) { require_once $includes.'/onboarding_completion.php'; return; }
    $root=sys_get_temp_dir().'/completion-source-'.bin2hex(random_bytes(12)); mkdir($root,0700);
    register_shutdown_function(static function () use ($root): void { foreach (['completion.php','attestations.php'] as $name) { if (is_file($root.'/'.$name)) { unlink($root.'/'.$name); } } rmdir($root); });
    $attestation=file_get_contents($includes.'/onboarding_attestations.php');
    $needle="function currentOnboardingAttestationBundle(): ?OnboardingAttestationBundle\n{\n    return null;\n}";
    $attestation=str_replace("\r\n","\n",$attestation);
    if (substr_count($attestation,$needle)!==1) { throw new RuntimeException('Synthetic resolver seam no longer matches production.'); }
    $attestation=str_replace($needle,"function currentOnboardingAttestationBundle(): ?OnboardingAttestationBundle\n{\n    return completionFixtureCurrentBundle();\n}",$attestation);
    $attestation=str_replace('__DIR__',var_export($includes,true),$attestation);
    file_put_contents($root.'/attestations.php',$attestation); chmod($root.'/attestations.php',0600);
    $originalCore=file_get_contents($includes.'/onboarding_completion.php');
    $core=$originalCore;
    $core=str_replace("require_once __DIR__ . '/onboarding_attestations.php';",'require_once '.var_export($root.'/attestations.php',true).';',$core);
    $core=str_replace('__DIR__',var_export($includes,true),$core);
    file_put_contents($root.'/completion.php',$core); chmod($root.'/completion.php',0600);
    if (explode('const ONBOARDING_COMPLETION_CONTRACT',$core,2)[1]!==explode('const ONBOARDING_COMPLETION_CONTRACT',$originalCore,2)[1]) { throw new RuntimeException('Test copy changed completion behavior.'); }
    require_once $root.'/completion.php';
    $GLOBALS['completion_fixture_bundle']=completionFixtureBundle();
}

function completionFixtureCurrentBundle(): ?OnboardingAttestationBundle { return $GLOBALS['completion_fixture_bundle']??null; }
function completionFixtureBundle(string $version='test-v1', string $language='en'): OnboardingAttestationBundle
{
    $doc=static fn(string $kind): OnboardingDocumentDescriptor=>new OnboardingDocumentDescriptor('test-'.$kind,$version,hash('sha256','SYNTHETIC ONLY:'.$kind.':'.$version.':'.$language),$language);
    return new OnboardingAttestationBundle($doc('age'),$doc('terms'),$doc('privacy'));
}
function completionAssert(bool $condition,string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function completionSnapshot(PDO $db): array
{
    $snapshot=[];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC)); sort($rows); $snapshot[$table]=hash('sha256',serialize($rows));
    }
    ksort($snapshot); return $snapshot;
}
function completionDenied(PDO $db, callable $operation,string $type): void
{
    $before=completionSnapshot($db); $caught=false;
    try { $operation(); } catch (Throwable $error) { $caught=true; completionAssert($error instanceof $type,'Unexpected denial class '.get_class($error)); }
    completionAssert($caught && $before===completionSnapshot($db),'Rejected completion changed protected data.');
}
function completionSessionRoot(): string
{
    $root=sys_get_temp_dir().'/completion-session-'.bin2hex(random_bytes(12)); mkdir($root,0700); session_save_path($root);
    session_start(['use_cookies'=>0,'cache_limiter'=>'']); return $root;
}
function completionSession(int $userId,bool $ready=true): AuthenticatedUserContext
{
    if (session_status()!==PHP_SESSION_ACTIVE) { session_id(''); session_start(['use_cookies'=>0,'cache_limiter'=>'']); }
    $_SESSION=[INTERNAL_USER_SESSION_KEY=>['internal_user_id'=>$userId,'authz_version'=>1,'authenticated_at'=>time(),'last_activity_at'=>time()]];
    recordOwnerReadinessObservation($userId,1,$ready,true);
    return AuthenticatedUserContext::fromValidatedUser($userId);
}
function completionEndSession(string $root): void
{
    if (session_status()===PHP_SESSION_ACTIVE) { $_SESSION=[]; session_destroy(); }
    foreach (glob($root.'/sess_*')?:[] as $file) { unlink($file); } rmdir($root);
}
function completionNewUser(PDO $db): int
{
    $db->prepare("INSERT INTO users (oidc_issuer,oidc_subject,account_status,authz_version) VALUES ('https://completion.invalid/',?,'active',1)")->execute([bin2hex(random_bytes(16))]); return (int)$db->lastInsertId();
}
function completionFields(?string $slug=null): array
{
    return ['display_name'=>'  مومن Example  ','country_code'=>'JO','preferred_language'=>'ar','professional_status'=>'other',
        'specialization'=>'  تخصص é 😀  ','headline'=>'Private final headline','slug_candidate'=>$slug??'completion-'.bin2hex(random_bytes(8)),
        'name_ar'=>'Private variant','name_en'=>'Private variant EN','city'=>'Private city','university'=>'Private university',
        'graduation_year'=>'0000','biography'=>"Private\nbiography",'phone'=>str_repeat('1',40)];
}
function completionSeed(PDO $db,int $userId,array $fields): AuthenticatedUserContext
{
    $context=completionSession($userId);
    foreach (onboardingDraftFields() as $section=>$rules) {
        $version=readOnboardingDraft($db,$context)['version'];
        saveOnboardingDraft($db,$context,$version,$version.':'.bin2hex(random_bytes(16)),$section,array_intersect_key($fields,$rules));
    }
    recordOnboardingAttestations($db,$context,completionFixtureBundle(),['age_18_or_older'=>true,'terms_accepted'=>true,'privacy_notice_acknowledged'=>true]);
    return $context;
}
function completionIntent(array $review): array
{
    return ['expectedVersion'=>$review['expectedVersion'],'operationId'=>$review['expectedVersion'].':'.bin2hex(random_bytes(16)),
        'reviewToken'=>$review['reviewToken'],'confirm_private_creation'=>true,'acknowledge_private_draft'=>true];
}
function completionCleanup(PDO $db,array $users): void
{
    if ($db->inTransaction()) { $db->rollBack(); }
    foreach ($users as $id) {
        $db->exec("DELETE FROM onboarding_completions WHERE user_id=$id");
        $db->exec("DELETE FROM personal_info WHERE portfolio_id IN (SELECT id FROM portfolios WHERE owner_user_id=$id)");
        $db->exec("DELETE FROM portfolios WHERE owner_user_id=$id; DELETE FROM onboarding_drafts WHERE user_id=$id; DELETE FROM onboarding_attestations WHERE user_id=$id; DELETE FROM users WHERE id=$id");
    }
}
function completionMigrate(bool $success,?string $failure=null): void
{
    $env=getenv(); unset($env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER']);
    if ($failure!==null) { $env['ATHERCAR_TEST_MIGRATION_FAIL_AFTER']=$failure; }
    $p=proc_open([PHP_BINARY,__DIR__.'/../../database/migrate.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    completionAssert(is_resource($p),'Migration worker unavailable'); fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    completionAssert((proc_close($p)===0)===$success,'Unexpected migration result: '.$out.$err);
}
function completionConnection(string $class=PDO::class): PDO
{
    return new $class('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_NAME').';charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function completionRace(array $inputs): array
{
    $workers=[];
    try {
        foreach ($inputs as $input) {
            $p=proc_open([PHP_BINARY,__DIR__.'/../../tests/Integration/run-onboarding-completion-race-worker.php',base64_encode(json_encode($input,JSON_THROW_ON_ERROR))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            completionAssert(is_resource($p),'Completion worker unavailable'); stream_set_timeout($pipes[1],30); $workers[]=[$p,$pipes];
        }
        foreach ($workers as [$p,$pipes]) { completionAssert(fgets($pipes[1])==="READY\n",'Completion worker barrier failed'); }
        foreach ($workers as [$p,$pipes]) { fwrite($pipes[0],"GO\n"); fclose($pipes[0]); }
        $results=[];
        foreach ($workers as [$p,$pipes]) {
            $results[]=json_decode(stream_get_contents($pipes[1]),true,32,JSON_THROW_ON_ERROR);
            completionAssert(stream_get_contents($pipes[2])==='','Completion worker emitted diagnostics'); fclose($pipes[1]); fclose($pipes[2]); completionAssert(proc_close($p)===0,'Completion worker exit failed');
        }
        return $results;
    } finally { foreach ($workers as [$p,$pipes]) { foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } } if (is_resource($p)) { proc_terminate($p); proc_close($p); } } }
}
