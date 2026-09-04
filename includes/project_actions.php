<?php

require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/media_image_policy.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/project_technologies.php';
require_once __DIR__ . '/project_presentation.php';

const PROJECT_ID_MAXIMUM = '4294967295';
const PROJECT_IMAGE_MAX_BYTES = 5 * 1024 * 1024;

function projectImageMaximumMegabytes(): int
{
    return (int) (PROJECT_IMAGE_MAX_BYTES / (1024 * 1024));
}

function projectImageSizeIsAllowed(mixed $size): bool
{
    return is_int($size) && $size >= 0 && $size <= PROJECT_IMAGE_MAX_BYTES;
}

/** @return array{extension: string, mime: string}|string */
function validateProjectImageUpload(array $file): array|string
{
    if (($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE || !projectImageSizeIsAllowed($file['size'] ?? null)) {
        reportPortfolioMediaEvent('media_upload_rejected', 'project', 'encoded_size', 'FILE_TOO_LARGE', null, is_int($file['size'] ?? null) ? $file['size'] : null);
        return 'The image must be ' . projectImageMaximumMegabytes() . ' MB or smaller.';
    }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_string($file['tmp_name'])) {
        reportPortfolioMediaEvent('media_upload_rejected', 'project', 'multipart', 'IMAGE_MALFORMED');
        return 'The image upload failed.';
    }

    $actualSize = @filesize($file['tmp_name']);
    if (!projectImageSizeIsAllowed($actualSize)) {
        reportPortfolioMediaEvent('media_upload_rejected', 'project', 'actual_size', 'FILE_TOO_LARGE', null, is_int($actualSize) ? $actualSize : null);
        return 'The image must be ' . projectImageMaximumMegabytes() . ' MB or smaller.';
    }
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo === false ? false : finfo_file($fileInfo, $file['tmp_name']);
    if ($fileInfo !== false) {
        finfo_close($fileInfo);
    }
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mimeType) || !isset($extensions[$mimeType])) {
        reportPortfolioMediaEvent('media_upload_rejected', 'project', 'mime', 'UNSUPPORTED_TYPE', is_string($mimeType) ? $mimeType : null, is_int($actualSize) ? $actualSize : null);
        return 'Only JPG, PNG, and WEBP images are allowed.';
    }
    $dimensions = @getimagesize($file['tmp_name']);
    if ($dimensions === false) {
        reportPortfolioMediaEvent('media_upload_rejected', 'project', 'image_metadata', 'IMAGE_MALFORMED', $mimeType, is_int($actualSize) ? $actualSize : null);
        return 'The uploaded project image could not be decoded.';
    }
    if (!projectImageDimensionsAreSafe($dimensions, $mimeType, $file['tmp_name'])) {
        reportPortfolioMediaEvent('media_upload_rejected', 'project', 'ingestion_dimensions', 'IMAGE_UNSAFE_DIMENSIONS', $mimeType, is_int($actualSize) ? $actualSize : null, $dimensions);
        return 'Project image dimensions are too large.';
    }

    return ['extension' => $extensions[$mimeType], 'mime' => $mimeType];
}

function projectFormDefaults(): array
{
    return ['id' => '', 'title' => '', 'category' => '', 'description' => '', 'github_url' => '', 'technologies' => '', 'image_path' => null];
}

function projectActionId($value): ?int
{
    if (!is_string($value) || !ctype_digit($value) || $value === '' || strlen($value) > strlen(PROJECT_ID_MAXIMUM)) {
        return null;
    }

    if (trim($value, '0') === '') {
        return null;
    }

    if (strlen($value) === strlen(PROJECT_ID_MAXIMUM) && strcmp($value, PROJECT_ID_MAXIMUM) > 0) {
        return null;
    }

    return (int) $value;
}

