<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/presentation.php';
require_once __DIR__ . '/includes/signin_copy.php';
require_once __DIR__ . '/includes/signin_icons.php';

httpRegisterExceptionBoundary('signin.php');
httpRequireMethod(['GET', 'HEAD']);
$locale = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'ar';
header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $locale);
?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="<?php echo signInCopy('description', $locale); ?>">
    <title><?php echo signInCopy('title', $locale); ?></title>
    <script src="<?php echo versionedAssetUrl('landing.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('signin.css'); ?>">
</head>
<body>
<a class="af-skip" href="#signin-options"><?php echo signInCopy('skip', $locale); ?></a>
<main id="ather-auth-flow" aria-label="<?php echo signInCopy('main', $locale); ?>">
    <div class="af-product">
        <div class="af-layout">
            <aside class="af-story-panel">
                <a class="af-brand" href="./?lang=<?php echo $locale; ?>" aria-label="<?php echo signInCopy('back', $locale); ?>"><span class="af-mark"><?php echo signInIcon('fingerprint'); ?></span><span><?php echo signInCopy('brand', $locale); ?></span></a>
                <div class="af-story-copy">
                    <div class="af-kicker"><?php echo signInCopy('kicker', $locale); ?></div>
                    <h2><?php echo signInCopy('story_heading', $locale); ?></h2>
                    <p><?php echo signInCopy('story_copy', $locale); ?></p>
                    <div class="af-story-points">
                        <div class="af-story-point"><?php echo signInIcon('lock'); ?><?php echo signInCopy('private', $locale); ?></div>
                        <div class="af-story-point"><?php echo signInIcon('badge-check'); ?><?php echo signInCopy('evidence', $locale); ?></div>
                        <div class="af-story-point"><?php echo signInIcon('languages'); ?><?php echo signInCopy('languages', $locale); ?></div>
                    </div>
                </div>
                <div class="af-story-footer"><?php echo signInCopy('footer', $locale); ?></div>
            </aside>
            <div class="af-auth-panel">
                <article class="af-card" id="signin-options" tabindex="-1" aria-labelledby="signin-heading">
                    <div class="af-card-top">
                        <a class="af-card-mark" href="./?lang=<?php echo $locale; ?>" aria-label="<?php echo signInCopy('back', $locale); ?>"><?php echo signInIcon('fingerprint'); ?></a>
                        <div class="af-card-controls">
                            <button class="af-theme hf-theme" type="button" aria-label="<?php echo signInCopy('theme', $locale); ?>" aria-pressed="false" hidden><?php echo signInIcon('moon'); ?></button>
                            <a class="af-language" href="?lang=<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" lang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" hreflang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" dir="auto" aria-label="<?php echo signInCopy('language', $locale); ?>"><?php echo $locale === 'ar' ? 'EN' : 'العربية'; ?></a>
                        </div>
                    </div>
                    <h1 id="signin-heading"><?php echo signInCopy('heading', $locale); ?></h1>
                    <p class="af-subtitle"><?php echo signInCopy('subtitle', $locale); ?></p>
                    <div class="af-actions">
                        <a class="af-auth-button" href="owner_login.php?method=google"><span class="af-provider-icon" aria-hidden="true">G</span><?php echo signInCopy('google', $locale); ?></a>
                        <button class="af-auth-button" type="button" disabled aria-describedby="microsoft-unavailable"><span class="af-provider-icon" aria-hidden="true">M</span><?php echo signInCopy('microsoft', $locale); ?></button>
                    </div>
                    <p class="af-method-note" id="microsoft-unavailable"><?php echo signInCopy('microsoft_unavailable', $locale); ?></p>
                    <div class="af-divider"><?php echo signInCopy('or', $locale); ?></div>
                    <button class="af-auth-button is-primary" type="button" disabled aria-describedby="email-unavailable"><?php echo signInIcon('mail'); ?><?php echo signInCopy('email', $locale); ?></button>
                    <p class="af-method-note" id="email-unavailable"><?php echo signInCopy('email_unavailable', $locale); ?></p>
                    <div class="af-safe-note"><?php echo signInIcon('external-link'); ?><span><?php echo signInCopy('safe', $locale); ?> <a href="owner_login.php"><?php echo signInCopy('ordinary', $locale); ?></a></span></div>
                    <p class="af-footnote"><?php echo signInCopy('unavailable', $locale); ?></p>
                </article>
            </div>
        </div>
    </div>
</main>
</body>
</html>
