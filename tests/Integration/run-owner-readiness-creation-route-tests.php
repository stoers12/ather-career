<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1') {
    exit(1);
}

require_once __DIR__ . '/../../includes/owner_session.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/owner_flow.php';

$rootPassword = getenv('ATHERCAR_TEST_DB_ROOT_PASSWORD');
$host = getenv('ATHERCAR_TEST_DB_HOST');
$port = getenv('DB_PORT');
if (!is_string($rootPassword) || $rootPassword === ''
    || !is_string($host) || preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]{0,252}$/', $host) !== 1
    || !is_string($port) || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('Disposable route test database configuration is unavailable.');
}
$name = 'ather_career_test_' . bin2hex(random_bytes(12));
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$databaseCreated = false;
$sessionDirectory = null;

function routeCase(PDO $db, int $userId, string $state, bool $csrfValid = true, array $extra = [], string $method = 'POST', string $failureMode = 'none'): array
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_id('');
    startOwnerSession();
    establishVerifiedInternalUserSession($userId, 1);
    if ($state !== 'missing') {
        recordOwnerReadinessObservation($userId, 1, $state === 'true', true);
    }
    if ($state === 'unknown') { $_SESSION[OWNER_READINESS_SESSION_KEY]['email_verified'] = null; }
    if ($state === 'malformed') { $_SESSION[OWNER_READINESS_SESSION_KEY]['email_verified'] = 'true'; }
    if ($state === 'stale') { $_SESSION[OWNER_READINESS_SESSION_KEY]['observed_at'] = $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] - 1; }
    if ($state === 'identity_mismatch') { $_SESSION[OWNER_READINESS_SESSION_KEY]['internal_user_id'] = $userId + 1; }
    if ($state === 'authz_mismatch') { $_SESSION[OWNER_READINESS_SESSION_KEY]['authz_version'] = 2; }
    if ($state === 'session_authz_mismatch') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authz_version'] = 2; }
    if ($state === 'invalid_session') { $_SESSION[INTERNAL_USER_SESSION_KEY]['internal_user_id'] = 'invalid'; }
    if ($state === 'expired') { $_SESSION[INTERNAL_USER_SESSION_KEY]['authenticated_at'] -= 43201; }
    $token = getCsrfToken();
    $id = session_id();
    session_write_close();

    $payload = array_merge(['csrf_token' => $csrfValid ? $token : 'invalid'], $extra);
    $source = <<<'PHP'
session_save_path((string) getenv('ATHERCAR_TEST_SESSION_DIR'));
session_name('portfolio_owner_session'); session_id($argv[1]);
$_SERVER['REQUEST_METHOD'] = $argv[3]; $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/handlers/owner_onboarding.php';
$_POST = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
if ($argv[4] === 'missing_config') { putenv('DB_PORT'); }
if ($argv[4] === 'database_unavailable') { putenv('DB_NAME=ather_career_test_unavailable'); }
register_shutdown_function(static function (): void { fwrite(STDERR, 'HTTP_STATUS=' . (http_response_code() ?: 200) . PHP_EOL); });
require __DIR__ . '/handlers/owner_onboarding.php';
PHP;
    $command = [PHP_BINARY, '-r', $source, $id, json_encode($payload, JSON_THROW_ON_ERROR), $method, $failureMode];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__ . '/../..');
    if (!is_resource($process)) { throw new RuntimeException('Could not execute the real POST route.'); }
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    $diagnostic = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || preg_match('/HTTP_STATUS=(\d+)/', $diagnostic, $match) !== 1) {
        throw new RuntimeException('POST route failed before returning a status.');
    }
    $count = $db->prepare('SELECT COUNT(*) FROM portfolios WHERE owner_user_id = :id');
    $count->execute(['id' => $userId]);
    return [(int) $match[1], (int) $count->fetchColumn(), $body];
}

function saveRouteUiEvidence(string $body, string $name): void
{
    $folder = getenv('ATHERCAR_TEST_UI_OUTPUT_DIR');
    if (!is_string($folder) || $folder === '') { return; }
    $folder = realpath($folder);
    if ($folder === false || !is_dir($folder)) { throw new RuntimeException('UI evidence directory is unavailable.'); }
    $safeBody = preg_replace('/(name="csrf_token" value=")[^"]*"/', '$1[REDACTED]"', $body);
    if (!is_string($safeBody)) { throw new RuntimeException('UI evidence redaction failed.'); }
    $styles = file_get_contents(__DIR__ . '/../../public/assets/css/style.css') . file_get_contents(__DIR__ . '/../../public/assets/css/admin.css');
    $safeBody = str_replace('</head>', '<style>' . $styles . '</style></head>', $safeBody);
    if (file_put_contents($folder . DIRECTORY_SEPARATOR . $name . '.html', $safeBody) === false) {
        throw new RuntimeException('UI evidence could not be saved.');
    }
}

