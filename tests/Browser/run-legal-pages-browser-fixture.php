<?php

declare(strict_types=1);

require_once __DIR__ . '/../Support/legal-page-fixtures.php';
if (in_array('--published', $_SERVER['argv'] ?? [], true)) { legalFixturePublish(dirname(__DIR__, 2)); }
putenv('EVIDENCE_HUB_VISUAL_TEST=1');
copy(__DIR__ . '/../Support/apache-gate-fixtures.conf', '/etc/apache2/conf-enabled/zzz-legal-test-fixtures.conf');
$process = proc_open([PHP_BINARY, __DIR__ . '/public-portfolio-visual.php'], [0 => ['pipe', 'r'], 1 => ['file', __DIR__ . '/legal-portfolio-preview.html', 'w'], 2 => ['file', '/tmp/legal-fixture-private.log', 'a']], $pipes);
if (!is_resource($process)) { exit(1); }
fclose($pipes[0]);
if (proc_close($process) !== 0) { exit(1); }
passthru('apache2-foreground', $status);
exit($status);
