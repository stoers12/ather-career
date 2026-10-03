<?php

declare(strict_types=1);

final class FooterPresentationStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $presentation = self::read('includes/portfolio_presentation.php');
        $stylesheet = self::read('portfolio.css');
        $public = self::read('public_portfolio.php');
        $preview = self::read('owner_preview.php');

        foreach ([
            '<footer class="portfolio-footer">',
            'portfolio-footer-primary',
            'portfolio-footer-brand',
            'portfolio-footer-navigation',
            'portfolio-footer-social',
            'portfolio-footer-secondary',
            'portfolio-footer-copyright',
            'portfolio-footer-back-to-top',
        ] as $required) {
            phase2Assert(str_contains($presentation, $required), "Footer presentation is missing {$required}.");
        }
        phase2Assert(str_contains($stylesheet, '.portfolio-footer-primary { display: grid') && str_contains($stylesheet, 'grid-template-columns: minmax(0, 1.15fr) minmax(0, .9fr) auto') && str_contains($stylesheet, '.portfolio-footer-secondary { display: flex') && str_contains($stylesheet, '@media (max-width: 820px)') && str_contains($stylesheet, '.portfolio-footer-primary { grid-template-columns: 1fr;') && str_contains($stylesheet, 'body.portfolio-page :where(a, button, input, textarea):focus-visible') && !str_contains($stylesheet, 'portfolio-footer-main') && !str_contains($stylesheet, 'portfolio-footer-subscribe') && !str_contains($stylesheet, 'portfolio-footer-resources'), 'Footer styling must provide only a compact responsive primary/secondary composition with visible keyboard focus.');
        phase2Assert(str_contains($public, 'renderPortfolioPresentation(') && str_contains($preview, 'renderPortfolioPresentation('), 'Public Portfolio and Private Preview must share the Footer renderer.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/portfolio_presentation.php';

        ob_start();
        renderPortfolioPresentation(
            [
                'full_name' => '<Footer Owner>',
                'professional_title' => 'Data <Leader>',
                'about_me' => 'A distinct About narrative.',
                'work_description' => 'Builds <safe> data products.',
                'location' => '<Amman>',
                'email' => 'private@example.test',
                'phone_primary' => '+962 00 000 0000',
                'linkedin_url' => 'https://linkedin.example.test/footer-owner',
                'github_url' => 'https://github.example.test/footer-owner',
                'website_url' => 'https://website.example.test/footer-owner',
                'instagram_url' => 'javascript:alert(1)',
            ],
            [['skill_name' => 'PHP']],
            [['id' => 1, 'title' => 'Project', 'category' => 'Data', 'description' => 'Description', 'github_url' => '', 'image_path' => null]],
            [
                'experiences' => [[
                    'experience_type' => 'employment',
                    'role_title' => 'Analyst',
                    'organization' => 'Organization',
                    'location' => null,
                    'start_month' => '2025-01',
                    'end_month' => null,
                    'is_current' => true,
                    'description' => null,
                ]],
            ],
        );
        $rendered = ob_get_clean();
        phase2Assert(is_string($rendered), 'Footer presentation did not render output.');
        $footer = preg_match('/<footer class="portfolio-footer">.*?<\/footer>/s', $rendered, $footerMatch) === 1 ? $footerMatch[0] : '';
        phase2Assert($footer !== '', 'Semantic Footer landmark is missing.');
        [$footerPrimary] = explode('<div class="portfolio-container portfolio-footer-secondary">', $footer, 2);
        $footerNavigation = preg_match('/<nav class="portfolio-footer-navigation".*?<\/nav>/s', $footer, $footerNavigationMatch) === 1 ? $footerNavigationMatch[0] : '';
        phase2Assert(substr_count($footerPrimary, 'aria-label="ATHER, home"') === 1 && str_contains($footerPrimary, 'src="/assets/images/ather-navbar-logo.png"') && str_contains($footerPrimary, 'A professional portfolio built with ATHER.'), 'Footer must render ATHER once as restrained platform identity with its approved supporting sentence.');
        phase2Assert(!str_contains($footerPrimary, '&lt;Footer Owner&gt;') && !str_contains($footerPrimary, 'Data &lt;Leader&gt;') && !str_contains($footerPrimary, 'Builds &lt;safe&gt; data products.') && !str_contains($footerPrimary, '&lt;Amman&gt;'), 'Footer primary area must not repeat Owner identity, title, summary, or location.');
        foreach (['#about', '#projects', '#experience', '#skills', '#contact'] as $target) {
            phase2Assert(str_contains($footerPrimary, 'href="' . $target . '"'), "Footer navigation is missing the real {$target} target.");
        }
        phase2Assert($footerNavigation !== '' && str_contains($footerNavigation, 'aria-label="Portfolio footer navigation"') && !str_contains($footerNavigation, 'href="#top"') && !str_contains($footerNavigation, 'href="#insights"'), 'Footer navigation must be labeled and include only available canonical section targets.');
        foreach (['Quick Links', 'Resources', 'Resume', 'Certifications', 'Insights', 'Case Studies', 'Planned resources', 'Stay Updated', 'Coming soon', 'Send a message', 'Subscribe', 'type="email"', 'aria-disabled="true"'] as $obsolete) {
            phase2Assert(!str_contains($footer, $obsolete), "Obsolete Footer content was retained: {$obsolete}");
        }
        phase2Assert(str_contains($footer, 'href="https://linkedin.example.test/footer-owner" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn profile"') && str_contains($footer, 'href="https://github.example.test/footer-owner" target="_blank" rel="noopener noreferrer" aria-label="GitHub profile"') && str_contains($footer, 'href="https://website.example.test/footer-owner" target="_blank" rel="noopener noreferrer" aria-label="Website profile"') && !str_contains($footer, 'javascript:alert'), 'Footer social controls must use only validated, secure external destinations.');
        phase2Assert(substr_count($footer, '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">') >= 3, 'Footer icon-only social controls must keep decorative SVG treatment.');
        phase2Assert(!str_contains($footer, 'private@example.test') && !str_contains($footer, '+962 00 000 0000'), 'Footer must not expose profile email or phone without a public field contract.');
        phase2Assert(str_contains($footer, '&copy; ' . date('Y') . ' &lt;Footer Owner&gt;. All rights reserved.') && str_contains($footer, 'class="portfolio-footer-back-to-top" href="#top" aria-label="Back to top"') && str_contains($presentation, '<section class="portfolio-hero" id="top"'), 'Footer secondary row must use dynamic escaped copyright and a real Back-to-top anchor.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Minimal Owner', 'website_url' => 'javascript:alert(1)'], [], [], ['experiences' => []]);
        $minimalRendered = ob_get_clean();
        $minimalFooter = is_string($minimalRendered) && preg_match('/<footer class="portfolio-footer">.*?<\/footer>/s', $minimalRendered, $minimalMatch) === 1 ? $minimalMatch[0] : '';
        phase2Assert($minimalFooter !== '' && !str_contains($minimalFooter, 'portfolio-footer-social') && !str_contains($minimalFooter, 'href="#about"') && !str_contains($minimalFooter, 'href="#projects"') && !str_contains($minimalFooter, 'href="#experience"') && !str_contains($minimalFooter, 'href="#skills"') && str_contains($minimalFooter, 'href="#contact"'), 'Footer must omit unavailable socials and conditional section links cleanly while retaining Contact.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Partial Owner', 'linkedin_url' => 'https://linkedin.example.test/partial'], [['skill_name' => 'PHP']], [], ['experiences' => []]);
        $partialRendered = ob_get_clean();
        $partialFooter = is_string($partialRendered) && preg_match('/<footer class="portfolio-footer">.*?<\/footer>/s', $partialRendered, $partialMatch) === 1 ? $partialMatch[0] : '';
        phase2Assert($partialFooter !== '' && str_contains($partialFooter, 'href="#skills"') && str_contains($partialFooter, 'href="#contact"') && !str_contains($partialFooter, 'href="#about"') && !str_contains($partialFooter, 'href="#projects"') && !str_contains($partialFooter, 'href="#experience"') && substr_count($partialFooter, 'portfolio-footer-social') === 1 && str_contains($partialFooter, 'aria-label="LinkedIn profile"'), 'Footer must adapt navigation and social controls to partial Portfolio data.');

        ob_start();
        renderPortfolioPresentation([], [], [], ['preview' => true, 'experiences' => []]);
        $emptyNameRendered = ob_get_clean();
        $emptyNameFooter = is_string($emptyNameRendered) && preg_match('/<footer class="portfolio-footer">.*?<\/footer>/s', $emptyNameRendered, $emptyNameMatch) === 1 ? $emptyNameMatch[0] : '';
        phase2Assert($emptyNameFooter !== '' && str_contains($emptyNameFooter, '&copy; ' . date('Y') . '. All rights reserved.') && !str_contains($emptyNameFooter, 'Your Portfolio'), 'Footer must degrade safely when an Owner name is unexpectedly absent.');

        ob_start();
        renderPortfolioPresentation(
            [
                'full_name' => '<Footer Owner>',
                'professional_title' => 'Data <Leader>',
                'about_me' => 'A distinct About narrative.',
                'work_description' => 'Builds <safe> data products.',
                'location' => '<Amman>',
                'email' => 'private@example.test',
                'phone_primary' => '+962 00 000 0000',
                'linkedin_url' => 'https://linkedin.example.test/footer-owner',
                'github_url' => 'https://github.example.test/footer-owner',
                'website_url' => 'https://website.example.test/footer-owner',
            ],
            [['skill_name' => 'PHP']],
            [['id' => 1, 'title' => 'Project', 'category' => 'Data', 'description' => 'Description', 'github_url' => '', 'image_path' => null]],
            ['preview' => true, 'experiences' => [[
                'experience_type' => 'employment', 'role_title' => 'Analyst', 'organization' => 'Organization', 'location' => null,
                'start_month' => '2025-01', 'end_month' => null, 'is_current' => true, 'description' => null,
            ]]],
        );
        $previewRendered = ob_get_clean();
        $previewFooter = is_string($previewRendered) && preg_match('/<footer class="portfolio-footer">.*?<\/footer>/s', $previewRendered, $previewFooterMatch) === 1 ? $previewFooterMatch[0] : '';
        phase2Assert($previewFooter === $footer, 'Private Preview and Public Portfolio must render the same shared Footer structure.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
