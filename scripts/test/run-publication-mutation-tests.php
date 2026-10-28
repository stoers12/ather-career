<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../../tests/phase2/support/completion-fixtures.php';
require_once __DIR__.'/../../tests/phase2/support/publication-mutation-fixtures.php';

if (($argv[1] ?? '') === '--worker') {
    $input = json_decode(base64_decode($argv[2], true), true, 16, JSON_THROW_ON_ERROR);
    $root = completionSessionRoot(); $db = completionConnection(PublicationFixtureDatabase::class);
    try {
        completionSession($input['user'], false);
        $context = publicationContext($input['user'], $input['portfolio']);
        $db->pause = $input['pause'] ?? '';
        $db->snapshot = $input['snapshot'] ?? false;
        if (isset($input['sql'])) {
            $db->barrier('before_user'); $db->prepare($input['sql'])->execute($input['parameters']);
        } elseif ($input['action'] === 'profile') {
            $profile = loadAuthorizedPersonalInfo($db, $context);
            saveOwnedProfilePatch($db, $context, (int)$profile['id'], (int)$profile['profile_version'], ['full_name'=>'Concurrent current name']);
        } else { publicationMutation($db, $context, $input['action'], $input['slug'] ?? null); }
        echo "saved\n";
    } catch (AuthorizationDeniedException) { echo "denied\n"; }
    catch (PublicLifecycleConflictException) { echo "conflict\n"; }
    finally { completionEndSession($root); }
    exit;
}

completionFixtureLoadCore(true);
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/owner_session.php';
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

ob_start();
$db = getDatabaseConnection(); $initial = completionSnapshot($db); $users = []; $portfolios = [];
$seal = $db->query('SELECT * FROM owner_journey_cutover')->fetchAll(PDO::FETCH_ASSOC);
$environment = TestEnvironment::create(); $root = $environment->namespaceRoot;
mkdir($root.'/sessions', 0700); session_save_path($root.'/sessions');
session_start(['use_cookies'=>0, 'cache_limiter'=>'']);
$workers = []; $server = null; $exit = 0; $stage = 'setup';

function publicationNew(bool $legacy = true, bool $profile = true): AuthorizedPortfolioContext
{
    global $db, $users, $portfolios;
    $user = completionNewUser($db); $users[] = $user;
    $db->prepare('INSERT INTO portfolios(owner_user_id) VALUES (?)')->execute([$user]);
    $portfolio = (int)$db->lastInsertId(); $portfolios[] = $portfolio;
    if ($legacy) { journeyFixtureLegacyOwner($db, $user); }
    if ($profile) { $db->prepare('INSERT INTO personal_info(portfolio_id,full_name) VALUES (?,?)')->execute([$portfolio, 'Current name']); }
    return publicationContext($user, $portfolio);
}
function publicationDenied(AuthorizedPortfolioContext $context, ?callable $alter = null): void
{
    global $db;
    foreach (['set_slug','publish','unpublish'] as $action) {
        completionSession($context->userId, false);
        if ($alter !== null) { $alter(); }
        completionDenied($db, static fn()=>publicationMutation($db, $context, $action, 'denied-slug'), AuthorizationDeniedException::class);
        completionAssert(!$db->inTransaction(), 'Denial left an open transaction.');
    }
}
function publicationStart(array $input): array
{
    global $workers;
    $worker = publicationWorker($input); $workers[] = $worker; return $worker;
}
function publicationInput(AuthorizedPortfolioContext $context, string $action, string $pause = ''): array
{
    return ['user'=>$context->userId, 'portfolio'=>$context->portfolioId, 'action'=>$action, 'pause'=>$pause, 'snapshot'=>true];
}
function publicationHttp(Client $client, array $form, int $expected, ?string $location = null): string
{
    $response = $client->post('/owner_publication.php', ['form_params'=>$form]);
    completionAssert($response->getStatusCode() === $expected, 'Publication HTTP status '.$response->getStatusCode().' expected '.$expected);
    completionAssert($response->getHeaderLine('Location') === ($location ?? ''), 'Publication HTTP destination mismatch.');
    completionAssert(str_contains($response->getHeaderLine('Cache-Control'), 'no-store'), 'HTTP lost no-store.');
    return (string)$response->getBody();
}

