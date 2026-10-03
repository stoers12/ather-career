<?php

declare(strict_types=1);

final class FrontendPerformanceResilienceStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $presentation = self::read('includes/portfolio_presentation.php');
        $public = self::read('public_portfolio.php');
        $stylesheet = self::read('portfolio.css');
        $javascript = self::read('portfolio.js');
        $ownerLayout = self::read('includes/owner_layout.php');

        phase2Assert(
            str_contains($public, "require_once __DIR__ . '/includes/public_url.php';")
            && str_contains($public, "'canonical_url' => publicPortfolioUrl(\$slug)"),
            'Published Portfolio pages must pass the configured canonical URL to the shared renderer.',
        );
        phase2Assert(
            str_contains($presentation, 'filter_var($options[\'canonical_url\'], FILTER_VALIDATE_URL)')
            && str_contains($presentation, '<link rel="canonical" href="')
            && str_contains($presentation, 'loading="eager" fetchpriority="high" decoding="async"')
            && !str_contains($presentation, 'onerror='),
            'Public canonical metadata or Hero image loading priority is incomplete.',
        );
        phase2Assert(
            substr_count($presentation, 'src="<?php echo portfolioPresentationEscape($script); ?>" defer') === 1
            && !str_contains($presentation, '<script>'),
            'The Portfolio enhancement script must have one external deferred application entry point without inline CSP exceptions.',
        );

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Canonical Owner'], [], [], ['canonical_url' => 'https://portfolio.example.test/p/canonical-owner']);
        $publicMarkup = (string) ob_get_clean();
        ob_start();
        renderPortfolioPresentation(['full_name' => 'Preview Owner'], [], [], ['preview' => true, 'canonical_url' => 'https://portfolio.example.test/p/preview-owner']);
        $previewMarkup = (string) ob_get_clean();
        ob_start();
        renderPortfolioPresentation(['full_name' => 'Unsafe Canonical'], [], [], ['canonical_url' => 'javascript:alert(1)']);
        $unsafeCanonicalMarkup = (string) ob_get_clean();
        phase2Assert(str_contains($publicMarkup, '<link rel="canonical" href="https://portfolio.example.test/p/canonical-owner">'), 'A valid public canonical URL was not rendered.');
        phase2Assert(!str_contains($previewMarkup, 'rel="canonical"'), 'Private Preview must not render public canonical metadata.');
        phase2Assert(!str_contains($unsafeCanonicalMarkup, 'rel="canonical"'), 'Unsafe canonical metadata must not be rendered.');
        phase2Assert(
            str_contains($ownerLayout, '<meta name="robots" content="noindex,nofollow">')
            && !str_contains($ownerLayout, 'rel="canonical"'),
            'Owner pages must remain isolated from the public Portfolio canonical identity.',
        );

        ob_start();
        renderPortfolioPresentation(
            ['full_name' => '<Hero Owner>'],
            [],
            [['id' => 7, 'title' => '<Below-fold Project>', 'image_path' => 'safe-image.jpg']],
            [
                'canonical_url' => 'https://portfolio.example.test/p/safe',
                'profile_media_url' => '/p/safe/media/profile',
                'project_media_url' => static fn (int $projectId): string => "/p/safe/media/project/{$projectId}",
            ],
        );
        $imageMarkup = (string) ob_get_clean();
        phase2Assert(
            str_contains($imageMarkup, 'src="/p/safe/media/profile" alt="&lt;Hero Owner&gt; portrait" loading="eager" fetchpriority="high" decoding="async"')
            && str_contains($imageMarkup, 'src="/p/safe/media/project/7" alt="&lt;Below-fold Project&gt; project preview" loading="lazy"'),
            'Hero and below-the-fold project images do not retain their intended loading priorities or escaped safe media URLs.',
        );

        phase2Assert(
            str_contains($stylesheet, '.portfolio-js [data-project-navigator]:not(.portfolio-project-navigator--active) .portfolio-project-card:nth-child(n+4)')
            && str_contains($stylesheet, 'nth-child(n+3)')
            && str_contains($stylesheet, 'nth-child(n+2)'),
            'Enhanced project-window CSS must reserve the same initial card count at desktop, intermediate, and narrow breakpoints.',
        );
        phase2Assert(
            str_contains($stylesheet, '.portfolio-hero-profile-card--image .portfolio-hero-profile-visual { aspect-ratio: 4 / 5; }')
            && str_contains($stylesheet, '.portfolio-project-card--image .portfolio-project-visual { aspect-ratio: 16 / 9; }'),
            'Image containers must reserve the Hero and project aspect ratios before their media finishes loading.',
        );
        phase2Assert(
            str_contains($javascript, "typeof window.IntersectionObserver === 'function'")
            && str_contains($javascript, 'initializeProjectNavigator();')
            && str_contains($javascript, 'initializePortfolioFeedbackAndBackToTop();'),
            'Missing optional IntersectionObserver support must not stop unrelated Portfolio initialization.',
        );
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
