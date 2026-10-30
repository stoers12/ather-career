<?php

declare(strict_types=1);

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/presentation.php';
require_once __DIR__ . '/owner_dashboard_copy.php';
require_once __DIR__ . '/evidence_hub_copy.php';
require_once __DIR__ . '/owner_flow.php';

function ownerEscapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/owner_form_feedback.php';

/** @param array{locale?: string, account_name?: string} $workspace Presentation only; routes retain authorization. */
function ownerLayoutStart(string $title, string $activePage, bool $themeCapable = false, array $workspace = []): void
{
    $isEvidenceHub = $activePage === 'evidence_hub';
    $isDashboard = $activePage === 'dashboard';
    $isSettings = $activePage === 'settings';
    $isProfile = $activePage === 'profile';
    $isProjects = $activePage === 'projects';
    $isExperiences = $activePage === 'experiences';
    $isEvidenceHubOverview = $isEvidenceHub && $title === 'Evidence Hub';
    $locale = ($workspace['locale'] ?? ownerLocale()) === 'en' ? 'en' : 'ar';
    $workspace['locale'] = $locale;
    if ($isEvidenceHub) {
        // Evidence Hub owns its copy and receives the locale explicitly.
        unset($GLOBALS['owner_workspace_locale']);
        ownerPersistLocale($locale);
    } else {
        $GLOBALS['owner_workspace_locale'] = $locale;
        ownerPersistLocale($locale);
        $title = ownerText($title);
    }
    ?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo $isEvidenceHub ? ownerEscapeHtml(evidenceHubText('Evidence Hub — My Portfolio', $locale)) : ownerEscapeHtml($title) . ' — Ather Career'; ?></title>
    <link rel="icon" type="image/png" href="/assets/images/ather-navbar-logo.png">
    <?php if ($isEvidenceHub): ?>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/evidence_hub.css'); ?>">
    <?php else: ?>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/owner_workspace.css'); ?>">
    <?php if ($isDashboard): ?><link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/owner_dashboard.css'); ?>"><?php endif; ?>
    <?php if ($isSettings): ?><link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/owner_settings.css'); ?>"><?php endif; ?>
    <?php endif; ?>
    <?php if ($isProfile): ?><link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/owner_profile.css'); ?>"><?php endif; ?>
    <?php if ($isProjects): ?><link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/owner_projects.css'); ?>"><?php endif; ?>
    <?php if ($isExperiences): ?><link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/owner_experiences.css'); ?>"><?php endif; ?>
    <?php if ($activePage === 'publication' || $activePage === 'messages'): ?><link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/owner_' . $activePage . '.css'); ?>"><?php endif; ?>
    <script src="<?php echo versionedAssetUrl('assets/js/admin.js'); ?>" defer></script>
    <?php if ($isExperiences): ?><script src="<?php echo versionedAssetUrl('assets/js/owner_experiences.js'); ?>" defer></script><?php endif; ?>
    <?php if ($isProjects): ?><script src="<?php echo versionedAssetUrl('assets/js/owner_projects.js'); ?>" defer></script><?php endif; ?>
    <?php if ($isProfile): ?><script src="<?php echo versionedAssetUrl('assets/js/owner_profile.js'); ?>" defer></script><?php endif; ?>
    <?php if ($isEvidenceHub): ?>
    <script src="<?php echo versionedAssetUrl('assets/js/evidence_hub.js'); ?>" defer></script>
    <?php endif; ?>
</head>
<body class="<?php echo $isEvidenceHub ? 'evidence-hub-page' : 'owner-workspace-page' . ($isDashboard ? ' owner-dashboard-page' : '') . ($isSettings ? ' owner-settings-page' : ''); ?>"<?php if ($isEvidenceHub): ?> data-owner-confirm-title="<?php echo ownerEscapeHtml(evidenceHubText('Are you sure?', $locale)); ?>" data-owner-confirm-cancel="<?php echo ownerEscapeHtml(evidenceHubText('Cancel', $locale)); ?>" data-owner-confirm-action="<?php echo ownerEscapeHtml(evidenceHubText('Continue', $locale)); ?>"<?php else: ?> data-owner-confirm-title="<?php echo ownerEscapeHtml(ownerText('Are you sure?')); ?>" data-owner-confirm-cancel="<?php echo ownerEscapeHtml(ownerText('Cancel')); ?>" data-owner-confirm-action="<?php echo ownerEscapeHtml(ownerText('Continue')); ?>"<?php endif; ?>>
<?php if ($themeCapable): ?>
<script src="<?php echo versionedAssetUrl('assets/js/owner_theme.js'); ?>"></script>
<?php endif; ?>
<div class="admin-layout<?php echo $isEvidenceHub ? ' evidence-hub-layout' : ''; ?>">
    <?php ownerNavigation($activePage, $workspace); ?>
    <main class="admin-content" id="main-content" tabindex="-1">
        <?php if ($isEvidenceHub): ?>
        <?php $hubText = static fn (string $english): string => ownerEscapeHtml(evidenceHubText($english, $locale)); $alternate = $locale === 'ar' ? 'en' : 'ar'; ?>
        <header class="evidence-hub-topbar" aria-label="<?php echo $hubText('Evidence Hub application chrome'); ?>">
            <div class="evidence-hub-topbar-inner">
                <span class="evidence-hub-topbar-context"><?php echo $hubText('Evidence Hub'); ?></span>
                <?php if ($isEvidenceHubOverview): ?>
                <nav class="evidence-hub-section-nav" aria-label="<?php echo $hubText('Evidence Hub sections'); ?>"><a href="#next-steps"><?php echo $hubText('Next steps'); ?></a><a href="#overview"><?php echo $hubText('Overview'); ?></a><a href="#project-evidence"><?php echo $hubText('Project evidence'); ?></a><a href="#technologies"><?php echo $hubText('Technologies'); ?></a><a href="#progress"><?php echo $hubText('Progress'); ?></a></nav>
                <?php endif; ?>
                <div class="evidence-hub-themes" role="group" aria-label="<?php echo $hubText('Appearance'); ?>">
                    <button type="button" data-owner-theme="light" hidden aria-label="<?php echo $hubText('Light appearance'); ?>" aria-pressed="false"><?php echo evidenceHubOwnerIcon('sun'); ?></button>
                    <button type="button" data-owner-theme="dark" hidden aria-label="<?php echo $hubText('Dark appearance'); ?>" aria-pressed="false"><?php echo evidenceHubOwnerIcon('moon'); ?></button>
                </div>
                <a class="evidence-hub-language" href="<?php echo ownerEscapeHtml(ownerLanguageUrl($alternate)); ?>" lang="<?php echo $alternate; ?>" hreflang="<?php echo $alternate; ?>" aria-label="<?php echo $hubText('Interface language'); ?>: <?php echo ownerEscapeHtml(evidenceHubLanguageName($alternate)); ?>"><?php echo ownerEscapeHtml(evidenceHubLanguageName($alternate)); ?></a>
            </div>
        </header>
        <?php else: ownerWorkspaceToolbar($locale, $activePage); endif; ?>
        <section>
    <?php
}

