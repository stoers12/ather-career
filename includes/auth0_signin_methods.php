<?php

declare(strict_types=1);

/** Connections verified in the configured application's read-only public metadata. */
function auth0SignInConnection(mixed $method): ?string
{
    if ($method === null) {
        return null;
    }

    $connections = [
        'google' => 'google-oauth2',
        'email' => 'Username-Password-Authentication',
    ];
    if (!is_string($method) || !isset($connections[$method])) {
        throw new InvalidArgumentException('Sign-in method is unavailable.');
    }

    return $connections[$method];
}
