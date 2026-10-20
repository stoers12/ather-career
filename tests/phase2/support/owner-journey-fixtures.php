<?php
declare(strict_types=1);

/** Explicit synthetic legacy cohort, never a production selector/capture path.
 * A fixture factory calls this only for its known synthetic User. It does not
 * scan/adopt other rows. The global fixture seal is empty before per-case seeds.
 */
function journeyFixtureLegacyOwner(PDO $database, int $userId): void
{
    if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='test' || getenv('ATHERCAR_TEST_MODE')!=='1' || preg_match('/\Aather_career_test_[a-f0-9]{24}\z/D',(string)$database->query('SELECT DATABASE()')->fetchColumn())!==1) { throw new RuntimeException('Legacy fixture requires isolated test database.'); }
    $database->exec("INSERT IGNORE INTO owner_journey_cutover (id,revision,snapshot_count,snapshot_digest,captured_at) VALUES (1,'owner-journey-cutover-v1',0,UNHEX(SHA2('[]',256)),UTC_TIMESTAMP(6))");
    $statement=$database->prepare('UPDATE portfolios SET legacy_owner_user_id=owner_user_id WHERE owner_user_id=?');
    $statement->execute([$userId]);
}
