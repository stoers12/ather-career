<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/^ather_career_test_[a-f0-9]{24}$/', (string) getenv('DB_NAME')) !== 1 || count($argv) !== 3) {
    exit(2);
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth0_registration.php';

// Test-owned PDO instrumentation provides an inter-process barrier after both
// real operations observed a missing binding, before either INSERT. There is
// no production hook, sleep, environment override, or provisioning fallback.
final class RegistrationRaceStatement extends PDOStatement
{
    public static int $identityConflicts = 0;

    public function execute(?array $params = null): bool
    {
        try {
            return parent::execute($params);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                self::$identityConflicts++;
            }
            throw $exception;
        }
    }
}

final class RegistrationRaceDatabase extends PDO
{
    public function beginTransaction(): bool
    {
        $started = parent::beginTransaction();
        fwrite(STDOUT, "READY\n");
        fflush(STDOUT);
        stream_set_timeout(STDIN, 20);
        if (fgets(STDIN) !== "GO\n") {
            throw new RuntimeException('Registration worker barrier failed.');
        }
        return $started;
    }
}

try {
    $database = new RegistrationRaceDatabase(
        'mysql:host=' . getRequiredDatabaseEnvironment('DB_HOST') . ';port=' . getRequiredDatabaseEnvironment('DB_PORT')
            . ';dbname=' . getRequiredDatabaseEnvironment('DB_NAME') . ';charset=utf8mb4',
        getRequiredDatabaseEnvironment('DB_USER'), getRequiredDatabaseEnvironment('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
         PDO::ATTR_STATEMENT_CLASS => [RegistrationRaceStatement::class]],
    );
    $issuer = base64_decode($argv[1], true);
    $subject = base64_decode($argv[2], true);
    if ($issuer === false || $subject === false) { exit(2); }
    $configuration = new Auth0OidcConfiguration($issuer, 'registration.invalid', 'synthetic-client', 'synthetic-secret', 'https://app.invalid/owner_oidc_callback.php');
    $result = registerAuth0InternalUser($database, $configuration, new Auth0ValidatedIdentity($issuer, $subject, true, true));
    echo json_encode(['result' => $result, 'conflicts' => RegistrationRaceStatement::$identityConflicts], JSON_THROW_ON_ERROR) . "\n";
} catch (IdentityRepositoryException) {
    echo json_encode(['denied' => true, 'conflicts' => RegistrationRaceStatement::$identityConflicts], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable) {
    fwrite(STDERR, "Registration worker failed.\n");
    exit(1);
}
