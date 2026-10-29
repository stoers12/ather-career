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
}
