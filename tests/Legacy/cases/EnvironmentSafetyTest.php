<?php

declare(strict_types=1);

final class EnvironmentSafetyTest
{
    public static function run(TestEnvironment $environment): void
    {
        phase2Assert(str_starts_with($environment->databaseName, 'ather_career_test_'), 'Test database namespace is not disposable.');
        phase2Assert(str_starts_with($environment->composeProject, 'ather-career-test-'), 'Test Compose namespace is not disposable.');
        phase2Assert(is_dir($environment->storageRoot), 'Test storage namespace was not created.');
        phase2Assert(!str_contains(strtolower($environment->storageRoot), 'careerfit'), 'Test storage namespace is CareerFit-coupled.');

        $probe = $environment->createProbeFile('teardown-proof.txt', 'run-owned');
        phase2Assert(is_file($probe), 'Run-owned probe file was not created.');

        $unsafeRejected = false;
        try {
            TestEnvironment::assertSafeEnvironment(['APP_ENV' => 'production', 'ATHERCAR_TEST_MODE' => '1']);
        } catch (RuntimeException) {
            $unsafeRejected = true;
        }
        phase2Assert($unsafeRejected, 'Production-like test environment was not rejected.');

        $unsafeDatabaseRejected = false;
        try {
            TestEnvironment::assertSafeEnvironment([
                'APP_ENV' => 'test',
                'ATHERCAR_TEST_MODE' => '1',
                'DB_NAME' => 'portfolio_db',
            ]);
        } catch (RuntimeException) {
            $unsafeDatabaseRejected = true;
        }
        phase2Assert($unsafeDatabaseRejected, 'Non-test database target was not rejected.');
    }
}
