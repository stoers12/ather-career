<?php

declare(strict_types=1);

use Ather\Http\FrontController;
use Ather\Http\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The production route table (config/routes.php) through the router (ENG-09). */
final class RouterTest extends TestCase
{
    private static function router(): Router
    {
        return new Router(require PHASE2_REPOSITORY_ROOT . '/config/routes.php');
    }

    /** @return iterable<string, array{string, string, string, array<string, string>|null}> */
    public static function routedPaths(): iterable
    {
        yield 'root' => ['/', 'index.php', 'index.php', null];
        yield 'index script' => ['/index.php', 'index.php', 'index.php', null];
        yield 'auth0 callback (registered in Auth0)' => ['/owner_oidc_callback.php', 'owner_oidc_callback.php', 'owner_oidc_callback.php', null];
        yield 'script with path info' => ['/signin.php/extra/part', 'signin.php', 'signin.php', null];
        yield 'evidence hub pretty' => ['/owner/evidence-hub', 'owner_evidence_hub.php', 'owner_evidence_hub.php', null];
        yield 'evidence hub trailing slash' => ['/owner/evidence-hub/', 'owner_evidence_hub.php', 'owner_evidence_hub.php', null];
        yield 'project evidence' => ['/owner/projects/42/evidence', 'owner_project_evidence.php', 'owner_project_evidence.php', null];
        yield 'portfolio' => ['/p/jane-doe', 'p.php', 'public_portfolio.php', ['slug' => 'jane-doe']];
        yield 'portfolio slash' => ['/p/jane-doe/', 'p.php', 'public_portfolio.php', ['slug' => 'jane-doe']];
        yield 'contact' => ['/p/jane-doe/contact', 'p_contact.php', 'public_contact.php', ['slug' => 'jane-doe']];
        yield 'projects json' => ['/p/jane-doe/projects.json', 'p_projects.php', 'public_projects_json.php', ['slug' => 'jane-doe']];
        yield 'profile media' => ['/p/jane-doe/media/profile', 'p_media.php', 'public_media.php', ['slug' => 'jane-doe', 'type' => 'profile']];
        yield 'project media' => ['/p/jane-doe/media/project/9', 'p_media.php', 'public_media.php', ['slug' => 'jane-doe', 'type' => 'project', 'id' => '9']];
        yield 'legacy portfolio script' => ['/p.php', 'p.php', 'public_portfolio.php', null];
    }

    /** @param array<string, string>|null $query */
    #[DataProvider('routedPaths')]
    public function testRoutesPath(string $path, string $entryScript, string $handler, ?array $query): void
    {
        $match = self::router()->match($path);

        self::assertNotNull($match);
        self::assertSame($entryScript, $match->entryScript);
        self::assertSame($handler, $match->handler);
        self::assertSame($query, $match->query);
    }

    /** @return iterable<string, array{string}> */
    public static function unroutedPaths(): iterable
    {
        foreach (['/nope', '/nope.php', '/Signin.php', '/owner/evidence-hubx', '/owner/projects/0/evidence', '/owner/projects/x/evidence',
            '/p/ab', '/p/Jane', '/p/-jane', '/p/jane-', '/p/' . str_repeat('a', 65), '/p/jane/projects.json/', '/p/jane/media/project/0',
            '/includes/http.php', '/config/routes.php', '/health.php', '/ready.php', '/assets/css/landing.css', '//index.php', '/index.phpx'] as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('unroutedPaths')]
    public function testRefusesPath(string $path): void
    {
        self::assertNull(self::router()->match($path));
    }

    public function testEveryHandlerExistsAndEveryRootHandlerIsRouted(): void
    {
        $routes = require PHASE2_REPOSITORY_ROOT . '/config/routes.php';
        foreach ($routes['scripts'] as $entry => $handler) {
            self::assertFileExists(PHASE2_REPOSITORY_ROOT . '/' . $handler, "{$entry} routes to a missing handler");
        }
        foreach ($routes['patterns'] as $route) {
            self::assertArrayHasKey($route['script'], $routes['scripts'], "{$route['pattern']} targets an unknown entry script");
        }
        // ready.php is served by public/ready.php directly, so readiness never depends on the router.
        $rootHandlers = array_map('basename', glob(PHASE2_REPOSITORY_ROOT . '/*.php') ?: []);
        self::assertSame(['ready.php'], array_values(array_diff($rootHandlers, array_values($routes['scripts']))), 'A root handler has no route');
    }

    public function testPublicHoldsOnlyTheFrontControllerAndProbes(): void
    {
        $files = array_map('basename', glob(PHASE2_REPOSITORY_ROOT . '/public/*.php') ?: []);
        sort($files);

        self::assertSame(['health.php', 'index.php', 'ready.php'], $files);
    }

    public function testNotFoundBodyMatchesApacheDefault(): void
    {
        self::assertSame(236, strlen(FrontController::NOT_FOUND_BODY));
        self::assertStringContainsString('<p>The requested URL was not found on this server.</p>', FrontController::NOT_FOUND_BODY);
    }
}
