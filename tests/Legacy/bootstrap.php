<?php

declare(strict_types=1);

const PHASE2_TEST_ROOT = __DIR__;
const PHASE2_REPOSITORY_ROOT = __DIR__ . '/../..';

require_once __DIR__ . '/../Support/TestEnvironment.php';
require_once __DIR__ . '/../Support/SyntheticFixtures.php';
require_once __DIR__ . '/../Support/TestAuthenticationContext.php';
require_once __DIR__ . '/../Support/StaticArchitectureGuards.php';
require_once __DIR__ . '/../Support/SplitSourceFile.php';
require_once __DIR__ . '/../Support/RouteContract.php';
require_once __DIR__ . '/../Support/owner-journey-fixtures.php';
require_once __DIR__ . '/../Support/evidence-hub-technology-review-schema.php';

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
