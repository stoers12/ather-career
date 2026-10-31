<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_layout.php';
require_once __DIR__ . '/owner_settings_copy.php';
require_once __DIR__ . '/legal_presentation.php';

/**
 * @param array{locale: string, languages: list<array{code: string, url: string, current: bool}>,
 *     appearance: array{storage_key: string, options: list<string>, default: string},
 *     text_size: array{storage_key: string, options: list<string>, default: string},
 *     motion: array{storage_key: string, options: list<string>, default: string}} $settings
 */
function renderOwnerSettingsPage(array $settings): void
{
    $copy = static fn (string $key): string => ownerEscapeHtml(ownerSettingsCopy($key, $settings['locale']));
    $names = ['ar' => 'العربية', 'en' => 'English'];
    ?>
<div class="owner-settings">
    <header class="owner-settings-heading">
        <span class="owner-settings-eyebrow"><?php echo $copy('eyebrow'); ?></span>
        <h1><?php echo $copy('title'); ?></h1>
        <p><?php echo $copy('subtitle'); ?></p>
    </header>
    <div class="owner-settings-layout">
        <nav class="owner-settings-nav" aria-label="<?php echo $copy('sections'); ?>">
            <a href="#appearance" data-owner-settings-link="appearance"><?php echo evidenceHubOwnerIcon('sliders-horizontal'); ?><?php echo $copy('appearance'); ?></a>
            <a href="#help" data-owner-settings-link="help"><?php echo evidenceHubOwnerIcon('circle-help'); ?><?php echo $copy('help'); ?></a>
        </nav>
        <div class="owner-settings-body">
            <section class="owner-settings-card" id="appearance" data-owner-settings-panel="appearance" aria-labelledby="appearance-title">
                <header><span class="owner-settings-eyebrow"><?php echo $copy('make_yours'); ?></span><h2 id="appearance-title"><?php echo $copy('appearance'); ?></h2><p><?php echo $copy('appearance_intro'); ?></p></header>
                <fieldset class="owner-theme-fieldset" data-owner-browser-preference hidden>
                    <legend><?php echo $copy('workspace_appearance'); ?></legend>
                    <div class="owner-theme-choices">
                        <?php foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'monitor'] as $value => $icon): ?>
                        <?php if (!in_array($value, $settings['appearance']['options'], true)) continue; ?>
                        <label class="owner-theme-choice">
                            <input type="radio" name="appearance" value="<?php echo ownerEscapeHtml($value); ?>" data-owner-appearance<?php echo $value === $settings['appearance']['default'] ? ' checked' : ''; ?>>
                            <span class="owner-theme-mini" data-sample="<?php echo ownerEscapeHtml($value); ?>" aria-hidden="true"><span class="owner-mini-sidebar"></span><span class="owner-mini-layout"><b></b><b></b><b></b></span></span>
                            <span><?php echo evidenceHubOwnerIcon($icon); ?><?php echo $copy($value); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="owner-setting-hint"><?php echo $copy('system_hint'); ?></p>
                </fieldset>
                <div class="owner-setting-row">
                    <div class="owner-setting-description"><h3 id="settings-language"><?php echo $copy('workspace_language'); ?></h3><p><?php echo $copy('language_hint'); ?></p></div>
                    <div class="owner-language-options" role="group" aria-labelledby="settings-language">
                        <?php foreach ($settings['languages'] as $language): ?>
                        <a href="<?php echo ownerEscapeHtml($language['url']); ?>" lang="<?php echo ownerEscapeHtml($language['code']); ?>" hreflang="<?php echo ownerEscapeHtml($language['code']); ?>"<?php echo $language['current'] ? ' aria-current="true"' : ''; ?>><?php echo ownerEscapeHtml($names[$language['code']] ?? $language['code']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="owner-setting-row">
                    <div class="owner-setting-description"><h3><?php echo $copy('public_language'); ?></h3><p><?php echo $copy('public_hint'); ?></p></div>
                    <span class="owner-public-language" lang="en">English</span>
                </div>
                <?php foreach (['text_size' => ['default', 'large'], 'motion' => ['system', 'reduce']] as $key => $values): ?>
                <div class="owner-setting-row" data-owner-browser-preference hidden>
                    <div class="owner-setting-description"><label for="settings-<?php echo $key; ?>"><?php echo $copy($key); ?></label><p id="settings-<?php echo $key; ?>-hint"><?php echo $copy($key === 'text_size' ? 'text_hint' : 'motion_hint'); ?></p></div>
                    <select class="owner-pref-select" id="settings-<?php echo $key; ?>" data-owner-preference="<?php echo $key; ?>" aria-describedby="settings-<?php echo $key; ?>-hint">
                        <?php foreach ($values as $value): ?>
                        <?php if (!in_array($value, $settings[$key]['options'], true)) continue; ?>
                        <option value="<?php echo ownerEscapeHtml($value); ?>"<?php echo $value === $settings[$key]['default'] ? ' selected' : ''; ?>><?php echo $copy($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </section>
            <section class="owner-settings-card" id="help" data-owner-settings-panel="help" aria-labelledby="help-title">
                <header><span class="owner-settings-eyebrow"><?php echo $copy('help_eyebrow'); ?></span><h2 id="help-title"><?php echo $copy('help'); ?></h2><p><?php echo $copy('help_intro'); ?></p></header>
                <?php renderLegalLinks($settings['locale'], 'settings'); ?>
                <?php foreach (['language', 'theme', 'visitors', 'storage'] as $question): ?>
                <details class="owner-settings-question"><summary><?php echo $copy('q_' . $question); ?></summary><p><?php echo $copy('a_' . $question); ?></p></details>
                <?php endforeach; ?>
            </section>
            <p class="owner-preference-status"><?php echo evidenceHubOwnerIcon('check'); ?><?php echo $copy('browser_note'); ?></p>
        </div>
    </div>
</div>
    <?php
}
