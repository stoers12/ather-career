<?php

declare(strict_types=1);

require_once __DIR__ . '/validation.php';

const EXPERIENCE_ID_MAXIMUM = '4294967295';
const EXPERIENCE_TYPE_VALUES = ['employment', 'training', 'internship', 'volunteer', 'leadership'];
const EXPERIENCE_ROLE_TITLE_MAX_LENGTH = 120;
const EXPERIENCE_ORGANIZATION_MAX_LENGTH = 160;
const EXPERIENCE_LOCATION_MAX_LENGTH = 160;
const EXPERIENCE_DESCRIPTION_MAX_LENGTH = 1200;

/** @return array<string, string> */
function experienceTypeOptions(): array
{
    return [
        'employment' => 'Employment',
        'training' => 'Training',
        'internship' => 'Internship',
        'volunteer' => 'Volunteer',
        'leadership' => 'Leadership',
    ];
}

/** @return array<string, mixed> */
function experienceFormDefaults(): array
{
    return [
        'id' => '',
        'experience_type' => '',
        'role_title' => '',
        'organization' => '',
        'location' => '',
        'start_month' => '',
        'end_month' => '',
        'is_current' => false,
        'description' => '',
    ];
}

function experienceActionId(mixed $value): ?int
{
    if (!is_string($value) || !ctype_digit($value) || $value === '' || strlen($value) > strlen(EXPERIENCE_ID_MAXIMUM)) {
        return null;
    }

    if (trim($value, '0') === '') {
        return null;
    }

    if (strlen($value) === strlen(EXPERIENCE_ID_MAXIMUM) && strcmp($value, EXPERIENCE_ID_MAXIMUM) > 0) {
        return null;
    }

    return (int) $value;
}

function experienceIsCurrent(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1';
}

function normalizeExperienceMonth(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $matches) !== 1) {
        return null;
    }

    return checkdate((int) $matches[2], 1, (int) $matches[1]) ? $value : null;
}

function experienceValidationReferenceMonth(?string $referenceMonth = null): string
{
    if ($referenceMonth === null) {
        return gmdate('Y-m');
    }

    $normalizedMonth = normalizeExperienceMonth($referenceMonth);
    if ($normalizedMonth === null) {
        throw new InvalidArgumentException('Experience validation reference month is invalid.');
    }

    return $normalizedMonth;
}

/** @return array<string, mixed> */
function experienceFormValues(array $post): array
{
    $value = static function (string $field) use ($post): string {
        return isset($post[$field]) && is_string($post[$field]) ? trim($post[$field]) : '';
    };

    return [
        'id' => $value('id'),
        'experience_type' => $value('experience_type'),
        'role_title' => $value('role_title'),
        'organization' => $value('organization'),
        'location' => $value('location'),
        // Dates are canonical protocol values, not free-form prose. Preserve
        // them exactly so the validator can reject ambiguous whitespace rather
        // than silently rewriting a direct POST.
        'start_month' => isset($post['start_month']) && is_string($post['start_month']) ? $post['start_month'] : '',
        'end_month' => isset($post['end_month']) && is_string($post['end_month']) ? $post['end_month'] : '',
        'is_current' => ($post['is_current'] ?? null) === '1',
        'description' => $value('description'),
    ];
}