try {
    completionAssert(is_file('/.dockerenv') && $db->query("SELECT version FROM schema_migrations WHERE version='020'")->fetchColumn() === '020', 'Requires migrated disposable Docker database.');
    $a = publicationNew(); $b = publicationNew(); $bare = publicationNew(false); $empty = publicationNew(true, false);
    $completedUser = completionNewUser($db); $users[] = $completedUser;
    $auth = completionSeed($db, $completedUser, completionFields());
    $review = prepareOnboardingCompletionReview($db, $auth, 3);
    $receipt = completeOnboarding($db, $auth, completionIntent($review));
    $completed = requireOwnedPortfolioContext($db); $portfolios[] = $completed->portfolioId;
    $GLOBALS['completion_fixture_bundle'] = null;
    completionAssert(currentOnboardingAttestationBundle() === null, 'Synthetic policies were not removed after fixture creation.');
    foreach ([$a, $completed] as $context) {
        completionSession($context->userId, false);
        setOwnedPublicSlug($db, $context, ' Private-'.bin2hex(random_bytes(6)).' ');
        publishOwnedPortfolio($db, $context); unpublishOwnedPortfolio($db, $context);
        $state = ownedPublicLifecycleState($db, $context);
        completionAssert($state['published_at'] !== null && $state['is_published'] === 0, 'Valid legacy/completed owner lost access.');
    }
    echo "PASS publication completed and sealed legacy owners without readiness or available policies\n";

    $stage = 'authorization';
    publicationDenied($a, static function (): void { $_SESSION = []; });
    publicationDenied($a, static function (): void { $_SESSION[INTERNAL_USER_SESSION_KEY]['authz_version'] = 'invalid'; });
    publicationDenied($a, static function (): void {
        $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] = time()-INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS-60;
        $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] = time()-INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS-1;
    });
    publicationDenied($a, static function (): void { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] = time()-INTERNAL_SESSION_ABSOLUTE_LIFETIME_SECONDS-1; });
    publicationDenied($a, static function (): void {
        $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] = time()+120;
        $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] = time()+120;
    });
    publicationDenied($a, static function () use ($b): void { completionSession($b->userId); });
    publicationDenied(publicationContext($a->userId, $b->portfolioId));
    publicationDenied(publicationContext(PHP_INT_MAX, $a->portfolioId));
    publicationDenied($bare);
    foreach (["authz_version=2", "account_status='disabled'"] as $change) {
        $db->exec('UPDATE users SET '.$change.' WHERE id='.$a->userId); publicationDenied($a);
        $db->exec("UPDATE users SET authz_version=1, account_status='active' WHERE id=".$a->userId);
    }
    $db->exec('UPDATE portfolios SET legacy_owner_user_id=NULL WHERE id='.$a->portfolioId); publicationDenied($a);
    $db->exec('UPDATE portfolios SET legacy_owner_user_id=owner_user_id WHERE id='.$a->portfolioId);
    $proof = $db->query('SELECT * FROM onboarding_completions WHERE user_id='.$completed->userId)->fetch(PDO::FETCH_ASSOC);
    $db->exec('DELETE FROM onboarding_completions WHERE user_id='.$completed->userId); publicationDenied($completed);
    $db->prepare('INSERT INTO onboarding_completions (`'.implode('`,`',array_keys($proof)).'`) VALUES ('.implode(',',array_fill(0,count($proof),'?')).')')->execute(array_values($proof));
    $frozenSeal = $db->query('SELECT * FROM owner_journey_cutover')->fetch(PDO::FETCH_ASSOC);
    $db->exec('DELETE FROM owner_journey_cutover'); publicationDenied($a);
    completionSession($completed->userId); publishOwnedPortfolio($db,$completed); unpublishOwnedPortfolio($db,$completed);
    $db->prepare('INSERT INTO owner_journey_cutover (`'.implode('`,`',array_keys($frozenSeal)).'`) VALUES ('.implode(',',array_fill(0,count($frozenSeal),'?')).')')->execute(array_values($frozenSeal));
    $newOwner = completionNewUser($db); $users[] = $newOwner;
    $db->exec('UPDATE portfolios SET owner_user_id='.$newOwner.' WHERE id='.$a->portfolioId); publicationDenied($a);
    $db->exec('UPDATE portfolios SET owner_user_id='.$a->userId.' WHERE id='.$a->portfolioId);
    echo "PASS publication all three helpers deny absent malformed expired future mismatched revoked disabled missing and unproven authority without writes\n";

    $stage = 'ambient and behavior'; completionSession($a->userId, false);
    foreach (['set_slug','publish','unpublish'] as $action) {
        $db->beginTransaction(); $db->query('SELECT * FROM users')->fetchAll(); // establish an RR snapshot
        $db->prepare('UPDATE personal_info SET location=? WHERE portfolio_id=?')->execute(['caller-owned', $a->portfolioId]);
        try { publicationMutation($db, $a, $action, 'ambient-slug'); throw new RuntimeException('Ambient write accepted.'); }
        catch (LogicException) {}
        completionAssert($db->inTransaction(), 'Publication ended caller transaction.');
        completionAssert($db->query('SELECT location FROM personal_info WHERE portfolio_id='.$a->portfolioId)->fetchColumn() === 'caller-owned', 'Publication rolled back caller work.');
        completionAssert(completionConnection()->query('SELECT location FROM personal_info WHERE portfolio_id='.$a->portfolioId)->fetchColumn() !== 'caller-owned', 'Publication committed caller work.');
        $db->rollBack();
    }
    $fresh = publicationNew(); completionSession($fresh->userId, false);
    foreach (['ab','bad--slug',str_repeat('a',65),...PUBLIC_SLUG_RESERVED] as $slug) {
        completionDenied($db, static fn()=>setOwnedPublicSlug($db, $fresh, $slug), PublicLifecycleValidationException::class);
    }
    completionDenied($db, static fn()=>publishOwnedPortfolio($db, $fresh), PublicLifecycleValidationException::class);
    completionSession($empty->userId); setOwnedPublicSlug($db, $empty, 'empty-'.bin2hex(random_bytes(6)));
    completionDenied($db, static fn()=>publishOwnedPortfolio($db, $empty), PublicLifecycleValidationException::class);
    completionSession($fresh->userId); $slug = 'normalized-'.bin2hex(random_bytes(6));
    completionAssert(setOwnedPublicSlug($db, $fresh, ' '.strtoupper($slug).' ') === $slug, 'Slug normalization changed.');
    $db->prepare('INSERT INTO projects(portfolio_id,title,category,description,github_url,updated_at) VALUES (?,?,?,?,?,?)')
        ->execute([$fresh->portfolioId,'Synthetic timestamp','Synthetic','Disposable project','https://publication.invalid/','2020-01-01 00:00:00']);
    publishOwnedPortfolio($db, $fresh); $first = ownedPublicLifecycleState($db, $fresh);
    $stamp = $db->query('SELECT updated_at FROM projects WHERE portfolio_id='.$fresh->portfolioId)->fetchColumn();
    publishOwnedPortfolio($db, $fresh);
    completionAssert($stamp === $db->query('SELECT updated_at FROM projects WHERE portfolio_id='.$fresh->portfolioId)->fetchColumn(), 'Idempotent publish refreshed project timestamp.');
    completionAssert(resolvePublicReadContext($db, $slug)?->portfolioId === $fresh->portfolioId, 'Published projection lost.');
    unpublishOwnedPortfolio($db, $fresh); unpublishOwnedPortfolio($db, $fresh);
    completionAssert(resolvePublicReadContext($db, $slug) === null && ownedPublicLifecycleState($db, $fresh)['published_at'] === $first['published_at'], 'Unpublish lost visibility/timestamp behavior.');
    completionDenied($db, static fn()=>setOwnedPublicSlug($db, $fresh, 'new-permanent'), PublicLifecycleConflictException::class);
    completionSession($b->userId); completionDenied($db, static fn()=>setOwnedPublicSlug($db, $b, $slug), PublicLifecycleConflictException::class);
    echo "PASS publication ambient snapshot rejection preserves caller work; normalization guards uniqueness permanence idempotence name/slug-only and visibility\n";

    $stage = 'outcomes'; completionSession($fresh->userId);
    foreach (['statement','before','false','after','rollback'] as $mode) {
        unpublishOwnedPortfolio($db, $fresh); $before = completionSnapshot($db);
        $fault = completionConnection(PublicationFixtureDatabase::class); $fault->fault = $mode; $caught = null;
        try { publishOwnedPortfolio($fault, $fresh); } catch (Throwable $error) { $caught = $error; }
        completionAssert($caught !== null && ($caught instanceof PublicLifecycleWriteUncertainException) === ($mode !== 'statement'), 'Incorrect rollback/uncertainty classification: '.$mode);
        if ($mode === 'after') { completionAssert(ownedPublicLifecycleState($db, requireOwnedPortfolioContext($db))['is_published'] === 1, 'Committed state was compensated.'); }
        else { completionAssert($before === completionSnapshot($db), 'Failed mutation damaged data: '.$mode); }
        completionAssert(!$fault->inTransaction(), 'Fault left transaction open.');
    }
    echo "PASS publication definite statement rollback and before/false/after COMMIT plus rollback-ack uncertainty; committed state read without replay or deletion\n";

    $stage = 'concurrency'; $other = completionConnection();
    $w = publicationStart(publicationInput($a,'publish','before_user')); publicationBarrier($w,'before_user');
    $db->exec('UPDATE portfolios SET legacy_owner_user_id=NULL WHERE id='.$a->portfolioId);
    $before = completionSnapshot($db); publicationRelease($w); completionAssert(publicationFinish($w)==='denied','Old snapshot authorized revoked legacy proof.');
    completionAssert($before===completionSnapshot($db),'Revoked legacy proof denial wrote data.');
    $db->exec('UPDATE portfolios SET legacy_owner_user_id=owner_user_id WHERE id='.$a->portfolioId);
    // Revocation wins before User lock, even with a previously admitted context.
    foreach (["authz_version=2", "account_status='disabled'"] as $change) {
        $w = publicationStart(publicationInput($fresh,'publish','before_user')); publicationBarrier($w,'before_user');
        $db->exec('UPDATE users SET '.$change.' WHERE id='.$fresh->userId);
        $before = completionSnapshot($db); publicationRelease($w); completionAssert(publicationFinish($w) === 'denied', 'Revocation before lock accepted.');
        completionAssert($before === completionSnapshot($db), 'Revocation denial wrote data.');
        $db->exec("UPDATE users SET authz_version=1,account_status='active' WHERE id=".$fresh->userId);
    }
    // Publication has User lock: revocation must wait until publication finishes.
    $w = publicationStart(publicationInput($fresh,'publish','after_user')); publicationBarrier($w,'after_user');
    publicationLockBlocked($other, 'UPDATE users SET authz_version=2 WHERE id=?', [$fresh->userId]);
    publicationRelease($w); completionAssert(publicationFinish($w) === 'saved', 'Authorized first operation failed.');
    $db->exec('UPDATE users SET authz_version=2 WHERE id='.$fresh->userId); publicationDenied($fresh);
    $db->exec('UPDATE users SET authz_version=1 WHERE id='.$fresh->userId);
    // Ownership can change before Portfolio lock; old owner's write safely denies.
    $w = publicationStart(publicationInput($fresh,'unpublish','after_user')); publicationBarrier($w,'after_user');
    $db->exec('UPDATE portfolios SET owner_user_id='.$newOwner.' WHERE id='.$fresh->portfolioId);
    $before = completionSnapshot($db); publicationRelease($w); completionAssert(publicationFinish($w) === 'denied', 'Changed ownership accepted.');
    completionAssert($before === completionSnapshot($db), 'Ownership denial wrote data.');
    $db->exec('UPDATE portfolios SET owner_user_id='.$fresh->userId.' WHERE id='.$fresh->portfolioId);
    // Both locks held: Profile, slug, publication and transfer must serialize.
    $serial = publicationNew(); completionSession($serial->userId); setOwnedPublicSlug($db,$serial,'serial-'.bin2hex(random_bytes(6)));
    $w = publicationStart(publicationInput($serial,'publish','after_portfolio')); publicationBarrier($w,'after_portfolio');
    publicationLockBlocked($other, 'UPDATE portfolios SET owner_user_id=? WHERE id=?', [$newOwner,$serial->portfolioId]);
    $profileWorker = publicationStart(publicationInput($serial,'profile','before_user')); publicationBarrier($profileWorker,'before_user');
    publicationRelease($profileWorker);
    // Real Profile preauthorization is followed by its same User lock.
    publicationLockBlocked($other, 'SELECT id FROM users WHERE id=? FOR UPDATE', [$serial->userId]);
    publicationRelease($w); completionAssert(publicationFinish($w) === 'saved' && publicationFinish($profileWorker) === 'saved', 'Profile/publication did not serialize.');
    completionAssert($db->query('SELECT full_name FROM personal_info WHERE portfolio_id='.$serial->portfolioId)->fetchColumn() === 'Concurrent current name', 'Concurrent Profile lost.');
    // Slug wins first; publication uses its current value. Then permanence wins.
    $serial2 = publicationNew();
    $w = publicationStart(publicationInput($serial2,'publish','before_user')); publicationBarrier($w,'before_user');
    completionSession($serial2->userId); $newSlug='serial-slug-'.bin2hex(random_bytes(6)); setOwnedPublicSlug($db,$serial2,$newSlug);
    publicationRelease($w); completionAssert(publicationFinish($w) === 'saved' && ownedPublicLifecycleState($db,$serial2)['public_slug'] === $newSlug,'Current concurrent slug lost.');
    $w = publicationStart(publicationInput($serial2,'unpublish','after_portfolio')); publicationBarrier($w,'after_portfolio');
    $next = publicationStart(publicationInput($serial2,'publish','before_user')); publicationBarrier($next,'before_user'); publicationRelease($next);
    publicationRelease($w); completionAssert(publicationFinish($w)==='saved' && publicationFinish($next)==='saved','Publication operations failed.');
    completionAssert(ownedPublicLifecycleState($db,$serial2)['is_published']===1,'Serialized publication intent lost.');
    $neverPublished = publicationNew(); completionSession($neverPublished->userId); setOwnedPublicSlug($db,$neverPublished,'first-'.bin2hex(random_bytes(6)));
    $w = publicationStart(publicationInput($neverPublished,'publish','after_portfolio')); publicationBarrier($w,'after_portfolio');
    $renameInput=publicationInput($neverPublished,'set_slug','before_user'); $renameInput['slug']='late-'.bin2hex(random_bytes(6));
    $rename=publicationStart($renameInput); publicationBarrier($rename,'before_user'); publicationRelease($rename);
    publicationRelease($w); completionAssert(publicationFinish($w)==='saved' && publicationFinish($rename)==='conflict','Concurrent first publication did not seal slug.');
    echo "PASS publication measured User/Portfolio lock conflicts; revocation on both sides ownership change Profile current slug and publish/unpublish serialization\n";

    $stage = 'HTTP';
    require __DIR__.'/../../tests/phase2/support/publication-mutation-http.php';
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL publication '.$stage.' '.get_class($error).': '.$error->getMessage()."\n"); $exit = 1;
} finally {
    if ($server !== null) { proc_terminate($server); proc_close($server); }
    foreach ($workers as [$process,$pipes]) {
        if (is_resource($process)) { proc_terminate($process); foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } } proc_close($process); }
    }
    if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION=[]; session_destroy(); }
    try {
        foreach ($portfolios as $portfolio) { $db->exec('DELETE FROM projects WHERE portfolio_id='.$portfolio); }
        completionCleanup($db, $users);
        $db->exec('DELETE FROM owner_journey_cutover');
        foreach ($seal as $row) {
            $db->prepare('INSERT INTO owner_journey_cutover (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
        }
        completionAssert($initial === completionSnapshot($db), 'Fixture cleanup changed protected data or seal.');
        $environment->tearDown(); completionAssert($environment->wasTornDown(), 'Private process/session/file resources remain.');
        echo "PASS publication complete database seal process session private-file cleanup and protected-data snapshot\n";
    } catch (Throwable $error) { fwrite(STDERR, 'FAIL publication cleanup '.$error->getMessage()."\n"); $exit=1; }
}
ob_end_flush(); exit($exit);
