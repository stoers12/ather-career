<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs every case in tests/phase2/registry.php, unchanged, as one PHPUnit test
 * each, sharing one TestEnvironment like scripts/run-phase2-tests.php does. A
 * phase2Assert() failure (RuntimeException) is reported as a PHPUnit failure;
 * anything else surfaces as a PHPUnit error. New tests are written natively in
 * tests/phpunit/ instead (docs/PHASE2_TESTING.md).
 */
final class LegacyPhase2CaseTest extends TestCase
{
    private static ?TestEnvironment $environment = null;

    public static function setUpBeforeClass(): void
    {
        self::$environment = TestEnvironment::create();
    }

    public static function tearDownAfterClass(): void
    {
        $environment = self::$environment;
        self::$environment = null;
        if ($environment !== null) {
            $environment->tearDown();
            self::assertTrue($environment->wasTornDown(), 'Run-owned test namespace still exists after teardown.');
        }
    }

    /** @return iterable<string, array{array{class-string, string}}> */
    public static function registeredCases(): iterable
    {
        foreach (require PHASE2_REPOSITORY_ROOT . '/tests/phase2/registry.php' as $name => $test) {
            yield $name => [$test];
        }
    }

    /** @param array{class-string, string} $test */
    #[DataProvider('registeredCases')]
    public function testRegisteredCase(array $test): void
    {
        try {
            $test(self::$environment);
        } catch (RuntimeException $failure) {
            self::fail($failure->getMessage());
        }
        // Legacy cases assert through phase2Assert(); a completed case counts as one assertion.
        $this->addToAssertionCount(1);
    }
}
