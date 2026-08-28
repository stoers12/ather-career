<?php

/**
 * Resolves a database-relative managed file path inside one approved upload root.
 * Paths outside that root, paths with directory traversal, and absolute paths are
 * rejected before any filesystem operation is attempted.
 */
function resolveManagedStoragePath(string $relativePath, string $managedPrefix): ?string
{
    if (!str_ends_with($managedPrefix, '/') || !str_starts_with($relativePath, $managedPrefix)) {
        return null;
    }

    $filename = substr($relativePath, strlen($managedPrefix));
    if ($filename === '' || $filename === '.' || $filename === '..' || str_contains($filename, '\\') || basename($relativePath) !== $filename) {
        return null;
    }

    $projectRoot = realpath(__DIR__ . '/..');
    $managedDirectory = $projectRoot === false ? false : realpath($projectRoot . '/' . rtrim($managedPrefix, '/'));
    if ($managedDirectory === false) {
        return null;
    }

    return $managedDirectory . DIRECTORY_SEPARATOR . $filename;
}

function createManagedUploadFilename(string $prefix, string $extension): string
{
    return $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
}

/**
 * Stores an already-validated PHP upload without changing its bytes or metadata.
 */
function storeManagedUpload(array $file, string $managedPrefix, string $filename): ?string
{
    $relativePath = $managedPrefix . $filename;
    $destination = resolveManagedStoragePath($relativePath, $managedPrefix);
    if ($destination === null || file_exists($destination) || !isset($file['tmp_name'])) {
        return null;
    }

    return move_uploaded_file($file['tmp_name'], $destination) ? $relativePath : null;
}

/**
 * Deletes only a managed file. A missing file is already clean; a rejected path or
 * failed unlink is reported to the caller as false so it can be logged safely.
 */
function deleteManagedFile(string $relativePath, string $managedPrefix): bool
{
    $path = resolveManagedStoragePath($relativePath, $managedPrefix);
    if ($path === null) {
        return false;
    }

    if (!file_exists($path)) {
        return true;
    }

    return is_file($path) && @unlink($path);
}
