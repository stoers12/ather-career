<?php

declare(strict_types=1);

final class FixtureContractTest
{
    public static function run(TestEnvironment $environment): void
    {
        $fixtures = SyntheticFixtures::all();
        foreach (['USER_A', 'PORTFOLIO_A', 'USER_B', 'PORTFOLIO_B', 'USER_NO_PORTFOLIO', 'USER_DISABLED', 'PRESERVED_V1_OWNER', 'PRESERVED_V1_PORTFOLIO'] as $requiredFixture) {
            phase2Assert(isset($fixtures[$requiredFixture]), "Missing canonical fixture {$requiredFixture}.");
        }

        phase2AssertSame($fixtures['USER_A']['id'], $fixtures['PORTFOLIO_A']['owner_user_id'], 'Portfolio A ownership fixture is inconsistent.');
        phase2AssertSame($fixtures['USER_B']['id'], $fixtures['PORTFOLIO_B']['owner_user_id'], 'Portfolio B ownership fixture is inconsistent.');
        phase2AssertSame('disabled', $fixtures['USER_DISABLED']['account_status'], 'Disabled-user fixture is inconsistent.');
        phase2AssertSame(1, $fixtures['USER_A']['authz_version'], 'User A authorization-version fixture is inconsistent.');
        phase2AssertSame(1, $fixtures['USER_B']['authz_version'], 'User B authorization-version fixture is inconsistent.');
        phase2Assert(!array_key_exists('portfolio_id', $fixtures['USER_NO_PORTFOLIO']), 'No-Portfolio fixture must not select a Portfolio.');

        $context = TestAuthenticationContext::fromFixture('USER_A');
        phase2AssertSame((int) $fixtures['USER_A']['id'], $context->userId, 'Test authentication context must derive only from fixture data.');
        phase2AssertSame('active', $context->accountStatus, 'Test authentication context status is inconsistent.');
    }
}
