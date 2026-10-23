<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/owner_actions.php';
require_once __DIR__ . '/includes/portfolio_scoped_data.php';
require_once __DIR__ . '/includes/owner_layout.php';
require_once __DIR__ . '/includes/final_profile_presentation.php';
require_once __DIR__ . '/includes/operational_security.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_profile.php');
httpRequireMethod(['GET', 'HEAD', 'POST']);

$fields = [
    'full_name', 'professional_title', 'hero_headline', 'email', 'phone_primary', 'phone_secondary',
    'location', 'about_me', 'work_description', 'linkedin_url', 'github_url',
    'instagram_url', 'facebook_url', 'website_url', ...FINAL_PROFILE_FIELDS,
];
$profile = array_fill_keys($fields, '');
$profile['profile_image_path'] = null;
$profile['public_email_visible'] = false;
$profile['public_phone_visible'] = false;
$skills = [];
$errors = [];
$fieldErrors = [];
$message = '';
$profileCompletion = 0;
$profileVersion = '';
$profileFormPost = $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? null, ['save_profile', 'upload_profile_image', 'remove_profile_image'], true);
if ($profileFormPost) { $profileVersion = is_string($_POST['expected_profile_version'] ?? null) ? $_POST['expected_profile_version'] : ''; }
$submittedSkillValue = '';
$submittedSkillId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_string($_POST['skill_name'] ?? null)) {
    $submittedSkillValue = trim($_POST['skill_name']);
    $submittedSkillId = ownerActionId($_POST['skill_id'] ?? null);
}

try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $current = loadAuthorizedPersonalInfo($database, $context);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        if ($profileFormPost) {
            $profile = array_merge($profile, $current ?? []);
            $profile['_submitted_profile_version'] = $profileVersion;
        }
        $mayMutate = true;
        if (($_POST['action'] ?? null) === 'upload_profile_image') {
            try {
                $limit = consumeOwnerUploadRateLimit($context);
                if (!$limit['allowed']) {
                    reportRateLimitDenial('owner_upload', $context);
                    http_response_code(429);
                    header('Retry-After: ' . $limit['retry_after']);
                    $errors[] = 'Please wait before uploading another image.';
                    $mayMutate = false;
                }
            } catch (Throwable $exception) {
                reportApplicationError($exception, 'owner_profile.php', 'owner_upload_rate_limit');
                http_response_code(503);
                $errors[] = 'Image uploads are temporarily unavailable.';
                $mayMutate = false;
            }
        }
        if ($mayMutate) {
            $result = handleAuthorizedProfileAction($database, $context, $_POST, $_FILES, $current, $fields, $profile);
            $errors = $result['errors'];
            $fieldErrors = $result['field_errors'];
            $profile = $result['profile'];
            if ($result['redirect'] !== null) {
                httpRedirect($result['redirect']);
            }
            if ($errors !== []) {
                http_response_code($result['status']);
            }
        }
    } else {
        if (isset($_GET['saved'])) $message = 'Personal information saved successfully.';
        if (isset($_GET['photo_updated'])) $message = 'Profile photo updated.';
        if (isset($_GET['photo_removed'])) $message = 'Profile photo removed.';
        if (isset($_GET['skill_added'])) $message = 'Skill added successfully.';
        if (isset($_GET['skill_updated'])) $message = 'Skill updated successfully.';
        if (isset($_GET['skill_deleted'])) $message = 'Skill removed.';
    }

    $savedProfile = ($_SERVER['REQUEST_METHOD'] === 'POST') ? loadAuthorizedPersonalInfo($database, $context) : $current;
    if (is_array($savedProfile) && !isset($profile['_submitted_profile_version'])) {
        $profile = array_merge($profile, $savedProfile);
    }
    $profileVersion = $profile['_submitted_profile_version'] ?? (string) ($savedProfile['profile_version'] ?? 0);
    $profileId = isset($profile['_submitted_profile_version'])
        ? (array_key_exists('profile_id', $_POST) ? ownerActionId($_POST['profile_id']) : null)
        : (is_array($savedProfile) ? (int) $savedProfile['id'] : null);
    $skills = listAuthorizedSkills($database, $context);
    if ($submittedSkillId !== null && ownerFieldErrorMessage($fieldErrors, 'skill_name') !== null) {
        foreach ($skills as &$skill) {
            if ((int) $skill['id'] === $submittedSkillId) {
                $skill['skill_name'] = $submittedSkillValue;
                break;
            }
        }
        unset($skill);
    }
    $completionFields = ['full_name', 'professional_title', 'email', 'phone_primary', 'about_me', 'work_description', 'location', 'linkedin_url', 'github_url', 'profile_image_path'];
    $profileCompletion = (int) round((count(array_filter($completionFields, static fn (string $field): bool => !empty($profile[$field]))) + (count($skills) > 0 ? 1 : 0)) / (count($completionFields) + 1) * 100);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_profile.php', 'owner_profile_request');
    http_response_code(503);
    $errors[] = 'Personal information is temporarily unavailable.';
    $profileId = null;
}

