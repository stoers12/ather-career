<?php

declare(strict_types=1);

require_once __DIR__ . '/csrf.php';

/*
 * The page an account scheduled for deletion sees (L-03). The frontend owns
 * this file (docs/contracts/account-data-rights.md); the backend ships this
 * plain version so the flow works before the designed one lands.
 */

/**
 * @param array{locale: 'ar'|'en', requested_at: string, due_at: string, grace_days: int, cancel_url: string,
 *     export_url: string, reauthenticate_url: string, logout_url: string, recently_authenticated: bool, notice: ?string} $page
 */
function renderOwnerAccountDeletionPage(array $page): void
{
    $ar = $page['locale'] === 'ar';
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    $token = $escape(getCsrfToken());
    $due = $escape(substr($page['due_at'], 0, 10));
    $title = $ar ? 'حسابك مجدول للحذف' : 'Your account is scheduled for deletion';
    $form = static fn (string $url, string $fields, string $label): string => '<form method="post" action="' . $escape($url) . '" data-owner-form>'
        . '<input type="hidden" name="csrf_token" value="' . $token . '">' . $fields . '<button type="submit">' . $label . '</button></form>';

    echo '<!DOCTYPE html><html lang="' . ($ar ? 'ar' : 'en') . '" dir="' . ($ar ? 'rtl' : 'ltr') . '"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">'
        . '<title>' . $title . '</title><link rel="stylesheet" href="/assets/css/style.css"><script src="/assets/js/admin.js" defer></script></head>'
        . '<body class="owner-auth-recovery"><main class="owner-auth-recovery-card" data-account-deletion-scheduled>'
        . '<h1>' . $title . '</h1>'
        . '<p>' . ($ar ? 'صفحتك العامة مخفية الآن. سيُحذف حسابك وكل بياناته وصوره نهائياً في ' : 'Your public page is hidden now. Your account, its data and images will be permanently deleted on ')
        . '<time datetime="' . $escape($page['due_at']) . '">' . $due . '</time>.</p>'
        . '<p>' . ($ar ? 'غيّرت رأيك؟ ألغِ الحذف، فيعود كل شيء كما كان.' : 'Changed your mind? Cancel the deletion and everything returns as it was.') . '</p>'
        . $form($page['cancel_url'], '<input type="hidden" name="action" value="cancel">', $ar ? 'إلغاء الحذف' : 'Cancel deletion')
        . ($page['notice'] === 'reauthenticate' ? '<p role="alert">' . ($ar ? 'أكّد هويتك أولاً لتنزيل بياناتك.' : 'Confirm it is you first to download your data.') . '</p>' : '')
        . ($page['recently_authenticated']
            ? $form($page['export_url'], '', $ar ? 'نزّل بياناتي' : 'Download my data')
            : $form($page['reauthenticate_url'], '<input type="hidden" name="return" value="deletion">', $ar ? 'أكّد هويتك لتنزيل بياناتك' : 'Confirm it is you to download your data'))
        . $form($page['logout_url'], '', $ar ? 'تسجيل الخروج' : 'Log out')
        . '</main></body></html>';
}
