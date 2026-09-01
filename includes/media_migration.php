<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_presentation.php';
require_once __DIR__ . '/storage.php';

function legacyMediaSource(string $sourceRoot, mixed $relativePath, string $prefix): ?string
{
    if (!is_string($relativePath) || preg_match('#^' . preg_quote($prefix, '#') . '[A-Za-z0-9][A-Za-z0-9._-]{0,127}$#', $relativePath) !== 1) {
        return null;
    }
    $root = realpath($sourceRoot);
    if ($root === false || !is_dir($root)) return null;
    $path = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

    return $path !== false && filesystemPathIsWithin($path, $root) && is_file($path) ? $path : null;
}

/** @return array{profiles: int, projects: int} */
function migrateLegacyMedia(PDO $database, string $sourceRoot): array
{
    requirePrivateStorageRoot(true);
    $migrated = ['profiles' => 0, 'projects' => 0];

    $profiles = $database->query("SELECT id, portfolio_id, profile_image_path FROM personal_info WHERE profile_image_path LIKE 'uploads/profile/%' AND profile_image_path NOT LIKE 'uploads/profile/derived/%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($profiles as $profile) {
        $portfolioId = (int) $profile['portfolio_id'];
        $oldKey = (string) $profile['profile_image_path'];
        $source = legacyMediaSource($sourceRoot, $oldKey, 'uploads/profile/');
        $mime = $source === null ? null : @mime_content_type($source);
        $extension = $mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : null);
        if ($extension === null || @getimagesize($source) === false) throw new RuntimeException('Legacy profile media is invalid.');
        $newKey = copyFileToPrivateMedia($source, $portfolioId, 'profile_original', createManagedUploadFilename('profile', $extension));
        $presentationKey = $newKey === null ? null : generateProfilePresentationImage($newKey, $portfolioId);
        if ($newKey === null || $presentationKey === null) {
            if ($newKey !== null) deletePrivateMediaFile($newKey, $portfolioId, 'profile_original');
            throw new RuntimeException('Legacy profile media could not be staged.');
        }
        try {
            $update = $database->prepare('UPDATE personal_info SET profile_image_path = :new_key WHERE id = :id AND portfolio_id = :portfolio_id AND profile_image_path = :old_key');
            $update->execute(['new_key' => $newKey, 'id' => $profile['id'], 'portfolio_id' => $portfolioId, 'old_key' => $oldKey]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Legacy profile database reference changed during migration.');
        } catch (Throwable $exception) {
            deleteProfilePresentationImage($newKey, $portfolioId);
            deletePrivateMediaFile($newKey, $portfolioId, 'profile_original');
            throw $exception;
        }
        $migrated['profiles']++;
    }

    $projects = $database->query("SELECT id, portfolio_id, image_path FROM projects WHERE image_path LIKE 'uploads/projects/%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($projects as $project) {
        $portfolioId = (int) $project['portfolio_id'];
        $oldKey = (string) $project['image_path'];
        $source = legacyMediaSource($sourceRoot, $oldKey, 'uploads/projects/');
        $mime = $source === null ? null : @mime_content_type($source);
        $extension = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null };
        if ($extension === null || @getimagesize($source) === false) throw new RuntimeException('Legacy project media is invalid.');
        $newKey = copyFileToPrivateMedia($source, $portfolioId, 'projects', createManagedUploadFilename('project', $extension));
        if ($newKey === null) throw new RuntimeException('Legacy project media could not be staged.');
        try {
            $update = $database->prepare('UPDATE projects SET image_path = :new_key WHERE id = :id AND portfolio_id = :portfolio_id AND image_path = :old_key');
            $update->execute(['new_key' => $newKey, 'id' => $project['id'], 'portfolio_id' => $portfolioId, 'old_key' => $oldKey]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Legacy project database reference changed during migration.');
        } catch (Throwable $exception) {
            deletePrivateMediaFile($newKey, $portfolioId, 'projects');
            throw $exception;
        }
        $migrated['projects']++;
    }

    return $migrated;
}
