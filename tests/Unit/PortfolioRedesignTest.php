<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/public_project_presentation.php';

final class PortfolioRedesignTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['lang'], $_COOKIE['ather_owner_locale']);
    }

    private function portfolio(array $options = []): string
    {
        ob_start();
        renderPortfolioPresentation(
            ['full_name' => 'Taylor Lee', 'professional_title' => 'Engineer', 'about_me' => "About <work>\nSecond line"],
            [['skill_name' => '<PHP>']],
            [
                ['id' => 7, 'title' => '<Project>', 'description' => '<script>private-looking input</script>', 'technologies' => ['<PHP>', 'SQL', 'CSS', 'HTML'], 'live_url' => 'https://example.org/demo?a=1&b=2', 'github_url' => 'javascript:alert(1)'],
                ['id' => 8, 'title' => 'Second', 'live_url' => 'http://127.0.0.1/private', 'problem_statement' => 'PRIVATE_EVIDENCE_SENTINEL', 'sources' => ['PRIVATE_SOURCE_SENTINEL']],
            ],
            [...$options, 'project_case_url' => static fn (int $id): string => ($options['preview'] ?? false) ? '/owner_preview.php?project=' . $id : '/p/taylor/projects/' . $id],
        );
        return (string) ob_get_clean();
    }

    public function testPublicDocumentRemainsEnglishAndHasOnlySafePublicContent(): void
    {
        $_GET['lang'] = 'ar';
        $_COOKIE['ather_owner_locale'] = 'ar';
        $html = $this->portfolio(['canonical_url' => 'https://portfolio.example.test/p/taylor']);
        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringNotContainsString('portfolio-preview-bar', $html);
        self::assertStringNotContainsString('Insights', $html);
        self::assertStringNotContainsString('Download Resume', $html);
        self::assertStringNotContainsString('PRIVATE_EVIDENCE_SENTINEL', $html);
        self::assertStringNotContainsString('PRIVATE_SOURCE_SENTINEL', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('127.0.0.1', $html);
        self::assertStringContainsString('href="/p/taylor/projects/7"', $html);
        self::assertStringContainsString('href="https://example.org/demo?a=1&amp;b=2" rel="noopener noreferrer">View live project', $html);
        self::assertStringContainsString('&lt;script&gt;private-looking input&lt;/script&gt;', $html);
        self::assertStringContainsString('About &lt;work&gt;<br />', $html);
        self::assertStringContainsString('aria-label="1 more technologies">+1', $html);
        self::assertSame(2, substr_count($html, 'portfolio-neutral-cover'));
        self::assertStringContainsString('>TL</span>', $html, 'Initials must derive from the real name');
    }

    public function testPreviewBarUsesActualStateAndOwnerLocaleOnly(): void
    {
        $_GET['lang'] = 'ar';
        foreach ([true => 'منشور', false => 'خاص'] as $published => $label) {
            $html = $this->portfolio(['preview' => true, 'portfolio_published' => (bool) $published, 'canonical_url' => 'https://example.test/public']);
            self::assertStringContainsString('<html lang="en">', $html);
            self::assertStringContainsString('lang="ar" dir="rtl"', $html);
            self::assertStringContainsString('<strong>' . $label . '</strong>', $html);
            self::assertStringContainsString('href="/owner.php"', $html);
            self::assertStringContainsString('href="/owner_preview.php?project=7"', $html);
            self::assertStringContainsString('noindex,nofollow', $html);
            self::assertStringNotContainsString('rel="canonical"', $html);
        }
        $html = $this->portfolio(['preview' => true, 'portfolio_published' => null]);
        self::assertStringContainsString('حالة النشر غير متاحة', $html);
        self::assertStringNotContainsString('<strong>خاص</strong>', $html, 'A 503 is not a private state');
    }
}
