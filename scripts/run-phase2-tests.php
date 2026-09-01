<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../tests/phase2/cases/EnvironmentSafetyTest.php';
require_once __DIR__ . '/../tests/phase2/cases/FixtureContractTest.php';
require_once __DIR__ . '/../tests/phase2/cases/IdentitySessionContractTest.php';
require_once __DIR__ . '/../tests/phase2/cases/StaticArchitectureGuardTest.php';

$environment = null;
$failures = [];
$tests = [
    'provider-independent internal-user session contract' => [IdentitySessionContractTest::class, 'run'],
    'environment safety and disposable namespace' => [EnvironmentSafetyTest::class, 'run'],
    'synthetic fixture contract and test authentication carrier' => [FixtureContractTest::class, 'run'],
    'static architecture guards' => [StaticArchitectureGuardTest::class, 'run'],
];

try {
    $environment = TestEnvironment::create();
    foreach ($tests as $name => $test) {
        try {
            $test($environment);
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (Throwable $exception) {
            $failures[] = "{$name}: {$exception->getMessage()}";
            fwrite(STDERR, "FAIL {$name}\n");
        }
    }
} catch (Throwable $exception) {
    $failures[] = 'test environment setup: ' . $exception->getMessage();
    fwrite(STDERR, "FAIL test environment setup\n");
} finally {
    if ($environment instanceof TestEnvironment) {
        try {
            $environment->tearDown();
            if (!$environment->wasTornDown()) {
                throw new RuntimeException('Run-owned test namespace still exists after teardown.');
            }
            fwrite(STDOUT, "PASS scoped test teardown\n");
        } catch (Throwable $exception) {
            $failures[] = 'scoped test teardown: ' . $exception->getMessage();
            fwrite(STDERR, "FAIL scoped test teardown\n");
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Phase 2 test foundation failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "Phase 2 test foundation passed.\n";
