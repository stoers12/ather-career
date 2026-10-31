<?php

declare(strict_types=1);

final class PortfolioPresentationStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $public = self::read('handlers/public_portfolio.php');
        $preview = self::read('handlers/owner_preview.php');
        $presentation = self::read('includes/portfolio_presentation.php');
        $scopedData = SplitSourceFile::read('includes/portfolio_scoped_data.php');
        $lifecycle = self::read('includes/public_lifecycle.php');
        $stylesheet = self::read('public/assets/css/portfolio.css');
        $script = self::read('public/assets/js/portfolio.js');
        $dockerfile = self::read('Dockerfile');
        $brandAsset = PHASE2_REPOSITORY_ROOT . '/public/assets/images/ather-navbar-logo.png';

        phase2Assert(str_contains($public, 'resolvePublicReadContext') && str_contains($public, 'renderPortfolioPresentation('), 'Public Portfolio does not resolve publication authority before the shared presentation.');
        phase2Assert(str_contains($preview, 'requireOwnerPortfolioContext') && str_contains($preview, 'renderPortfolioPresentation(') && !str_contains($preview, 'ownerLayoutStart'), 'Private preview does not use owner authority and the shared standalone presentation.');
        phase2Assert(str_contains($presentation, '$preview = ($options[\'preview\'] ?? false) === true;'), 'Shared presentation preview mode must be caller-controlled.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationEscape(') && str_contains($presentation, 'isPublicWebsiteDestination('), 'Portfolio content or external links lack presentation safety checks.');
        $copy = self::read('includes/portfolio_copy.php');
        $shared = self::read('includes/portfolio_shared_presentation.php');
        phase2Assert(str_contains($presentation, '<header class="portfolio-header">') && str_contains($presentation, '<div class="portfolio-header-inner">') && str_contains($presentation, "portfolioCopy('brandHome')") && str_contains($presentation, "renderPortfolioWordmark('portfolio-brand-logo')") && str_contains($shared, 'portfolio-wordmark') && str_contains($presentation, 'portfolio-header-cta'), 'Reference header must retain the ATHER identity and real contact CTA.');
        foreach (['top', 'about', 'projects', 'experience', 'skills', 'contact'] as $section) {
            phase2Assert(str_contains($presentation, 'data-portfolio-section="' . $section . '"'), "Implemented navigation target missing: {$section}");
        }
        phase2Assert(!str_contains($presentation, 'Insights') && !str_contains($presentation, 'Coming soon') && !str_contains($presentation, 'href="#insights"'), 'Public navigation must omit the unavailable Insights item entirely.');
        phase2Assert(str_contains($presentation, "portfolioCopy('talk')") && str_contains($presentation, 'aria-hidden="true" focusable="false"') && !str_contains($presentation, 'Telegram'), 'Contact CTA must have visible copy and a decorative generic icon.');
        phase2Assert(is_file($brandAsset) && filesize($brandAsset) > 0 && str_contains($dockerfile, 'cp -a /var/www/app/public/. /var/www/public/'), 'Public favicon asset must remain published.');
        phase2Assert(strpos($presentation, '<header class="portfolio-header">') < strpos($presentation, '<main id="portfolio-main">'), 'Header precedes main content.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationHeroHeadline(') && str_contains($presentation, 'portfolio-hero-reserved') && str_contains($presentation, 'portfolio-hero-profile-card') && str_contains($presentation, 'portfolio-hero-profile-role') && !str_contains($presentation, 'Turning Data into') && substr_count($presentation, '<h1 ') === 1, 'Reference hero uses the owner headline and a real name/title identity card.');
        phase2Assert(str_contains($presentation, '<section class="portfolio-metrics"') && str_contains($presentation, 'portfolioPresentationMetrics(') && str_contains($copy, 'Professional Overview') && str_contains($presentation, 'portfolio-hero-profile-location') && !str_contains($presentation, 'portfolio-about-location') && !str_contains($presentation, 'Based in ') && !str_contains($presentation, 'href="/download'), 'Overview retains truthful counts and About without duplicate location or fabricated destinations.');
        phase2Assert(str_contains($presentation, 'portfolioPresentationProjects(') && !str_contains($presentation, 'array_slice($projects, 0, 3)') && str_contains($presentation, 'data-project-navigator') && str_contains($copy, 'Show previous projects') && str_contains($copy, 'Show next projects') && str_contains($presentation, "renderPortfolioNeutralCover('portfolio-project-visual', \$projectTitle)") && str_contains($shared, 'portfolio-neutral-cover') && str_contains($presentation, 'loading="lazy"') && !str_contains($presentation, 'profileInitials($projectTitle)'), 'All eligible projects retain their supplied order, real images or one neutral CSS cover, and accessible navigation.');
        phase2Assert(str_contains($scopedData, 'ORDER BY created_at DESC, id DESC') && str_contains($lifecycle, 'ORDER BY projects.created_at DESC, projects.id DESC'), 'Owner/public project reads remain newest-first.');
        phase2Assert(str_contains($presentation, 'portfolio-hero-actions-cluster') && str_contains($presentation, 'portfolio-hero-social-list') && str_contains($presentation, 'portfolioPresentationSocialIcon(') && str_contains($presentation, 'portfolioPresentationHeroSocialActions(') && str_contains($presentation, "['LinkedIn', 'GitHub']"), 'Hero social priority and safe icon actions remain data-driven.');
        phase2Assert(str_contains($presentation, 'href="#projects"') && str_contains($presentation, 'portfolio-button-secondary" href="#contact"') && !str_contains($presentation, 'Download Resume') && !str_contains($presentation, 'href="/download'), 'Hero actions target actual work and contact; no unavailable Resume control.');
        phase2Assert(str_contains($presentation, 'renderPortfolioPreviewBar') && str_contains($copy, 'The contact form is inactive in private preview.') && str_contains($shared, 'portfolioCopy($state, $locale)'), 'Preview distinguishes its real publication state and inactive contact form.');
        foreach (['Years Experience', 'Happy Clients', 'Industry Awards', 'Testimonials', 'Trusted By', 'Resume download', 'Newsletter', 'Experience timeline'] as $unsupportedClaim) {
            phase2Assert(!str_contains($presentation, $unsupportedClaim), "Unsupported Portfolio claim introduced: {$unsupportedClaim}");
        }
        phase2Assert(str_contains($stylesheet, '--pf-container: 1440px') && str_contains($stylesheet, 'minmax(0, 1.4fr) minmax(0, 1fr)') && str_contains($stylesheet, 'Georgia, "Times New Roman", serif') && str_contains($stylesheet, 'portfolio-hero-reserved'), 'Reference hero geometry and editorial heading type are preserved.');
        phase2Assert(str_contains($stylesheet, '.portfolio-hero-profile-card--image') && str_contains($stylesheet, '.portfolio-hero-profile-card--fallback') && str_contains($stylesheet, 'aspect-ratio: 4 / 3') && str_contains($stylesheet, '.portfolio-hero-profile-visual img { width: 100%; height: 100%; object-fit: cover;'), 'Real profile image reserves space; initials fallback remains data-driven.');
        phase2Assert(str_contains($stylesheet, '.portfolio-hero-actions-cluster { display: flex; flex-direction: column; align-items: flex-start; gap: 16px') && str_contains($stylesheet, '.portfolio-hero-actions { display: flex; flex-wrap: wrap;') && str_contains($stylesheet, 'min-height: 44px') && str_contains($stylesheet, '.portfolio-hero-social-list'), 'Hero CTAs and social actions wrap and retain accessible targets.');
        phase2Assert(str_contains($stylesheet, '.portfolio-closing-layout { display: grid; grid-template-columns: 1fr; align-items: start;') && str_contains($stylesheet, '.portfolio-closing-layout--contact-only') && str_contains($stylesheet, '.portfolio-contact { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));') && str_contains($stylesheet, '.portfolio-contact { grid-template-columns: 1fr;'), 'Experience precedes the two-column Contact card; Contact stacks responsively.');
        phase2Assert(str_contains($stylesheet, '.portfolio-metrics-strip { display: grid') && str_contains($stylesheet, '.portfolio-metrics-strip--count-') && str_contains($stylesheet, '.portfolio-about-card { display: grid;') && str_contains($stylesheet, '.portfolio-about-heading { border-right: 1px') && !str_contains($stylesheet, '.portfolio-about-location'), 'Real metrics adapt to available counts; About keeps readable responsive hierarchy.');
        phase2Assert(str_contains($stylesheet, '.portfolio-project-grid { display: grid; min-width: 0; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: stretch;') && str_contains($stylesheet, '.portfolio-project-visual { height: 170px;') && str_contains($stylesheet, '.portfolio-neutral-cover { background: var(--pf-raised);') && str_contains($stylesheet, '.portfolio-project-visual img { width: 100%; height: 100%; object-fit: cover;') && str_contains($stylesheet, '.portfolio-project-technologies { display: flex; flex-wrap: wrap;') && !str_contains($stylesheet, 'line-clamp'), 'Reference cards show real or neutral covers, full descriptions and wrapping technologies.');
        phase2Assert(str_contains($presentation, 'portfolio-skills-region') && str_contains($presentation, 'portfolio-skills-panel') && str_contains($presentation, '<span class="portfolio-skill-label">') && str_contains($script, "document.createElement('button')") && !preg_match('/skill_(?:category|group|level|proficiency|years)/i', $presentation), 'Skills retain real labels and plain no-JS labels enhanced to native accessible selection without invented skill ratings.');
        phase2Assert(str_contains($stylesheet, '.portfolio-skills-panel { width: 100%;') && str_contains($stylesheet, '.portfolio-skills-list { display: flex; flex-wrap: wrap;') && str_contains($stylesheet, '.portfolio-skill-control[aria-pressed="true"]') && str_contains($stylesheet, '.portfolio-skill-control:focus-visible'), 'Skills remain full-width, wrapping and visibly focusable.');
        phase2Assert(str_contains($script, 'initializePortfolioSkills') && str_contains($script, "button.getAttribute('aria-pressed') === 'true'") && str_contains($script, "selected.setAttribute('aria-pressed', 'false')") && str_contains($script, 'initializeProjectNavigator') && str_contains($script, 'card.hidden = index < start || index >= end') && !str_contains($presentation, 'data-project-range') && !preg_match('/localStorage|sessionStorage|fetch\(|XMLHttpRequest|document\.cookie/i', $script), 'Skills and project navigation remain scoped, delegated, non-persistent enhancements.');
        phase2Assert(str_contains($presentation, "portfolioCopy('code')") && str_contains($presentation, "portfolioPresentationSocialIcon('GitHub')") && str_contains($stylesheet, '.portfolio-project-link svg { width: 15px'), 'Safe GitHub actions retain decorative icons and meaningful copy.');
        phase2Assert(str_contains($stylesheet, 'position: sticky') && str_contains($stylesheet, '.portfolio-header-inner') && str_contains($stylesheet, '.portfolio-nav-links a.is-current') && str_contains($stylesheet, 'min-height: 94px'), 'Sticky header preserves bounded geometry and current-section indication.');
        phase2Assert(str_contains($stylesheet, '.portfolio-header-cta svg { width: 18px; height: 18px; flex-basis: 18px; }'), 'CTA icon remains balanced and decorative.');
        phase2Assert(str_contains($script, 'IntersectionObserver') && str_contains($script, 'data-portfolio-section') && str_contains($script, 'aria-current') && str_contains($script, 'const initialId = window.location.hash.slice(1)'), 'Scrollspy retains initial hashes and accessible current state.');
        phase2Assert(str_contains($presentation, 'portfolio-mobile-nav-layer') && str_contains($presentation, 'role="dialog" aria-modal="true"') && str_contains($presentation, 'aria-controls="portfolio-mobile-nav"') && !str_contains($presentation, 'portfolio-mobile-nav-placeholder'), 'Mobile navigation keeps dialog semantics and only real destinations.');
        phase2Assert(str_contains($stylesheet, '.portfolio-js .portfolio-nav-links { display: none; }') && str_contains($stylesheet, '.portfolio-js .portfolio-menu-toggle { display: inline-flex; }') && str_contains($stylesheet, 'scroll-margin-top: 110px'), 'Enhanced drawer keeps a native no-JS navigation fallback and safe anchor offset.');
        phase2Assert(str_contains($script, "event.key === 'Escape'") && str_contains($script, "toggle.setAttribute('aria-expanded', 'true')") && str_contains($script, "toggle.setAttribute('aria-expanded', 'false')") && str_contains($script, 'portfolio-mobile-nav-open') && str_contains($script, 'closeNavigation();'), 'Mobile navigation keeps keyboard open/close, Escape, focus isolation and scroll lock.');
        phase2Assert(str_contains($stylesheet, ':focus-visible') && str_contains($stylesheet, 'prefers-reduced-motion'), 'Focus and reduced-motion support remain available.');
        foreach (['@media (max-width: 1100px)', '@media (max-width: 820px)', '@media (max-width: 620px)', '@media (max-width: 430px)'] as $breakpoint) {
            phase2Assert(str_contains($stylesheet, $breakpoint), "Responsive breakpoint missing: {$breakpoint}");
        }
        phase2Assert(is_file(PHASE2_REPOSITORY_ROOT . '/public/assets/js/portfolio.js') && is_file(PHASE2_REPOSITORY_ROOT . '/public/assets/css/portfolio.css'), 'Production public assets must remain available.');

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
                ['id' => 7, 'title' => '<Project>', 'category' => 'Data Science', 'description' => 'Description', 'github_url' => 'https://example.test/project', 'image_path' => null, 'technologies' => ['Python', '<script>alert(1)</script>', 'Scikit-learn', 'Pandas', 'XGBoost']],
                ['id' => 8, 'title' => 'Image Project', 'category' => 'Machine Learning', 'description' => 'Image description', 'github_url' => 'https://example.test/image-project', 'image_path' => 'portfolios/7/projects/project_abc123.jpg'],
                ['id' => 9, 'title' => 'Third Project', 'category' => 'Analytics', 'description' => 'Third description', 'github_url' => 'javascript:alert(1)', 'image_path' => null],
                ['id' => 10, 'title' => 'Fourth Project', 'category' => 'Product Engineering', 'description' => 'Fourth description', 'github_url' => '', 'image_path' => null],
            ],
            [
                'profile_media_url' => '/p/safe/media/profile',
                'project_media_url' => static fn (int $projectId): string => "/media/{$projectId}",
                'contact_action' => '/p/safe/contact',
            ],
        );
        $rendered = ob_get_clean();
        phase2Assert(is_string($rendered) && str_contains($rendered, '<h1 id="portfolio-title" dir="auto">&lt;Build useful systems&gt;</h1>') && str_contains($rendered, '&lt;Owner&gt;') && str_contains($rendered, '&lt;PHP&gt;') && str_contains($rendered, '&lt;Project&gt;'), 'Shared Portfolio presentation does not encode user-owned Hero or Portfolio content.');
        $renderedHeader = preg_match('/<header class="portfolio-header">.*?<\/header>/s', $rendered, $headerMatch) === 1 ? $headerMatch[0] : '';
        $renderedProfileCard = preg_match('/<article class="portfolio-hero-profile-card.*?<\/article>/s', $rendered, $profileCardMatch) === 1 ? $profileCardMatch[0] : '';
        phase2Assert($renderedHeader !== '' && str_contains($renderedHeader, 'aria-label="ATHER, home"') && !str_contains($renderedHeader, '&lt;Owner&gt;'), 'Header must identify the ATHER platform without repeating owner identity.');
        phase2Assert($renderedProfileCard !== '' && str_contains($renderedProfileCard, '&lt;Owner&gt;') && str_contains($renderedProfileCard, '&lt;Amman&gt;') && str_contains($renderedProfileCard, '>Engineer<'), 'Hero Personal ID must retain owner name/location without repeating the professional title.');
        phase2Assert(str_contains($rendered, 'portfolio-hero-profile-card--image') && str_contains($rendered, 'src="/p/safe/media/profile"') && str_contains($rendered, 'alt="&lt;Owner&gt; portrait"') && str_contains($rendered, '&lt;Amman&gt;') && !str_contains($rendered, 'Years Experience'), 'S03 Hero profile card does not render escaped real data or introduced fabricated experience.');
        phase2Assert(str_contains($rendered, 'portfolio-metrics-strip') && str_contains($rendered, '>3</strong>') && str_contains($rendered, 'Projects') && str_contains($rendered, 'Core Skills') && str_contains($rendered, 'Focus Areas') && !str_contains($rendered, 'Projects Featured') && !str_contains($rendered, 'Project Categories') && !str_contains($rendered, '25+') && !str_contains($rendered, 'Happy Clients') && !str_contains($rendered, 'Industry Awards'), 'S04 metrics must use only truthful rendered Portfolio counts and professional labels.');
        $fallbackProjectCard = preg_match('/<article class="portfolio-project-card portfolio-project-card--fallback">.*?<\/article>/s', $rendered, $fallbackProjectCardMatch) === 1 ? $fallbackProjectCardMatch[0] : '';
        $imageProjectCard = preg_match('/<article class="portfolio-project-card portfolio-project-card--image">.*?<\/article>/s', $rendered, $imageProjectCardMatch) === 1 ? $imageProjectCardMatch[0] : '';
        $projectGithubLink = preg_match('/<a class="portfolio-project-link".*?<\/a>/s', $rendered, $projectGithubLinkMatch) === 1 ? $projectGithubLinkMatch[0] : '';
        phase2AssertSame(2, substr_count($rendered, 'class="portfolio-project-link"'), 'Absent or unsafe project URLs created a GitHub focus target.');
        phase2Assert(!str_contains($rendered, 'javascript:alert') && $fallbackProjectCard !== '' && $imageProjectCard !== '' && str_contains($fallbackProjectCard, 'portfolio-neutral-cover') && !str_contains($fallbackProjectCard, '<img') && str_contains($fallbackProjectCard, 'portfolio-project-identity') && str_contains($fallbackProjectCard, 'aria-hidden="true"') && str_contains($imageProjectCard, 'src="/media/8"') && str_contains($imageProjectCard, 'alt="Image Project project preview" loading="lazy"') && !str_contains($rendered, 'portfolio-project-fallback-category') && substr_count($rendered, '<article class="portfolio-project-card ') === 4 && str_contains($rendered, 'Fourth Project') && str_contains($rendered, 'data-project-navigator') && str_contains($rendered, 'aria-controls="portfolio-project-list"') && substr_count($rendered, 'Data Science') === 1 && str_contains($rendered, 'action="/p/safe/contact#contact"') && str_contains($projectGithubLink, 'View on GitHub') && str_contains($projectGithubLink, '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">') && !str_contains($projectGithubLink, '↗') && str_contains($projectGithubLink, 'rel="noopener noreferrer"') && str_contains($projectGithubLink, 'aria-label="View &lt;Project&gt; on GitHub"'), 'S05 shared presentation must retain all eligible cards, safe image/fallback states, scoped contact action, real cover media, and the validated GitHub action without an empty fallback visual band.');
        phase2Assert(str_contains($rendered, 'portfolio-project-technologies') && str_contains($rendered, 'Python') && !str_contains($rendered, 'Pandas') && str_contains($rendered, '+2') && str_contains($rendered, 'Scikit-learn') && str_contains($rendered, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($rendered, 'XGBoost') && substr_count($rendered, 'portfolio-project-technologies') === 1, 'S05C Project card technologies must render only the first three escaped stored labels plus the real remainder count and omit absent rows.');
        $skillsRegion = preg_match('/<section class="portfolio-skills-region".*?<\/section>/s', $rendered, $skillsMatch) === 1 ? $skillsMatch[0] : '';
        phase2Assert($skillsRegion !== '' && strpos($rendered, 'id="projects-title"') < strpos($rendered, 'id="portfolio-skills-heading"') && substr_count($skillsRegion, 'class="portfolio-skill-label"') === 3 && strpos($skillsRegion, '&lt;PHP&gt;') < strpos($skillsRegion, '>SQL</bdi>') && str_contains($skillsRegion, '&quot;&gt;&lt;script&gt;alert(&quot;skill&quot;)&lt;/script&gt;') && !str_contains($skillsRegion, 'portfolio-skill-tag') && !str_contains($skillsRegion, 'Python') && !str_contains($skillsRegion, 'Pandas'), 'S06B Skills must follow Projects in a full-width supporting section while preserving stored order, escaped labels, and static no-JS labels and enhanced native button semantics.');
        ob_start();
        renderPortfolioPresentation(['full_name' => 'Projects Only'], [], [['id' => 9, 'title' => 'Only Project', 'category' => 'Data', 'description' => 'Description', 'github_url' => '', 'image_path' => null]]);
        $projectsOnly = ob_get_clean();
        phase2Assert(is_string($projectsOnly) && !str_contains($projectsOnly, 'portfolio-project-link') && !str_contains($projectsOnly, 'View on GitHub'), 'Project without URL rendered a GitHub control.');
        phase2Assert(is_string($projectsOnly) && str_contains($projectsOnly, 'portfolio-work-content') && str_contains($projectsOnly, 'portfolio-projects') && !str_contains($projectsOnly, 'data-project-direction') && !str_contains($projectsOnly, 'portfolio-skills-panel'), 'S06 must omit empty Skills and unnecessary Project navigation while preserving a full-width Projects composition.');
        ob_start();
        renderPortfolioPresentation(['full_name' => 'Skills Only'], [['skill_name' => 'PHP']], []);
        $skillsOnly = ob_get_clean();
        phase2Assert(is_string($skillsOnly) && str_contains($skillsOnly, 'portfolio-work-content') && !str_contains($skillsOnly, 'portfolio-projects') && str_contains($skillsOnly, 'portfolio-skills-region') && str_contains($skillsOnly, 'portfolio-skill-label'), 'S06 must render Skills independently without an empty Projects region.');
        phase2Assert(
            str_contains($rendered, 'href="#projects"')
            && str_contains($rendered, 'href="mailto:owner@example.test"')
            && str_contains($rendered, 'aria-label="Email &lt;Owner&gt;"')
            && !str_contains($rendered, 'href="/download')
            && !str_contains($rendered, 'href="#"')
            && str_contains($rendered, '<a class="portfolio-button portfolio-button-secondary" href="#contact">')
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
        phase2AssertSame([11, 12, 13, 14], array_column(portfolioPresentationProjects([
            ['id' => 11],
            ['id' => 12],
            ['id' => 13],
            ['id' => 14],
        ]), 'id'), 'S05 Projects must preserve the scoped deterministic display order without renderer truncation.');

        ob_start();
        renderPortfolioPresentation([
            'full_name' => 'Overview Owner',
            'about_me' => "First &lt;line&gt;\nSecond line",
            'location' => '<Amman>',
        ], [], [], ['preview' => true]);
        $overviewRendered = ob_get_clean();
        $overviewProfileCard = preg_match('/<article class="portfolio-hero-profile-card.*?<\/article>/s', $overviewRendered, $overviewProfileCardMatch) === 1 ? $overviewProfileCardMatch[0] : '';
        phase2Assert(is_string($overviewRendered) && $overviewProfileCard !== '' && str_contains($overviewRendered, 'portfolio-preview-bar') && str_contains($overviewRendered, '<h2 id="about-title">Professional Overview</h2>') && str_contains($overviewRendered, 'First &amp;lt;line&amp;gt;<br />') && str_contains($overviewRendered, 'Second line') && !str_contains($overviewRendered, 'portfolio-about-location') && !str_contains($overviewRendered, 'Based in ') && str_contains($overviewProfileCard, 'portfolio-hero-profile-location') && str_contains($overviewProfileCard, '&lt;Amman&gt;') && strpos($overviewRendered, 'Professional Overview') < strpos($overviewRendered, 'First &amp;lt;line&amp;gt;'), 'S04 About must preserve escaped line breaks and its controlled heading hierarchy while rendering dynamic location only in the Hero profile card.');
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
        phase2Assert(is_string($previewRendered) && str_contains($previewRendered, 'portfolio-preview-bar') && str_contains($previewRendered, 'disabled aria-disabled="true"') && str_contains($previewRendered, 'portfolio-hero-profile-card--fallback') && str_contains($previewRendered, 'portfolio-hero-profile-fallback') && !str_contains($previewRendered, 'portfolio-hero-profile-location'), 'S03A fallback profile visual or optional location handling is incomplete.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
