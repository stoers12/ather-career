<?php

declare(strict_types=1);

const PHASE2_TEST_ROOT = __DIR__;
const PHASE2_REPOSITORY_ROOT = __DIR__ . '/../..';

require_once __DIR__ . '/support/TestEnvironment.php';
require_once __DIR__ . '/support/SyntheticFixtures.php';
require_once __DIR__ . '/support/TestAuthenticationContext.php';
require_once __DIR__ . '/support/StaticArchitectureGuards.php';
require_once __DIR__ . '/support/SplitSourceFile.php';
require_once __DIR__ . '/support/RouteContract.php';
require_once __DIR__ . '/support/owner-journey-fixtures.php';
require_once __DIR__ . '/support/evidence-hub-technology-review-schema.php';

function phase2Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase2AssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}
