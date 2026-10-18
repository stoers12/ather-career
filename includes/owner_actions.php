<?php

declare(strict_types=1);

require_once __DIR__ . '/portfolio_scoped_data.php';
require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/profile_actions.php';
require_once __DIR__ . '/project_actions.php';
require_once __DIR__ . '/experience_actions.php';
require_once __DIR__ . '/transaction.php';
require_once __DIR__ . '/profile_form_actions.php';

function ownerActionId(mixed $value): ?int
{
    return is_string($value) ? projectActionId($value) : null;
}

/** @return array{errors: list<string>, field_errors: array<string, string>, profile: array<string, mixed>, redirect: string|null, status: int} */
function ownerProfileActionResult(array $errors, array $profile, ?string $redirect = null, array $fieldErrors = [], int $status = 200): array
{
    return ['errors' => $errors, 'field_errors' => $fieldErrors, 'profile' => $profile, 'redirect' => $redirect, 'status' => $status];
}

/** @return array<string, mixed>|null */
function ownerProfileActionTarget(PDO $database, AuthorizedPortfolioContext $context, array $post, ?array $current): ?array
{
    if (!array_key_exists('profile_id', $post)) {
        return $current;
    }

    $profileId = ownerActionId($post['profile_id']);
    if ($profileId === null) {
        return null;
    }

    return findAuthorizedPersonalInfo($database, $context, $profileId);
}

function handleAuthorizedProfileAction(
    PDO $database,
    AuthorizedPortfolioContext $context,
    array $post,
    array $files,
    ?array $current,
    array $fields,
    array $profile,
): array {
    $action = isset($post['action']) && is_string($post['action']) ? $post['action'] : '';
    if (in_array($action, ['save_profile', 'upload_profile_image', 'remove_profile_image'], true)) {
        return handleVersionedProfileAction($database, $context, $post, $files, $current, $fields, $profile);
    }
    $errors = [];
    $fieldErrors = [];
    $target = null;

    try {
        $target = ownerProfileActionTarget($database, $context, $post, $current);
        if (array_key_exists('profile_id', $post) && $target === null) {
            $status = ownerActionId($post['profile_id']) === null ? 422 : 404;
            $fieldErrors['profile_id'] = $status === 422 ? 'Please provide a valid profile ID.' : 'Profile not found.';
            return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, $status);
        }

        if ($action === 'add_skill') {
            $skillInput = submittedStringField($post, 'skill_name', SKILL_NAME_MAX_LENGTH, 'Skill name', true);
            $skill = $skillInput['value'];
            if ($skillInput['error'] !== null) {
                $fieldErrors['skill_name'] = $skillInput['error'];
                return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, 422);
            }

            runDatabaseTransaction($database, static fn (): int => createAuthorizedSkill($database, $context, $skill));
            return ownerProfileActionResult([], $profile, 'owner_profile.php?skill_added=1');
        }

        if ($action === 'delete_skill') {
            $skillId = ownerActionId($post['skill_id'] ?? null);
            if ($skillId === null || !runDatabaseTransaction($database, static fn (): bool => deleteAuthorizedSkill($database, $context, $skillId))) {
                $status = $skillId === null ? 422 : 404;
                $fieldErrors['skill_id'] = $status === 422 ? 'Please provide a valid skill ID.' : 'Skill not found.';
                return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, $status);
            }

            return ownerProfileActionResult([], $profile, 'owner_profile.php?skill_deleted=1');
        }

        if ($action === 'update_skill') {
            $skillId = ownerActionId($post['skill_id'] ?? null);
            $skillInput = submittedStringField($post, 'skill_name', SKILL_NAME_MAX_LENGTH, 'Skill name', true);
            $skill = $skillInput['value'];
            if ($skillId === null) {
                $fieldErrors['skill_id'] = 'Please provide a valid skill ID.';
            }
            if ($skillInput['error'] !== null) {
                $fieldErrors['skill_name'] = $skillInput['error'];
            }
            if ($fieldErrors !== []) {
                return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, 422);
            }
            if (!runDatabaseTransaction($database, static fn (): bool => updateAuthorizedSkill($database, $context, $skillId, $skill))) {
                $fieldErrors['skill_id'] = 'Skill not found.';
                return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, 404);
            }

            return ownerProfileActionResult([], $profile, 'owner_profile.php?skill_updated=1');
        }

        $fieldErrors['action'] = 'Invalid profile action.';
        return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, 400);
    } catch (Throwable $exception) {
        if ($action === 'add_skill' && $exception instanceof PDOException && isMySqlDuplicateKeyViolation($exception)) {
            $fieldErrors['skill_name'] = 'That skill already exists.';
            return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, 422);
        }
        reportApplicationError($exception, 'owner_profile.php', 'owner_profile_' . ($action === '' ? 'unknown' : $action));
        $fieldErrors['profile'] = 'The requested change could not be saved.';
        return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, 503);
    }
}

