<?php
declare(strict_types=1);
require_once __DIR__ . '/../tests/phase2/support/attestation-fixtures.php';
if (count($argv) !== 2 || !ctype_digit($argv[1])) { exit(2); }
final class AttestationRaceDatabase extends PDO
{
    public function beginTransaction(): bool
    {
        $result = parent::beginTransaction();
        echo "READY\n"; fflush(STDOUT);
        stream_set_timeout(STDIN, 20);
        if (fgets(STDIN) !== "GO\n") { throw new RuntimeException('Barrier failed.'); }
        return $result;
    }
}
$db = new AttestationRaceDatabase('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$sessionRoot = attestationTestStartSession();
try {
    $context = attestationTestSession((int) $argv[1]);
    echo json_encode(recordOnboardingAttestations($db, $context, attestationTestBundle('test-race'), attestationTestAffirmations()), JSON_THROW_ON_ERROR) . "\n";
} finally { attestationTestEndSession($sessionRoot); }
