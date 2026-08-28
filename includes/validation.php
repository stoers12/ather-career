<?php

function isSafeHttpUrl(string $url): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);

    return $parts !== false
        && isset($parts['scheme'], $parts['host'])
        && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        && $parts['host'] !== '';
}
