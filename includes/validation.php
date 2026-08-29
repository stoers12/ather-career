<?php

const PROJECT_TITLE_MAX_LENGTH = 150;
const PROJECT_CATEGORY_MAX_LENGTH = 100;
const PROJECT_GITHUB_URL_MAX_LENGTH = 500;
const SKILL_NAME_MAX_LENGTH = 100;

const PERSONAL_INFO_FIELD_MAX_LENGTHS = [
    'full_name' => 150,
    'professional_title' => 150,
    'email' => 150,
    'phone_primary' => 30,
    'phone_secondary' => 30,
    'location' => 150,
    'linkedin_url' => 255,
    'github_url' => 255,
    'instagram_url' => 255,
    'facebook_url' => 255,
    'website_url' => 255,
];

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

function utf8CharacterLength(string $value): ?int
{
    if (preg_match('//u', $value) !== 1) {
        return null;
    }

    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    if (function_exists('iconv_strlen')) {
        $length = iconv_strlen($value, 'UTF-8');
        if ($length !== false) {
            return $length;
        }
    }

    $length = preg_match_all('/./us', $value);

    return $length === false ? null : $length;
}

function utf8FieldLengthError(string $value, int $maximum, string $label): ?string
{
    $length = utf8CharacterLength($value);
    if ($length === null) {
        return $label . ' must contain valid UTF-8 characters.';
    }

    if ($length > $maximum) {
        return $label . ' must be ' . $maximum . ' characters or fewer.';
    }

    return null;
}
