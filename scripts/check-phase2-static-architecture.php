<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/support/StaticArchitectureGuards.php';

$failures = StaticArchitectureGuards::check(dirname(__DIR__));
if ($failures !== []) {
    fwrite(STDERR, "Phase 2 static architecture guard failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "Phase 2 static architecture guard passed.\n";
