<?php
declare(strict_types=1);
require_once __DIR__ . '/../tests/phase2/support/profile-version-fixtures.php';
if (count($argv) !== 3 || !ctype_digit($argv[1])) { exit(2); }
$input = json_decode(base64_decode($argv[2], true), true, 16, JSON_THROW_ON_ERROR);
final class ProfileRaceDatabase extends PDO
{
    public function beginTransaction(): bool
    {
        $started = parent::beginTransaction(); echo "READY\n"; fflush(STDOUT); stream_set_timeout(STDIN, 20);
        if (fgets(STDIN) !== "GO\n") { throw new RuntimeException('Profile barrier failed.'); }
        return $started;
    }
}
$db = draftTestConnection(ProfileRaceDatabase::class); $root = attestationTestStartSession(); $exitCode = 0;
try {
    $context = profileTestContext($db, (int) $argv[1]);
    try {
        if ($input['mode'] === 'migration') { migrateLegacyMedia($db, $input['source']); }
        elseif ($input['mode'] === 'image') { saveOwnedProfileImage($db, $context, $input['id'], $input['version'], $input['key']); }
        else { saveOwnedProfilePatch($db, $context, $input['id'], $input['version'], $input['patch']); }
        echo "saved\n";
    } catch (ProfileVersionConflictException) { echo "conflict\n"; }
} catch (Throwable $error) {
    fwrite(STDERR, 'Worker ' . $input['mode'] . ' failed: ' . ($error instanceof PDOException ? 'PDO failure' : $error->getMessage())); $exitCode = 1;
} finally { attestationTestEndSession($root); }
exit($exitCode);
