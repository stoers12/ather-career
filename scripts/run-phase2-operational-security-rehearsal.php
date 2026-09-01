<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/account_operations.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/owner_session.php';
require_once __DIR__ . '/../includes/operational_security.php';
require_once __DIR__ . '/../includes/profile_presentation.php';
require_once __DIR__ . '/../includes/public_contact.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';
require_once __DIR__ . '/../includes/storage.php';

/** @return list<string> */
function operationalConcurrentWorkers(string $script, array $argumentSets): array
{
    $processes = [];
    foreach ($argumentSets as $arguments) {
        $command = array_merge([PHP_BINARY, $script], array_map('strval', $arguments));
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Concurrent worker could not start.');
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Concurrent worker failed: ' . $stderr);
        $results[] = $stdout;
    }
    return $results;
}

function operationalStartSession(int $userId, int $authzVersion): void
{
    if (session_status() === PHP_SESSION_ACTIVE) destroyInternalUserSession();
    startOwnerSession();
    establishVerifiedInternalUserSession($userId, $authzVersion);
}

function operationalPng(string $path): void
{
    $image = imagecreatetruecolor(480, 480);
    imagefill($image, 0, 0, imagecolorallocate($image, 40, 100, 180));
    if (!imagepng($image, $path)) throw new RuntimeException('Operational media fixture failed.');
    imagedestroy($image);
}

