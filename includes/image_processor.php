<?php

declare(strict_types=1);

require_once __DIR__ . '/media_image_policy.php';
require_once __DIR__ . '/storage.php';

const PORTFOLIO_IMAGE_PROCESSOR_EXECUTABLE = '/usr/bin/vips';
const PORTFOLIO_IMAGE_PROCESSOR_TIMEOUT_MILLISECONDS = 10000;
const PORTFOLIO_IMAGE_NORMALIZATION_LOCK_WAIT_MILLISECONDS = 1000;

/** @return array{ok: bool, reason: string, duration_ms: int, width: int|null, height: int|null, bytes: int|null} */
function portfolioImageProcessorResult(bool $ok, string $reason, int $durationMilliseconds = 0, ?int $width = null, ?int $height = null, ?int $bytes = null): array
{
    return [
        'ok' => $ok,
        'reason' => $reason,
        'duration_ms' => $durationMilliseconds,
        'width' => $width,
        'height' => $height,
        'bytes' => $bytes,
    ];
}

function portfolioImageProcessorExecutable(): string
{
    return PORTFOLIO_IMAGE_PROCESSOR_EXECUTABLE;
}

function portfolioImageProcessorTimeoutMilliseconds(): int
{
    return PORTFOLIO_IMAGE_PROCESSOR_TIMEOUT_MILLISECONDS;
}

function portfolioImageNormalizationLockWaitMilliseconds(): int
{
    return PORTFOLIO_IMAGE_NORMALIZATION_LOCK_WAIT_MILLISECONDS;
}

function portfolioImageProcessorNowNanoseconds(): int
{
    return function_exists('hrtime') ? hrtime(true) : (int) round(microtime(true) * 1000000000);
}

function portfolioImageProcessorElapsedMilliseconds(int $startedNanoseconds): int
{
    return max(0, intdiv(portfolioImageProcessorNowNanoseconds() - $startedNanoseconds, 1000000));
}

function portfolioImageProcessorPathIsControlled(string $path, bool $mustExist): bool
{
    $root = requirePrivateStorageRoot(true);
    $directory = realpath(dirname($path));
    if ($directory === false || !filesystemPathIsWithin($directory, $root)) {
        return false;
    }
    if ($mustExist) {
        $resolved = realpath($path);
        return $resolved !== false && is_file($resolved) && filesystemPathIsWithin($resolved, $root);
    }

    return preg_match('/^\.stage-[a-f0-9]{12}\.(?:jpg|png|webp)$/', basename($path)) === 1 && !file_exists($path);
}

function portfolioImageTemporaryDerivativePath(string $destinationPath): ?string
{
    $extension = strtolower((string) pathinfo($destinationPath, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'png', 'webp'], true)) {
        return null;
    }

    try {
        $token = bin2hex(random_bytes(6));
    } catch (Throwable) {
        return null;
    }

    return dirname($destinationPath) . DIRECTORY_SEPARATOR . '.stage-' . $token . '.' . $extension;
}

/** @return resource|null */
function acquirePortfolioImageNormalizationLock()
{
    $root = requirePrivateStorageRoot(true);
    $handle = @fopen($root . DIRECTORY_SEPARATOR . '.image-normalization.lock', 'c+');
    if ($handle === false) {
        return null;
    }
    $started = portfolioImageProcessorNowNanoseconds();
    $waitMilliseconds = portfolioImageNormalizationLockWaitMilliseconds();
    do {
        if (flock($handle, LOCK_EX | LOCK_NB)) {
            @chmod($root . DIRECTORY_SEPARATOR . '.image-normalization.lock', 0600);
            return $handle;
        }
        usleep(20000);
    } while (portfolioImageProcessorElapsedMilliseconds($started) < $waitMilliseconds);

    fclose($handle);
    return null;
}

/** @param resource $handle */
function releasePortfolioImageNormalizationLock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

function portfolioImageProcessorOutputSpec(string $destinationPath, string $mime): ?string
{
    return match ($mime) {
        'image/jpeg' => $destinationPath . '[Q=84,strip]',
        'image/png' => $destinationPath . '[compression=7,strip]',
        'image/webp' => $destinationPath . '[Q=82,strip]',
        default => null,
    };
}

/** @return array{width: int, height: int}|null */
function portfolioImageVisualDimensions(string $sourcePath, string $mime, mixed $dimensions): ?array
{
    if (!is_array($dimensions) || !isset($dimensions[0], $dimensions[1]) || !is_int($dimensions[0]) || !is_int($dimensions[1])) {
        return null;
    }
    $width = $dimensions[0];
    $height = $dimensions[1];
    if ($mime === 'image/jpeg' && jpegSourceOrientation($sourcePath) >= 5 && jpegSourceOrientation($sourcePath) <= 8) {
        [$width, $height] = [$height, $width];
    }

    return ['width' => $width, 'height' => $height];
}

function jpegSourceOrientation(string $sourcePath): int
{
    if (!function_exists('exif_read_data')) {
        return 1;
    }
    $exif = @exif_read_data($sourcePath, 'IFD0', true, false);
    $orientation = is_array($exif) ? (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1) : 1;

    return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
}

