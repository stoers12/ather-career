<?php

declare(strict_types=1);

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

function readJpegOrientation(string $sourcePath): int
{
    if (!function_exists('exif_read_data')) return 1;
    $exif = @exif_read_data($sourcePath, 'IFD0', true, false);

    return is_array($exif) ? (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1) : 1;
}

function orientJpegPresentation($image, int $orientation)
{
    if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) imageflip($image, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
    $rotated = false;
    if (in_array($orientation, [3, 4], true)) $rotated = imagerotate($image, 180, 0);
    elseif (in_array($orientation, [5, 6], true)) $rotated = imagerotate($image, -90, 0);
    elseif (in_array($orientation, [7, 8], true)) $rotated = imagerotate($image, 90, 0);
    if ($rotated !== false) { imagedestroy($image); return $rotated; }

    return $image;
}

function generateProfilePresentationImage(string $originalKey, int $portfolioId): ?string
{
    if (!extension_loaded('gd')) return null;
    $sourcePath = resolvePrivateMediaPath($originalKey, $portfolioId, 'profile_original');
    $presentationKey = profilePresentationKey($originalKey, $portfolioId);
    if ($sourcePath === null || !is_file($sourcePath) || $presentationKey === null) return null;
    $existing = resolvePrivateMediaPath($presentationKey, $portfolioId, 'profile_presentation');
    if ($existing !== null && @getimagesize($existing) !== false) return $presentationKey;
    $directory = ensurePrivateMediaDirectory($portfolioId, 'profile_presentation');
    $destination = $directory === null ? null : resolvePrivateMediaPath($presentationKey, $portfolioId, 'profile_presentation');
    if ($destination === null) return null;

    $mime = @mime_content_type($sourcePath);
    $source = $mime === 'image/jpeg' ? @imagecreatefromjpeg($sourcePath) : ($mime === 'image/png' ? @imagecreatefrompng($sourcePath) : false);
    if ($source === false) return null;
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $scale = min(1, PROFILE_PRESENTATION_MAX_DIMENSION / max($sourceWidth, $sourceHeight));
    $targetWidth = max(1, (int) round($sourceWidth * $scale));
    $targetHeight = max(1, (int) round($sourceHeight * $scale));
    $presentation = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($presentation === false) { imagedestroy($source); return null; }
    if ($mime === 'image/png') {
        imagealphablending($presentation, false);
        imagesavealpha($presentation, true);
        imagefill($presentation, 0, 0, imagecolorallocatealpha($presentation, 0, 0, 0, 127));
    }
    imagecopyresampled($presentation, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
    imagedestroy($source);
    if ($mime === 'image/jpeg') $presentation = orientJpegPresentation($presentation, readJpegOrientation($sourcePath));

    try {
        $committed = withPortfolioQuotaReservation($portfolioId, PROFILE_PRESENTATION_QUOTA_RESERVATION_BYTES, static function () use ($destination, $mime, $presentation): bool {
            $temporary = $destination . '.stage-' . bin2hex(random_bytes(6));
            $written = $mime === 'image/jpeg' ? @imagejpeg($presentation, $temporary, 84) : @imagepng($presentation, $temporary, 7);
            if (!$written || @getimagesize($temporary) === false || filesize($temporary) > PROFILE_PRESENTATION_QUOTA_RESERVATION_BYTES || file_exists($destination) || !@rename($temporary, $destination)) {
                if (is_file($temporary)) @unlink($temporary);
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
    if (!$committed) return null;

    return $presentationKey;
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