$environment = null;
try {
    $environment = TestEnvironment::create();
    putenv('RATE_LIMIT_STATE_DIR=' . $environment->storageRoot . '/rate-limit');
    putenv('ATHERCAR_STORAGE_ROOT=' . $environment->storageRoot . '/private');
    mkdir($environment->storageRoot . '/private', 0700);
    $database = getDatabaseConnection();
    phase2AssertSame('public_lifecycle', $database->query("SELECT name FROM schema_migrations WHERE version = '005'")->fetchColumn(), 'P2J-08 requires the completed Phase-2 schema.');

    $_SERVER['REMOTE_ADDR'] = '198.51.100.41';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';
    $_SERVER['HTTP_FORWARDED'] = 'for=203.0.113.10';
    phase2AssertSame('198.51.100.41', rateLimitClientIp(), 'Forwarded headers changed limiter authority.');
    $rateArguments = array_fill(0, 30, ['p2j08_concurrent', 'shared-worker', 20, 900]);
    $rateResults = operationalConcurrentWorkers(__DIR__ . '/run-phase2-rate-worker.php', $rateArguments);
    phase2AssertSame(20, count(array_filter($rateResults, fn ($value) => $value === 'allowed')), 'Concurrent limiter bypassed or undercounted the threshold: ' . json_encode(array_count_values($rateResults)));
    phase2AssertSame(10, count(array_filter($rateResults, fn ($value) => $value === 'denied')), 'Concurrent limiter denial count is incorrect: ' . json_encode(array_count_values($rateResults)));
    $corruptDirectory = rateLimitDirectory();
    $corruptPath = rateLimitStatePath($corruptDirectory, 'corrupt', 'state');
    file_put_contents($corruptPath, '{invalid');
    try { consumeRateLimit('corrupt', 'state', 1, 900); phase2Assert(false, 'Corrupt limiter state was accepted.'); } catch (RuntimeException) {}
    putenv('RATE_LIMIT_STATE_DIR=' . $environment->storageRoot . '/missing-parent/state');
    file_put_contents($environment->storageRoot . '/missing-parent', 'blocked');
    try { consumeRateLimit('unavailable', 'state', 1, 900); phase2Assert(false, 'Unavailable limiter state failed open.'); } catch (RuntimeException) {}
    putenv('RATE_LIMIT_STATE_DIR=' . $environment->storageRoot . '/rate-limit');
    $passed[] = 'T-OPS-LIMITER-CONCURRENCY-FAIL-CLOSED';

    $userStatement = $database->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES ('https://issuer.test/ather-career', :subject, 'active', 5)");
    $userStatement->execute(['subject' => 'p2j08-a-' . bin2hex(random_bytes(6))]);
    $userA = (int) $database->lastInsertId();
    $portfolioStatement = $database->prepare("INSERT INTO portfolios (owner_user_id, public_slug, is_published, published_at) VALUES (:owner, 'ops-a', 1, '2026-01-01 00:00:00')");
    $portfolioStatement->execute(['owner' => $userA]);
    $portfolioA = (int) $database->lastInsertId();
    $userStatement->execute(['subject' => 'p2j08-b-' . bin2hex(random_bytes(6))]);
    $userB = (int) $database->lastInsertId();
    $portfolioStatement = $database->prepare("INSERT INTO portfolios (owner_user_id, public_slug, is_published, published_at) VALUES (:owner, 'ops-b', 1, '2026-01-01 00:00:00')");
    $portfolioStatement->execute(['owner' => $userB]);
    $portfolioB = (int) $database->lastInsertId();

    clearRateLimit('contact', 'policy-contact');
    $contactDecisions = [];
    for ($attempt = 0; $attempt < 4; $attempt++) $contactDecisions[] = consumeRateLimit('contact', 'policy-contact', 3, 900)['allowed'];
    phase2AssertSame([true, true, true, false], $contactDecisions, 'Public contact 3/900 policy changed.');
    $contextA = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($userA), $portfolioA);
    clearRateLimit('owner_upload', $userA . ':' . $portfolioA);
    clearRateLimit('owner_publication', (string) $portfolioA);
    $uploadAllowed = 0; $publicationAllowed = 0;
    for ($attempt = 0; $attempt < 21; $attempt++) {
        if (consumeOwnerUploadRateLimit($contextA)['allowed']) $uploadAllowed++;
        if (consumeOwnerPublicationRateLimit($contextA)['allowed']) $publicationAllowed++;
    }
    phase2AssertSame(20, $uploadAllowed, 'Owner upload 20/900 policy is incorrect.');
    phase2AssertSame(20, $publicationAllowed, 'Publication 20/900 policy is incorrect.');
    $passed[] = 'T-OPS-AUTHORIZED-NUMERIC-POLICY';

    $quotaArgs = [[$portfolioA, 62914560, 'race-a.bin'], [$portfolioA, 62914560, 'race-b.bin']];
    $quotaResults = operationalConcurrentWorkers(__DIR__ . '/run-phase2-quota-worker.php', $quotaArgs);
    phase2AssertSame(1, count(array_filter($quotaResults, fn ($value) => $value === 'allowed')), 'Same-Portfolio quota race admitted more than one reservation.');
    phase2AssertSame(1, count(array_filter($quotaResults, fn ($value) => $value === 'denied')), 'Same-Portfolio quota race did not deny excess capacity.');
    phase2Assert(portfolioStorageUsageBytes($portfolioA) <= PORTFOLIO_STORAGE_QUOTA_BYTES, 'Quota race exceeded aggregate capacity.');
    phase2AssertSame(0, portfolioStorageUsageBytes($portfolioB), 'Portfolio A quota changed Portfolio B storage.');
    withPortfolioQuotaReservation($portfolioB, 1024, static function () use ($portfolioB): void {
        file_put_contents(portfolioStorageDirectory($portfolioB, true) . '/b.bin', str_repeat('b', 1024));
    });
    phase2AssertSame(1024, portfolioStorageUsageBytes($portfolioB), 'Portfolio B quota reservation was affected by A.');
    $lockPortfolio = $portfolioB + 1000000;
    $lockDirectory = portfolioStorageDirectory($lockPortfolio, true);
    mkdir($lockDirectory . '/.quota.lock');
    try { withPortfolioQuotaReservation($lockPortfolio, 1, static fn () => null); phase2Assert(false, 'Quota lock failure was accepted.'); } catch (RuntimeException) {}
    $passed[] = 'T-OPS-QUOTA-CONCURRENCY-ISOLATION';

    $now = time();
    operationalStartSession($userA, 5);
    phase2Assert(requireAuthenticatedUser($database)->userId === $userA, 'Valid session was rejected.');
    $authenticatedAt = $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'];
    phase2AssertSame(null, internalSessionLifetimeFailure(['authenticated_at' => $now - 43200, 'last_activity_at' => $now], $now), 'Absolute boundary was rejected early.');
    phase2AssertSame('absolute_timeout', internalSessionLifetimeFailure(['authenticated_at' => $now - 43201, 'last_activity_at' => $now], $now), 'Absolute timeout was not enforced.');
    phase2AssertSame('idle_timeout', internalSessionLifetimeFailure(['authenticated_at' => $now - 2000, 'last_activity_at' => $now - 1801], $now), 'Idle timeout was not enforced.');
    phase2AssertSame('future_timestamp', internalSessionLifetimeFailure(['authenticated_at' => $now + 61, 'last_activity_at' => $now + 61], $now), 'Future timestamp was accepted.');
    phase2AssertSame($authenticatedAt, $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'], 'Normal activity extended absolute authentication time.');
    operationalStartSession($userA, 5);
    $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] = time() - 1801;
    try { requireAuthenticatedUser($database); phase2Assert(false, 'Idle-expired session retained authority.'); } catch (AuthorizationDeniedException) {}
    operationalStartSession($userA, 5);
    $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] = time() - 43201;
    try { requireAuthenticatedUser($database); phase2Assert(false, 'Absolute-expired session retained authority.'); } catch (AuthorizationDeniedException) {}
    operationalStartSession($userA, 5);
    unset($_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at']);
    try { requireAuthenticatedUser($database); phase2Assert(false, 'Malformed session retained authority.'); } catch (AuthorizationDeniedException) {}
    $passed[] = 'T-OPS-SESSION-LIFETIME';

    $temporary = tempnam(sys_get_temp_dir(), 'p2j08-media-');
    operationalPng($temporary);
    $profileKey = copyFileToPrivateMedia($temporary, $portfolioA, 'profile_original', createManagedUploadFilename('profile', 'png'));
    $presentationKey = $profileKey === null ? null : generateProfilePresentationImage($profileKey, $portfolioA);
    $projectKey = copyFileToPrivateMedia($temporary, $portfolioA, 'projects', createManagedUploadFilename('project', 'png'));
    phase2Assert($profileKey !== null && $presentationKey !== null && $projectKey !== null, 'Disable preservation media fixture failed.');
    $database->prepare('INSERT INTO personal_info (portfolio_id, full_name, profile_image_path) VALUES (:portfolio, \'Ops A\', :image)')->execute(['portfolio' => $portfolioA, 'image' => $profileKey]);
    $database->prepare("INSERT INTO projects (portfolio_id, title, category, description, github_url, image_path) VALUES (:portfolio, 'Ops Project', 'Ops', 'Preserved', 'https://example.test', :image)")->execute(['portfolio' => $portfolioA, 'image' => $projectKey]);
    $projectId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO messages (recipient_portfolio_id, name, email, message) VALUES (:portfolio, 'Sender', 'sender@example.test', 'Preserved message')")->execute(['portfolio' => $portfolioA]);
    $beforeCounts = [(int) $database->query("SELECT COUNT(*) FROM personal_info WHERE portfolio_id = {$portfolioA}")->fetchColumn(), (int) $database->query("SELECT COUNT(*) FROM projects WHERE portfolio_id = {$portfolioA}")->fetchColumn(), (int) $database->query("SELECT COUNT(*) FROM messages WHERE recipient_portfolio_id = {$portfolioA}")->fetchColumn()];
    $beforeHash = hash_file('sha256', resolvePrivateMediaPath($projectKey, $portfolioA, 'projects'));
    operationalStartSession($userA, 5);
    $disabled = transitionAccountStatus($database, $userA, 'disabled');
    phase2AssertSame('disabled', $disabled['account_status'], 'Account was not disabled.');
    phase2AssertSame(6, $disabled['authz_version'], 'Disable did not increment authz_version atomically.');
    try { requireAuthenticatedUser($database); phase2Assert(false, 'Pre-disable session retained authority.'); } catch (AuthorizationDeniedException) {}
    phase2AssertSame(null, resolvePublicReadContext($database, 'ops-a'), 'Disabled Portfolio remained publicly readable.');
    phase2AssertSame(null, preparePublicContactSubmission($database, 'ops-a', ['name' => 'A', 'email' => 'a@example.test', 'message' => 'Denied'])['context'], 'Disabled Portfolio remained contactable.');
    phase2AssertSame($beforeCounts, [(int) $database->query("SELECT COUNT(*) FROM personal_info WHERE portfolio_id = {$portfolioA}")->fetchColumn(), (int) $database->query("SELECT COUNT(*) FROM projects WHERE portfolio_id = {$portfolioA}")->fetchColumn(), (int) $database->query("SELECT COUNT(*) FROM messages WHERE recipient_portfolio_id = {$portfolioA}")->fetchColumn()], 'Disable deleted tenant data.');
    phase2AssertSame($beforeHash, hash_file('sha256', resolvePrivateMediaPath($projectKey, $portfolioA, 'projects')), 'Disable changed tenant media.');
    $enabled = transitionAccountStatus($database, $userA, 'active');
    phase2AssertSame(7, $enabled['authz_version'], 'Re-enable did not invalidate older sessions.');

    $beforeB = accountOperationalState($database, $userB);
    try { transitionAccountStatus($database, $userB, 'disabled', static function (): void { throw new RuntimeException('Injected transaction failure.'); }); phase2Assert(false, 'Injected disable failure succeeded.'); } catch (Throwable) {}
    phase2AssertSame($beforeB, accountOperationalState($database, $userB), 'Failed disable partially changed account state.');
    $passed[] = 'T-OPS-DISABLE-PRESERVATION';

    $logPath = $environment->storageRoot . '/security-events.log';
    ini_set('error_log', $logPath);
    reportSecurityEvent('quota_denial', 'denied', ['internal_user_id' => $userA, 'portfolio_id' => $portfolioA, 'reason' => 'test', 'session_id' => 'SECRET_SESSION', 'path' => '/private/secret', 'message' => 'SECRET_BODY']);
    $log = file_get_contents($logPath);
    phase2Assert(is_string($log) && str_contains($log, '"event":"quota_denial"') && str_contains($log, '"timestamp"'), 'Structured security event was not written.');
    foreach (['SECRET_SESSION', '/private/secret', 'SECRET_BODY', 'cookie', 'password', 'token'] as $secret) phase2Assert(!str_contains($log, $secret), 'Security log leaked sensitive data.');
    $passed[] = 'T-OPS-LOG-REDACTION';

    @unlink($temporary);
    foreach ($passed as $test) echo "PASS {$test}\n";
    echo "PASS operational security rehearsal\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL operational security rehearsal: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) destroyInternalUserSession();
    putenv('RATE_LIMIT_STATE_DIR'); putenv('ATHERCAR_STORAGE_ROOT');
    if ($environment instanceof TestEnvironment) $environment->tearDown();
}
