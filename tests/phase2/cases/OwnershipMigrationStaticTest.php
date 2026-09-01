<?php

declare(strict_types=1);

final class OwnershipMigrationStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $expand = self::read('database/migrations/003_ownership_expand.sql');
        $contract = self::read('database/migrations/004_ownership_contract.sql');
        $runner = self::read('database/migrate.php');
        $backfill = self::read('database/backfill-v1-ownership.php');

        phase2Assert(str_contains($expand, 'Migration 003'), 'Migration 003 ownership expand marker is missing.');
        phase2Assert(str_contains($contract, 'Migration 004'), 'Migration 004 ownership contract marker is missing.');
        phase2Assert(str_contains($runner, 'owner_user_id INT UNSIGNED NOT NULL'), 'Portfolio ownership must be non-null from Migration 003.');
        phase2Assert(str_contains($runner, 'UNIQUE KEY uq_portfolios_owner_user_id'), 'One-User/one-Portfolio integrity is missing.');
        phase2Assert(str_contains($runner, 'UNIQUE KEY uq_users_oidc_subject'), 'The accepted single-issuer subject uniqueness is missing.');
        phase2Assert(str_contains($runner, 'chk_users_account_status'), 'The account-status state constraint is missing.');
        phase2Assert(str_contains($runner, 'chk_users_authz_version_positive'), 'The positive authz_version constraint is missing.');
        phase2Assert(str_contains($runner, 'OwnershipBackfill::assertReadyForContract'), 'Migration 004 must verify backfill before contract DDL.');
        phase2Assert(str_contains($runner, 'ON UPDATE RESTRICT ON DELETE RESTRICT'), 'Ownership foreign keys must use RESTRICT lifecycle behavior.');
        phase2Assert(str_contains($backfill, 'testOnlyBackfillFailurePointAllowed'), 'Backfill test failure injection must be explicitly test-gated.');
        phase2Assert(str_contains($runner, "getenv('APP_ENV') !== 'test'"), 'Migration failure injection must fail outside the explicit test environment.');

        // Later additive migrations share the runner. P2J-02's schema boundary is
        // established by its two immutable migration descriptors, not by forbidding
        // all future migration names in the shared runner.
        $ownershipSources = $expand . "\n" . $contract . "\n" . $backfill;
        foreach (['public_slug', 'is_published', 'published_at'] as $forbiddenColumn) {
            phase2Assert(!str_contains($ownershipSources, $forbiddenColumn), "P2J-02 must not add {$forbiddenColumn}.");
        }
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        if (!is_string($contents)) {
            throw new RuntimeException("{$relativePath} is unreadable.");
        }

        return $contents;
    }
}
