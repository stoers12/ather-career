<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../includes/owner_actions.php';
require_once __DIR__ . '/../../includes/owner_profile_presentation.php';
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
$profile = array_fill_keys(array_keys(PERSONAL_INFO_FIELD_MAX_LENGTHS), '');
$profile = array_merge($profile, [
    'full_name' => 'Sami Ahmad', 'professional_title' => 'AI Engineer', 'location' => 'Amman, Jordan',
    'email' => 'hello@example.com', 'about_me' => 'I build reliable software that solves real problems.',
    'work_description' => 'AI engineering and practical software development.', 'profile_image_path' => null,
    'public_email_visible' => false, 'public_phone_visible' => false,
]);
$locale = ownerLocale();
$_COOKIE['ather_owner_locale'] = $locale;
$_SERVER['REQUEST_URI'] = '/owner_profile.php?lang=' . $locale;
$error = ($_GET['state'] ?? '') === 'error';
if ($error) $profile['linkedin_url'] = 'ftp://example.invalid/not-http';
ownerLayoutStart('Personal Info', 'profile', true, ['account_name' => $profile['full_name'], 'locale' => $locale]);
renderOwnerProfilePage($profile, 1, '7', 73, [['id' => 1, 'skill_name' => 'Python'], ['id' => 2, 'skill_name' => 'REST API']], '', $error ? ['Please provide a valid LinkedIn URL.'] : [], $error ? ['linkedin_url' => 'Please provide a valid LinkedIn URL.'] : [], null, '');
ownerLayoutEnd();
