<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../tests/Support/completion-fixtures.php';
require_once __DIR__ . '/../../tests/Support/owner-journey-fixtures.php';
require_once __DIR__ . '/../../tests/Support/zip-reader.php';
completionFixtureLoadCore(false);
require_once __DIR__ . '/../../includes/account_data_rights.php';
require_once __DIR__ . '/../../includes/account_operations.php';
require_once __DIR__ . '/../../includes/owner_journey.php';
require_once __DIR__ . '/../../includes/public_lifecycle.php';

// L-03 data rights against MySQL: export isolation, immediate public 404,
// cancel restores everything, and the purge leaves no row, file or Auth0 user.
const DATA_RIGHTS_ISSUER = 'https://data-rights-test.invalid/';

final class RecordingEraser implements AccountIdentityEraser
{
    /** @var list<string> */
    public array $erased = [];
    public bool $fail = false;
    public function eraseUser(string $subject): void
    {
        if ($this->fail) { throw new Auth0ManagementException('unreachable'); }
        $this->erased[] = $subject;
    }
}

function dataRightsSession(int $userId, int $authzVersion, int $authenticatedAt): AuthenticatedUserContext
{
    $_SESSION = [INTERNAL_USER_SESSION_KEY => ['internal_user_id' => $userId, 'authz_version' => $authzVersion, 'authenticated_at' => $authenticatedAt, 'last_activity_at' => time()]];
    return AuthenticatedUserContext::fromValidatedUser($userId);
}

/** @return array{user: int, portfolio: int, subject: string, slug: string, marker: string} */
function dataRightsAccount(PDO $db, string $storage, string $name): array
{
    $marker = $name . '-' . bin2hex(random_bytes(6));
    $subject = 'google-oauth2|' . $marker;
    $db->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES (?, ?, 'active', 1)")->execute([DATA_RIGHTS_ISSUER, $subject]);
    $user = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject, is_primary) VALUES (?, ?, ?, 1), (?, ?, ?, 0), (?, ?, ?, 0)')
        ->execute([$user, DATA_RIGHTS_ISSUER, $subject, $user, DATA_RIGHTS_ISSUER, 'auth0|' . $marker, $user, 'https://other-issuer.invalid/', 'foreign|' . $marker]);
    $slug = $marker;
    $db->prepare('INSERT INTO portfolios (owner_user_id, public_slug, is_published, published_at) VALUES (?, ?, 1, CURRENT_TIMESTAMP)')->execute([$user, $slug]);
    $portfolio = (int) $db->lastInsertId();
    journeyFixtureLegacyOwner($db, $user);
    $db->prepare("INSERT INTO personal_info (portfolio_id, full_name, email, profile_image_path) VALUES (?, ?, ?, ?)")
        ->execute([$portfolio, 'Name ' . $marker, $marker . '@example.test', 'portfolios/' . $portfolio . '/profile/original/profile.jpg']);
    $db->prepare("INSERT INTO projects (portfolio_id, title, category, description, is_published) VALUES (?, ?, 'web', ?, 1)")->execute([$portfolio, 'Project ' . $marker, 'Description ' . $marker]);
    $project = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO project_evidence_sources (portfolio_id, project_id, title, url) VALUES (?, ?, ?, ?)')->execute([$portfolio, $project, 'Source ' . $marker, 'https://example.test/' . $marker]);
    $db->prepare("INSERT INTO experiences (portfolio_id, experience_type, role_title, organization, start_month, is_current) VALUES (?, 'employment', ?, 'Org', '2024-01', 1)")->execute([$portfolio, 'Role ' . $marker]);
    $db->prepare('INSERT INTO skills (portfolio_id, skill_name) VALUES (?, ?)')->execute([$portfolio, 'Skill ' . $marker]);
    $db->prepare("INSERT INTO messages (recipient_portfolio_id, name, email, message) VALUES (?, 'Visitor', 'visitor@example.test', ?)")->execute([$portfolio, 'Message ' . $marker]);
    $db->prepare("INSERT INTO policy_acceptances (user_id, document, version) VALUES (?, 'privacy', '1.0'), (?, 'terms', '1.0')")->execute([$user, $user]);
    foreach (['profile/original/profile.jpg', 'profile/presentation/profile.webp', 'projects/project.png', 'project/presentation/project.webp'] as $file) {
        $path = $storage . '/portfolios/' . $portfolio . '/' . $file;
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0700, true); }
        file_put_contents($path, 'image ' . $marker . ' ' . $file);
    }
    return ['user' => $user, 'portfolio' => $portfolio, 'subject' => $subject, 'slug' => $slug, 'marker' => $marker];
}

