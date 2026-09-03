<?php

declare(strict_types=1);

final class PortfolioPresentationStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $public = self::read('public_portfolio.php');
        $preview = self::read('owner_preview.php');
        $presentation = self::read('includes/portfolio_presentation.php');
        $lifecycle = self::read('includes/public_lifecycle.php');
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
        phase2Assert(str_contains($presentation, 'portfolio-hero-badge') && str_contains($presentation, 'Turning Data into <span>Intelligence</span>') && str_contains($presentation, 'portfolio-hero-reserved') && str_contains($presentation, 'portfolio-hero-profile-card') && !str_contains($presentation, 'PROFILE / 01') && !str_contains($presentation, 'portfolio-profile-orbit') && substr_count($presentation, '<h1 ') === 1, 'S03 Hero foundation is missing its truthful badge, value headline, profile card, or single H1.');
        phase2Assert(str_contains($presentation, '<section class="portfolio-metrics"') && str_contains($presentation, 'portfolioPresentationMetrics(') && !str_contains($presentation, 'portfolio-profile-card') && !str_contains($presentation, 'portfolio-hero-wave') && !str_contains($presentation, 'portfolio-hero-grid-lines') && !str_contains($presentation, 'href="/download'), 'S04 must render only the truthful metrics strip and avoid legacy Hero visual shells or fake resume destinations.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationFeaturedProjects(') && str_contains($presentation, 'array_slice($projects, 0, 3)') && str_contains($presentation, 'portfolioPresentationProjectFallbackIcon(') && str_contains($presentation, 'loading="lazy"') && !str_contains($presentation, 'profileInitials($projectTitle)'), 'S05 featured Projects must use the existing deterministic order, cap the presentation at three cards, and provide a non-initial fallback.');
        phase2Assert(str_contains($lifecycle, 'ORDER BY created_at ASC, id ASC'), 'S05 featured Projects must derive their capped row from the existing deterministic public Project order.');
        phase2Assert(str_contains($presentation, 'portfolio-hero-actions-cluster') && str_contains($presentation, 'portfolio-hero-social-list') && str_contains($presentation, 'portfolioPresentationSocialIcon(') && str_contains($presentation, 'portfolioPresentationHeroSocialActions(') && str_contains($presentation, "['LinkedIn', 'GitHub']"), 'S02C Hero social actions are missing their explicit cluster or data-driven priority order.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationActionIcon(') && str_contains($presentation, '<span>View My Work</span>') && str_contains($presentation, '<span>Download Resume</span>') && str_contains($presentation, 'type="button" disabled aria-label="Download Resume (unavailable)"') && !str_contains($presentation, 'href="/download'), 'S02B Hero CTAs do not preserve the real Projects action and truthful disabled Resume placeholder.');
        phase2Assert(str_contains($presentation, 'Private preview') && str_contains($presentation, 'The contact form is inactive in private preview.'), 'Private preview distinction is incomplete.');
        foreach (['Years Experience', 'Certifications', 'Happy Clients', 'Industry Awards', 'Testimonials', 'Trusted By', 'Resume download', 'Newsletter', 'Experience timeline'] as $unsupportedClaim) {
            phase2Assert(!str_contains($presentation, $unsupportedClaim), "Unsupported Portfolio claim was introduced: {$unsupportedClaim}");
        }

        phase2Assert(str_contains($stylesheet, '--pf-container: 1260px') && str_contains($stylesheet, '.portfolio-hero-grid') && str_contains($stylesheet, 'grid-template-columns: repeat(2, minmax(0, 1fr))') && str_contains($stylesheet, '.portfolio-hero-reserved'), 'S02 Hero master geometry or reserved right column is incomplete.');
        phase2Assert(str_contains($presentation, "portfolio-hero-profile-card--<?php echo \$profileMediaUrl !== '' ? 'image' : 'fallback'; ?>") && str_contains($stylesheet, '.portfolio-hero-profile-card--image') && str_contains($stylesheet, '.portfolio-hero-profile-card--fallback') && str_contains($stylesheet, 'aspect-ratio:') && str_contains($stylesheet, '.portfolio-hero-profile-visual img { width: 100%; height: 100%; object-fit: cover;'), 'S03A Hero profile card must adapt its visual region to the available image data.');
        phase2Assert(str_contains($stylesheet, '.portfolio-hero-actions-cluster { display: flex; flex-direction: column; align-items: flex-start; gap: 18px') && str_contains($stylesheet, '.portfolio-button-primary { width: 194px') && str_contains($stylesheet, '.portfolio-button-secondary { width: 214px') && str_contains($stylesheet, 'height: 50px') && str_contains($stylesheet, 'width: 42px; height: 42px') && str_contains($stylesheet, '.portfolio-hero-social-list { display: flex; flex-wrap: wrap; gap: 10px; margin: 0'), 'S02C Hero CTA or explicit social-cluster gap contract is incomplete.');
        phase2Assert(str_contains($stylesheet, '.portfolio-metrics-strip { display: grid') && str_contains($stylesheet, 'repeat(var(--portfolio-metric-count), minmax(0, 1fr))') && str_contains($stylesheet, '.portfolio-metric + .portfolio-metric { border-inline-start'), 'S04 metrics strip does not use an adaptive shared shell with subtle dividers.');
        phase2Assert(str_contains($stylesheet, '.portfolio-project-card--image .portfolio-project-visual { aspect-ratio: 16 / 9; }') && str_contains($stylesheet, '.portfolio-project-card--fallback .portfolio-project-visual { gap: 9px; padding: 18px; }') && str_contains($stylesheet, '.portfolio-project-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: start;') && str_contains($stylesheet, '.portfolio-projects .portfolio-section-heading h2 { font-size: clamp(1.65rem, 2.4vw, 2.1rem); }') && str_contains($stylesheet, '.portfolio-project-visual img { width: 100%; height: 100%; object-fit: cover;') && str_contains($stylesheet, '.portfolio-project-fallback') && str_contains($stylesheet, '-webkit-line-clamp: 3') && str_contains($stylesheet, '.portfolio-project-technologies { display: flex; flex-wrap: wrap;'), 'S05E Project cards must retain 16:9 real-image media while using an intrinsic fallback visual, natural mixed-state rows, a subordinate section heading, and compact truthful content.');
        phase2Assert(str_contains($presentation, '<aside class="portfolio-skills-panel" id="skills" aria-labelledby="skills-title">') && str_contains($presentation, '<ul class="portfolio-skills-list" aria-labelledby="skills-title">') && str_contains($presentation, '<button class="portfolio-skill-control" type="button" aria-pressed="false">') && !preg_match('/skill_(?:category|group|level|proficiency|years)|portfolio-skill-(?:category|group|level|proficiency|years)/i', $presentation), 'S06 Skills must use escaped native toggle buttons in one truthful ungrouped collection without unsupported metadata.');
        phase2Assert(str_contains($stylesheet, '.portfolio-work-grid--split { grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr); }') && str_contains($stylesheet, '.portfolio-work-grid--split .portfolio-project-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }') && str_contains($stylesheet, '.portfolio-skills-panel') && str_contains($stylesheet, '.portfolio-skills-list { display: flex; flex-wrap: wrap;') && str_contains($stylesheet, '.portfolio-skill-control[aria-pressed="true"]') && str_contains($stylesheet, '.portfolio-skill-control:focus-visible'), 'S06 Projects and Skills composition, content-driven controls, selected state, or keyboard focus styling is incomplete.');
        phase2Assert(str_contains($script, 'initializePortfolioSkills') && str_contains($script, "button.getAttribute('aria-pressed') === 'true'") && str_contains($script, "selected.setAttribute('aria-pressed', 'false')") && str_contains($script, "button.setAttribute('aria-pressed', 'true')") && !preg_match('/localStorage|sessionStorage|fetch\(|XMLHttpRequest|document\.cookie/i', $script), 'S06 single-selection toggle behavior is incomplete or introduced persistence/network behavior.');
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
                'location' => '<Amman>',
                'email' => 'owner@example.test',
                'linkedin_url' => 'https://linkedin.example.test/profile',
                'github_url' => 'https://example.test/profile',
                'website_url' => 'https://website.example.test/profile',
            ],
            [
                ['skill_name' => '<PHP>'],
                ['skill_name' => 'SQL'],
                ['skill_name' => '"><script>alert("skill")</script>'],
            ],
            [
                ['id' => 7, 'title' => '<Project>', 'category' => 'Data Science', 'description' => 'Description', 'github_url' => 'https://example.test/project', 'image_path' => null, 'technologies' => ['Python', 'Pandas', 'Scikit-learn', '<script>alert(1)</script>', 'XGBoost']],
                ['id' => 8, 'title' => 'Image Project', 'category' => 'Machine Learning', 'description' => 'Image description', 'github_url' => 'https://example.test/image-project', 'image_path' => 'portfolios/7/projects/project_abc123.jpg'],
            ],
            [
                'profile_media_url' => '/p/safe/media/profile',
                'project_media_url' => static fn (int $projectId): string => "/media/{$projectId}",
                'contact_action' => '/p/safe/contact',
            ],
        );
        $rendered = ob_get_clean();
        phase2Assert(is_string($rendered) && str_contains($rendered, '&lt;Owner&gt;') && str_contains($rendered, '&lt;PHP&gt;') && str_contains($rendered, '&lt;Project&gt;'), 'Shared Portfolio presentation does not encode user content.');
        phase2Assert(str_contains($rendered, 'portfolio-hero-profile-card--image') && str_contains($rendered, 'src="/p/safe/media/profile"') && str_contains($rendered, 'alt="&lt;Owner&gt; portrait"') && str_contains($rendered, '&lt;Amman&gt;') && !str_contains($rendered, 'Years Experience'), 'S03 Hero profile card does not render escaped real data or introduced fabricated experience.');
        phase2Assert(str_contains($rendered, 'portfolio-metrics-strip') && str_contains($rendered, '>3</strong>') && str_contains($rendered, 'Projects Featured') && str_contains($rendered, 'Core Skills') && str_contains($rendered, 'Project Categories') && !str_contains($rendered, '25+') && !str_contains($rendered, 'Happy Clients') && !str_contains($rendered, 'Industry Awards'), 'S04 metrics must use only truthful rendered Portfolio counts.');
        phase2Assert(!str_contains($rendered, 'javascript:alert') && str_contains($rendered, 'portfolio-project-card--fallback') && str_contains($rendered, 'portfolio-project-card--image') && !str_contains($rendered, 'portfolio-project-fallback-category') && substr_count($rendered, 'Data Science') === 1 && str_contains($rendered, 'action="/p/safe/contact"') && str_contains($rendered, 'portfolio-project-fallback') && str_contains($rendered, 'src="/media/8"') && str_contains($rendered, 'alt="Image Project project preview" loading="lazy"') && str_contains($rendered, 'View on GitHub') && str_contains($rendered, 'aria-label="View &lt;Project&gt; on GitHub"'), 'S05 shared presentation accepted an unsafe URL, lost explicit image/fallback card states, rendered duplicate fallback metadata, the scoped contact action, real cover, or safe GitHub action.');
        phase2Assert(str_contains($rendered, 'portfolio-project-technologies') && str_contains($rendered, 'Python') && str_contains($rendered, 'Pandas') && str_contains($rendered, 'Scikit-learn') && str_contains($rendered, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($rendered, 'XGBoost') && substr_count($rendered, 'portfolio-project-technologies') === 1, 'S05C Project card technologies must render only the first four escaped stored labels and omit absent rows.');
        $skillsPanel = preg_match('/<aside class="portfolio-skills-panel".*?<\/aside>/s', $rendered, $skillsMatch) === 1 ? $skillsMatch[0] : '';
        phase2Assert($skillsPanel !== '' && substr_count($skillsPanel, 'class="portfolio-skill-control" type="button" aria-pressed="false"') === 3 && strpos($skillsPanel, '&lt;PHP&gt;') < strpos($skillsPanel, '>SQL</button>') && str_contains($skillsPanel, '&quot;&gt;&lt;script&gt;alert(&quot;skill&quot;)&lt;/script&gt;') && !str_contains($skillsPanel, 'Python') && !str_contains($skillsPanel, 'Pandas'), 'S06 Skills must preserve stored order, escape labels, initialize unselected, and remain separate from Project technologies.');
        ob_start();
        renderPortfolioPresentation(['full_name' => 'Projects Only'], [], [['id' => 9, 'title' => 'Only Project', 'category' => 'Data', 'description' => 'Description', 'github_url' => '', 'image_path' => null]]);
        $projectsOnly = ob_get_clean();
        phase2Assert(is_string($projectsOnly) && str_contains($projectsOnly, 'portfolio-work-grid--single') && str_contains($projectsOnly, 'portfolio-projects') && !str_contains($projectsOnly, 'portfolio-skills-panel'), 'S06 must omit an empty Skills panel while preserving a full-width Projects composition.');
        phase2Assert(
            str_contains($rendered, 'href="#projects"')
            && str_contains($rendered, 'href="mailto:owner@example.test"')
            && str_contains($rendered, 'aria-label="Email &lt;Owner&gt;"')
            && !str_contains($rendered, 'href="/download')
            && !str_contains($rendered, 'href="#"')
            && str_contains($rendered, '<button class="portfolio-button portfolio-button-secondary" type="button" disabled aria-label="Download Resume (unavailable)">')
            && strpos($rendered, 'aria-label="LinkedIn"') < strpos($rendered, 'aria-label="GitHub"')
            && strpos($rendered, 'aria-label="GitHub"') < strpos($rendered, 'aria-label="Email &lt;Owner&gt;"')
            && strpos($rendered, 'aria-label="Email &lt;Owner&gt;"') < strpos($rendered, 'aria-label="Website"'),
            'S02A Hero actions must retain the real Projects target, validate email, avoid fabricated resumes, and order safe icon controls correctly.'
        );

        phase2AssertSame([], portfolioPresentationHeroSocialActions(['website_url' => 'javascript:alert(1)'], ''), 'S02A Hero actions must reject unsafe URLs and omit unavailable actions.');
        phase2AssertSame([
            ['key' => 'projects', 'value' => 3, 'label' => 'Projects Featured'],
            ['key' => 'skills', 'value' => 2, 'label' => 'Core Skills'],
            ['key' => 'categories', 'value' => 2, 'label' => 'Project Categories'],
        ], portfolioPresentationMetrics([
            ['category' => 'Data'],
            ['category' => ' data '],
            ['category' => 'Analytics'],
        ], [
            ['skill_name' => 'PHP'],
            ['skill_name' => ''],
            ['skill_name' => ' SQL '],
        ]), 'S04 metrics must count only actual projects, non-empty skills, and distinct project categories.');
        phase2AssertSame([11, 12, 13], array_column(portfolioPresentationFeaturedProjects([
            ['id' => 11],
            ['id' => 12],
            ['id' => 13],
            ['id' => 14],
        ]), 'id'), 'S05 featured Projects must preserve the public display order and cap the row at three entries.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Preview Owner'], [], [], ['preview' => true]);
        $previewRendered = ob_get_clean();
        phase2Assert(is_string($previewRendered) && str_contains($previewRendered, 'Private preview') && str_contains($previewRendered, 'disabled aria-disabled="true"') && str_contains($previewRendered, 'portfolio-hero-profile-card--fallback') && str_contains($previewRendered, 'portfolio-hero-profile-fallback') && !str_contains($previewRendered, 'portfolio-hero-profile-location'), 'S03A fallback profile visual or optional location handling is incomplete.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
