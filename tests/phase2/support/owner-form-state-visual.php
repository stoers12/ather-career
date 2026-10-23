<?php
declare(strict_types=1);

// CLI-rendered synthetic chrome only: no database, session authority or HTTP auth routes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../../includes/owner_layout.php';
require_once __DIR__ . '/../../../includes/public_lifecycle.php';
require_once __DIR__ . '/../../../includes/owner_publication_presentation.php';
$_SESSION = ['csrf_token' => str_repeat('b', 64)];
$locale = ($argv[1] ?? null) === 'en' ? 'en' : 'ar';
$page = $argv[2] ?? 'dashboard';
$pages = ['dashboard'=>'Dashboard', 'profile'=>'Personal Info', 'projects'=>'Projects',
    'experiences'=>'Experience', 'messages'=>'Messages', 'publication'=>'Publication', 'evidence_hub'=>'Evidence Hub'];
if (!isset($pages[$page])) exit(2);
ownerLayoutStart($pages[$page], $page, true, ['locale'=>$locale, 'account_name'=>'Synthetic owner']);
?>
<h1>Owner form-state regression fixture</h1>
<?php if ($page === 'publication'): ?>
<?php renderOwnerPublicationPresentation(['public_slug'=>'synthetic-owner', 'is_published'=>1, 'published_at'=>'2026-01-01 00:00:00'], 'https://synthetic.invalid/p/synthetic-owner'); ?>
<?php else: ?>
<!-- Representative shared submit structures; account forms above are the actual shared renderer. -->
<form id="shared-form" method="POST" action="/synthetic-submit" data-owner-form>
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
    <input id="required-field" name="title" value="Synthetic content" required>
    <button id="plain-submit" type="submit" data-pending-label="Saving…" aria-disabled="false" style="min-width: 120px !important">Save</button>
    <button id="nested-submit" type="submit" data-pending-label="Saving nested…"><svg viewBox="0 0 24 24" aria-hidden="true"><title>Keep SVG title</title><path d="M4 12h16"/></svg><span class="label"><b>Save</b> <span>changes</span></span></button>
    <input id="input-submit" type="submit" value="Save input" data-pending-label="Saving input…">
    <button id="icon-submit" type="submit" aria-label="Save icon" data-pending-label="Saving icon…"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16"/></svg></button>
    <button id="disabled-control" type="button" disabled>Unavailable</button>
</form>
<?php endif; ?>
<?php ownerLayoutEnd();
