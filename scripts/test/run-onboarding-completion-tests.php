<?php
declare(strict_types=1);
require_once __DIR__.'/../../tests/phase2/support/completion-fixtures.php';
completionFixtureLoadCore(false);
require_once __DIR__.'/../../database/onboarding_attestations.php';
require_once __DIR__.'/../../database/onboarding_completions.php';
ob_start(); $db=getDatabaseConnection(); $initial=completionSnapshot($db); $users=[]; $root=completionSessionRoot(); $exit=0; $stage='migration';
try {
    completionAssert(currentOnboardingAttestationBundle()===null,'Production resolver enabled');
    completionAssert((int)$db->query('SELECT COUNT(*) FROM onboarding_completions')->fetchColumn()===0,'Requires empty disposable completion table');
    $ledger=$db->query("SELECT applied_at FROM schema_migrations WHERE version='019'")->fetchColumn(); completionAssert($ledger!==false,'Requires complete 019 schema');
    $db->exec("DELETE FROM schema_migrations WHERE version='019'; DROP TABLE onboarding_completions");
    completionMigrate(false,'after-completion-table-create');
    completionAssert($db->query("SELECT applied_at FROM schema_migrations WHERE version='019'")->fetchColumn()===false,'Interrupted CREATE recorded ledger');
    completionMigrate(true); completionMigrate(true);
    $db->exec("DELETE FROM schema_migrations WHERE version='019'; DROP TABLE onboarding_completions; CREATE TABLE onboarding_completions (user_id INT UNSIGNED NOT NULL) ENGINE=InnoDB"); completionMigrate(false);
    $db->exec('DROP TABLE onboarding_completions'); completionMigrate(true);
    $db->exec('ALTER TABLE onboarding_completions MODIFY operation_id VARBINARY(42) NOT NULL'); completionMigrate(false);
    $db->exec('ALTER TABLE onboarding_completions MODIFY operation_id VARBINARY(43) NOT NULL');
    $db->exec('ALTER TABLE onboarding_completions ALTER CHECK chk_completion_draft_version NOT ENFORCED'); completionMigrate(false);
    $db->exec('ALTER TABLE onboarding_completions ALTER CHECK chk_completion_draft_version ENFORCED');
    $db->exec('ALTER TABLE onboarding_completions DROP CHECK chk_completion_draft_version, ADD CONSTRAINT chk_completion_draft_version CHECK (draft_version>0 OR TRUE)'); completionMigrate(false);
    $db->exec('ALTER TABLE onboarding_completions DROP CHECK chk_completion_draft_version, ADD CONSTRAINT chk_completion_draft_version CHECK ('.onboardingCompletionSchemaChecks()['chk_completion_draft_version'].')');
    $db->exec('ALTER TABLE onboarding_completions DROP FOREIGN KEY fk_completion_attestation'); completionMigrate(false);
    $key=implode(',',onboardingCompletionAttestationKey());
    $db->exec("ALTER TABLE onboarding_completions ADD CONSTRAINT fk_completion_attestation FOREIGN KEY ($key) REFERENCES onboarding_attestations($key) ON UPDATE RESTRICT ON DELETE RESTRICT"); completionMigrate(true);
    $db->prepare("UPDATE schema_migrations SET applied_at=? WHERE version='019'")->execute([$ledger]);
    completionAssert($initial===completionSnapshot($db),'019 changed protected data or ledger');
    echo "PASS completion fresh/populated 019, repeat/interrupted/partial recovery, exact checks/FKs and protected-data preservation\n";

    $stage='fields'; $fields=completionFields();
    foreach (['display_name'=>150,'specialization'=>150,'headline'=>180] as $field=>$limit) {
        foreach (['a',str_repeat('😀',$limit),"  e\u{0301} اسم  "] as $value) { $f=$fields; $f[$field]=$value; $profile=onboardingCompletionProfile($f); completionAssert(in_array($value,$profile,true),'Exact text changed'); }
        foreach ([''," \u{00A0}",str_repeat('a',$limit+1),str_repeat('😀',$limit+1),"bad\xFF","bad\0","bad\n","bad\u{0085}",[],false,1,null] as $value) {
            $f=$fields; $f[$field]=$value; $caught=false; try { onboardingCompletionProfile($f); } catch (InvalidArgumentException) { $caught=true; } completionAssert($caught,'Invalid text boundary accepted');
        }
    }
    foreach (ONBOARDING_COMPLETION_REQUIRED as $field) { $f=$fields; unset($f[$field]); $caught=false; try { onboardingCompletionProfile($f); } catch (InvalidArgumentException) { $caught=true; } completionAssert($caught,'Missing required field accepted'); }
    foreach (['country_code'=>['US','ZZ','jo','JO '], 'preferred_language'=>['fr-CA','AR'], 'professional_status'=>['early','retired']] as $field=>$values) {
        foreach ($values as $value) { $f=$fields; $f[$field]=$value; $caught=false; try { onboardingCompletionProfile($f); } catch (InvalidArgumentException) { $caught=true; } completionAssert($caught,'Historical catalog value accepted at completion'); }
    }
    foreach (array_keys(finalProfileCatalog()['countries']) as $code) { $f=$fields; $f['country_code']=$code; onboardingCompletionProfile($f); }
    foreach (['abc',str_repeat('a',63)] as $slug) { $f=$fields; $f['slug_candidate']=$slug; onboardingCompletionProfile($f); }
    foreach (['a','ab',str_repeat('a',64),'UPPER',' lower','trailing-','-leading','a--b',...PUBLIC_SLUG_RESERVED] as $slug) { $f=$fields; $f['slug_candidate']=$slug; $caught=false; try { onboardingCompletionProfile($f); } catch (PublicLifecycleValidationException) { $caught=true; } completionAssert($caught,'Invalid initial slug accepted'); }
    completionAssert(requirePublicSlug(str_repeat('a',64))===str_repeat('a',64),'Existing 64-character slug lifecycle changed');
    echo "PASS completion seven exact fields, UTF-8/code-point/byte bounds, historical rejection, canonical 3-63 slugs and existing 64 compatibility\n";

    $stage='production unavailable'; $id=completionNewUser($db); $users[]=$id; $context=completionSeed($db,$id,$fields);
    $intent=['expectedVersion'=>3,'operationId'=>'3:'.bin2hex(random_bytes(16)),'reviewToken'=>str_repeat('a',64),'confirm_private_creation'=>true,'acknowledge_private_draft'=>true];
    completionAssert(readOnboardingCompletion($db,$context)===null,'Absence manufactured completion');
    completionDenied($db,fn()=>prepareOnboardingCompletionReview($db,$context,3),OnboardingCompletionUnavailableException::class);
    completionDenied($db,fn()=>completeOnboarding($db,$context,$intent),OnboardingCompletionUnavailableException::class);
    foreach (['user_id','portfolio_id','fields','policies','catalog_revision','contract_revision'] as $extra) { completionDenied($db,fn()=>completeOnboarding($db,$context,$intent+[$extra=>'forged']),InvalidArgumentException::class); }
    foreach (['expectedVersion'=>[true,'3',0,2147483648,[]],'operationId'=>['3:bad',3,[]],'reviewToken'=>['bad',[],false],'confirm_private_creation'=>[false,1,'true',[]],'acknowledge_private_draft'=>[false,1,null]] as $key=>$values) {
        foreach ($values as $value) { $bad=$intent; $bad[$key]=$value; completionDenied($db,fn()=>completeOnboarding($db,$context,$bad),InvalidArgumentException::class); }
    }
    $db->beginTransaction(); completionDenied($db,fn()=>completeOnboarding($db,$context,$intent),LogicException::class); completionAssert($db->inTransaction(),'Ambient transaction ended'); $db->rollBack();
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_SILENT); completionDenied($db,fn()=>readOnboardingCompletion($db,$context),LogicException::class); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    echo "PASS completion production policies remain null, strict server-only intent, unavailable creates nothing and owned-transaction boundary\n";

    $stage='synthetic child';
    $p=proc_open([PHP_BINARY,__DIR__.'/run-onboarding-completion-synthetic-tests.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    completionAssert(is_resource($p),'Synthetic child unavailable'); fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $status=proc_close($p);
    echo $out; if ($err!=='') { fwrite(STDERR,$err); } completionAssert($status===0,'Guarded synthetic child failed');
} catch (Throwable $error) { fwrite(STDERR,'FAIL completion '.$stage.': '.($error instanceof PDOException?'PDO constraint/infrastructure failure':$error->getMessage())."\n"); $exit=1; }
finally { completionCleanup($db,$users); completionEndSession($root); completionAssert($initial===completionSnapshot($db),'Completion fixture changed protected data'); }
if ($exit===0) { echo "PASS completion all preexisting data, private synthetic source/session files and database fixtures preserved/cleaned\n"; }
ob_end_flush(); exit($exit);
