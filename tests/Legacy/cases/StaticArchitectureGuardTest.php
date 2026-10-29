<?php

declare(strict_types=1);

final class StaticArchitectureGuardTest
{
    public static function run(TestEnvironment $environment): void
    {
        $failures = StaticArchitectureGuards::check(PHASE2_REPOSITORY_ROOT);
        phase2AssertSame([], $failures, 'Static architecture guard failures: ' . implode(' | ', $failures));
    }
}
