<?php

declare(strict_types=1);

/**
 * Schema-neutral fixture contracts. They intentionally model future Phase 2
 * identities without creating Phase 2 rows before P2J-02.
 */
final class SyntheticFixtures
{
    /** @return array<string, array<string, int|string|null>> */
    public static function all(): array
    {
        return [
            'USER_A' => ['id' => 101, 'issuer' => 'https://issuer.test/ather-career', 'subject' => 'subject-user-a', 'account_status' => 'active', 'authz_version' => 1],
            'PORTFOLIO_A' => ['id' => 1001, 'owner_user_id' => 101, 'slug' => 'portfolio-a'],
            'USER_B' => ['id' => 102, 'issuer' => 'https://issuer.test/ather-career', 'subject' => 'subject-user-b', 'account_status' => 'active', 'authz_version' => 1],
            'PORTFOLIO_B' => ['id' => 1002, 'owner_user_id' => 102, 'slug' => 'portfolio-b'],
            'USER_NO_PORTFOLIO' => ['id' => 103, 'issuer' => 'https://issuer.test/ather-career', 'subject' => 'subject-user-no-portfolio', 'account_status' => 'active', 'authz_version' => 1],
            'USER_DISABLED' => ['id' => 104, 'issuer' => 'https://issuer.test/ather-career', 'subject' => 'subject-user-disabled', 'account_status' => 'disabled', 'authz_version' => 1],
            'PRESERVED_V1_OWNER' => ['id' => 105, 'issuer' => 'https://issuer.test/ather-career', 'subject' => 'subject-preserved-v1-owner', 'account_status' => 'active', 'authz_version' => 1],
            'PRESERVED_V1_PORTFOLIO' => ['id' => 1005, 'owner_user_id' => 105, 'slug' => 'preserved-v1-portfolio'],
        ];
    }
}