function storeValidatedProjectImage(array $file, array &$errors, ?int $portfolioId = null): ?string
{
    $validation = validateProjectImageUpload($file);
    if (is_string($validation)) {
        $errors[] = $validation;
        return null;
    }
    if ($portfolioId === null || $portfolioId < 1) {
        $errors[] = 'Private media storage is unavailable.';
        return null;
    }

    try {
        $key = storePrivateUploadedImage($file, $portfolioId, 'projects', 'project', $validation['extension'], $validation['mime']);
    } catch (PortfolioQuotaExceededException) {
        reportSecurityEvent('quota_denial', 'denied', ['portfolio_id' => $portfolioId, 'resource_type' => 'project']);
        $errors[] = 'Portfolio storage quota exceeded.';
        return null;
    }
    if ($key === null) {
        reportPortfolioMediaEvent('media_upload_rejected', 'project', 'storage', 'private_staging_failed');
        $errors[] = 'The image could not be saved. Please try again.';
        return null;
    }
    $presentation = generateProjectPresentationResult($key, $portfolioId);
    if ($presentation['key'] === null) {
        deletePrivateMediaFile($key, $portfolioId, 'projects');
        reportPortfolioMediaEvent('media_upload_rejected', 'project', 'normalization', $presentation['reason']);
        $errors[] = portfolioImageFailureMessage($presentation['reason']);
        return null;
    }

    return $key;
}

function cleanProjectImage(?string $imagePath, string $action, ?int $portfolioId = null): void
{
    if ($imagePath === null || $imagePath === '') {
        return;
    }

    if ($portfolioId === null || resolvePrivateMediaPath($imagePath, $portfolioId, 'projects') === null) {
        reportApplicationError(new RuntimeException('Managed project path rejected.'), 'projects.php', $action . '_path_rejected');
        return;
    }

    if (!deleteProjectPresentationImage($imagePath, $portfolioId)) {
        reportApplicationError(new RuntimeException('Project presentation cleanup failed.'), 'projects.php', $action . '_presentation_cleanup_failed');
    }
    if (!deletePrivateMediaFile($imagePath, $portfolioId, 'projects')) {
        reportApplicationError(new RuntimeException('Project image cleanup failed.'), 'projects.php', $action . '_cleanup_failed');
    }
}

function setProjectSuccessFlash(string $message): void
{
    $_SESSION['project_success_flash'] = $message;
}

function takeProjectSuccessFlash(): string
{
    $message = isset($_SESSION['project_success_flash']) && is_string($_SESSION['project_success_flash'])
        ? $_SESSION['project_success_flash']
        : '';
    unset($_SESSION['project_success_flash']);

    return $message;
}

function projectActionResult(array $errors = [], string $formMode = 'add', ?array $editingProject = null, ?string $redirect = null): array
{
    return [
        'errors' => $errors,
        'form_mode' => $formMode,
        'editing_project' => $editingProject ?? projectFormDefaults(),
        'redirect' => $redirect,
    ];
}

