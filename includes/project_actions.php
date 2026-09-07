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
        reportApplicationError(new RuntimeException('Managed project path rejected.'), 'owner_projects.php', $action . '_path_rejected');
        return;
    }

    if (!deleteProjectPresentationImage($imagePath, $portfolioId)) {
        reportApplicationError(new RuntimeException('Project presentation cleanup failed.'), 'owner_projects.php', $action . '_presentation_cleanup_failed');
    }
    if (!deletePrivateMediaFile($imagePath, $portfolioId, 'projects')) {
        reportApplicationError(new RuntimeException('Project image cleanup failed.'), 'owner_projects.php', $action . '_cleanup_failed');
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