ownerLayoutStart('Personal Info', 'profile', true, ['account_name' => $profile['full_name'] ?? '']);
?>
<div class="admin-page-header">
    <div class="admin-page-header-copy"><p class="admin-eyebrow"><?php echo ownerEscapeHtml(ownerText('Profile')); ?></p><h1 class="admin-page-title"><?php echo ownerEscapeHtml(ownerText('Personal Info')); ?></h1><p class="admin-page-description"><?php echo ownerEscapeHtml(ownerText('Manage your private Portfolio information.')); ?></p></div>
    <div class="admin-page-header-actions"><a class="button-primary" href="owner_preview.php"><?php echo ownerEscapeHtml(ownerText('Private Preview')); ?></a></div>
</div>
<?php ownerRenderFormFeedback($message, $errors, $fieldErrors); if (isset($profile['_submitted_profile_version']) && $errors !== []) { echo '<p><a href="owner_profile.php">Reload saved profile and review before applying your changes again</a>. A selected photo may need reselection.</p>'; } ?>
<div class="profile-summary"><div><strong><?php echo ownerEscapeHtml($profile['full_name'] ?: ownerText('Your name')); ?></strong><span><?php echo ownerEscapeHtml($profile['professional_title'] ?: ownerText('Add a professional title')); ?></span></div><div class="completion"><div class="completion-header"><span><?php echo ownerEscapeHtml(ownerText('Profile completion')); ?></span><strong><?php echo $profileCompletion; ?>%</strong></div><progress value="<?php echo $profileCompletion; ?>" max="100"><?php echo $profileCompletion; ?>%</progress></div></div>