try {
    $server->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4");
    $databaseCreated = true;
    $sessionDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ather-readiness-session-' . bin2hex(random_bytes(12));
    if (!mkdir($sessionDirectory, 0700)) { throw new RuntimeException('Private test session directory could not be created.'); }
    session_save_path($sessionDirectory);
    putenv('ATHERCAR_TEST_SESSION_DIR=' . $sessionDirectory);
    putenv('DB_HOST=' . $host);
    putenv('DB_NAME=' . $name);
    putenv('DB_USER=root');
    putenv('DB_PASSWORD=' . $rootPassword);
    $db = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE users (id INT PRIMARY KEY, account_status VARCHAR(20) NOT NULL, authz_version INT NOT NULL, deletion_requested_at TIMESTAMP NULL)');
    $db->exec('CREATE TABLE portfolios (id INT AUTO_INCREMENT PRIMARY KEY, owner_user_id INT NOT NULL UNIQUE, legacy_owner_user_id INT NULL)');
    $db->exec('CREATE TABLE personal_info (id INT PRIMARY KEY, portfolio_id INT NOT NULL)');
    $db->exec('CREATE TABLE onboarding_completions (user_id INT PRIMARY KEY, portfolio_id INT NOT NULL, profile_id INT NOT NULL)');
    $db->exec("CREATE TABLE owner_journey_cutover (id INT PRIMARY KEY,revision VARBINARY(64),snapshot_count INT,snapshot_digest BINARY(32),captured_at DATETIME(6))");
    $db->exec("INSERT INTO owner_journey_cutover VALUES (1,'owner-journey-cutover-v1',0,UNHEX(SHA2('[]',256)),UTC_TIMESTAMP(6))");
    for ($id = 1; $id <= 16; $id++) { $db->exec("INSERT INTO users (id, account_status, authz_version) VALUES ({$id}, 'active', 1)"); }
    foreach (['false','unknown','missing','malformed','stale','identity_mismatch','authz_mismatch','true','expired','session_authz_mismatch','invalid_session'] as $state) {
        foreach ([true,false] as $csrf) {
            [$status,$count]=routeCase($db,1,$state,$csrf,['email_verified'=>'true','user_id'=>'13']);
            if ($status!==405 || $count!==0) { throw new RuntimeException('Retired POST accepted readiness/identity/CSRF override.'); }
        }
    }
    foreach (['false','unknown','missing','malformed','stale','identity_mismatch','authz_mismatch','true'] as $state) {
        [$status,$count,$body]=routeCase($db,13,$state,true,[],'GET');
        if ($status!==503 || $count!==0 || !str_contains($body,'Use another account') || !str_contains($body,'Logout') || str_contains($body,'>Create Portfolio</button>')) { throw new RuntimeException('Unavailable GET lost account options or exposed bare creation.'); }
    }
    $db->exec('INSERT INTO portfolios (owner_user_id,legacy_owner_user_id) VALUES (12,12),(16,16)');
    foreach ([12,16] as $id) {
        foreach (['false','unknown','missing','true'] as $state) {
            [$status,$count]=routeCase($db,$id,$state,true,[],'GET');
            if ($status!==303 || $count!==1) { throw new RuntimeException('Explicit grandfathered owner was gated by readiness.'); }
        }
    }
    foreach (['expired','session_authz_mismatch'] as $state) {
        [$status,$count]=routeCase($db,13,$state,true,[],'GET');
        if ($status!==403 || $count!==0) { throw new RuntimeException('Invalid session reached protected legacy GET.'); }
    }
    $db->exec("UPDATE users SET account_status='disabled' WHERE id=10");
    [$status,$count]=routeCase($db,10,'true',true,[],'GET');
    if ($status!==403 || $count!==0) { throw new RuntimeException('Disabled User reached protected legacy GET.'); }
    foreach (['missing_config','database_unavailable'] as $failureMode) {
        [$status,$count,$body]=routeCase($db,13,'true',true,[],'GET',$failureMode);
        if ($status!==503 || $count!==0 || !str_contains($body,'Use another account') || !str_contains($body,'Logout')) { throw new RuntimeException('Dependency holding response lost safe account controls.'); }
    }
    session_id('');
    startOwnerSession();
    establishVerifiedInternalUserSession(13, 1);
    recordOwnerReadinessObservation(13, 1, true, true);
    try {
        createOwnedPortfolio($db, AuthenticatedUserContext::fromValidatedUser(14));
        throw new RuntimeException('Mismatched server User context was accepted.');
    } catch (AuthorizationDeniedException) {
    }
    $count = $db->query('SELECT COUNT(*) FROM portfolios WHERE owner_user_id = 14')->fetchColumn();
    if ((int) $count !== 0) { throw new RuntimeException('Mismatched context inserted a Portfolio.'); }
    echo "Owner readiness real POST route: PASS\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    try {
        if ($databaseCreated) { $server->exec("DROP DATABASE `{$name}`"); }
    } finally {
        if (is_string($sessionDirectory) && is_dir($sessionDirectory)) {
            foreach (new DirectoryIterator($sessionDirectory) as $entry) {
                if ($entry->isDot()) { continue; }
                if (!$entry->isFile() || $entry->isLink()) { throw new RuntimeException('Unexpected private session directory entry.'); }
                if (!unlink($entry->getPathname())) { throw new RuntimeException('Private test session cleanup failed.'); }
            }
            if (!rmdir($sessionDirectory)) { throw new RuntimeException('Private test session directory cleanup failed.'); }
        }
    }
}
