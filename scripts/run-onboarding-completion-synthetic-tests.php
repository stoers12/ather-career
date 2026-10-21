<?php
declare(strict_types=1);
require_once __DIR__.'/../tests/phase2/support/completion-fixtures.php';
completionFixtureLoadCore(true);
require_once __DIR__.'/../tests/phase2/support/owner-journey-fixtures.php';
require_once __DIR__.'/../includes/profile_writes.php';

final class CompletionFaultDatabase extends PDO
{
    public int $point=0;
    private int $writes=0;
    public string $commitMode='normal';
    public bool $failRollback=false;
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if (preg_match('/\AINSERT INTO (?:portfolios|personal_info|onboarding_completions)\b/',$query)===1) {
            ++$this->writes;
            if ($this->point===$this->writes) { $this->exec('SELECT * FROM completion_injected_missing_table'); }
        }
        if ($this->point===4 && $this->writes===3 && str_starts_with($query,'SELECT * FROM onboarding_completions')) { $this->exec('SELECT * FROM completion_injected_missing_table'); }
        return parent::prepare($query,$options);
    }
    public function commit(): bool
    {
        if ($this->commitMode==='before') { throw new PDOException('Synthetic unacknowledged commit'); }
        $result=parent::commit();
        if ($this->commitMode==='lost') { throw new PDOException('Synthetic lost commit acknowledgement'); }
        if ($this->commitMode==='false') { return false; }
        return $result;
    }
    public function rollBack(): bool
    {
        if ($this->failRollback) { throw new PDOException('Synthetic rollback acknowledgement failure'); }
        return parent::rollBack();
    }
    public function confirmedTestRollback(): void { parent::rollBack(); }
}
final class CompletionRevokeDatabase extends PDO
{
    public int $revokeUser=0;
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if ($this->revokeUser>0 && str_contains($query,'FROM users') && str_contains($query,'FOR UPDATE')) {
            $other=completionConnection(); $other->exec('UPDATE users SET authz_version=2 WHERE id='.$this->revokeUser); $this->revokeUser=0;
        }
        return parent::prepare($query,$options);
    }
}
ob_start(); $db=getDatabaseConnection(); $initial=completionSnapshot($db); $ledgerTime=$db->query("SELECT applied_at FROM schema_migrations WHERE version='019'")->fetchColumn(); $root=completionSessionRoot(); $users=[]; $exit=0; $stage='authority';
$new=static function (?array $fields=null) use ($db,&$users): array {
    $id=completionNewUser($db); $users[]=$id; $context=completionSeed($db,$id,$fields??completionFields());
    return [$id,$context];
};
$review=static fn(AuthenticatedUserContext $context): array=>completionIntent(prepareOnboardingCompletionReview($db,$context,3));
try {
    [$id,$context]=$new(); $validSession=$_SESSION;
    foreach (['missing','false','numeric','mismatch','malformed','future'] as $case) {
        $_SESSION=$validSession;
        if ($case==='missing') { unset($_SESSION[OWNER_READINESS_SESSION_KEY]); }
        if ($case==='false') { $_SESSION[OWNER_READINESS_SESSION_KEY]['email_verified']=false; }
        if ($case==='numeric') { $_SESSION[OWNER_READINESS_SESSION_KEY]['email_verified']=1; }
        if ($case==='mismatch') { $_SESSION[OWNER_READINESS_SESSION_KEY]['internal_user_id']=$id+1; }
        if ($case==='malformed') { $_SESSION[OWNER_READINESS_SESSION_KEY]['observed_at']='1'; }
        if ($case==='future') { $_SESSION[OWNER_READINESS_SESSION_KEY]['observed_at']=time()+1000; }
        completionDenied($db,fn()=>prepareOnboardingCompletionReview($db,$context,3),AuthorizationDeniedException::class);
    }
    foreach (['anonymous','expired','absolute','malformed','disabled','revoked','cross-user'] as $case) {
        $_SESSION=$validSession; $ctx=$context;
        if ($case==='anonymous') { $_SESSION=[]; }
        if ($case==='expired') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at']-=2000; $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at']-=2000; }
        if ($case==='absolute') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at']-=50000; }
        if ($case==='malformed') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authz_version']='1'; }
        if ($case==='disabled') { $db->exec("UPDATE users SET account_status='disabled' WHERE id=$id"); }
        if ($case==='revoked') { $db->exec("UPDATE users SET authz_version=2 WHERE id=$id"); }
        if ($case==='cross-user') { $ctx=AuthenticatedUserContext::fromValidatedUser($id+1); }
        completionDenied($db,fn()=>prepareOnboardingCompletionReview($db,$ctx,3),AuthorizationDeniedException::class);
        $db->exec("UPDATE users SET account_status='active',authz_version=1 WHERE id=$id");
    }
    $context=completionSession($id); $fault=completionConnection(CompletionRevokeDatabase::class); $fault->revokeUser=$id;
    try { prepareOnboardingCompletionReview($fault,$context,3); throw new RuntimeException('Revocation race allowed'); } catch (AuthorizationDeniedException) {}
    completionAssert((int)$db->query("SELECT COUNT(*) FROM portfolios WHERE owner_user_id=$id")->fetchColumn()===0,'Revocation created a Portfolio');
    $db->exec("UPDATE users SET authz_version=1 WHERE id=$id"); $context=completionSession($id);
    foreach ([null,completionFixtureBundle('test-v2'),completionFixtureBundle('test-v1','ar'),new OnboardingAttestationBundle(completionFixtureBundle()->age,new OnboardingDocumentDescriptor('test-terms','test-v1',str_repeat('a',64),'en'),completionFixtureBundle()->privacy)] as $bundle) {
        $GLOBALS['completion_fixture_bundle']=$bundle;
        completionDenied($db,fn()=>prepareOnboardingCompletionReview($db,$context,3),$bundle===null?OnboardingCompletionUnavailableException::class:AuthorizationDeniedException::class);
    }
    $GLOBALS['completion_fixture_bundle']=completionFixtureBundle();
    $db->exec("DELETE FROM onboarding_attestations WHERE user_id=$id");
    completionDenied($db,fn()=>prepareOnboardingCompletionReview($db,$context,3),AuthorizationDeniedException::class);
    recordOnboardingAttestations($db,$context,completionFixtureBundle(),['age_18_or_older'=>true,'terms_accepted'=>true,'privacy_notice_acknowledged'=>true]);
    echo "PASS completion current authorization/readiness revalidation, revocation race and exact current bundle/version/language/digest evidence\n";

    $stage='review'; $intent=$review($context); $session=$_SESSION;
    completionDenied($db,fn()=>completeOnboarding($db,$context,array_replace($intent,['reviewToken'=>str_repeat('b',64)])),OnboardingCompletionConflictException::class);
    $_SESSION=$session; unset($_SESSION[ONBOARDING_COMPLETION_REVIEW_KEY]); completionDenied($db,fn()=>completeOnboarding($db,$context,$intent),OnboardingCompletionConflictException::class);
    $_SESSION=$session; $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at']-=1;
    recordOwnerReadinessObservation($id,1,true,true); completionDenied($db,fn()=>completeOnboarding($db,$context,$intent),OnboardingCompletionConflictException::class);
    $_SESSION=$session; $GLOBALS['completion_fixture_bundle']=completionFixtureBundle('test-v2');
    recordOnboardingAttestations($db,$context,completionFixtureCurrentBundle(),['age_18_or_older'=>true,'terms_accepted'=>true,'privacy_notice_acknowledged'=>true]);
    completionDenied($db,fn()=>completeOnboarding($db,$context,$intent),OnboardingCompletionConflictException::class);
    $GLOBALS['completion_fixture_bundle']=completionFixtureBundle(); $_SESSION=$session;
    saveOnboardingDraft($db,$context,3,'3:'.bin2hex(random_bytes(16)),'professional_snapshot',['headline'=>str_repeat('h',200)]);
    completionDenied($db,fn()=>completeOnboarding($db,$context,$intent),OnboardingCompletionConflictException::class);
    completionDenied($db,fn()=>prepareOnboardingCompletionReview($db,$context,4),InvalidArgumentException::class);
    $stored=readOnboardingDraft($db,$context); completionAssert(strlen($stored['fields']['headline'])===200,'Long saved draft was truncated');
    saveOnboardingDraft($db,$context,4,'4:'.bin2hex(random_bytes(16)),'professional_snapshot',['headline'=>'Explicit corrected headline']);
    $presentation=prepareOnboardingCompletionReview($db,$context,5);
    completionAssert(count($presentation['deferred_fields'])===7 && $presentation['fields']['phone']===str_repeat('1',40) && str_contains($presentation['disposition'],'not added'),'Deferred fields/disposition not disclosed');
    $intent=completionIntent($presentation); $draftBefore=readOnboardingDraft($db,$context);
    $outcome=completeOnboarding($db,$context,$intent); $receiptBefore=completionSnapshot($db);
    completionAssert($draftBefore===readOnboardingDraft($db,$context),'Completion rewrote draft');
    $owned=requireOwnedPortfolioContext($db); $profile=loadAuthorizedPersonalInfo($db,$owned);
    completionAssert($profile['full_name']===$draftBefore['fields']['display_name'] && $profile['hero_headline']==='Explicit corrected headline' && (int)$profile['profile_version']===1 && (int)$profile['public_email_visible']===0 && (int)$profile['public_phone_visible']===0,'Final mapping/version/private contact mismatch');
    foreach (['email','phone_primary','phone_secondary','location','professional_title','about_me','profile_image_path'] as $field) { completionAssert($profile[$field]===null,'Optional draft/provider data promoted'); }
    $state=ownedPublicLifecycleState($db,$owned); completionAssert($state['is_published']===0 && $state['published_at']===null,'Completion published');
    $row=$db->query("SELECT * FROM onboarding_completions WHERE user_id=$id")->fetch(PDO::FETCH_ASSOC);
    completionAssert($row['draft_digest']===hash('sha256',onboardingDraftEncode($draftBefore['fields']),true) && $row['catalog_revision']===finalProfileCatalog()['revision'],'Durable revision/draft evidence mismatch');
    foreach (completionFixtureBundle()->parameters() as $key=>$value) { completionAssert($row[$key]===$value,'Exact attestation descriptor lost'); }
    completionAssert($receiptBefore===completionSnapshot($db) && $outcome===completeOnboarding($db,$context,array_reverse($intent,true)),'Exact retry rewrote data/time');
    completionAssert($receiptBefore===completionSnapshot($db),'Exact retry changed committed evidence');
    $db->exec("UPDATE users SET authz_version=2 WHERE id=$id");
    completionDenied($db,fn()=>completeOnboarding($db,$context,$intent),AuthorizationDeniedException::class);
    $db->exec("UPDATE users SET authz_version=1 WHERE id=$id"); $context=completionSession($id);
    $changed=$intent; $changed['operationId']='5:'.bin2hex(random_bytes(16)); completionDenied($db,fn()=>completeOnboarding($db,$context,$changed),OnboardingCompletionConflictException::class);
    $changed=$intent; $changed['reviewToken']=str_repeat('c',64); completionDenied($db,fn()=>completeOnboarding($db,$context,$changed),OnboardingCompletionConflictException::class);
    completionDenied($db,fn()=>saveOnboardingDraft($db,$context,5,'5:'.bin2hex(random_bytes(16)),'public_identity',['display_name'=>'Must not replay']),OnboardingDraftConflictException::class);
    completionAssert($outcome===readOnboardingCompletion($db,$context),'Receipt recovery disagreed');
    $beforeEdit=completionSnapshot($db); saveOwnedProfilePatch($db,$owned,$outcome['profile_id'],1,['full_name'=>'Current professional name','public_email_visible'=>1,'public_phone_visible'=>0,'hero_headline'=>null,'country_code'=>null,'preferred_language'=>null,'professional_status'=>null,'specialization'=>null]);
    $afterEdit=completionSnapshot($db); $context=completionSession($id,false); $GLOBALS['completion_fixture_bundle']=null;
    completionAssert(readOnboardingCompletion($db,$context)===$outcome && completeOnboarding($db,$context,$intent)===$outcome && $afterEdit===completionSnapshot($db),'Later-session receipt replay overwrote current Profile');
    completionAssert(loadAuthorizedPersonalInfo($db,requireOwnedPortfolioContext($db))['full_name']==='Current professional name','Current Profile lost');
    $results=completionRace([['user'=>$id,'session'=>$_SESSION,'mode'=>'recover'],['user'=>$id,'session'=>$_SESSION,'mode'=>'retry_production','intent'=>$intent]]);
    completionAssert($results[0]['result']===$outcome && $results[1]['result']===$outcome && $afterEdit===completionSnapshot($db),'Unmodified production resolver blocked recovery/replayed Profile');
    $GLOBALS['completion_fixture_bundle']=completionFixtureBundle();
    $db->exec("DELETE FROM schema_migrations WHERE version='019'"); completionMigrate(false);
    $db->prepare("INSERT INTO schema_migrations (version,name,applied_at) VALUES ('019','onboarding_completions',?)")->execute([$ledgerTime]);
    echo "PASS completion server-bound review, explicit correction/disposition, atomic private mapping, durable exact retries and later Profile authority\n";

    $stage='stored boundaries';
    foreach (['country_code'=>'US','preferred_language'=>'fr-CA','professional_status'=>'early'] as $field=>$value) {
        [$uid,$ctx]=$new(); $stored=readOnboardingDraft($db,$ctx); $stored['fields'][$field]=$value;
        $db->prepare('UPDATE onboarding_drafts SET payload=? WHERE user_id=?')->execute([onboardingDraftEncode($stored['fields']),$uid]);
        completionDenied($db,fn()=>prepareOnboardingCompletionReview($db,$ctx,3),InvalidArgumentException::class);
        completionAssert(readOnboardingDraft($db,$ctx)['fields'][$field]===$value,'Historical rejected completion erased saved choice');
    }
    $maximum=completionFields(str_repeat('z',63)); $maximum['display_name']=str_repeat('😀',150); $maximum['headline']=str_repeat('😀',180); $maximum['specialization']=str_repeat('😀',150);
    [$uid,$ctx]=$new($maximum);
    $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at']=time()-3600; $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at']=time()-3000;
    recordOwnerReadinessObservation($uid,1,true,true,time()-3000); $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at']=time();
    $input=$review($ctx); completeOnboarding($db,$ctx,$input); $maxProfile=loadAuthorizedPersonalInfo($db,requireOwnedPortfolioContext($db));
    completionAssert(strlen($maxProfile['full_name'])===600 && strlen($maxProfile['hero_headline'])===720 && strlen($maxProfile['specialization'])===600,'Maximum UTF-8 storage lost bytes');
    echo "PASS completion stored historical choices remain private, exact maximum MySQL mapping and existing readiness observation without a new TTL\n";

    $stage='faults';
    for ($point=1;$point<=4;$point++) {
        [$uid,$ctx]=$new(); $input=$review($ctx); $before=completionSnapshot($db); $fault=completionConnection(CompletionFaultDatabase::class); $fault->point=$point;
        completionDenied($db,fn()=>completeOnboarding($fault,$ctx,$input),PDOException::class);
        completionAssert($before===completionSnapshot($db),'Write fault left partial completion');
    }
    foreach (['before','lost','false'] as $mode) {
        [$uid,$ctx]=$new(); $input=$review($ctx); $before=completionSnapshot($db); $fault=completionConnection(CompletionFaultDatabase::class); $fault->commitMode=$mode;
        try { completeOnboarding($fault,$ctx,$input); throw new RuntimeException('Uncertain COMMIT reported success'); } catch (OnboardingCompletionUncertainException) {}
        if ($mode==='before') { completionAssert(readOnboardingCompletion($db,$ctx)===null && $before===completionSnapshot($db),'Uncommitted failure left rows'); }
        else { $committed=completionSnapshot($db); $recovered=readOnboardingCompletion($db,$ctx); completionAssert($recovered!==null && $recovered===completeOnboarding($db,$ctx,$input) && $committed===completionSnapshot($db),'Lost acknowledgement deleted/replayed committed data'); }
    }
    [$uid,$ctx]=$new(); $input=$review($ctx); $before=completionSnapshot($db); $fault=completionConnection(CompletionFaultDatabase::class); $fault->point=1; $fault->failRollback=true;
    try { completeOnboarding($fault,$ctx,$input); throw new RuntimeException('Unknown rollback reported confirmed failure'); } catch (OnboardingCompletionUncertainException) {}
    $fault->confirmedTestRollback(); completionAssert($before===completionSnapshot($db),'Uncertain rollback deleted/changed durable data');
    echo "PASS completion real SQL rollback at all three writes/after receipt, conservative uncertain COMMIT and lost-response durable recovery\n";

    $stage='concurrency';
    foreach ([true,false] as $same) {
        [$uid,$ctx]=$new(); $input=$review($ctx); $session=$_SESSION; $other=$input; if (!$same) { $other['operationId']='3:'.bin2hex(random_bytes(16)); }
        $results=completionRace([['user'=>$uid,'session'=>$session,'mode'=>'complete','intent'=>$input],['user'=>$uid,'session'=>$session,'mode'=>'complete','intent'=>$other]]);
        $statuses=array_column($results,'status'); sort($statuses); completionAssert($statuses===($same?['saved','saved']:['conflict','saved']),'Same-User race did not serialize');
        if ($same) { completionAssert($results[0]['result']===$results[1]['result'],'Exact concurrent retries disagree'); }
        completionAssert((int)$db->query("SELECT COUNT(*) FROM portfolios WHERE owner_user_id=$uid")->fetchColumn()===1,'Duplicate Portfolio created');
    }
    $slug='raced-'.bin2hex(random_bytes(8)); $inputs=[];
    foreach ([1,2] as $i) { [$uid,$ctx]=$new(completionFields($slug)); $input=$review($ctx); $inputs[]=['user'=>$uid,'session'=>$_SESSION,'mode'=>'complete','intent'=>$input]; }
    $results=completionRace($inputs); $statuses=array_column($results,'status'); sort($statuses); completionAssert($statuses===['conflict','saved'],'Cross-User slug race failed');
    foreach ($results as $i=>$result) { $uid=$inputs[$i]['user']; completionAssert((int)$db->query("SELECT COUNT(*) FROM portfolios WHERE owner_user_id=$uid")->fetchColumn()===($result['status']==='saved'?1:0),'Slug loser left partial rows'); }
    foreach ([1,2,3] as $attempt) {
        [$uid,$ctx]=$new(); $input=$review($ctx); $session=$_SESSION;
        $results=completionRace([['user'=>$uid,'session'=>$session,'mode'=>'complete','intent'=>$input],['user'=>$uid,'session'=>$session,'mode'=>'save']]);
        $statuses=array_column($results,'status'); sort($statuses); completionAssert($statuses===['conflict','saved'],'Draft/completion race failed');
        $ctx=completionSession($uid); $done=readOnboardingCompletion($db,$ctx); $draft=readOnboardingDraft($db,$ctx);
        completionAssert(($done!==null && $draft['version']===3) || ($done===null && $draft['version']===4),'Race mixed completion and draft mutation');
    }
    echo "PASS completion genuine same-User exact/changed-intent, cross-User slug and draft-save races serialize without partial winners\n";

    $stage='legacy'; [$uid,$ctx]=$new();
    $db->prepare('INSERT INTO portfolios (owner_user_id,public_slug) VALUES (?,?)')->execute([$uid,str_repeat('l',64)]); journeyFixtureLegacyOwner($db,$uid); $ctx=completionSession($uid,false);
    createAuthorizedPersonalInfo($db,requireOwnedPortfolioContext($db),['full_name'=>'Existing professional name','professional_title'=>'Existing role','public_email_visible' => 1, 'public_phone_visible' => 1]);
    completionAssert(requireOwnedPortfolioContext($db)->userId===$uid && readOnboardingCompletion($db,$ctx)===null,'Existing owner access changed or completion manufactured');
    completionDenied($db,fn()=>prepareOnboardingCompletionReview($db,$ctx,3),OnboardingCompletionConflictException::class);
    echo "PASS completion existing-owner policy, 64-character existing slug and no manufactured legacy completion\n";
} catch (Throwable $error) { fwrite(STDERR,'FAIL completion synthetic '.$stage.': '.($error instanceof PDOException?'PDO constraint/infrastructure failure':$error->getMessage())."\n"); $exit=1; }
finally { completionCleanup($db,$users); completionEndSession($root); }
completionAssert($initial===completionSnapshot($db),'Synthetic completion fixtures changed protected data');
ob_end_flush(); exit($exit);
