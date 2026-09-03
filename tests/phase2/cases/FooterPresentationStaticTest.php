<?php

declare(strict_types=1);

final class FooterPresentationStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $presentation = self::read('includes/portfolio_presentation.php');
        $stylesheet = self::read('portfolio.css');

        foreach ([
            '<footer class="portfolio-footer">',
            'portfolio-footer-main',
            'portfolio-footer-identity',
            'portfolio-footer-quick-links-heading',
            'portfolio-footer-resources-heading',
            'portfolio-footer-connect-heading',
            'portfolio-footer-updates-heading',
            'portfolio-footer-bottom',
        ] as $required) {
            phase2Assert(str_contains($presentation, $required), "Footer presentation is missing {$required}.");
        }
        phase2Assert(str_contains($stylesheet, 'grid-template-columns: minmax(260px, 1.75fr)') && str_contains($stylesheet, '.portfolio-footer-bottom') && str_contains($stylesheet, '.portfolio-footer-subscribe'), 'Footer styling must provide a content-driven desktop grid, bottom bar, and disabled update shell.');

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
        phase2Assert(str_contains($footer, '&lt;Footer Owner&gt;') && str_contains($footer, 'Data &lt;Leader&gt;') && str_contains($footer, 'Builds &lt;safe&gt; data products.') && str_contains($footer, '&lt;Amman&gt;'), 'Footer identity, summary, or location must use escaped public profile data.');
        foreach (['#top', '#about', '#projects', '#experience', '#skills', '#contact'] as $target) {
            phase2Assert(str_contains($footer, 'href="' . $target . '"'), "Footer Quick Links are missing the real {$target} target.");
        }
        phase2Assert(!str_contains($footer, 'href="#insights"') && !str_contains($footer, 'href="#resume"') && !str_contains($footer, 'href="#certifications"') && !str_contains($footer, 'href="#case-studies"') && !str_contains($footer, 'href="#"'), 'Unavailable Footer resources must not use fake anchors.');
        foreach (['Resume', 'Certifications', 'Insights', 'Case Studies'] as $resource) {
            phase2Assert(str_contains($footer, '<span aria-disabled="true">' . $resource . '</span>'), "Unavailable Footer resource {$resource} must remain non-focusable and disabled.");
        }
        phase2Assert(str_contains($footer, 'href="https://linkedin.example.test/footer-owner"') && str_contains($footer, 'href="https://github.example.test/footer-owner"') && str_contains($footer, 'href="https://website.example.test/footer-owner"') && !str_contains($footer, 'javascript:alert'), 'Footer social destinations must render only validated public URLs.');
        phase2Assert(str_contains($footer, 'aria-label="LinkedIn profile for &lt;Footer Owner&gt;"') && str_contains($footer, 'aria-label="GitHub profile for &lt;Footer Owner&gt;"') && str_contains($footer, 'aria-label="Website profile for &lt;Footer Owner&gt;"'), 'Footer icon-only social actions need escaped accessible names.');
        phase2Assert(!str_contains($footer, 'private@example.test') && !str_contains($footer, '+962 00 000 0000'), 'Footer must not expose profile email or phone without a public field contract.');
        phase2Assert(str_contains($footer, 'portfolio-footer-subscribe') && str_contains($footer, 'type="email"') && str_contains($footer, 'aria-describedby="portfolio-footer-subscribe-status" disabled') && str_contains($footer, '<button type="button" aria-describedby="portfolio-footer-subscribe-status" disabled>Subscribe</button>') && !str_contains($footer, '<form'), 'Missing subscription capability must remain a disabled, described, non-submitting visual shell.');
        phase2Assert(str_contains($footer, '&copy; ' . date('Y') . ' &lt;Footer Owner&gt;. All rights reserved.'), 'Footer copyright must use the current year and escaped profile identity.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Minimal Owner', 'website_url' => 'javascript:alert(1)'], [], [], ['experiences' => []]);
        $minimalRendered = ob_get_clean();
        $minimalFooter = is_string($minimalRendered) && preg_match('/<footer class="portfolio-footer">.*?<\/footer>/s', $minimalRendered, $minimalMatch) === 1 ? $minimalMatch[0] : '';
        phase2Assert($minimalFooter !== '' && !str_contains($minimalFooter, 'portfolio-footer-social') && !str_contains($minimalFooter, 'href="#about"') && !str_contains($minimalFooter, 'href="#projects"') && !str_contains($minimalFooter, 'href="#experience"') && !str_contains($minimalFooter, 'href="#skills"'), 'Footer must omit unavailable socials and conditional section links cleanly.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