function ownerNavigation(string $activePage, array $workspace = []): void
{
    if ($activePage === 'evidence_hub') {
        ownerEvidenceHubNavigation(($workspace['locale'] ?? 'en') === 'ar' ? 'ar' : 'en');
        return;
    }
    $locale = ($workspace['locale'] ?? ownerLocale()) === 'en' ? 'en' : 'ar';
    $copy = static fn (string $key): string => ownerDashboardCopy($key, $locale);
    // One definition of destinations and groups: Overview, two groups, then Evidence Hub.
    $links = [
        'dashboard' => ['/owner.php', 'owner-house'],
        'profile' => ['/owner_profile.php', 'user-round'],
        'projects' => ['/owner_projects.php', 'owner-folder-open'],
        'experiences' => ['/owner_experiences.php', 'owner-briefcase-business'],
        'publication' => ['/owner_publication.php', 'owner-send'],
        'messages' => ['/owner_messages.php', 'owner-mail'],
        'evidence_hub' => ['/owner/evidence-hub', 'book-open-check'],
        'settings' => ['/owner_settings.php', 'settings'],
        'help' => ['/owner_settings.php', 'circle-help'],
    ];
    $groups = ['group_portfolio' => ['profile', 'projects', 'experiences'], 'group_sharing' => ['publication', 'messages']];
    $accountName = trim((string) ($workspace['account_name'] ?? ''));
    $initials = mb_strtoupper(mb_substr((string) (preg_split('/\s+/u', $accountName)[0] ?? ''), 0, 2, 'UTF-8'), 'UTF-8');
    $unread = ownerWorkspaceUnreadMessageCount();
    $item = static function (string $key) use ($activePage, $copy, $links, $locale, $unread): void {
        [$href, $icon] = $links[$key];
        $current = $activePage === $key;
        $label = $key === 'messages' && $unread !== null && $unread > 0 ? sprintf($copy('messages_unread'), $unread) : $copy('nav_' . $key);
        ?><a aria-label="<?php echo ownerEscapeHtml($label); ?>" title="<?php echo $copy('nav_' . $key); ?>" class="owner-nav-item<?php echo $current ? ' active' : ''; ?>" href="<?php echo $href; ?><?php echo $key === 'evidence_hub' ? '' : '?lang=' . $locale; ?><?php echo $key === 'help' ? '#help' : ''; ?>"<?php echo $current ? ' aria-current="page"' : ''; ?>><?php echo evidenceHubOwnerIcon($icon); ?><span><?php echo $copy('nav_' . $key); ?></span><?php if ($key === 'messages' && $unread !== null && $unread > 0): ?><span class="owner-unread-badge" aria-hidden="true"><?php echo $unread; ?></span><?php endif; ?><?php if ($key === 'evidence_hub'): ?><span class="owner-nav-badge"><?php echo $copy('private'); ?></span><?php endif; ?></a><?php
    };
    ?>
    <a class="skip-link admin-skip-link" href="#main-content"><?php echo $copy('skip'); ?></a>
    <aside class="admin-sidebar" id="owner-sidebar">
        <div class="owner-brand-row">
        <a class="admin-brand" href="/owner.php?lang=<?php echo $locale; ?>" aria-label="Ather"><span class="owner-wordmark">ATHER</span><span class="owner-compact-wordmark" aria-hidden="true">A<span>•</span></span><span class="owner-brand-caption" lang="en">CAREER WORKSPACE</span></a>
        <button class="owner-sidebar-toggle" type="button" data-owner-sidebar-toggle hidden aria-controls="owner-sidebar" aria-expanded="true" aria-label="<?php echo $copy('collapse_sidebar'); ?>" data-label-collapse="<?php echo $copy('collapse_sidebar'); ?>" data-label-expand="<?php echo $copy('expand_sidebar'); ?>"><?php echo evidenceHubOwnerIcon('panel-left-close'); ?></button>
        <button class="owner-nav-toggle" type="button" data-owner-nav-toggle aria-controls="owner-navigation" aria-expanded="false" aria-label="<?php echo $copy('menu'); ?>"><span class="owner-menu-open-icon"><?php echo evidenceHubOwnerIcon('menu'); ?></span><span class="owner-menu-close-icon"><?php echo evidenceHubOwnerIcon('x'); ?></span></button>
        </div>
        <div class="owner-navigation" id="owner-navigation">
            <nav class="owner-nav" aria-label="<?php echo $copy('navigation'); ?>">
                <?php $item('dashboard'); ?>
                <?php foreach ($groups as $group => $keys): ?>
                <details class="owner-nav-group" open>
                    <summary><span><?php echo $copy($group); ?></span><?php echo evidenceHubOwnerIcon('chevron-up'); ?></summary>
                    <?php foreach ($keys as $key) { $item($key); } ?>
                </details>
                <?php endforeach; ?>
                <div class="owner-nav-separator" aria-hidden="true"></div>
                <?php $item('evidence_hub'); ?>
                <div class="owner-nav-separator" aria-hidden="true"></div>
                <div class="owner-nav-utilities"><?php $item('settings'); $item('help'); ?></div>
            </nav>
            <div class="sidebar-footer">
                <details class="owner-account-menu">
                    <summary class="owner-account" aria-label="<?php echo ownerEscapeHtml($copy('account_options') . ($accountName === '' ? '' : ': ' . $accountName)); ?>"><span class="owner-avatar" aria-hidden="true"><?php echo ownerEscapeHtml($initials); ?></span><span class="owner-account-copy"><bdi><?php echo ownerEscapeHtml($accountName !== '' ? $accountName : $copy('account')); ?></bdi><small><?php echo $copy('personal_account'); ?></small></span><?php echo evidenceHubOwnerIcon('chevrons-up-down'); ?></summary>
                    <div class="owner-account-popover">
                        <span><?php echo $copy('account_label'); ?></span>
                        <strong dir="auto"><?php echo ownerEscapeHtml($accountName !== '' ? $accountName : $copy('account')); ?></strong>
                        <a href="/owner_profile.php?lang=<?php echo $locale; ?>"><?php echo evidenceHubOwnerIcon('user'); ?><?php echo $copy('my_profile'); ?></a>
                        <?php ownerAccountSelectionForm(false, $locale); ?>
                        <form class="sidebar-logout-form" method="POST" action="/owner_logout.php" data-owner-form>
                            <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                            <button class="sidebar-logout-button" type="submit" data-pending-label="<?php echo $copy('logout_pending'); ?>"><?php echo evidenceHubOwnerIcon('log-out'); ?><?php echo $copy('sign_out'); ?></button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </aside>
    <?php
}

