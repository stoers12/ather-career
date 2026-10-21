<?php
declare(strict_types=1);
require_once __DIR__ . '/../tests/phase2/support/profile-version-fixtures.php';
require_once __DIR__ . '/../includes/portfolio_presentation.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';
require_once __DIR__ . '/../includes/runtime_readiness.php';
ob_start();

final class ContactCommitFault extends PDO
{
    public string $fault = 'before';
    public function commit(): bool
    {
        if ($this->fault === 'after') { parent::commit(); }
        if ($this->fault === 'false') { return false; }
        throw new PDOException('Synthetic contact commit acknowledgement failure.');
    }
}
final class ContactStatementFault extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'UPDATE personal_info')) { throw new PDOException('Synthetic contact statement failure.'); }
        return parent::prepare($query, $options);
    }
}
function contactFlags(PDO $db, int $id): array
{
    return array_map('intval', $db->query("SELECT public_email_visible,public_phone_visible FROM personal_info WHERE id=$id")->fetch(PDO::FETCH_NUM));
}
function contactDenied(PDO $db, callable $action, string $class): void
{
    $before = profileTestSnapshot($db); $caught = false;
    try { $action(); } catch (Throwable $e) { if (!$e instanceof $class) { throw $e; } $caught = true; }
    profileTestAssert($caught && $before === profileTestSnapshot($db), 'Denied contact patch changed data or wrong failure.');
}
$db = getDatabaseConnection(); $initial = profileTestSnapshot($db); $users = []; $portfolios = []; $session = null; $exit = 0; $phase = 'migration';
$ledger = $db->query("SELECT applied_at FROM schema_migrations WHERE version='021'")->fetchColumn();
$originalFlags = $db->query('SELECT id,public_email_visible,public_phone_visible FROM personal_info')->fetchAll(PDO::FETCH_ASSOC);
try {
    profileTestAssert(is_file('/.dockerenv') && $ledger !== false && runtimeMigrationLedgerMatches($db), 'Requires fully migrated disposable namespace.');
    $session = attestationTestStartSession();
    for ($i = 0; $i < 3; $i++) {
        $db->prepare("INSERT INTO users(oidc_issuer,oidc_subject,account_status,authz_version) VALUES('https://contact-types-test.invalid/',?,'active',1)")->execute([bin2hex(random_bytes(16))]);
        $users[] = (int) $db->lastInsertId(); $db->prepare('INSERT INTO portfolios(owner_user_id) VALUES(?)')->execute([$users[$i]]); $portfolios[] = (int) $db->lastInsertId();
        if ($i === 2) { continue; }
        $db->prepare("INSERT INTO personal_info(portfolio_id,full_name,email,phone_primary,phone_secondary,public_contact_visible,updated_at) VALUES(?,'Synthetic Contact Owner','contact-types@example.test','+962 79 234 5678','+962 78 987 6543',?,'2025-01-01 00:00:00')")->execute([$portfolios[$i], $i]);
    }
    $ids = array_map('intval', $db->query('SELECT id FROM personal_info WHERE portfolio_id IN (' . implode(',', $portfolios) . ') ORDER BY portfolio_id')->fetchAll(PDO::FETCH_COLUMN));
    profileTestAssert(contactFlags($db,$ids[0])===[0,0] && contactFlags($db,$ids[1])===[0,0], 'Fresh Profiles did not default private independently of aggregate.');
    $db->exec("DELETE FROM schema_migrations WHERE version='021'");
    $db->exec('ALTER TABLE personal_info DROP CHECK chk_profile_public_email_visible, DROP CHECK chk_profile_public_phone_visible, DROP COLUMN public_email_visible, DROP COLUMN public_phone_visible');
    profileTestAssert(!runtimeSchemaIsCompatible($db) && !runtimeMigrationLedgerMatches($db), 'Readiness accepted the old contact schema.');
    $legacy = $db->query('SELECT * FROM personal_info ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $db->exec('ALTER TABLE personal_info ALTER COLUMN public_contact_visible SET DEFAULT 1'); profileTestMigrate(false);
    profileTestAssert((int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='personal_info' AND column_name IN ('public_email_visible','public_phone_visible')")->fetchColumn()===0,'Incompatible legacy source added new columns.');
    $db->exec('ALTER TABLE personal_info ALTER COLUMN public_contact_visible SET DEFAULT 0');
    profileTestMigrate(false, 'after-contact-types-add');
    profileTestAssert((int) $db->query('SELECT COUNT(*) FROM personal_info WHERE public_email_visible IS NOT NULL OR public_phone_visible IS NOT NULL')->fetchColumn() === 0, 'Staged columns invented exposure.');
    $db->exec("UPDATE personal_info SET public_email_visible=1,updated_at=updated_at WHERE id={$ids[0]}"); profileTestMigrate(false);
    $db->exec("UPDATE personal_info SET public_email_visible=NULL,updated_at=updated_at WHERE id={$ids[0]}");
    $db->exec('ALTER TABLE personal_info ALTER COLUMN public_phone_visible SET DEFAULT 1'); profileTestMigrate(false);
    $db->exec('ALTER TABLE personal_info ALTER COLUMN public_phone_visible SET DEFAULT NULL');
    $db->exec('ALTER TABLE personal_info DROP CHECK chk_profile_public_phone_visible, DROP COLUMN public_phone_visible'); profileTestMigrate(false);
    $db->exec('ALTER TABLE personal_info ADD COLUMN public_phone_visible TINYINT(1) NULL DEFAULT NULL, ADD CONSTRAINT chk_profile_public_phone_visible CHECK(public_phone_visible IN(0,1))');
    profileTestMigrate(false, 'after-contact-types-backfill');
    profileTestAssert(contactFlags($db, $ids[0]) === [0,0] && contactFlags($db, $ids[1]) === [1,1], 'Legacy exposure was not copied exactly.');
    foreach ([0,1] as $legacyVisibility) {
        $public=loadPublicPersonalInfo($db,PublicReadContext::fromPublishedPortfolio($portfolios[$legacyVisibility]));
        profileTestAssert($public['email']===($legacyVisibility?'contact-types@example.test':null) && $public['phone_primary']===($legacyVisibility?'+962 79 234 5678':null), 'Migration changed previous public contact exposure.');
    }
    $rows = $db->query('SELECT * FROM personal_info ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) { unset($row['public_email_visible'], $row['public_phone_visible']); } unset($row);
    profileTestAssert($rows === $legacy, 'Migration changed text, ownership, profile version or timestamps.');
    // Model independent choices after an interrupted backfill: never recompute them.
    $db->exec("UPDATE personal_info SET public_email_visible=0,public_phone_visible=1,updated_at=updated_at WHERE id={$ids[1]}");
    profileTestMigrate(false, 'after-contact-types-finalize'); profileTestMigrate(true); profileTestMigrate(true);
    profileTestAssert(contactFlags($db, $ids[1]) === [0,1], 'Recovery overwrote independent choices.');
    $db->exec("DELETE FROM schema_migrations WHERE version='021'"); profileTestMigrate(true);
    profileTestAssert(contactFlags($db, $ids[1]) === [0,1], 'Lost ledger acknowledgement repeated backfill.');
    $db->exec('ALTER TABLE personal_info ALTER CHECK chk_profile_public_email_visible NOT ENFORCED'); profileTestMigrate(false);
    $db->exec('ALTER TABLE personal_info ALTER CHECK chk_profile_public_email_visible ENFORCED'); profileTestMigrate(true);
    $db->prepare("UPDATE schema_migrations SET applied_at=? WHERE version='021'")->execute([$ledger]);
    foreach ($originalFlags as $row) { $db->prepare('UPDATE personal_info SET public_email_visible=?,public_phone_visible=?,updated_at=updated_at WHERE id=?')->execute([$row['public_email_visible'],$row['public_phone_visible'],$row['id']]); }
    echo "PASS contact-types populated migration preserves exposure and exact rows; interrupted add/backfill/finalize and lost ledger never replay initialized choices; partial ambiguous incompatible schemas deny\n";

    $phase = 'defaults'; $empty=profileTestContext($db,$users[2]);
    $created=saveOwnedProfilePatch($db,$empty,null,0,['full_name'=>'New private owner']);
    profileTestAssert(contactFlags($db,$created['id'])===[0,0] && loadAuthorizedPersonalInfo($db,$empty)['email']===null && loadAuthorizedPersonalInfo($db,$empty)['phone_primary']===null, 'New Profile imported contact values or exposed them.');
    profileTestAssert(runtimeSchemaIsCompatible($db) && runtimeMigrationLedgerMatches($db), 'Final contact schema is not ready.');
    echo "PASS contact-types fresh Profile private defaults and runtime schema/ledger readiness without contact imports\n";
    $phase = 'projection'; $context = profileTestContext($db, $users[0]); $id = $ids[0];
    $publicContext = PublicReadContext::fromPublishedPortfolio($portfolios[0]);
    $version = static fn (): int => (int) loadAuthorizedPersonalInfo($db, $context)['profile_version'];
    foreach ([[0,0],[1,0],[0,1],[1,1]] as [$email, $phone]) {
        saveOwnedProfilePatch($db, $context, $id, $version(), ['public_email_visible'=>$email, 'public_phone_visible'=>$phone]);
        profileTestAssert(contactFlags($db, $id) === [$email,$phone], 'Independent save mismatch.');
        $public = loadPublicPersonalInfo($db, $publicContext);
        profileTestAssert($public['email'] === ($email ? 'contact-types@example.test' : null)
            && $public['phone_primary'] === ($phone ? '+962 79 234 5678' : null)
            && !array_key_exists('phone_secondary', $public), 'Public SQL exposed a hidden contact.');
        foreach ([[], ['contact_form_error'=>'Correct the fields.', 'contact_values'=>['name'=>'Synthetic visitor']]] as $options) {
            ob_start(); renderPortfolioPresentation($public, [], [], $options); $html = (string) ob_get_clean();
            profileTestAssert(str_contains($html,'contact-types@example.test') === (bool) $email
                && str_contains($html,'mailto:contact-types@example.test') === (bool) $email
                && str_contains($html,'+962 79 234 5678') === (bool) $phone
                && str_contains($html,'tel:+962792345678') === (bool) $phone
                && !str_contains($html,'+962 78 987 6543'), 'Public HTML/link/error/metadata disclosure.');
        }
        ob_start(); renderPortfolioPresentation(loadAuthorizedPersonalInfo($db,$context),[],[],['preview'=>true]); $preview=(string)ob_get_clean();
        profileTestAssert(str_contains($preview,'contact-types@example.test') && str_contains($preview,'tel:+962792345678'), 'Private owner preview lost saved contacts.');
    }
    echo "PASS contact-types all four stored combinations independently filter public HTML mailto tel and contact-error output; secondary private and owner preview preserved\n";

    $phase = 'patch and authority';
    $fields = array_values(array_diff(AUTHORIZED_PERSONAL_INFO_FIELDS, ['public_email_visible','public_phone_visible','profile_image_path']));
    $action = static function (array $input) use ($db,$context,$id,$fields): array {
        $current=loadAuthorizedPersonalInfo($db,$context);
        return handleAuthorizedProfileAction($db,$context,['action'=>'save_profile','profile_id'=>(string)$id,'expected_profile_version'=>(string)$current['profile_version'],...$input],[],$current,$fields,$current);
    };
    $action(['public_phone_visible'=>'0']); profileTestAssert(contactFlags($db,$id)===[1,0], 'Explicit phone off changed email.');
    $action(['public_email_present'=>'1']); profileTestAssert(contactFlags($db,$id)===[0,0], 'Unchecked email marker changed phone.');
    $action(['public_phone_visible'=>'1']); $beforeFlags=contactFlags($db,$id);
    $action(['professional_title'=>'Only text']);
    saveOwnedProfileImage($db,$context,$id,$version(),null);
    profileTestAssert(contactFlags($db,$id)===$beforeFlags, 'Text/photo action changed omitted flags.');
    foreach (['public_email_visible','public_phone_visible'] as $field) {
        foreach ([null, true, 1, [], '01', '', 'yes'] as $bad) {
            $before=profileTestSnapshot($db); $result=$action(['professional_title'=>'Must not save',$field=>$bad]);
            profileTestAssert($result['status']===422 && $before===profileTestSnapshot($db), 'Malformed contact form partially saved.');
        }
        contactDenied($db,fn()=>saveOwnedProfilePatch($db,$context,$id,$version(),[$field=>'1']),InvalidArgumentException::class);
    }
    foreach (['public_email_present','public_phone_present'] as $field) {
        $before=profileTestSnapshot($db); $result=$action([$field=>'0']);
        profileTestAssert($result['status']===422 && $before===profileTestSnapshot($db), 'Malformed marker wrote data.');
    }
    foreach ([['public_contact_visible'=>'1'],['public_contact_visible'=>'0'],['public_contact_present'=>'1'],['public_contact_visible'=>['1']]] as $old) {
        $before=profileTestSnapshot($db); $result=$action(['full_name'=>'Retained outdated input',...$old]);
        profileTestAssert($result['status']===409 && $result['profile']['full_name']==='Retained outdated input'
            && str_contains(implode(' ',$result['errors']),'outdated') && $before===profileTestSnapshot($db), 'Old aggregate form inferred choices or lost input.');
    }
    contactDenied($db,fn()=>saveOwnedProfilePatch($db,$context,$id,$version(),['public_contact_visible'=>true]),InvalidArgumentException::class);
    contactDenied($db,fn()=>saveOwnedProfilePatch($db,$context,$id,$version()-1,['public_email_visible'=>1]),ProfileVersionConflictException::class);
    profileTestContext($db,$users[1]);
    contactDenied($db,fn()=>saveOwnedProfilePatch($db,$context,$id,$version(),['public_phone_visible'=>0]),AuthorizationDeniedException::class);
    $context=profileTestContext($db,$users[0]);
    // A leftover aggregate bit cannot authorize public contact exposure.
    $db->exec("UPDATE personal_info SET public_contact_visible=1,updated_at=updated_at WHERE id=$id");
    saveOwnedProfilePatch($db,$context,$id,$version(),['public_email_visible'=>0,'public_phone_visible'=>0]);
    profileTestAssert(loadPublicPersonalInfo($db,$publicContext)['email']===null && loadPublicPersonalInfo($db,$publicContext)['phone_primary']===null, 'Legacy aggregate still controlled public exposure.');
    echo "PASS contact-types omission explicit per-type off malformed atomic rejection retired aggregate409 retained input stale and cross-account denial; frozen aggregate ignored\n";

    $phase='concurrency'; $v=$version();
    profileTestAssert(profileTestRace($users[0],[
        ['mode'=>'patch','id'=>$id,'version'=>$v,'patch'=>['public_email_visible'=>1]],
        ['mode'=>'patch','id'=>$id,'version'=>$v,'patch'=>['public_phone_visible'=>1]],
    ])===['conflict','saved'],'Independent controls bypassed atomic Profile CAS.');
    $flags=contactFlags($db,$id); profileTestAssert(array_sum($flags)===1 && $version()===$v+1,'Concurrent contact save silently combined choices.');
    $v=$version(); $name=loadAuthorizedPersonalInfo($db,$context)['full_name'];
    profileTestAssert(profileTestRace($users[0],[
        ['mode'=>'patch','id'=>$id,'version'=>$v,'patch'=>['public_email_visible'=>1-$flags[0]]],
        ['mode'=>'patch','id'=>$id,'version'=>$v,'patch'=>['full_name'=>'Concurrent text']],
    ])===['conflict','saved'],'Text/contact race overwrote a winner.');
    $after=loadAuthorizedPersonalInfo($db,$context);
    profileTestAssert((((int)$after['public_email_visible']!==$flags[0]) !== ($after['full_name']!==$name)),'Text/contact race partially merged losing patch.');
    echo "PASS contact-types genuine same-version contact/contact and text/contact races have one winner with atomic CAS and no implicit merge\n";

    $phase='transaction outcomes';
    $failed=draftTestConnection(ContactStatementFault::class);
    contactDenied($db,fn()=>saveOwnedProfilePatch($failed,$context,$id,$version(),['public_email_visible'=>1,'public_phone_visible'=>1]),PDOException::class);
    foreach (['before','false','after'] as $fault) {
        saveOwnedProfilePatch($db,$context,$id,$version(),['public_email_visible'=>0,'public_phone_visible'=>1]);
        $v=$version(); $connection=draftTestConnection(ContactCommitFault::class); $connection->fault=$fault; $caught=false;
        try { saveOwnedProfilePatch($connection,$context,$id,$v,['public_email_visible'=>1,'public_phone_visible'=>0]); }
        catch (ProfileWriteUncertainException) { $caught=true; }
        profileTestAssert($caught && contactFlags($db,$id)===($fault==='after'?[1,0]:[0,1]) && $version()===($fault==='after'?$v+1:$v),'Uncertain contact write replayed, compensated or misclassified.');
    }
    $current=loadAuthorizedPersonalInfo($db,$context); $v=(int)$current['profile_version'];
    $connection=draftTestConnection(ContactCommitFault::class); $connection->fault='after';
    $result=handleAuthorizedProfileAction($connection,$context,['action'=>'save_profile','profile_id'=>(string)$id,'expected_profile_version'=>(string)$v,
        'full_name'=>'Retained uncertain contact input','public_email_visible'=>'0','public_phone_visible'=>'1'],[],$current,$fields,$current);
    profileTestAssert($result['status']===503 && $result['profile']['full_name']==='Retained uncertain contact input'
        && $result['profile']['_submitted_profile_version']===(string)$v && !$result['profile']['public_email_visible'] && $result['profile']['public_phone_visible']
        && contactFlags($db,$id)===[0,1] && $version()===$v+1, 'Uncertain form lost original input/token, replayed or compensated.');
    echo "PASS contact-types definite rollback and before false after COMMIT uncertainty preserve actual independent state without replay or compensation\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL contact-types ' . $phase . ': ' . ($e instanceof PDOException ? 'PDO constraint/infrastructure failure' : $e->getMessage()) . "\n"); $exit = 1;
} finally {
    foreach ($portfolios as $id) { $db->exec("DELETE FROM personal_info WHERE portfolio_id=$id; DELETE FROM portfolios WHERE id=$id"); }
    foreach ($users as $id) { $db->exec("DELETE FROM users WHERE id=$id"); }
    if ($session !== null) { attestationTestEndSession($session); }
    if ($exit === 0) { profileTestAssert($initial === profileTestSnapshot($db), 'Contact tests changed protected data or ledger.'); }
}
if ($exit === 0) { echo "PASS contact-types complete protected-data ledger session process and fixture cleanup\n"; }
ob_end_flush(); exit($exit);
