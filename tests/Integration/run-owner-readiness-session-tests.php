<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../../includes/authorization.php';
require_once __DIR__ . '/../../includes/owner_session.php';

function checkReadiness(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function sameReadiness(mixed $expected, mixed $actual, string $message): void
{
    checkReadiness($expected === $actual, $message);
}

final class ReadinessUserStatement extends PDOStatement
{
    public function __construct(private array $user) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->user;
    }
}

final class ReadinessUserDatabase extends PDO
{
    public function __construct(public array $user) {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new ReadinessUserStatement($this->user);
    }
}

function newReadinessSession(int $userId = 101, int $version = 7): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { destroyOwnerSession(); }
    session_id('');
    startOwnerSession();
    establishVerifiedInternalUserSession($userId, $version);
}

function deniedReadiness(callable $read, string $message): void
{
    try { $read(); } catch (AuthorizationDeniedException) { return; }
    throw new RuntimeException($message);
}

try {
    newReadinessSession();
    $database = new ReadinessUserDatabase(['id' => 101, 'account_status' => 'active', 'authz_version' => 7]);
    $observedAt = time();
    recordOwnerReadinessObservation(101, 7, true, true, $observedAt);
    sameReadiness(['observed_at' => $observedAt, 'email_verified' => true], readAuthorizedOwnerReadinessObservation($database), 'Authorized read lost positive observation.');
    $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] = $observedAt - 5;
    $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] = $observedAt - 5;
    $_SESSION[OWNER_READINESS_SESSION_KEY]['authenticated_at'] = $observedAt - 5;
    sameReadiness($observedAt, readAuthorizedOwnerReadinessObservation($database)['observed_at'], 'Protected activity refreshed observation time.');
    checkReadiness($_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] >= $observedAt, 'Protected activity did not refresh idle time.');
    sameReadiness(null, readOwnerReadinessObservation(102, 7), 'Another identity read the observation.');
    sameReadiness(null, readOwnerReadinessObservation(101, 7), 'Identity mismatch did not clear the observation.');

    recordOwnerReadinessObservation(101, 7, true, true);
    sameReadiness(null, readOwnerReadinessObservation(101, 8), 'Authorization-version mismatch read the observation.');
    sameReadiness(null, readOwnerReadinessObservation(101, 7), 'Authorization-version mismatch did not clear it.');
    recordOwnerReadinessObservation(101, 7, true, true);
    foreach ([false, null, 'true', ['email_verified' => true]] as $replacement) {
        recordOwnerReadinessObservation(101, 7, $replacement, true);
        sameReadiness($replacement === false ? false : null, readOwnerReadinessObservation(101, 7)['email_verified'], 'A false or malformed observation retained a positive.');
        recordOwnerReadinessObservation(101, 7, true, true);
    }
    recordOwnerReadinessObservation(101, 7, true, false);
    sameReadiness(null, readOwnerReadinessObservation(101, 7)['email_verified'], 'Untrusted positive replaced a trusted positive.');

    recordOwnerReadinessObservation(101, 7, true, true);
    try { recordOwnerReadinessObservation(102, 7, true, true); throw new RuntimeException('Mismatched writer was accepted.'); }
    catch (LogicException) {}
    sameReadiness(null, readOwnerReadinessObservation(101, 7), 'Mismatched writer retained positive observation.');

    recordOwnerReadinessObservation(101, 7, true, true);
    $database->user['authz_version'] = 8;
    deniedReadiness(static fn () => readAuthorizedOwnerReadinessObservation($database), 'Current authorization version was not checked.');
    sameReadiness(null, readOwnerReadinessObservation(101, 7), 'Authorization denial retained observation.');

    newReadinessSession();
    recordOwnerReadinessObservation(101, 7, true, true);
    $database->user = ['id' => 202, 'account_status' => 'active', 'authz_version' => 7];
    deniedReadiness(static fn () => readAuthorizedOwnerReadinessObservation($database), 'Another current User received the observation.');
    sameReadiness(null, readOwnerReadinessObservation(101, 7), 'Current User mismatch retained observation.');
    $database->user['id'] = 101;

    newReadinessSession();
    recordOwnerReadinessObservation(101, 7, true, true);
    destroyOwnerSession();
    sameReadiness(null, readOwnerReadinessObservation(101, 7), 'Logout retained observation.');

    newReadinessSession();
    recordOwnerReadinessObservation(101, 7, true, true);
    beginFreshOwnerAccountSelectionSession();
    sameReadiness(null, readOwnerReadinessObservation(101, 7), 'Account switch retained observation.');
    establishVerifiedInternalUserSession(202, 3);
    sameReadiness(null, readOwnerReadinessObservation(202, 3), 'Switched account inherited observation.');
    recordOwnerReadinessObservation(202, 3, true, true);
    establishVerifiedInternalUserSession(101, 7);
    sameReadiness(null, readOwnerReadinessObservation(101, 7), 'Fresh authentication inherited earlier observation.');
    recordOwnerReadinessObservation(101, 7, true, true);
    establishVerifiedInternalUserSession(101, 7);
    sameReadiness(null, readOwnerReadinessObservation(101, 7), 'Same-identity authentication inherited an earlier instance.');

    recordOwnerReadinessObservation(101, 7, true, true);
    $base = time();
    $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] = $base - INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS;
    $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] = $base - INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS;
    $_SESSION[OWNER_READINESS_SESSION_KEY]['authenticated_at'] = $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'];
    $_SESSION[OWNER_READINESS_SESSION_KEY]['observed_at'] = $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'];
    checkReadiness(readOwnerReadinessObservation(101, 7, $base) !== null, 'Exact idle boundary rejected observation.');
    sameReadiness(null, readOwnerReadinessObservation(101, 7, $base + 1), 'Idle expiry retained observation.');
    newReadinessSession();
    recordOwnerReadinessObservation(101, 7, true, true);
    $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] = time() - INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS - 1;
    $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] = $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'];
    $_SESSION[OWNER_READINESS_SESSION_KEY]['authenticated_at'] = $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'];
    deniedReadiness(static fn () => readAuthorizedOwnerReadinessObservation($database), 'Idle-expired protected read retained authority.');
    sameReadiness(PHP_SESSION_NONE, session_status(), 'Idle-expired protected read did not destroy the session.');
    newReadinessSession();
    recordOwnerReadinessObservation(101, 7, true, true);
    $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] = $base - INTERNAL_SESSION_ABSOLUTE_LIFETIME_SECONDS;
    $_SESSION[INTERNAL_USER_SESSION_KEY]['last_activity_at'] = $base;
    $_SESSION[OWNER_READINESS_SESSION_KEY]['authenticated_at'] = $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'];
    $_SESSION[OWNER_READINESS_SESSION_KEY]['observed_at'] = $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'];
    checkReadiness(readOwnerReadinessObservation(101, 7, $base) !== null, 'Exact absolute boundary rejected observation.');
    sameReadiness(null, readOwnerReadinessObservation(101, 7, $base + 1), 'Absolute expiry retained observation.');
    newReadinessSession();
    recordOwnerReadinessObservation(101, 7, true, true);
    $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] = time() - INTERNAL_SESSION_ABSOLUTE_LIFETIME_SECONDS - 1;
    deniedReadiness(static fn () => readAuthorizedOwnerReadinessObservation($database), 'Absolute-expired protected read retained authority.');
    sameReadiness(PHP_SESSION_NONE, session_status(), 'Absolute-expired protected read did not destroy the session.');

    echo "Owner readiness session lifecycle: PASS\n";
} catch (Throwable $exception) {
    if (session_status() === PHP_SESSION_ACTIVE) { destroyOwnerSession(); }
    fwrite(STDERR, 'Owner readiness session lifecycle: FAIL: ' . $exception->getMessage() . "\n");
    exit(1);
}
