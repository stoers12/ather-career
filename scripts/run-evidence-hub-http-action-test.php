<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../tests/phase2/cases/EvidenceHubRecommendationActionHttpMySqlTest.php';

try {
    TestEnvironment::assertSafeEnvironment(getenv());
    if (getenv('EVIDENCE_HUB_HTTP_ACTION_TEST') !== '1') {
        throw new RuntimeException('Evidence Hub HTTP action test requires explicit disposable-test authorization.');
    }
    EvidenceHubRecommendationActionHttpMySqlTest::run();
    fwrite(STDOUT, "HTTP_ACTION_MATRIX_EXIT=0\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL HTTP action matrix: {$exception->getMessage()}\n");
    exit(1);
}
