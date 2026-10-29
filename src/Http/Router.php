<?php

declare(strict_types=1);

namespace Ather\Http;

/**
 * Maps a decoded request path to a route from config/routes.php (ENG-09).
 *
 * Matching reproduces what Apache did before the front controller: the pretty
 * URLs of the former vhost RewriteRules (first match wins, case-sensitive), and
 * every former public/ entry script, which Apache also served with a trailing
 * PATH_INFO segment such as /signin.php/extra.
 */
final class Router
{
    /**
     * @param array{
     *     scripts: array<string, string>,
     *     patterns: list<array{pattern: string, script: string, query?: array<string, string>}>,
     *     not_found: string
     * } $routes
     */
    public function __construct(private readonly array $routes)
    {
    }

    public function match(string $path): ?RouteMatch
    {
        if ($path === '/') {
            return $this->scriptMatch('index.php');
        }

        foreach ($this->routes['patterns'] as $route) {
            if (preg_match($route['pattern'], $path, $captures) !== 1) {
                continue;
            }
            $query = null;
            if (isset($route['query'])) {
                $query = [];
                foreach ($route['query'] as $name => $value) {
                    $query[$name] = preg_replace_callback(
                        '/\{([a-z]+)\}/',
                        static fn (array $placeholder): string => $captures[$placeholder[1]] ?? '',
                        $value,
                    );
                }
            }

            return $this->scriptMatch($route['script'], $query);
        }

        if (preg_match('#^/([A-Za-z0-9_]+\.php)(?:/.*)?$#', $path, $script) === 1) {
            return $this->scriptMatch($script[1]);
        }

        return null;
    }

    /** @param array<string, string>|null $query */
    private function scriptMatch(string $entryScript, ?array $query = null): ?RouteMatch
    {
        $handler = $this->routes['scripts'][$entryScript] ?? null;

        return $handler === null ? null : new RouteMatch($entryScript, $handler, $query);
    }
}
