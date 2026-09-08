<?php

declare(strict_types=1);

final class ResponsiveAccessibilityStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $http = self::read('includes/http.php');
        $publicPortfolio = self::read('public_portfolio.php');
        $publicContact = self::read('public_contact.php');
        $presentation = self::read('includes/portfolio_presentation.php');
        $portfolioCss = self::read('portfolio.css');
        $portfolioJavascript = self::read('portfolio.js');
        $adminJavascript = self::read('admin.js');

        phase2Assert(
            str_contains($http, 'function httpRenderStatusPage')
            && str_contains($http, '<meta name="viewport"')
            && str_contains($http, '<main id="request-main">'),
            'Rendered HTML status pages must expose responsive viewport and primary-content semantics.',
        );
        phase2Assert(
            str_contains($publicPortfolio, "httpRenderStatusPage('Portfolio not found'")
            && str_contains($publicContact, "httpRenderStatusPage('Message unavailable'"),
            'Public unavailable responses must reuse the semantic HTML status-page renderer.',
        );

        phase2Assert(
            str_contains($presentation, 'class="portfolio-skip-link" href="#portfolio-main"')
            && str_contains($presentation, 'id="portfolio-main"')
            && str_contains($presentation, 'id="portfolio-title"'),
            'Public Portfolio skip navigation and return focus target are incomplete.',
        );
        phase2Assert(
            substr_count($presentation, "if (\$presentationExperiences !== []): ?><a href=\"#experience\"") === 2,
            'Experience navigation must not link to an absent Experience section.',
        );
        phase2Assert(
            str_contains($presentation, 'role="dialog" aria-modal="true" aria-labelledby="portfolio-mobile-navigation-title"')
            && str_contains($presentation, 'data-portfolio-error-summary'),
            'Public dialog semantics or validation-summary focus target are missing.',
        );

        phase2Assert(
            str_contains($portfolioCss, 'min-width: 0;')
            && !str_contains($portfolioCss, 'min-width: 320px;')
            && str_contains($portfolioCss, '.portfolio-js .portfolio-nav-links { display: none; }')
            && str_contains($portfolioCss, '.portfolio-js .portfolio-menu-toggle { display: inline-flex; }')
            && str_contains($portfolioCss, '.portfolio-mobile-nav-layer { z-index: 70; }'),
            'Portfolio narrow-screen reflow, no-JavaScript navigation, or modal stacking safeguard is missing.',
        );
        phase2Assert(
            str_contains($portfolioCss, '@media (prefers-reduced-motion: reduce)')
            && str_contains($portfolioCss, '#portfolio-title:focus'),
            'Portfolio reduced-motion or visible back-to-top focus treatment is missing.',
        );
        phase2Assert(
            str_contains($portfolioJavascript, "document.documentElement.classList.add('portfolio-js')")
            && str_contains($portfolioJavascript, "background.forEach((element) => element.setAttribute('inert', ''))")
            && str_contains($portfolioJavascript, "title.setAttribute('tabindex', '-1')")
            && str_contains($portfolioJavascript, 'title.focus({ preventScroll: true })')
            && str_contains($portfolioJavascript, 'errorSummary.focus()'),
            'Portfolio progressive navigation isolation, return focus, or validation focus behavior is incomplete.',
        );
        phase2Assert(
            str_contains($adminJavascript, 'aria-describedby="confirm-message"')
            && str_contains($adminJavascript, 'id="confirm-message"'),
            'Owner confirmation dialog must describe its affected action.',
        );
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
