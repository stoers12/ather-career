<?php
declare(strict_types=1);

require_once __DIR__.'/../bootstrap.php';
require_once __DIR__.'/../../../includes/public_lifecycle.php';
require_once __DIR__.'/../../../includes/profile_writes.php';

/** Old CLI publication regressions now establish a real synthetic session for
 * each intended owner. Never used for denial tests or by production routes.
 */
function publicationFixtureMutation(PDO $db, AuthorizedPortfolioContext $context, string $action, mixed $slug = null): mixed
{
    // Some existing runners attach their exact test DB after loading helpers.
    // Validate the guarded session fixture at use time, after that attachment.
    require_once __DIR__.'/completion-fixtures.php';
    $previous = session_status() === PHP_SESSION_ACTIVE ? $_SESSION : null;
    $previousPath = session_save_path();
    $previousCookies = ini_get('session.use_cookies');
    $root = $previous === null ? completionSessionRoot() : null;
    try {
        $version = $db->prepare('SELECT authz_version FROM users WHERE id=?');
        $version->execute([$context->userId]);
        $_SESSION = [INTERNAL_USER_SESSION_KEY => ['internal_user_id'=>$context->userId,
            'authz_version'=>(int)$version->fetchColumn(), 'authenticated_at'=>time(), 'last_activity_at'=>time()]];
        return publicationMutation($db, $context, $action, $slug);
    } finally {
        if ($root !== null) {
            completionEndSession($root); session_id(''); session_save_path($previousPath);
            ini_set('session.use_cookies', $previousCookies);
        } else { $_SESSION = $previous; }
    }
}

function publicationMutation(PDO $db, AuthorizedPortfolioContext $context, string $action, mixed $slug = null): mixed
{
    switch ($action) {
        case 'project': setOwnedProjectPublication($db, $context, $slug['id'], $slug['value']); return null;
        case 'set_slug': return setOwnedPublicSlug($db, $context, $slug);
        case 'publish': publishOwnedPortfolio($db, $context); return null;
        case 'unpublish': unpublishOwnedPortfolio($db, $context); return null;
        default: throw new LogicException('Unknown fixture action.');
    }
}

function publicationContext(int $user, int $portfolio): AuthorizedPortfolioContext
{
    return AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($user), $portfolio);
}

/** Statement/COMMIT faults and scheduling barriers run only in disposable
 * fixtures. SQL and the production authorization/mutation functions are real.
 */
class PublicationFixtureDatabase extends PDO
{
    public string $fault = '';
    public string $pause = '';
    public bool $snapshot = false;
    private bool $paused = false;

    public function beginTransaction(): bool
    {
        $started = parent::beginTransaction();
        if ($this->snapshot) { $this->query('SELECT COUNT(*) FROM portfolios')->fetchColumn(); }
        return $started;
    }

    public function barrier(string $point): void
    {
        if ($this->pause !== $point || $this->paused) { return; }
        $this->paused = true;
        // The CLI coordination channel must not mark PHP response headers sent;
        // a later real authorization denial still clears its session cookie.
        fwrite(STDOUT, 'BARRIER '.$point."\n"); fflush(STDOUT); stream_set_timeout(STDIN, 15);
        if (fgets(STDIN) !== "GO\n") { throw new RuntimeException('Publication barrier timeout.'); }
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'FROM users') && str_contains($query, 'FOR UPDATE')) { $this->barrier('before_user'); }
        if (str_starts_with($query, 'SELECT id FROM portfolios')) { $this->barrier('after_user'); }
        if (str_starts_with($query, 'SELECT is_published FROM projects')) { $this->barrier('after_portfolio'); }
        if (str_starts_with($query, 'UPDATE portfolios')) { $this->barrier('after_portfolio'); }
        if ($this->fault === 'statement' && str_starts_with($query, 'UPDATE projects')) {
            throw new PDOException('Synthetic failure after Portfolio update.');
        }
        return parent::prepare($query, $options);
    }

    public function commit(): bool
    {
        if ($this->fault === 'after') { parent::commit(); throw new PDOException('Synthetic lost COMMIT acknowledgement.'); }
        if ($this->fault === 'before' || $this->fault === 'rollback') { throw new PDOException('Synthetic COMMIT failure.'); }
        if ($this->fault === 'false') { return false; }
        return parent::commit();
    }

    public function rollBack(): bool
    {
        $result = parent::rollBack();
        if ($this->fault === 'rollback') { throw new PDOException('Synthetic lost rollback acknowledgement.'); }
        return $result;
    }
}

function publicationWorker(array $input): array
{
    $process = proc_open([PHP_BINARY, __DIR__.'/../../../scripts/test/run-publication-mutation-tests.php',
        '--worker', base64_encode(json_encode($input, JSON_THROW_ON_ERROR))],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    completionAssert(is_resource($process), 'Worker unavailable.');
    stream_set_timeout($pipes[1], 20);
    return [$process, $pipes];
}

function publicationBarrier(array $worker, string $point): void
{
    completionAssert(fgets($worker[1][1]) === 'BARRIER '.$point."\n", 'Worker barrier missing: '.$point);
}

function publicationRelease(array $worker): void { fwrite($worker[1][0], "GO\n"); fclose($worker[1][0]); }

function publicationFinish(array $worker): string
{
    [$process, $pipes] = $worker;
    $result = trim(stream_get_contents($pipes[1])); $diagnostic = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    completionAssert(proc_close($process) === 0, 'Worker failed: '.$result.' '.$diagnostic);
    // Expected session invalidation events may be emitted by denial cases.
    foreach (explode("\n", trim($diagnostic)) as $line) {
        if ($line !== '') { completionAssert(str_contains($line, 'session_invalidation'), 'Unexpected worker diagnostic: '.$line); }
    }
    return $result;
}

/** A second connection must actually time out on the held row, rather than
 * inferring concurrency from sleep timing or process launch order.
 */
function publicationLockBlocked(PDO $db, string $sql, array $parameters): void
{
    $db->exec('SET SESSION innodb_lock_wait_timeout=1');
    try { $db->prepare($sql)->execute($parameters); throw new RuntimeException('Expected row lock was not held.'); }
    catch (PDOException $error) { completionAssert(($error->errorInfo[1] ?? null) === 1205, 'Unexpected locking error.'); }
}
