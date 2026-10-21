<?php
declare(strict_types=1);

require_once __DIR__ . '/onboarding_drafts.php';
require_once __DIR__ . '/onboarding_attestations.php';
require_once __DIR__ . '/portfolio_scoped_data.php';
require_once __DIR__ . '/public_lifecycle.php';

const ONBOARDING_COMPLETION_CONTRACT = 'ather-first-completion-2026-10-19-r1';
const ONBOARDING_COMPLETION_REVIEW_KEY = 'onboarding_completion_review';
const ONBOARDING_COMPLETION_REQUIRED = ['display_name','country_code','preferred_language','professional_status','specialization','headline','slug_candidate'];
const ONBOARDING_COMPLETION_DEFERRED = ['name_ar','name_en','city','university','graduation_year','biography','phone'];

final class OnboardingCompletionConflictException extends RuntimeException {}
final class OnboardingCompletionUnavailableException extends RuntimeException {}
final class OnboardingCompletionUncertainException extends RuntimeException {}

/** Pure first-completion validation, not stored-draft decoding or Profile edits.
 * Exact text only; never trim, normalize, infer, truncate or promote optional data.
 */
function onboardingCompletionProfile(array $fields): array
{
    foreach (ONBOARDING_COMPLETION_REQUIRED as $field) {
        if (!isset($fields[$field]) || !is_string($fields[$field])) {
            throw new InvalidArgumentException('Required completion field is missing.');
        }
    }
    foreach (['display_name'=>[150,600], 'headline'=>[180,720]] as $field=>[$characters,$bytes]) {
        $value=$fields[$field];
        if (preg_match('//u',$value)!==1 || strlen($value)>$bytes || mb_strlen($value,'UTF-8')>$characters
            || preg_match('/\p{Cc}/u',$value)!==0 || preg_match('/(*UCP)\S/u',$value)!==1) {
            throw new InvalidArgumentException('Invalid completion text.');
        }
    }
    onboardingDraftValidateCurrentValues(array_intersect_key($fields,array_flip(FINAL_PROFILE_FIELDS)));
    $slug=$fields['slug_candidate'];
    if (strlen($slug)>63 || requirePublicSlug($slug)!==$slug) {
        throw new PublicLifecycleValidationException('Invalid initial profile link.');
    }
    return ['full_name'=>$fields['display_name'],'hero_headline'=>$fields['headline'],
        ...array_intersect_key($fields,array_flip(FINAL_PROFILE_FIELDS)), 'public_email_visible'=>0, 'public_phone_visible'=>0];
}

/** @internal One owned transaction, User lock and independently repeated session
 * authorization. Once COMMIT is attempted, failure means uncertain, not deletion.
 */
function onboardingCompletionTransaction(PDO $database, AuthenticatedUserContext $context, callable $operation): mixed
{
    if (session_status()!==PHP_SESSION_ACTIVE) { throw new AuthorizationDeniedException('Completion requires an active session.'); }
    if ($database->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $database->inTransaction()) {
        throw new LogicException('Completion requires exception mode and an owned transaction.');
    }
    $current=requireAuthenticatedUser($database);
    if ($current->userId!==$context->userId) { throw new AuthorizationDeniedException('Completion User mismatch.'); }
    if (!$database->beginTransaction()) { throw new RuntimeException('Completion transaction unavailable.'); }
    $commitAttempted=false;
    try {
        $lock=$database->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $lock->execute([$current->userId]);
        $current=requireAuthenticatedUser($database);
        if ($current->userId!==$context->userId) { throw new AuthorizationDeniedException('Completion User mismatch.'); }
        $result=$operation($current);
        $commitAttempted=true;
        if (!$database->commit()) { throw new RuntimeException('Completion commit not acknowledged.'); }
        return $result;
    } catch (Throwable $error) {
        $rolledBack=false;
        try { $rolledBack=$database->inTransaction() && $database->rollBack(); } catch (Throwable) {}
        if (!$commitAttempted && $rolledBack) { throw $error; }
        throw new OnboardingCompletionUncertainException('Completion outcome is uncertain; recover the receipt before another attempt.',0,$error);
    }
}

