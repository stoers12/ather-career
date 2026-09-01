<?php

declare(strict_types=1);

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/presentation.php';

function ownerEscapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ownerLayoutStart(string $title, string $activePage): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo ownerEscapeHtml($title); ?> - My Portfolio</title>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('admin.css'); ?>">
    <script src="<?php echo versionedAssetUrl('admin.js'); ?>" defer></script>
</head>
<body>
<div class="admin-layout">
    <?php ownerNavigation($activePage); ?>
    <main class="admin-content" id="main-content">
        <section>
    <?php
}

function ownerNavigation(string $activePage): void
{
    $links = [
        'dashboard' => ['owner.php', 'Dashboard'],
        'profile' => ['owner_profile.php', 'Personal info'],
        'projects' => ['owner_projects.php', 'Projects'],
        'messages' => ['owner_messages.php', 'Messages'],
        'publication' => ['owner_publication.php', 'Publication'],
    ];
    ?>
    <a class="skip-link admin-skip-link" href="#main-content">Skip to main content</a>
    <aside class="admin-sidebar">
        <a class="admin-brand" href="owner.php"><span class="brand-mark">P</span><span>Portfolio Owner</span></a>
        <nav aria-label="Owner navigation">
            <span class="nav-group-label">Workspace</span>
            <?php foreach ($links as $key => [$href, $label]): ?>
                <a class="<?php echo $activePage === $key ? 'active' : ''; ?>" href="<?php echo $href; ?>"<?php echo $activePage === $key ? ' aria-current="page"' : ''; ?>><?php echo ownerEscapeHtml($label); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="owner_preview.php">Private Preview</a>
            <form class="sidebar-logout-form" method="POST" action="owner_logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                <button class="sidebar-logout-button" type="submit">Logout</button>
            </form>
        </div>
    </aside>
    <?php
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