/** @return array<string, list<array<string, mixed>>> every account row, by table */
function dataRightsRows(PDO $db, int $user, int $portfolio): array
{
    $rows = [];
    foreach (ACCOUNT_DATA_USER_TABLES as $table => $column) { $rows[$table] = accountTableRows($db, $table, $column, $user); }
    foreach (ACCOUNT_DATA_PORTFOLIO_TABLES as $table => $column) { $rows[$table] = accountTableRows($db, $table, $column, $portfolio); }
    return $rows;
}

function dataRightsExport(PDO $db, int $user): array
{
    $stream = fopen('php://memory', 'w+b');
    writeAccountExport($db, $user, 'en', $stream);
    rewind($stream);
    return testReadZipEntries((string) stream_get_contents($stream));
}

ob_start();
$db = getDatabaseConnection(); $users = []; $exit = 0;
$storage = sys_get_temp_dir() . '/data-rights-storage-' . bin2hex(random_bytes(8)); mkdir($storage, 0700);
putenv('ATHERCAR_STORAGE_ROOT=' . $storage);
$sessionRoot = completionSessionRoot();
try {
    // Every table that references an account is exported and purged, or knowingly excluded.
    $references = $db->query("SELECT DISTINCT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IN ('users', 'portfolios', 'projects')")->fetchAll(PDO::FETCH_COLUMN);
    $covered = [...array_keys(ACCOUNT_DATA_USER_TABLES), ...array_keys(ACCOUNT_DATA_PORTFOLIO_TABLES), ...ACCOUNT_DATA_EXCLUDED_TABLES];
    completionAssert(array_diff($references, $covered) === [], 'A table references an account but is not covered: ' . implode(', ', array_diff($references, $covered)));
    $purged = ACCOUNT_PURGE_ORDER; $owned = [...array_keys(ACCOUNT_DATA_USER_TABLES), ...array_keys(ACCOUNT_DATA_PORTFOLIO_TABLES)]; sort($purged); sort($owned);
    completionAssert($purged === $owned, 'The purge order does not delete exactly the exported tables.');
    echo "PASS every table that references an account is exported and purged, or explicitly excluded\n";

    $a = dataRightsAccount($db, $storage, 'alpha'); $users[] = $a['user'];
    $b = dataRightsAccount($db, $storage, 'bravo'); $users[] = $b['user'];
    $db->prepare("INSERT INTO registration_invitations (code_digest, label, expires_at, used_at, used_by_user_id) VALUES (?, 'Alpha – designer', CURRENT_TIMESTAMP + INTERVAL 30 DAY, CURRENT_TIMESTAMP, ?)")
        ->execute([hash('sha256', $a['marker']), $a['user']]);
    $invitation = (int) $db->lastInsertId();

    // Export: everything of A, nothing of B.
    $entries = dataRightsExport($db, $a['user']);
    $document = json_decode($entries['data.json'], true, 64, JSON_THROW_ON_ERROR);
    completionAssert($document['format'] === 'ather-account-export' && array_diff($owned, array_keys($document['data'])) === [], 'Export does not list every table.');
    foreach (['users', 'user_identities', 'portfolios', 'personal_info', 'projects', 'project_evidence_sources', 'experiences', 'skills', 'messages', 'policy_acceptances'] as $table) {
        completionAssert(count($document['data'][$table]) >= 1, "Export is missing {$table}.");
    }
    $archive = implode("\n", $entries);
    completionAssert(substr_count($entries['data.json'], $a['marker']) >= 8 && str_contains($entries['readable.html'], 'Project ' . $a['marker']), 'Export lacks the owner data.');
    completionAssert(!str_contains($archive, $b['marker']), 'Export contains another account.');
    $images = array_values(array_filter(array_keys($entries), static fn (string $name): bool => str_starts_with($name, 'images/')));
    sort($images);
    completionAssert($images === ['images/profile/profile.jpg', 'images/projects/project.png'], 'Export images are wrong: ' . implode(', ', $images));
    completionAssert($entries['images/projects/project.png'] === 'image ' . $a['marker'] . ' projects/project.png', 'Export image bytes changed.');
    echo "PASS export holds every row and original image of the account and nothing of another account\n";

    // A stale sign-in or a wrong confirmation changes nothing.
    $before = completionSnapshot($db);
    foreach ([[ACCOUNT_RECENT_AUTHENTICATION_SECONDS + 5, $a['slug'], 'reauthentication_required'], [0, 'not-the-slug', 'confirmation_mismatch'], [0, null, 'confirmation_mismatch']] as [$age, $typed, $reason]) {
        try { requestAccountDeletion($db, dataRightsSession($a['user'], 1, time() - $age), $typed); completionAssert(false, 'Deletion was accepted without ' . $reason); }
        catch (AccountDataRightsException $exception) { completionAssert($exception->reason === $reason, 'Wrong refusal ' . $exception->reason); }
    }
    completionAssert($before === completionSnapshot($db) && !$db->inTransaction(), 'A refused deletion changed data.');
    echo "PASS deletion needs a sign-in from the last 10 minutes and the typed page name\n";

    // Request: hidden at once, signed out everywhere, 30 days to cancel.
    completionAssert(resolvePublicReadContext($db, $a['slug']) !== null, 'Fixture A is not public.');
    $rowsBefore = dataRightsRows($db, $a['user'], $a['portfolio']);
    $schedule = requestAccountDeletion($db, dataRightsSession($a['user'], 1, time()), '  ' . strtoupper($a['slug']) . ' ');
    completionAssert(strtotime($schedule['due_at']) - strtotime($schedule['requested_at']) === ACCOUNT_DELETION_GRACE_DAYS * 86400, 'Grace period is not 30 days.');
    completionAssert(resolvePublicReadContext($db, $a['slug']) === null, 'Public page still resolves after the request.');
    completionAssert(resolvePublicReadContext($db, $b['slug'])?->portfolioId === $b['portfolio'], 'Another account lost its public page.');
    completionDenied($db, static function () use ($db, $a): void { dataRightsSession($a['user'], 1, time()); requireAuthenticatedUser($db); }, AuthorizationDeniedException::class);
    $signedIn = dataRightsSession($a['user'], 2, time());
    completionAssert(ownerJourneyDestination($db, $signedIn) === '/owner_account_deletion.php', 'A scheduled account reached the workspace.');
    try { requestAccountDeletion($db, $signedIn, $a['slug']); completionAssert(false, 'A second request was accepted.'); }
    catch (AccountDataRightsException $exception) { completionAssert($exception->reason === 'already_scheduled', 'Wrong second-request refusal.'); }
    completionAssert(isset(dataRightsExport($db, $a['user'])['data.json']), 'A scheduled account cannot export its data.');
    echo "PASS a request hides the page at once, signs out every session and keeps export available\n";

    // Cancel: everything as it was, except the bumped authorization version.
    cancelAccountDeletion($db, $signedIn);
    $rowsAfter = dataRightsRows($db, $a['user'], $a['portfolio']);
    $rowsBefore['users'][0]['authz_version'] = 2;
    completionAssert($rowsAfter === $rowsBefore, 'Cancel did not restore every row.');
    completionAssert(resolvePublicReadContext($db, $a['slug'])?->portfolioId === $a['portfolio'] && ownerJourneyDestination($db, $signedIn) === '/owner.php', 'Cancel did not restore the page and workspace.');
    try { cancelAccountDeletion($db, $signedIn); completionAssert(false, 'Cancel without a request succeeded.'); }
    catch (AccountDataRightsException $exception) { completionAssert($exception->reason === 'not_scheduled', 'Wrong cancel refusal.'); }
    echo "PASS cancelling within the grace period restores everything\n";

    // Purge: nothing before the due date.
    requestAccountDeletion($db, dataRightsSession($a['user'], 2, time()), $a['slug']);
    $eraser = new RecordingEraser();
    $result = purgeDueAccountDeletions($db, $eraser, DATA_RIGHTS_ISSUER);
    completionAssert(!in_array($a['user'], $result['deleted'], true) && $eraser->erased === [], 'An account was purged before its due date.');
    $db->prepare('UPDATE users SET deletion_requested_at = CURRENT_TIMESTAMP - INTERVAL 31 DAY, deletion_due_at = CURRENT_TIMESTAMP - INTERVAL 1 DAY WHERE id = ?')->execute([$a['user']]);

    // A failing Auth0 call keeps every row and file, closes the account, and is retried.
    $eraser->fail = true;
    $result = purgeDueAccountDeletions($db, $eraser, DATA_RIGHTS_ISSUER);
    completionAssert($result['failed'] === [$a['user']] && !$db->inTransaction(), 'An Auth0 failure was not reported.');
    completionAssert(count(dataRightsRows($db, $a['user'], $a['portfolio'])['projects']) === 1 && is_dir($storage . '/portfolios/' . $a['portfolio']), 'Data was deleted while the Auth0 account survived.');
    completionAssert(accountOperationalState($db, $a['user'])['account_status'] === 'disabled', 'A failed purge left the account usable.');
    try { cancelAccountDeletion($db, dataRightsSession($a['user'], 4, time())); completionAssert(false, 'A purge in progress was cancelled.'); }
    catch (AccountDataRightsException $exception) { completionAssert($exception->reason === 'not_scheduled', 'Wrong in-purge cancel refusal.'); }

    $eraser->fail = false;
    $bBefore = dataRightsRows($db, $b['user'], $b['portfolio']);
    $result = purgeDueAccountDeletions($db, $eraser, DATA_RIGHTS_ISSUER);
    completionAssert($result === ['deleted' => [$a['user']], 'failed' => []], 'The due account was not purged.');
    sort($eraser->erased);
    completionAssert($eraser->erased === ['auth0|' . $a['marker'], $a['subject']], 'Auth0 deletions are wrong: ' . implode(', ', $eraser->erased));
    foreach (dataRightsRows($db, $a['user'], $a['portfolio']) as $table => $rows) { completionAssert($rows === [], "Rows remain in {$table}."); }
    completionAssert(!file_exists($storage . '/portfolios/' . $a['portfolio']) && is_dir($storage . '/portfolios/' . $b['portfolio']), 'Media files were not removed exactly.');
    $tombstone = $db->query('SELECT * FROM account_deletions WHERE user_id = ' . $a['user'])->fetchAll(PDO::FETCH_ASSOC);
    completionAssert(count($tombstone) === 1 && array_keys($tombstone[0]) === ['user_id', 'requested_at', 'deleted_at'], 'The deletion record is wrong.');
    $spent = $db->query('SELECT label, used_by_user_id, used_at FROM registration_invitations WHERE id = ' . $invitation)->fetch(PDO::FETCH_ASSOC);
    completionAssert($spent['label'] === null && $spent['used_by_user_id'] === null && $spent['used_at'] !== null, 'The spent invitation still names the person.');
    completionAssert(dataRightsRows($db, $b['user'], $b['portfolio']) === $bBefore && resolvePublicReadContext($db, $b['slug']) !== null, 'The purge touched another account.');
    completionAssert(purgeDueAccountDeletions($db, $eraser, DATA_RIGHTS_ISSUER) === ['deleted' => [], 'failed' => []], 'A second run found work.');
    echo "PASS the purge deletes the Auth0 users, every row and file, keeps only id and dates, and retries after a failure\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL ' . get_class($exception) . ': ' . $exception->getMessage() . "\n");
    $exit = 1;
} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
    foreach ($users as $id) {
        $portfolio = accountPortfolioId($db, $id);
        foreach (ACCOUNT_PURGE_ORDER as $table) {
            $key = isset(ACCOUNT_DATA_USER_TABLES[$table]) ? $id : $portfolio;
            if ($key !== null) { $db->prepare('DELETE FROM `' . $table . '` WHERE `' . (ACCOUNT_DATA_USER_TABLES[$table] ?? ACCOUNT_DATA_PORTFOLIO_TABLES[$table]) . '` = ?')->execute([$key]); }
        }
        $db->prepare('DELETE FROM account_deletions WHERE user_id = ?')->execute([$id]);
    }
    if (isset($invitation)) { $db->prepare('DELETE FROM registration_invitations WHERE id = ?')->execute([$invitation]); }
    if (is_dir($storage)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($storage);
    }
    completionEndSession($sessionRoot);
}
exit($exit);
