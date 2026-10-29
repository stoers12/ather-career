<?php

declare(strict_types=1);

// Loaded by owner_layout.php.
// The owner workspace shell (UI-02, decision ق-29): the reference package's
// sidebar and toolbar, rendered on the server with its exact markup, classes
// and copy (ATHER-UI-Package assets/app.js: sidebarBrand, workspaceNavigation,
// sidebarGroup, sidebarItem, sidebarUtilities, accountControl, the toolbar).
// Prototype-only interactions become links, forms and <details>, so every
// control works without JavaScript; admin.js only enhances (collapse, menu).

/** Shell copy from the reference's own Arabic/English pairs. */
function ownerShellText(string $key, string $locale): string
{
    static $copy = [
        'navigation' => ['التنقل والحساب', 'Navigation & account'],
        'main_navigation' => ['التنقل الرئيسي', 'Main navigation'],
        'brand' => ['ATHER — الرئيسية', 'ATHER — Overview'],
        'collapse' => ['طي الشريط الجانبي', 'Collapse sidebar'],
        'expand' => ['توسيع الشريط الجانبي', 'Expand sidebar'],
        'collapse_tip' => ['طي القائمة', 'Collapse navigation'],
        'open_navigation' => ['فتح قائمة التنقل', 'Open navigation'],
        'close_navigation' => ['إغلاق قائمة التنقل', 'Close navigation'],
        'dashboard' => ['الرئيسية', 'Overview'],
        'profile' => ['الملف المهني', 'Profile'],
        'projects' => ['المشاريع', 'Projects'],
        'experiences' => ['الخبرات', 'Experience'],
        'publication' => ['نشر الملف', 'Publish portfolio'],
        'messages' => ['الرسائل', 'Messages'],
        'evidence_hub' => ['مركز الأدلة', 'Evidence Hub'],
        'settings' => ['الإعدادات', 'Settings'],
        'help' => ['المساعدة', 'Help'],
        'group_portfolio' => ['ملف الأعمال', 'Portfolio'],
        'group_sharing' => ['النشر والتواصل', 'Publish & connect'],
        'current' => ['حالي', 'Current'],
        'private' => ['خاص', 'Private'],
        'help_settings' => ['المساعدة والإعدادات', 'Help & settings'],
        // Not in the reference: Settings and Help pages do not exist yet (ق-29).
        'coming_soon' => ['قريباً', 'Coming soon'],
        'personal_account' => ['حساب شخصي', 'Personal account'],
        'account_options' => ['قائمة حساب ', 'Account options for '],
        'account' => ['الحساب', 'ACCOUNT'],
        'my_profile' => ['ملفي المهني', 'My profile'],
        'switch_account' => ['تبديل الحساب', 'Switch account'],
        'sign_out' => ['تسجيل الخروج', 'Sign out'],
        'workspace' => ['مساحة العمل', 'Workspace'],
        'crumb_dashboard' => ['نظرة عامة', 'Overview'],
        'crumb_profile' => ['الملف الشخصي', 'Profile'],
        'crumb_projects' => ['المشاريع', 'Projects'],
        'crumb_experiences' => ['الخبرات', 'Experience'],
        'crumb_messages' => ['الرسائل', 'Messages'],
        'crumb_publication' => ['إعدادات النشر', 'Publishing settings'],
        'publish_share' => ['النشر والمشاركة', 'Publishing & sharing'],
        'preview' => ['معاينة البورتفوليو', 'Preview portfolio'],
        'dark' => ['الوضع الداكن', 'Dark mode'],
        'light' => ['الوضع الفاتح', 'Light mode'],
    ];

    return $copy[$key][$locale === 'en' ? 1 : 0];
}

/** The reference avatar shows the first two letters of the owner's first name. */
function ownerAccountInitials(string $name): string
{
    $first = preg_split('/\s+/u', trim($name), 2, PREG_SPLIT_NO_EMPTY)[0] ?? '';

    return mb_strtoupper(mb_substr($first, 0, 2, 'UTF-8'), 'UTF-8');
}

/**
 * A redesigned page's own stylesheet, assets/css/owner-page-<page>.css, loaded
 * after the shell when it exists. Page cards (UI-03…UI-07) add only that file,
 * so they never edit the shared layout or each other's styles.
 */
function ownerPageStylesheet(string $activePage, ?string $publicRoot = null): ?string
{
    if (preg_match('/^[a-z_]+$/', $activePage) !== 1) {
        return null;
    }
    $path = 'assets/css/owner-page-' . $activePage . '.css';

    return is_file(($publicRoot ?? dirname(__DIR__) . '/public') . '/' . $path) ? $path : null;
}

/** The reference's page key for data-page on the root. */
function ownerShellPageKey(string $activePage): string
{
    return ['dashboard' => 'overview'][$activePage] ?? $activePage;
}

