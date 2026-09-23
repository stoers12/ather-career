<?php

declare(strict_types=1);

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/presentation.php';

function ownerEscapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/owner_form_feedback.php';

function ownerLayoutStart(string $title, string $activePage): void
{
    $isEvidenceHub = $activePage === 'evidence_hub';
    ?>
<!DOCTYPE html>
<html<?php echo $isEvidenceHub ? ' lang="ar" dir="rtl"' : ' lang="en"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo ownerEscapeHtml($title); ?> - My Portfolio</title>
    <link rel="icon" type="image/png" href="/assets/images/ather-navbar-logo.png">
    <link rel="stylesheet" href="/<?php echo versionedAssetUrl('style.css'); ?>">
    <link rel="stylesheet" href="/<?php echo versionedAssetUrl('admin.css'); ?>">
    <?php if ($isEvidenceHub): ?>
    <link rel="stylesheet" href="/<?php echo versionedAssetUrl('evidence_hub.css'); ?>">
    <?php endif; ?>
    <script src="/<?php echo versionedAssetUrl('admin.js'); ?>" defer></script>
    <?php if ($isEvidenceHub): ?>
    <script src="/<?php echo versionedAssetUrl('evidence_hub.js'); ?>" defer></script>
    <?php endif; ?>
</head>
<body<?php echo $isEvidenceHub ? ' class="evidence-hub-page"' : ''; ?>>
<div class="admin-layout<?php echo $isEvidenceHub ? ' evidence-hub-layout' : ''; ?>">
    <?php ownerNavigation($activePage); ?>
    <main class="admin-content" id="main-content" tabindex="-1">
        <section>
    <?php
}

function ownerNavigation(string $activePage): void
{
    if ($activePage === 'evidence_hub') {
        ownerEvidenceHubNavigation();
        return;
    }
    $links = [
        'dashboard' => ['/owner.php', 'Dashboard'],
        'profile' => ['/owner_profile.php', 'Personal info'],
        'projects' => ['/owner_projects.php', 'Projects'],
        'experiences' => ['/owner_experiences.php', 'Experience'],
        'messages' => ['/owner_messages.php', 'Messages'],
        'evidence_hub' => ['/owner/evidence-hub', 'Evidence Hub'],
        'publication' => ['/owner_publication.php', 'Publication'],
    ];
    ?>
    <a class="skip-link admin-skip-link" href="#main-content">Skip to main content</a>
    <aside class="admin-sidebar">
        <a class="admin-brand" href="/owner.php"><span class="brand-mark">P</span><span>Portfolio Owner</span></a>
        <nav aria-label="Owner navigation">
            <span class="nav-group-label">Workspace</span>
            <?php foreach ($links as $key => [$href, $label]): ?>
                <a class="<?php echo $activePage === $key ? 'active' : ''; ?>" href="<?php echo $href; ?>"<?php echo $activePage === $key ? ' aria-current="page"' : ''; ?>><?php echo ownerEscapeHtml($label); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="/owner_preview.php">Private Preview</a>
            <form class="sidebar-logout-form" method="POST" action="/owner_logout.php" data-owner-form>
                <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                <button class="sidebar-logout-button" type="submit" data-pending-label="Signing out…">Logout</button>
            </form>
        </div>
    </aside>
    <?php
}

