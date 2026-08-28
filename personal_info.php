<?php
require_once __DIR__ . '/includes/admin_session.php';
require_once __DIR__ . '/includes/csrf.php';

startAdminSession();
requireAdminAuthentication();

require_once __DIR__ . '/config/database.php';

function escapePersonalInfoHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function uploadProfileImage(array $file, array &$errors): ?string
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
    $filename = 'profile_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $relativePath = 'uploads/profile/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $relativePath)) {
        $errors[] = 'The uploaded image could not be processed.';
        return null;
    }
    return $relativePath;
}

function deleteProfileImage(?string $path): void
{
    $prefix = 'uploads/profile/';
    if ($path !== null && str_starts_with($path, $prefix) && basename($path) === substr($path, strlen($prefix))) {
        $file = __DIR__ . '/' . $path;
        if (is_file($file)) {
            unlink($file);
        }
    }
}

$fields = [
    'full_name', 'professional_title', 'email', 'phone_primary', 'phone_secondary',
    'location', 'about_me', 'work_description', 'linkedin_url', 'github_url',
    'instagram_url', 'facebook_url', 'website_url',
];
$profileFields = array_merge($fields, ['profile_image_path']);
$profile = array_fill_keys($fields, '');
$profile['profile_image_path'] = null;
$errors = [];
$message = '';
$profileCompletion = 0;
$activePage = 'personal_info';

