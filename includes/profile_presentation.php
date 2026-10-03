<?php

declare(strict_types=1);

require_once __DIR__ . '/image_processor.php';
require_once __DIR__ . '/storage.php';

const PROFILE_PRESENTATION_MAX_DIMENSION = 960;
const PROFILE_PRESENTATION_QUOTA_RESERVATION_BYTES = 8388608;

function profilePresentationKey(string $originalKey, int $portfolioId): ?string
{
    $parts = parseManagedMediaKey($originalKey);
    if ($parts === null || $parts['portfolio_id'] !== $portfolioId || $parts['collection'] !== 'profile_original') return null;
    $stem = pathinfo($parts['filename'], PATHINFO_FILENAME);
    $extension = strtolower((string) pathinfo($parts['filename'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'png'], true)) return null;

    return managedMediaKey($portfolioId, 'profile_presentation', $stem . '_presentation.' . $extension);
}

/** @return array{key: string|null, reason: string} */
function generateProfilePresentationResult(string $originalKey, int $portfolioId): array
{
    $sourcePath = resolvePrivateMediaPath($originalKey, $portfolioId, 'profile_original');
    $presentationKey = profilePresentationKey($originalKey, $portfolioId);
    if ($sourcePath === null || !is_file($sourcePath) || $presentationKey === null) return ['key' => null, 'reason' => 'NORMALIZATION_FAILED'];
    $sourceDimensions = @getimagesize($sourcePath);
    $sourceMime = @mime_content_type($sourcePath);
    if (!is_string($sourceMime) || !profileImageDimensionsAreSafe($sourceDimensions, $sourceMime, $sourcePath)) return ['key' => null, 'reason' => 'IMAGE_UNSAFE_DIMENSIONS'];
    $existing = resolvePrivateMediaPath($presentationKey, $portfolioId, 'profile_presentation');
    if ($existing !== null && @getimagesize($existing) !== false) return ['key' => $presentationKey, 'reason' => 'success'];
    $directory = ensurePrivateMediaDirectory($portfolioId, 'profile_presentation');
    $destination = $directory === null ? null : resolvePrivateMediaPath($presentationKey, $portfolioId, 'profile_presentation');
    if ($destination === null) return ['key' => null, 'reason' => 'STORAGE_FAILED'];

    try {
        $reason = 'NORMALIZATION_FAILED';
        $committed = withPortfolioQuotaReservation($portfolioId, PROFILE_PRESENTATION_QUOTA_RESERVATION_BYTES, static function () use ($destination, $sourcePath, $sourceMime, &$reason): bool {
            $temporary = portfolioImageTemporaryDerivativePath($destination);
            if ($temporary === null) {
                return false;
            }
            $normalized = normalizePortfolioImage($sourcePath, $temporary, PROFILE_PRESENTATION_MAX_DIMENSION, $sourceMime, 'profile');
            $reason = $normalized['reason'];
            if (!$normalized['ok'] || ($normalized['bytes'] ?? 0) > PROFILE_PRESENTATION_QUOTA_RESERVATION_BYTES || file_exists($destination) || !@rename($temporary, $destination)) {
                if (is_file($temporary)) @unlink($temporary);
                return false;
            }
            @chmod($destination, 0600);
            return true;
        });
    } catch (PortfolioQuotaExceededException) {
        $committed = false;
        $reason = 'STORAGE_FAILED';
    }
    if (!$committed) return ['key' => null, 'reason' => $reason ?? 'NORMALIZATION_FAILED'];

    return ['key' => $presentationKey, 'reason' => 'success'];
}

function generateProfilePresentationImage(string $originalKey, int $portfolioId): ?string
{
    return generateProfilePresentationResult($originalKey, $portfolioId)['key'];
}

function profilePresentationData(?string $originalKey, int $portfolioId): ?array
{
    if ($originalKey === null || $originalKey === '') return null;
    $presentationKey = generateProfilePresentationImage($originalKey, $portfolioId);
    if ($presentationKey === null) return null;
    $descriptor = privateMediaDescriptor($presentationKey, $portfolioId, 'profile_presentation');
    $dimensions = $descriptor === null ? false : @getimagesize($descriptor['path']);

    return $dimensions === false ? null : ['key' => $presentationKey, 'width' => $dimensions[0], 'height' => $dimensions[1]];
}

function deleteProfilePresentationImage(string $originalKey, int $portfolioId): bool
{
    $presentationKey = profilePresentationKey($originalKey, $portfolioId);

    return $presentationKey === null || deletePrivateMediaFile($presentationKey, $portfolioId, 'profile_presentation');
}
