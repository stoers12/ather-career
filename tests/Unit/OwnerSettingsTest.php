<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Backend of the owner settings page (decision ق-31, docs/contracts/owner-settings.md). */
final class OwnerSettingsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_settings.php';
    }

    public function testRouteServesTheSettingsHandler(): void
    {
        $routes = require PHASE2_REPOSITORY_ROOT . '/config/routes.php';
        self::assertSame('handlers/owner_settings.php', $routes['scripts']['owner_settings.php']);
    }

    public function testHandlerIsOwnerOnlyAndReadOnly(): void
    {
        $handler = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/handlers/owner_settings.php');
        $order = [
            'startOwnerSession();',
            "httpRequireMethod(['GET', 'HEAD']);",
            'requireOwnerPortfolioContext($database)',
            'ownerLayoutStart(',
        ];
        $position = -1;
        foreach ($order as $step) {
            $next = strpos($handler, $step);
            self::assertNotFalse($next, "Missing: {$step}");
            self::assertGreaterThan($position, $next, "{$step} runs out of order: the owner must be confirmed before any chrome renders");
            $position = $next;
        }
        self::assertStringContainsString("httpAbortHtml(503,", $handler, 'Without the database no account chrome renders');
        self::assertStringNotContainsString('$_POST', $handler, 'Settings write nothing on the server');
    }

    public function testLanguageLinksStayOnSettings(): void
    {
        $saved = [$_SERVER['REQUEST_URI'] ?? null, $_GET];
        try {
            $_SERVER['REQUEST_URI'] = '/owner_settings.php?lang=ar';
            $_GET = ['lang' => 'ar', 'return' => 'https://untrusted.invalid/'];
            $data = ownerSettingsPageData('ar');
            self::assertSame('ar', $data['locale']);
            self::assertSame([
                ['code' => 'ar', 'url' => '/owner_settings.php?lang=ar', 'current' => true],
                ['code' => 'en', 'url' => '/owner_settings.php?lang=en', 'current' => false],
            ], $data['languages']);
            self::assertSame('ar', ownerSettingsPageData('unexpected')['locale'], 'Unknown locales fall back to Arabic, like ownerLocale()');
        } finally {
            [$_SERVER['REQUEST_URI'], $_GET] = $saved;
        }
    }

    public function testBrowserPreferencesMatchTheContract(): void
    {
        $data = ownerSettingsPageData('en');
        self::assertSame('ather.evidenceHub.theme', $data['appearance']['storage_key'], 'The existing theme key stays the single theme store');
        self::assertSame(['system', 'light', 'dark'], $data['appearance']['options']);
        self::assertSame(['default', 'large'], $data['text_size']['options']);
        self::assertSame(['system', 'reduce'], $data['motion']['options']);
        $theme = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/public/assets/js/owner_theme.js');
        self::assertStringContainsString("'" . OWNER_SETTINGS_THEME_KEY . "'", $theme, 'Backend and owner_theme.js must share the theme key');
    }
}
