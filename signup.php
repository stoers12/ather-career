<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/presentation.php';
require_once __DIR__ . '/includes/signin_copy.php';
require_once __DIR__ . '/includes/signup_copy.php';
require_once __DIR__ . '/includes/account_entry_icons.php';

require_once __DIR__ . '/includes/auth0_oidc.php';
require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';

httpRegisterExceptionBoundary('signup.php');
httpRequireMethod(['GET', 'HEAD']);
$locale = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'ar';
require_once __DIR__ . '/includes/authenticated_entry.php';
presentAuthenticatedEntryIfPresent($locale);
$registrationEnabled = auth0RegistrationIsEnabled();
if ($registrationEnabled) { startOwnerSession(); }
$canRegister = $registrationEnabled && !array_key_exists(INTERNAL_USER_SESSION_KEY, $_SESSION ?? []);
header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $locale);
?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="<?php echo signUpCopy($canRegister ? 'description_enabled' : 'description', $locale); ?>">
    <title><?php echo signUpCopy('title', $locale); ?></title>
    <script src="<?php echo versionedAssetUrl('landing.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('signin.css'); ?>">
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('signup.css'); ?>">
</head>
<body>
<a class="af-skip" href="#signup-options"><?php echo signUpCopy('skip', $locale); ?></a>
<main id="ather-auth-flow" aria-label="<?php echo signUpCopy('main', $locale); ?>">
    <div class="af-product">
        <div class="af-layout">
            <aside class="af-story-panel">
                <a class="af-brand" href="./?lang=<?php echo $locale; ?>" aria-label="<?php echo signInCopy('back', $locale); ?>"><span class="af-mark"><?php echo signInIcon('fingerprint'); ?></span><span><?php echo signInCopy('brand', $locale); ?></span></a>
                <div class="af-story-copy">
                    <div class="af-kicker"><?php echo signInCopy('kicker', $locale); ?></div>
                    <h2><?php echo signInCopy('story_heading', $locale); ?></h2>
                    <p><?php echo signUpCopy('story_copy', $locale); ?></p>
                    <div class="af-story-points">
                        <div class="af-story-point"><?php echo signInIcon('lock'); ?><?php echo signInCopy('private', $locale); ?></div>
                        <div class="af-story-point"><?php echo signInIcon('badge-check'); ?><?php echo signInCopy('evidence', $locale); ?></div>
                        <div class="af-story-point"><?php echo signInIcon('languages'); ?><?php echo signInCopy('languages', $locale); ?></div>
                    </div>
                </div>
                <div class="af-story-footer"><?php echo signInCopy('footer', $locale); ?></div>
            </aside>
            <div class="af-auth-panel">
                <article class="af-card" id="signup-options" tabindex="-1" aria-labelledby="signup-heading">
                    <div class="af-card-top">
                        <a class="af-card-mark" href="./?lang=<?php echo $locale; ?>" aria-label="<?php echo signInCopy('back', $locale); ?>"><?php echo signInIcon('fingerprint'); ?></a>
                        <div class="af-card-controls">
                            <button class="af-theme hf-theme" type="button" aria-label="<?php echo signInCopy('theme', $locale); ?>" aria-pressed="false" hidden><?php echo signInIcon('moon'); ?></button>
                            <a class="af-language" href="?lang=<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" lang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" hreflang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" dir="auto" aria-label="<?php echo signInCopy('language', $locale); ?>"><?php echo $locale === 'ar' ? 'EN' : 'العربية'; ?></a>
                        </div>
                    </div>
                    <h1 id="signup-heading"><?php echo signUpCopy('heading', $locale); ?></h1>
                    <p class="af-subtitle"><?php echo signUpCopy('subtitle', $locale); ?></p>
                    <div class="af-actions">
                        <?php if ($canRegister): ?>
                        <form action="/owner_register.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <button class="af-auth-button" type="submit"><span class="af-provider-icon" aria-hidden="true">G</span><?php echo signUpCopy('google', $locale); ?></button>
                        </form>
                        <?php else: ?>
                        <button class="af-auth-button" type="button" disabled aria-describedby="registration-unavailable"><span class="af-provider-icon" aria-hidden="true">G</span><?php echo signUpCopy('google', $locale); ?></button>
                        <?php endif; ?>
                        <button class="af-auth-button" type="button" disabled aria-describedby="registration-unavailable microsoft-unavailable"><span class="af-provider-icon" aria-hidden="true">M</span><?php echo signUpCopy('microsoft', $locale); ?></button>
                    </div>
                    <p class="af-method-note" id="microsoft-unavailable"><?php echo signInCopy('microsoft_unavailable', $locale); ?></p>
                    <div class="af-divider"><?php echo signInCopy('or', $locale); ?></div>
                    <button class="af-auth-button is-gold" type="button" disabled aria-describedby="registration-unavailable email-unavailable"><?php echo accountEntryIcon('mail-plus'); ?><?php echo signUpCopy('email', $locale); ?></button>
                    <p class="af-method-note" id="email-unavailable"><?php echo signUpCopy('email_unavailable', $locale); ?></p>
                    <p class="af-method-note" id="registration-unavailable"><?php echo $canRegister ? signUpCopy('google_available', $locale) : ($registrationEnabled ? signUpCopy('signed_in', $locale) : signInCopy('unavailable', $locale)); ?></p>
                    <div class="af-warning"><?php echo accountEntryIcon('info'); ?><span><?php echo signUpCopy('age', $locale); ?></span></div>
                    <p class="af-footnote"><?php echo signUpCopy('existing', $locale); ?> <a href="signin.php?lang=<?php echo $locale; ?>"><?php echo signUpCopy('signin', $locale); ?></a></p>
                </article>
            </div>
        </div>
    </div>
</main>
</body>
</html>
