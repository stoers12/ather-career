<?php

require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/media_image_policy.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/profile_presentation.php';

const PROFILE_IMAGE_MAX_BYTES = 8 * 1024 * 1024;

function profileImageMaximumMegabytes(): int
{
    return (int) (PROFILE_IMAGE_MAX_BYTES / (1024 * 1024));
}

function profileImageSizeIsAllowed(mixed $size): bool
{
    return is_int($size) && $size >= 0 && $size <= PROFILE_IMAGE_MAX_BYTES;
}

/** @return array{extension: string, mime: string}|string */
function validateProfileImageUpload(array $file): array|string
{
    if (($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE || !profileImageSizeIsAllowed($file['size'] ?? null)) {
        reportPortfolioMediaEvent('media_upload_rejected', 'profile', 'encoded_size', 'FILE_TOO_LARGE', null, is_int($file['size'] ?? null) ? $file['size'] : null);
        return 'Profile photo must be ' . profileImageMaximumMegabytes() . ' MB or smaller.';
    }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_string($file['tmp_name'])) {
        reportPortfolioMediaEvent('media_upload_rejected', 'profile', 'multipart', 'IMAGE_MALFORMED');
        return 'The uploaded image could not be processed.';
    }

    $actualSize = @filesize($file['tmp_name']);
    if (!profileImageSizeIsAllowed($actualSize)) {
        reportPortfolioMediaEvent('media_upload_rejected', 'profile', 'actual_size', 'FILE_TOO_LARGE', null, is_int($actualSize) ? $actualSize : null);
        return 'Profile photo must be ' . profileImageMaximumMegabytes() . ' MB or smaller.';
    }
    $info = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $info === false ? false : finfo_file($info, $file['tmp_name']);
    if ($info !== false) {
        finfo_close($info);
    }
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        reportPortfolioMediaEvent('media_upload_rejected', 'profile', 'mime', 'UNSUPPORTED_TYPE', is_string($mime) ? $mime : null, is_int($actualSize) ? $actualSize : null);
        return 'Please upload a JPG or PNG image.';
    }
    $dimensions = @getimagesize($file['tmp_name']);
    if ($dimensions === false) {
        reportPortfolioMediaEvent('media_upload_rejected', 'profile', 'image_metadata', 'IMAGE_MALFORMED', $mime, is_int($actualSize) ? $actualSize : null);
        return 'The uploaded profile photo could not be decoded.';
    }
    if ($dimensions[0] < 400 || $dimensions[1] < 400) {
        reportPortfolioMediaEvent('media_upload_rejected', 'profile', 'minimum_dimensions', 'IMAGE_TOO_SMALL', $mime, is_int($actualSize) ? $actualSize : null, $dimensions);
        return 'Profile photo must be at least 400 × 400 pixels.';
    }
    if (!profileImageDimensionsAreSafe($dimensions, $mime, $file['tmp_name'])) {
        reportPortfolioMediaEvent('media_upload_rejected', 'profile', 'ingestion_dimensions', 'IMAGE_UNSAFE_DIMENSIONS', $mime, is_int($actualSize) ? $actualSize : null, $dimensions);
        return 'Profile photo dimensions are too large.';
    }

    return ['extension' => $extensions[$mime], 'mime' => $mime];
}

function isMySqlDuplicateKeyViolation(PDOException $exception): bool
{
    $driverCode = $exception->errorInfo[1] ?? null;

    return safePdoErrorCode($exception) === '23000'
        && (is_int($driverCode) || ctype_digit((string) $driverCode))
        && (int) $driverCode === 1062;
}

function storeValidatedProfileImage(array $file, array &$errors, ?int $portfolioId = null): ?string
{
    $validation = validateProfileImageUpload($file);
    if (is_string($validation)) {
        $errors[] = $validation;
        return null;
    }
    if ($portfolioId === null || $portfolioId < 1) {
        $errors[] = 'Private media storage is unavailable.';
        return null;
    }

    try {
        $key = storePrivateUploadedImage($file, $portfolioId, 'profile_original', 'profile', $validation['extension'], $validation['mime']);
    } catch (PortfolioQuotaExceededException) {
        reportSecurityEvent('quota_denial', 'denied', ['portfolio_id' => $portfolioId, 'resource_type' => 'profile']);
        $errors[] = 'Portfolio storage quota exceeded.';
        return null;
    }
    if ($key === null) {
        reportPortfolioMediaEvent('media_upload_rejected', 'profile', 'storage', 'private_staging_failed');
        $errors[] = 'The image could not be saved. Please try again.';
        return null;
    }
    try {
        $presentation = generateProfilePresentationResult($key, $portfolioId);
    } catch (Throwable $exception) {
        reportApplicationError($exception, 'owner_profile.php', 'profile_presentation_storage_failure');
        $presentation = ['key' => null, 'reason' => 'NORMALIZATION_FAILED'];
    }
    if ($presentation['key'] === null) {
        // Normalization may have committed a derivative before a later check
        // fails. Both files belong to this request, so retire both.
        deleteProfilePresentationImage($key, $portfolioId);
        deletePrivateMediaFile($key, $portfolioId, 'profile_original');
        reportPortfolioMediaEvent('media_upload_rejected', 'profile', 'normalization', $presentation['reason']);
        $errors[] = portfolioImageFailureMessage($presentation['reason']);
        return null;
    }

    return $key;
}

function cleanProfileImage(?string $imagePath, string $action, ?int $portfolioId = null): void
{
    if ($imagePath === null || $imagePath === '') {
        return;
    }

    if ($portfolioId === null || resolvePrivateMediaPath($imagePath, $portfolioId, 'profile_original') === null) {
        reportApplicationError(new RuntimeException('Managed profile path rejected.'), 'owner_profile.php', $action . '_path_rejected');
        return;
    }

    if (!deleteProfilePresentationImage($imagePath, $portfolioId)) {
        reportApplicationError(new RuntimeException('Profile presentation cleanup failed.'), 'owner_profile.php', $action . '_presentation_cleanup_failed');
    }
    if (!deletePrivateMediaFile($imagePath, $portfolioId, 'profile_original')) {
        reportApplicationError(new RuntimeException('Profile image cleanup failed.'), 'owner_profile.php', $action . '_cleanup_failed');
    }
}
