<?php

declare(strict_types=1);

namespace Ather\Http;

/** A resolved request: the legacy entry script, its handler file and any rewritten query. */
final class RouteMatch
{
    /**
     * @param string $entryScript Former public/ entry name, e.g. "p.php"; request identity for legacy checks.
     * @param string $handler Handler file relative to the application root.
     * @param array<string, string>|null $query Replacement query (as the former Apache rewrite produced), or null to keep the request's own.
     */
    public function __construct(
        public readonly string $entryScript,
        public readonly string $handler,
        public readonly ?array $query = null,
    ) {
    }
}
