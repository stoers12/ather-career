<?php

declare(strict_types=1);

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/http.php';

function renderOwnerAuthRecoveryPage(bool $authorizationDenied, int $status = 403): never
{
    if (!in_array($status, [403, 429, 503], true)) {
        throw new InvalidArgumentException('Recovery status is invalid.');
    }
    httpSetHtmlResponse($status);
    header('Cache-Control: no-store');
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    $detail = $authorizationDenied
        ? 'The account selection was cancelled or denied.'
        : 'The authorization response could not be accepted.';
    $arabicDetail = $authorizationDenied
        ? 'تم إلغاء اختيار الحساب أو رفضه.'
        : 'تعذر قبول استجابة تسجيل الدخول.';
    ?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign-in not completed | تعذر إكمال تسجيل الدخول</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body class="owner-auth-recovery">
<main class="owner-auth-recovery-card" aria-labelledby="recovery-title">
    <p class="owner-auth-recovery-brand">ATHER</p>
    <h1 id="recovery-title">Sign-in was not completed <span lang="ar" dir="rtl">لم يكتمل تسجيل الدخول</span></h1>
    <p><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?> You are signed out. No data was changed.</p>
    <p lang="ar" dir="rtl"><?= htmlspecialchars($arabicDetail, ENT_QUOTES, 'UTF-8') ?> تم تسجيل خروجك. لم تتغير أي بيانات.</p>
    <form action="/owner_auth_retry.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $token ?>">
        <button type="submit" autofocus aria-describedby="recovery-title">Choose another account <span lang="ar" dir="rtl">اختيار حساب آخر</span></button>
    </form>
    <a href="/owner_login.php">Back to sign in <span lang="ar" dir="rtl">العودة إلى تسجيل الدخول</span></a>
</main>
</body>
</html>
<?php
    exit;
}
