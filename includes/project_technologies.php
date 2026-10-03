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

/**
 * This preserves the established public reader behavior while allowing private
 * Evidence Hub aggregation to distinguish an empty collection from corruption.
 *
 * @return list<string>
 */
function projectTechnologiesFromStorage(mixed $stored): array
{
    return parseProjectTechnologiesStorage($stored)['labels'];
}

/**
 * @return array{storage_state: 'valid'|'invalid', reason_codes: list<string>, labels: list<string>}
 */
function parseProjectTechnologiesStorage(mixed $stored): array
{
    if ($stored === null || $stored === '') {
        return projectTechnologyStorageValidResult([]);
    }
    if (!is_string($stored)) {
        return projectTechnologyStorageInvalidResult();
    }

    try {
        $decoded = json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return projectTechnologyStorageInvalidResult();
    }
    if (!is_array($decoded) || !array_is_list($decoded) || count($decoded) > PROJECT_TECHNOLOGIES_MAXIMUM) {
        return projectTechnologyStorageInvalidResult();
    }

    $technologies = [];
    $seen = [];
    foreach ($decoded as $label) {
        if (!is_string($label) || trim($label) !== $label || $label === '') {
            return projectTechnologyStorageInvalidResult();
        }

        $length = utf8CharacterLength($label);
        if ($length === null || $length > PROJECT_TECHNOLOGY_MAX_LENGTH) {
            return projectTechnologyStorageInvalidResult();
        }

        $comparisonKey = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
        if (isset($seen[$comparisonKey])) {
            return projectTechnologyStorageInvalidResult();
        }

        $seen[$comparisonKey] = true;
        $technologies[] = $label;
    }

    return projectTechnologyStorageValidResult($technologies);
}

/** @param list<string> $labels
 * @return array{storage_state: 'valid', reason_codes: list<string>, labels: list<string>}
 */
function projectTechnologyStorageValidResult(array $labels): array
{
    return ['storage_state' => 'valid', 'reason_codes' => [], 'labels' => $labels];
}

/** @return array{storage_state: 'invalid', reason_codes: list<string>, labels: list<string>} */
function projectTechnologyStorageInvalidResult(): array
{
    return ['storage_state' => 'invalid', 'reason_codes' => ['TECHNOLOGY_STORAGE_INVALID'], 'labels' => []];
}

/** @param list<string> $technologies */
function projectTechnologiesFormValue(array $technologies): string
{
    return implode("\n", $technologies);
}
