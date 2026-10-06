<?php

declare(strict_types=1);

final class AccountSelectionStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $root = PHASE2_REPOSITORY_ROOT . '/';
        $route = (string) file_get_contents($root . 'owner_switch_account.php');
        $shim = (string) file_get_contents($root . 'public/owner_switch_account.php');
        $session = (string) file_get_contents($root . 'includes/owner_session.php');
        $internalSession = (string) file_get_contents($root . 'includes/session.php');
        $oidc = (string) file_get_contents($root . 'includes/auth0_oidc.php');
        $layout = (string) file_get_contents($root . 'includes/owner_layout.php');
        $callback = (string) file_get_contents($root . 'owner_oidc_callback.php');
        $retry = (string) file_get_contents($root . 'owner_auth_retry.php');
        $recovery = (string) file_get_contents($root . 'includes/owner_auth_recovery.php');
        $retryShim = (string) file_get_contents($root . 'public/owner_auth_retry.php');
        $stylesheet = (string) file_get_contents($root . 'style.css');

        phase2Assert(str_contains($route, "httpRequireMethod(['POST'])")
            && str_contains($route, "requireValidCsrfToken(\$_POST['csrf_token'] ?? null)")
            && strpos($route, 'requireValidCsrfToken') < strpos($route, 'requireOwnerAuthenticatedUser')
            && strpos($route, 'requireOwnerAuthenticatedUser') < strpos($route, 'beginFreshOwnerAccountSelectionSession')
            && strpos($route, 'beginFreshOwnerAccountSelectionSession') < strpos($route, 'beginAuth0Authorization'), 'Account selection POST, CSRF, or session ordering changed.');
        phase2Assert(str_contains($route, "consumeRateLimit('oidc_start'")
            && str_contains($route, "'select_account'")
            && !preg_match('/\$_(?:GET|POST|REQUEST)\[(?:.redirect|.return|.next)/', $route), 'Account selection prompt, limiter, or fixed destination changed.');
        phase2Assert(str_contains($session, 'destroyOwnerSession();')
            && str_contains($session, "session_id('');")
            && str_contains($session, 'session_regenerate_id(true)')
            && str_contains($internalSession, 'if (!session_destroy())'), 'Switch must verify retirement and rotate the local session.');
        phase2Assert(str_contains($oidc, "\$prompt !== 'select_account'")
            && str_contains($oidc, "\$parameters['prompt'] = \$prompt")
            && str_contains($oidc, 'unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY])'), 'Prompt allow-list or one-time transaction changed.');
        phase2Assert(str_contains($layout, 'ownerAccountSelectionForm();') && str_contains($layout, 'ownerAccountSelectionForm(true);')
            && str_contains($layout, 'action="/owner_switch_account.php"')
            && str_contains($layout, 'method="POST"')
            && str_contains($layout, 'getCsrfToken()')
            && str_contains($layout, 'Use another account')
            && str_contains($layout, 'lang="ar" dir="rtl"'), 'Account selection must be keyboard-operable and bilingual in both Owner navigations.');
        phase2Assert(str_contains($shim, "require dirname(__DIR__) . '/app/owner_switch_account.php'")
            && str_contains($callback, 'resolveAuth0InternalUser')
            && !preg_match('/logout_uri|federated|account_link|account_merge|INSERT\s+INTO\s+users/i', $route), 'Selection must return through the existing exact binding and never federate logout or create accounts.');
        phase2Assert(str_contains($retry, "httpRequireMethod(['POST'])")
            && str_contains($retry, "requireValidCsrfToken(\$_POST['csrf_token'] ?? null)")
            && strpos($retry, 'requireValidCsrfToken') < strpos($retry, 'beginFreshOwnerAccountSelectionSession')
            && str_contains($retry, 'currentInternalUserSession() !== null')
            && str_contains($retry, "consumeRateLimit('oidc_start'")
            && str_contains($retry, "'select_account'")
            && str_contains($retryShim, "require dirname(__DIR__) . '/app/owner_auth_retry.php'"), 'Recovery retry lost its anonymous POST, CSRF, rotation, or chooser contract.');
        phase2Assert(str_contains($callback, "=== 'authorization_denied'")
            && str_contains($callback, 'beginFreshOwnerAccountSelectionSession()')
            && str_contains($callback, 'renderOwnerAuthRecoveryPage(')
            && str_contains($recovery, "header('Cache-Control: no-store')")
            && str_contains($recovery, 'getCsrfToken()')
            && str_contains($recovery, 'action="/owner_auth_retry.php" method="POST"')
            && str_contains($recovery, 'href="/owner_login.php"')
            && str_contains($recovery, 'autofocus')
            && str_contains($recovery, 'lang="ar" dir="rtl"')
            && str_contains($recovery, 'You are signed out. No data was changed.')
            && str_contains($recovery, 'تم تسجيل خروجك. لم تتغير أي بيانات.')
            && !str_contains($recovery, '<script')
            && str_contains($stylesheet, '.owner-auth-recovery :is(button,a):focus')
            && str_contains($stylesheet, '@media(max-width:400px)'), 'Recovery page lost bilingual, no-JavaScript, focus, mobile, or fixed-destination behavior.');
        phase2Assert(!preg_match('/logout_uri|federated|account_link|account_merge|INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM|\$_(?:GET|POST|REQUEST)\[(?:.redirect|.return|.next)/i', $retry . $recovery), 'Recovery retry may not federate logout, mutate accounts, or accept a return URL.');

        require_once $root . 'includes/owner_layout.php';
        $previousSession = $_SESSION ?? [];
        $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $outputLevel = ob_get_level();
        try {
            $_SESSION = ['csrf_token' => str_repeat('a', 64)];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            ob_start();
            ownerAccountSelectionForm();
            $standard = (string) ob_get_clean();
            ob_start();
            ownerAccountSelectionForm(true);
            $hub = (string) ob_get_clean();
            foreach ([$standard, $hub] as $rendered) {
                phase2Assert(str_contains($rendered, 'method="POST"')
                    && str_contains($rendered, 'action="/owner_switch_account.php"')
                    && str_contains($rendered, 'name="csrf_token"')
                    && str_contains($rendered, 'value="' . str_repeat('a', 64) . '"')
                    && str_contains($rendered, '<button ')
                    && str_contains($rendered, 'Use another account')
                    && str_contains($rendered, '<span lang="ar" dir="rtl">استخدام حساب آخر</span>'), 'Rendered account chooser lost its POST, CSRF, keyboard, English, or Arabic contract.');
            }
            phase2Assert(str_contains($hub, 'data-sidebar-tooltip="Use another account"')
                && str_contains($hub, 'evidence-hub-sidebar-label'), 'Collapsed Evidence Hub chooser lost its accessible label.');
        } finally {
            while (ob_get_level() > $outputLevel) {
                ob_end_clean();
            }
            $_SESSION = $previousSession;
            if ($previousMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previousMethod;
            }
        }
    }
}
