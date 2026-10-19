<?php
declare(strict_types=1);
// Only the disposable restored-database rehearsal invokes this guarded CLI file.
// No HTTP/public shim, production resolver override or runtime fake-policy setting.
require_once __DIR__.'/../tests/phase2/support/completion-fixtures.php';
completionFixtureLoadCore(true);
$db=getDatabaseConnection();
completionAssert((int)$db->query('SELECT COUNT(*) FROM onboarding_completions')->fetchColumn()===0,'Restored startup fixture requires no prior completion evidence');
$root=completionSessionRoot();
try {
    $id=completionNewUser($db); $context=completionSeed($db,$id,completionFields());
    $outcome=completeOnboarding($db,$context,completionIntent(prepareOnboardingCompletionReview($db,$context,3)));
    completionAssert($outcome===readOnboardingCompletion($db,$context),'Restored completion evidence missing');
    echo "PASS guarded restored fixture contains committed private completion/Profile/draft/exact attestation evidence\n";
} finally { completionEndSession($root); }
// Durable rows intentionally remain only in the disposable restore database;
// full dump equality must preserve them before the outer harness drops that DB.
