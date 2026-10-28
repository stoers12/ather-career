<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/owner_session.php';

class OwnerReadinessDeniedException extends RuntimeException
{
}

function ownerAuthorizationDenied(): never
{
    httpAbortHtml(403, 'You are not authorized to access this resource.');
}

function ownerAuthenticationRequired(): never
{
    httpRedirect('/owner_login.php');
}

function requireOwnerAuthenticatedUser(PDO $database): AuthenticatedUserContext
{
    if (currentInternalUserSession() === null) {
        ownerAuthenticationRequired();
    }

    try {
        return requireAuthenticatedUser($database);
    } catch (AuthorizationDeniedException) {
        ownerAuthorizationDenied();
    }
}

/** Existence lookup only; never sufficient for Owner authority. */
function ownerHasPortfolio(PDO $database, AuthenticatedUserContext $user): bool
{
    try {
        $statement = $database->prepare(
            'SELECT id
             FROM portfolios
             WHERE owner_user_id = :internal_user_id
             LIMIT 1'
        );
        $statement->execute(['internal_user_id' => $user->userId]);

        return authorizationPositiveInteger($statement->fetchColumn()) !== null;
    } catch (PDOException $exception) {
        throw new AuthorizationDeniedException('Portfolio authorization failed.', 0, $exception);
    }
}

function requireOwnerPortfolioContext(PDO $database): AuthorizedPortfolioContext
{
    $user = requireOwnerAuthenticatedUser($database);
    try {
        $destination = ownerJourneyDestination($database, $user);
        if ($destination !== '/owner.php') { httpRedirect($destination); }
        return requireOwnedPortfolioContext($database);
    } catch (AuthorizationDeniedException) { ownerAuthorizationDenied(); }
}

/** Retired first-creation API: alternate callers cannot bypass completion. */
function createOwnedPortfolio(PDO $database, AuthenticatedUserContext $user): ?int
{
    $currentUser = requireAuthenticatedUser($database);
    if ($currentUser->userId !== $user->userId) { throw new AuthorizationDeniedException('Portfolio creation identity mismatch.'); }
    throw new OwnerReadinessDeniedException('Legacy Portfolio creation is retired; use explicit onboarding completion.');
}

/** Server-selected fixed actions; presentation mode never supplies authority. */
function renderOwnerJourneyHolding(int $status = 503, ?string $destination = null, bool $paused = false, string $locale = 'en'): never
{
    require_once __DIR__.'/csrf.php';
    httpSetHtmlResponse($status);
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $ar=$locale==='ar';
    header('Content-Language: '.($ar ? 'ar' : 'en'));
    $token=htmlspecialchars(getCsrfToken(),ENT_QUOTES,'UTF-8');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        $title=$paused ? ($ar ? 'تم إيقاف الإعداد مؤقتًا' : 'Onboarding paused') : ($ar ? 'الإعداد غير متاح' : 'Onboarding is unavailable');
        $summary=$ar ? 'تبقى فقط التعديلات التي تأكد حفظها. التوقف لا يكمل الإعداد ولا ينشر ملفك.' : 'Only acknowledged saves are kept. Pausing does not complete setup or publish your Profile.';
        $unavailable=$ar ? 'المتابعة غير متاحة الآن. يمكنك العودة لاحقًا أو استخدام حساب آخر أو تسجيل الخروج.' : 'Continuation is unavailable right now. You can return later, use another account or log out.';
        // Keep the original holding page understandable in both languages.
        $alternate=$paused ? '' : '<p lang="'.($ar ? 'en' : 'ar').'" dir="'.($ar ? 'ltr' : 'rtl').'">'.($ar ? 'Continuation is unavailable right now. You can use another account or log out.' : 'المتابعة غير متاحة الآن. يمكنك استخدام حساب آخر أو تسجيل الخروج.').'</p>';
        $action='';
        if ($paused && $destination==='/owner_onboarding_editor.php') {
            $action='<p><a data-journey-resume href="/owner_onboarding.php?lang='.($ar ? 'ar' : 'en').'">'.($ar ? 'استئناف الإعداد' : 'Resume setup').'</a></p>';
        } elseif ($paused && $destination==='/owner.php') {
            $action='<p>'.($ar ? 'لوحة التحكم متاحة لحسابك الحالي.' : 'Dashboard is available for your current account.').'</p><p><a data-journey-dashboard href="/owner.php">'.($ar ? 'الانتقال إلى لوحة التحكم' : 'Go to Dashboard').'</a></p>';
        }
        echo '<!DOCTYPE html><html lang="'.($ar ? 'ar' : 'en').'" dir="'.($ar ? 'rtl' : 'ltr').'"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.$title.'</title><link rel="stylesheet" href="/assets/css/style.css"><script src="/assets/js/admin.js" defer></script></head><body class="owner-auth-recovery"><main class="owner-auth-recovery-card"><h1>'.$title.'</h1><p>'.$summary.'</p>'.($action!=='' ? $action : '<p role="status">'.$unavailable.'</p>').$alternate.'<form method="post" action="/owner_switch_account.php" data-owner-form><input type="hidden" name="csrf_token" value="'.$token.'"><button type="submit" data-pending-label="'.($ar ? 'جارٍ تبديل الحساب…' : 'Switching account…').'">'.($paused ? ($ar ? 'استخدام حساب آخر' : 'Use another account') : 'Use another account / استخدام حساب آخر').'</button></form><form method="post" action="/owner_logout.php" data-owner-form><input type="hidden" name="csrf_token" value="'.$token.'"><button type="submit" data-pending-label="'.($ar ? 'جارٍ تسجيل الخروج…' : 'Logging out…').'">'.($paused ? ($ar ? 'تسجيل الخروج' : 'Logout') : 'Logout / تسجيل الخروج').'</button></form></main></body></html>';
    }
    exit;
}