/** @param array{locale: string, account_name?: string} $workspace */
function ownerWorkspaceShellStart(string $activePage, array $workspace, bool $themeCapable): void
{
    $locale = $workspace['locale'];
    $text = static fn (string $key): string => ownerEscapeHtml(ownerShellText($key, $locale));
    $accountName = trim((string) ($workspace['account_name'] ?? ''));
    $links = [
        'dashboard' => ['/owner.php', 'house'],
        'profile' => ['/owner_profile.php', 'user-round'],
        'projects' => ['/owner_projects.php', 'folder-open'],
        'experiences' => ['/owner_experiences.php', 'briefcase-business'],
        'publication' => ['/owner_publication.php', 'send'],
        'messages' => ['/owner_messages.php', 'mail'],
        'evidence_hub' => ['/owner/evidence-hub', 'book-open-check'],
    ];
    $item = static function (string $key, string $extra = '') use ($activePage, $links, $locale, $text): void {
        [$href, $icon] = $links[$key];
        $current = $activePage === $key;
        $href .= $key === 'evidence_hub' ? '' : '?lang=' . $locale;
        ?><a class="an-item<?php echo $extra !== '' ? ' ' . $extra : ''; ?><?php echo $current ? ' active' : ''; ?>" href="<?php echo $href; ?>" aria-label="<?php echo $text($key); ?>"<?php echo $current ? ' aria-current="page"' : ''; ?> title="<?php echo $text($key); ?>"><span class="an-item-icon"><?php echo atherIcon($icon); ?></span><span class="an-item-label"><?php echo $text($key); ?></span><?php if ($key === 'evidence_hub'): ?><span class="an-private-label"><?php echo $text('private'); ?></span><?php endif; ?></a><?php
    };
    $groups = ['portfolio' => ['group_portfolio', ['profile', 'projects', 'experiences']], 'sharing' => ['group_sharing', ['publication', 'messages']]];
    $collapseIcon = $locale === 'ar' ? 'panel-right' : 'panel-left';
    ?>
<div class="export-frame">
<div id="ather-navigation" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>" lang="<?php echo $locale; ?>" data-ath-theme="system" data-page="<?php echo ownerEscapeHtml(ownerShellPageKey($activePage)); ?>">
<?php if ($themeCapable): ?>
<?php // Runs as soon as the root exists, so the saved theme and sidebar state apply before the first paint. ?>
<script src="<?php echo versionedAssetUrl('assets/js/owner_theme.js'); ?>"></script>
<?php endif; ?>
<div class="ath-shell admin-layout">
    <aside class="ath-sidebar admin-sidebar" aria-label="<?php echo $text('navigation'); ?>"><div class="an-sidebar-stack">
        <div class="ath-brand">
            <div class="an-brand-row">
                <a class="an-brand-link" href="/owner.php?lang=<?php echo $locale; ?>" aria-label="<?php echo $text('brand'); ?>"><span class="ath-wordmark an-full-wordmark">ATHER</span><span class="an-compact-wordmark" aria-hidden="true">A<span>•</span></span></a>
                <button type="button" class="ath-button an-collapse-button ath-icon-button ath-ghost" data-ath-collapse aria-label="<?php echo $text('collapse'); ?>" aria-expanded="true" aria-controls="owner-navigation" title="<?php echo $text('collapse_tip'); ?>" data-label-collapse="<?php echo $text('collapse'); ?>" data-label-expand="<?php echo $text('expand'); ?>"><span class="ath-collapse-close"><?php echo atherIcon($collapseIcon . '-close'); ?></span><span class="ath-collapse-open"><?php echo atherIcon($collapseIcon . '-open'); ?></span></button>
                <button type="button" class="ath-button ath-mobile-menu ath-icon-button owner-nav-toggle" data-owner-nav-toggle aria-controls="owner-navigation" aria-expanded="false" aria-label="<?php echo $text('open_navigation'); ?>" data-label-open="<?php echo $text('open_navigation'); ?>" data-label-close="<?php echo $text('close_navigation'); ?>"><span class="ath-menu-open"><?php echo atherIcon('menu'); ?></span><span class="ath-menu-close"><?php echo atherIcon('x'); ?></span></button>
            </div>
            <p class="an-brand-caption">CAREER WORKSPACE</p>
        </div>
        <div class="an-sidebar-body owner-navigation" id="owner-navigation">
            <nav class="ath-nav" aria-label="<?php echo $text('main_navigation'); ?>">
                <?php $item('dashboard', 'an-home'); ?>
                <?php foreach ($groups as $group => [$label, $keys]): $currentGroup = in_array($activePage, $keys, true); ?>
                <details class="an-group" data-group="<?php echo $group; ?>" open>
                    <summary class="an-group-toggle"<?php echo $currentGroup ? ' data-current-group="true"' : ''; ?>><span><?php echo $text($label); ?></span><?php if ($currentGroup): ?><span class="an-current-group"><?php echo $text('current'); ?></span><?php endif; ?><span class="ath-group-open"><?php echo atherIcon('chevron-up'); ?></span><span class="ath-group-closed"><?php echo atherIcon('chevron-down'); ?></span></summary>
                    <div class="an-group-items"><?php foreach ($keys as $key) { $item($key); } ?></div>
                </details>
                <?php endforeach; ?>
                <div class="an-module"><?php $item('evidence_hub'); ?></div>
            </nav>
            <div class="ath-sidebar-footer">
                <nav class="an-utilities" aria-label="<?php echo $text('help_settings'); ?>">
                    <?php foreach (['settings' => 'settings', 'help' => 'circle-help'] as $key => $icon): ?>
                    <button type="button" class="an-item" aria-disabled="true" aria-label="<?php echo $text($key); ?> — <?php echo $text('coming_soon'); ?>" title="<?php echo $text('coming_soon'); ?>"><span class="an-item-icon"><?php echo atherIcon($icon); ?></span><span class="an-item-label"><?php echo $text($key); ?></span><span class="an-private-label ath-soon-label"><?php echo $text('coming_soon'); ?></span></button>
                    <?php endforeach; ?>
                </nav>
                <details class="ath-account-area">
                    <summary class="ath-account ath-account-trigger" aria-label="<?php echo $text('account_options'); ?><?php echo ownerEscapeHtml($accountName); ?>" title="<?php echo ownerEscapeHtml($accountName); ?>"><span class="ath-avatar"><?php echo ownerEscapeHtml(ownerAccountInitials($accountName)); ?></span><span class="ath-account-copy"><strong dir="auto"><?php echo ownerEscapeHtml($accountName); ?></strong><small><?php echo $text('personal_account'); ?></small></span><?php echo atherIcon('chevrons-up-down'); ?></summary>
                    <div class="ath-account-popover">
                        <span><?php echo $text('account'); ?></span>
                        <strong class="an-account-name" dir="auto"><?php echo ownerEscapeHtml($accountName); ?></strong>
                        <a class="ath-button ath-ghost" href="/owner_profile.php?lang=<?php echo $locale; ?>"><?php echo atherIcon('user-round'); ?><?php echo $text('my_profile'); ?></a>
                        <form class="sidebar-logout-form" method="POST" action="/owner_switch_account.php" data-owner-form>
                            <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                            <button class="ath-button ath-ghost sidebar-logout-button" type="submit" data-pending-label="<?php echo ownerDashboardCopy('switch_pending', $locale); ?>"><?php echo atherIcon('users-round'); ?><?php echo $text('switch_account'); ?></button>
                        </form>
                        <form class="sidebar-logout-form" method="POST" action="/owner_logout.php" data-owner-form>
                            <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                            <button class="ath-button ath-ghost sidebar-logout-button" type="submit" data-pending-label="<?php echo ownerDashboardCopy('logout_pending', $locale); ?>"><?php echo atherIcon('log-out'); ?><?php echo $text('sign_out'); ?></button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </div></aside>
    <div class="ath-workspace">
        <header class="ath-toolbar owner-toolbar" aria-label="<?php echo ownerEscapeHtml(ownerText('Workspace tools')); ?>">
            <div class="ath-breadcrumb"><span><?php echo $text('workspace'); ?></span><span>/</span><span><?php echo $text('crumb_' . $activePage); ?></span></div>
            <div class="ath-actions">
                <?php if ($activePage === 'dashboard'): ?>
                <a class="ath-button" href="/owner_publication.php?lang=<?php echo $locale; ?>" aria-label="<?php echo $text('publish_share'); ?>"><?php echo atherIcon('link-2'); ?><span class="ath-preview-text"><?php echo $text('publish_share'); ?></span></a>
                <?php else: ?>
                <a class="ath-button owner-preview" href="/owner_preview.php" aria-label="<?php echo $text('preview'); ?>"><?php echo atherIcon('eye'); ?><span class="ath-preview-text"><?php echo $text('preview'); ?></span></a>
                <?php endif; ?>
                <?php foreach (['dark' => 'moon', 'light' => 'sun'] as $theme => $icon): ?>
                <button type="button" class="ath-button ath-icon-button ath-ghost" data-owner-theme="<?php echo $theme; ?>" aria-label="<?php echo $text($theme); ?>" aria-pressed="false"><?php echo atherIcon($icon); ?></button>
                <?php endforeach; ?>
            </div>
        </header>
        <main class="ath-main admin-content" id="main-content" tabindex="-1">
            <section>
    <?php
}

function ownerWorkspaceShellEnd(): void
{
    ?>
            </section>
        </main>
    </div>
</div>
</div>
</div>
</body>
</html>
    <?php
}
