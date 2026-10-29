<?php

declare(strict_types=1);

namespace Ather\Http;

/**
 * The route the front controller resolved for this request. Code that used to
 * identify a request by basename($_SERVER['SCRIPT_FILENAME']) reads
 * entryScript() instead, because behind the front controller every request's
 * script is public/index.php.
 */
final class CurrentRoute
{
    private static ?RouteMatch $match = null;

    public static function set(RouteMatch $match): void
    {
        self::$match = $match;
    }

    public static function entryScript(): ?string
    {
        return self::$match?->entryScript;
    }

    public static function reset(): void
    {
        self::$match = null;
    }
}
