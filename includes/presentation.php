<?php

/**
 * Absolute URL of a public asset, versioned by its content so that every
 * build and machine emits the same URL for the same bytes.
 *
 * @param string $relativePath path below public/, e.g. "assets/css/landing.css"
 */
function versionedAssetUrl(string $relativePath): string
{
    static $versions = [];
    if (!preg_match('#^assets/(?:css|js|fonts|images)/[A-Za-z0-9_.-]+$#', $relativePath) || str_contains($relativePath, '..')) {
        throw new InvalidArgumentException('Unsupported public asset path.');
    }
    if (!isset($versions[$relativePath])) {
        $assetPath = dirname(__DIR__) . '/public/' . $relativePath;
        $hash = is_file($assetPath) ? hash_file('sha256', $assetPath) : false;
        if (!is_string($hash)) {
            throw new RuntimeException('Public asset is missing: ' . $relativePath);
        }
        $versions[$relativePath] = substr($hash, 0, 16);
    }

    return '/' . $relativePath . '?v=' . $versions[$relativePath];
}
function utf8FirstCharacter(string $value): string
{
    if (preg_match('/^./us', $value, $match) !== 1) {
        return '';
    }

    return $match[0];
}

function profileInitials(string $fullName): string
{
    $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false || $parts === []) {
        return '';
    }

    $initials = utf8FirstCharacter($parts[0]);
    if (count($parts) > 1) {
        $initials .= utf8FirstCharacter($parts[count($parts) - 1]);
    }

    return strtoupper($initials);
}

function isPublicWebsiteDestination(string $url): bool
{
    if (!isSafeHttpUrl($url)) {
        return false;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host)) {
        return false;
    }

    $host = strtolower(trim($host, '[]'));
    if ($host === 'localhost' || $host === '::1') {
        return false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        return explode('.', $host, 2)[0] !== '127';
    }

    return true;
}
