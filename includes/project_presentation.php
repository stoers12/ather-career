<?php

declare(strict_types=1);

require_once __DIR__ . '/image_processor.php';
require_once __DIR__ . '/storage.php';

const PROJECT_PRESENTATION_MAX_DIMENSION = 1600;
const PROJECT_PRESENTATION_QUOTA_RESERVATION_BYTES = 8388608;

function projectPresentationKey(string $originalKey, int $portfolioId): ?string
{
    $parts = parseManagedMediaKey($originalKey);
    if ($parts === null || $parts['portfolio_id'] !== $portfolioId || $parts['collection'] !== 'projects') {
        return null;
    }
    $stem = pathinfo($parts['filename'], PATHINFO_FILENAME);
    $extension = strtolower((string) pathinfo($parts['filename'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'png', 'webp'], true)) {
        return null;
    }

    return managedMediaKey($portfolioId, 'project_presentation', $stem . '_presentation.' . $extension);
}

/** @return array{key: string|null, reason: string} */
function generateProjectPresentationResult(string $originalKey, int $portfolioId): array
{
    $sourcePath = resolvePrivateMediaPath($originalKey, $portfolioId, 'projects');
    $presentationKey = projectPresentationKey($originalKey, $portfolioId);
    if ($sourcePath === null || !is_file($sourcePath) || $presentationKey === null) {
        return ['key' => null, 'reason' => 'NORMALIZATION_FAILED'];
    }
    $sourceDimensions = @getimagesize($sourcePath);
    $sourceMime = @mime_content_type($sourcePath);
    if (!is_string($sourceMime) || !projectImageDimensionsAreSafe($sourceDimensions, $sourceMime, $sourcePath)) {
        return ['key' => null, 'reason' => 'IMAGE_UNSAFE_DIMENSIONS'];
    }
    $existing = resolvePrivateMediaPath($presentationKey, $portfolioId, 'project_presentation');
    if ($existing !== null && @getimagesize($existing) !== false) {
        return ['key' => $presentationKey, 'reason' => 'success'];
    }
    $directory = ensurePrivateMediaDirectory($portfolioId, 'project_presentation');
    $destination = $directory === null ? null : resolvePrivateMediaPath($presentationKey, $portfolioId, 'project_presentation');
    if ($destination === null) {
        return ['key' => null, 'reason' => 'STORAGE_FAILED'];
    }

    try {
        $reason = 'NORMALIZATION_FAILED';
        $committed = withPortfolioQuotaReservation($portfolioId, PROJECT_PRESENTATION_QUOTA_RESERVATION_BYTES, static function () use ($destination, $sourcePath, $sourceMime, &$reason): bool {
            $temporary = portfolioImageTemporaryDerivativePath($destination);
            if ($temporary === null) {
                return false;
            }
            $normalized = normalizePortfolioImage($sourcePath, $temporary, PROJECT_PRESENTATION_MAX_DIMENSION, $sourceMime, 'project');
            $reason = $normalized['reason'];
            if (!$normalized['ok']
                || ($normalized['bytes'] ?? 0) > PROJECT_PRESENTATION_QUOTA_RESERVATION_BYTES
                || file_exists($destination)
                || !@rename($temporary, $destination)) {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
                return false;
            }
            @chmod($destination, 0600);
            return true;
        });
    } catch (PortfolioQuotaExceededException) {
        $committed = false;
        $reason = 'STORAGE_FAILED';
    }
    if (!$committed) {
        return ['key' => null, 'reason' => $reason ?? 'NORMALIZATION_FAILED'];
    }

    return ['key' => $presentationKey, 'reason' => 'success'];
}

function generateProjectPresentationImage(string $originalKey, int $portfolioId): ?string
{
    return generateProjectPresentationResult($originalKey, $portfolioId)['key'];
}

function deleteProjectPresentationImage(string $originalKey, int $portfolioId): bool
{
    $presentationKey = projectPresentationKey($originalKey, $portfolioId);

    return $presentationKey === null || deletePrivateMediaFile($presentationKey, $portfolioId, 'project_presentation');
}
