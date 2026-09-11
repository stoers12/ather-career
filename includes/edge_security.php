<?php

declare(strict_types=1);

/*
 * Response security is deliberately self-contained. Auth0 is a top-level
 * navigation target, not a subresource origin, so it is not in this policy.
 */
const EDGE_CONTENT_SECURITY_POLICY = "default-src 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; media-src 'self'";

function edgeSecuritySensitiveResponse(): void
{
    header('Cache-Control: no-store');
}
