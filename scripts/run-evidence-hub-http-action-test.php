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
    $ownerASessionConsumer = null;
    $handoffPath = getenv('EVIDENCE_HUB_HTTP_ACTION_SESSION_HANDOFF');
    if (is_string($handoffPath) && $handoffPath !== '') {
        if (getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1') {
            throw new RuntimeException('Evidence Hub session handoff requires the disposable test environment.');
        }
        if (!str_starts_with($handoffPath, '/tmp/bridge/') || dirname($handoffPath) !== '/tmp/bridge' || basename($handoffPath) !== substr($handoffPath, strlen('/tmp/bridge/'))) {
            throw new RuntimeException('Evidence Hub session handoff destination is not task-owned.');
        }
        if (!is_dir('/tmp/bridge') || file_exists($handoffPath) || is_link($handoffPath)) {
            throw new RuntimeException('Evidence Hub session handoff destination is unavailable.');
        }
        $ownerASessionConsumer = static function (string $sessionId) use ($handoffPath): void {
            $handle = @fopen($handoffPath, 'x');
            if (!is_resource($handle)) {
                throw new RuntimeException('Evidence Hub session handoff could not be created.');
            }
            try {
                if (fwrite($handle, $sessionId) !== strlen($sessionId) || !fflush($handle) || !fclose($handle)) {
                    $handle = null;
                    throw new RuntimeException('Evidence Hub session handoff could not be completed.');
                }
                $handle = null;
            } catch (Throwable $exception) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                @unlink($handoffPath);
                throw $exception;
            }
        };
    }
    EvidenceHubRecommendationActionHttpMySqlTest::run($ownerASessionConsumer);
    fwrite(STDOUT, "HTTP_ACTION_MATRIX_EXIT=0\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL HTTP action matrix: {$exception->getMessage()}\n");
    exit(1);
}
