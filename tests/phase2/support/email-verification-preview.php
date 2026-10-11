<?php

declare(strict_types=1);

// Only the isolated preview router or CLI contract tests may include this fixture.
// There is intentionally no public verification endpoint or client-state selector.
if (!defined('ATHER_EMAIL_PREVIEW') || ATHER_EMAIL_PREVIEW !== true || !in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    http_response_code(404);
    exit;
}
if (!isset($verificationFixture) || !in_array($verificationFixture, ['pending', 'verified', 'resent', 'expired'], true)) {
    throw new InvalidArgumentException('Unknown verification fixture.');
}

require_once dirname(__DIR__, 3) . '/includes/http.php';
require_once dirname(__DIR__, 3) . '/includes/presentation.php';
require_once dirname(__DIR__, 3) . '/includes/signin_copy.php';
require_once dirname(__DIR__, 3) . '/includes/account_entry_icons.php';
require_once dirname(__DIR__, 3) . '/includes/email_verification_copy.php';

httpRequireMethod(['GET', 'HEAD']);
$locale = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'ar';
$isVerifiedFixture = $verificationFixture === 'verified';
$isExpiredFixture = $verificationFixture === 'expired';
$isResentFixture = $verificationFixture === 'resent';
$stateIcon = match ($verificationFixture) {
    'verified' => 'badge-check',
    'resent' => 'send',
    'expired' => 'clock-alert',
    default => 'mail',
};
$infoIcon = match ($verificationFixture) {
    'verified' => 'lock',
    'resent' => 'shield-check',
    'expired' => 'triangle-alert',
    default => 'info',
};
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
    <title><?php echo emailVerificationCopy('title', $locale); ?></title>
    <script src="<?php echo versionedAssetUrl('landing.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('email_verification.css'); ?>">
</head>
<body>
<a class="es-skip" href="#verification-fixture"><?php echo emailVerificationCopy('skip', $locale); ?></a>
<main id="ather-email-states" aria-label="<?php echo emailVerificationCopy('main', $locale); ?>">
    <div class="es-product">
        <div class="es-layout">
            <aside class="es-side">
                <a class="es-brand" href="./?lang=<?php echo $locale; ?>" aria-label="<?php echo signInCopy('back', $locale); ?>"><span class="es-mark"><?php echo accountEntryIcon('fingerprint'); ?></span><span><?php echo signInCopy('brand', $locale); ?></span></a>
                <div class="es-side-copy">
                    <div class="es-kicker"><?php echo emailVerificationCopy('kicker', $locale); ?></div>
                    <h2><?php echo emailVerificationCopy('story_heading', $locale); ?></h2>
                    <p><?php echo emailVerificationCopy('story_copy', $locale); ?></p>
                    <div class="es-side-list">
                        <div class="es-side-item"><?php echo accountEntryIcon('mail-check'); ?><?php echo emailVerificationCopy('identity', $locale); ?></div>
                        <div class="es-side-item"><?php echo accountEntryIcon('shield-check'); ?><?php echo emailVerificationCopy('links', $locale); ?></div>
                        <div class="es-side-item"><?php echo accountEntryIcon('eye-off'); ?><?php echo emailVerificationCopy('private', $locale); ?></div>
                    </div>
                </div>
                <div class="es-side-footer"><?php echo emailVerificationCopy('footer', $locale); ?></div>
            </aside>
            <div class="es-main">
                <article class="es-card" id="verification-fixture" tabindex="-1" aria-labelledby="verification-heading" aria-describedby="preview-disclosure">
                    <div class="es-card-top">
                        <a class="es-card-logo" href="./?lang=<?php echo $locale; ?>" aria-label="<?php echo signInCopy('back', $locale); ?>"><?php echo accountEntryIcon('fingerprint'); ?></a>
                        <div class="es-card-controls">
                            <button class="es-theme hf-theme" type="button" aria-label="<?php echo signInCopy('theme', $locale); ?>" aria-pressed="false" hidden><?php echo accountEntryIcon('moon'); ?></button>
                            <a class="es-language" href="?lang=<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" lang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" hreflang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" dir="auto" aria-label="<?php echo signInCopy('language', $locale); ?>"><?php echo $locale === 'ar' ? 'EN' : 'العربية'; ?></a>
                        </div>
                    </div>
                    <p class="es-preview-note" id="preview-disclosure"><?php echo emailVerificationCopy($isResentFixture || $isExpiredFixture ? 'preview_unchanged' : 'preview', $locale); ?></p>
                    <div class="es-state-icon<?php echo $isVerifiedFixture || $isResentFixture ? ' is-success' : ''; ?>"><?php echo accountEntryIcon($stateIcon); ?></div>
                    <h1 id="verification-heading"><?php echo emailVerificationCopy($verificationFixture . '_heading', $locale); ?></h1>
                    <p class="es-subtitle"><?php echo emailVerificationCopy($verificationFixture . '_copy', $locale); ?></p>
                    <?php if ($isExpiredFixture): ?>
                    <div class="es-actions">
                        <button class="es-button is-primary" type="button" disabled aria-describedby="verification-unavailable"><?php echo accountEntryIcon('send'); ?><?php echo emailVerificationCopy('new_link', $locale); ?></button>
                        <a class="es-button" href="signin.php?lang=<?php echo $locale; ?>"><?php echo emailVerificationCopy('signin', $locale); ?></a>
                    </div>
                    <p class="es-method-note" id="verification-unavailable"><?php echo emailVerificationCopy('expired_unavailable', $locale); ?></p>
                    <?php elseif (!$isVerifiedFixture): ?>
                    <div class="es-email"><bdi dir="ltr">m••••d@example.com</bdi></div>
                    <div class="es-actions">
                        <button class="es-button is-primary" type="button" disabled aria-describedby="verification-unavailable"><?php echo accountEntryIcon('refresh-cw'); ?><?php echo emailVerificationCopy('refresh', $locale); ?></button>
                        <button class="es-button" type="button" disabled aria-describedby="verification-unavailable"><?php echo accountEntryIcon('mail'); ?><?php echo emailVerificationCopy('resend', $locale); ?></button>
                    </div>
                    <p class="es-method-note" id="verification-unavailable"><?php echo emailVerificationCopy('pending_unavailable', $locale); ?></p>
                    <a class="es-text-action" href="signin.php?lang=<?php echo $locale; ?>"><?php echo emailVerificationCopy('signin', $locale); ?></a>
                    <?php else: ?>
                    <div class="es-actions">
                        <button class="es-button is-gold" type="button" disabled aria-describedby="verification-unavailable"><span class="es-next-icon"><?php echo accountEntryIcon('arrow-left'); ?></span><?php echo emailVerificationCopy('onboarding', $locale); ?></button>
                    </div>
                    <p class="es-method-note" id="verification-unavailable"><?php echo emailVerificationCopy('onboarding_unavailable', $locale); ?></p>
                    <?php endif; ?>
                    <div class="es-info<?php echo $isExpiredFixture ? ' is-warning' : ''; ?>"><?php echo accountEntryIcon($infoIcon); ?><span><?php echo emailVerificationCopy($verificationFixture . '_info', $locale); ?></span></div>
                </article>
            </div>
        </div>
    </div>
</main>
</body>
</html>
