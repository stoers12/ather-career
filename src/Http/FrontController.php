<?php

declare(strict_types=1);

namespace Ather\Http;

/**
 * Resolves the current request for public/index.php (ENG-09). It returns the
 * handler file that index.php loads at file scope. An unknown path gets status
 * 404 and the route table's not_found handler, which renders Ather's page
 * (owner decision 2026-10-29); it has no request identity, so CurrentRoute
 * stays unset.
 */
final class FrontController
{
    /**
     * @param array{
     *     scripts: array<string, string>,
     *     patterns: list<array{pattern: string, script: string, query?: array<string, string>}>,
     *     not_found: string
     * } $routes The table from config/routes.php.
     */
    public static function resolve(string $applicationRoot, array $routes): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $match = is_string($path) ? (new Router($routes))->match(rawurldecode($path)) : null;
        if ($match === null) {
            http_response_code(404);
            // Unknown paths never advertised PHP, matching the former Apache 404.
            header_remove('X-Powered-By');

            return $applicationRoot . '/' . $routes['not_found'];
        }

        if ($match->query !== null) {
            // The former rewrites substituted a query and, without QSA, dropped the original.
            $_GET = $match->query;
            $_SERVER['QUERY_STRING'] = http_build_query($match->query, '', '&', PHP_QUERY_RFC3986);
        }
        CurrentRoute::set($match);

        return $applicationRoot . '/' . $match->handler;
    }
}
