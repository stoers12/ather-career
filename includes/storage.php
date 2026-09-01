<?php

declare(strict_types=1);

final class PrivateStorageConfigurationException extends RuntimeException
{
}

const PRIVATE_MEDIA_COLLECTIONS = [
    'profile_original' => ['profile', 'original'],
    'profile_presentation' => ['profile', 'presentation'],
    'projects' => ['projects'],
];

function isAbsoluteFilesystemPath(string $path): bool
{
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

function normalizedFilesystemPath(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

function filesystemPathIsWithin(string $path, string $parent): bool
{
    $path = normalizedFilesystemPath($path);
    $parent = normalizedFilesystemPath($parent);

    return $path === $parent || str_starts_with($path . '/', $parent . '/');
}

function requirePrivateStorageRoot(bool $requireWritable = false): string
{
    $configured = getenv('ATHERCAR_STORAGE_ROOT');
    if (!is_string($configured) || $configured === '' || !isAbsoluteFilesystemPath($configured)) {
        throw new PrivateStorageConfigurationException('Private media storage is not configured.');
    }

    $root = realpath($configured);
    if ($root === false || !is_dir($root)) {
        throw new PrivateStorageConfigurationException('Private media storage is unavailable.');
    }

    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? getenv('DOCUMENT_ROOT');
    foreach (array_filter([$documentRoot, '/var/www/public']) as $publicRoot) {
        $resolvedPublicRoot = realpath((string) $publicRoot);
        if ($resolvedPublicRoot !== false && filesystemPathIsWithin($root, $resolvedPublicRoot)) {
            throw new PrivateStorageConfigurationException('Private media storage must be outside the public document root.');
        }
    }

    if (!is_readable($root) || ($requireWritable && !is_writable($root))) {
        throw new PrivateStorageConfigurationException('Private media storage permissions are insufficient.');
    }

    return $root;
}

/** @return array{portfolio_id: int, collection: string, filename: string}|null */
function parseManagedMediaKey(mixed $candidate): ?array
{
    if (!is_string($candidate) || $candidate === '' || strlen($candidate) > 240 || str_contains($candidate, '\\') || str_contains($candidate, '%')) {
        return null;
    }
    if (preg_match('#^portfolios/([1-9][0-9]{0,9})/(profile/original|profile/presentation|projects)/([a-z0-9][a-z0-9._-]{0,127})$#', $candidate, $matches) !== 1) {
        return null;
    }

    $collection = match ($matches[2]) {
        'profile/original' => 'profile_original',
        'profile/presentation' => 'profile_presentation',
        'projects' => 'projects',
    };

    return ['portfolio_id' => (int) $matches[1], 'collection' => $collection, 'filename' => $matches[3]];
}

function managedMediaKey(int $portfolioId, string $collection, string $filename): ?string
{
    if ($portfolioId < 1 || !isset(PRIVATE_MEDIA_COLLECTIONS[$collection]) || preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $filename) !== 1) {
        return null;
    }

    return 'portfolios/' . $portfolioId . '/' . implode('/', PRIVATE_MEDIA_COLLECTIONS[$collection]) . '/' . $filename;
}

function resolvePrivateMediaPath(mixed $key, int $portfolioId, string $collection): ?string
{
    $parts = parseManagedMediaKey($key);
    if ($parts === null || $parts['portfolio_id'] !== $portfolioId || $parts['collection'] !== $collection) {
        return null;
    }

    return requirePrivateStorageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $key);
}

function ensurePrivateMediaDirectory(int $portfolioId, string $collection): ?string
{
    $key = managedMediaKey($portfolioId, $collection, 'probe');
    if ($key === null) {
        return null;
    }

    $root = requirePrivateStorageRoot(true);
    $directory = dirname($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key));
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        return null;
    }

    return is_writable($directory) ? $directory : null;
}

function createManagedUploadFilename(string $prefix, string $extension): string
{
    if (preg_match('/^[a-z0-9]{1,24}$/', $prefix) !== 1 || preg_match('/^[a-z0-9]{2,5}$/', $extension) !== 1) {
        throw new InvalidArgumentException('Managed media filename components are invalid.');
    }

    return $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
}

function storePrivateUploadedImage(array $file, int $portfolioId, string $collection, string $prefix, string $extension, string $expectedMime): ?string
{
    if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    $directory = ensurePrivateMediaDirectory($portfolioId, $collection);
    $filename = createManagedUploadFilename($prefix, $extension);
    $key = managedMediaKey($portfolioId, $collection, $filename);
    if ($directory === null || $key === null) {
        return null;
    }

    $staged = $directory . DIRECTORY_SEPARATOR . '.stage-' . bin2hex(random_bytes(12));
    $final = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $staged)) {
        return null;
    }
    $mime = @mime_content_type($staged);
    $dimensions = @getimagesize($staged);
    if ($mime !== $expectedMime || $dimensions === false || file_exists($final) || !@rename($staged, $final)) {
        if (is_file($staged)) @unlink($staged);
        return null;
    }
    @chmod($final, 0600);

    return $key;
}

function copyFileToPrivateMedia(string $source, int $portfolioId, string $collection, string $filename): ?string
{
    if (!is_file($source) || !is_readable($source)) {
        return null;
    }
    $directory = ensurePrivateMediaDirectory($portfolioId, $collection);
    $key = managedMediaKey($portfolioId, $collection, $filename);
    if ($directory === null || $key === null) {
        return null;
    }

    $staged = $directory . DIRECTORY_SEPARATOR . '.stage-' . bin2hex(random_bytes(12));
    $final = $directory . DIRECTORY_SEPARATOR . $filename;
    $sourceHash = hash_file('sha256', $source);
    if ($sourceHash === false || !@copy($source, $staged)) {
        return null;
    }
    $stagedHash = hash_file('sha256', $staged);
    if (!hash_equals($sourceHash, (string) $stagedHash) || @getimagesize($staged) === false || file_exists($final) || !@rename($staged, $final)) {
        if (is_file($staged)) @unlink($staged);
        return null;
    }
    @chmod($final, 0600);

    return $key;
}

function deletePrivateMediaFile(mixed $key, int $portfolioId, string $collection): bool
{
    $path = resolvePrivateMediaPath($key, $portfolioId, $collection);
    if ($path === null) {
        return false;
    }

    return !file_exists($path) || (is_file($path) && @unlink($path));
}

function privateMediaDescriptor(mixed $key, int $portfolioId, string $collection): ?array
{
    $path = resolvePrivateMediaPath($key, $portfolioId, $collection);
    if ($path === null || !is_file($path) || !is_readable($path)) {
        return null;
    }
    $mime = @mime_content_type($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || @getimagesize($path) === false) {
        return null;
    }

    return ['path' => $path, 'mime' => $mime, 'size' => filesize($path)];
}

function streamPrivateMedia(array $descriptor): never
{
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $descriptor['mime']);
    header('Content-Disposition: inline');
    if (is_int($descriptor['size']) && $descriptor['size'] >= 0) {
        header('Content-Length: ' . $descriptor['size']);
    }
    readfile($descriptor['path']);
    exit;
}
