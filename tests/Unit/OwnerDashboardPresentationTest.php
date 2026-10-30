<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OwnerDashboardPresentationTest extends TestCase
{
    private function render(?array $data, string $locale): DOMXPath
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_dashboard_presentation.php';
        $saved = [$_GET, $_SESSION ?? [], $GLOBALS['owner_workspace_locale'] ?? null];
        $_GET = ['lang' => $locale];
        $_SESSION = ['csrf_token' => str_repeat('b', 64)];
        ob_start();
        try {
            renderOwnerDashboard($data, $locale);
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            [$_GET, $_SESSION, $previousLocale] = $saved;
            if ($previousLocale === null) unset($GLOBALS['owner_workspace_locale']);
            else $GLOBALS['owner_workspace_locale'] = $previousLocale;
        }
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($dom);
    }

    private function data(string $next = 'preview'): array
    {
        return [
            'owner_name' => '<script>اسم Owner</script>', 'first_name' => '<script>اسم</script>',
            'overview' => ['total' => 7, 'published' => 5, 'drafts' => 2, 'needs_writing' => 1, 'recent' => [
                ['id' => 19, 'title' => '<img src=x onerror=alert(1)> مشروع', 'category' => '<script>category</script>', 'published' => false, 'written_parts' => 1],
            ]],
            'next_step' => $next, 'publication_state' => 'published', 'public_url' => 'https://portfolio.example.test/p/owner',
        ];
    }

    public function testContractDataAndNativeDestinationsInBothLanguages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $xpath = $this->render($this->data(), $locale);
            self::assertSame(1, $xpath->query('//html[@lang="' . $locale . '" and @dir="' . ($locale === 'ar' ? 'rtl' : 'ltr') . '"]')->length);
            self::assertSame('<script>اسم</script>', $xpath->evaluate('string(//header[@class="dashboard-header"]//h1/bdi)'));
            self::assertSame(0, $xpath->query('//bdi/script | //h3/img | //p/script')->length);
            self::assertSame('<img src=x onerror=alert(1)> مشروع', $xpath->evaluate('string(//li[@class="dashboard-recent-row"]//h3)'));
            self::assertSame('<script>category</script>', $xpath->evaluate('string(//li[@class="dashboard-recent-row"]//p)'));
            $label = $locale === 'ar' ? 'إضافة مشروع' : 'Add project';
            self::assertSame(1, $xpath->query('//a[@href="/owner_projects.php?add=1" and normalize-space(.)="' . $label . '"]')->length);
            self::assertSame(1, $xpath->query('//a[@href="/owner_projects.php?edit=19&lang=' . $locale . '"]')->length);
            self::assertSame(1, $xpath->query('//a[@href="https://portfolio.example.test/p/owner"]')->length);
            self::assertSame(1, $xpath->query('//section[@data-next-step="preview"]')->length);
            self::assertStringContainsString('7', $xpath->evaluate('string(//section[@data-next-step]/div/p)'));
            self::assertStringContainsString('5', $xpath->evaluate('string(//section[@data-next-step]/div/p)'));
        }
    }

    public function testEverySuggestedStepUsesItsContractDestination(): void
    {
        foreach (['first_project' => '/owner_projects.php?add=1', 'write_evidence' => '/owner/evidence-hub', 'publish_drafts' => '/owner_projects.php?lang=en', 'preview' => '/owner_preview.php'] as $step => $href) {
            $xpath = $this->render($this->data($step), 'en');
            self::assertSame(1, $xpath->query('//section[@data-next-step="' . $step . '"]/a[@href="' . $href . '"]')->length);
        }
    }

    public function testUnavailableReadShowsOnlyRecoveryAndNoPortfolioState(): void
    {
        $xpath = $this->render(null, 'en');
        self::assertSame(1, $xpath->query('//div[@role="alert"]')->length);
        self::assertSame(1, $xpath->query('//div[@role="alert"]/a[@href="/owner.php?lang=en"]')->length);
        self::assertSame(0, $xpath->query('//section[@data-next-step] | //ul[@class="dashboard-recent-list"] | //aside[@class="dashboard-sharing"] | //a[@href="/owner_projects.php?add=1"]')->length);
    }
}