/** @internal Call only under the authorized User lock. */
function onboardingCompletionRow(PDO $database, int $userId): ?array
{
    $select=$database->prepare('SELECT * FROM onboarding_completions WHERE user_id=?'); $select->execute([$userId]);
    return $select->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Historical outcome only, never current or draft profile values. */
function onboardingCompletionResult(array $row): array
{
    return ['portfolio_id'=>(int)$row['portfolio_id'],'profile_id'=>(int)$row['profile_id'],
        'draft_version'=>(int)$row['draft_version'],'initial_profile_version'=>1,
        'contract_revision'=>$row['contract_revision'],'catalog_revision'=>$row['catalog_revision'],
        'deferred_fields'=>json_decode($row['deferred_fields'],true,8,JSON_THROW_ON_ERROR),
        'completed_at'=>$row['completed_at']];
}

/** Later authorized sessions can recover without old cookies/review/session secrets. */
function readOnboardingCompletion(PDO $database, AuthenticatedUserContext $context): ?array
{
    return onboardingCompletionTransaction($database,$context,static function (AuthenticatedUserContext $current) use ($database): ?array {
        $row=onboardingCompletionRow($database,$current->userId);
        return $row===null ? null : onboardingCompletionResult($row);
    });
}

/** @internal Authoritative present-time prerequisites for a NEW completion only. */
function onboardingCompletionInputs(PDO $database, AuthenticatedUserContext $current, int $version): array
{
    if (onboardingCompletionRow($database,$current->userId)!==null) { throw new OnboardingCompletionConflictException('Already completed.'); }
    $select=$database->prepare('SELECT id FROM portfolios WHERE owner_user_id=?'); $select->execute([$current->userId]);
    if ($select->fetchColumn()!==false) { throw new OnboardingCompletionConflictException('Existing owner cannot be completed by this operation.'); }
    $observation=readAuthorizedOwnerReadinessObservation($database);
    if (($observation['email_verified']??null)!==true) { throw new AuthorizationDeniedException('Current-session readiness is required.'); }
    $bundle=currentOnboardingAttestationBundle();
    if ($bundle===null) { throw new OnboardingCompletionUnavailableException('Required documents are unavailable.'); }
    $evidence=findLockedOnboardingAttestation($database,$current,$bundle);
    if ($evidence===null) { throw new AuthorizationDeniedException('Exact current document evidence is required.'); }
    $select=$database->prepare('SELECT * FROM onboarding_drafts WHERE user_id=?'); $select->execute([$current->userId]);
    $draft=$select->fetch(PDO::FETCH_ASSOC);
    if (!is_array($draft) || (int)$draft['version']!==$version) { throw new OnboardingCompletionConflictException('Saved draft changed; review again.'); }
    $fields=onboardingDraftDecode($draft['payload']); $profile=onboardingCompletionProfile($fields);
    $deferred=array_keys(array_intersect_key($fields,array_flip(ONBOARDING_COMPLETION_DEFERRED))); sort($deferred,SORT_STRING);
    return [$draft,$fields,$profile,$bundle,$evidence,$observation,$deferred];
}

/** @internal Review binds immutable server data and session authentication, not a
 * new TTL/provider freshness assertion. No client chooses revisions or policies.
 */
function onboardingCompletionReviewBinding(array $draft, OnboardingAttestationBundle $bundle, array $observation, array $deferred): array
{
    $session=currentInternalUserSession();
    return ['user_id'=>$session['internal_user_id'],'authz_version'=>$session['authz_version'],
        'authenticated_at'=>$session['authenticated_at'],'draft_version'=>(int)$draft['version'],
        'draft_digest'=>hash('sha256',$draft['payload']),'contract_revision'=>ONBOARDING_COMPLETION_CONTRACT,
        'catalog_revision'=>finalProfileCatalog()['revision'],'documents'=>$bundle->parameters(),
        'readiness_observed_at'=>$observation['observed_at'],'deferred_fields'=>$deferred];
}

/** Server-only preparation; future HTTP/UI must privately show ALL returned fields
 * and disposition, then explicitly confirm. This function records no attestation.
 */
function prepareOnboardingCompletionReview(PDO $database, AuthenticatedUserContext $context, mixed $expectedVersion): array
{
    if (!is_int($expectedVersion) || $expectedVersion<1 || $expectedVersion>ONBOARDING_DRAFT_MAX_VERSION) { throw new InvalidArgumentException('Invalid saved draft version.'); }
    $review=onboardingCompletionTransaction($database,$context,static function (AuthenticatedUserContext $current) use ($database,$expectedVersion): array {
        [$draft,$fields,$profile,$bundle,$evidence,$observation,$deferred]=onboardingCompletionInputs($database,$current,$expectedVersion);
        $binding=onboardingCompletionReviewBinding($draft,$bundle,$observation,$deferred);
        return ['binding'=>$binding,'token'=>bin2hex(random_bytes(32)),'fields'=>$fields];
    });
    $_SESSION[ONBOARDING_COMPLETION_REVIEW_KEY]=['binding'=>$review['binding'],'token'=>$review['token']];
    return ['expectedVersion'=>$expectedVersion,'reviewToken'=>$review['token'],'fields'=>$review['fields'],
        'deferred_fields'=>$review['binding']['deferred_fields'],
        'disposition'=>'Optional details stay in your private historical draft and are not added to your Profile. Retention/deletion remains a launch decision. / تبقى التفاصيل الاختيارية في مسودتك التاريخية الخاصة ولا تُضاف إلى ملفك. مدة الاحتفاظ والحذف قرار منفصل عند الإطلاق.'];
}

/** Core only: no route, gate, CSRF substitute, field overrides or authority selectors.
 * Exact receipt recognition follows normal authorization/strict structural checks,
 * before current prerequisites, so later sessions/catalogs/policies cannot erase it.
 */
function completeOnboarding(PDO $database, AuthenticatedUserContext $context, array $intent): array
{
    $keys=array_keys($intent); sort($keys,SORT_STRING);
    if ($keys!==['acknowledge_private_draft','confirm_private_creation','expectedVersion','operationId','reviewToken']
        || !is_int($intent['expectedVersion']) || $intent['expectedVersion']<1 || $intent['expectedVersion']>ONBOARDING_DRAFT_MAX_VERSION
        || !is_string($intent['operationId']) || preg_match('/\A'.$intent['expectedVersion'].':[a-f0-9]{32}\z/D',$intent['operationId'])!==1
        || !is_string($intent['reviewToken']) || preg_match('/\A[a-f0-9]{64}\z/D',$intent['reviewToken'])!==1
        || $intent['confirm_private_creation']!==true || $intent['acknowledge_private_draft']!==true) {
        throw new InvalidArgumentException('Explicit version-bound private creation and draft disposition confirmation required.');
    }
    ksort($intent,SORT_STRING); $intentDigest=hash('sha256',json_encode($intent,JSON_THROW_ON_ERROR),true);
    $result=onboardingCompletionTransaction($database,$context,static function (AuthenticatedUserContext $current) use ($database,$intent,$intentDigest): array {
        $receipt=onboardingCompletionRow($database,$current->userId);
        if ($receipt!==null) {
            if (!hash_equals($receipt['operation_id'],$intent['operationId']) || !hash_equals($receipt['intent_digest'],$intentDigest)) {
                throw new OnboardingCompletionConflictException('Already completed with another intent.');
            }
            return onboardingCompletionResult($receipt);
        }
        [$draft,$fields,$profile,$bundle,$evidence,$observation,$deferred]=onboardingCompletionInputs($database,$current,$intent['expectedVersion']);
        $binding=onboardingCompletionReviewBinding($draft,$bundle,$observation,$deferred);
        $review=$_SESSION[ONBOARDING_COMPLETION_REVIEW_KEY]??null;
        if (!is_array($review) || ($review['binding']??null)!==$binding || !is_string($review['token']??null)
            || !hash_equals($review['token'],$intent['reviewToken'])) { throw new OnboardingCompletionConflictException('Review is missing or changed; review again.'); }
        try {
            $insert=$database->prepare('INSERT INTO portfolios (owner_user_id,public_slug,is_published,published_at) VALUES (?,?,0,NULL)');
            $insert->execute([$current->userId,$fields['slug_candidate']]);
        } catch (PDOException $error) {
            if (publicLifecycleDuplicateKey($error)) { throw new OnboardingCompletionConflictException('Profile link or first creation is unavailable.',0,$error); }
            throw $error;
        }
        $portfolioId=(int)$database->lastInsertId();
        $owned=AuthorizedPortfolioContext::fromValidatedOwnership($current,$portfolioId);
        $profileId=createAuthorizedPersonalInfo($database,$owned,$profile);
        $parameters=['user_id'=>$current->userId,'portfolio_id'=>$portfolioId,'profile_id'=>$profileId,
            'draft_version'=>(int)$draft['version'],'draft_digest'=>hash('sha256',$draft['payload'],true),
            'operation_id'=>$intent['operationId'],'intent_digest'=>$intentDigest,
            'review_digest'=>hash('sha256',json_encode($binding,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),true),
            'contract_revision'=>ONBOARDING_COMPLETION_CONTRACT,'catalog_revision'=>finalProfileCatalog()['revision'],
            'authz_version'=>$binding['authz_version'],'readiness_observed_at'=>$observation['observed_at'],
            ...$bundle->parameters(),'attested_at'=>$evidence['recorded_at'],
            'deferred_fields'=>json_encode($deferred,JSON_THROW_ON_ERROR)];
        $columns=implode(',',array_keys($parameters)); $values=implode(',',array_map(static fn(string $key): string=>':'.$key,array_keys($parameters)));
        $insert=$database->prepare("INSERT INTO onboarding_completions ($columns,completed_at) VALUES ($values,UTC_TIMESTAMP(6))"); $insert->execute($parameters);
        $receipt=onboardingCompletionRow($database,$current->userId);
        if ($receipt===null) { throw new RuntimeException('Completion evidence unavailable.'); }
        return onboardingCompletionResult($receipt);
    });
    unset($_SESSION[ONBOARDING_COMPLETION_REVIEW_KEY]);
    return $result;
}
