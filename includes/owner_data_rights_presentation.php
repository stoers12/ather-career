<?php

declare(strict_types=1);

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/presentation.php';
require_once __DIR__ . '/recovery_icons.php';
require_once __DIR__ . '/owner_data_rights_copy.php';

/**
 * @param array{locale: 'ar'|'en', requested_at: string, due_at: string, grace_days: int, cancel_url: string,
 *     export_url: string, reauthenticate_url: string, logout_url: string, recently_authenticated: bool, notice: ?string} $page
 */
function renderOwnerAccountDeletionPage(array $page): void
{
    $ar = $page['locale'] === 'ar';
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    $copy = static fn (string $key): string => $escape(ownerDataRightsCopy($key, $page['locale']));
    $token = $escape(getCsrfToken());
    // The server-local date is usable without JS; the enhancement uses the device timezone.
    $date = new DateTimeImmutable($page['due_at']);
    $formatter = new IntlDateFormatter($ar ? 'ar' : 'en', IntlDateFormatter::LONG, IntlDateFormatter::NONE, date_default_timezone_get(), IntlDateFormatter::GREGORIAN);
    $due = $escape((string) $formatter->format($date));
    ?>
<!DOCTYPE html>
<html lang="<?php echo $ar ? 'ar' : 'en'; ?>" dir="<?php echo $ar ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo $copy('scheduled_title'); ?> — Ather Career</title>
    <script src="<?php echo versionedAssetUrl('assets/js/landing.js'); ?>"></script>
    <script src="<?php echo versionedAssetUrl('assets/js/owner_data_rights.js'); ?>" defer></script>
    <script src="<?php echo versionedAssetUrl('assets/js/admin.js'); ?>" defer></script>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/recovery.css'); ?>">
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/owner_data_rights.css'); ?>">
</head>
<body>
<div id="ather-recovery-design">
    <div class="ap-product owner-deletion-product">
        <main class="ap-main">
            <article class="ap-card" aria-labelledby="deletion-title" data-account-deletion-scheduled>
                <div class="ap-card-top"><span class="ap-card-mark"><?php echo recoveryIcon('fingerprint'); ?></span><span>Ather · أثر</span></div>
                <div class="ap-state-mark"><?php echo recoveryIcon('folder-lock'); ?></div>
                <h1 id="deletion-title"><?php echo $copy('scheduled_title'); ?></h1>
                <p class="ap-subtitle"><?php echo $copy('hidden'); ?></p>
                <div class="owner-deletion-date">
                    <span><?php echo $copy('due'); ?></span>
                    <time datetime="<?php echo $escape($page['due_at']); ?>" data-local-date><?php echo $due; ?></time>
                    <p><?php echo $copy('permanent'); ?></p>
                </div>
                <p class="ap-subtitle"><?php echo $copy('cancel_hint'); ?></p>
                <form method="post" action="<?php echo $escape($page['cancel_url']); ?>" data-owner-form>
                    <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button class="ap-primary" type="submit" data-pending-label="<?php echo $copy('pending_cancel'); ?>"><?php echo $copy('cancel'); ?></button>
                </form>
                <section class="owner-deletion-download" aria-labelledby="deletion-download-title">
                    <h2 id="deletion-download-title"><?php echo $copy('download'); ?></h2>
                    <p class="ap-subtitle"><?php echo $copy('zip'); ?></p>
                    <?php if ($page['notice'] === 'reauthenticate'): ?>
                    <p class="owner-deletion-notice" role="alert"><?php echo $copy('reauthenticate'); ?></p>
                    <?php endif; ?>
                    <form method="post" action="<?php echo $escape($page[$page['recently_authenticated'] ? 'export_url' : 'reauthenticate_url']); ?>"<?php echo $page['recently_authenticated'] ? '' : ' data-owner-form'; ?>>
                        <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
                        <?php if (!$page['recently_authenticated']): ?><input type="hidden" name="return" value="deletion"><?php endif; ?>
                        <button class="owner-deletion-secondary" type="submit"<?php if (!$page['recently_authenticated']): ?> data-pending-label="<?php echo $copy('pending_confirm'); ?>"<?php endif; ?>><?php echo $copy($page['recently_authenticated'] ? 'download' : 'confirm_download'); ?></button>
                    </form>
                </section>
                <form method="post" action="<?php echo $escape($page['logout_url']); ?>" data-owner-form>
                    <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
                    <button class="owner-deletion-logout" type="submit" data-pending-label="<?php echo $copy('pending_logout'); ?>"><?php echo $copy('logout'); ?></button>
                </form>
            </article>
        </main>
    </div>
</div>
</body>
</html>
    <?php
}
