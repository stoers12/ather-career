<?php

declare(strict_types=1);

require_once __DIR__ . '/security_events.php';

const PORTFOLIO_IMAGE_MAX_EDGE = 12000;
const PORTFOLIO_JPEG_PIXEL_CEILING = 64000000;
const PORTFOLIO_PROGRESSIVE_JPEG_PIXEL_CEILING = 26000000;
const PORTFOLIO_PNG_PIXEL_CEILING = 64000000;
const PORTFOLIO_WEBP_PIXEL_CEILING = 32000000;

function imageDimensionsAreWithinPixelCeiling(mixed $dimensions, int $pixelCeiling): bool
{
    if (!is_array($dimensions)
        || !isset($dimensions[0], $dimensions[1])
        || !is_int($dimensions[0])
        || !is_int($dimensions[1])
        || $dimensions[0] < 1
        || $dimensions[1] < 1
        || $pixelCeiling < 1) {
        return false;
    }

    return $dimensions[0] <= intdiv($pixelCeiling, $dimensions[1]);
}

function imageDimensionsPixelCount(mixed $dimensions): ?int
{
    if (!is_array($dimensions)
        || !isset($dimensions[0], $dimensions[1])
        || !is_int($dimensions[0])
        || !is_int($dimensions[1])
        || $dimensions[0] < 1
        || $dimensions[1] < 1
        || $dimensions[0] > intdiv(PHP_INT_MAX, $dimensions[1])) {
        return null;
    }

    return $dimensions[0] * $dimensions[1];
}

function jpegSourceIsProgressive(string $path): ?bool
{
    $handle = @fopen($path, 'rb');
    if ($handle === false || fread($handle, 2) !== "\xFF\xD8") {
        if (is_resource($handle)) fclose($handle);
        return null;
    }

    try {
        $scanned = 2;
        while ($scanned < 1048576) {
            $prefix = fread($handle, 1);
            if ($prefix === '' || $prefix === false) return null;
            $scanned++;
            if ($prefix !== "\xFF") continue;
            do {
                $marker = fread($handle, 1);
                if ($marker === '' || $marker === false) return null;
                $scanned++;
            } while ($marker === "\xFF");
            $code = ord($marker);
            if ($code === 0xD9 || $code === 0xDA) return null;
            if (($code >= 0xC0 && $code <= 0xC3) || ($code >= 0xC5 && $code <= 0xC7) || ($code >= 0xC9 && $code <= 0xCB) || ($code >= 0xCD && $code <= 0xCF)) {
                return in_array($code, [0xC2, 0xC6, 0xCA, 0xCE], true);
            }
            if (in_array($code, [0x01, 0xD0, 0xD1, 0xD2, 0xD3, 0xD4, 0xD5, 0xD6, 0xD7], true)) continue;
            $lengthBytes = fread($handle, 2);
            if (!is_string($lengthBytes) || strlen($lengthBytes) !== 2) return null;
            $scanned += 2;
            $length = unpack('nlength', $lengthBytes)['length'] ?? 0;
            if (!is_int($length) || $length < 2 || $length - 2 > 1048576 - $scanned) return null;
            if (fseek($handle, $length - 2, SEEK_CUR) !== 0) return null;
            $scanned += $length - 2;
        }
    } finally {
        fclose($handle);
    }

    return null;
}

function portfolioImagePixelCeiling(string $mime, string $sourcePath): ?int
{
    return match ($mime) {
        'image/jpeg' => match (jpegSourceIsProgressive($sourcePath)) {
            true => PORTFOLIO_PROGRESSIVE_JPEG_PIXEL_CEILING,
            false => PORTFOLIO_JPEG_PIXEL_CEILING,
            default => null,
        },
        'image/png' => PORTFOLIO_PNG_PIXEL_CEILING,
        'image/webp' => PORTFOLIO_WEBP_PIXEL_CEILING,
        default => null,
    };
}

/** @return array{safe: bool, pixels: int|null, ceiling: int|null, progressive: bool|null} */
function portfolioImageDimensionsSafety(mixed $dimensions, string $mime, string $sourcePath): array
{
    if (!is_array($dimensions) || !isset($dimensions[0], $dimensions[1]) || !is_int($dimensions[0]) || !is_int($dimensions[1])) {
        return ['safe' => false, 'pixels' => null, 'ceiling' => null, 'progressive' => null];
    }
    $width = $dimensions[0];
    $height = $dimensions[1];
    $progressive = $mime === 'image/jpeg' ? jpegSourceIsProgressive($sourcePath) : null;
    $ceiling = portfolioImagePixelCeiling($mime, $sourcePath);
    $pixels = imageDimensionsPixelCount($dimensions);
    $safe = $width > 0
        && $height > 0
        && $width <= PORTFOLIO_IMAGE_MAX_EDGE
        && $height <= PORTFOLIO_IMAGE_MAX_EDGE
        && $ceiling !== null
        && imageDimensionsAreWithinPixelCeiling($dimensions, $ceiling);

    return ['safe' => $safe, 'pixels' => $pixels, 'ceiling' => $ceiling, 'progressive' => $progressive];
}

function profileImageDimensionsAreSafe(mixed $dimensions, string $mime, string $sourcePath): bool
{
    return portfolioImageDimensionsSafety($dimensions, $mime, $sourcePath)['safe'];
}

function projectImageDimensionsAreSafe(mixed $dimensions, string $mime, string $sourcePath): bool
{
    return portfolioImageDimensionsSafety($dimensions, $mime, $sourcePath)['safe'];
}

function reportPortfolioMediaEvent(string $event, string $mediaKind, string $stage, string $reason, ?string $mime = null, ?int $bytes = null, mixed $dimensions = null, ?int $durationMilliseconds = null): void
{
    $width = is_array($dimensions) && isset($dimensions[0]) && is_int($dimensions[0]) ? $dimensions[0] : null;
    $height = is_array($dimensions) && isset($dimensions[1]) && is_int($dimensions[1]) ? $dimensions[1] : null;
    reportSecurityEvent($event, $event === 'media_normalized' ? 'success' : 'rejected', [
        'media_kind' => $mediaKind,
        'stage' => $stage,
        'reason' => $reason,
        'mime' => $mime,
        'bytes' => $bytes,
        'width' => $width,
        'height' => $height,
        'pixels' => imageDimensionsPixelCount($dimensions),
        'processor' => 'libvips',
        'duration_ms' => $durationMilliseconds,
    ]);
}
