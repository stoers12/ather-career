<?php
declare(strict_types=1);
require_once __DIR__.'/../../tests/Support/draft-fixtures.php';
if (!is_file('/.dockerenv')) { exit(2); }
$db=getDatabaseConnection();
if (($argv[1]??'')==='park') { $db->exec('RENAME TABLE onboarding_drafts TO editor_test_parked_drafts'); }
elseif (($argv[1]??'')==='restore') { $db->exec('RENAME TABLE editor_test_parked_drafts TO onboarding_drafts'); }
else { exit(2); }
