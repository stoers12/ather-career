<?php

declare(strict_types=1);

final class PortfolioPresentationStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $public = self::read('public_portfolio.php');
        $preview = self::read('owner_preview.php');
        $presentation = self::read('includes/portfolio_presentation.php');
        $stylesheet = self::read('portfolio.css');
        $script = self::read('portfolio.js');
        $dockerfile = self::read('Dockerfile.production');

        phase2Assert(str_contains($public, 'resolvePublicReadContext') && str_contains($public, 'renderPortfolioPresentation('), 'Public Portfolio does not resolve publication authority before the shared presentation.');
        phase2Assert(str_contains($preview, 'requireOwnerPortfolioContext') && str_contains($preview, 'renderPortfolioPresentation(') && !str_contains($preview, 'ownerLayoutStart'), 'Private preview does not use owner authority and the shared standalone presentation.');
        phase2Assert(str_contains($presentation, '$preview = ($options[\'preview\'] ?? false) === true;'), 'Shared presentation preview mode must be caller-controlled.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationEscape(') && str_contains($presentation, 'isPublicWebsiteDestination('), 'Portfolio content or external links lack presentation safety checks.');
        phase2Assert(str_contains($presentation, '<header class="portfolio-header">') && str_contains($presentation, '<div class="portfolio-header-inner">') && str_contains($presentation, 'data-portfolio-section="top" aria-current="page">Home</a>') && str_contains($presentation, 'href="#about" data-portfolio-section="about">About</a>') && str_contains($presentation, 'href="#projects" data-portfolio-section="projects">Projects</a>') && str_contains($presentation, 'href="#skills" data-portfolio-section="skills">Skills</a>') && str_contains($presentation, 'href="#contact" data-portfolio-section="contact">Contact</a>') && str_contains($presentation, 'portfolio-nav-placeholder" aria-disabled="true" title="Coming soon">Experience</span>') && str_contains($presentation, 'portfolio-nav-placeholder" aria-disabled="true" title="Coming soon">Insights</span>') && str_contains($presentation, 'portfolio-header-cta') && str_contains($presentation, '<svg viewBox="0 0 24 24"'), 'S01 header navigation, disabled future entries, or contact CTA is incomplete.');
        phase2Assert(!str_contains($presentation, 'href="#experience"') && !str_contains($presentation, 'href="#insights"'), 'S01 must not create false navigation targets for unfinished sections.');
        phase2Assert(strpos($presentation, '<header class="portfolio-header">') < strpos($presentation, '<main id="portfolio-main">'), 'S01 header must be the first Portfolio content element.');
        phase2Assert(str_contains($presentation, 'count($projects)') && str_contains($presentation, 'count($skills)'), 'Portfolio metrics are not derived from current scoped data.');
        phase2Assert(str_contains($presentation, 'portfolio-project-visual--') && str_contains($presentation, 'portfolioPresentationProjectVisual(') && !str_contains($presentation, 'profileInitials($projectTitle)'), 'Image-free project cards must use deterministic decorative artwork rather than title initials.');
        phase2Assert(str_contains($presentation, 'portfolio-profile-fallback') && str_contains($presentation, 'portfolio-profile-orbit'), 'Image-free profile presentation must use the premium fallback artwork.');
        phase2Assert(str_contains($presentation, 'Private preview') && str_contains($presentation, 'The contact form is inactive in private preview.'), 'Private preview distinction is incomplete.');
        foreach (['Years Experience', 'Certifications', 'Happy Clients', 'Industry Awards', 'Testimonials', 'Trusted By', 'Resume download', 'Newsletter', 'Experience timeline'] as $unsupportedClaim) {
            phase2Assert(!str_contains($presentation, $unsupportedClaim), "Unsupported Portfolio claim was introduced: {$unsupportedClaim}");
        }

        phase2Assert(str_contains($stylesheet, '--pf-container: 1260px') && str_contains($stylesheet, 'portfolio-hero-wave') && str_contains($stylesheet, 'portfolio-hero-grid-lines'), 'V2 enterprise background and density system is incomplete.');
        phase2Assert(str_contains($stylesheet, '.portfolio-header-inner') && str_contains($stylesheet, 'grid-template-columns: minmax(200px, 230px) minmax(0, 1fr) minmax(152px, 160px)') && str_contains($stylesheet, 'justify-self: end') && str_contains($stylesheet, 'min-height: 66px') && str_contains($stylesheet, 'position: sticky') && str_contains($stylesheet, 'portfolio-header-cta') && str_contains($stylesheet, 'white-space: nowrap') && str_contains($stylesheet, 'a.is-current::after'), 'S01 premium sticky header, bounded shared geometry, CTA, or active underline is incomplete.');
        phase2Assert(str_contains($script, 'IntersectionObserver') && str_contains($script, 'data-portfolio-section') && str_contains($script, 'aria-current') && str_contains($script, 'const initialId = window.location.hash.slice(1)') && !str_contains($script, 'experience') && !str_contains($script, 'insights'), 'S01 scrollspy must apply only to implemented Portfolio sections and honor direct section links.');
        phase2Assert(str_contains($stylesheet, ':focus-visible') && str_contains($stylesheet, 'prefers-reduced-motion'), 'Portfolio focus or reduced-motion behavior is missing.');
        foreach (['@media (max-width: 1100px)', '@media (max-width: 820px)', '@media (max-width: 620px)', '@media (max-width: 430px)'] as $breakpoint) {
            phase2Assert(str_contains($stylesheet, $breakpoint), "Portfolio responsive breakpoint is missing: {$breakpoint}");
        }
        phase2Assert(str_contains($dockerfile, '/var/www/app/portfolio.css') && str_contains($dockerfile, '/var/www/app/portfolio.js'), 'Production image does not publish the Portfolio presentation assets.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/portfolio_presentation.php';
        ob_start();
        renderPortfolioPresentation(
            [
                'full_name' => '<Owner>',
                'professional_title' => 'Engineer',
                'about_me' => 'Fuller safe narrative',
                'work_description' => 'Professional summary',
                'github_url' => 'https://example.test/profile',
                'website_url' => 'javascript:alert(1)',
            ],
            [['skill_name' => '<PHP>']],
            [['id' => 7, 'title' => '<Project>', 'category' => 'Data Science', 'description' => 'Description', 'github_url' => 'https://example.test/project', 'image_path' => null]],
            [
                'project_media_url' => static fn (int $projectId): string => "/media/{$projectId}",
                'contact_action' => '/p/safe/contact',
            ],
        );
        $rendered = ob_get_clean();
        phase2Assert(is_string($rendered) && str_contains($rendered, '&lt;Owner&gt;') && str_contains($rendered, '&lt;PHP&gt;') && str_contains($rendered, '&lt;Project&gt;'), 'Shared Portfolio presentation does not encode user content.');
        phase2Assert(!str_contains($rendered, 'javascript:alert') && str_contains($rendered, 'action="/p/safe/contact"') && str_contains($rendered, 'portfolio-project-visual--data-science'), 'Shared Portfolio presentation accepted an unsafe URL, lost the scoped contact action, or lost its decorative project art.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Preview Owner'], [], [], ['preview' => true]);
        $previewRendered = ob_get_clean();
        phase2Assert(is_string($previewRendered) && str_contains($previewRendered, 'Private preview') && str_contains($previewRendered, 'disabled aria-disabled="true"'), 'Private preview rendering is not visibly distinct and inert.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
