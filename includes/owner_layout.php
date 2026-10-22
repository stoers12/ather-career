<?php

declare(strict_types=1);

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/presentation.php';

function ownerEscapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/owner_form_feedback.php';

/** @param array{locale?:string, account_name?:string} $dashboard Dashboard-only presentation context. */
function ownerLayoutStart(string $title, string $activePage, bool $themeCapable = false, array $dashboard = []): void
{
    $isDashboard = $activePage === 'dashboard' && $dashboard !== [];
    if ($isDashboard) require_once __DIR__ . '/owner_dashboard_copy.php';
    $locale = $isDashboard && ($dashboard['locale'] ?? null) !== 'en' ? 'ar' : 'en';
    $isEvidenceHub = $activePage === 'evidence_hub';
    $isEvidenceHubOverview = $isEvidenceHub && $title === 'Evidence Hub';
    ?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo $isDashboard ? ownerEscapeHtml($title) . ' — Ather Career' : ($isEvidenceHub ? 'Evidence Hub — My Portfolio' : ownerEscapeHtml($title) . ' - My Portfolio'); ?></title>
    <link rel="icon" type="image/png" href="/assets/images/ather-navbar-logo.png">
    <?php if ($isDashboard): ?>
    <link rel="stylesheet" href="/<?php echo versionedAssetUrl('owner_dashboard.css'); ?>">
    <?php else: ?>
    <link rel="stylesheet" href="/<?php echo versionedAssetUrl('style.css'); ?>">
    <link rel="stylesheet" href="/<?php echo versionedAssetUrl('admin.css'); ?>">
    <?php endif; ?>
    <?php if ($isEvidenceHub): ?>
    <link rel="stylesheet" href="/<?php echo versionedAssetUrl('evidence_hub.css'); ?>">
    <?php endif; ?>
    <script src="/<?php echo versionedAssetUrl('admin.js'); ?>" defer></script>
    <?php if ($isEvidenceHub): ?>
    <script src="/<?php echo versionedAssetUrl('evidence_hub.js'); ?>" defer></script>
    <?php endif; ?>
</head>
<body<?php echo $isDashboard ? ' class="owner-dashboard-page"' : ($isEvidenceHub ? ' class="evidence-hub-page"' : ''); ?>>
<?php if ($themeCapable): ?>
<script src="/<?php echo versionedAssetUrl('owner_theme.js'); ?>"></script>
<?php endif; ?>
<div class="admin-layout<?php echo $isEvidenceHub ? ' evidence-hub-layout' : ''; ?>">
    <?php ownerNavigation($activePage, $dashboard); ?>
    <main class="admin-content" id="main-content" tabindex="-1">
        <?php if ($isEvidenceHub): ?>
        <header class="evidence-hub-topbar" aria-label="Evidence Hub application chrome">
            <div class="evidence-hub-topbar-inner">
                <span class="evidence-hub-topbar-context">Evidence Hub</span>
                <?php if ($isEvidenceHubOverview): ?>
                <nav class="evidence-hub-section-nav" aria-label="Evidence Hub sections"><a href="#next-steps">Next steps</a><a href="#overview">Overview</a><a href="#project-evidence">Project evidence</a><a href="#technologies">Technologies</a><a href="#progress">Progress</a></nav>
                <?php endif; ?>
            </div>
        </header>
        <?php endif; ?>
        <section>
    <?php
}

