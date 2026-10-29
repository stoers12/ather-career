<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The shared stylesheet public/assets/css/ather.css (UI-01, docs/DESIGN_SYSTEM.md). */
final class DesignSystemTest extends TestCase
{
    private const STYLESHEET = PHASE2_REPOSITORY_ROOT . '/public/assets/css/ather.css';

    private static function css(): string
    {
        $css = file_get_contents(self::STYLESHEET);
        self::assertIsString($css);

        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /** @return array<string, string> */
    private static function tokens(string $selector): array
    {
        $found = preg_match_all('/(?:^|[{}])\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', self::css(), $blocks);
        self::assertGreaterThan(0, $found, "No token block for {$selector}");
        $tokens = [];
        foreach ($blocks[1] as $block) {
            preg_match_all('/--ath-([a-z-]+):\s*(#[0-9a-fA-F]{3,8})\s*;/', $block, $pairs, PREG_SET_ORDER);
            foreach ($pairs as [, $name, $value]) {
                $tokens[$name] = $value;
            }
        }

        return $tokens;
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $channels = array_map(static function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 0, 6), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function contrast(string $foreground, string $background): float
    {
        $a = self::luminance($foreground);
        $b = self::luminance($background);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public function testEveryRuleIsScopedToOptedInPages(): void
    {
        $css = (string) preg_replace('/@font-face\s*\{[^}]*\}/', '', self::css());
        $css = (string) preg_replace('/@media[^{]*\{/', '', $css);
        preg_match_all('/([^{}]+)\{[^{}]*\}/', $css, $rules);
        self::assertNotEmpty($rules[1]);
        foreach ($rules[1] as $selectorList) {
            // Split on top-level commas only; :is(h1, h2) is one selector.
            foreach (preg_split('/,(?![^()]*\))/', $selectorList) ?: [] as $selector) {
                if (trim($selector) === '') {
                    continue;
                }
                self::assertMatchesRegularExpression('/^\s*(body)?\.ath-app\b/', $selector, 'Unscoped selector: ' . trim($selector));
            }
        }
    }

    public function testAssetsAreLocalAndLicensed(): void
    {
        preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/', self::css(), $urls);
        self::assertCount(4, $urls[1], 'Four font faces are expected');
        foreach ($urls[1] as $url) {
            self::assertStringStartsWith('../fonts/', $url, 'Assets must load from the site itself (CSP font-src self)');
            $path = PHASE2_REPOSITORY_ROOT . '/public/assets/fonts/' . substr($url, strlen('../fonts/'));
            self::assertFileExists($path);
            self::assertSame('wOF2', (string) file_get_contents($path, false, null, 0, 4), "{$url} is not WOFF2");
        }
        foreach (['IBM-PLEX-SANS-ARABIC-OFL.txt', 'INTER-OFL.txt'] as $license) {
            self::assertStringContainsString('SIL Open Font License', (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/resources/licenses/' . $license));
        }
    }

    public function testDarkThemeIsIdenticalWithAndWithoutJavaScript(): void
    {
        $explicit = self::tokens('body.ath-app[data-evidence-hub-theme="dark"]');
        $system = self::tokens('body.ath-app:not([data-evidence-hub-theme="light"])');
        self::assertNotEmpty($explicit);
        self::assertSame($explicit, $system, 'The device preference and the saved choice must render the same dark palette');
    }

    /** @return iterable<string, array{string, list<array{string, string, float}>}> */
    public static function palettes(): iterable
    {
        $text = 4.5;
        $control = 3.0;
        $pairs = [
            ['text', 'bg', $text], ['text', 'surface', $text], ['text', 'raised', $text],
            ['muted', 'bg', $text], ['muted', 'surface', $text], ['muted', 'raised', $text],
            ['gold', 'bg', $text], ['gold', 'surface', $text],
            ['green', 'surface', $text], ['green', 'soft', $text], ['on-green', 'green', $text],
            ['danger', 'danger-bg', $text], ['danger', 'surface', $text],
            ['input-line', 'surface', $control],
        ];
        yield 'light' => ['body.ath-app', $pairs];
        yield 'dark' => ['body.ath-app[data-evidence-hub-theme="dark"]', $pairs];
        yield 'sidebar' => ['body.ath-app .ath-sidebar', [
            ['text', 'sidebar', $text], ['muted', 'sidebar', $text], ['gold', 'sidebar', $text], ['green', 'sidebar', $text],
            ['text', 'raised', $text], ['muted', 'raised', $text], ['text', 'surface', $text],
        ]];
    }

    /** @param list<array{string, string, float}> $pairs */
    #[DataProvider('palettes')]
    public function testPaletteMeetsWcagAa(string $selector, array $pairs): void
    {
        $tokens = self::tokens($selector);
        if ($selector !== 'body.ath-app') {
            // Overrides inherit every token they do not redefine.
            $tokens += self::tokens('body.ath-app');
        }
        foreach ($pairs as [$foreground, $background, $minimum]) {
            self::assertArrayHasKey($foreground, $tokens);
            self::assertArrayHasKey($background, $tokens);
            $ratio = self::contrast($tokens[$foreground], $tokens[$background]);
            self::assertGreaterThanOrEqual($minimum, $ratio, sprintf('%s/%s is %.2f:1 in %s', $foreground, $background, $ratio, $selector));
        }
    }
}
