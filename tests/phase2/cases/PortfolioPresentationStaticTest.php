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
        $brandAsset = PHASE2_REPOSITORY_ROOT . '/assets/images/ather-navbar-logo.png';

        phase2Assert(str_contains($public, 'resolvePublicReadContext') && str_contains($public, 'renderPortfolioPresentation('), 'Public Portfolio does not resolve publication authority before the shared presentation.');
        phase2Assert(str_contains($preview, 'requireOwnerPortfolioContext') && str_contains($preview, 'renderPortfolioPresentation(') && !str_contains($preview, 'ownerLayoutStart'), 'Private preview does not use owner authority and the shared standalone presentation.');
        phase2Assert(str_contains($presentation, '$preview = ($options[\'preview\'] ?? false) === true;'), 'Shared presentation preview mode must be caller-controlled.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationEscape(') && str_contains($presentation, 'isPublicWebsiteDestination('), 'Portfolio content or external links lack presentation safety checks.');
        phase2Assert(str_contains($presentation, '<header class="portfolio-header">') && str_contains($presentation, '<div class="portfolio-header-inner">') && str_contains($presentation, 'aria-label="ATHER, home"') && str_contains($presentation, 'src="/assets/images/ather-navbar-logo.png"') && str_contains($presentation, 'data-portfolio-section="top" aria-current="page">Home</a>') && str_contains($presentation, 'href="#about" data-portfolio-section="about">About</a>') && str_contains($presentation, 'href="#projects" data-portfolio-section="projects">Projects</a>') && str_contains($presentation, 'href="#experience" data-portfolio-section="experience">Experience</a>') && str_contains($presentation, 'href="#skills" data-portfolio-section="skills">Skills</a>') && str_contains($presentation, 'href="#contact" data-portfolio-section="contact">Contact</a>') && str_contains($presentation, 'portfolio-nav-placeholder" aria-disabled="true" title="Coming soon">Insights</span>') && str_contains($presentation, 'portfolio-header-cta') && str_contains($presentation, '<svg viewBox="0 0 24 24"'), 'S13 Header platform brand, navigation, disabled Insights entry, or contact CTA is incomplete.');
        phase2Assert(is_file($brandAsset) && filesize($brandAsset) > 0 && str_contains($dockerfile, '/assets/images/ather-navbar-logo.png'), 'Approved compact ATHER asset is missing or is not published by the production image.');
        phase2Assert(!str_contains($presentation, 'href="#insights"') && !str_contains($presentation, 'portfolio-nav-placeholder" aria-disabled="true" title="Coming soon">Experience</span>'), 'S01 must not retain disabled Experience navigation or create a false Insights target.');
        phase2Assert(strpos($presentation, '<header class="portfolio-header">') < strpos($presentation, '<main id="portfolio-main">'), 'S01 header must be the first Portfolio content element.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationHeroHeadline(') && str_contains($presentation, 'portfolio-hero-reserved') && str_contains($presentation, 'portfolio-hero-profile-card') && !str_contains($presentation, 'portfolio-hero-badge') && !str_contains($presentation, 'portfolio-hero-profile-role') && !str_contains($presentation, 'Turning Data into') && !str_contains($presentation, 'PROFILE / 01') && !str_contains($presentation, 'portfolio-profile-orbit') && substr_count($presentation, '<h1 ') === 1, 'S13 Hero must use the owner headline and a non-redundant Personal ID card without a title badge.');
        phase2Assert(str_contains($presentation, '<section class="portfolio-metrics"') && str_contains($presentation, 'portfolioPresentationMetrics(') && str_contains($presentation, 'Professional Overview') && str_contains($presentation, 'portfolio-hero-profile-location') && !str_contains($presentation, 'portfolio-about-location') && !str_contains($presentation, 'Based in ') && !str_contains($presentation, 'Projects Featured') && !str_contains($presentation, 'Project Categories') && !str_contains($presentation, 'ABOUT / 02') && !str_contains($presentation, 'A closer look at my work.') && !str_contains($presentation, 'portfolio-profile-card') && !str_contains($presentation, 'portfolio-hero-wave') && !str_contains($presentation, 'portfolio-hero-grid-lines') && !str_contains($presentation, 'href="/download'), 'S04 must render the truthful professional overview without duplicate location metadata, legacy metric labels, template copy, Hero visual shells, or fake resume destinations.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationFeaturedProjects(') && str_contains($presentation, 'array_slice($projects, 0, 3)') && str_contains($presentation, 'portfolioPresentationProjectFallbackIcon(') && str_contains($presentation, 'loading="lazy"') && !str_contains($presentation, 'profileInitials($projectTitle)'), 'S05 featured Projects must use the existing deterministic order, cap the presentation at three cards, and provide a non-initial fallback.');
        phase2Assert(str_contains($lifecycle, 'ORDER BY created_at ASC, id ASC'), 'S05 featured Projects must derive their capped row from the existing deterministic public Project order.');
        phase2Assert(str_contains($presentation, 'portfolio-hero-actions-cluster') && str_contains($presentation, 'portfolio-hero-social-list') && str_contains($presentation, 'portfolioPresentationSocialIcon(') && str_contains($presentation, 'portfolioPresentationHeroSocialActions(') && str_contains($presentation, "['LinkedIn', 'GitHub']"), 'S02C Hero social actions are missing their explicit cluster or data-driven priority order.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationActionIcon(') && str_contains($presentation, '<span>View My Work</span>') && str_contains($presentation, '<span>Download Resume</span>') && str_contains($presentation, 'type="button" disabled aria-label="Download Resume (unavailable)"') && !str_contains($presentation, 'href="/download'), 'S02B Hero CTAs do not preserve the real Projects action and truthful disabled Resume placeholder.');
        phase2Assert(str_contains($presentation, 'Private preview') && str_contains($presentation, 'The contact form is inactive in private preview.'), 'Private preview distinction is incomplete.');
        foreach (['Years Experience', 'Happy Clients', 'Industry Awards', 'Testimonials', 'Trusted By', 'Resume download', 'Newsletter', 'Experience timeline'] as $unsupportedClaim) {
            phase2Assert(!str_contains($presentation, $unsupportedClaim), "Unsupported Portfolio claim was introduced: {$unsupportedClaim}");
        }

        phase2Assert(str_contains($stylesheet, '--pf-container: 1260px') && str_contains($stylesheet, '.portfolio-hero-grid') && str_contains($stylesheet, 'grid-template-columns: repeat(2, minmax(0, 1fr))') && str_contains($stylesheet, '.portfolio-hero-reserved'), 'S02 Hero master geometry or reserved right column is incomplete.');
        phase2Assert(str_contains($presentation, "portfolio-hero-profile-card--<?php echo \$profileMediaUrl !== '' ? 'image' : 'fallback'; ?>") && str_contains($stylesheet, '.portfolio-hero-profile-card--image') && str_contains($stylesheet, '.portfolio-hero-profile-card--fallback') && str_contains($stylesheet, 'aspect-ratio:') && str_contains($stylesheet, '.portfolio-hero-profile-visual img { width: 100%; height: 100%; object-fit: cover;'), 'S03A Hero profile card must adapt its visual region to the available image data.');
        phase2Assert(str_contains($stylesheet, '.portfolio-hero-actions-cluster { display: flex; flex-direction: column; align-items: flex-start; gap: 16px') && str_contains($stylesheet, '.portfolio-button-primary { width: 194px') && str_contains($stylesheet, '.portfolio-button-secondary { width: 214px') && str_contains($stylesheet, 'height: 50px') && str_contains($stylesheet, 'width: 42px; height: 42px') && str_contains($stylesheet, '.portfolio-hero-social-list { display: flex; flex-wrap: wrap; gap: 10px; margin: 0; padding: 2px'), 'S13 Hero CTA or intentional accessible social-cluster contract is incomplete.');
        phase2Assert(str_contains($presentation, 'portfolio-closing-layout') && str_contains($stylesheet, '.portfolio-closing-layout { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: stretch;') && str_contains($stylesheet, '.portfolio-closing-layout--contact-only') && str_contains($stylesheet, '.portfolio-closing-panel { display: flex;') && str_contains($stylesheet, 'height: 100%') && str_contains($stylesheet, '@media (max-width: 1100px)') && str_contains($stylesheet, '.portfolio-closing-layout { grid-template-columns: 1fr; align-items: start; }.portfolio-closing-panel { height: auto; }') && !str_contains($stylesheet, '.portfolio-contact-shell'), 'S13 Experience and Contact must use equal-height desktop panels that stack naturally without retaining the legacy contact shell.');
        phase2Assert(str_contains($stylesheet, '.portfolio-metrics-strip { display: grid') && str_contains($stylesheet, 'repeat(var(--portfolio-metric-count), minmax(0, 1fr))') && str_contains($stylesheet, '--portfolio-metric-compact-count') && str_contains($stylesheet, '.portfolio-metric-icon { display: grid; width: 34px') && str_contains($stylesheet, 'grid-template-columns: minmax(220px, 35fr) minmax(0, 65fr)') && str_contains($stylesheet, '.portfolio-hero-profile-location') && !str_contains($stylesheet, '.portfolio-about-location'), 'S04 metrics and About must retain an adaptive, compact Professional Overview composition without duplicate location styling.');
        phase2Assert(str_contains($stylesheet, '.portfolio-project-card--image .portfolio-project-visual { aspect-ratio: 16 / 9; }') && str_contains($stylesheet, '.portfolio-project-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: stretch;') && str_contains($stylesheet, '.portfolio-project-identity') && str_contains($stylesheet, '.portfolio-project-visual img { width: 100%; height: 100%; object-fit: cover;') && str_contains($stylesheet, '.portfolio-project-fallback { display: grid; width: 32px') && !str_contains($stylesheet, '.portfolio-project-card--fallback .portfolio-project-visual') && !str_contains($stylesheet, '-webkit-line-clamp: 3') && str_contains($stylesheet, '.portfolio-project-technologies { display: flex; flex-wrap: wrap;'), 'S05E Project cards must retain controlled real-image media, compact in-body fallback identity, full truthful descriptions, and safe technology wrapping.');
        phase2Assert(str_contains($presentation, '<section class="portfolio-skills-region" id="skills" aria-labelledby="portfolio-skills-heading">') && str_contains($presentation, '<header class="portfolio-section-heading portfolio-skills-heading"><h2 id="portfolio-skills-heading">Skills &amp; Technologies</h2></header>') && str_contains($presentation, '<div class="portfolio-skills-panel">') && str_contains($presentation, '<ul class="portfolio-skills-list">') && str_contains($presentation, '<button class="portfolio-skill-control" type="button" aria-pressed="false">') && !str_contains($presentation, 'portfolio-skill-tag') && !preg_match('/skill_(?:category|group|level|proficiency|years)|portfolio-skill-(?:category|group|level|proficiency|years)/i', $presentation), 'S06B Skills must use accessible buttons with an explicit initial pressed state.');
        phase2Assert(str_contains($presentation, 'portfolio-work-content') && str_contains($stylesheet, '.portfolio-work-content { display: grid; gap: clamp(46px, 5vw, 64px);') && !str_contains($stylesheet, 'portfolio-work-grid') && str_contains($stylesheet, '@media (max-width: 1100px)') && str_contains($stylesheet, '.portfolio-project-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }') && str_contains($stylesheet, '.portfolio-projects .portfolio-section-heading h2, .portfolio-skills-heading h2') && str_contains($stylesheet, '.portfolio-skills-panel { width: 100%;') && str_contains($stylesheet, '.portfolio-skills-list { display: flex; flex-wrap: wrap;') && str_contains($stylesheet, '.portfolio-skill-control') && str_contains($stylesheet, '.portfolio-skill-control[aria-pressed="true"]') && str_contains($stylesheet, '.portfolio-skill-control:focus-visible'), 'S06B Projects and Skills must form a vertical, full-width presentation with responsive Projects and accessible wrapping Skill controls.');
        phase2Assert(str_contains($script, 'initializePortfolioSkills') && str_contains($script, "button.getAttribute('aria-pressed') === 'true'") && str_contains($script, 'document.querySelectorAll(\'.portfolio-skills-list\')') && str_contains($script, "selected.setAttribute('aria-pressed', 'false')") && str_contains($script, "button.setAttribute('aria-pressed', 'true')") && !preg_match('/localStorage|sessionStorage|fetch\(|XMLHttpRequest|document\.cookie/i', $script), 'S06 skill behavior must use scoped, non-persistent delegated selection.');
        phase2Assert(str_contains($presentation, "View on GitHub <?php echo portfolioPresentationSocialIcon('GitHub'); ?>") && !str_contains($presentation, 'View on GitHub <span aria-hidden="true">↗</span>') && str_contains($stylesheet, '.portfolio-project-link svg { width: 15px'), 'S05 GitHub actions must use the existing decorative GitHub SVG instead of the project-link arrow.');
        phase2Assert(str_contains($stylesheet, '.portfolio-header-inner') && str_contains($stylesheet, 'grid-template-columns: minmax(200px, 230px) minmax(0, 1fr) minmax(152px, 160px)') && str_contains($stylesheet, 'justify-self: end') && str_contains($stylesheet, 'min-height: 66px') && str_contains($stylesheet, 'position: sticky') && str_contains($stylesheet, 'portfolio-header-cta') && str_contains($stylesheet, 'white-space: nowrap') && str_contains($stylesheet, 'a.is-current::after'), 'S01 premium sticky header, bounded shared geometry, CTA, or active underline is incomplete.');
        phase2Assert(str_contains($script, 'IntersectionObserver') && str_contains($script, 'data-portfolio-section') && str_contains($script, 'aria-current') && str_contains($script, 'const initialId = window.location.hash.slice(1)') && str_contains($script, "hashId === 'experience' || hashId === 'contact'") && str_contains($script, 'Math.abs(hashBounds.top - siblingBounds.top) < 2') && !str_contains($script, 'insights'), 'S01 scrollspy must honor direct Experience/Contact anchors while their desktop panels share a row.');
        phase2Assert(str_contains($presentation, 'class="portfolio-menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="portfolio-mobile-nav"') && str_contains($presentation, 'class="portfolio-mobile-nav-layer" aria-hidden="true" inert') && str_contains($presentation, '<nav class="portfolio-mobile-nav-links" id="portfolio-mobile-nav"') && str_contains($presentation, 'class="portfolio-mobile-nav-close" type="button" aria-label="Close navigation"'), 'S11D narrow navigation must provide a real controlled menu button and an initially inert sidebar.');
        phase2Assert(str_contains($presentation, 'class="portfolio-mobile-nav-placeholder" aria-disabled="true">Insights') && !str_contains($presentation, 'href="#insights"'), 'S11D mobile Insights must remain disabled without a false target.');
        $mobileNavigation = strstr($presentation, '<div class="portfolio-mobile-nav-layer"');
        phase2Assert(is_string($mobileNavigation) && str_contains($mobileNavigation, 'href="#top" data-portfolio-section="top"') && str_contains($mobileNavigation, 'href="#experience" data-portfolio-section="experience">Experience</a>') && str_contains($mobileNavigation, 'href="#contact" data-portfolio-section="contact">Contact</a>') && !str_contains($mobileNavigation, 'portfolio-header-cta'), 'S11D mobile navigation must retain real section targets without duplicating the Header contact CTA.');
        phase2Assert(str_contains($stylesheet, '.portfolio-nav-links { display: none; }') && str_contains($stylesheet, '.portfolio-mobile-nav-layer') && str_contains($stylesheet, '.portfolio-mobile-nav-panel') && str_contains($stylesheet, '.portfolio-section, .portfolio-projects, .portfolio-skills-region, .portfolio-closing-panel { scroll-margin-top: 100px; }'), 'S11D must replace narrow horizontal navigation with an off-canvas sidebar while preserving the accepted narrow anchor offset.');
        phase2Assert(str_contains($script, 'initializePortfolioMobileNavigation') && str_contains($script, "event.key === 'Escape'") && str_contains($script, "toggle.setAttribute('aria-expanded', 'true')") && str_contains($script, "toggle.setAttribute('aria-expanded', 'false')") && str_contains($script, 'portfolio-mobile-nav-open') && str_contains($script, 'closeNavigation();'), 'S11D mobile navigation must reuse safe DOM state for open, close, Escape, link close, and scroll lock.');
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
                'hero_headline' => '<Build useful systems>',
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
        phase2Assert(is_string($rendered) && str_contains($rendered, '<h1 id="portfolio-title">&lt;Build useful systems&gt;</h1>') && str_contains($rendered, '&lt;Owner&gt;') && str_contains($rendered, '&lt;PHP&gt;') && str_contains($rendered, '&lt;Project&gt;'), 'Shared Portfolio presentation does not encode user-owned Hero or Portfolio content.');
        $renderedHeader = preg_match('/<header class="portfolio-header">.*?<\/header>/s', $rendered, $headerMatch) === 1 ? $headerMatch[0] : '';
        $renderedProfileCard = preg_match('/<article class="portfolio-hero-profile-card.*?<\/article>/s', $rendered, $profileCardMatch) === 1 ? $profileCardMatch[0] : '';
        phase2Assert($renderedHeader !== '' && str_contains($renderedHeader, 'aria-label="ATHER, home"') && !str_contains($renderedHeader, '&lt;Owner&gt;'), 'Header must identify the ATHER platform without repeating owner identity.');
        phase2Assert($renderedProfileCard !== '' && str_contains($renderedProfileCard, '&lt;Owner&gt;') && str_contains($renderedProfileCard, '&lt;Amman&gt;') && !str_contains($renderedProfileCard, '>Engineer<'), 'Hero Personal ID must retain owner name/location without repeating the professional title.');
        phase2Assert(str_contains($rendered, 'portfolio-hero-profile-card--image') && str_contains($rendered, 'src="/p/safe/media/profile"') && str_contains($rendered, 'alt="&lt;Owner&gt; portrait"') && str_contains($rendered, '&lt;Amman&gt;') && !str_contains($rendered, 'Years Experience'), 'S03 Hero profile card does not render escaped real data or introduced fabricated experience.');
        phase2Assert(str_contains($rendered, 'portfolio-metrics-strip') && str_contains($rendered, '>3</strong>') && str_contains($rendered, 'Projects') && str_contains($rendered, 'Core Skills') && str_contains($rendered, 'Focus Areas') && !str_contains($rendered, 'Projects Featured') && !str_contains($rendered, 'Project Categories') && !str_contains($rendered, '25+') && !str_contains($rendered, 'Happy Clients') && !str_contains($rendered, 'Industry Awards'), 'S04 metrics must use only truthful rendered Portfolio counts and professional labels.');
        $fallbackProjectCard = preg_match('/<article class="portfolio-project-card portfolio-project-card--fallback">.*?<\/article>/s', $rendered, $fallbackProjectCardMatch) === 1 ? $fallbackProjectCardMatch[0] : '';
        $imageProjectCard = preg_match('/<article class="portfolio-project-card portfolio-project-card--image">.*?<\/article>/s', $rendered, $imageProjectCardMatch) === 1 ? $imageProjectCardMatch[0] : '';
        $projectGithubLink = preg_match('/<a class="portfolio-project-link".*?<\/a>/s', $rendered, $projectGithubLinkMatch) === 1 ? $projectGithubLinkMatch[0] : '';
        phase2Assert(!str_contains($rendered, 'javascript:alert') && $fallbackProjectCard !== '' && $imageProjectCard !== '' && !str_contains($fallbackProjectCard, 'portfolio-project-visual') && str_contains($fallbackProjectCard, 'portfolio-project-identity') && str_contains($fallbackProjectCard, 'portfolio-project-fallback') && str_contains($imageProjectCard, 'src="/media/8"') && str_contains($imageProjectCard, 'alt="Image Project project preview" loading="lazy"') && !str_contains($rendered, 'portfolio-project-fallback-category') && substr_count($rendered, 'Data Science') === 1 && str_contains($rendered, 'action="/p/safe/contact#contact"') && str_contains($projectGithubLink, 'View on GitHub') && str_contains($projectGithubLink, '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">') && !str_contains($projectGithubLink, '↗') && str_contains($projectGithubLink, 'rel="noopener noreferrer"') && str_contains($projectGithubLink, 'aria-label="View &lt;Project&gt; on GitHub"'), 'S05 shared presentation must retain safe image/fallback card states, scoped contact action, real cover media, and the validated GitHub action without an empty fallback visual band.');
        phase2Assert(str_contains($rendered, 'portfolio-project-technologies') && str_contains($rendered, 'Python') && str_contains($rendered, 'Pandas') && str_contains($rendered, 'Scikit-learn') && str_contains($rendered, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($rendered, 'XGBoost') && substr_count($rendered, 'portfolio-project-technologies') === 1, 'S05C Project card technologies must render only the first four escaped stored labels and omit absent rows.');
        $skillsRegion = preg_match('/<section class="portfolio-skills-region".*?<\/section>/s', $rendered, $skillsMatch) === 1 ? $skillsMatch[0] : '';
        phase2Assert($skillsRegion !== '' && strpos($rendered, 'id="projects-title"') < strpos($rendered, 'id="portfolio-skills-heading"') && substr_count($skillsRegion, 'class="portfolio-skill-control" type="button" aria-pressed="false"') === 3 && strpos($skillsRegion, '&lt;PHP&gt;') < strpos($skillsRegion, '>SQL</button>') && str_contains($skillsRegion, '&quot;&gt;&lt;script&gt;alert(&quot;skill&quot;)&lt;/script&gt;') && !str_contains($skillsRegion, 'portfolio-skill-tag') && !str_contains($skillsRegion, 'Python') && !str_contains($skillsRegion, 'Pandas'), 'S06B Skills must follow Projects in a full-width supporting section while preserving stored order, escaped labels, and native button semantics.');
        ob_start();
        renderPortfolioPresentation(['full_name' => 'Projects Only'], [], [['id' => 9, 'title' => 'Only Project', 'category' => 'Data', 'description' => 'Description', 'github_url' => '', 'image_path' => null]]);
        $projectsOnly = ob_get_clean();
        phase2Assert(is_string($projectsOnly) && str_contains($projectsOnly, 'portfolio-work-content') && str_contains($projectsOnly, 'portfolio-projects') && !str_contains($projectsOnly, 'portfolio-skills-panel'), 'S06 must omit an empty Skills panel while preserving a full-width Projects composition.');
        ob_start();
        renderPortfolioPresentation(['full_name' => 'Skills Only'], [['skill_name' => 'PHP']], []);
        $skillsOnly = ob_get_clean();
        phase2Assert(is_string($skillsOnly) && str_contains($skillsOnly, 'portfolio-work-content') && !str_contains($skillsOnly, 'portfolio-projects') && str_contains($skillsOnly, 'portfolio-skills-region') && str_contains($skillsOnly, 'portfolio-skill-control'), 'S06 must render Skills independently without an empty Projects region.');
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
            ['key' => 'projects', 'value' => 3, 'label' => 'Projects'],
            ['key' => 'skills', 'value' => 2, 'label' => 'Core Skills'],
            ['key' => 'categories', 'value' => 2, 'label' => 'Focus Areas'],
        ], portfolioPresentationMetrics([
            ['category' => 'Data'],
            ['category' => ' data '],
            ['category' => 'Analytics'],
        ], [
            ['skill_name' => 'PHP'],
            ['skill_name' => ''],
            ['skill_name' => ' SQL '],
        ]), 'S04 metrics must count only actual projects, non-empty skills, and distinct project categories.');
        phase2AssertSame([
            ['key' => 'skills', 'value' => 1, 'label' => 'Core Skills'],
        ], portfolioPresentationMetrics([], [
            ['skill_name' => 'PHP'],
        ]), 'S04 metrics must render the available single metric without fabricated values.');
        phase2AssertSame([
            ['key' => 'projects', 'value' => 2, 'label' => 'Projects'],
            ['key' => 'categories', 'value' => 2, 'label' => 'Focus Areas'],
        ], portfolioPresentationMetrics([
            ['category' => 'Data'],
            ['category' => 'Design'],
        ], []), 'S04 metrics must render the available two metrics without an empty Skills metric.');
        phase2AssertSame([
            ['key' => 'projects', 'value' => 4, 'label' => 'Projects'],
            ['key' => 'skills', 'value' => 1, 'label' => 'Core Skills'],
            ['key' => 'categories', 'value' => 2, 'label' => 'Focus Areas'],
        ], portfolioPresentationMetrics([
            ['category' => 'Data'],
            ['category' => 'Design'],
            ['category' => 'Data'],
            ['category' => 'Data'],
        ], [
            ['skill_name' => 'PHP'],
        ]), 'S04 metrics must retain the truthful project count beyond the three displayed project cards.');
        phase2AssertSame([11, 12, 13], array_column(portfolioPresentationFeaturedProjects([
            ['id' => 11],
            ['id' => 12],
            ['id' => 13],
            ['id' => 14],
        ]), 'id'), 'S05 featured Projects must preserve the public display order and cap the row at three entries.');

        ob_start();
        renderPortfolioPresentation([
            'full_name' => 'Overview Owner',
            'about_me' => "First &lt;line&gt;\nSecond line",
            'location' => '<Amman>',
        ], [], [], ['preview' => true]);
        $overviewRendered = ob_get_clean();
        $overviewProfileCard = preg_match('/<article class="portfolio-hero-profile-card.*?<\/article>/s', $overviewRendered, $overviewProfileCardMatch) === 1 ? $overviewProfileCardMatch[0] : '';
        phase2Assert(is_string($overviewRendered) && $overviewProfileCard !== '' && str_contains($overviewRendered, 'Private preview') && str_contains($overviewRendered, '<h2 id="about-title">Professional Overview</h2>') && str_contains($overviewRendered, 'First &amp;lt;line&amp;gt;<br />') && str_contains($overviewRendered, 'Second line') && !str_contains($overviewRendered, 'portfolio-about-location') && !str_contains($overviewRendered, 'Based in ') && str_contains($overviewProfileCard, 'portfolio-hero-profile-location') && str_contains($overviewProfileCard, '&lt;Amman&gt;') && strpos($overviewRendered, 'Professional Overview') < strpos($overviewRendered, 'First &amp;lt;line&amp;gt;'), 'S04 About must preserve escaped line breaks and its controlled heading hierarchy while rendering dynamic location only in the Hero profile card.');
        ob_start();
        renderPortfolioPresentation(['full_name' => 'No Location', 'about_me' => 'Narrative', 'location' => ' '], [], []);
        $noLocationRendered = ob_get_clean();
        phase2Assert(is_string($noLocationRendered) && str_contains($noLocationRendered, 'Professional Overview') && !str_contains($noLocationRendered, 'portfolio-metrics') && !str_contains($noLocationRendered, 'portfolio-about-location') && !str_contains($noLocationRendered, 'Based in ') && !str_contains($noLocationRendered, 'Private preview'), 'S04 public rendering must omit empty metrics and duplicate location metadata while retaining the shared About section.');
        ob_start();
        renderPortfolioPresentation(['full_name' => 'No About', 'about_me' => " \n "], [], []);
        $noAboutRendered = ob_get_clean();
        phase2Assert(is_string($noAboutRendered) && !str_contains($noAboutRendered, '<section class="portfolio-section portfolio-about"'), 'S04 empty About content must omit the About section entirely.');

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