function ownerNavigation(string $activePage, array $dashboard = []): void
{
    if ($activePage === 'evidence_hub') {
        ownerEvidenceHubNavigation();
        return;
    }
    $isDashboard = $activePage === 'dashboard' && $dashboard !== [];
    $locale = ($dashboard['locale'] ?? null) === 'en' ? 'en' : 'ar';
    $copy = static fn (string $key): string => ownerDashboardCopy($key, $locale);
    $icons = ['dashboard' => 'home', 'profile' => 'user', 'projects' => 'folder', 'experiences' => 'briefcase', 'messages' => 'mail', 'evidence_hub' => 'document-check', 'publication' => 'settings'];
    $links = [
        'dashboard' => ['/owner.php', 'Dashboard'],
        'profile' => ['/owner_profile.php', 'Personal info'],
        'projects' => ['/owner_projects.php', 'Projects'],
        'experiences' => ['/owner_experiences.php', 'Experience'],
        'messages' => ['/owner_messages.php', 'Messages'],
        'evidence_hub' => ['/owner/evidence-hub', 'Evidence Hub'],
        'publication' => ['/owner_publication.php', 'Publication'],
    ];
    if ($isDashboard) $links['dashboard'][0] .= '?lang=' . $locale;
    ?>
    <a class="skip-link admin-skip-link" href="#main-content"><?php echo $isDashboard ? $copy('skip') : 'Skip to main content'; ?></a>
    <aside class="admin-sidebar">
        <a class="admin-brand" href="/owner.php<?php echo $isDashboard ? '?lang=' . $locale : ''; ?>"><?php if ($isDashboard): ?><img src="/assets/images/ather-navbar-logo.png" alt="Ather" width="880" height="155"><span>Career</span><?php else: ?><span class="brand-mark">P</span><span>Portfolio Owner</span><?php endif; ?></a>
        <?php if ($isDashboard): ?><button class="dashboard-nav-toggle" type="button" data-owner-nav-toggle aria-controls="dashboard-navigation" aria-expanded="false"><?php echo evidenceHubOwnerIcon('menu'); ?><?php echo $copy('menu'); ?></button><?php endif; ?>
        <?php if ($isDashboard): ?><div class="dashboard-navigation" id="dashboard-navigation"><?php endif; ?>
        <nav aria-label="<?php echo $isDashboard ? $copy('navigation') : 'Owner navigation'; ?>">
            <?php if (!$isDashboard): ?><span class="nav-group-label">Workspace</span><?php endif; ?>
            <?php foreach ($links as $key => [$href, $label]): ?>
                <a class="<?php echo $activePage === $key ? 'active' : ''; ?>" href="<?php echo $href; ?>"<?php echo $activePage === $key ? ' aria-current="page"' : ''; ?>><?php if ($isDashboard): ?><?php echo evidenceHubOwnerIcon($icons[$key]); ?><span><?php echo $copy($key); ?></span><?php else: ?><?php echo ownerEscapeHtml($label); ?><?php endif; ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <?php if ($isDashboard): ?><div class="dashboard-account"><?php echo evidenceHubOwnerIcon('user'); ?><bdi><?php echo ownerEscapeHtml($dashboard['account_name'] !== '' ? $dashboard['account_name'] : $copy('account')); ?></bdi></div><?php else: ?><a href="/owner_preview.php">Private Preview</a><?php endif; ?>
            <?php ownerAccountSelectionForm(false, $isDashboard ? $locale : null); ?>
            <form class="sidebar-logout-form" method="POST" action="/owner_logout.php" data-owner-form>
                <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                <button class="sidebar-logout-button" type="submit" data-pending-label="<?php echo $isDashboard ? $copy('logout_pending') : 'Signing out…'; ?>"><?php if ($isDashboard) echo evidenceHubOwnerIcon('log-out'); ?><?php echo $isDashboard ? $copy('logout') : 'Logout'; ?></button>
            </form>
        </div>
        <?php if ($isDashboard): ?></div><?php endif; ?>
    </aside>
    <?php
}

