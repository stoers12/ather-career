<?php
declare(strict_types=1);
require_once __DIR__ . '/../../tests/phase2/support/draft-fixtures.php';
if (count($argv) !== 3 || !ctype_digit($argv[1])) { exit(2); }
$input = json_decode(base64_decode($argv[2], true), true, 16, JSON_THROW_ON_ERROR);
final class DraftRaceDatabase extends PDO
{
    public function beginTransaction(): bool
    {
        $started = parent::beginTransaction();
        echo "READY\n"; fflush(STDOUT);
        stream_set_timeout(STDIN, 20);
        if (fgets(STDIN) !== "GO\n") { throw new RuntimeException('Draft barrier failed.'); }
        return $started;
    }
}
$db = draftTestConnection(DraftRaceDatabase::class);
$root = attestationTestStartSession();
try {
    $context = attestationTestSession((int) $argv[1]);
    try {
        $result = saveOnboardingDraft($db, $context, ...$input);
        echo json_encode(['status' => 'saved', 'result' => $result], JSON_THROW_ON_ERROR) . "\n";
    } catch (OnboardingDraftConflictException) {
        echo "{\"status\":\"conflict\"}\n";
    }
} finally { attestationTestEndSession($root); }