try {
    $database = getDatabaseConnection();
    $current = $database->query('SELECT id, profile_image_path FROM personal_info ORDER BY id ASC LIMIT 1')->fetch();
    $currentImagePath = $current['profile_image_path'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'upload_profile_image') {
            $newImagePath = uploadProfileImage($_FILES['profile_image'] ?? [], $errors);
            if ($errors === [] && $current !== false) {
                $statement = $database->prepare('UPDATE personal_info SET profile_image_path = :path WHERE id = :id');
                $statement->execute(['path' => $newImagePath, 'id' => $current['id']]);
                deleteProfileImage($currentImagePath);
                header('Location: personal_info.php?photo_updated=1');
                exit;
            }
            deleteProfileImage($newImagePath);
        } elseif ($action === 'save_profile') {
            foreach ($fields as $field) {
                $profile[$field] = isset($_POST[$field]) && is_string($_POST[$field]) ? trim($_POST[$field]) : '';
            }
            if ($profile['full_name'] === '') {
                $errors[] = 'Full name is required.';
            }
            if ($profile['email'] !== '' && filter_var($profile['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Please enter a valid email address.';
            }
            foreach (['linkedin_url', 'github_url', 'instagram_url', 'facebook_url', 'website_url'] as $urlField) {
                if ($profile[$urlField] !== '' && filter_var($profile[$urlField], FILTER_VALIDATE_URL) === false) {
                    $errors[] = 'Please enter valid URLs.';
                    break;
                }
            }
            if ($errors === []) {
                $existing = $current;
                $imagePath = $currentImagePath;
                $values = array_combine(
                    array_map(fn ($field) => ':' . $field, $fields),
                    array_map(fn ($field) => $profile[$field], $fields)
                );
                $values[':profile_image_path'] = $imagePath;
                if ($existing === false) {
                    $statement = $database->prepare(
                        'INSERT INTO personal_info (' . implode(', ', $profileFields) . ') VALUES (' . implode(', ', array_keys($values)) . ')'
                    );
                } else {
                    $updates = implode(', ', array_map(fn ($field) => "$field = :$field", $profileFields));
                    $statement = $database->prepare("UPDATE personal_info SET $updates WHERE id = :id");
                    $values[':id'] = $existing['id'];
                }
                $statement->execute($values);
                header('Location: personal_info.php?saved=1');
                exit;
            }
        } elseif ($action === 'remove_profile_image') {
            if ($current !== false && $currentImagePath !== null) {
                $statement = $database->prepare('UPDATE personal_info SET profile_image_path = NULL WHERE id = :id');
                $statement->execute(['id' => $current['id']]);
                deleteProfileImage($currentImagePath);
            }
            header('Location: personal_info.php?photo_removed=1');
            exit;
        } elseif ($action === 'add_skill') {
            $skill = isset($_POST['skill_name']) && is_string($_POST['skill_name']) ? trim($_POST['skill_name']) : '';
            if ($skill === '' || strlen($skill) > 100) {
                $errors[] = 'Skill must be between 1 and 100 characters.';
            } else {
                $statement = $database->prepare('SELECT id FROM skills WHERE skill_name = :skill LIMIT 1');
                $statement->execute(['skill' => $skill]);
                if ($statement->fetch() !== false) {
                    $errors[] = 'That skill already exists.';
                } else {
                    $statement = $database->prepare('INSERT INTO skills (skill_name) VALUES (:skill)');
                    $statement->execute(['skill' => $skill]);
                    header('Location: personal_info.php?skill_added=1');
                    exit;
                }
            }
        } elseif ($action === 'delete_skill') {
            $skillId = filter_var($_POST['skill_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($skillId === false) {
                $errors[] = 'Please provide a valid skill.';
            } else {
                $statement = $database->prepare('DELETE FROM skills WHERE id = :id');
                $statement->execute(['id' => $skillId]);
                header('Location: personal_info.php?skill_deleted=1');
                exit;
            }
        }
    } else {
        if (isset($_GET['saved'])) $message = 'Personal information saved successfully.';
        if (isset($_GET['photo_updated'])) $message = 'Profile photo updated.';
        if (isset($_GET['photo_removed'])) $message = 'Profile photo removed.';
        if (isset($_GET['skill_added'])) $message = 'Skill added successfully.';
        if (isset($_GET['skill_deleted'])) $message = 'Skill removed.';
    }
    $statement = $database->query('SELECT ' . implode(', ', $profileFields) . ' FROM personal_info ORDER BY id ASC LIMIT 1');
    $savedProfile = $statement->fetch();
    if ($savedProfile !== false && ($_SERVER['REQUEST_METHOD'] !== 'POST' || (($_POST['action'] ?? '') !== 'save_profile'))) {
        $profile = array_merge($profile, $savedProfile);
    }
    $skills = $database->query('SELECT id, skill_name FROM skills ORDER BY created_at ASC, id ASC')->fetchAll();
    $completionFields = ['full_name', 'professional_title', 'email', 'phone_primary', 'about_me', 'work_description', 'location', 'linkedin_url', 'github_url', 'profile_image_path'];
    $profileCompletion = (int) round((count(array_filter($completionFields, static fn ($field) => !empty($profile[$field]))) + (count($skills) > 0 ? 1 : 0)) / (count($completionFields) + 1) * 100);
} catch (PDOException $exception) {
    $errors[] = 'Personal information is temporarily unavailable.';
    $skills = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Info - My Portfolio</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js" defer></script>
</head>
<body>
<div class="admin-layout">
    <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
    <main class="admin-content">
        <section>
            <div class="admin-page-header">
                <div class="admin-page-header-copy">
                    <p class="admin-eyebrow">Profile</p>
                    <h1 class="admin-page-title">Personal Info</h1>
                    <p class="admin-page-description">Manage the information displayed on your public portfolio.</p>
                </div>
                <div class="admin-page-header-actions">
                    <a class="button-primary" href="index.php" target="_blank" rel="noopener">Preview Portfolio ↗</a>
                </div>
            </div>
            <?php if ($message !== ''): ?><p class="status-message" data-toast role="status"><?php echo escapePersonalInfoHtml($message); ?></p><?php endif; ?>
            <?php if ($errors !== []): ?><ul class="status-message error" role="alert"><?php foreach ($errors as $error): ?><li><?php echo escapePersonalInfoHtml($error); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <div class="profile-summary">
                <div class="profile-summary-avatar">
                    <?php if (!empty($profile['profile_image_path'])): ?>
                        <img src="<?php echo escapePersonalInfoHtml($profile['profile_image_path']); ?>" alt="<?php echo escapePersonalInfoHtml($profile['full_name']); ?>">
                    <?php else: ?>
                        <?php echo escapePersonalInfoHtml(strtoupper(substr($profile['full_name'] ?: 'P', 0, 1))); ?>
                    <?php endif; ?>
                </div>
                <div><strong><?php echo escapePersonalInfoHtml($profile['full_name'] ?: 'Your name'); ?></strong><span><?php echo escapePersonalInfoHtml($profile['professional_title'] ?: 'Add a professional title'); ?></span></div>
                <div class="completion"><div class="completion-header"><span>Profile completion</span><strong><?php echo $profileCompletion; ?>%</strong></div><progress value="<?php echo $profileCompletion; ?>" max="100"><?php echo $profileCompletion; ?>%</progress></div>
            </div>
            <div class="profile-photo-card">
                <div class="profile-photo-preview">
                    <?php if (!empty($profile['profile_image_path'])): ?>
                        <img id="profile-photo-preview" data-saved-src="<?php echo escapePersonalInfoHtml($profile['profile_image_path']); ?>" src="<?php echo escapePersonalInfoHtml($profile['profile_image_path']); ?>" alt="<?php echo escapePersonalInfoHtml($profile['full_name']); ?>">
                    <?php else: ?>
                        <?php $photoInitials = strtoupper(substr($profile['full_name'], 0, 1) . (strrpos($profile['full_name'], ' ') !== false ? substr($profile['full_name'], strrpos($profile['full_name'], ' ') + 1, 1) : '')); ?>
                        <span data-profile-initials><?php echo escapePersonalInfoHtml($photoInitials); ?></span><img id="profile-photo-preview" alt="" hidden>
                    <?php endif; ?>
                </div>
                <div class="profile-photo-controls"><h2>Profile Photo</h2><p class="photo-intro">Update the photo shown on your public portfolio.</p><p class="photo-requirements">JPG or PNG<br>Minimum 400 × 400 px<br>Maximum 8 MB</p>
                    <form id="profile-photo-form" class="profile-photo-form" method="POST" action="personal_info.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_profile_image">
                        <input type="hidden" name="csrf_token" value="<?php echo escapePersonalInfoHtml(getCsrfToken()); ?>">
                        <input id="profile_image" class="visually-hidden" data-image-preview="#profile-photo-preview" type="file" name="profile_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png" aria-describedby="photo-selection-status">
                        <div class="photo-action-row"><button class="button-secondary photo-picker" type="button" data-file-trigger="profile_image"><?php echo !empty($profile['profile_image_path']) ? 'Change Photo' : 'Choose Photo'; ?></button><button class="button-primary photo-save" type="submit" data-photo-submit disabled>Save Photo</button></div>
                        <span id="photo-selection-status" class="photo-selection-status" aria-live="polite"></span>
                    </form>
                    <?php if (!empty($profile['profile_image_path'])): ?><form class="profile-photo-remove-form" method="POST" action="personal_info.php" data-confirm-title="Remove profile photo?" data-confirm="Your current profile photo will be removed." data-confirm-action="Remove Photo"><input type="hidden" name="action" value="remove_profile_image"><input type="hidden" name="csrf_token" value="<?php echo escapePersonalInfoHtml(getCsrfToken()); ?>"><button class="button-danger photo-remove" type="submit">Remove Photo</button></form><?php endif; ?>
                </div>
            </div>
            <form id="profile-form" class="profile-form" method="POST" action="personal_info.php">
                <input type="hidden" name="action" value="save_profile">
                <input type="hidden" name="csrf_token" value="<?php echo escapePersonalInfoHtml(getCsrfToken()); ?>">
                <div class="form-grid">
                    <h2 class="form-section-title">Basic Information</h2>
                    <?php foreach ([
                        'full_name' => 'Full Name', 'professional_title' => 'Professional Title',
                        'email' => 'Email', 'phone_primary' => 'Primary Phone',
                        'phone_secondary' => 'Secondary Phone', 'location' => 'Location',
                    ] as $field => $label): ?>
                        <label class="form-field" for="<?php echo $field; ?>"><span><?php echo $label; ?></span><input type="<?php echo $field === 'email' ? 'email' : 'text'; ?>" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo escapePersonalInfoHtml($profile[$field]); ?>" <?php echo $field === 'full_name' ? 'required' : ''; ?>></label>
                    <?php endforeach; ?>
                    <h2 class="form-section-title">About Me</h2>
                    <label class="form-field form-field-full" for="about_me"><span>About Me</span><textarea id="about_me" name="about_me"><?php echo escapePersonalInfoHtml($profile['about_me']); ?></textarea></label>
                    <label class="form-field form-field-full" for="work_description"><span>Work / Professional Description</span><textarea id="work_description" name="work_description"><?php echo escapePersonalInfoHtml($profile['work_description']); ?></textarea></label>
                    <h2 class="form-section-title">Social Accounts</h2>
                    <?php foreach (['linkedin_url' => 'LinkedIn URL', 'github_url' => 'GitHub URL', 'instagram_url' => 'Instagram URL', 'facebook_url' => 'Facebook URL', 'website_url' => 'Personal Website URL'] as $field => $label): ?>
                        <label class="form-field" for="<?php echo $field; ?>"><span><?php echo $label; ?></span><input type="url" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo escapePersonalInfoHtml($profile[$field]); ?>"></label>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions"><span class="form-hint">Changes are saved to your public profile.</span><button class="button-primary" type="submit">Save Changes</button></div>
            </form>
            <div class="section-heading-row" id="skills"><h2>Skills</h2><span class="muted"><?php echo count($skills); ?> added</span></div>
            <?php if ($skills === []): ?><div class="empty-state admin-empty"><strong>No skills added yet</strong><span>Add your first skill to help visitors understand your strengths.</span></div><?php endif; ?>
            <div class="skills-chips"><?php foreach ($skills as $skill): ?>
                <div class="skill-row" data-skill-name="<?php echo escapePersonalInfoHtml($skill['skill_name']); ?>">
                    <span><?php echo escapePersonalInfoHtml($skill['skill_name']); ?></span>
                    <form method="POST" action="personal_info.php">
                        <input type="hidden" name="action" value="delete_skill">
                        <input type="hidden" name="csrf_token" value="<?php echo escapePersonalInfoHtml(getCsrfToken()); ?>">
                        <input type="hidden" name="skill_id" value="<?php echo (int) $skill['id']; ?>">
                        <button type="submit" class="button-danger" data-confirm="Remove this skill?">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?></div>
            <form class="skill-add-form" method="POST" action="personal_info.php">
                <input type="hidden" name="action" value="add_skill">
                <input type="hidden" name="csrf_token" value="<?php echo escapePersonalInfoHtml(getCsrfToken()); ?>">
                <label class="form-field" for="skill_name"><span>Skill Name</span><input type="text" id="skill_name" name="skill_name" maxlength="100" required><span id="skill-feedback" class="field-feedback" aria-live="polite"></span></label>
                <button class="button-primary" type="submit">Add Skill</button>
            </form>
        </section>
    </main>
</div>
</body>
</html>
