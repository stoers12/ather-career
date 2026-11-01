<?php
declare(strict_types=1);

/** No request/session cohort flags or ID/time threshold. Historical completion
 * is bound to current ownership and its original Profile, never current policies.
 * Missing/partial maintenance state fails closed for uncompleted owners.
 * Write callers already holding User -> Portfolio locks request current
 * locking proof reads; this does not add eligibility rules or policy gates.
 * @return array{portfolio_id:?int, access:bool, state:string}
 */
function ownerJourneyState(PDO $database, AuthenticatedUserContext $user, bool $forUpdate = false): array
{
    $statement=$database->prepare('SELECT p.id,p.legacy_owner_user_id,c.user_id AS completed_user,i.id AS completed_profile FROM portfolios p LEFT JOIN onboarding_completions c ON c.user_id=p.owner_user_id AND c.portfolio_id=p.id LEFT JOIN personal_info i ON i.id=c.profile_id AND i.portfolio_id=p.id WHERE p.owner_user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $statement->execute([$user->userId]); $row=$statement->fetch(PDO::FETCH_ASSOC);
    $id=is_array($row) ? authorizationPositiveInteger($row['id']) : null;
    if ($id!==null && authorizationPositiveInteger($row['completed_user'])===$user->userId && authorizationPositiveInteger($row['completed_profile'])!==null) { return ['portfolio_id'=>$id,'access'=>true,'state'=>'completed']; }
    $seal=$database->query('SELECT id,revision,snapshot_count,snapshot_digest,captured_at FROM owner_journey_cutover' . ($forUpdate ? ' FOR SHARE' : ''))->fetchAll(PDO::FETCH_ASSOC);
    if (count($seal)!==1 || (int)$seal[0]['id']!==1 || $seal[0]['revision']!=='owner-journey-cutover-v1' || strlen($seal[0]['snapshot_digest'])!==32 || !is_string($seal[0]['captured_at'])) { return ['portfolio_id'=>$id,'access'=>false,'state'=>'maintenance']; }
    if ($id!==null && authorizationPositiveInteger($row['legacy_owner_user_id'])===$user->userId) { return ['portfolio_id'=>$id,'access'=>true,'state'=>'grandfathered']; }
    return ['portfolio_id'=>$id,'access'=>false,'state'=>$id===null ? 'incomplete' : 'unproven_portfolio'];
}

/** A fixed local destination, not a redirect supplied by a browser. Reauthorizes
 * even when called after callback validation or with an older server context.
 * No readiness/attestation requirement is added to ordinary owner access.
 */
function ownerJourneyDestination(PDO $database, AuthenticatedUserContext $user): string
{
    $current=requireAuthenticatedUser($database);
    if ($current->userId!==$user->userId) { throw new AuthorizationDeniedException('Journey User mismatch.'); }
    // An account scheduled for deletion (L-03) sees only the page that cancels it.
    $deletion=$database->prepare('SELECT deletion_requested_at FROM users WHERE id=? LIMIT 1');
    $deletion->execute([$current->userId]);
    if (is_string($deletion->fetchColumn())) { return '/owner_account_deletion.php'; }
    $state=ownerJourneyState($database,$current);
    // A confirmation sign-in (L-03) returns to the page that asked for it.
    if ($state['access']) { return (function_exists('auth0CompletedReturnDestination') ? auth0CompletedReturnDestination(null, true) : null) ?? '/owner.php'; }
    // No incomplete User receives Owner access. The unchanged completion core
    // also refuses to overwrite/adopt any unproven existing Portfolio.
    if ($state['state']==='maintenance') { return '/owner_onboarding.php'; }
    require_once __DIR__.'/onboarding_draft_http.php';
    require_once __DIR__.'/onboarding_attestation_http.php';
    require_once __DIR__.'/onboarding_completion_http.php';
    require_once __DIR__.'/onboarding_attestation_presentation.php';
    if (!onboardingDraftHttpEnabled() || !onboardingAttestationHttpEnabled() || !onboardingCompletionHttpEnabled() || resolveOnboardingAttestationPresentation()===null) { return '/owner_onboarding.php'; }
    return '/owner_onboarding_editor.php';
}
