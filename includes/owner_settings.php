<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_dashboard_copy.php';

/*
 * Owner settings data (backend, decision ق-31). The contract the settings page
 * template receives; see docs/contracts/owner-settings.md.
 *
 * Language is the existing server-side owner preference (ownerLocale(),
 * switched through ownerLanguageUrl()). Appearance, text size and motion are
 * browser preferences: the template only offers them, and owner_theme.js
 * stores and applies them under the keys below. The theme key is the existing
 * one, so the current light/dark/system behaviour is kept.
 */
const OWNER_SETTINGS_THEME_KEY = 'ather.evidenceHub.theme';
const OWNER_SETTINGS_TEXT_SIZE_KEY = 'ather.owner.textSize';
const OWNER_SETTINGS_MOTION_KEY = 'ather.owner.motion';

/**
 * @return array{
 *     locale: 'ar'|'en',
 *     languages: list<array{code: 'ar'|'en', url: string, current: bool}>,
 *     appearance: array{storage_key: string, options: list<'system'|'light'|'dark'>, default: 'system'},
 *     text_size: array{storage_key: string, options: list<'default'|'large'>, default: 'default'},
 *     motion: array{storage_key: string, options: list<'system'|'reduce'>, default: 'system'}
 * }
 */
function ownerSettingsPageData(string $locale): array
{
    $locale = $locale === 'en' ? 'en' : 'ar';
    $languages = [];
    foreach (['ar', 'en'] as $code) {
        $languages[] = ['code' => $code, 'url' => ownerLanguageUrl($code), 'current' => $code === $locale];
    }

    return [
        'locale' => $locale,
        'languages' => $languages,
        'appearance' => ['storage_key' => OWNER_SETTINGS_THEME_KEY, 'options' => ['system', 'light', 'dark'], 'default' => 'system'],
        'text_size' => ['storage_key' => OWNER_SETTINGS_TEXT_SIZE_KEY, 'options' => ['default', 'large'], 'default' => 'default'],
        'motion' => ['storage_key' => OWNER_SETTINGS_MOTION_KEY, 'options' => ['system', 'reduce'], 'default' => 'system'],
    ];
}
