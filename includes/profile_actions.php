<?php

require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/validation.php';

const PROFILE_IMAGE_PREFIX = 'uploads/profile/';

function storeValidatedProfileImage(array $file, array &$errors): ?string
{
    if (($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE || (($file['size'] ?? 0) > 8 * 1024 * 1024)) {
        $errors[] = 'Profile photo must be 8 MB or smaller.';
        return null;
    }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !isset($file['tmp_name'])) {
        $errors[] = 'The uploaded image could not be processed.';
        return null;
    }

    $info = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($info, $file['tmp_name']);
    finfo_close($info);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $dimensions = @getimagesize($file['tmp_name']);
    if (!isset($extensions[$mime])) {
        $errors[] = 'Please upload a JPG or PNG image.';
        return null;
    }
    if ($dimensions === false || $dimensions[0] < 400 || $dimensions[1] < 400) {
        $errors[] = 'Profile photo must be at least 400 × 400 pixels.';
        return null;
    }

    $filename = createManagedUploadFilename('profile', $extensions[$mime]);
    $relativePath = storeManagedUpload($file, PROFILE_IMAGE_PREFIX, $filename);
    if ($relativePath === null) {
        $errors[] = 'The uploaded image could not be processed.';
    }

    return $relativePath;
}

function cleanProfileImage(?string $imagePath, string $action): void
{
    if ($imagePath === null || $imagePath === '') {
        return;
    }

    if (resolveManagedStoragePath($imagePath, PROFILE_IMAGE_PREFIX) === null) {
        reportApplicationError(new RuntimeException('Managed profile path rejected.'), 'personal_info.php', $action . '_path_rejected');
        return;
    }

    if (!deleteManagedFile($imagePath, PROFILE_IMAGE_PREFIX)) {
        reportApplicationError(new RuntimeException('Profile image cleanup failed.'), 'personal_info.php', $action . '_cleanup_failed');
    }
}

function profileActionResult(array $errors, array $profile, ?string $redirect = null): array
{
    return ['errors' => $errors, 'profile' => $profile, 'redirect' => $redirect];
}

function handleProfileAction(PDO $database, array $post, array $files, $current, ?string $currentImagePath, array $fields, array $profile): array
{
    $action = isset($post['action']) && is_string($post['action']) ? $post['action'] : '';
    $errors = [];

    try {
        if ($action === 'upload_profile_image') {
            if ($current === false) {
                return profileActionResult(['Save your personal information before uploading a photo.'], $profile);
            }

            $newImagePath = storeValidatedProfileImage($files['profile_image'] ?? [], $errors);
            if ($errors !== []) {
                return profileActionResult($errors, $profile);
            }

            $statement = $database->prepare('UPDATE personal_info SET profile_image_path = :path WHERE id = :id');
            $statement->execute(['path' => $newImagePath, 'id' => $current['id']]);
            if ($statement->rowCount() !== 1) {
                cleanProfileImage($newImagePath, 'profile_update_compensation');
                return profileActionResult(['The profile photo could not be updated.'], $profile);
            }

            cleanProfileImage($currentImagePath, 'profile_update_old_image');
            return profileActionResult([], $profile, 'personal_info.php?photo_updated=1');
        }

        if ($action === 'save_profile') {
            foreach ($fields as $field) {
                $profile[$field] = isset($post[$field]) && is_string($post[$field]) ? trim($post[$field]) : '';
            }
            if ($profile['full_name'] === '') {
                $errors[] = 'Full name is required.';
            }
            if ($profile['email'] !== '' && filter_var($profile['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Please enter a valid email address.';
            }
            foreach (['linkedin_url', 'github_url', 'instagram_url', 'facebook_url', 'website_url'] as $urlField) {
                if ($profile[$urlField] !== '' && !isSafeHttpUrl($profile[$urlField])) {
                    $errors[] = 'Please enter valid URLs.';
                    break;
                }
            }
            if ($errors !== []) {
                return profileActionResult($errors, $profile);
            }

            $profileFields = array_merge($fields, ['profile_image_path']);
            $values = array_combine(
                array_map(fn ($field) => ':' . $field, $fields),
                array_map(fn ($field) => $profile[$field], $fields)
            );
            $values[':profile_image_path'] = $currentImagePath;
            if ($current === false) {
                $statement = $database->prepare('INSERT INTO personal_info (' . implode(', ', $profileFields) . ') VALUES (' . implode(', ', array_keys($values)) . ')');
            } else {
                $updates = implode(', ', array_map(fn ($field) => "$field = :$field", $profileFields));
                $statement = $database->prepare("UPDATE personal_info SET $updates WHERE id = :id");
                $values[':id'] = $current['id'];
            }
            $statement->execute($values);
            return profileActionResult([], $profile, 'personal_info.php?saved=1');
        }

        if ($action === 'remove_profile_image') {
            if ($current === false || $currentImagePath === null) {
                return profileActionResult(['Profile photo not found.'], $profile);
            }

            $statement = $database->prepare('UPDATE personal_info SET profile_image_path = NULL WHERE id = :id AND profile_image_path = :path');
            $statement->execute(['id' => $current['id'], 'path' => $currentImagePath]);
            if ($statement->rowCount() !== 1) {
                return profileActionResult(['Profile photo not found.'], $profile);
            }

            cleanProfileImage($currentImagePath, 'profile_remove_old_image');
            return profileActionResult([], $profile, 'personal_info.php?photo_removed=1');
        }

        if ($action === 'add_skill') {
            $skill = isset($post['skill_name']) && is_string($post['skill_name']) ? trim($post['skill_name']) : '';
            if ($skill === '' || strlen($skill) > 100) {
                return profileActionResult(['Skill must be between 1 and 100 characters.'], $profile);
            }

            $statement = $database->prepare('SELECT id FROM skills WHERE skill_name = :skill LIMIT 1');
            $statement->execute(['skill' => $skill]);
            if ($statement->fetch() !== false) {
                return profileActionResult(['That skill already exists.'], $profile);
            }

            $statement = $database->prepare('INSERT INTO skills (skill_name) VALUES (:skill)');
            $statement->execute(['skill' => $skill]);
            return profileActionResult([], $profile, 'personal_info.php?skill_added=1');
        }

        if ($action === 'delete_skill') {
            $skillId = filter_var($post['skill_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($skillId === false) {
                return profileActionResult(['Please provide a valid skill.'], $profile);
            }

            $statement = $database->prepare('DELETE FROM skills WHERE id = :id');
            $statement->execute(['id' => $skillId]);
            if ($statement->rowCount() !== 1) {
                return profileActionResult(['Skill not found.'], $profile);
            }

            return profileActionResult([], $profile, 'personal_info.php?skill_deleted=1');
        }

        return profileActionResult([], $profile);
    } catch (PDOException $exception) {
        reportApplicationError($exception, 'personal_info.php', 'profile_' . ($action === '' ? 'unknown' : $action));
        if (isset($newImagePath)) {
            cleanProfileImage($newImagePath, 'profile_database_compensation');
        }
        return profileActionResult(['The requested change could not be saved.'], $profile);
    }
}
