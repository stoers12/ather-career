<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/error_reporting.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/owner_actions.php';
require_once dirname(__DIR__) . '/includes/portfolio_scoped_data.php';
require_once dirname(__DIR__) . '/includes/owner_layout.php';
require_once dirname(__DIR__) . '/includes/final_profile_presentation.php';
require_once dirname(__DIR__) . '/includes/operational_security.php';

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

require_once dirname(__DIR__) . '/includes/owner_profile_presentation.php';
ownerLayoutStart('Personal Info', 'profile', true, ['account_name' => $profile['full_name'] ?? '']);
renderOwnerProfilePage($profile, $profileId, $profileVersion, $profileCompletion, $skills, $message, $errors, $fieldErrors, $submittedSkillId, $submittedSkillValue);
ownerLayoutEnd();