function ownerEvidenceHubNavigation(): void
{
    $primaryLinks = [
        'evidence_hub' => ['/owner/evidence-hub', 'مركز الأدلة'],
        'projects' => ['/owner_projects.php', 'إدارة المشاريع'],
    ];
    $capabilityLinks = [
        'dashboard' => ['/owner.php', 'لوحة التحكم'],
        'profile' => ['/owner_profile.php', 'المعلومات الشخصية'],
        'experiences' => ['/owner_experiences.php', 'الخبرات'],
        'messages' => ['/owner_messages.php', 'الرسائل'],
        'publication' => ['/owner_publication.php', 'النشر'],
    ];
    $capabilityIcons = [
        'dashboard' => 'home',
        'profile' => 'user',
        'experiences' => 'briefcase',
        'messages' => 'message',
        'publication' => 'send',
    ];
    ?>
    <a class="skip-link admin-skip-link" href="#main-content">انتقل إلى المحتوى الرئيسي</a>
    <button class="evidence-hub-mobile-toggle" id="evidence-hub-mobile-toggle" type="button" aria-controls="evidence-hub-mobile-drawer" aria-expanded="false" aria-label="فتح التنقل">
        <span class="visually-hidden">فتح التنقل</span><?php echo evidenceHubOwnerIcon('menu'); ?>
    </button>
    <aside class="admin-sidebar evidence-hub-sidebar" id="evidence-hub-mobile-drawer" aria-label="تنقل المالك">
        <div class="evidence-hub-sidebar-head">
            <a class="admin-brand" href="/owner.php" aria-label="العودة إلى لوحة التحكم"><span class="brand-mark">A</span><span class="evidence-hub-sidebar-label">Ather</span></a>
            <button class="evidence-hub-sidebar-toggle" id="evidence-hub-sidebar-toggle" type="button" aria-controls="evidence-hub-mobile-drawer" aria-expanded="true" aria-label="طي الشريط الجانبي" data-sidebar-tooltip="طي الشريط الجانبي">
                <span class="visually-hidden">طي الشريط الجانبي</span><span data-evidence-hub-panel-state="close"><?php echo evidenceHubOwnerIcon('panel-right-close'); ?></span><span data-evidence-hub-panel-state="open" hidden><?php echo evidenceHubOwnerIcon('panel-right-open'); ?></span>
            </button>
            <button class="evidence-hub-mobile-close" id="evidence-hub-mobile-close" type="button" aria-label="إغلاق القائمة">
                <?php echo evidenceHubOwnerIcon('x'); ?>
            </button>
        </div>
        <nav aria-label="تنقل مركز الأدلة">
            <span class="nav-group-label evidence-hub-sidebar-label">مساحة العمل</span>
            <?php foreach ($primaryLinks as $key => [$href, $label]): ?>
                <a class="<?php echo $key === 'evidence_hub' ? 'active' : ''; ?>" href="<?php echo $href; ?>"<?php echo $key === 'evidence_hub' ? ' aria-current="page"' : ''; ?> data-sidebar-tooltip="<?php echo ownerEscapeHtml($label); ?>"><?php echo evidenceHubOwnerIcon($key === 'evidence_hub' ? 'layout-dashboard' : 'folder-kanban'); ?><span class="evidence-hub-sidebar-label"><?php echo ownerEscapeHtml($label); ?></span></a>
            <?php endforeach; ?>
            <span class="nav-group-label evidence-hub-sidebar-label">كل أدوات المالك</span>
            <?php foreach ($capabilityLinks as $key => [$href, $label]): ?>
                <a href="<?php echo $href; ?>" data-sidebar-tooltip="<?php echo ownerEscapeHtml($label); ?>"><?php echo evidenceHubOwnerIcon($capabilityIcons[$key]); ?><span class="evidence-hub-sidebar-label"><?php echo ownerEscapeHtml($label); ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="/owner_preview.php" data-sidebar-tooltip="معاينة خاصة"><?php echo evidenceHubOwnerIcon('eye'); ?><span class="evidence-hub-sidebar-label">معاينة خاصة</span></a>
            <button class="evidence-hub-theme-toggle" id="evidence-hub-theme-toggle" type="button" aria-pressed="false" aria-label="تفعيل المظهر الداكن" data-sidebar-tooltip="تفعيل المظهر الداكن"><?php echo evidenceHubOwnerIcon('moon'); ?><span class="evidence-hub-sidebar-label">المظهر</span></button>
            <form class="sidebar-logout-form" method="POST" action="/owner_logout.php" data-owner-form>
                <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                <button class="sidebar-logout-button" type="submit" data-pending-label="جارٍ تسجيل الخروج…" data-sidebar-tooltip="تسجيل الخروج"><?php echo evidenceHubOwnerIcon('log-out'); ?><span class="evidence-hub-sidebar-label">تسجيل الخروج</span></button>
            </form>
        </div>
    </aside>
    <div class="evidence-hub-mobile-backdrop" id="evidence-hub-mobile-backdrop" aria-hidden="true" hidden></div>
    <?php
}

function evidenceHubOwnerIcon(string $name): string
{
    $paths = [
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'panel-right-close' => '<rect width="18" height="18" x="3" y="3" rx="3"/><path d="M15 3v18M9 9l3 3-3 3"/>',
        'panel-right-open' => '<rect width="18" height="18" x="3" y="3" rx="3"/><path d="M15 3v18m-4-6-3 3 3 3"/>',
        'layout-dashboard' => '<rect width="7" height="8" x="3" y="3" rx="2"/><rect width="7" height="5" x="14" y="3" rx="2"/><rect width="7" height="8" x="14" y="13" rx="2"/><rect width="7" height="5" x="3" y="16" rx="2"/>',
        'folder-kanban' => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H10l2 2h5.5A2.5 2.5 0 0 1 20 9.5v8A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5Z"/><path d="M8 11v4m4-4v2m4-2v4"/>',
        'home' => '<path d="m4 10 8-6 8 6v9a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1Z"/>',
        'user' => '<circle cx="12" cy="8" r="3"/><path d="M5 20c.6-4 2.9-6 7-6s6.4 2 7 6"/>',
        'briefcase' => '<rect width="16" height="12" x="4" y="7" rx="2"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7m-11 5h16m-10 0h4"/>',
        'message' => '<path d="M5 5.5A2.5 2.5 0 0 1 7.5 3h9A2.5 2.5 0 0 1 19 5.5v7a2.5 2.5 0 0 1-2.5 2.5H11l-4 3v-3H7.5A2.5 2.5 0 0 1 5 12.5Z"/><path d="M9 9h6"/>',
        'send' => '<path d="m20 4-7.5 16-2.5-7.5L4 10Z"/><path d="M10 12.5 20 4"/>',
        'eye' => '<path d="M3 12s3.2-5 9-5 9 5 9 5-3.2 5-9 5-9-5-9-5Z"/><circle cx="12" cy="12" r="2.5"/>',
        'log-out' => '<path d="m11 16 4-4-4-4m4 4H4"/><path d="M20 18V6a2 2 0 0 0-2-2h-5"/>',
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
