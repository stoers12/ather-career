<?php

declare(strict_types=1);

require_once __DIR__ . '/public_lifecycle.php';

final class PublicUrlConfigurationException extends RuntimeException
{
}

function publicUrlApplicationEnvironment(): string
{
    $environment = getenv('APP_ENV');

    return is_string($environment) && trim($environment) !== ''
        ? strtolower(trim($environment))
        : 'development';
}

function publicUrlIsLoopbackHost(string $host): bool
{
    $normalizedHost = strtolower(trim($host, '[]'));

    return $normalizedHost === 'localhost'
        || $normalizedHost === '::1'
        || filter_var($normalizedHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === '127.0.0.1';
}

function publicUrlHostIsValid(string $host): bool
{
    $normalizedHost = trim($host, '[]');
    if ($normalizedHost === '' || preg_match('/[\s\/?#@]/', $normalizedHost) === 1) {
        return false;
    }
    if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false) {
        return true;
    }

    return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $normalizedHost) === 1;
}

function publicBaseUrl(): string
{
    $configured = getenv('PUBLIC_BASE_URL');
    if (!is_string($configured) || trim($configured) === '') {
        throw new PublicUrlConfigurationException('PUBLIC_BASE_URL must be configured as an absolute origin.');
    }

    $value = trim($configured);
    if (str_starts_with($value, '//') || !filter_var($value, FILTER_VALIDATE_URL)) {
        throw new PublicUrlConfigurationException('PUBLIC_BASE_URL must be a valid absolute origin.');
    }

    $parts = parse_url($value);
    if (!is_array($parts)
        || !isset($parts['scheme'], $parts['host'])
        || array_key_exists('user', $parts)
        || array_key_exists('pass', $parts)
        || array_key_exists('query', $parts)
        || array_key_exists('fragment', $parts)
        || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        || !publicUrlHostIsValid((string) $parts['host'])) {
        throw new PublicUrlConfigurationException('PUBLIC_BASE_URL must be a valid absolute origin.');
    }

    $path = $parts['path'] ?? '';
    if ($path !== '' && $path !== '/') {
        throw new PublicUrlConfigurationException('PUBLIC_BASE_URL must not include a path.');
    }

    $port = $parts['port'] ?? null;
    if ($port !== null && (!is_int($port) || $port < 1 || $port > 65535)) {
        throw new PublicUrlConfigurationException('PUBLIC_BASE_URL has an invalid port.');
    }

    $scheme = strtolower((string) $parts['scheme']);
    if (publicUrlApplicationEnvironment() === 'production' && $scheme !== 'https') {
        throw new PublicUrlConfigurationException('PUBLIC_BASE_URL must use HTTPS in production.');
    }
    if ($scheme === 'http' && !publicUrlIsLoopbackHost((string) $parts['host'])) {
        throw new PublicUrlConfigurationException('HTTP PUBLIC_BASE_URL values are limited to explicitly configured loopback origins.');
    }

    $authority = (string) $parts['host'];
    if ($port !== null) {
        $authority .= ':' . $port;
    }

    return $scheme . '://' . $authority;
}

function publicPortfolioUrl(string $slug): string
{
    $normalizedSlug = requirePublicSlug($slug);

    return publicBaseUrl() . '/p/' . rawurlencode($normalizedSlug);
}
