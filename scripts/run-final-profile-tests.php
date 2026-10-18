<?php
declare(strict_types=1);
require_once __DIR__ . '/../tests/phase2/support/profile-version-fixtures.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';
require_once __DIR__ . '/../includes/portfolio_presentation.php';
ob_start();
$db=getDatabaseConnection(); $initial=profileTestSnapshot($db); $users=[]; $portfolios=[]; $session=null; $exit=0; $phase='migration';
$ledger=$db->query("SELECT applied_at FROM schema_migrations WHERE version='018'")->fetchColumn();
try {
    profileTestAssert(is_file('/.dockerenv') && $ledger!==false, 'Requires migrated disposable Docker database.');
    $rows=$db->query('SELECT * FROM personal_info')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) { foreach (FINAL_PROFILE_FIELDS as $field) { profileTestAssert($row[$field]===null,'018 upgrade fixture must precede final-field edits.'); } }
    $db->exec("DELETE FROM schema_migrations WHERE version='018'");
    $db->exec('ALTER TABLE personal_info DROP COLUMN country_code, DROP COLUMN preferred_language, DROP COLUMN professional_status, DROP COLUMN specialization');
    profileTestMigrate(false,'after-final-profile-alter');
    profileTestAssert($db->query("SELECT applied_at FROM schema_migrations WHERE version='018'")->fetchColumn()===false,'Interrupted ALTER recorded ledger.');
    profileTestAssert($rows===$db->query('SELECT * FROM personal_info')->fetchAll(PDO::FETCH_ASSOC),'018 changed existing values/timestamps/ownership/visibility/version.');
    profileTestMigrate(true); profileTestMigrate(true);
    $db->exec('ALTER TABLE personal_info MODIFY preferred_language CHAR(2) CHARACTER SET ascii COLLATE ascii_general_ci NULL'); profileTestMigrate(false);
    $db->exec('ALTER TABLE personal_info MODIFY preferred_language CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL');
    $db->exec('ALTER TABLE personal_info DROP COLUMN specialization'); profileTestMigrate(false);
    $db->exec('ALTER TABLE personal_info ADD specialization VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL AFTER professional_status');
    profileTestMigrate(true);
    $db->prepare("UPDATE schema_migrations SET applied_at=? WHERE version='018'")->execute([$ledger]);
    profileTestAssert($initial===profileTestSnapshot($db),'018 recovery/repeat did not preserve protected data.');
    echo "PASS final-profile fresh/populated upgrade, interrupted/repeated 018, partial/collation rejection and exact preservation\n";

    $phase='catalog'; $root=__DIR__.'/../data/profile-catalog'; $provenance=json_decode(file_get_contents($root.'/provenance.json'),true,32,JSON_THROW_ON_ERROR);
    foreach ($provenance['files'] as $file) { profileTestAssert(hash_file('sha256',$root.'/'.$file['path'])===$file['sha256'],'Pinned source checksum mismatch.'); }
    $iso=json_decode(file_get_contents($root.'/sources/iso-3166-1.json'),true,32,JSON_THROW_ON_ERROR)['3166-1'];
    $codes=array_column($iso,'alpha_2'); sort($codes); $catalog=finalProfileCatalog();
    profileTestAssert(count($codes)===249 && count(array_unique($codes))===249 && $codes===array_keys($catalog['countries']),'Country membership differs from independent ISO snapshot.');
    foreach (['ar','en'] as $language) {
        $cldr=json_decode(file_get_contents($root.'/sources/cldr-'.$language.'.json'),true,32,JSON_THROW_ON_ERROR)['main'][$language]['localeDisplayNames']['territories'];
        foreach ($codes as $code) { profileTestAssert($catalog['countries'][$code][$language]===($cldr[$code]??null) && $cldr[$code]!=='','CLDR label mismatch.'); }
        $names=json_decode(file_get_contents($root.'/sources/cldr-'.$language.'-languages.json'),true,32,JSON_THROW_ON_ERROR)['main'][$language]['localeDisplayNames']['languages'];
        foreach (['ar','en'] as $code) { profileTestAssert($catalog['languages'][$code][$language]===$names[$code],'CLDR language label mismatch.'); }
    }
    foreach (['XK','ZZ','EU','UN','UK','AC','CP','DG','EA','IC','TA','SU','AN','BU','TP','YU','001','419'] as $code) { profileTestAssert(!isset($catalog['countries'][$code]),'Non-ISO code included.'); }
    profileTestAssert(array_keys($catalog['languages'])===['ar','en'] && array_keys($catalog['statuses'])===['student','graduate','employed','self_employed','seeking_work','other'],'Choice token drift.');
    foreach (['languages','statuses'] as $group) { foreach ($catalog[$group] as $labels) { profileTestAssert(($labels['ar']??'')!=='' && ($labels['en']??'')!=='','Missing choice label.'); } }
    echo "PASS final-profile exact 249-code membership, pinned checksums/AR-EN labels, exclusions and candidate choices\n";

    $phase='validation';
    foreach (FINAL_PROFILE_FIELDS as $field) {
        profileTestAssert(finalProfileFieldError($field,null, $catalog)===null,'NULL should be optional.');
        foreach ([[],1,true,1.0] as $value) { profileTestAssert(finalProfileFieldError($field,$value, $catalog)!==null,'Non-text accepted.'); }
    }
    foreach (['jo',' JO','JO ','ZZ','XK','UK',"J\0",''] as $value) { profileTestAssert(finalProfileFieldError('country_code',$value, $catalog)!==null,'Unsupported country accepted.'); }
    foreach (['AR','en ','fr',''] as $value) { profileTestAssert(finalProfileFieldError('preferred_language',$value, $catalog)!==null,'Unsupported language accepted.'); }
    foreach (['Student','early','independent','graduate ',"other\n",''] as $value) { profileTestAssert(finalProfileFieldError('professional_status',$value, $catalog)!==null,'Unsupported status accepted.'); }
    foreach (['JO','AX','TW','PS','AQ','BQ'] as $value) { profileTestAssert(finalProfileFieldError('country_code',$value, $catalog)===null,'Official country missing.'); }
    foreach (['a',str_repeat('a',150),str_repeat('😀',150),'  تخصص دقيق  ',"e\u{0301}","é"] as $value) { profileTestAssert(finalProfileFieldError('specialization',$value, $catalog)===null,'Valid exact specialization rejected.'); }
    foreach (['', '   ',"\u{00A0}\u{2028}",str_repeat('a',151),str_repeat('😀',151),"bad\xFF","a\n","a\t","a\0","a\u{0085}","a\u{007F}"] as $value) { profileTestAssert(finalProfileFieldError('specialization',$value, $catalog)!==null,'Invalid specialization accepted.'); }
    echo "PASS final-profile strict token/type and exact Unicode 150-code-point/600-byte boundaries without normalization\n";

    $phase='storage/public'; $session=attestationTestStartSession();
    $db->prepare("INSERT INTO users (oidc_issuer,oidc_subject,account_status,authz_version) VALUES ('https://final-profile.invalid/',?,'active',1)")->execute([bin2hex(random_bytes(16))]); $users[]=(int)$db->lastInsertId();
    $db->prepare('INSERT INTO portfolios (owner_user_id) VALUES (?)')->execute([$users[0]]); $portfolios[]=(int)$db->lastInsertId(); $context=profileTestContext($db,$users[0]);
    $values=['full_name'=>'Synthetic Private Fields','professional_title'=>'Original role','location'=>'Original location','country_code'=>'JO','preferred_language'=>'ar','professional_status'=>'other','specialization'=>'  Private exact specialization  '];
    $saved=saveOwnedProfilePatch($db,$context,null,0,$values); $loaded=loadAuthorizedPersonalInfo($db,$context);
    foreach ($values as $field=>$value) { profileTestAssert($loaded[$field]===$value,'Scoped read/storage changed field.'); }
    saveOwnedProfilePatch($db,$context,$saved['id'],1,['hero_headline'=>'Legacy headline']);
    $loaded=loadAuthorizedPersonalInfo($db,$context); foreach ($values as $field=>$value) { profileTestAssert($loaded[$field]===$value,'Omitted field lost.'); }
    foreach ([['country_code'=>'ZZ'],['specialization'=>"bad\n"],['preferred_language'=>'AR']] as $patch) {
        try { saveOwnedProfilePatch($db,$context,$saved['id'],2,$patch); throw new RuntimeException('Invalid service patch accepted.'); }
        catch (InvalidArgumentException) {}
    }
    $race=['mode'=>'text','id'=>$saved['id'],'version'=>2,'patch'=>['specialization'=>$values['specialization']]];
    profileTestAssert(profileTestRace($users[0],[$race,$race])===['conflict','saved'],'Same-value final-field race did not have one winner.');
    $loaded=loadAuthorizedPersonalInfo($db,$context);
    profileTestAssert((int)$loaded['profile_version']===3 && $loaded['specialization']===$values['specialization'] && $loaded['country_code']==='JO','Final-field race changed omitted data.');
    $public=loadPublicPersonalInfo($db,PublicReadContext::fromPublishedPortfolio($portfolios[0]));
    profileTestAssert(array_intersect(FINAL_PROFILE_FIELDS,array_keys($public))===[],'Private fields leaked through public projection.');
    ob_start(); renderPortfolioPresentation($public,[],[]); $html=ob_get_clean();
    profileTestAssert(!str_contains($html,'Private exact specialization') && !str_contains(json_encode($public), 'specialization'),'Private fields leaked into renderer/JSON.');
    echo "PASS final-profile scoped storage/validation, legacy omission and unchanged public HTML-JSON-contact projection\n";
} catch (Throwable $e) { fwrite(STDERR,'FAIL final-profile '.$phase.': '.($e instanceof PDOException?'PDO constraint/infrastructure failure':$e->getMessage())."\n"); $exit=1; }
finally {
    foreach ($portfolios as $id) { $db->exec("DELETE FROM personal_info WHERE portfolio_id=$id; DELETE FROM portfolios WHERE id=$id"); }
    foreach ($users as $id) { $db->exec("DELETE FROM users WHERE id=$id"); }
    if ($session!==null) { attestationTestEndSession($session); }
    profileTestAssert($initial===profileTestSnapshot($db),'Final-profile tests changed protected data.');
}
if ($exit===0) { echo "PASS final-profile all preexisting data and private fixture cleanup\n"; }
ob_end_flush(); exit($exit);
