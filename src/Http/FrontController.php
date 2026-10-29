<?php

declare(strict_types=1);

namespace Ather\Http;

/**
 * Resolves the current request for public/index.php (ENG-09). It returns the
 * handler file that index.php loads at file scope, or answers 404 itself,
 * byte-for-byte as Apache answered an unknown path before the front controller.
 */
final class FrontController
{
    /** Apache 2.4's default 404 document, kept identical (ENG-09 design 7a.2). */
    public const NOT_FOUND_BODY = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\" \"http://www.w3.org/TR/html4/strict.dtd\">\n"
        . "<html><head>\n<title>404 Not Found</title>\n</head><body>\n<h1>Not Found</h1>\n"
        . "<p>The requested URL was not found on this server.</p>\n</body></html>\n";

    /**
     * @param array{
     *     scripts: array<string, string>,
     *     patterns: list<array{pattern: string, script: string, query?: array<string, string>}>
     * } $routes The table from config/routes.php.
     */
    public static function resolve(string $applicationRoot, array $routes): ?string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $match = is_string($path) ? (new Router($routes))->match(rawurldecode($path)) : null;
        if ($match === null) {
            self::notFound();

            return null;
        }

        if ($match->query !== null) {
            // The former rewrites substituted a query and, without QSA, dropped the original.
            $_GET = $match->query;
            $_SERVER['QUERY_STRING'] = http_build_query($match->query, '', '&', PHP_QUERY_RFC3986);
        }
        CurrentRoute::set($match);

        return $applicationRoot . '/' . $match->handler;
    }

    private static function notFound(): void
    {
        http_response_code(404);
        // Apache's own 404 never carried X-Powered-By, even where expose_php is on.
        header_remove('X-Powered-By');
        header('Content-Type: text/html; charset=iso-8859-1');
        echo self::NOT_FOUND_BODY;
    }
}
