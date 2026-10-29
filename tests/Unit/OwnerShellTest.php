<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The owner workspace shell rendered with the reference markup (UI-02, ق-29). */
final class OwnerShellTest extends TestCase
{
    private string $publicRoot;

    public static function setUpBeforeClass(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_layout.php';
    }

    protected function setUp(): void
    {
        $this->publicRoot = sys_get_temp_dir() . '/ather-owner-shell-' . bin2hex(random_bytes(6));
        mkdir($this->publicRoot . '/assets/css', 0700, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->publicRoot . '/assets/css/owner-page-projects.css');
        @rmdir($this->publicRoot . '/assets/css');
        @rmdir($this->publicRoot . '/assets');
        @rmdir($this->publicRoot);
    }

    private static function render(string $page, string $locale): DOMXPath
    {
        $_SESSION = ['csrf_token' => str_repeat('c', 64)];
        $_GET = ['lang' => $locale];
        ob_start();
        ownerLayoutStart('Projects', $page, true, ['locale' => $locale, 'account_name' => 'Momen Al Omari']);
        ownerLayoutEnd();
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . (string) ob_get_clean());

        return new DOMXPath($dom);
    }

    public function testPageStylesheetLoadsOnlyWhenThePageHasOne(): void
    {
        self::assertNull(ownerPageStylesheet('projects', $this->publicRoot));
        file_put_contents($this->publicRoot . '/assets/css/owner-page-projects.css', "/* page */\n");
        self::assertSame('assets/css/owner-page-projects.css', ownerPageStylesheet('projects', $this->publicRoot));
        foreach (['../projects', 'Projects', 'projects.css', ''] as $unsafe) {
            self::assertNull(ownerPageStylesheet($unsafe, $this->publicRoot), "'{$unsafe}' must not resolve to a stylesheet");
        }
    }

    public function testAvatarShowsTheFirstTwoLettersLikeTheReference(): void
    {
        self::assertSame('MO', ownerAccountInitials('Momen Al Omari'));
        self::assertSame('مؤ', ownerAccountInitials('مؤمن عمري'));
        self::assertSame('', ownerAccountInitials('   '));
    }

    /** @return iterable<string, array{string, string}> */
    public static function pages(): iterable
    {
        yield 'Arabic overview' => ['dashboard', 'ar'];
        yield 'English projects' => ['projects', 'en'];
    }

    #[DataProvider('pages')]
    public function testShellUsesTheReferenceStructure(string $page, string $locale): void
    {
        $xpath = self::render($page, $locale);
        $root = '//div[@class="export-frame"]/div[@id="ather-navigation" and @data-ath-theme="system"]';
        self::assertSame(1, $xpath->query($root . '[@dir="' . ($locale === 'ar' ? 'rtl' : 'ltr') . '" and @lang="' . $locale . '" and @data-page="' . ($page === 'dashboard' ? 'overview' : $page) . '"]')->length);
        self::assertSame(1, $xpath->query($root . '/div[contains(@class,"ath-shell")]/aside[contains(@class,"ath-sidebar")]')->length);
        // Overview, the five grouped pages and Evidence Hub, in the reference order.
        $labels = array_map(static fn (DOMNode $node): string => trim($node->textContent), iterator_to_array($xpath->query('//nav[@class="ath-nav"]//a[contains(@class,"an-item")]/span[@class="an-item-label"]')));
        $expected = $locale === 'ar'
            ? ['الرئيسية', 'الملف المهني', 'المشاريع', 'الخبرات', 'نشر الملف', 'الرسائل', 'مركز الأدلة']
            : ['Overview', 'Profile', 'Projects', 'Experience', 'Publish portfolio', 'Messages', 'Evidence Hub'];
        self::assertSame($expected, $labels);
        self::assertSame(2, $xpath->query('//details[contains(@class,"an-group") and @open]/summary[@class="an-group-toggle"]')->length, 'Groups start open and work without JavaScript');
        // Settings and Help are visible but inactive until their pages exist (ق-29).
        self::assertSame(2, $xpath->query('//nav[@class="an-utilities"]/button[@aria-disabled="true"]')->length);
        self::assertSame(1, $xpath->query('//details[@class="ath-account-area"]/summary[contains(@class,"ath-account-trigger")]/span[@class="ath-avatar" and .="MO"]')->length);
        // The toolbar action follows the reference: publishing on Overview, the preview elsewhere.
        $action = $page === 'dashboard' ? '/owner_publication.php?lang=' . $locale : '/owner_preview.php';
        self::assertSame(1, $xpath->query('//header[contains(@class,"ath-toolbar")]/div[@class="ath-actions"]/a[@href="' . $action . '"]')->length);
        // No language switch in the workspace: the reference keeps it in Settings (ق-29).
        self::assertSame(0, $xpath->query('//a[@hreflang]')->length);
    }
}
