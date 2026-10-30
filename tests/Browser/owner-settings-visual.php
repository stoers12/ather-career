<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') { http_response_code(404); exit; }
require_once __DIR__ . '/../../includes/owner_settings.php';
require_once __DIR__ . '/../../includes/owner_settings_presentation.php';
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
$locale = ownerLocale();
$_COOKIE['ather_owner_locale'] = $locale;
$_SERVER['REQUEST_URI'] = '/owner_settings.php?lang=' . $locale;
ownerLayoutStart('Settings', 'settings', true, ['locale' => $locale, 'account_name' => 'Sami Ahmad']);
renderOwnerSettingsPage(ownerSettingsPageData($locale));
ownerLayoutEnd();
