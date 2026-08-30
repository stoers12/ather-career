<?php

function versionedAssetUrl(string $relativePath): string
{
    $projectRoot = dirname(__DIR__);
    $assetPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $version = is_file($assetPath) ? (string) filemtime($assetPath) : '1';

    return $relativePath . '?v=' . rawurlencode($version);
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
