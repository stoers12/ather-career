<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') { http_response_code(404); exit; }
require_once __DIR__ . '/../../includes/owner_settings.php';
require_once __DIR__ . '/../../includes/owner_settings_presentation.php';
require_once __DIR__ . '/../../includes/owner_data_rights_presentation.php';
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
$locale = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'ar';
$_COOKIE['ather_owner_locale'] = $locale;
$view = $_GET['view'] ?? 'settings';
$recent = ($_GET['recent'] ?? null) === '1';
$notice = in_array($_GET['notice'] ?? null, ['reauthenticate', 'confirmation'], true) ? $_GET['notice'] : null;
if ($view === 'scheduled') {
    renderOwnerAccountDeletionPage(['locale' => $locale, 'requested_at' => '2026-11-01T23:30:00Z', 'due_at' => '2026-12-01T23:30:00Z',
        'grace_days' => 30, 'cancel_url' => '/owner_account_deletion.php', 'export_url' => '/owner_data_export.php',
        'reauthenticate_url' => '/owner_reauthenticate.php', 'logout_url' => '/owner_logout.php',
        'recently_authenticated' => $recent, 'notice' => $notice]);
} elseif ($view === 'signin') {
    require __DIR__ . '/../../handlers/signin.php';
} elseif ($view === 'dashboard') {
    require __DIR__ . '/owner-workspace-visual.php';
} else {
    $_SERVER['REQUEST_URI'] = '/owner_settings.php?lang=' . $locale;
    ownerLayoutStart('Settings', 'settings', true, ['locale' => $locale, 'account_name' => 'Sami Ahmad']);
    renderOwnerSettingsPage(ownerSettingsPageData($locale) + ['data_rights' => [
        'export_url' => '/owner_data_export.php', 'reauthenticate_url' => '/owner_reauthenticate.php',
        'deletion_url' => '/owner_account_deletion.php', 'recently_authenticated' => $recent,
        'recent_until' => null, 'confirmation_phrase' => 'sami-ahmad', 'grace_days' => 30, 'notice' => $notice,
    ]]);
    ownerLayoutEnd();
}
