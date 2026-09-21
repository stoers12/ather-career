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
                <a href="<?php echo $href; ?>" data-sidebar-tooltip="<?php echo ownerEscapeHtml($label); ?>"><?php echo evidenceHubOwnerIcon('circle'); ?><span class="evidence-hub-sidebar-label"><?php echo ownerEscapeHtml($label); ?></span></a>
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

/** Lucide Icons v1.47.0 (ISC): https://lucide.dev/license */
function evidenceHubOwnerIcon(string $name): string
{
    $paths = [
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'panel-right-close' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m8 9 3 3-3 3"/>',
        'panel-right-open' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m11 9-3 3 3 3"/>',
        'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'folder-kanban' => '<path d="M6 5a2 2 0 0 1 2-2h2l2 2h4a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 10v4M12 10v2M16 10v4"/>',
        'circle' => '<circle cx="12" cy="12" r="4"/>',
        'eye' => '<path d="M2.1 12.6a1 1 0 0 1 0-1.2C3.6 8.3 6.6 6 12 6s8.4 2.3 9.9 5.4a1 1 0 0 1 0 1.2C20.4 15.7 17.4 18 12 18s-8.4-2.3-9.9-5.4Z"/><circle cx="12" cy="12" r="3"/>',
        'log-out' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M21 19V5a2 2 0 0 0-2-2h-6"/>',
        'moon' => '<path d="M20.5 14.1A8.2 8.2 0 0 1 9.9 3.5 8.2 8.2 0 1 0 20.5 14.1Z"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
    ];
    $path = $paths[$name] ?? $paths['circle'];
    return '<svg class="evidence-hub-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
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