/** @param array<string, mixed> $values */
function validateExperienceValues(array $values, ?string $referenceMonth = null): array
{
    $errors = [];
    $type = isset($values['experience_type']) && is_string($values['experience_type']) ? $values['experience_type'] : '';
    $roleTitle = isset($values['role_title']) && is_string($values['role_title']) ? $values['role_title'] : '';
    $organization = isset($values['organization']) && is_string($values['organization']) ? $values['organization'] : '';
    $location = isset($values['location']) && is_string($values['location']) ? $values['location'] : '';
    $startMonth = isset($values['start_month']) && is_string($values['start_month']) ? $values['start_month'] : '';
    $endMonth = isset($values['end_month']) && is_string($values['end_month']) ? $values['end_month'] : '';
    $isCurrent = experienceIsCurrent($values['is_current'] ?? false);
    $description = isset($values['description']) && is_string($values['description']) ? $values['description'] : '';
    $currentMonth = experienceValidationReferenceMonth($referenceMonth);

    if (!in_array($type, EXPERIENCE_TYPE_VALUES, true)) {
        $errors[] = 'Please choose a valid experience type.';
    }
    if ($roleTitle === '') {
        $errors[] = 'Role or title is required.';
    }
    if ($organization === '') {
        $errors[] = 'Organization is required.';
    }

    foreach ([
        [$roleTitle, EXPERIENCE_ROLE_TITLE_MAX_LENGTH, 'Role or title'],
        [$organization, EXPERIENCE_ORGANIZATION_MAX_LENGTH, 'Organization'],
        [$location, EXPERIENCE_LOCATION_MAX_LENGTH, 'Location'],
        [$description, EXPERIENCE_DESCRIPTION_MAX_LENGTH, 'Description'],
    ] as [$value, $maximum, $label]) {
        $error = utf8FieldLengthError($value, $maximum, $label);
        if ($error !== null) {
            $errors[] = $error;
        }
    }

    $normalizedStartMonth = normalizeExperienceMonth($startMonth);
    if ($startMonth === '') {
        $errors[] = 'Start month is required.';
    } elseif ($normalizedStartMonth === null) {
        $errors[] = 'Start month must be a valid month.';
    } elseif ($normalizedStartMonth > $currentMonth) {
        $errors[] = 'Start month cannot be later than the current month.';
    }

    $normalizedEndMonth = null;
    if ($isCurrent) {
        if ($endMonth !== '') {
            $errors[] = 'End month must be empty for a current role.';
        }
    } else {
        $normalizedEndMonth = normalizeExperienceMonth($endMonth);
        if ($endMonth === '') {
            $errors[] = 'End month is required unless this is your current role.';
        } elseif ($normalizedEndMonth === null) {
            $errors[] = 'End month must be a valid month.';
        } elseif ($normalizedEndMonth > $currentMonth) {
            $errors[] = 'End month cannot be later than the current month.';
        }
    }
    if ($normalizedStartMonth !== null && $normalizedEndMonth !== null && $normalizedEndMonth < $normalizedStartMonth) {
        $errors[] = 'End month cannot be earlier than start month.';
    }

    return $errors;
}

/** @param array<string, mixed> $values
 *  @return array{experience_type: string, role_title: string, organization: string, location: string|null, start_month: string, end_month: string|null, is_current: int, description: string|null}
 */
function authorizedExperienceValues(array $values, ?string $referenceMonth = null): array
{
    $normalized = [
        'experience_type' => isset($values['experience_type']) && is_string($values['experience_type']) ? trim($values['experience_type']) : '',
        'role_title' => isset($values['role_title']) && is_string($values['role_title']) ? trim($values['role_title']) : '',
        'organization' => isset($values['organization']) && is_string($values['organization']) ? trim($values['organization']) : '',
        'location' => isset($values['location']) && is_string($values['location']) ? trim($values['location']) : '',
        'start_month' => isset($values['start_month']) && is_string($values['start_month']) ? $values['start_month'] : '',
        'end_month' => isset($values['end_month']) && is_string($values['end_month']) ? $values['end_month'] : '',
        'is_current' => experienceIsCurrent($values['is_current'] ?? false),
        'description' => isset($values['description']) && is_string($values['description']) ? trim($values['description']) : '',
    ];
    $errors = validateExperienceValues($normalized, $referenceMonth);
    if ($errors !== []) {
        throw new InvalidArgumentException('Experience values are invalid.');
    }

    if ($normalized['is_current']) {
        $normalized['end_month'] = '';
    }

    $startMonth = normalizeExperienceMonth($normalized['start_month']);
    $endMonth = $normalized['is_current'] ? null : normalizeExperienceMonth($normalized['end_month']);
    if ($startMonth === null || (!$normalized['is_current'] && $endMonth === null)) {
        throw new LogicException('Validated Experience months could not be normalized.');
    }

    return [
        'experience_type' => $normalized['experience_type'],
        'role_title' => $normalized['role_title'],
        'organization' => $normalized['organization'],
        'location' => $normalized['location'] === '' ? null : $normalized['location'],
        'start_month' => $startMonth,
        'end_month' => $endMonth,
        'is_current' => $normalized['is_current'] ? 1 : 0,
        'description' => $normalized['description'] === '' ? null : $normalized['description'],
    ];
}
