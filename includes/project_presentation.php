<?php

declare(strict_types=1);

require_once __DIR__ . '/media_image_policy.php';
require_once __DIR__ . '/profile_presentation.php';
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

function createProjectPresentationSource(string $sourcePath, string $mime): mixed
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png' => @imagecreatefrompng($sourcePath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        default => false,
    };
}

function writeProjectPresentation(string $path, string $mime, mixed $image): bool
{
    return match ($mime) {
        'image/jpeg' => @imagejpeg($image, $path, 84),
        'image/png' => @imagepng($image, $path, 7),
        'image/webp' => function_exists('imagewebp') && @imagewebp($image, $path, 82),
        default => false,
    };
}

function generateProjectPresentationImage(string $originalKey, int $portfolioId): ?string
{
    if (!extension_loaded('gd')) {
        return null;
    }
    $sourcePath = resolvePrivateMediaPath($originalKey, $portfolioId, 'projects');
    $presentationKey = projectPresentationKey($originalKey, $portfolioId);
    if ($sourcePath === null || !is_file($sourcePath) || $presentationKey === null) {
        return null;
    }
    $sourceDimensions = @getimagesize($sourcePath);
    if (!projectImageDimensionsAreSafe($sourceDimensions)) {
        return null;
    }
    $existing = resolvePrivateMediaPath($presentationKey, $portfolioId, 'project_presentation');
    if ($existing !== null && @getimagesize($existing) !== false) {
        return $presentationKey;
    }
    $directory = ensurePrivateMediaDirectory($portfolioId, 'project_presentation');
    $destination = $directory === null ? null : resolvePrivateMediaPath($presentationKey, $portfolioId, 'project_presentation');
    if ($destination === null) {
        return null;
    }

    $mime = @mime_content_type($sourcePath);
    $source = is_string($mime) ? createProjectPresentationSource($sourcePath, $mime) : false;
    if ($source === false) {
        return null;
    }
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $scale = min(1, PROJECT_PRESENTATION_MAX_DIMENSION / max($sourceWidth, $sourceHeight));
    $targetWidth = max(1, (int) round($sourceWidth * $scale));
    $targetHeight = max(1, (int) round($sourceHeight * $scale));
    $presentation = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($presentation === false) {
        imagedestroy($source);
        return null;
    }
    if (in_array($mime, ['image/png', 'image/webp'], true)) {
        imagealphablending($presentation, false);
        imagesavealpha($presentation, true);
        imagefill($presentation, 0, 0, imagecolorallocatealpha($presentation, 0, 0, 0, 127));
    }
    imagecopyresampled($presentation, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
    imagedestroy($source);
    if ($mime === 'image/jpeg') {
        $presentation = orientJpegPresentation($presentation, readJpegOrientation($sourcePath));
    }

    try {
        $committed = withPortfolioQuotaReservation($portfolioId, PROJECT_PRESENTATION_QUOTA_RESERVATION_BYTES, static function () use ($destination, $mime, $presentation): bool {
            $temporary = $destination . '.stage-' . bin2hex(random_bytes(6));
            $written = writeProjectPresentation($temporary, $mime, $presentation);
            if (!$written
                || @getimagesize($temporary) === false
                || filesize($temporary) > PROJECT_PRESENTATION_QUOTA_RESERVATION_BYTES
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
    } finally {
        imagedestroy($presentation);
    }
    if (!$committed) {
        return null;
    }

    return $presentationKey;
}

function deleteProjectPresentationImage(string $originalKey, int $portfolioId): bool
{
    $presentationKey = projectPresentationKey($originalKey, $portfolioId);

    return $presentationKey === null || deletePrivateMediaFile($presentationKey, $portfolioId, 'project_presentation');
}
