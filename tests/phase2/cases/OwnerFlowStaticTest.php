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
        phase2Assert(str_contains($onboarding, "httpRedirect('owner.php')"), 'P2J-04 onboarding must PRG after Portfolio creation.');

        foreach (['owner.php', 'owner_profile.php', 'owner_projects.php', 'owner_experiences.php', 'owner_messages.php', 'owner_preview.php', 'owner_publication.php'] as $route) {
            $contents = self::read($route);
            phase2Assert(str_contains($contents, 'startOwnerSession();'), "{$route} does not start the owner session.");
            phase2Assert(str_contains($contents, 'requireOwnerPortfolioContext($database)'), "{$route} does not require a server-derived Portfolio context.");
            phase2Assert(!str_contains($contents, 'require' . 'Admin' . 'Authentication') && !str_contains($contents, 'admin_' . 'logged_in'), "{$route} must not accept retired global authority.");
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

        $profileRoute = self::read('owner_profile.php');
        $adminScript = self::read('admin.js');
        phase2Assert(str_contains($profileRoute, 'id="profile-form"')
            && str_contains($adminScript, "getElementById('profile-form')")
            && str_contains($adminScript, 'formState(profileForm)'), 'Owner profile unsaved-change protection is not wired end to end.');
        foreach (['owner_profile.php', 'owner_projects.php', 'owner_experiences.php', 'includes/owner_publication_presentation.php'] as $route) {
            phase2Assert(str_contains(self::read($route), 'data-confirm='), "{$route} bypasses the shared confirmation dialog for a material action.");
        }
        self::optionalProjectGithubUrl();
    }

    private static function optionalProjectGithubUrl(): void
    {
        $form = self::read('owner_projects.php');
        phase2Assert(str_contains($form, 'GitHub URL <em>(optional)</em>') && str_contains($form, 'Leave blank to hide the public GitHub link.'), 'Owner form does not explain optional GitHub URLs.');
        phase2Assert(!preg_match('/id="github_url"[^>]*\srequired(?:\s|>)/', $form), 'Owner form still requires a GitHub URL.');
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_actions.php';
        phase2AssertSame(null, submittedStringField(['github_url' => '   '], 'github_url', PROJECT_GITHUB_URL_MAX_LENGTH, 'GitHub URL')['error'], 'Blank GitHub URL was rejected.');
        foreach (['http://example.test/project', 'https://example.test/project'] as $url) {
            phase2Assert(isSafeHttpUrl($url), "Valid URL {$url} was rejected.");
        }
        foreach (['ftp://example.test/project', 'javascript:alert(1)', 'not-a-url'] as $url) {
            phase2Assert(!isSafeHttpUrl($url), "Unsafe URL {$url} was accepted.");
        }
        phase2Assert(submittedStringField(['github_url' => 'https://example.test/' . str_repeat('x', 500)], 'github_url', PROJECT_GITHUB_URL_MAX_LENGTH, 'GitHub URL')['error'] !== null, 'Over-limit URL was accepted.');
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            return;
        }
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec("CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL);
            CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, title TEXT, category TEXT, description TEXT, github_url TEXT NULL, image_path TEXT NULL, technologies TEXT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, created_at TEXT, updated_at TEXT);
            INSERT INTO portfolios (id, owner_user_id) VALUES (10, 1), (20, 2);
            INSERT INTO projects (id, portfolio_id, title, category, description, github_url, image_path, technologies, problem_statement, personal_role, measurable_outcome, created_at, updated_at)
            VALUES (19, 10, 'Project', 'Web', 'Description', 'https://example.test/old', NULL, NULL, NULL, NULL, NULL, '2020-01-01', '2020-01-01')");
        $owner = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $otherOwner = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 20);
        $post = ['action' => 'update', 'id' => '19', 'title' => 'Project', 'category' => 'Web', 'description' => 'Description', 'technologies' => '', 'github_url' => ''];
        foreach (['http://example.test/new', 'https://example.test/new'] as $url) {
            $post['github_url'] = $url;
            phase2AssertSame(200, handleAuthorizedProjectAction($database, $owner, $post, [])['status'], "Owner form rejected {$url}.");
        }
        $database->exec("UPDATE projects SET updated_at = '2020-01-01' WHERE id = 19");
        $before = $database->query('SELECT * FROM projects WHERE id = 19')->fetch(PDO::FETCH_ASSOC);
        $post['github_url'] = '  ';
        phase2AssertSame(200, handleAuthorizedProjectAction($database, $owner, $post, [])['status'], 'Owner form rejected blank URL.');
        $after = $database->query('SELECT * FROM projects WHERE id = 19')->fetch(PDO::FETCH_ASSOC);
        phase2AssertSame(null, $after['github_url'], 'Blank URL did not persist as NULL.');
        $changes = [];
        foreach ($before as $column => $value) {
            if ($value !== $after[$column]) {
                $changes[] = $column;
            }
        }
        phase2AssertSame(['github_url', 'updated_at'], $changes, 'Clearing a URL changed an unrelated project column.');
        foreach (['ftp://example.test/bad', 'not-a-url', 'https://example.test/' . str_repeat('x', 500)] as $url) {
            $post['github_url'] = $url;
            phase2AssertSame(422, handleAuthorizedProjectAction($database, $owner, $post, [])['status'], 'Invalid URL was accepted by Owner form.');
        }
        $post['github_url'] = 'https://example.test/foreign';
        phase2AssertSame(404, handleAuthorizedProjectAction($database, $otherOwner, $post, [])['status'], 'Foreign Owner updated project URL.');
        phase2AssertSame($after, $database->query('SELECT * FROM projects WHERE id = 19')->fetch(PDO::FETCH_ASSOC), 'Rejected requests changed the project.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
