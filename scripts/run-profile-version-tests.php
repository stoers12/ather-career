<?php
declare(strict_types=1);
require_once __DIR__ . '/../tests/phase2/support/profile-version-fixtures.php';
ob_start();
final class ProfileCommitFault extends PDO
{
    public string $fault = 'before';
    public function commit(): bool
    {
        if ($this->fault === 'after') { parent::commit(); }
        if ($this->fault === 'false') { return false; }
        throw new PDOException('Synthetic profile commit acknowledgement failure.');
    }
}
final class ProfileRollbackFault extends PDO
{
    public function commit(): bool { throw new PDOException('Synthetic lost connection.'); }
    public function rollBack(): bool { parent::rollBack(); throw new PDOException('Synthetic lost rollback acknowledgement.'); }
}
final class ProfileStatementFault extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'UPDATE personal_info')) { throw new PDOException('Synthetic profile statement failure before COMMIT.'); }
        return parent::prepare($query, $options);
    }
}
$db = getDatabaseConnection(); $initial = profileTestSnapshot($db); $users = []; $portfolios = []; $hiddenLegacy = []; $hiddenProjects = []; $environment = null; $sessionRoot = null; $exit = 0; $phase = 'migration';
$ledger = $db->query("SELECT applied_at FROM schema_migrations WHERE version='017'")->fetchColumn();
try {
    profileTestAssert($ledger !== false && is_file('/.dockerenv'), 'Requires migrated disposable Docker database.');
    $profiles = profileTestCanonicalRows($db->query('SELECT * FROM personal_info')->fetchAll(PDO::FETCH_ASSOC));
    foreach ($profiles as $row) { profileTestAssert((int) $row['profile_version'] === 1, 'Migration upgrade fixture must precede profile editing.'); }
    $db->exec("DELETE FROM schema_migrations WHERE version='017'");
    $db->exec('ALTER TABLE personal_info DROP CHECK chk_personal_info_profile_version, DROP COLUMN profile_version');
    profileTestMigrate(false, 'after-profile-version-alter');
    profileTestAssert($db->query("SELECT applied_at FROM schema_migrations WHERE version='017'")->fetchColumn() === false, 'Failed migration recorded ledger.');
    profileTestAssert($profiles === profileTestCanonicalRows($db->query('SELECT * FROM personal_info')->fetchAll(PDO::FETCH_ASSOC)), 'Upgrade changed profile data/timestamps/ownership.');
    profileTestMigrate(true); profileTestMigrate(true);
    $db->exec('ALTER TABLE personal_info ALTER CHECK chk_personal_info_profile_version NOT ENFORCED'); profileTestMigrate(false);
    $db->exec('ALTER TABLE personal_info ALTER CHECK chk_personal_info_profile_version ENFORCED');
    $db->exec('ALTER TABLE personal_info DROP CHECK chk_personal_info_profile_version'); profileTestMigrate(false);
    $db->exec('ALTER TABLE personal_info ADD CONSTRAINT chk_personal_info_profile_version CHECK (profile_version > 0 AND profile_version <= 2147483647)');
    profileTestMigrate(true);
    $db->prepare("UPDATE schema_migrations SET applied_at=? WHERE version='017'")->execute([$ledger]);
    profileTestAssert($initial === profileTestSnapshot($db), 'Repeated/interrupted migration changed protected data.');
    echo "PASS profile additive populated upgrade, interrupted/repeated recovery, exact enforced check and data preservation\n";

    $phase = 'core'; $environment = TestEnvironment::create(); putenv('ATHERCAR_STORAGE_ROOT=' . $environment->storageRoot);
    $sessionRoot = attestationTestStartSession(); $png = $environment->namespaceRoot . '/synthetic.png'; profileTestPng($png);
    for ($i = 0; $i < 3; $i++) {
        $db->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES ('https://profile-test.invalid/', ?, 'active', 1)")->execute([bin2hex(random_bytes(16))]);
        $users[] = (int) $db->lastInsertId(); $db->prepare('INSERT INTO portfolios (owner_user_id) VALUES (?)')->execute([$users[$i]]); $portfolios[] = (int) $db->lastInsertId();
    }
    [$a, $b, $empty] = $users; $context = profileTestContext($db, $a);
    $saved = saveOwnedProfilePatch($db, $context, null, 0, ['full_name' => 'Synthetic Name', 'location' => 'Keep location', 'public_email_visible' => true, 'public_phone_visible' => true,
        'country_code'=>'JO', 'preferred_language'=>'ar', 'professional_status'=>'student', 'specialization'=>'Exact retained discipline']); $id = $saved['id'];
    $v = 1; $saved = saveOwnedProfilePatch($db, $context, $id, $v, ['full_name' => 'Synthetic Name']);
    profileTestAssert($saved['profile_version'] === 2 && loadAuthorizedPersonalInfo($db, $context)['location'] === 'Keep location', 'Same-value increment or omission failed.');
    foreach ([null, '', '01', '-1', '1.0', 1, true, [], '2147483648', ' 1'] as $token) { profileTestAssert(submittedProfileVersion($token) === null, 'Malformed token accepted.'); }
    foreach (['0', '1', '2147483647'] as $token) { profileTestAssert(submittedProfileVersion($token) === (int) $token, 'Valid token rejected.'); }
    try { saveOwnedProfilePatch($db, $context, $id, 1, ['location' => 'Stale']); throw new RuntimeException('Stale write succeeded.'); } catch (ProfileVersionConflictException) {}
    profileTestAssert(profileTestRace($a, [
        ['mode' => 'patch', 'id' => $id, 'version' => 2, 'patch' => ['full_name' => 'Synthetic Name']],
        ['mode' => 'patch', 'id' => $id, 'version' => 2, 'patch' => ['full_name' => 'Synthetic Name']],
    ]) === ['conflict', 'saved'], 'Concurrent same-value saves did not have one winner.');
    profileTestAssert((int) loadAuthorizedPersonalInfo($db, $context)['profile_version'] === 3, 'Race version increment incorrect.');
    profileTestAssert(profileTestRace($empty, [
        ['mode' => 'patch', 'id' => null, 'version' => 0, 'patch' => ['full_name' => 'First']],
        ['mode' => 'patch', 'id' => null, 'version' => 0, 'patch' => ['full_name' => 'Second']],
    ]) === ['conflict', 'saved'], 'Concurrent absent-profile creation overwrote a winner.');
    echo "PASS profile strict tokens, omission, stale and same-value CAS, genuine concurrent saves and absent creation\n";

    $contextB = profileTestContext($db, $b); saveOwnedProfilePatch($db, $contextB, null, 0, ['full_name' => 'Other account']);
    try { saveOwnedProfilePatch($db, $context, $id, 3, ['location' => 'Foreign']); throw new RuntimeException('Context crossed accounts.'); } catch (AuthorizationDeniedException) {}
    $context = profileTestContext($db, $a);
    $db->exec("UPDATE personal_info SET profile_version=2147483647, updated_at=updated_at WHERE id=$id");
    try { saveOwnedProfilePatch($db, $context, $id, PROFILE_MAX_VERSION, []); throw new RuntimeException('Version wrapped.'); } catch (ProfileVersionConflictException) {}
    foreach ([0, 2147483648] as $version) {
        try { $db->exec("UPDATE personal_info SET profile_version=$version WHERE id=$id"); throw new RuntimeException('Version CHECK missing.'); } catch (PDOException) {}
    }
    $db->exec("UPDATE personal_info SET profile_version=3, updated_at=updated_at WHERE id=$id");
    echo "PASS profile account-bound context and enforced version exhaustion\n";

    $phase = 'media'; $key = profileTestImage($png, $context->portfolioId);
    saveOwnedProfileImage($db, $context, $id, 3, $key);
    saveOwnedProfileImage($db, $context, $id, 4, $key); $v = 5;
    profileTestAssert(profileTestImageExists($key, $context->portfolioId), 'Same-key image save deleted its referenced file.');
    foreach (['statement', 'before', 'false', 'after', 'rollback'] as $mode) {
        $candidate = profileTestImage($png, $context->portfolioId);
        $fault = draftTestConnection(match ($mode) { 'statement' => ProfileStatementFault::class, 'rollback' => ProfileRollbackFault::class, default => ProfileCommitFault::class });
        if ($fault instanceof ProfileCommitFault) { $fault->fault = $mode; }
        $uncertain = false; $failed = false;
        try { saveOwnedProfileImage($fault, $context, $id, $v, $candidate); }
        catch (ProfileWriteUncertainException) { $uncertain = true; $failed = true; }
        catch (RuntimeException) { $failed = true; }
        profileTestAssert($failed, 'Fault result incorrectly saved.');
        profileTestAssert($uncertain === ($mode !== 'statement'), 'Commit outcome classification wrong.');
        profileTestAssert(profileTestImageExists($candidate, $context->portfolioId) === $uncertain, 'Unsafe staged media compensation at ' . $mode . '.');
        profileTestAssert(profileTestImageExists($key, $context->portfolioId), 'Unconfirmed commit retired original image.');
        if ($mode === 'after') { $v++; $key = $candidate; }
    }
    $fault = draftTestConnection(ProfileCommitFault::class); $fault->fault = 'after';
    $current = loadAuthorizedPersonalInfo($db, $context);
    $form = ['action' => 'save_profile', 'profile_id' => (string) $id, 'expected_profile_version' => (string) $v, 'location' => '  uncertain text  '];
    $result = handleAuthorizedProfileAction($fault, $context, $form, [], $current, array_keys(PERSONAL_INFO_FIELD_MAX_LENGTHS), $current);
    profileTestAssert($result['status'] === 503 && $result['profile']['_submitted_profile_version'] === (string) $v
        && $result['profile']['location'] === $form['location'] && str_contains($result['errors'][0], 'may already be saved'), 'Uncertain text outcome lost input/version or claimed rollback.'); $v++;
    $current = loadAuthorizedPersonalInfo($db, $context);
    $form = ['action' => 'remove_profile_image', 'profile_id' => (string) $id, 'expected_profile_version' => (string) $v];
    $result = handleAuthorizedProfileAction($fault, $context, $form, [], $current, array_keys(PERSONAL_INFO_FIELD_MAX_LENGTHS), $current);
    profileTestAssert($result['status'] === 503 && profileTestImageExists($key, $context->portfolioId)
        && loadAuthorizedPersonalInfo($db, $context)['profile_image_path'] === null, 'Uncertain remove retired media or concealed committed result.'); $v++;
    $key = profileTestImage($png, $context->portfolioId); saveOwnedProfileImage($db, $context, $id, $v, $key); $v++;
    $next = profileTestImage($png, $context->portfolioId);
    profileTestAssert(profileTestRace($a, [
        ['mode' => 'patch', 'id' => $id, 'version' => $v, 'patch' => ['location' => 'Racing text']],
        ['mode' => 'image', 'id' => $id, 'version' => $v, 'key' => $next],
    ]) === ['conflict', 'saved'], 'Text/image race failed.'); $v++;
    $afterRace = loadAuthorizedPersonalInfo($db, $context);
    profileTestAssert((int) $afterRace['profile_version'] === $v && profileTestImageExists($afterRace['profile_image_path'], $context->portfolioId)
        && profileTestImageExists($next, $context->portfolioId) === ($afterRace['profile_image_path'] === $next), 'Text/image race lost a referenced file or leaked losing staged file.');
    $previous = $afterRace['profile_image_path'];
    $next = profileTestImage($png, $context->portfolioId);
    profileTestAssert(profileTestRace($a, [
        ['mode' => 'image', 'id' => $id, 'version' => $v, 'key' => null],
        ['mode' => 'image', 'id' => $id, 'version' => $v, 'key' => $next],
    ]) === ['conflict', 'saved'], 'Remove/upload race failed.'); $v++;
    $afterRace = loadAuthorizedPersonalInfo($db, $context);
    profileTestAssert((int) $afterRace['profile_version'] === $v && !profileTestImageExists($previous, $context->portfolioId)
        && profileTestImageExists($next, $context->portfolioId) === ($afterRace['profile_image_path'] === $next), 'Remove/upload race cleanup was unsafe.');
    $legacy = $environment->namespaceRoot . '/legacy'; mkdir($legacy); mkdir($legacy . '/uploads'); mkdir($legacy . '/uploads/profile'); copy($png, $legacy . '/uploads/profile/synthetic.png');
    // Baseline ownership fixtures contain legacy keys without source files. Hide
    // only those references during this global maintenance fixture, then restore
    // their exact paths/timestamps without changing versions or final fingerprints.
    $hiddenLegacy = $db->query("SELECT id, profile_image_path, updated_at FROM personal_info WHERE id<>$id AND profile_image_path LIKE 'uploads/profile/%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($hiddenLegacy as $row) { $db->prepare('UPDATE personal_info SET profile_image_path=NULL, updated_at=updated_at WHERE id=?')->execute([$row['id']]); }
    $hiddenProjects = $db->query("SELECT id, image_path, updated_at FROM projects WHERE image_path LIKE 'uploads/projects/%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($hiddenProjects as $row) { $db->prepare('UPDATE projects SET image_path=NULL, updated_at=updated_at WHERE id=?')->execute([$row['id']]); }
    saveOwnedProfilePatch($db, $context, $id, $v, ['profile_image_path' => 'uploads/profile/synthetic.png']); $v++;
    $fault = draftTestConnection(ProfileCommitFault::class); $fault->fault = 'after';
    try { migrateLegacyMedia($fault, $legacy); throw new RuntimeException('Migration lost commit acknowledgement was accepted.'); }
    catch (ProfileWriteUncertainException) {}
    $v++;
    $migratedKey = loadAuthorizedPersonalInfo($db, $context)['profile_image_path'];
    profileTestAssert(profileTestImageExists($migratedKey, $context->portfolioId) && is_file($legacy . '/uploads/profile/synthetic.png'), 'Uncertain migration deleted potentially referenced media.');
    saveOwnedProfilePatch($db, $context, $id, $v, ['profile_image_path' => 'uploads/profile/synthetic.png']); $v++;
    foreach (['text', 'upload', 'remove'] as $competitor) {
        if ($competitor !== 'text') { saveOwnedProfilePatch($db, $context, $id, $v, ['profile_image_path' => 'uploads/profile/synthetic.png']); $v++; }
        $candidate = $competitor === 'upload' ? profileTestImage($png, $context->portfolioId) : null;
        $input = $competitor === 'text'
            ? ['mode' => 'patch', 'id' => $id, 'version' => $v, 'patch' => ['location' => 'Migration race']]
            : ['mode' => 'image', 'id' => $id, 'version' => $v, 'key' => $candidate, 'legacy_notice' => true];
        $beforeFiles = profileTestPrivateFiles($environment->storageRoot);
        profileTestAssert(profileTestRace($a, [$input, ['mode' => 'migration', 'source' => $legacy]]) === ['conflict', 'saved'], 'Migration/' . $competitor . ' race failed.'); $v++;
        $after = loadAuthorizedPersonalInfo($db, $context);
        profileTestAssert((int) $after['profile_version'] === $v, 'Migration race did not increment exactly once.');
        $migrationWon = is_string($after['profile_image_path']) && $after['profile_image_path'] !== 'uploads/profile/synthetic.png' && $after['profile_image_path'] !== $candidate;
        $added = array_diff_key(profileTestPrivateFiles($environment->storageRoot), $beforeFiles);
        profileTestAssert(count($added) === ($migrationWon ? 2 : 0), 'Migration race leaked staged originals/derivatives after confirmed failure.');
        if ($migrationWon) { profileTestAssert(profileTestImageExists($after['profile_image_path'], $context->portfolioId), 'Migration winner lost referenced image.'); }
        if ($candidate !== null) { profileTestAssert(profileTestImageExists($candidate, $context->portfolioId) === ($after['profile_image_path'] === $candidate), 'Migration/upload race cleanup unsafe.'); }
        profileTestAssert(is_file($legacy . '/uploads/profile/synthetic.png'), 'Race retired legacy source.');
    }
    $afterWriters=loadAuthorizedPersonalInfo($db,$context);
    foreach (['country_code'=>'JO','preferred_language'=>'ar','professional_status'=>'student','specialization'=>'Exact retained discipline'] as $field=>$value) {
        profileTestAssert($afterWriters[$field]===$value,'Text/photo/direct migration race or failed write changed omitted final fields.');
    }
    echo "PASS profile confirmed rollback cleanup, uncertain commit retention, text/image/remove and direct migration races\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL profile core at ' . $phase . ': ' . ($error instanceof PDOException ? 'PDO infrastructure/constraint error' : $error->getMessage()) . "\n"); $exit = 1;
} finally {
    foreach ($hiddenLegacy as $row) { $db->prepare('UPDATE personal_info SET profile_image_path=?, updated_at=? WHERE id=?')->execute([$row['profile_image_path'], $row['updated_at'], $row['id']]); }
    foreach ($hiddenProjects as $row) { $db->prepare('UPDATE projects SET image_path=?, updated_at=? WHERE id=?')->execute([$row['image_path'], $row['updated_at'], $row['id']]); }
    foreach ($portfolios as $portfolio) { $db->exec("DELETE FROM personal_info WHERE portfolio_id=$portfolio; DELETE FROM portfolios WHERE id=$portfolio"); }
    foreach ($users as $user) { $db->exec("DELETE FROM users WHERE id=$user"); }
    if ($sessionRoot !== null) { attestationTestEndSession($sessionRoot); }
    if ($environment !== null) { $environment->tearDown(); }
    profileTestAssert($initial === profileTestSnapshot($db), 'Profile tests changed preexisting rows or left fixtures.');
}
if ($exit === 0) { echo "PASS profile preexisting data preservation and private fixture cleanup\n"; }
ob_end_flush(); exit($exit);
