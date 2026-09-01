<?php

declare(strict_types=1);

require_once __DIR__ . '/portfolio_scoped_data.php';
require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/profile_actions.php';
require_once __DIR__ . '/project_actions.php';

function ownerActionId(mixed $value): ?int
{
    return is_string($value) ? projectActionId($value) : null;
}

function ownerProfileActionResult(array $errors, array $profile, ?string $redirect = null): array
{
    return ['errors' => $errors, 'profile' => $profile, 'redirect' => $redirect];
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
    $errors = [];

    try {
        $target = ownerProfileActionTarget($database, $context, $post, $current);
        if (array_key_exists('profile_id', $post) && $target === null) {
            return ownerProfileActionResult(['Profile not found.'], $profile);
        }

        if ($action === 'upload_profile_image') {
            if ($target === null) {
                return ownerProfileActionResult(['Save your personal information before uploading a photo.'], $profile);
            }

            $newImagePath = storeValidatedProfileImage($files['profile_image'] ?? [], $errors);
            if ($errors !== []) {
                return ownerProfileActionResult($errors, $profile);
            }

            if (!updateAuthorizedPersonalInfo($database, $context, (int) $target['id'], ['profile_image_path' => $newImagePath])) {
                cleanProfileImage($newImagePath, 'owner_profile_update_compensation');
                return ownerProfileActionResult(['The profile photo could not be updated.'], $profile);
            }

            cleanProfileImage($target['profile_image_path'] ?? null, 'owner_profile_update_old_image');
            return ownerProfileActionResult([], $profile, 'owner_profile.php?photo_updated=1');
        }

        if ($action === 'save_profile') {
            foreach ($fields as $field) {
                $profile[$field] = isset($post[$field]) && is_string($post[$field]) ? trim($post[$field]) : '';
            }
            if ($profile['full_name'] === '') {
                $errors[] = 'Full name is required.';
            }
            foreach (PERSONAL_INFO_FIELD_MAX_LENGTHS as $field => $maximum) {
                $error = utf8FieldLengthError($profile[$field], $maximum, ucwords(str_replace('_', ' ', $field)));
                if ($error !== null) {
                    $errors[] = $error;
                }
            }
            if ($profile['email'] !== '' && filter_var($profile['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Please enter a valid email address.';
            }
            foreach (['linkedin_url', 'github_url', 'instagram_url', 'facebook_url', 'website_url'] as $urlField) {
                if ($profile[$urlField] !== '' && !isSafeHttpUrl($profile[$urlField])) {
                    $errors[] = 'Please enter valid URLs.';
                    break;
                }
            }
            if ($errors !== []) {
                return ownerProfileActionResult($errors, $profile);
            }

            $values = [];
            foreach ($fields as $field) {
                $values[$field] = $profile[$field];
            }
            $values['profile_image_path'] = $target['profile_image_path'] ?? null;

            if ($target === null) {
                createAuthorizedPersonalInfo($database, $context, $values);
            } elseif (!updateAuthorizedPersonalInfo($database, $context, (int) $target['id'], $values)) {
                return ownerProfileActionResult(['Your personal information could not be saved. Please reload and try again.'], $profile);
            }

            return ownerProfileActionResult([], $profile, 'owner_profile.php?saved=1');
        }

        if ($action === 'remove_profile_image') {
            if ($target === null || empty($target['profile_image_path'])) {
                return ownerProfileActionResult(['Profile photo not found.'], $profile);
            }

            $imagePath = (string) $target['profile_image_path'];
            if (!updateAuthorizedPersonalInfo($database, $context, (int) $target['id'], ['profile_image_path' => null])) {
                return ownerProfileActionResult(['Profile photo not found.'], $profile);
            }

            cleanProfileImage($imagePath, 'owner_profile_remove_old_image');
            return ownerProfileActionResult([], $profile, 'owner_profile.php?photo_removed=1');
        }

        if ($action === 'add_skill') {
            $skill = isset($post['skill_name']) && is_string($post['skill_name']) ? trim($post['skill_name']) : '';
            $skillLength = utf8CharacterLength($skill);
            if ($skill === '' || $skillLength === null || $skillLength > SKILL_NAME_MAX_LENGTH) {
                return ownerProfileActionResult(['Skill must be between 1 and 100 characters.'], $profile);
            }

            createAuthorizedSkill($database, $context, $skill);
            return ownerProfileActionResult([], $profile, 'owner_profile.php?skill_added=1');
        }

        if ($action === 'delete_skill') {
            $skillId = ownerActionId($post['skill_id'] ?? null);
            if ($skillId === null || !deleteAuthorizedSkill($database, $context, $skillId)) {
                return ownerProfileActionResult(['Skill not found.'], $profile);
            }

            return ownerProfileActionResult([], $profile, 'owner_profile.php?skill_deleted=1');
        }

        if ($action === 'update_skill') {
            $skillId = ownerActionId($post['skill_id'] ?? null);
            $skill = isset($post['skill_name']) && is_string($post['skill_name']) ? trim($post['skill_name']) : '';
            $skillLength = utf8CharacterLength($skill);
            if ($skillId === null || $skill === '' || $skillLength === null || $skillLength > SKILL_NAME_MAX_LENGTH) {
                return ownerProfileActionResult(['Skill must be between 1 and 100 characters.'], $profile);
            }
            if (!updateAuthorizedSkill($database, $context, $skillId, $skill)) {
                return ownerProfileActionResult(['Skill not found.'], $profile);
            }

            return ownerProfileActionResult([], $profile, 'owner_profile.php?skill_updated=1');
        }

        return ownerProfileActionResult([], $profile);
    } catch (PDOException $exception) {
        if ($action === 'add_skill' && isMySqlDuplicateKeyViolation($exception)) {
            return ownerProfileActionResult(['That skill already exists.'], $profile);
        }
        if ($action === 'save_profile' && $target === null && isMySqlDuplicateKeyViolation($exception)) {
            return ownerProfileActionResult(['Profile was initialized by another request. Please reload and try again.'], $profile);
        }

        reportApplicationError($exception, 'owner_profile.php', 'owner_profile_' . ($action === '' ? 'unknown' : $action));
        if (isset($newImagePath)) {
            cleanProfileImage($newImagePath, 'owner_profile_database_compensation');
        }
        return ownerProfileActionResult(['The requested change could not be saved.'], $profile);
    }
}

function handleAuthorizedProjectAction(PDO $database, AuthorizedPortfolioContext $context, array $post, array $files): array
{
    $action = isset($post['action']) && is_string($post['action']) ? $post['action'] : '';

    try {
        if ($action === 'delete') {
            $projectId = ownerActionId($post['id'] ?? null);
            $project = $projectId === null ? null : findAuthorizedProject($database, $context, $projectId);
            if ($project === null || !deleteAuthorizedProject($database, $context, $projectId)) {
                return projectActionResult(['Project not found.']);
            }

            cleanProjectImage($project['image_path'] ?? null, 'owner_project_delete');
            setProjectSuccessFlash('Project deleted successfully.');
            return projectActionResult([], 'add', null, 'owner_projects.php');
        }

        if ($action !== 'add' && $action !== 'update') {
            return projectActionResult();
        }

        $title = isset($post['title']) && is_string($post['title']) ? trim($post['title']) : '';
        $category = isset($post['category']) && is_string($post['category']) ? trim($post['category']) : '';
        $description = isset($post['description']) && is_string($post['description']) ? trim($post['description']) : '';
        $githubUrl = isset($post['github_url']) && is_string($post['github_url']) ? trim($post['github_url']) : '';
        $formMode = $action === 'update' ? 'edit' : 'add';
        $editingProject = ['id' => $post['id'] ?? '', 'title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubUrl, 'image_path' => null];
        $errors = [];

        foreach (['title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubUrl] as $field => $value) {
            if ($value === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        foreach ([
            [$title, PROJECT_TITLE_MAX_LENGTH, 'Title'],
            [$category, PROJECT_CATEGORY_MAX_LENGTH, 'Category'],
            [$githubUrl, PROJECT_GITHUB_URL_MAX_LENGTH, 'GitHub URL'],
        ] as [$value, $maximum, $label]) {
            $error = utf8FieldLengthError($value, $maximum, $label);
            if ($error !== null) {
                $errors[] = $error;
            }
        }
        if ($githubUrl !== '' && !isSafeHttpUrl($githubUrl)) {
            $errors[] = 'Please enter a valid HTTP or HTTPS URL.';
        }

        $projectId = $action === 'update' ? ownerActionId($post['id'] ?? null) : null;
        $existing = null;
        if ($action === 'update' && $projectId === null) {
            $errors[] = 'Please provide a valid project ID.';
        } elseif ($action === 'update') {
            $existing = findAuthorizedProject($database, $context, $projectId);
            if ($existing === null) {
                $errors[] = 'Project not found.';
            } else {
                $editingProject['image_path'] = $existing['image_path'];
            }
        }

        $newImagePath = null;
        $hasUpload = isset($files['project_image']) && (($files['project_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
        if ($errors === [] && $hasUpload) {
            $newImagePath = storeValidatedProjectImage($files['project_image'], $errors);
        }
        if ($errors !== []) {
            cleanProjectImage($newImagePath, 'owner_project_validation_compensation');
            return projectActionResult($errors, $formMode, $editingProject);
        }

        if ($action === 'add') {
            createAuthorizedProject($database, $context, $title, $category, $description, $githubUrl, $newImagePath);
            setProjectSuccessFlash('Project added successfully.');
            return projectActionResult([], 'add', null, 'owner_projects.php');
        }

        $removeImage = isset($post['remove_image']) && $post['remove_image'] === '1';
        $oldImagePath = $existing['image_path'] ?? null;
        $imagePath = $newImagePath ?? ($removeImage ? null : $oldImagePath);
        if (!updateAuthorizedProject($database, $context, $projectId, $title, $category, $description, $githubUrl, $imagePath)) {
            cleanProjectImage($newImagePath, 'owner_project_update_compensation');
            return projectActionResult(['Project not found.'], $formMode, $editingProject);
        }

        if ($newImagePath !== null || $removeImage) {
            cleanProjectImage($oldImagePath, 'owner_project_update_old_image');
        }
        setProjectSuccessFlash('Project updated successfully.');
        return projectActionResult([], 'add', null, 'owner_projects.php');
    } catch (PDOException $exception) {
        reportApplicationError($exception, 'owner_projects.php', 'owner_project_' . ($action === '' ? 'unknown' : $action));
        if (isset($newImagePath)) {
            cleanProjectImage($newImagePath, 'owner_project_database_compensation');
        }
        return projectActionResult(['The project could not be saved.'], $formMode ?? 'add', $editingProject ?? null);
    }
}
