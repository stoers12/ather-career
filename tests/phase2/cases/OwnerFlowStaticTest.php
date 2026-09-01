<?php

declare(strict_types=1);

final class OwnerFlowStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $ownerFlow = self::read('includes/owner_flow.php');
        $ownerActions = self::read('includes/owner_actions.php');
        $onboarding = self::read('owner_onboarding.php');

        phase2Assert(str_contains($ownerFlow, 'function requireOwnerPortfolioContext(PDO $database): AuthorizedPortfolioContext'), 'P2J-04 owner Portfolio route helper is missing.');
        phase2Assert(str_contains($ownerFlow, 'return requireOwnedPortfolioContext($database);'), 'P2J-04 owner routes do not reuse the P2J-03 context.');
        phase2Assert(str_contains($ownerFlow, 'function createOwnedPortfolio(PDO $database, AuthenticatedUserContext $user): ?int'), 'P2J-04 server-owned Portfolio creation helper is missing.');
        phase2Assert(str_contains($ownerFlow, 'INSERT INTO portfolios (owner_user_id)'), 'P2J-04 Portfolio creation is missing its owner relationship.');
        phase2Assert(!str_contains($ownerFlow, '$_POST[\'owner_user_id\']') && !str_contains($ownerFlow, '$_GET[\'owner_user_id\']'), 'P2J-04 must not accept client owner identity.');
        phase2Assert(str_contains($onboarding, 'requireOwnerAuthenticatedUser($database)') && str_contains($onboarding, 'createOwnedPortfolio($database, $user)'), 'P2J-04 onboarding does not validate the current User before server-owned creation.');
        phase2Assert(str_contains($onboarding, "header('Location: owner.php', true, 303)"), 'P2J-04 onboarding must PRG after Portfolio creation.');

        foreach (['owner.php', 'owner_profile.php', 'owner_projects.php', 'owner_messages.php', 'owner_preview.php'] as $route) {
            $contents = self::read($route);
            phase2Assert(str_contains($contents, 'startOwnerSession();'), "{$route} does not start the owner session.");
            phase2Assert(str_contains($contents, 'requireOwnerPortfolioContext($database)'), "{$route} does not require a server-derived Portfolio context.");
            phase2Assert(!str_contains($contents, 'requireAdminAuthentication') && !str_contains($contents, 'admin_logged_in'), "{$route} must not accept V1 admin authority.");
        }

        foreach ([
            'loadAuthorizedPersonalInfo',
            'listAuthorizedSkills',
            'findAuthorizedProject',
            'createAuthorizedProject',
            'updateAuthorizedProject',
            'deleteAuthorizedProject',
            'findAuthorizedMessage',
            'authorizedPortfolioDashboardAggregate',
        ] as $required) {
            phase2Assert(str_contains($ownerActions . self::read('owner.php') . self::read('owner_profile.php') . self::read('owner_messages.php'), $required), "P2J-04 owner flow is missing {$required} scoped access.");
        }

        phase2Assert(str_contains($ownerActions, 'findAuthorizedProject($database, $context, $projectId)') && str_contains($ownerActions, 'storeValidatedProjectImage'), 'P2J-04 must scope a project before a project upload can be stored.');
        phase2Assert(!str_contains($ownerActions, 'WHERE id = :id'), 'P2J-04 owner actions must not use unscoped ID mutations.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
