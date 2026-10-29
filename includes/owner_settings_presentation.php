<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_layout.php';

/**
 * Settings page template (frontend). Minimal working baseline from the backend
 * PR: the frontend slice FE-02 replaces this markup with the reference design.
 * It receives exactly ownerSettingsPageData() (docs/contracts/owner-settings.md).
 *
 * @param array{locale: string, languages: list<array{code: string, url: string, current: bool}>} $settings
 */
function renderOwnerSettingsPage(array $settings): void
{
    $names = ['ar' => 'العربية', 'en' => 'English'];
    ?>
<h1><?php echo ownerEscapeHtml(ownerText('Settings')); ?></h1>
<section aria-labelledby="settings-language">
    <h2 id="settings-language"><?php echo ownerEscapeHtml(ownerText('Language')); ?></h2>
    <ul>
        <?php foreach ($settings['languages'] as $language): ?>
        <li><?php if ($language['current']): ?><strong lang="<?php echo $language['code']; ?>"><?php echo $names[$language['code']]; ?></strong><?php else: ?><a href="<?php echo ownerEscapeHtml($language['url']); ?>" lang="<?php echo $language['code']; ?>" hreflang="<?php echo $language['code']; ?>"><?php echo $names[$language['code']]; ?></a><?php endif; ?></li>
        <?php endforeach; ?>
    </ul>
</section>
    <?php
}
