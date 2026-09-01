<?php

declare(strict_types=1);

final class TenantAuthorizationStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $authorization = self::read('includes/authorization.php');
        $scopedData = self::read('includes/portfolio_scoped_data.php');

        phase2Assert(str_contains($authorization, 'function requireAuthenticatedUser(PDO $database): AuthenticatedUserContext'), 'P2J-03 authenticated User helper is missing.');
        phase2Assert(str_contains($authorization, 'function requireOwnedPortfolioContext(PDO $database): AuthorizedPortfolioContext'), 'P2J-03 owned Portfolio helper is missing.');
        phase2Assert(str_contains($authorization, 'account_status') && str_contains($authorization, "!== 'active'"), 'Current active-account validation is missing.');
        phase2Assert(str_contains($authorization, 'authz_version') && str_contains($authorization, '!== $session[\'authz_version\']'), 'Session authz version validation is missing.');
        phase2Assert(str_contains($authorization, 'WHERE owner_user_id = :internal_user_id'), 'Owned Portfolio lookup is not derived from the current User.');
        phase2Assert(!str_contains($authorization, '$_GET') && !str_contains($authorization, '$_POST') && !str_contains($authorization, '$_REQUEST') && !str_contains($authorization, '$_COOKIE') && !str_contains($authorization, '$_SERVER'), 'Authorization helpers must not accept client tenant selectors.');

        foreach ([
            'WHERE portfolio_id = :authorized_portfolio_id',
            'WHERE recipient_portfolio_id = :authorized_portfolio_id',
            "WHERE id = :resource_id\n           AND portfolio_id = :authorized_portfolio_id",
            "WHERE id = :resource_id\n           AND recipient_portfolio_id = :authorized_portfolio_id",
            'AND portfolio_id = :authorized_portfolio_id',
            'AND recipient_portfolio_id = :authorized_portfolio_id',
            'INSERT INTO skills (portfolio_id, skill_name)',
            'INSERT INTO projects (portfolio_id, title, category, description, github_url, image_path)',
            'INSERT INTO personal_info (',
        ] as $requiredFragment) {
            phase2Assert(str_contains($scopedData, $requiredFragment), "P2J-03 scoped data contract is missing {$requiredFragment}.");
        }

        phase2Assert(!str_contains($scopedData, 'storeManagedUpload') && !str_contains($scopedData, 'deleteManagedFile') && !str_contains($scopedData, 'unlink('), 'Scoped data helpers must not cause filesystem effects.');
        phase2Assert(!str_contains($scopedData, '$_GET') && !str_contains($scopedData, '$_POST') && !str_contains($scopedData, '$_REQUEST') && !str_contains($scopedData, '$_COOKIE') && !str_contains($scopedData, '$_SERVER'), 'Scoped data helpers must not accept client tenant selectors.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
