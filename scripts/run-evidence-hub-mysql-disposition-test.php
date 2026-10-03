<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../tests/phase2/cases/EvidenceHubRecommendationDispositionMySqlTest.php';

try {
    TestEnvironment::assertSafeEnvironment(getenv());
    EvidenceHubRecommendationDispositionMySqlTest::run();
    fwrite(STDOUT, "PASS Evidence Hub R3 disposable MySQL disposition repository and constraints\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL Evidence Hub R3 disposable MySQL disposition repository and constraints: {$exception->getMessage()}\n");
    exit(1);
}
