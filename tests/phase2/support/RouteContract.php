<?php

declare(strict_types=1);

/**
 * Static tests assert routing behavior through the production route table and
 * router (ENG-09), instead of matching Apache RewriteRule text: a route either
 * resolves to the expected handler and query, or the contract fails.
 */
final class RouteContract
{
    /** @return array{script: string, handler: string, query: array<string, string>|null}|null */
    public static function resolve(string $path): ?array
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/autoload.php';
        $match = (new \Ather\Http\Router(require PHASE2_REPOSITORY_ROOT . '/config/routes.php'))->match($path);

        return $match === null ? null : ['script' => $match->entryScript, 'handler' => $match->handler, 'query' => $match->query];
    }

    /** @param array<string, string>|null $query */
    public static function assertRoute(string $path, string $handler, ?array $query, string $message): void
    {
        $resolved = self::resolve($path);
        phase2Assert($resolved !== null && $resolved['handler'] === $handler && $resolved['query'] === $query, $message);
    }

    public static function assertNoRoute(string $path, string $message): void
    {
        phase2Assert(self::resolve($path) === null, $message);
    }

    /**
     * Writes the former per-route public/ entry scripts into a private document root.
     *
     * HTTP harnesses serve such a root with PHP's built-in server, which answers 404
     * for a missing *.php file instead of falling back to index.php, so they need one
     * entry script per route. Each is generated from the route table, so it requires
     * the same handler as the public/ script it replaced and as the front controller.
     * Without a list, the probes public/ still serves are copied too, recreating the
     * whole document root the harnesses were written for.
     *
     * @param list<string>|null $scripts
     */
    public static function writeEntryScripts(string $documentRoot, ?array $scripts = null): void
    {
        $routes = require PHASE2_REPOSITORY_ROOT . '/config/routes.php';
        foreach ($scripts ?? array_keys($routes['scripts']) as $script) {
            phase2Assert(isset($routes['scripts'][$script]), 'Unknown route script ' . $script . '.');
            $source = "<?php\n\nrequire dirname(__DIR__) . '/app/" . $routes['scripts'][$script] . "';\n";
            phase2Assert(file_put_contents($documentRoot . '/' . $script, $source) !== false, 'Could not write the entry script ' . $script . '.');
        }
        if ($scripts === null) {
            foreach (['health.php', 'ready.php'] as $probe) {
                phase2Assert(copy(PHASE2_REPOSITORY_ROOT . '/public/' . $probe, $documentRoot . '/' . $probe), 'Could not copy the probe ' . $probe . '.');
            }
        }
    }
}
