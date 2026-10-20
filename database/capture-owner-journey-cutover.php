<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/migrate.php';
try {
    $database=getDatabaseConnection();
    $name=$database->query('SELECT DATABASE()')->fetchColumn();
    if ($argc!==3 || $argv[1]!=='--confirm-exclusive-maintenance' || $argv[2]!=='--database='.$name) { throw new RuntimeException('Explicit exclusive-maintenance and exact database acknowledgement required.'); }
    captureLegacyOwnerCutover($database);
    echo "Frozen legacy owner cutover verified. No new owners adopted on retry.\n";
} catch (Throwable) { fwrite(STDERR,"Owner cutover stopped; inspect schema/snapshot and database outcome under exclusive maintenance.\n"); exit(1); }