<?php if ($profileId !== null): ?>
<div class="profile-photo-card">
    <div class="profile-photo-preview"><?php if (!empty($profile['profile_image_path'])): ?><img src="owner_media.php?type=profile" alt="<?php echo ownerEscapeHtml($profile['full_name']); ?>"><?php else: ?><span><?php echo ownerEscapeHtml(profileInitials($profile['full_name'] ?: 'P')); ?></span><?php endif; ?></div>
    <div class="profile-photo-controls"><h2><?php echo ownerEscapeHtml(ownerText('Profile Photo')); ?></h2><p class="photo-intro"><?php echo ownerEscapeHtml(ownerText('Update the photo shown in your private preview.')); ?></p>
        <form method="POST" action="owner_profile.php" enctype="multipart/form-data" data-owner-form><input type="hidden" name="action" value="upload_profile_image"><input type="hidden" name="expected_profile_version" value="<?php echo ownerEscapeHtml($profileVersion); ?>"><input type="hidden" name="profile_id" value="<?php echo $profileId; ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><label class="form-field" for="profile_image"><span><?php echo ownerEscapeHtml(ownerText('JPG or PNG, at least 400 × 400 px, maximum')); ?> <?php echo profileImageMaximumMegabytes(); ?> <?php echo ownerEscapeHtml(ownerText('MB. High-resolution images are optimized automatically.')); ?></span><input id="profile_image" type="file" name="profile_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required data-profile-image-input data-profile-image-max-bytes="<?php echo PROFILE_IMAGE_MAX_BYTES; ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'profile_image', 'profile-image-status'); ?>><small id="profile-image-status" class="file-selection-status" role="status" aria-live="polite"></small></label><?php ownerRenderFieldError($fieldErrors, 'profile_image'); ?><button class="button-primary" type="submit" data-pending-label="<?php echo ownerEscapeHtml(ownerText('Saving photo…')); ?>"><?php echo ownerEscapeHtml(ownerText('Save Photo')); ?></button></form>
        <?php if (!empty($profile['profile_image_path'])): ?><form method="POST" action="owner_profile.php" data-owner-form data-confirm="<?php echo ownerEscapeHtml(ownerText('Remove this profile photo? This action cannot be undone.')); ?>" data-confirm-title="<?php echo ownerEscapeHtml(ownerText('Remove profile photo?')); ?>" data-confirm-action="<?php echo ownerEscapeHtml(ownerText('Remove photo')); ?>"><input type="hidden" name="action" value="remove_profile_image"><input type="hidden" name="expected_profile_version" value="<?php echo ownerEscapeHtml($profileVersion); ?>"><input type="hidden" name="profile_id" value="<?php echo $profileId; ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><button class="button-danger" type="submit" data-pending-label="<?php echo ownerEscapeHtml(ownerText('Removing photo…')); ?>"><?php echo ownerEscapeHtml(ownerText('Remove Photo')); ?></button></form><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<form class="profile-form" id="profile-form" method="POST" action="owner_profile.php" data-owner-form>
    <input type="hidden" name="action" value="save_profile"><input type="hidden" name="expected_profile_version" value="<?php echo ownerEscapeHtml($profileVersion); ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><?php if ($profileId !== null): ?><input type="hidden" name="profile_id" value="<?php echo $profileId; ?>"><?php endif; ?>
    <div class="form-grid">
        <h2 class="form-section-title"><?php echo ownerEscapeHtml(ownerText('Basic Information')); ?></h2>
        <?php foreach (['full_name' => 'Full Name', 'professional_title' => 'Professional Title'] as $field => $label): ?>
            <label class="form-field" for="<?php echo $field; ?>"><span><?php echo ownerEscapeHtml(ownerText($label)); ?><?php if ($field === 'full_name'): ?> <?php ownerRenderRequiredIndicator(); ?><?php endif; ?></span><input type="<?php echo $field === 'email' ? 'email' : 'text'; ?>" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo ownerEscapeHtml((string) $profile[$field]); ?>" maxlength="<?php echo PERSONAL_INFO_FIELD_MAX_LENGTHS[$field]; ?>"<?php echo $field === 'full_name' ? ' required' : ''; ?><?php echo ownerFieldAccessibilityAttributes($fieldErrors, $field); ?>></label><?php ownerRenderFieldError($fieldErrors, $field); ?>
        <?php endforeach; ?>
        <label class="form-field form-field-full" for="hero_headline"><span><?php echo ownerEscapeHtml(ownerText('Hero headline')); ?> <em><?php echo ownerEscapeHtml(ownerText('(optional)')); ?></em></span><input type="text" id="hero_headline" name="hero_headline" value="<?php echo ownerEscapeHtml((string) $profile['hero_headline']); ?>" maxlength="<?php echo PERSONAL_INFO_FIELD_MAX_LENGTHS['hero_headline']; ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'hero_headline', 'hero-headline-help'); ?>><small id="hero-headline-help" class="form-hint"><?php echo ownerEscapeHtml(ownerText('Describe what you build, solve, or contribute in one concise professional statement.')); ?></small></label><?php ownerRenderFieldError($fieldErrors, 'hero_headline'); ?>
        <?php foreach (['email' => 'Email', 'phone_primary' => 'Primary Phone', 'phone_secondary' => 'Secondary Phone', 'location' => 'Location'] as $field => $label): ?>
            <label class="form-field" for="<?php echo $field; ?>"><span><?php echo ownerEscapeHtml(ownerText($label)); ?></span><input type="<?php echo $field === 'email' ? 'email' : 'text'; ?>" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo ownerEscapeHtml((string) $profile[$field]); ?>" maxlength="<?php echo PERSONAL_INFO_FIELD_MAX_LENGTHS[$field]; ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, $field); ?>></label><?php ownerRenderFieldError($fieldErrors, $field); ?>
        <?php endforeach; ?>
        <?php foreach (['public_email_visible' => ['public_email_present', 'profile email'], 'public_phone_visible' => ['public_phone_present', 'primary phone']] as $visibility => [$marker, $contactLabel]): ?>
            <div class="form-field-full">
                <input type="hidden" name="<?php echo $marker; ?>" value="1"><label class="checkbox-field" for="<?php echo $visibility; ?>"><input type="checkbox" id="<?php echo $visibility; ?>" name="<?php echo $visibility; ?>" value="1"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, $visibility, $visibility . '-help'); ?><?php echo !empty($profile[$visibility]) ? ' checked' : ''; ?>><?php echo ownerEscapeHtml(ownerText('Show my')); ?> <?php echo ownerEscapeHtml(ownerText($contactLabel)); ?> <?php echo ownerEscapeHtml(ownerText('on my public Portfolio.')); ?></label>
                <p class="form-hint" id="<?php echo $visibility; ?>-help"><?php echo ownerEscapeHtml(ownerText('Only your saved')); ?> <?php echo ownerEscapeHtml(ownerText($contactLabel)); ?> <?php echo ownerEscapeHtml(ownerText('is affected. Private Preview always shows your saved contact details.')); ?></p>
                <?php ownerRenderFieldError($fieldErrors, $visibility); ?>
            </div>
        <?php endforeach; ?>
        <p class="form-hint form-field-full"><?php echo ownerEscapeHtml(ownerText('Your secondary phone stays private.')); ?></p>
        <h2 class="form-section-title"><?php echo ownerEscapeHtml(ownerText('About Me')); ?></h2>
        <label class="form-field form-field-full" for="about_me"><span><?php echo ownerEscapeHtml(ownerText('About Me')); ?></span><textarea id="about_me" name="about_me" maxlength="<?php echo PERSONAL_INFO_FIELD_MAX_LENGTHS['about_me']; ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'about_me'); ?>><?php echo ownerEscapeHtml((string) $profile['about_me']); ?></textarea></label><?php ownerRenderFieldError($fieldErrors, 'about_me'); ?>
        <label class="form-field form-field-full" for="work_description"><span><?php echo ownerEscapeHtml(ownerText('Professional summary')); ?> <em><?php echo ownerEscapeHtml(ownerText('(optional)')); ?></em></span><textarea id="work_description" name="work_description" maxlength="<?php echo PERSONAL_INFO_FIELD_MAX_LENGTHS['work_description']; ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'work_description', 'professional-summary-help'); ?>><?php echo ownerEscapeHtml((string) $profile['work_description']); ?></textarea><small id="professional-summary-help" class="form-hint"><?php echo ownerEscapeHtml(ownerText('Add supporting context for your public Hero.')); ?></small></label><?php ownerRenderFieldError($fieldErrors, 'work_description'); ?>
        <?php renderFinalProfileFields($profile, $fieldErrors); ?>
        <h2 class="form-section-title"><?php echo ownerEscapeHtml(ownerText('Social Accounts')); ?></h2>
        <?php foreach (['linkedin_url' => 'LinkedIn URL', 'github_url' => 'GitHub URL', 'instagram_url' => 'Instagram URL', 'facebook_url' => 'Facebook URL', 'website_url' => 'Personal Website URL'] as $field => $label): ?>
            <label class="form-field" for="<?php echo $field; ?>"><span><?php echo ownerEscapeHtml(ownerText($label)); ?></span><input type="url" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo ownerEscapeHtml((string) $profile[$field]); ?>" maxlength="<?php echo PERSONAL_INFO_FIELD_MAX_LENGTHS[$field]; ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, $field); ?>></label><?php ownerRenderFieldError($fieldErrors, $field); ?>
        <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="button-primary" type="submit" data-pending-label="<?php echo ownerEscapeHtml(ownerText('Saving changes…')); ?>"><?php echo ownerEscapeHtml(ownerText('Save Changes')); ?></button></div>
