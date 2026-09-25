<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/owner_layout.php';

$_SESSION = ['csrf_token' => str_repeat('a', 64)];
ownerLayoutStart('Owner Dashboard', 'dashboard');
?>
<div class="admin-page-header">
    <div class="admin-page-header-copy">
        <p class="admin-eyebrow">Overview</p>
        <h1 class="admin-page-title">Owner Dashboard</h1>
        <p class="admin-page-description">Manage your private Portfolio workspace.</p>
    </div>
</div>
<div class="stat-grid" aria-label="Portfolio summary">
    <article class="stat-card"><span class="stat-label">Projects</span><strong>0</strong><a href="/owner_projects.php">Manage projects →</a></article>
</div>
<?php ownerLayoutEnd();