function ownerEvidenceHubNavigation(): void
{
    $dashboardHref = '/owner.php';
    $workspaceLinks = [
        ['overview', 'Overview', 'layout-dashboard', null],
        ['projects', 'Projects', 'folder-kanban', null],
        ['evidence_hub', 'Evidence Hub', 'blocks', '/owner/evidence-hub'],
        ['credentials', 'Credentials', 'badge-check', null],
    ];
    ?>
    <a class="skip-link admin-skip-link" href="#main-content">Skip to main content</a>
    <button class="evidence-hub-mobile-toggle" id="evidence-hub-mobile-toggle" type="button" aria-controls="evidence-hub-mobile-drawer" aria-expanded="false" aria-label="Open navigation" data-sidebar-tooltip="Open navigation">
        <span class="visually-hidden">Open navigation</span><?php echo evidenceHubOwnerIcon('menu'); ?>
    </button>
    <aside class="admin-sidebar evidence-hub-sidebar" id="evidence-hub-mobile-drawer" aria-label="Owner navigation">
        <div class="evidence-hub-sidebar-head">
            <a class="admin-brand evidence-hub-brand" href="<?php echo ownerEscapeHtml($dashboardHref); ?>" aria-label="Ather — Evidence Hub" data-sidebar-tooltip="Ather — Evidence Hub">
                <img class="evidence-hub-brand-logo" src="/assets/images/ather-navbar-logo.png" alt="" width="880" height="155" decoding="async">
                <span class="evidence-hub-brand-copy"><small>Evidence Hub</small></span>
            </a>
            <a class="evidence-hub-sidebar-back" href="<?php echo ownerEscapeHtml($dashboardHref); ?>" aria-label="Back to dashboard" data-sidebar-tooltip="Back to dashboard" data-sidebar-tooltip-always="true">
                <?php echo evidenceHubOwnerIcon('arrow-left'); ?><span class="visually-hidden">Back to dashboard</span>
            </a>
            <button class="evidence-hub-sidebar-toggle" id="evidence-hub-sidebar-toggle" type="button" aria-controls="evidence-hub-mobile-drawer" aria-expanded="true" aria-label="Collapse navigation" data-sidebar-tooltip="Collapse navigation" data-sidebar-tooltip-always="true">
                <span class="visually-hidden">Collapse navigation</span><span data-evidence-hub-panel-state="close"><?php echo evidenceHubOwnerIcon('panel-left-close'); ?></span><span data-evidence-hub-panel-state="open" hidden><?php echo evidenceHubOwnerIcon('panel-left-open'); ?></span>
            </button>
            <button class="evidence-hub-mobile-close" id="evidence-hub-mobile-close" type="button" aria-label="Close navigation" data-sidebar-tooltip="Close navigation">
                <?php echo evidenceHubOwnerIcon('x'); ?>
            </button>
        </div>
        <nav aria-label="Evidence Hub workspace">
            <span class="nav-group-label evidence-hub-sidebar-label">WORKSPACE</span>
            <?php foreach ($workspaceLinks as [$key, $label, $icon, $href]): ?>
                <?php if ($href !== null): ?>
                <a class="<?php echo $key === 'evidence_hub' ? 'active' : ''; ?>" href="<?php echo ownerEscapeHtml($href); ?>" aria-current="page" data-sidebar-tooltip="<?php echo ownerEscapeHtml($label); ?>">
                    <?php echo evidenceHubOwnerIcon($icon); ?><span class="evidence-hub-sidebar-label"><?php echo ownerEscapeHtml($label); ?></span>
                </a>
                <?php else: ?>
                <button class="evidence-hub-future-link" type="button" aria-disabled="true" tabindex="0" title="Coming soon" data-sidebar-tooltip="Coming soon" data-sidebar-tooltip-always="true">
                    <?php echo evidenceHubOwnerIcon($icon); ?><span class="evidence-hub-sidebar-label"><?php echo ownerEscapeHtml($label); ?></span>
                </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer evidence-hub-sidebar-footer">
            <div class="evidence-hub-owner-context" tabindex="0" data-sidebar-tooltip="Portfolio owner — Private workspace" data-sidebar-tooltip-always="true">
                <?php echo evidenceHubOwnerIcon('user-round'); ?>
                <span class="evidence-hub-owner-context-copy"><span>Portfolio owner</span><span class="evidence-hub-private-workspace"><?php echo evidenceHubOwnerIcon('lock-keyhole'); ?><span>Private workspace</span></span></span>
            </div>
            <?php ownerAccountSelectionForm(true); ?>
            <form class="sidebar-logout-form" method="POST" action="/owner_logout.php" data-owner-form>
                <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                <button class="sidebar-logout-button evidence-hub-sign-out" type="submit" data-pending-label="Logging out…" aria-label="Log out" data-sidebar-tooltip="Log out" data-sidebar-tooltip-always="true"><?php echo evidenceHubOwnerIcon('log-out'); ?><span class="evidence-hub-sidebar-label">Log out</span></button>
            </form>
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
        <button class="sidebar-logout-button<?php echo $evidenceHub ? ' evidence-hub-sign-out' : ''; ?>" type="submit" data-pending-label="<?php echo $locale !== null ? ownerDashboardCopy('switch_pending', $locale) : 'Opening account selection…'; ?>"<?php if ($evidenceHub): ?> data-sidebar-tooltip="Use another account" data-sidebar-tooltip-always="true"<?php endif; ?>><?php if ($evidenceHub): ?><?php echo evidenceHubOwnerIcon('user-round'); ?><span class="evidence-hub-sidebar-label"><?php endif; ?><?php if ($locale !== null): ?><?php echo evidenceHubOwnerIcon('switch'); ?><?php echo ownerDashboardCopy('switch', $locale); ?><?php else: ?>Use another account <span lang="ar" dir="rtl">استخدام حساب آخر</span><?php endif; ?><?php if ($evidenceHub): ?></span><?php endif; ?></button>
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
