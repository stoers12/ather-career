<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../tests/phase2/cases/EvidenceHubTimestampMySqlTest.php';

try {
    TestEnvironment::assertSafeEnvironment(getenv());
    EvidenceHubTimestampMySqlTest::run();
    fwrite(STDOUT, "PASS Evidence Hub disposable MySQL UTC timestamp contract\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL Evidence Hub disposable MySQL UTC timestamp contract: {$exception->getMessage()}\n");
    exit(1);
}