function ownerWorkspaceToolbar(string $locale, string $activePage = ''): void
{
    $copy = static fn (string $key): string => ownerDashboardCopy($key, $locale);

    $crumb = in_array($activePage, ['dashboard', 'profile', 'projects', 'experiences', 'messages', 'publication', 'settings'], true) ? $copy('crumb_' . $activePage) : '';
    ?>
    <header class="owner-toolbar" aria-label="<?php echo ownerEscapeHtml(ownerText('Workspace tools')); ?>">
        <nav class="owner-breadcrumb" aria-label="<?php echo $copy('breadcrumb'); ?>"><span><?php echo $copy('workspace'); ?></span><?php if ($crumb !== ''): ?><span aria-hidden="true">/</span><span aria-current="page"><?php echo $crumb; ?></span><?php endif; ?></nav>
        <?php if ($activePage === 'dashboard'): ?>
        <a class="owner-preview" href="/owner_publication.php?lang=<?php echo $locale; ?>"><?php echo evidenceHubOwnerIcon('owner-link'); ?><span><?php echo $copy('publish_share'); ?></span></a>
        <?php else: ?>
        <a class="owner-preview" href="/owner_preview.php"><?php echo evidenceHubOwnerIcon('eye'); ?><span><?php echo $copy('preview_portfolio'); ?></span></a>
        <?php endif; ?>

        <div class="owner-themes" role="group" aria-label="<?php echo ownerEscapeHtml(ownerText('Appearance')); ?>">
            <?php foreach (['dark' => 'moon', 'light' => 'sun'] as $theme => $icon): ?>
            <button type="button" data-owner-theme="<?php echo $theme; ?>" aria-label="<?php echo $copy($theme); ?>" aria-pressed="false"><?php echo evidenceHubOwnerIcon($icon); ?></button>
            <?php endforeach; ?>
        </div>
    </header>
    <?php
}

