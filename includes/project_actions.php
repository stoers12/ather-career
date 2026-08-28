<?php

require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/validation.php';

const PROJECT_IMAGE_PREFIX = 'uploads/projects/';

function projectFormDefaults(): array
{
    return ['id' => '', 'title' => '', 'category' => '', 'description' => '', 'github_url' => '', 'image_path' => null];
}

function projectActionId($value): ?int
{
    if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
        return null;
    }

    return (int) $value;
}

function storeValidatedProjectImage(array $file, array &$errors): ?string
{
    if (isset($file['error']) && $file['error'] === UPLOAD_ERR_INI_SIZE) {
        $errors[] = 'The image must be 2 MB or smaller.';
        return null;
    }

    if (!isset($file['error'], $file['tmp_name'], $file['size']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'The image upload failed.';
        return null;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'The image must be 2 MB or smaller.';
        return null;
    }

    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mimeType])) {
        $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';
        return null;
    }

    $filename = createManagedUploadFilename('project', $extensions[$mimeType]);
    $relativePath = storeManagedUpload($file, PROJECT_IMAGE_PREFIX, $filename);
    if ($relativePath === null) {
        $errors[] = 'The image could not be saved.';
    }

    return $relativePath;
}

function cleanProjectImage(?string $imagePath, string $action): void
{
    if ($imagePath === null || $imagePath === '') {
        return;
    }

    if (resolveManagedStoragePath($imagePath, PROJECT_IMAGE_PREFIX) === null) {
        reportApplicationError(new RuntimeException('Managed project path rejected.'), 'projects.php', $action . '_path_rejected');
        return;
    }

    if (!deleteManagedFile($imagePath, PROJECT_IMAGE_PREFIX)) {
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
        $formMode = $action === 'update' ? 'edit' : 'add';
        $editingProject = ['id' => $post['id'] ?? '', 'title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubUrl, 'image_path' => null];
        $errors = [];

        foreach (['title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubUrl] as $field => $value) {
            if ($value === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
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
            $find = $database->prepare('SELECT image_path FROM projects WHERE id = :id');
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
            $statement = $database->prepare('INSERT INTO projects (title, category, description, github_url, image_path) VALUES (:title, :category, :description, :github_url, :image_path)');
            $statement->execute(['title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubUrl, 'image_path' => $newImagePath]);
            setProjectSuccessFlash('Project added successfully.');
            return projectActionResult([], 'add', null, 'projects.php');
        }

        $removeImage = isset($post['remove_image']) && $post['remove_image'] === '1';
        $imagePath = $newImagePath ?? ($removeImage ? null : $oldImagePath);
        $statement = $database->prepare('UPDATE projects SET title = :title, category = :category, description = :description, github_url = :github_url, image_path = :image_path WHERE id = :id');
        $statement->execute(['title' => $title, 'category' => $category, 'description' => $description, 'github_url' => $githubUrl, 'image_path' => $imagePath, 'id' => $projectId]);
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
