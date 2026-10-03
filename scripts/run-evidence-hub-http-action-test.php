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
    if (($argv[1] ?? '') === '--seed') {
        EvidenceHubRecommendationActionHttpMySqlTest::seedSyntheticOwners();
        fwrite(STDOUT, "HTTP_ACTION_SYNTHETIC_OWNERS_SEEDED\n");
        exit(0);
    }
    if (count($argv) !== 1) {
        throw new RuntimeException('Evidence Hub HTTP action test accepts only the --seed preparation mode.');
    }
    EvidenceHubRecommendationActionHttpMySqlTest::run();
    fwrite(STDOUT, "HTTP_ACTION_MATRIX_EXIT=0\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL HTTP action matrix: {$exception->getMessage()}\n");
    exit(1);
}