function ownerEvidenceHubNavigation(string $locale = 'en'): void
{
    $t = static fn (string $english): string => ownerEscapeHtml(evidenceHubText($english, $locale));
    $dashboardHref = '/owner.php';
    $workspaceLinks = [
        ['overview', 'Overview', 'layout-dashboard', '/owner.php'],
        ['projects', 'Projects', 'folder-kanban', '/owner_projects.php'],
        ['evidence_hub', 'Evidence Hub', 'blocks', '/owner/evidence-hub'],
        ['credentials', 'Credentials', 'badge-check', null],
    ];
    ?>
    <a class="skip-link admin-skip-link" href="#main-content"><?php echo $t('Skip to main content'); ?></a>
    <button class="evidence-hub-mobile-toggle" id="evidence-hub-mobile-toggle" type="button" aria-controls="evidence-hub-mobile-drawer" aria-expanded="false" aria-label="<?php echo $t('Open navigation'); ?>" data-sidebar-tooltip="<?php echo $t('Open navigation'); ?>">
        <span class="visually-hidden"><?php echo $t('Open navigation'); ?></span><?php echo evidenceHubOwnerIcon('menu'); ?>
    </button>
    <aside class="admin-sidebar evidence-hub-sidebar" id="evidence-hub-mobile-drawer" aria-label="<?php echo $t('Owner navigation'); ?>">
        <div class="evidence-hub-sidebar-head">
            <a class="admin-brand evidence-hub-brand" href="<?php echo ownerEscapeHtml($dashboardHref); ?>" aria-label="<?php echo $t('Ather — Evidence Hub'); ?>" data-sidebar-tooltip="<?php echo $t('Ather — Evidence Hub'); ?>">
                <span class="evidence-hub-brand-logo" aria-hidden="true">ATHER</span>
                <span class="evidence-hub-brand-copy"><small><?php echo $t('EVIDENCE WORKSPACE'); ?></small></span>
            </a>
            <a class="evidence-hub-sidebar-back" href="<?php echo ownerEscapeHtml($dashboardHref); ?>" aria-label="<?php echo $t('Back to dashboard'); ?>" data-sidebar-tooltip="<?php echo $t('Back to dashboard'); ?>" data-sidebar-tooltip-always="true">
                <?php echo evidenceHubOwnerIcon('arrow-left'); ?><span class="visually-hidden"><?php echo $t('Back to dashboard'); ?></span>
            </a>
            <button class="evidence-hub-sidebar-toggle" id="evidence-hub-sidebar-toggle" type="button" aria-controls="evidence-hub-mobile-drawer" aria-expanded="true" aria-label="<?php echo $t('Collapse navigation'); ?>" data-sidebar-tooltip="<?php echo $t('Collapse navigation'); ?>" data-sidebar-tooltip-always="true" data-label-collapse="<?php echo $t('Collapse navigation'); ?>" data-label-expand="<?php echo $t('Expand navigation'); ?>">
                <span class="visually-hidden"><?php echo $t('Collapse navigation'); ?></span><span data-evidence-hub-panel-state="close"><?php echo evidenceHubOwnerIcon('panel-left-close'); ?></span><span data-evidence-hub-panel-state="open" hidden><?php echo evidenceHubOwnerIcon('panel-left-open'); ?></span>
            </button>
            <button class="evidence-hub-mobile-close" id="evidence-hub-mobile-close" type="button" aria-label="<?php echo $t('Close navigation'); ?>" data-sidebar-tooltip="<?php echo $t('Close navigation'); ?>">
                <?php echo evidenceHubOwnerIcon('x'); ?>
            </button>
        </div>
        <nav aria-label="<?php echo $t('Evidence Hub workspace'); ?>">
            <span class="nav-group-label evidence-hub-sidebar-label"><?php echo $t('WORKSPACE'); ?></span>
            <?php foreach ($workspaceLinks as [$key, $label, $icon, $href]): ?>
                <?php if ($href !== null): ?>
                <a class="<?php echo $key === 'evidence_hub' ? 'active' : ''; ?>" href="<?php echo ownerEscapeHtml($href); ?>"<?php echo $key === 'evidence_hub' ? ' aria-current="page"' : ''; ?> data-sidebar-tooltip="<?php echo $t($label); ?>">
                    <?php echo evidenceHubOwnerIcon($icon); ?><span class="evidence-hub-sidebar-label"><?php echo $t($label); ?></span>
                </a>
                <?php else: ?>
                <button class="evidence-hub-future-link" type="button" aria-disabled="true" tabindex="0" title="<?php echo $t('Coming soon'); ?>" data-sidebar-tooltip="<?php echo $t('Coming soon'); ?>" data-sidebar-tooltip-always="true">
                    <?php echo evidenceHubOwnerIcon($icon); ?><span class="evidence-hub-sidebar-label"><?php echo $t($label); ?></span>
                </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer evidence-hub-sidebar-footer">
            <details class="evidence-hub-account-menu">
                <summary class="evidence-hub-owner-context" aria-label="<?php echo $t('Account options'); ?>" aria-controls="evidence-hub-account-actions" data-sidebar-tooltip="<?php echo $t('Account options'); ?>">
                    <?php echo evidenceHubOwnerIcon('user-round'); ?>
                    <span class="evidence-hub-owner-context-copy"><span><?php echo $t('Portfolio owner'); ?></span><span class="evidence-hub-private-workspace"><?php echo evidenceHubOwnerIcon('lock-keyhole'); ?><span><?php echo $t('Private workspace'); ?></span></span></span>
                    <span class="evidence-hub-account-indicator" aria-hidden="true"></span>
                </summary>
                <div class="evidence-hub-account-panel" id="evidence-hub-account-actions" role="group" aria-label="<?php echo $t('Account actions'); ?>">
                    <?php ownerAccountSelectionForm(true, $locale === 'ar' ? 'ar' : null); ?>
                    <form class="sidebar-logout-form" method="POST" action="/owner_logout.php" data-owner-form>
                        <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                        <button class="sidebar-logout-button evidence-hub-sign-out" type="submit" data-pending-label="<?php echo $t('Logging out…'); ?>" aria-label="<?php echo $t('Log out'); ?>"><?php echo evidenceHubOwnerIcon('log-out'); ?><span class="evidence-hub-account-action-label"><?php echo $t('Log out'); ?></span></button>
                    </form>
                </div>
            </details>
        </div>
    </aside>
    <div class="evidence-hub-mobile-backdrop" id="evidence-hub-mobile-backdrop" aria-hidden="true" hidden></div>
    <?php
}

