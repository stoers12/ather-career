<?php
declare(strict_types=1);

final class OwnerWorkspacePresentationTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_layout.php';
        $saved = [$_GET, $_COOKIE, $_SERVER, $_SESSION ?? [], $GLOBALS['owner_workspace_locale'] ?? null];
        try {
            $_SESSION = ['csrf_token' => str_repeat('b', 64)];
            $_SERVER['REQUEST_URI'] = '/owner_projects.php?edit=19&lang=en';
            foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
                $_GET = ['edit' => '19', 'lang' => $locale, 'return' => 'https://untrusted.invalid/', 'portfolio_id' => '44'];
                $_COOKIE = ['ather_owner_locale' => $locale === 'en' ? 'ar' : 'en'];
                $name = '<script>untrusted owner</script>' . str_repeat('LongOwner', 12);
                ob_start();
                ownerLayoutStart('Projects', 'projects', true, ['account_name' => $name]);
                ownerLayoutEnd();
                $rendered = (string) ob_get_clean();
                $dom = new DOMDocument();
                @$dom->loadHTML('<?xml encoding="UTF-8">' . $rendered);
                $xpath = new DOMXPath($dom);
                phase2Assert($xpath->query('//html[@lang="' . $locale . '" and @dir="' . $direction . '"]')->length === 1, 'Workspace language/direction must honor an allowlisted explicit locale.');
                phase2Assert($xpath->query('//body[contains(@class,"owner-workspace-page") and not(contains(@class,"owner-dashboard-page"))]')->length === 1, 'Project/editor pages must use the shared shell without impersonating Dashboard.');
                phase2Assert($xpath->query('//aside//nav/a')->length === 7
                    && $xpath->query('//aside//nav/a[@aria-current="page" and @href="/owner_projects.php?lang=' . $locale . '"]')->length === 1
                    && $xpath->query('//aside//nav/a[@href="/owner/evidence-hub"]')->length === 1, 'Workspace navigation must expose the seven real routes, one correct active page and unchanged Evidence Hub URL.');
                phase2Assert(str_contains($rendered, 'owner_workspace.css?') && !str_contains($rendered, 'owner_dashboard.css?')
                    && !str_contains($rendered, 'href="/admin.css?') && !str_contains($rendered, 'href="/style.css?'), 'Shared pages must not load Dashboard content CSS or the frozen legacy cascade.');
                phase2Assert(str_contains($rendered, '&lt;script&gt;untrusted owner&lt;/script&gt;') && $xpath->query('//bdi/script')->length === 0, 'Workspace identity must escape owner-entered data.');
                foreach (['/owner_switch_account.php', '/owner_logout.php'] as $action) {
                    $form = '//form[@action="' . $action . '" and @method="POST"]';
                    phase2Assert($xpath->query($form)->length === 1 && $xpath->query($form . '/input')->length === 1
                        && $xpath->query($form . '/input[@name="csrf_token" and @value="' . str_repeat('b', 64) . '"]')->length === 1
                        && $xpath->query($form . '/button[@type="submit"]')->length === 1, 'Account forms must retain exact POST destinations, a single CSRF input and a native submit button.');
                }
                phase2Assert($xpath->query('//button[@data-owner-theme]')->length === 2
                    && $xpath->query('//button[@data-owner-nav-toggle and @aria-controls="owner-navigation" and @aria-expanded="false"]')->length === 1, 'The shared shell must expose accessible theme and menu controls.');
                $alternate = $locale === 'ar' ? 'en' : 'ar';
                $url = ownerLanguageUrl($alternate);
                phase2Assert(str_contains($url, 'edit=19') && str_contains($url, 'lang=' . $alternate)
                    && !str_contains($url, 'return') && !str_contains($url, 'portfolio_id') && !str_contains($url, 'https:'), 'Language switching must preserve editor state without forwarding authority or return selectors.');
                phase2Assert(ownerText('Full Name') === ($locale === 'ar' ? 'الاسم الكامل' : 'Full Name'), 'Field labels must resolve in the active workspace locale.');
                phase2Assert(ownerText('Synthetic owner text') === 'Synthetic owner text', 'User/domain content must not be translated by UI copy lookup.');
            }
            $_GET = [];
            $_COOKIE = ['ather_owner_locale' => 'en'];
            phase2AssertSame('en', ownerLocale(), 'A valid presentation preference must survive navigation without a lang query.');
            $_GET = ['lang' => ['en']];
            phase2AssertSame('ar', ownerLocale(), 'Structured locale input must safely fall back without becoming authority.');
            $_GET = [];
            $_COOKIE = ['ather_owner_locale' => ['en']];
            phase2AssertSame('ar', ownerLocale(), 'Structured preference input must safely fall back.');

            $_GET = [];
            $_COOKIE = [];
            ob_start();
            ownerLayoutStart('Dashboard', 'dashboard', true, ['locale' => 'en']);
            ownerLayoutEnd();
            $explicitLocale = (string) ob_get_clean();
            phase2Assert(str_contains($explicitLocale, '<html lang="en" dir="ltr">')
                && str_contains($explicitLocale, 'href="/owner_projects.php?lang=en"'), 'The existing explicit layout locale must also drive navigation labels and destinations.');

            $_GET = ['lang' => 'ar'];
            ob_start();
            ownerLayoutStart('Evidence Hub', 'evidence_hub', true);
            ownerLayoutEnd();
            $hub = (string) ob_get_clean();
            phase2Assert(str_contains($hub, '<html lang="en" dir="ltr">')
                && !str_contains($hub, 'owner_workspace.css') && !str_contains($hub, 'owner-toolbar')
                && !str_contains($hub, 'data-owner-confirm-') && str_contains($hub, 'evidence_hub.css?')
                && !isset($GLOBALS['owner_workspace_locale']), 'Evidence Hub must remain in its own English shell, styles and behavior regardless of workspace preference.');
        } finally {
            [$_GET, $_COOKIE, $_SERVER, $_SESSION, $locale] = $saved;
            if ($locale === null) {
                unset($GLOBALS['owner_workspace_locale']);
            } else {
                $GLOBALS['owner_workspace_locale'] = $locale;
            }
        }
    }
}
