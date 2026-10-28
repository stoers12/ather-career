<?php
declare(strict_types=1);

require_once __DIR__ . '/owner_session.php';
require_once __DIR__ . '/http.php';

/** Public anonymous presentation needs no database or new session. A cookie is
 * only a reason to inspect a session, never evidence of current authority.
 */
function presentAuthenticatedEntryIfPresent(string $locale): void
{
    header('Vary: Cookie', false);
    if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[OWNER_SESSION_NAME])) { return; }
    $authorized = false;
    try {
        startOwnerSession();
        if (!array_key_exists(INTERNAL_USER_SESSION_KEY, $_SESSION)) { return; }
        // Includes a present-but-null record, which is not an anonymous entry.
        if (currentInternalUserSession() === null) {
            destroyOwnerSession();
            renderAuthenticatedEntry($locale, null, false, 403);
        }
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/authorization.php';
        $database = getDatabaseConnection();
        $user = requireAuthenticatedUser($database);
        $authorized = true;
        $destination = ownerJourneyDestination($database, $user);
    } catch (AuthorizationDeniedException $error) {
        renderAuthenticatedEntry($locale, null, false, $error->getPrevious() instanceof PDOException ? 503 : 403);
    } catch (Throwable) {
        renderAuthenticatedEntry($locale, null, $authorized, 503);
    }
    renderAuthenticatedEntry($locale, $destination, true, $destination === '/owner_onboarding.php' ? 503 : 200);
}

/** Only destinations returned by the server journey boundary become actions.
 * Targets authorize again; this presentation grants no access or completion.
 */
function renderAuthenticatedEntry(string $locale, ?string $destination, bool $authorized, int $status): never
{
    require_once __DIR__ . '/presentation.php';
    require_once __DIR__ . '/signin_copy.php';
    require_once __DIR__ . '/signin_icons.php';
    require_once __DIR__ . '/csrf.php';
    $ar = $locale === 'ar';
    $locale = $ar ? 'ar' : 'en';
    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $copy = static fn(string $arText, string $enText): string => $escape($ar ? $arText : $enText);
    $heading = $authorized ? $copy('أنت مسجّل الدخول', 'You are signed in')
        : ($status === 403 ? $copy('يرجى تسجيل الدخول مجددًا', 'Please sign in again') : $copy('تعذّر التحقق من حسابك الآن', 'We could not check your account right now'));
    httpSetHtmlResponse($status);
    header('Cache-Control: private, no-store');
    header('Content-Language: ' . $locale);
    header('X-Robots-Tag: noindex, nofollow');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') { exit; }
    ?>
<!DOCTYPE html>
<html lang="<?= $locale ?>" dir="<?= $ar ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= $heading ?></title>
    <script src="<?= versionedAssetUrl('assets/js/landing.js') ?>"></script>
    <link rel="stylesheet" href="<?= versionedAssetUrl('assets/css/signin.css') ?>">
</head>
<body>
<a class="af-skip" href="#account-options"><?= $copy('انتقل إلى خيارات الحساب', 'Skip to account options') ?></a>
<main id="ather-auth-flow">
    <div class="af-product"><div class="af-layout">
        <aside class="af-story-panel">
            <a class="af-brand" href="/?lang=<?= $locale ?>"><?= signInIcon('fingerprint') ?><?= signInCopy('brand', $locale) ?></a>
            <div class="af-story-copy">
                <h2><?= signInCopy('story_heading', $locale) ?></h2>
                <p><?= $authorized ? $copy('تابع باستخدام حسابك الحالي أو اختر حسابًا آخر.', 'Continue with your current account, or choose another account.') : $copy('تحقّق من الوصول إلى حسابك للمتابعة.', 'Check access to your account to continue.') ?></p>
            </div>
            <div class="af-story-footer"><?= signInCopy('footer', $locale) ?></div>
        </aside>
        <div class="af-auth-panel">
        <article class="af-card" id="account-options" tabindex="-1" aria-labelledby="account-heading" data-authenticated-entry="<?= $authorized ? 'authorized' : 'unavailable' ?>">
            <div class="af-card-top">
                <a class="af-card-mark" href="/?lang=<?= $locale ?>" aria-label="<?= signInCopy('back', $locale) ?>"><?= signInIcon('fingerprint') ?></a>
                <div class="af-card-controls">
                    <button class="af-theme hf-theme" type="button" aria-label="<?= signInCopy('theme', $locale) ?>" aria-pressed="false" hidden><?= signInIcon('moon') ?></button>
                    <a class="af-language" href="?lang=<?= $ar ? 'en' : 'ar' ?>" lang="<?= $ar ? 'en' : 'ar' ?>" dir="auto"><?= $ar ? 'English' : 'العربية' ?></a>
                </div>
            </div>
            <h1 id="account-heading"><?= $heading ?></h1>
            <div class="af-actions">
            <?php if ($authorized): ?>
                <?php if ($destination === '/owner.php'): ?>
                <a class="af-auth-button is-primary" data-entry-dashboard href="/owner.php"><?= $copy('الانتقال إلى لوحة التحكم', 'Go to Dashboard') ?></a>
                <?php elseif ($destination === '/owner_onboarding_editor.php'): ?>
                <a class="af-auth-button is-primary" data-entry-resume href="/owner_onboarding.php?lang=<?= $locale ?>"><?= $copy('استئناف الإعداد', 'Resume setup') ?></a>
                <?php else: ?>
                <p role="status"><?= $copy('متابعة الإعداد غير متاحة الآن. يمكنك العودة لاحقًا أو استخدام حساب آخر أو تسجيل الخروج.', 'Setup is unavailable right now. You can return later, use another account or log out.') ?></p>
                <?php endif; ?>
                <form method="POST" action="/owner_switch_account.php">
                    <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                    <button class="af-auth-button is-primary" type="submit"><?= $copy('استخدام حساب آخر', 'Use another account') ?></button>
                </form>
                <form method="POST" action="/owner_logout.php">
                    <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                    <button class="af-auth-button is-primary" type="submit"><?= $copy('تسجيل الخروج', 'Logout') ?></button>
                </form>
            <?php else: ?>
                <p role="alert"><?= $status === 403 ? $copy('جلستك غير متاحة. سجّل الدخول للمتابعة.', 'Your session is unavailable. Sign in to continue.') : $copy('حاول مرة أخرى لاحقًا. لم نتمكن من تأكيد الوصول إلى حسابك.', 'Try again later. We could not confirm access to your account.') ?></p>
                <a class="af-auth-button" href="/signin.php?lang=<?= $locale ?>"><?= $copy('العودة إلى تسجيل الدخول', 'Return to sign in') ?></a>
            <?php endif; ?>
            </div>
        </article>
    </div></div></div>
</main>
</body>
</html>
    <?php
    exit;
}