function handleProjectAction(PDO $database, array $post, array $files): array
{
    $action = isset($post['action']) && is_string($post['action']) ? $post['action'] : '';

    try {
        if ($action === 'delete') {
            $projectId = projectActionId($post['id'] ?? null);
            if ($projectId === null) {
                return projectActionResult(['Please provide a valid project ID.']);
            }

            $find = $database->prepare('SELECT image_path FROM projects WHERE id = :id');
            $find->execute(['id' => $projectId]);
            $project = $find->fetch();
            if ($project === false) {
                return projectActionResult(['Project not found.']);
            }

            $statement = $database->prepare('DELETE FROM projects WHERE id = :id');
            $statement->execute(['id' => $projectId]);
            if ($statement->rowCount() !== 1) {
                return projectActionResult(['Project not found.']);
            }

            cleanProjectImage($project['image_path'] ?? null, 'project_delete');
            setProjectSuccessFlash('Project deleted successfully.');
            return projectActionResult([], 'add', null, 'projects.php');
        }

        if ($action !== 'add' && $action !== 'update') {
            return projectActionResult();
        }

        $title = isset($post['title']) && is_string($post['title']) ? trim($post['title']) : '';
        $category = isset($post['category']) && is_string($post['category']) ? trim($post['category']) : '';
        $description = isset($post['description']) && is_string($post['description']) ? trim($post['description']) : '';
        $githubUrl = isset($post['github_url']) && is_string($post['github_url']) ? trim($post['github_url']) : '';
        $technologiesInput = !array_key_exists('technologies', $post) || is_string($post['technologies'])
            ? ($post['technologies'] ?? '')
            : null;
        $formMode = $action === 'update' ? 'edit' : 'add';
        $editingProject = ['id' => $post['id'] ?? '', 'title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubUrl, 'technologies' => $technologiesInput, 'image_path' => null];
        $errors = [];
        $technologies = normalizeProjectTechnologies($technologiesInput, $errors);

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

        $projectId = $action === 'update' ? projectActionId($post['id'] ?? null) : null;
        $oldImagePath = null;
        if ($action === 'update' && $projectId === null) {
            $errors[] = 'Please provide a valid project ID.';
        } elseif ($action === 'update') {
            $find = $database->prepare('SELECT image_path, technologies FROM projects WHERE id = :id');
            $find->execute(['id' => $projectId]);
            $existing = $find->fetch();
            if ($existing === false) {
                $errors[] = 'Project not found.';
            } else {
                $oldImagePath = $existing['image_path'];
                $editingProject['image_path'] = $oldImagePath;
            }
        }

        $newImagePath = null;
        $hasUpload = isset($files['project_image']) && (($files['project_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
        if ($errors === [] && $hasUpload) {
            $newImagePath = storeValidatedProjectImage($files['project_image'], $errors);
        }
        if ($errors !== []) {
            cleanProjectImage($newImagePath, 'project_validation_compensation');
            return projectActionResult($errors, $formMode, $editingProject);
        }

        if ($action === 'add') {
            $statement = $database->prepare('INSERT INTO projects (title, category, description, github_url, image_path, technologies) VALUES (:title, :category, :description, :github_url, :image_path, :technologies)');
            $statement->execute(['title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubUrl, 'image_path' => $newImagePath, 'technologies' => projectTechnologiesToStorage($technologies ?? [])]);
            setProjectSuccessFlash('Project added successfully.');
            return projectActionResult([], 'add', null, 'projects.php');
        }

        $removeImage = isset($post['remove_image']) && $post['remove_image'] === '1';
        $imagePath = $newImagePath ?? ($removeImage ? null : $oldImagePath);
        $statement = $database->prepare('UPDATE projects SET title = :title, category = :category, description = :description, github_url = :github_url, image_path = :image_path, technologies = :technologies WHERE id = :id');
        $statement->execute(['title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubUrl, 'image_path' => $imagePath, 'technologies' => projectTechnologiesToStorage($technologies ?? []), 'id' => $projectId]);
        if ($statement->rowCount() === 0) {
            $verify = $database->prepare('SELECT id FROM projects WHERE id = :id');
            $verify->execute(['id' => $projectId]);
            if ($verify->fetch() === false) {
                cleanProjectImage($newImagePath, 'project_update_compensation');
                return projectActionResult(['Project not found.'], $formMode, $editingProject);
            }
        }

        if ($newImagePath !== null || $removeImage) {
            cleanProjectImage($oldImagePath, 'project_update_old_image');
        }
        setProjectSuccessFlash('Project updated successfully.');
        return projectActionResult([], 'add', null, 'projects.php');
    } catch (PDOException $exception) {
        reportApplicationError($exception, 'projects.php', 'project_' . ($action === '' ? 'unknown' : $action));
        if (isset($newImagePath)) {
            cleanProjectImage($newImagePath, 'project_database_compensation');
        }
        return projectActionResult(['The project could not be saved.'], $formMode ?? 'add', $editingProject ?? null);
    }
}