</form>

<div class="section-heading-row" id="skills"><h2><?php echo ownerEscapeHtml(ownerText('Skills')); ?></h2><span class="muted"><?php echo count($skills); ?> <?php echo ownerEscapeHtml(ownerText('added')); ?></span></div>
<?php if ($skills === []): ?><div class="empty-state admin-empty"><strong><?php echo ownerEscapeHtml(ownerText('No skills added yet')); ?></strong><span><?php echo ownerEscapeHtml(ownerText('Add your first skill to help visitors understand your strengths.')); ?></span></div><?php endif; ?>
<div class="skills-chips"><?php foreach ($skills as $skill): $skillFieldErrors = $submittedSkillId === (int) $skill['id'] ? $fieldErrors : []; ?><div class="skill-row"><form method="POST" action="owner_profile.php" data-owner-form><input type="hidden" name="action" value="update_skill"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="skill_id" value="<?php echo (int) $skill['id']; ?>"><label class="visually-hidden" for="skill_<?php echo (int) $skill['id']; ?>"><?php echo ownerEscapeHtml(ownerText('Skill name')); ?></label><input id="skill_<?php echo (int) $skill['id']; ?>" type="text" name="skill_name" value="<?php echo ownerEscapeHtml((string) $skill['skill_name']); ?>" maxlength="<?php echo SKILL_NAME_MAX_LENGTH; ?>" required<?php echo ownerFieldAccessibilityAttributes($skillFieldErrors, 'skill_name'); ?>><button type="submit" class="button-secondary" data-pending-label="<?php echo ownerEscapeHtml(ownerText('Saving skill…')); ?>"><?php echo ownerEscapeHtml(ownerText('Save')); ?></button><?php ownerRenderFieldError($skillFieldErrors, 'skill_name'); ?></form><form method="POST" action="owner_profile.php" data-owner-form data-confirm="Remove ‘<?php echo ownerEscapeHtml((string) $skill['skill_name']); ?>’ from your skills? This action cannot be undone." data-confirm-title="<?php echo ownerEscapeHtml(ownerText('Remove skill?')); ?>" data-confirm-action="<?php echo ownerEscapeHtml(ownerText('Remove skill')); ?>"><input type="hidden" name="action" value="delete_skill"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="skill_id" value="<?php echo (int) $skill['id']; ?>"><button type="submit" class="button-danger" data-pending-label="<?php echo ownerEscapeHtml(ownerText('Removing skill…')); ?>"><?php echo ownerEscapeHtml(ownerText('Remove')); ?></button></form></div><?php endforeach; ?></div>
<?php $skillAddFieldErrors = $submittedSkillId === null ? $fieldErrors : []; ?><form class="skill-add-form" method="POST" action="owner_profile.php" data-owner-form><input type="hidden" name="action" value="add_skill"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><label class="form-field" for="skill_name"><span><?php echo ownerEscapeHtml(ownerText('Skill Name')); ?> <?php ownerRenderRequiredIndicator(); ?></span><input type="text" id="skill_name" name="skill_name" value="<?php echo ownerEscapeHtml($submittedSkillId === null ? $submittedSkillValue : ''); ?>" maxlength="<?php echo SKILL_NAME_MAX_LENGTH; ?>" required<?php echo ownerFieldAccessibilityAttributes($skillAddFieldErrors, 'skill_name'); ?>></label><?php ownerRenderFieldError($skillAddFieldErrors, 'skill_name'); ?><button class="button-primary" type="submit" data-pending-label="<?php echo ownerEscapeHtml(ownerText('Adding skill…')); ?>"><?php echo ownerEscapeHtml(ownerText('Add Skill')); ?></button></form>
<?php ownerLayoutEnd();
