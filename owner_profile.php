<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/owner_actions.php';
require_once __DIR__ . '/includes/portfolio_scoped_data.php';
require_once __DIR__ . '/includes/owner_layout.php';

startOwnerSession();

$fields = [
    'full_name', 'professional_title', 'email', 'phone_primary', 'phone_secondary',
    'location', 'about_me', 'work_description', 'linkedin_url', 'github_url',
    'instagram_url', 'facebook_url', 'website_url',
];
$profile = array_fill_keys($fields, '');
$profile['profile_image_path'] = null;
$skills = [];
$errors = [];
$message = '';
$profileCompletion = 0;

try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $current = loadAuthorizedPersonalInfo($database, $context);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $result = handleAuthorizedProfileAction($database, $context, $_POST, $_FILES, $current, $fields, $profile);
        $errors = $result['errors'];
        $profile = $result['profile'];
        if ($result['redirect'] !== null) {
            header('Location: ' . $result['redirect'], true, 303);
            exit;
        }
    } else {
        if (isset($_GET['saved'])) $message = 'Personal information saved successfully.';
        if (isset($_GET['photo_updated'])) $message = 'Profile photo updated.';
        if (isset($_GET['photo_removed'])) $message = 'Profile photo removed.';
        if (isset($_GET['skill_added'])) $message = 'Skill added successfully.';
        if (isset($_GET['skill_updated'])) $message = 'Skill updated successfully.';
        if (isset($_GET['skill_deleted'])) $message = 'Skill removed.';
    }

    $savedProfile = loadAuthorizedPersonalInfo($database, $context);
    if (is_array($savedProfile) && ($_SERVER['REQUEST_METHOD'] !== 'POST' || (($_POST['action'] ?? '') !== 'save_profile'))) {
        $profile = array_merge($profile, $savedProfile);
    }
    $profileId = is_array($savedProfile) ? (int) $savedProfile['id'] : null;
    $skills = listAuthorizedSkills($database, $context);
    $completionFields = ['full_name', 'professional_title', 'email', 'phone_primary', 'about_me', 'work_description', 'location', 'linkedin_url', 'github_url', 'profile_image_path'];
    $profileCompletion = (int) round((count(array_filter($completionFields, static fn (string $field): bool => !empty($profile[$field]))) + (count($skills) > 0 ? 1 : 0)) / (count($completionFields) + 1) * 100);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_profile.php', 'owner_profile_request');
    http_response_code(503);
    $errors[] = 'Personal information is temporarily unavailable.';
    $profileId = null;
}

ownerLayoutStart('Personal Info', 'profile');
?>
<div class="admin-page-header">
    <div class="admin-page-header-copy"><p class="admin-eyebrow">Profile</p><h1 class="admin-page-title">Personal Info</h1><p class="admin-page-description">Manage your private Portfolio information.</p></div>
    <div class="admin-page-header-actions"><a class="button-primary" href="owner_preview.php">Private Preview</a></div>
</div>
<?php if ($message !== ''): ?><p class="status-message" role="status"><?php echo ownerEscapeHtml($message); ?></p><?php endif; ?>
<?php if ($errors !== []): ?><ul class="status-message error" role="alert"><?php foreach ($errors as $error): ?><li><?php echo ownerEscapeHtml($error); ?></li><?php endforeach; ?></ul><?php endif; ?>
<div class="profile-summary"><div><strong><?php echo ownerEscapeHtml($profile['full_name'] ?: 'Your name'); ?></strong><span><?php echo ownerEscapeHtml($profile['professional_title'] ?: 'Add a professional title'); ?></span></div><div class="completion"><div class="completion-header"><span>Profile completion</span><strong><?php echo $profileCompletion; ?>%</strong></div><progress value="<?php echo $profileCompletion; ?>" max="100"><?php echo $profileCompletion; ?>%</progress></div></div>

