<?php

declare(strict_types=1);

// Included only by an isolated, authentication-disabled preview router or CLI tests.
if (!defined('ATHER_RECOVERY_PREVIEW') || ATHER_RECOVERY_PREVIEW !== true || !in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    http_response_code(404);
    exit;
}
if (!isset($recoveryFixture) || !in_array($recoveryFixture, ['forgot', 'reset'], true)) {
    throw new InvalidArgumentException('Unknown recovery fixture.');
}

require_once dirname(__DIR__, 3) . '/includes/http.php';
require_once dirname(__DIR__, 3) . '/includes/presentation.php';
require_once dirname(__DIR__, 3) . '/includes/recovery_copy.php';
require_once dirname(__DIR__, 3) . '/includes/recovery_icons.php';

httpRequireMethod(['GET', 'HEAD']);
$locale = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'ar';
$isReset = $recoveryFixture === 'reset';
$oppositeLocale = $locale === 'ar' ? 'en' : 'ar';
$pageName = $isReset ? 'reset-password.php' : 'forgot-password.php';
header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $locale);
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo recoveryCopy($isReset ? 'title_reset' : 'title_forgot', $locale); ?></title>
    <script src="<?php echo versionedAssetUrl('landing.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('recovery.css'); ?>">
</head>
<body>
<a class="ap-skip" href="#recovery-card"><?php echo recoveryCopy('skip', $locale); ?></a>
<div id="ather-recovery-design">
    <div class="ap-product">
        <aside class="ap-story">
            <a class="ap-brand" href="./?lang=<?php echo $locale; ?>"><span class="ap-brand-mark"><?php echo recoveryIcon('fingerprint'); ?></span><span>Ather · أثر</span></a>
            <div class="ap-story-copy">
                <div class="ap-kicker"><?php echo recoveryCopy('kicker', $locale); ?></div>
                <h2><?php echo recoveryCopy('story_heading', $locale); ?></h2>
                <p><?php echo recoveryCopy('story_copy', $locale); ?></p>
                <div class="ap-points">
                    <div class="ap-point"><?php echo recoveryIcon('shield-check'); ?><span><?php echo recoveryCopy('point_one', $locale); ?></span></div>
                    <div class="ap-point"><?php echo recoveryIcon('lock-keyhole'); ?><span><?php echo recoveryCopy('point_two', $locale); ?></span></div>
                    <div class="ap-point"><?php echo recoveryIcon('folder-lock'); ?><span><?php echo recoveryCopy('point_three', $locale); ?></span></div>
                </div>
            </div>
            <div class="ap-story-footer"><?php echo recoveryCopy('story_footer', $locale); ?></div>
        </aside>
        <main class="ap-main" aria-label="<?php echo recoveryCopy('main', $locale); ?>">
            <article class="ap-card" id="recovery-card" tabindex="-1" aria-labelledby="recovery-heading" aria-describedby="recovery-footer">
                <div class="ap-card-top">
                    <span class="ap-card-mark"><?php echo recoveryIcon('fingerprint'); ?></span>
                    <div class="ap-controls">
                        <a class="ap-control ap-language" href="<?php echo $pageName; ?>?lang=<?php echo $oppositeLocale; ?>" lang="<?php echo $oppositeLocale; ?>" hreflang="<?php echo $oppositeLocale; ?>" aria-label="<?php echo $locale === 'ar' ? 'Switch to English' : 'التبديل إلى العربية'; ?>"><?php echo $locale === 'ar' ? 'EN' : 'العربية'; ?></a>
                        <button class="ap-control hf-theme" type="button" aria-label="<?php echo $locale === 'ar' ? 'تبديل الثيم' : 'Change theme'; ?>" aria-pressed="false" hidden><?php echo recoveryIcon('moon'); ?></button>
                    </div>
                </div>
                <div class="ap-state-mark"><?php echo recoveryIcon($isReset ? 'key-round' : 'mail-key'); ?></div>
                <h1 id="recovery-heading"><?php echo recoveryCopy($isReset ? 'reset_heading' : 'forgot_heading', $locale); ?></h1>
                <p class="ap-subtitle"><?php echo recoveryCopy($isReset ? 'reset_subtitle' : 'forgot_subtitle', $locale); ?></p>
                <div class="ap-fields">
                    <?php if ($isReset): ?>
                    <div>
                        <label class="ap-label" for="ap-password"><?php echo recoveryCopy('password_label', $locale); ?></label>
                        <div class="ap-field"><?php echo recoveryIcon('lock-keyhole'); ?><input id="ap-password" type="password" placeholder="••••••••••••" readonly autocomplete="off" aria-describedby="ap-password-hint recovery-footer"><?php echo recoveryIcon('eye-off'); ?></div>
                        <p class="ap-hint" id="ap-password-hint"><?php echo recoveryCopy('password_hint', $locale); ?></p>
                    </div>
                    <div>
                        <label class="ap-label" for="ap-confirm"><?php echo recoveryCopy('confirm_label', $locale); ?></label>
                        <div class="ap-field"><?php echo recoveryIcon('lock-keyhole'); ?><input id="ap-confirm" type="password" placeholder="••••••••••••" readonly autocomplete="off" aria-describedby="recovery-footer"><?php echo recoveryIcon('eye-off'); ?></div>
                    </div>
                    <?php else: ?>
                    <div>
                        <label class="ap-label" for="ap-email"><?php echo recoveryCopy('email_label', $locale); ?></label>
                        <div class="ap-field"><?php echo recoveryIcon('mail'); ?><input id="ap-email" type="email" placeholder="name@example.com" readonly autocomplete="off" aria-describedby="ap-email-hint recovery-footer"></div>
                        <p class="ap-hint" id="ap-email-hint"><?php echo recoveryCopy('email_hint', $locale); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <button class="ap-primary" type="button" disabled aria-describedby="recovery-footer"><?php echo recoveryIcon($isReset ? 'check' : 'send'); ?><span><?php echo recoveryCopy($isReset ? 'reset_action' : 'send_action', $locale); ?></span></button>
                <div class="ap-safe"><?php echo recoveryIcon('shield-check'); ?><span><?php echo recoveryCopy($isReset ? 'reset_notice' : 'forgot_notice', $locale); ?></span></div>
                <div class="ap-return"><a href="signin.php?lang=<?php echo $locale; ?>"><?php echo recoveryIcon('arrow-right'); ?><span><?php echo recoveryCopy('return', $locale); ?></span></a></div>
                <p class="ap-footer" id="recovery-footer"><?php echo recoveryCopy($isReset ? 'reset_footer' : 'forgot_footer', $locale); ?></p>
            </article>
        </main>
    </div>
</div>
</body>
</html>
