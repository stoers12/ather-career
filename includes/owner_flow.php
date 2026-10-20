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

function renderOwnerJourneyHolding(int $status = 503): never
{
    require_once __DIR__.'/csrf.php';
    httpSetHtmlResponse($status);
    header('Cache-Control: private, no-store');
    $token=htmlspecialchars(getCsrfToken(),ENT_QUOTES,'UTF-8');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Onboarding unavailable | الإعداد غير متاح</title><link rel="stylesheet" href="/style.css"><script src="/admin.js" defer></script></head><body class="owner-auth-recovery"><main class="owner-auth-recovery-card"><h1>Onboarding is unavailable</h1><p>Your account is signed in, but setup cannot continue right now. You can use another account or log out.</p><p lang="ar" dir="rtl">أنت مسجل الدخول، لكن إعداد الحساب غير متاح الآن. يمكنك استخدام حساب آخر أو تسجيل الخروج.</p><form method="post" action="/owner_switch_account.php" data-owner-form><input type="hidden" name="csrf_token" value="'.$token.'"><button type="submit" data-pending-label="Switching account…">Use another account / استخدام حساب آخر</button></form><form method="post" action="/owner_logout.php" data-owner-form><input type="hidden" name="csrf_token" value="'.$token.'"><button type="submit" data-pending-label="Logging out…">Logout / تسجيل الخروج</button></form></main></body></html>';
    }
    exit;
}