<?php if ($profileId !== null): ?>
<div class="profile-photo-card">
    <div class="profile-photo-preview"><?php if (!empty($profile['profile_image_path'])): ?><img src="owner_media.php?type=profile" alt="<?php echo ownerEscapeHtml($profile['full_name']); ?>"><?php else: ?><span><?php echo ownerEscapeHtml(profileInitials($profile['full_name'] ?: 'P')); ?></span><?php endif; ?></div>
    <div class="profile-photo-controls"><h2>Profile Photo</h2><p class="photo-intro">Update the photo shown in your private preview.</p>
        <form method="POST" action="owner_profile.php" enctype="multipart/form-data"><input type="hidden" name="action" value="upload_profile_image"><input type="hidden" name="profile_id" value="<?php echo $profileId; ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><label class="form-field" for="profile_image"><span>JPG or PNG, at least 400 × 400 px, maximum 8 MB</span><input id="profile_image" type="file" name="profile_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required></label><button class="button-primary" type="submit">Save Photo</button></form>
        <?php if (!empty($profile['profile_image_path'])): ?><form method="POST" action="owner_profile.php"><input type="hidden" name="action" value="remove_profile_image"><input type="hidden" name="profile_id" value="<?php echo $profileId; ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><button class="button-danger" type="submit">Remove Photo</button></form><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<form class="profile-form" method="POST" action="owner_profile.php">
    <input type="hidden" name="action" value="save_profile"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><?php if ($profileId !== null): ?><input type="hidden" name="profile_id" value="<?php echo $profileId; ?>"><?php endif; ?>
    <div class="form-grid">
        <h2 class="form-section-title">Basic Information</h2>
        <?php foreach (['full_name' => 'Full Name', 'professional_title' => 'Professional Title', 'email' => 'Email', 'phone_primary' => 'Primary Phone', 'phone_secondary' => 'Secondary Phone', 'location' => 'Location'] as $field => $label): ?>
            <label class="form-field" for="<?php echo $field; ?>"><span><?php echo $label; ?></span><input type="<?php echo $field === 'email' ? 'email' : 'text'; ?>" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo ownerEscapeHtml((string) $profile[$field]); ?>" maxlength="<?php echo PERSONAL_INFO_FIELD_MAX_LENGTHS[$field]; ?>"<?php echo $field === 'full_name' ? ' required' : ''; ?>></label>
        <?php endforeach; ?>
        <h2 class="form-section-title">About Me</h2>
        <label class="form-field form-field-full" for="about_me"><span>About Me</span><textarea id="about_me" name="about_me"><?php echo ownerEscapeHtml((string) $profile['about_me']); ?></textarea></label>
        <label class="form-field form-field-full" for="work_description"><span>Work / Professional Description</span><textarea id="work_description" name="work_description"><?php echo ownerEscapeHtml((string) $profile['work_description']); ?></textarea></label>
        <h2 class="form-section-title">Social Accounts</h2>
        <?php foreach (['linkedin_url' => 'LinkedIn URL', 'github_url' => 'GitHub URL', 'instagram_url' => 'Instagram URL', 'facebook_url' => 'Facebook URL', 'website_url' => 'Personal Website URL'] as $field => $label): ?>
            <label class="form-field" for="<?php echo $field; ?>"><span><?php echo $label; ?></span><input type="url" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo ownerEscapeHtml((string) $profile[$field]); ?>" maxlength="<?php echo PERSONAL_INFO_FIELD_MAX_LENGTHS[$field]; ?>"></label>
        <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="button-primary" type="submit">Save Changes</button></div>
</form>

<div class="section-heading-row" id="skills"><h2>Skills</h2><span class="muted"><?php echo count($skills); ?> added</span></div>
<?php if ($skills === []): ?><div class="empty-state admin-empty"><strong>No skills added yet</strong><span>Add your first skill to help visitors understand your strengths.</span></div><?php endif; ?>
<div class="skills-chips"><?php foreach ($skills as $skill): ?><div class="skill-row"><form method="POST" action="owner_profile.php"><input type="hidden" name="action" value="update_skill"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="skill_id" value="<?php echo (int) $skill['id']; ?>"><label class="visually-hidden" for="skill_<?php echo (int) $skill['id']; ?>">Skill name</label><input id="skill_<?php echo (int) $skill['id']; ?>" type="text" name="skill_name" value="<?php echo ownerEscapeHtml((string) $skill['skill_name']); ?>" maxlength="<?php echo SKILL_NAME_MAX_LENGTH; ?>" required><button type="submit" class="button-secondary">Save</button></form><form method="POST" action="owner_profile.php"><input type="hidden" name="action" value="delete_skill"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="skill_id" value="<?php echo (int) $skill['id']; ?>"><button type="submit" class="button-danger">Remove</button></form></div><?php endforeach; ?></div>
<form class="skill-add-form" method="POST" action="owner_profile.php"><input type="hidden" name="action" value="add_skill"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><label class="form-field" for="skill_name"><span>Skill Name</span><input type="text" id="skill_name" name="skill_name" maxlength="<?php echo SKILL_NAME_MAX_LENGTH; ?>" required></label><button class="button-primary" type="submit">Add Skill</button></form>
<?php ownerLayoutEnd();