function ownerAccountSelectionForm(bool $evidenceHub = false, ?string $locale = null): void
{
    ?>
    <form class="sidebar-logout-form" method="POST" action="/owner_switch_account.php" data-owner-form>
        <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
        <button class="sidebar-logout-button<?php echo $evidenceHub ? ' evidence-hub-sign-out' : ''; ?>" type="submit" data-pending-label="<?php echo $locale !== null ? ownerDashboardCopy('switch_pending', $locale) : 'Opening account selection…'; ?>"><?php if ($evidenceHub): ?><?php echo evidenceHubOwnerIcon('user-round'); ?><span class="evidence-hub-account-action-label"><?php endif; ?><?php if ($locale !== null): ?><?php if (!$evidenceHub): ?><?php echo evidenceHubOwnerIcon('users-round'); ?><?php endif; ?><?php echo ownerDashboardCopy('switch', $locale); ?><?php else: ?>Use another account<?php if (!$evidenceHub): ?> <span lang="ar" dir="rtl">استخدام حساب آخر</span><?php endif; ?><?php endif; ?><?php if ($evidenceHub): ?></span><?php endif; ?></button>
    </form>
    <?php
}

function evidenceHubOwnerIcon(string $name): string
{
    /*
     * Lucide icon paths, Copyright (c) 2022 Lucide Contributors.
     * Permission to use, copy, modify, and/or distribute this software for any
     * purpose with or without fee is hereby granted, provided that this notice
     * and the permission notice appear in all copies. THE SOFTWARE IS PROVIDED
     * "AS IS" AND THE COPYRIGHT HOLDERS DISCLAIM ALL WARRANTIES AND LIABILITY.
     */
    $paths = [
        'monitor' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/>',
        'sliders-horizontal' => '<path d="M21 4h-7m-4 0H3m18 8h-9m-4 0H3m18 8h-5m-4 0H3M14 2v4m-6 4v4m8 4v4"/>',
        'owner-house' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'owner-folder-open' => '<path d="m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2"/>',
        'owner-briefcase-business' => '<path d="M12 12h.01"/><path d="M16 6V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M22 13a18.15 18.15 0 0 1-20 0"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
        'owner-send' => '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>',
        'owner-mail' => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/>',
        'owner-link' => '<path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><path d="M8 12h8"/>',
        'folder' => '<path d="M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
        'mail' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m3 5 9 8 9-8"/>',
        'settings' => '<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/>',
        'plus' => '<path d="M12 4v16M4 12h16"/>',
        'switch' => '<path d="M4 8h16l-4-4M20 16H4l4 4"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'arrow-left' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'panel-left-close' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18m7-6-3-3 3-3"/>',
        'panel-left-open' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18m6-6 3-3-3-3"/>',
        'blocks' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'badge-check' => '<path d="M3.85 8.62a4 4 0 0 1 4.77-4.77 4 4 0 0 1 6.76 0 4 4 0 0 1 4.77 4.77 4 4 0 0 1 0 6.76 4 4 0 0 1-4.77 4.77 4 4 0 0 1-6.76 0 4 4 0 0 1-4.77-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/>',
        'layout-dashboard' => '<rect width="7" height="8" x="3" y="3" rx="2"/><rect width="7" height="5" x="14" y="3" rx="2"/><rect width="7" height="8" x="14" y="13" rx="2"/><rect width="7" height="5" x="3" y="16" rx="2"/>',
        'folder-kanban' => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H10l2 2h5.5A2.5 2.5 0 0 1 20 9.5v8A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5Z"/><path d="M8 11v4m4-4v2m4-2v4"/>',
        'home' => '<path d="m4 10 8-6 8 6v9a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1Z"/>',
        'user' => '<circle cx="12" cy="8" r="3"/><path d="M5 20c.6-4 2.9-6 7-6s6.4 2 7 6"/>',
        'user-round' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
        'lock-keyhole' => '<circle cx="12" cy="16" r="1"/><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3"/>',
        'briefcase' => '<rect width="16" height="12" x="4" y="7" rx="2"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7m-11 5h16m-10 0h4"/>',
        'message' => '<path d="M5 5.5A2.5 2.5 0 0 1 7.5 3h9A2.5 2.5 0 0 1 19 5.5v7a2.5 2.5 0 0 1-2.5 2.5H11l-4 3v-3H7.5A2.5 2.5 0 0 1 5 12.5Z"/><path d="M9 9h6"/>',
        'send' => '<path d="m20 4-7.5 16-2.5-7.5L4 10Z"/><path d="M10 12.5 20 4"/>',
        'eye' => '<path d="M3 12s3.2-5 9-5 9 5 9 5-3.2 5-9 5-9-5-9-5Z"/><circle cx="12" cy="12" r="2.5"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'moon' => '<path d="M19.5 15.2A8 8 0 0 1 8.8 4.5 8 8 0 1 0 19.5 15.2Z"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'spark' => '<path d="m12 3 1.6 5.4L19 10l-5.4 1.6L12 17l-1.6-5.4L5 10l5.4-1.6Z"/><path d="m18 17 .7 2.3L21 20l-2.3.7L18 23l-.7-2.3L15 20l2.3-.7Z"/>',
        'document-check' => '<path d="M7 3h7l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5m-9 7 2 2 4-4"/>',
        'nodes' => '<circle cx="6" cy="6" r="2"/><circle cx="18" cy="7" r="2"/><circle cx="12" cy="18" r="2"/><path d="m7.7 7.1 2.8 8.2m5.9-7-2.9 8"/>',
        'target' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2"/>',
        'check' => '<path d="m5 12 4.2 4L19 6"/>',
        'alert' => '<path d="M12 4 3.7 19a1.3 1.3 0 0 0 1.1 2h14.4a1.3 1.3 0 0 0 1.1-2Z"/><path d="M12 9v4m0 4h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/>',
        'dismiss' => '<circle cx="12" cy="12" r="8"/><path d="m9 9 6 6m0-6-6 6"/>',
        'arrow' => '<path d="M5 12h13m-5-5 5 5-5 5"/>',
        'chevron-up' => '<path d="m18 15-6-6-6 6"/>',
        'chevrons-up-down' => '<path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/>',
        'users-round' => '<path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/>',
        'book-open-check' => '<path d="M12 5v16"/><path d="m16 12 2 2 4-4"/><path d="M22 6V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2h4.001A2 2 0 0022 17v-1.344"/>',
        'user-round-pen' => '<path d="M2 21a8 8 0 0 1 10.821-7.487"/><path d="M21.378 16.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/><circle cx="10" cy="8" r="5"/>',
        'panels-top-left' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
        'circle-help' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
        'arrow-up-left' => '<path d="M7 17V7h10"/><path d="M17 17 7 7"/>',
        'pencil' => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h4"/>',
        'circle-check' => '<circle cx="12" cy="12" r="10"/><path d="m16 9-5.5 5.5L8 12"/>',
        'eye-off' => '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/>',
        'file-pen-line' => '<path d="M14.364 13.634a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506l4.013-4.009a1 1 0 0 0-3.004-3.004z"/><path d="M14.487 7.858A1 1 0 0 1 14 7V2"/><path d="M20 19.645V20a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l2.516 2.516"/><path d="M8 18h1"/>',
    ];
    $path = $paths[$name] ?? $paths['spark'];
    return '<svg class="evidence-hub-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

function ownerLayoutEnd(): void
{
    ?>
        </section>
    </main>
</div>
</body>
</html>
    <?php
}