function handleAuthorizedProjectAction(PDO $database, AuthorizedPortfolioContext $context, array $post, array $files): array
{
    $action = isset($post['action']) && is_string($post['action']) ? $post['action'] : '';
    $newImagePath = null;

    try {
        if ($action === 'delete') {
            $projectId = ownerActionId($post['id'] ?? null);
            $project = $projectId === null ? null : findAuthorizedProject($database, $context, $projectId);
            if ($project === null || !runDatabaseTransaction($database, static fn (): bool => deleteAuthorizedProject($database, $context, $projectId))) {
                $status = $projectId === null ? 422 : 404;
                $fieldErrors = ['id' => $status === 422 ? 'Please provide a valid project ID.' : 'Project not found.'];
                return projectActionResult(validationErrorList($fieldErrors), 'add', null, null, $fieldErrors, $status);
            }

            $oldImagePath = $project['image_path'] ?? null;
            if (authorizedProjectImageIsUnreferenced($database, $context, is_string($oldImagePath) ? $oldImagePath : null)) {
                cleanProjectImage($oldImagePath, 'owner_project_delete', $context->portfolioId);
            }
            setProjectSuccessFlash('Project deleted successfully.');
            return projectActionResult([], 'add', null, 'owner_projects.php');
        }

        if ($action !== 'add' && $action !== 'update') {
            $fieldErrors = ['action' => 'Invalid project action.'];
            return projectActionResult(validationErrorList($fieldErrors), 'add', null, null, $fieldErrors, 400);
        }

        $fieldErrors = [];
        $titleInput = submittedStringField($post, 'title', PROJECT_TITLE_MAX_LENGTH, 'Title', true);
        $categoryInput = submittedStringField($post, 'category', PROJECT_CATEGORY_MAX_LENGTH, 'Category', true);
        $githubInput = submittedStringField($post, 'github_url', PROJECT_GITHUB_URL_MAX_LENGTH, 'GitHub URL');
        $descriptionInput = array_key_exists('description', $post) && !is_string($post['description'])
            ? ['value' => '', 'error' => 'Description must be submitted as text.']
            : ['value' => is_string($post['description'] ?? null) ? trim($post['description']) : '', 'error' => null];
        $title = $titleInput['value'];
        $category = $categoryInput['value'];
        $description = $descriptionInput['value'];
        $githubUrl = $githubInput['value'] === '' ? null : $githubInput['value'];
        foreach (['title' => $titleInput, 'category' => $categoryInput, 'description' => $descriptionInput, 'github_url' => $githubInput] as $field => $input) {
            if ($input['error'] !== null) {
                $fieldErrors[$field] = $input['error'];
            }
        }
        if ($description === '') {
            $fieldErrors['description'] = 'Description is required.';
        }
        $technologiesInput = !array_key_exists('technologies', $post) || is_string($post['technologies'])
            ? ($post['technologies'] ?? '')
            : null;
        $formMode = $action === 'update' ? 'edit' : 'add';
        $editingProject = ['id' => is_string($post['id'] ?? null) ? $post['id'] : '', 'title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubInput['value'], 'technologies' => $technologiesInput, 'image_path' => null];
        $technologyErrors = [];
        $technologies = normalizeProjectTechnologies($technologiesInput, $technologyErrors);
        if ($technologyErrors !== []) {
            $fieldErrors['technologies'] = $technologyErrors[0];
        }
        if (!isset($fieldErrors['github_url']) && $githubUrl !== null && !isSafeHttpUrl($githubUrl)) {
            $fieldErrors['github_url'] = 'Please enter a valid HTTP or HTTPS URL.';
        }

        $projectId = $action === 'update' ? ownerActionId($post['id'] ?? null) : null;
        $existing = null;
        if ($action === 'update' && $projectId === null) {
            $fieldErrors['id'] = 'Please provide a valid project ID.';
            $editingProject['id'] = '';
            $formMode = 'add';
        } elseif ($action === 'update') {
            $existing = findAuthorizedProject($database, $context, $projectId);
            if ($existing === null) {
                $fieldErrors['id'] = 'Project not found.';
                $editingProject['id'] = '';
                $formMode = 'add';
            } else {
                $editingProject['image_path'] = $existing['image_path'];
            }
        }

        $hasUpload = isset($files['project_image']) && (($files['project_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
        if ($fieldErrors === [] && $hasUpload) {
            $imageErrors = [];
            $newImagePath = storeValidatedProjectImage($files['project_image'], $imageErrors, $context->portfolioId);
            if ($imageErrors !== []) {
                $fieldErrors['project_image'] = $imageErrors[0];
            }
        }
        if ($fieldErrors !== []) {
            cleanProjectImage($newImagePath, 'owner_project_validation_compensation', $context->portfolioId);
            $status = isset($fieldErrors['id']) && $fieldErrors['id'] === 'Project not found.' ? 404 : 422;
            return projectActionResult(validationErrorList($fieldErrors), $formMode, $editingProject, null, $fieldErrors, $status);
        }

        if ($action === 'add') {
            runDatabaseTransaction($database, static fn (): int => createAuthorizedProject($database, $context, $title, $category, $description, $githubUrl, $newImagePath, $technologies ?? []));
            setProjectSuccessFlash('Project added successfully.');
            return projectActionResult([], 'add', null, 'owner_projects.php');
        }

        $removeImage = isset($post['remove_image']) && $post['remove_image'] === '1';
        $oldImagePath = $existing['image_path'] ?? null;
        $imagePath = $newImagePath ?? ($removeImage ? null : $oldImagePath);
        if (!runDatabaseTransaction($database, static fn (): bool => updateAuthorizedProject($database, $context, $projectId, $title, $category, $description, $githubUrl, $imagePath, $technologies ?? []))) {
            cleanProjectImage($newImagePath, 'owner_project_update_compensation', $context->portfolioId);
            $fieldErrors = ['id' => 'Project not found.'];
            return projectActionResult(validationErrorList($fieldErrors), $formMode, $editingProject, null, $fieldErrors, 404);
        }

        if (($newImagePath !== null || $removeImage)
            && authorizedProjectImageIsUnreferenced($database, $context, is_string($oldImagePath) ? $oldImagePath : null)) {
            cleanProjectImage($oldImagePath, 'owner_project_update_old_image', $context->portfolioId);
        }
        setProjectSuccessFlash('Project updated successfully.');
        return projectActionResult([], 'add', null, 'owner_projects.php');
    } catch (Throwable $exception) {
        reportApplicationError($exception, 'owner_projects.php', 'owner_project_' . ($action === '' ? 'unknown' : $action));
        if ($newImagePath !== null) {
            cleanProjectImage($newImagePath, 'owner_project_database_compensation', $context->portfolioId);
        }
        $fieldErrors = ['project' => 'The project could not be saved.'];
        return projectActionResult(validationErrorList($fieldErrors), $formMode ?? 'add', $editingProject ?? null, null, $fieldErrors, 503);
    }
}

/** @return array{errors: list<string>, field_errors: array<string, string>, form_mode: string, editing_experience: array<string, mixed>, redirect: string|null, status: int} */
function handleAuthorizedExperienceAction(PDO $database, AuthorizedPortfolioContext $context, array $post, ?string $referenceMonth = null): array
{
    $action = isset($post['action']) && is_string($post['action']) ? $post['action'] : '';

    try {
        if ($action === 'delete') {
            $experienceId = experienceActionId($post['id'] ?? null);
            $experience = $experienceId === null ? null : findAuthorizedExperience($database, $context, $experienceId);
            if ($experience === null || !runDatabaseTransaction($database, static fn (): bool => deleteAuthorizedExperience($database, $context, $experienceId))) {
                $status = $experienceId === null ? 422 : 404;
                $fieldErrors = ['id' => $status === 422 ? 'Please provide a valid experience record ID.' : 'Experience record not found.'];
                return experienceActionResult(validationErrorList($fieldErrors), 'add', null, null, $fieldErrors, $status);
            }

            setExperienceSuccessFlash('Experience record deleted successfully.');
            return experienceActionResult([], 'add', null, 'owner_experiences.php');
        }

        if ($action !== 'add' && $action !== 'update') {
            $fieldErrors = ['action' => 'Invalid experience action.'];
            return experienceActionResult(validationErrorList($fieldErrors), 'add', null, null, $fieldErrors, 400);
        }

        $formMode = $action === 'update' ? 'edit' : 'add';
        $editingExperience = experienceFormValues($post);
        $fieldErrors = experienceSubmittedFieldErrors($post);
        foreach (experienceValidationFieldErrors($editingExperience, $referenceMonth) as $field => $error) {
            $fieldErrors[$field] ??= $error;
        }
        $experienceId = $action === 'update' ? experienceActionId($post['id'] ?? null) : null;
        if ($action === 'update' && $experienceId === null) {
            $fieldErrors['id'] = 'Please provide a valid experience record ID.';
            $editingExperience['id'] = '';
            $formMode = 'add';
        } elseif ($action === 'update' && findAuthorizedExperience($database, $context, $experienceId) === null) {
            $fieldErrors['id'] = 'Experience record not found.';
            $editingExperience['id'] = '';
            $formMode = 'add';
        }

        if ($fieldErrors !== []) {
            $status = isset($fieldErrors['id']) && $fieldErrors['id'] === 'Experience record not found.' ? 404 : 422;
            return experienceActionResult(validationErrorList($fieldErrors), $formMode, $editingExperience, null, $fieldErrors, $status);
        }

        if ($action === 'add') {
            runDatabaseTransaction($database, static fn (): int => createAuthorizedExperience($database, $context, $editingExperience, $referenceMonth));
            setExperienceSuccessFlash('Experience record added successfully.');
            return experienceActionResult([], 'add', null, 'owner_experiences.php');
        }

        if (!runDatabaseTransaction($database, static fn (): bool => updateAuthorizedExperience($database, $context, $experienceId, $editingExperience, $referenceMonth))) {
            $fieldErrors = ['id' => 'Experience record not found.'];
            return experienceActionResult(validationErrorList($fieldErrors), $formMode, $editingExperience, null, $fieldErrors, 404);
        }

        setExperienceSuccessFlash('Experience record updated successfully.');
        return experienceActionResult([], 'add', null, 'owner_experiences.php');
    } catch (Throwable $exception) {
        reportApplicationError($exception, 'owner_experiences.php', 'owner_experience_' . ($action === '' ? 'unknown' : $action));

        $fieldErrors = ['experience' => 'The experience record could not be saved.'];
        return experienceActionResult(validationErrorList($fieldErrors), $formMode ?? 'add', $editingExperience ?? null, null, $fieldErrors, 503);
    }
}
