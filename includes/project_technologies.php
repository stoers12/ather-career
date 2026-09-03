<?php

declare(strict_types=1);

require_once __DIR__ . '/validation.php';

const PROJECT_TECHNOLOGIES_MAXIMUM = 12;
const PROJECT_TECHNOLOGY_MAX_LENGTH = 60;

/**
 * @return list<string>|null Null means the submitted value is invalid.
 */
function normalizeProjectTechnologies(mixed $input, array &$errors): ?array
{
    if (!is_string($input)) {
        $errors[] = 'Technologies must be submitted as text.';

        return null;
    }

    $lines = preg_split('/\R/u', $input);
    if ($lines === false) {
        $errors[] = 'Technologies must contain valid UTF-8 characters.';

        return null;
    }

    $technologies = [];
    $seen = [];
    foreach ($lines as $line) {
        $label = trim($line);
        if ($label === '') {
            continue;
        }

        $length = utf8CharacterLength($label);
        if ($length === null) {
            $errors[] = 'Each technology must contain valid UTF-8 characters.';

            return null;
        }
        if ($length > PROJECT_TECHNOLOGY_MAX_LENGTH) {
            $errors[] = 'Each technology must be ' . PROJECT_TECHNOLOGY_MAX_LENGTH . ' characters or fewer.';

            return null;
        }

        $comparisonKey = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
        if (isset($seen[$comparisonKey])) {
            continue;
        }

        $seen[$comparisonKey] = true;
        $technologies[] = $label;
        if (count($technologies) > PROJECT_TECHNOLOGIES_MAXIMUM) {
            $errors[] = 'Add no more than ' . PROJECT_TECHNOLOGIES_MAXIMUM . ' technologies.';

            return null;
        }
    }

    return $technologies;
}

/** @param list<string> $technologies */
function projectTechnologiesToStorage(array $technologies): ?string
{
    if ($technologies === []) {
        return null;
    }

    return json_encode(array_values($technologies), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

/** @return list<string> */
function projectTechnologiesFromStorage(mixed $stored): array
{
    if ($stored === null || $stored === '') {
        return [];
    }
    if (!is_string($stored)) {
        return [];
    }

    try {
        $decoded = json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
    if (!is_array($decoded) || !array_is_list($decoded) || count($decoded) > PROJECT_TECHNOLOGIES_MAXIMUM) {
        return [];
    }

    $technologies = [];
    $seen = [];
    foreach ($decoded as $label) {
        if (!is_string($label) || trim($label) !== $label || $label === '') {
            return [];
        }

        $length = utf8CharacterLength($label);
        if ($length === null || $length > PROJECT_TECHNOLOGY_MAX_LENGTH) {
            return [];
        }

        $comparisonKey = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
        if (isset($seen[$comparisonKey])) {
            return [];
        }

        $seen[$comparisonKey] = true;
        $technologies[] = $label;
    }

    return $technologies;
}

/** @param list<string> $technologies */
function projectTechnologiesFormValue(array $technologies): string
{
    return implode("\n", $technologies);
}
