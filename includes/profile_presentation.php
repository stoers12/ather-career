<?php

require_once __DIR__ . '/storage.php';

const PROFILE_IMAGE_PREFIX = 'uploads/profile/';
const PROFILE_PRESENTATION_PREFIX = 'uploads/profile/derived/';
const PROFILE_PRESENTATION_MAX_DIMENSION = 960;

function ensureProfilePresentationDirectory(): bool
{
    $projectRoot = realpath(__DIR__ . '/..');
    if ($projectRoot === false) {
        return false;
    }

    $directory = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'derived';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return false;
    }

    return realpath($directory) !== false;
}

function profilePresentationRelativePath(string $originalPath): ?string
{
    $original = resolveManagedStoragePath($originalPath, PROFILE_IMAGE_PREFIX);
    if ($original === null || !is_file($original)) {
        return null;
    }

    $mime = @mime_content_type($original);
    $extension = $mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : null);
    if ($extension === null) {
        return null;
    }

    $stem = pathinfo(basename($originalPath), PATHINFO_FILENAME);
    if (preg_match('/^[A-Za-z0-9._-]+$/', $stem) !== 1) {
        return null;
    }

    return PROFILE_PRESENTATION_PREFIX . $stem . '_presentation.' . $extension;
}

function readJpegOrientation(string $sourcePath): int
{
    if (!function_exists('exif_read_data')) {
        return 1;
    }

    $exif = @exif_read_data($sourcePath, 'IFD0', true, false);
    $orientation = is_array($exif)
        ? (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1)
        : 1;

    return $orientation;
}

function orientJpegPresentation($image, int $orientation)
{
    if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) {
        imageflip($image, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
    }
    $rotated = false;
    if (in_array($orientation, [3, 4], true)) {
        $rotated = imagerotate($image, 180, 0);
    } elseif (in_array($orientation, [5, 6], true)) {
        $rotated = imagerotate($image, -90, 0);
    } elseif (in_array($orientation, [7, 8], true)) {
        $rotated = imagerotate($image, 90, 0);
    }
    if ($rotated !== false) {
        imagedestroy($image);
        return $rotated;
    }

    return $image;
}

function generateProfilePresentationImage(string $originalPath): ?string
{
    if (!extension_loaded('gd') || !ensureProfilePresentationDirectory()) {
        return null;
    }

    $sourcePath = resolveManagedStoragePath($originalPath, PROFILE_IMAGE_PREFIX);
    $presentationPath = profilePresentationRelativePath($originalPath);
    $destination = $presentationPath === null ? null : resolveManagedStoragePath($presentationPath, PROFILE_PRESENTATION_PREFIX);
    if ($sourcePath === null || $destination === null) {
        return null;
    }

    $existing = is_file($destination) ? @getimagesize($destination) : false;
    if ($existing !== false && $existing[0] > 0 && $existing[1] > 0) {
        return $presentationPath;
    }

    $mime = @mime_content_type($sourcePath);
    $orientation = $mime === 'image/jpeg' ? readJpegOrientation($sourcePath) : 1;
    $source = $mime === 'image/jpeg' ? @imagecreatefromjpeg($sourcePath) : ($mime === 'image/png' ? @imagecreatefrompng($sourcePath) : false);
    if ($source === false) {
        return null;
    }
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $scale = min(1, PROFILE_PRESENTATION_MAX_DIMENSION / max($sourceWidth, $sourceHeight));
    $targetWidth = max(1, (int) round($sourceWidth * $scale));
    $targetHeight = max(1, (int) round($sourceHeight * $scale));
    $presentation = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($presentation === false) {
        imagedestroy($source);
        return null;
    }
    if ($mime === 'image/png') {
        imagealphablending($presentation, false);
        imagesavealpha($presentation, true);
        $transparent = imagecolorallocatealpha($presentation, 0, 0, 0, 127);
        imagefill($presentation, 0, 0, $transparent);
    }
    imagecopyresampled($presentation, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
    imagedestroy($source);
    if ($mime === 'image/jpeg') {
        $presentation = orientJpegPresentation($presentation, $orientation);
    }

    $temporary = $destination . '.tmp-' . bin2hex(random_bytes(6));
    $written = $mime === 'image/jpeg'
        ? @imagejpeg($presentation, $temporary, 84)
        : @imagepng($presentation, $temporary, 7);
    imagedestroy($presentation);
    if (!$written || !is_file($temporary) || @getimagesize($temporary) === false || !@rename($temporary, $destination)) {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
        return null;
    }
    @chmod($destination, 0644);

    return $presentationPath;
}

function profilePresentationData(?string $originalPath): ?array
{
    if ($originalPath === null || $originalPath === '') {
        return null;
    }

    $presentationPath = generateProfilePresentationImage($originalPath);
    $path = $presentationPath ?? $originalPath;
    $prefix = $presentationPath === null ? PROFILE_IMAGE_PREFIX : PROFILE_PRESENTATION_PREFIX;
    $filesystemPath = resolveManagedStoragePath($path, $prefix);
    $dimensions = $filesystemPath === null ? false : @getimagesize($filesystemPath);
    if ($dimensions === false) {
        return null;
    }

    return ['path' => $path, 'width' => $dimensions[0], 'height' => $dimensions[1], 'derived' => $presentationPath !== null];
}

function deleteProfilePresentationImage(string $originalPath): bool
{
    $presentationPath = profilePresentationRelativePath($originalPath);

    return $presentationPath === null || deleteManagedFile($presentationPath, PROFILE_PRESENTATION_PREFIX);
}
