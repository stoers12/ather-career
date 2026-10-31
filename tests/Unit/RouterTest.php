<?php

declare(strict_types=1);

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
        yield 'root' => ['/', 'index.php', 'handlers/index.php', null];
        yield 'index script' => ['/index.php', 'index.php', 'handlers/index.php', null];
        yield 'auth0 callback (registered in Auth0)' => ['/owner_oidc_callback.php', 'owner_oidc_callback.php', 'handlers/owner_oidc_callback.php', null];
        yield 'script with path info' => ['/signin.php/extra/part', 'signin.php', 'handlers/signin.php', null];
        yield 'evidence hub pretty' => ['/owner/evidence-hub', 'owner_evidence_hub.php', 'handlers/owner_evidence_hub.php', null];
        yield 'evidence hub trailing slash' => ['/owner/evidence-hub/', 'owner_evidence_hub.php', 'handlers/owner_evidence_hub.php', null];
        yield 'project evidence' => ['/owner/projects/42/evidence', 'owner_project_evidence.php', 'handlers/owner_project_evidence.php', null];
        yield 'portfolio' => ['/p/jane-doe', 'p.php', 'handlers/public_portfolio.php', ['slug' => 'jane-doe']];
        yield 'portfolio slash' => ['/p/jane-doe/', 'p.php', 'handlers/public_portfolio.php', ['slug' => 'jane-doe']];
        yield 'contact' => ['/p/jane-doe/contact', 'p_contact.php', 'handlers/public_contact.php', ['slug' => 'jane-doe']];
        yield 'projects json' => ['/p/jane-doe/projects.json', 'p_projects.php', 'handlers/public_projects_json.php', ['slug' => 'jane-doe']];
        yield 'profile media' => ['/p/jane-doe/media/profile', 'p_media.php', 'handlers/public_media.php', ['slug' => 'jane-doe', 'type' => 'profile']];
        yield 'project media' => ['/p/jane-doe/media/project/9', 'p_media.php', 'handlers/public_media.php', ['slug' => 'jane-doe', 'type' => 'project', 'id' => '9']];
        yield 'project case study' => ['/p/jane-doe/projects/9', 'p_project.php', 'handlers/public_project.php', ['slug' => 'jane-doe', 'id' => '9']];
        yield 'project case study slash' => ['/p/jane-doe/projects/12/', 'p_project.php', 'handlers/public_project.php', ['slug' => 'jane-doe', 'id' => '12']];
        yield 'privacy' => ['/privacy', 'privacy.php', 'handlers/privacy.php', null];
        yield 'terms' => ['/terms', 'terms.php', 'handlers/terms.php', null];
        yield 'legacy portfolio script' => ['/p.php', 'p.php', 'handlers/public_portfolio.php', null];
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
            '/p/jane/projects', '/p/jane/projects/0', '/p/jane/projects/007', '/p/jane/projects/x', '/p/jane/projects/9/evidence',
            '/includes/http.php', '/config/routes.php', '/health.php', '/ready.php', '/assets/css/landing.css', '//index.php', '/index.phpx'] as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('unroutedPaths')]
    public function testRefusesPath(string $path): void
    {
        self::assertNull(self::router()->match($path));
    }

    public function testEveryHandlerExistsAndIsRouted(): void
    {
        $routes = require PHASE2_REPOSITORY_ROOT . '/config/routes.php';
        foreach ($routes['scripts'] as $entry => $handler) {
            self::assertFileExists(PHASE2_REPOSITORY_ROOT . '/' . $handler, "{$entry} routes to a missing handler");
        }
        foreach ($routes['patterns'] as $route) {
            self::assertArrayHasKey($route['script'], $routes['scripts'], "{$route['pattern']} targets an unknown entry script");
        }
        // ready.php is served by public/ready.php directly, so readiness never depends on the router.
        self::assertSame([], glob(PHASE2_REPOSITORY_ROOT . '/*.php') ?: [], 'Repository root must not contain PHP files');
        $handlers = array_map(static fn (string $path): string => 'handlers/' . basename($path), glob(PHASE2_REPOSITORY_ROOT . '/handlers/*.php') ?: []);
        self::assertCount(34, $handlers);
        $routed = [...array_values($routes['scripts']), $routes['not_found']];
        self::assertSame(['handlers/ready.php'], array_values(array_diff($handlers, $routed)), 'A handler has no route');
    }

    public function testPublicHoldsOnlyTheFrontControllerAndProbes(): void
    {
        $files = array_map('basename', glob(PHASE2_REPOSITORY_ROOT . '/public/*.php') ?: []);
        sort($files);

        self::assertSame(['health.php', 'index.php', 'ready.php'], $files);
    }

    public function testUnknownPathsRenderAtherNotFoundPage(): void
    {
        $routes = require PHASE2_REPOSITORY_ROOT . '/config/routes.php';
        self::assertSame('handlers/not_found.php', $routes['not_found']);
        self::assertNull(self::router()->match('/not_found.php'), 'The 404 page must not be an addressable entry');

        ob_start();
        require PHASE2_REPOSITORY_ROOT . '/' . $routes['not_found'];
        $html = (string) ob_get_clean();

        // Visitors may land here, so the page is English and works under the
        // strict CSP: an external stylesheet only, no inline style or script.
        self::assertStringContainsString('<html lang="en" dir="ltr">', $html);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $html);
        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/assets/css/not_found\.css\?v=[0-9a-f]{16}">#', $html);
        self::assertStringContainsString('<h1 id="nf-title">This page doesn\'t exist</h1>', $html);
        self::assertStringContainsString('<a class="nf-action nf-action-primary" href="/">', $html);
        self::assertStringContainsString('<a class="nf-action" href="/signin.php">', $html);
        self::assertDoesNotMatchRegularExpression('#<script|<style|\sstyle=#i', $html);
    }
}
