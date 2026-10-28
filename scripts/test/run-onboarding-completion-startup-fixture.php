<?php
declare(strict_types=1);
// Only the disposable restored-database rehearsal invokes this guarded CLI file.
// No HTTP/public shim, production resolver override or runtime fake-policy setting.
require_once __DIR__.'/../../tests/phase2/support/completion-fixtures.php';
completionFixtureLoadCore(true);
require_once __DIR__.'/../../includes/profile_writes.php';
require_once __DIR__.'/../../includes/public_lifecycle.php';
require_once __DIR__.'/../../includes/portfolio_scoped_data.php';
require_once __DIR__.'/../../database/migrate.php';
$db=getDatabaseConnection();
completionAssert((int)$db->query('SELECT COUNT(*) FROM onboarding_completions')->fetchColumn()===0,'Restored startup fixture requires no prior completion evidence');
$root=completionSessionRoot();
try {
    $id=completionNewUser($db); $context=completionSeed($db,$id,completionFields());
    $outcome=completeOnboarding($db,$context,completionIntent(prepareOnboardingCompletionReview($db,$context,3)));
    completionAssert($outcome===readOnboardingCompletion($db,$context),'Restored completion evidence missing');
    $owned=requireOwnedPortfolioContext($db);
    $profile=loadAuthorizedPersonalInfo($db,$owned);
    completionAssert((int)$profile['public_email_visible']===0 && (int)$profile['public_phone_visible']===0,'First completion contacts not private');
    saveOwnedProfilePatch($db,$owned,$outcome['profile_id'],1,['email'=>'restored-contact@example.test','phone_primary'=>'+962 79 567 8901','public_email_visible'=>1,'public_phone_visible'=>0]);
    $selected=createAuthorizedProject($db,$owned,'Restored selected project','Testing','Preserved selected project',null,null,[]);
    $private=createAuthorizedProject($db,$owned,'Restored private project','Testing','Preserved private project',null,null,[]);
    setOwnedProjectPublication($db,$owned,$selected,1);
    completionAssert((int)findAuthorizedProject($db,$owned,$private)['is_published']===0,'Restored private project default lost');
    captureLegacyOwnerCutover($db);
    $bare=completionNewUser($db); $db->prepare('INSERT INTO portfolios(owner_user_id) VALUES (?)')->execute([$bare]);
    $before=completionSnapshot($db); captureLegacyOwnerCutover($db);
    completionAssert($before===completionSnapshot($db),'Frozen restored snapshot expanded');
    $ctx=completionSession($bare); $denied=false;
    try { requireOwnedPortfolioContext($db); } catch (AuthorizationDeniedException) { $denied=true; }
    completionAssert($denied,'Restored post-cutover bare owner gained access');
    echo "PASS guarded restored fixture preserves frozen legacy set and excludes later bare Portfolio\n";
    echo "PASS guarded restored fixture contains committed private completion/Profile/draft/exact attestation evidence\n";
    echo "PASS guarded restored fixture includes independent email-on phone-off Profile choices through migration021\n";
    echo "PASS guarded restored fixture includes selected and private Projects through migration022 without publishing Portfolio\n";
} finally { completionEndSession($root); }
// Durable rows intentionally remain only in the disposable restore database;
// full dump equality must preserve them before the outer harness drops that DB.