function portfolioImageDerivativeIsValid(string $path, string $expectedMime, int $maxDimension, string $sourcePath, mixed $sourceDimensions): array|false
{
    if (!is_file($path) || !is_readable($path)) {
        return false;
    }
    $bytes = @filesize($path);
    $info = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $info === false ? false : finfo_file($info, $path);
    if ($info !== false) finfo_close($info);
    $dimensions = @getimagesize($path);
    $visual = portfolioImageVisualDimensions($sourcePath, $expectedMime, $sourceDimensions);
    if (!is_int($bytes) || $bytes < 1 || $mime !== $expectedMime || !is_array($dimensions) || $visual === null) {
        return false;
    }
    if ($dimensions[0] < 1 || $dimensions[1] < 1 || max($dimensions[0], $dimensions[1]) > $maxDimension) {
        return false;
    }
    $scale = min(1, $maxDimension / max($visual['width'], $visual['height']));
    $expectedWidth = max(1, (int) round($visual['width'] * $scale));
    $expectedHeight = max(1, (int) round($visual['height'] * $scale));
    if (abs($dimensions[0] - $expectedWidth) > 1 || abs($dimensions[1] - $expectedHeight) > 1) {
        return false;
    }

    return ['width' => $dimensions[0], 'height' => $dimensions[1], 'bytes' => $bytes];
}

/** @param resource $process @param array<int, resource> $pipes */
function closePortfolioImageProcessor($process, array $pipes, bool $terminate): int
{
    if ($terminate) {
        @proc_terminate($process);
        usleep(100000);
        $status = proc_get_status($process);
        if (($status['running'] ?? false) === true) {
            @proc_terminate($process, 9);
        }
    }
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) fclose($pipe);
    }

    return proc_close($process);
}

/** @return array{ok: bool, reason: string, duration_ms: int, width: int|null, height: int|null, bytes: int|null} */
function normalizePortfolioImage(string $sourcePath, string $destinationPath, int $maxDimension, string $expectedMime, string $mediaKind): array
{
    $sourceDimensions = @getimagesize($sourcePath);
    $sourceSafety = portfolioImageDimensionsSafety($sourceDimensions, $expectedMime, $sourcePath);
    if (!$sourceSafety['safe']) {
        return portfolioImageProcessorResult(false, 'IMAGE_UNSAFE_DIMENSIONS');
    }
    if ($maxDimension < 1 || !portfolioImageProcessorPathIsControlled($sourcePath, true) || !portfolioImageProcessorPathIsControlled($destinationPath, false)) {
        return portfolioImageProcessorResult(false, 'NORMALIZATION_FAILED');
    }
    $outputSpec = portfolioImageProcessorOutputSpec($destinationPath, $expectedMime);
    $executable = portfolioImageProcessorExecutable();
    if ($outputSpec === null || !is_file($executable) || !is_executable($executable)) {
        return portfolioImageProcessorResult(false, 'NORMALIZATION_FAILED');
    }
    $lock = acquirePortfolioImageNormalizationLock();
    if ($lock === null) {
        return portfolioImageProcessorResult(false, 'NORMALIZATION_RESOURCE_LIMIT');
    }

    $started = portfolioImageProcessorNowNanoseconds();
    $stdout = '';
    $stderr = '';
    try {
        $command = [$executable, 'thumbnail', $sourcePath, $outputSpec, (string) $maxDimension, '--height', (string) $maxDimension, '--size', 'down', '--auto-rotate'];
        $environment = [
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'LANG' => 'C',
            'VIPS_CONCURRENCY' => '1',
            'VIPS_BLOCK_UNTRUSTED' => '1',
        ];
        $process = @proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, $environment, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            return portfolioImageProcessorResult(false, 'NORMALIZATION_FAILED');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $timedOut = false;
        do {
            $stdout .= substr((string) stream_get_contents($pipes[1]), 0, max(0, 4096 - strlen($stdout)));
            $stderr .= substr((string) stream_get_contents($pipes[2]), 0, max(0, 4096 - strlen($stderr)));
            $status = proc_get_status($process);
            if (($status['running'] ?? false) !== true) break;
            if (portfolioImageProcessorElapsedMilliseconds($started) >= portfolioImageProcessorTimeoutMilliseconds()) {
                $timedOut = true;
                break;
            }
            usleep(10000);
        } while (true);
        $exitCode = closePortfolioImageProcessor($process, $pipes, $timedOut);
        $duration = portfolioImageProcessorElapsedMilliseconds($started);
        if ($timedOut) {
            @unlink($destinationPath);
            return portfolioImageProcessorResult(false, 'NORMALIZATION_TIMEOUT', $duration);
        }
        if ($exitCode !== 0) {
            @unlink($destinationPath);
            return portfolioImageProcessorResult(false, $exitCode === 137 ? 'NORMALIZATION_RESOURCE_LIMIT' : 'NORMALIZATION_FAILED', $duration);
        }
        $derivative = portfolioImageDerivativeIsValid($destinationPath, $expectedMime, $maxDimension, $sourcePath, $sourceDimensions);
        if ($derivative === false) {
            @unlink($destinationPath);
            return portfolioImageProcessorResult(false, 'NORMALIZATION_FAILED', $duration);
        }
        reportPortfolioMediaEvent('media_normalized', $mediaKind, 'derivative', 'success', $expectedMime, $derivative['bytes'], [$derivative['width'], $derivative['height']], $duration);

        return portfolioImageProcessorResult(true, 'success', $duration, $derivative['width'], $derivative['height'], $derivative['bytes']);
    } catch (Throwable) {
        @unlink($destinationPath);
        return portfolioImageProcessorResult(false, 'NORMALIZATION_FAILED', portfolioImageProcessorElapsedMilliseconds($started));
    } finally {
        releasePortfolioImageNormalizationLock($lock);
    }
}

function portfolioImageFailureMessage(string $reason): string
{
    return match ($reason) {
        'NORMALIZATION_TIMEOUT' => 'Image processing took too long. Please try another image.',
        'NORMALIZATION_RESOURCE_LIMIT' => 'This image cannot be processed safely.',
        'IMAGE_UNSAFE_DIMENSIONS' => 'The image dimensions are not supported.',
        default => 'The uploaded image could not be processed.',
    };
}
