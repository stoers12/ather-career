<?php

declare(strict_types=1);

require_once __DIR__ . '/experience.php';

function setExperienceSuccessFlash(string $message): void
{
    $_SESSION['experience_success_flash'] = $message;
}

function takeExperienceSuccessFlash(): string
{
    $message = isset($_SESSION['experience_success_flash']) && is_string($_SESSION['experience_success_flash'])
        ? $_SESSION['experience_success_flash']
        : '';
    unset($_SESSION['experience_success_flash']);

    return $message;
}

/** @return array{errors: list<string>, field_errors: array<string, string>, form_mode: string, editing_experience: array<string, mixed>, redirect: string|null, status: int} */
function experienceActionResult(array $errors = [], string $formMode = 'add', ?array $editingExperience = null, ?string $redirect = null, array $fieldErrors = [], int $status = 200): array
{
    return [
        'errors' => $errors,
        'field_errors' => $fieldErrors,
        'form_mode' => $formMode,
        'editing_experience' => $editingExperience ?? experienceFormDefaults(),
        'redirect' => $redirect,
        'status' => $status,
    ];
}
