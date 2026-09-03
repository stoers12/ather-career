<?php

declare(strict_types=1);

require_once __DIR__ . '/presentation.php';
require_once __DIR__ . '/validation.php';

function portfolioPresentationEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function portfolioPresentationValue(array $profile, string $field): string
{
    $value = $profile[$field] ?? '';

    return is_string($value) ? trim($value) : '';
}

/** @return list<array{label: string, url: string}> */
function portfolioPresentationSocialLinks(array $profile): array
{
    $links = [];
    foreach ([
        'linkedin_url' => 'LinkedIn',
        'github_url' => 'GitHub',
        'instagram_url' => 'Instagram',
        'facebook_url' => 'Facebook',
        'website_url' => 'Website',
    ] as $field => $label) {
        $url = portfolioPresentationValue($profile, $field);
        if ($url !== '' && isPublicWebsiteDestination($url)) {
            $links[] = ['label' => $label, 'url' => $url];
        }
    }

    return $links;
}

function portfolioPresentationProjectLink(array $project): ?string
{
    $url = isset($project['github_url']) && is_string($project['github_url'])
        ? trim($project['github_url'])
        : '';

    return $url !== '' && isPublicWebsiteDestination($url) ? $url : null;
}

/** @return list<array{label: string, url: string, external: bool}> */
function portfolioPresentationHeroSocialActions(array $profile, string $emailAction): array
{
    $socialByLabel = [];
    foreach (portfolioPresentationSocialLinks($profile) as $link) {
        $socialByLabel[$link['label']] = $link['url'];
    }

    $actions = [];
    foreach (['LinkedIn', 'GitHub'] as $label) {
        if (isset($socialByLabel[$label])) {
            $actions[] = ['label' => $label, 'url' => $socialByLabel[$label], 'external' => true];
        }
    }
    if ($emailAction !== '') {
        $actions[] = ['label' => 'Email', 'url' => $emailAction, 'external' => false];
    }

    // The current public Portfolio model has no resume or profile-document
    // destination, so a validated personal website is the truthful fallback.
    if (isset($socialByLabel['Website'])) {
        $actions[] = ['label' => 'Website', 'url' => $socialByLabel['Website'], 'external' => true];
    }

    return $actions;
}

/** @return list<array<string, mixed>> */
function portfolioPresentationFeaturedProjects(array $projects): array
{
    // Public projects already arrive in the product's deterministic display
    // order (created_at ASC, id ASC). There is no separate featured field.
    return array_slice($projects, 0, 3);
}

function portfolioPresentationProjectFallbackIcon(): string
{
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 7.5h14v10H5zM8 4.5h8M8 11.5h8M8 15.5h5"/></svg>';
}

/** @return list<array{key: string, value: int, label: string}> */
function portfolioPresentationMetrics(array $projects, array $skills): array
{
    $skillCount = 0;
    foreach ($skills as $skill) {
        if (isset($skill['skill_name']) && trim((string) $skill['skill_name']) !== '') {
            ++$skillCount;
        }
    }

    $categories = [];
    foreach ($projects as $project) {
        $category = isset($project['category']) ? trim((string) $project['category']) : '';
        if ($category !== '') {
            $categories[strtolower($category)] = true;
        }
    }

    $metrics = [];
    if ($projects !== []) {
        $metrics[] = ['key' => 'projects', 'value' => count($projects), 'label' => 'Projects Featured'];
    }
    if ($skillCount > 0) {
        $metrics[] = ['key' => 'skills', 'value' => $skillCount, 'label' => 'Core Skills'];
    }
    if ($categories !== []) {
        $metrics[] = ['key' => 'categories', 'value' => count($categories), 'label' => 'Project Categories'];
    }

    return $metrics;
}

function portfolioPresentationSocialIcon(string $label): string
{
    return match ($label) {
        'LinkedIn' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.2 8.2v9.6M6.2 5.1v.1M10.7 17.8v-5.3a2.7 2.7 0 0 1 5.4 0v5.3M10.7 12.9c.4-1.2 1.3-2 2.8-2 1.6 0 2.6 1 2.6 3v3.9"/></svg>',
        'GitHub' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9.2 19.4c-4.1 1.2-4.1-2.1-5.8-2.6M15 19.4v-2.2a2.1 2.1 0 0 0-.6-1.7c2.1-.2 4.3-1 4.3-4.6a3.6 3.6 0 0 0-1-2.6 3.4 3.4 0 0 0-.1-2.6s-.8-.3-2.7 1a9.2 9.2 0 0 0-4.9 0c-1.9-1.3-2.7-1-2.7-1a3.4 3.4 0 0 0-.1 2.6 3.6 3.6 0 0 0-1 2.6c0 3.6 2.2 4.4 4.3 4.6a2.1 2.1 0 0 0-.6 1.7v2.2"/></svg>',
        'Website' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8.5"/><path d="M3.8 12h16.4M12 3.5c2.1 2.3 3.2 5.1 3.2 8.5s-1.1 6.2-3.2 8.5c-2.1-2.3-3.2-5.1-3.2-8.5S9.9 5.8 12 3.5Z"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8A2.5 2.5 0 0 1 17.5 17H10l-4.5 3v-3.5A2.5 2.5 0 0 1 3 14.5v-8Z"/><path d="m5 6.5 6.2 4.5L18 6.5"/></svg>',
    };
}

function portfolioPresentationActionIcon(string $action): string
{
    return match ($action) {
        'arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h13M13 6l6 6-6 6"/></svg>',
        'download' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4v10M8 10l4 4 4-4M5 19h14"/></svg>',
        default => portfolioPresentationSocialIcon('Email'),
    };
}

function portfolioPresentationMetricIcon(string $metric): string
{
    return match ($metric) {
        'projects' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 7.5h14v10H5zM8 4.5h8M8 11.5h8M8 15.5h5"/></svg>',
        'skills' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 6.5h14v11H5zM8 6.5V4.5h8v2M8.5 11h7M8.5 14.5h4"/></svg>',
    };
}

/**
 * @param list<array<string, mixed>> $skills
 * @param list<array<string, mixed>> $projects
 * @param array{
 *     preview?: bool,
 *     preview_error?: string,
 *     profile_media_url?: string,
 *     project_media_url?: callable(int): string,
 *     contact_action?: string,
 *     contact_sent?: bool
 * } $options
 */
function renderPortfolioPresentation(array $profile, array $skills, array $projects, array $options = []): void
{
    $preview = ($options['preview'] ?? false) === true;
    $name = portfolioPresentationValue($profile, 'full_name');
    if ($name === '') {
        $name = $preview ? 'Your Portfolio' : 'Portfolio';
    }
    $title = portfolioPresentationValue($profile, 'professional_title');
    $location = portfolioPresentationValue($profile, 'location');
    $aboutMe = portfolioPresentationValue($profile, 'about_me');
    $workDescription = portfolioPresentationValue($profile, 'work_description');
    $heroSummary = $workDescription !== '' ? $workDescription : $aboutMe;
    $aboutNarrative = $aboutMe !== '' && $aboutMe !== $heroSummary ? $aboutMe : '';
    $email = portfolioPresentationValue($profile, 'email');
    $emailAction = filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? 'mailto:' . $email : '';
    $socialLinks = portfolioPresentationSocialLinks($profile);
    $heroSocialActions = portfolioPresentationHeroSocialActions($profile, $emailAction);
    $metrics = portfolioPresentationMetrics($projects, $skills);
    $featuredProjects = portfolioPresentationFeaturedProjects($projects);
    $initials = profileInitials($name) ?: 'P';
    $showProjects = $projects !== [];
    $showSkills = $skills !== [];
    $showAbout = $aboutNarrative !== '';
    $profileMediaUrl = isset($options['profile_media_url']) && is_string($options['profile_media_url'])
        ? $options['profile_media_url']
        : '';
    $projectMediaUrl = isset($options['project_media_url']) && is_callable($options['project_media_url'])
        ? $options['project_media_url']
        : static fn (int $projectId): string => '';
    $contactAction = isset($options['contact_action']) && is_string($options['contact_action'])
        ? $options['contact_action']
        : '';
    $previewError = isset($options['preview_error']) && is_string($options['preview_error'])
        ? $options['preview_error']
        : '';
    $stylesheet = '/' . versionedAssetUrl('portfolio.css');
    $script = '/' . versionedAssetUrl('portfolio.js');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($preview): ?><meta name="robots" content="noindex,nofollow"><?php else: ?><meta name="robots" content="index,follow"><?php endif; ?>
    <meta name="color-scheme" content="dark">
    <title><?php echo portfolioPresentationEscape($name); ?> — Portfolio</title>
    <link rel="stylesheet" href="<?php echo portfolioPresentationEscape($stylesheet); ?>">
    <script src="<?php echo portfolioPresentationEscape($script); ?>" defer></script>
</head>
<body class="portfolio-page<?php echo $preview ? ' portfolio-preview-mode' : ''; ?>">
<a class="portfolio-skip-link" href="#portfolio-main">Skip to content</a>

<header class="portfolio-header">
    <div class="portfolio-header-inner">
        <a class="portfolio-brand" href="#top" aria-label="<?php echo portfolioPresentationEscape($name); ?>, home">
            <span class="portfolio-brand-mark" aria-hidden="true"><?php echo portfolioPresentationEscape($initials); ?></span>
            <span class="portfolio-brand-copy"><strong><?php echo portfolioPresentationEscape($name); ?></strong></span>
        </a>
        <nav class="portfolio-nav-links" aria-label="Portfolio sections">
            <a class="is-current" href="#top" data-portfolio-section="top" aria-current="page">Home</a>
            <?php if ($showAbout): ?><a href="#about" data-portfolio-section="about">About</a><?php endif; ?>
            <?php if ($showProjects): ?><a href="#projects" data-portfolio-section="projects">Projects</a><?php endif; ?>
            <span class="portfolio-nav-placeholder" aria-disabled="true" title="Coming soon">Experience</span>
            <?php if ($showSkills): ?><a href="#skills" data-portfolio-section="skills">Skills</a><?php endif; ?>
            <span class="portfolio-nav-placeholder" aria-disabled="true" title="Coming soon">Insights</span>
            <a href="#contact" data-portfolio-section="contact">Contact</a>
        </nav>
        <a class="portfolio-header-cta" href="#contact"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3.5 6.5A2.5 2.5 0 0 1 6 4h12a2.5 2.5 0 0 1 2.5 2.5v11A2.5 2.5 0 0 1 18 20H6a2.5 2.5 0 0 1-2.5-2.5v-11Z"/><path d="m4.5 6 6.07 5.06a2.23 2.23 0 0 0 2.86 0L19.5 6"/></svg><span>Let’s Connect</span></a>
    </div>
</header>

<?php if ($preview): ?>
    <aside class="portfolio-preview-bar" aria-label="Private preview status">
        <div class="portfolio-container portfolio-preview-bar-inner"><span><strong>Private preview</strong> · viewing does not publish this Portfolio.</span><a href="owner.php">Dashboard</a></div>
    </aside>
<?php endif; ?>

<main id="portfolio-main">
    <?php if ($previewError !== ''): ?><div class="portfolio-container portfolio-alert" role="alert"><?php echo portfolioPresentationEscape($previewError); ?></div><?php endif; ?>

    <section class="portfolio-hero" id="top" aria-labelledby="portfolio-title">
        <div class="portfolio-container portfolio-hero-grid">
            <div class="portfolio-hero-copy">
                <p class="portfolio-hero-badge"><span aria-hidden="true"></span><?php echo portfolioPresentationEscape($title !== '' ? $title : 'Professional'); ?></p>
                <h1 id="portfolio-title">Turning Data into <span>Intelligence</span></h1>
                <?php if ($heroSummary !== ''): ?><p class="portfolio-hero-summary"><?php echo nl2br(portfolioPresentationEscape($heroSummary)); ?></p><?php endif; ?>
                <div class="portfolio-hero-actions-cluster">
                    <div class="portfolio-hero-actions">
                        <?php if ($showProjects): ?><a class="portfolio-button portfolio-button-primary" href="#projects"><span>View My Work</span><?php echo portfolioPresentationActionIcon('arrow'); ?></a><?php endif; ?>
                        <button class="portfolio-button portfolio-button-secondary" type="button" disabled aria-label="Download Resume (unavailable)"><span>Download Resume</span><?php echo portfolioPresentationActionIcon('download'); ?></button>
                    </div>
                    <?php if ($heroSocialActions !== []): ?>
                        <ul class="portfolio-hero-social-list" aria-label="Professional links">
                            <?php foreach ($heroSocialActions as $link): ?><li><a href="<?php echo portfolioPresentationEscape($link['url']); ?>" aria-label="<?php echo portfolioPresentationEscape($link['label'] === 'Email' ? 'Email ' . $name : $link['label']); ?>"<?php if ($link['external']): ?> rel="noopener noreferrer"<?php endif; ?>><?php echo portfolioPresentationSocialIcon($link['label']); ?></a></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="portfolio-hero-reserved">
                <article class="portfolio-hero-profile-card portfolio-hero-profile-card--<?php echo $profileMediaUrl !== '' ? 'image' : 'fallback'; ?>" aria-label="Profile summary">
                    <div class="portfolio-hero-profile-visual">
                        <?php if ($profileMediaUrl !== ''): ?>
                            <img src="<?php echo portfolioPresentationEscape($profileMediaUrl); ?>" alt="<?php echo portfolioPresentationEscape($name); ?> portrait">
                        <?php else: ?>
                            <span class="portfolio-hero-profile-fallback" aria-hidden="true"><?php echo portfolioPresentationEscape($initials); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="portfolio-hero-profile-details">
                        <h2><?php echo portfolioPresentationEscape($name); ?></h2>
                        <?php if ($title !== ''): ?><p class="portfolio-hero-profile-role"><?php echo portfolioPresentationEscape($title); ?></p><?php endif; ?>
                        <?php if ($location !== ''): ?><p class="portfolio-hero-profile-location"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20s6-5.1 6-11a6 6 0 1 0-12 0c0 5.9 6 11 6 11Z"/><circle cx="12" cy="9" r="2"/></svg><?php echo portfolioPresentationEscape($location); ?></p><?php endif; ?>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <?php if ($metrics !== []): ?>
        <section class="portfolio-metrics" aria-label="Portfolio metrics">
            <div class="portfolio-container">
                <ul class="portfolio-metrics-strip" style="--portfolio-metric-count: <?php echo count($metrics); ?>">
                    <?php foreach ($metrics as $metric): ?>
                        <li class="portfolio-metric"><span class="portfolio-metric-icon"><?php echo portfolioPresentationMetricIcon($metric['key']); ?></span><span><strong><?php echo $metric['value']; ?></strong><small><?php echo portfolioPresentationEscape($metric['label']); ?></small></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($showAbout): ?>
        <section class="portfolio-section portfolio-about" id="about" aria-labelledby="about-title"><div class="portfolio-container"><div class="portfolio-about-card">
            <div class="portfolio-about-heading"><p class="portfolio-section-kicker">ABOUT / 02</p><h2 id="about-title">A closer look at my work.</h2></div>
            <div class="portfolio-about-copy"><p><?php echo nl2br(portfolioPresentationEscape($aboutNarrative)); ?></p><?php if ($location !== ''): ?><span class="portfolio-location-pill">Based in <?php echo portfolioPresentationEscape($location); ?></span><?php endif; ?></div>
        </div></div></section>
    <?php endif; ?>

    <?php if ($showProjects || $showSkills): ?>
        <div class="portfolio-section portfolio-work"><div class="portfolio-container portfolio-work-grid portfolio-work-grid--<?php echo $showProjects && $showSkills ? 'split' : 'single'; ?>">
            <?php if ($showProjects): ?>
                <section class="portfolio-projects" id="projects" aria-labelledby="projects-title">
                    <header class="portfolio-section-heading"><h2 id="projects-title">Featured Projects</h2></header>
                    <div class="portfolio-project-grid">
                        <?php foreach ($featuredProjects as $project):
                            $projectId = isset($project['id']) ? (int) $project['id'] : 0;
                            $projectTitle = isset($project['title']) ? trim((string) $project['title']) : 'Project';
                            $category = isset($project['category']) ? trim((string) $project['category']) : '';
                            $projectImageUrl = (string) $projectMediaUrl($projectId);
                            $hasProjectImage = isset($project['image_path']) && trim((string) $project['image_path']) !== '' && $projectImageUrl !== '';
                            $projectLink = portfolioPresentationProjectLink($project);
                            $projectTechnologies = isset($project['technologies']) && is_array($project['technologies'])
                                ? array_slice($project['technologies'], 0, 4)
                                : [];
                            ?>
                            <article class="portfolio-project-card portfolio-project-card--<?php echo $hasProjectImage ? 'image' : 'fallback'; ?>">
                                <div class="portfolio-project-visual">
                                    <?php if ($hasProjectImage): ?>
                                        <img src="<?php echo portfolioPresentationEscape($projectImageUrl); ?>" alt="<?php echo portfolioPresentationEscape($projectTitle); ?> project preview" loading="lazy">
                                    <?php else: ?>
                                        <span class="portfolio-project-fallback" aria-hidden="true"><?php echo portfolioPresentationProjectFallbackIcon(); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="portfolio-project-body">
                                    <?php if ($category !== ''): ?><p class="portfolio-project-category"><?php echo portfolioPresentationEscape($category); ?></p><?php endif; ?>
                                    <h3><?php echo portfolioPresentationEscape($projectTitle); ?></h3>
                                    <?php if (isset($project['description']) && trim((string) $project['description']) !== ''): ?><p class="portfolio-project-description"><?php echo nl2br(portfolioPresentationEscape($project['description'])); ?></p><?php endif; ?>
                                    <?php if ($projectTechnologies !== []): ?><ul class="portfolio-project-technologies" aria-label="Technologies used"><?php foreach ($projectTechnologies as $technology): ?><li><?php echo portfolioPresentationEscape($technology); ?></li><?php endforeach; ?></ul><?php endif; ?>
                                    <?php if ($projectLink !== null): ?><a class="portfolio-project-link" href="<?php echo portfolioPresentationEscape($projectLink); ?>" rel="noopener noreferrer" aria-label="View <?php echo portfolioPresentationEscape($projectTitle); ?> on GitHub">View on GitHub <span aria-hidden="true">↗</span></a><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($showSkills): ?>
                <div class="portfolio-skills-region" id="skills">
                    <header class="portfolio-section-heading portfolio-skills-heading"><h2 id="portfolio-skills-heading">Skills &amp; Technologies</h2></header>
                    <aside class="portfolio-skills-panel" aria-labelledby="portfolio-skills-heading">
                        <ul class="portfolio-skills-list"><?php foreach ($skills as $skill): $skillName = isset($skill['skill_name']) ? trim((string) $skill['skill_name']) : ''; ?><?php if ($skillName !== ''): ?><li><button class="portfolio-skill-control" type="button" aria-pressed="false"><?php echo portfolioPresentationEscape($skillName); ?></button></li><?php endif; ?><?php endforeach; ?></ul>
                    </aside>
                </div>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>

    <section class="portfolio-section portfolio-contact" id="contact" aria-labelledby="contact-title"><div class="portfolio-container"><div class="portfolio-contact-shell">
        <div class="portfolio-contact-copy"><p class="portfolio-section-kicker">CONTACT / 05</p><h2 id="contact-title">Let’s build something useful.</h2><p>Have a project or professional opportunity in mind? Send a message through this Portfolio.</p>
            <?php if ($socialLinks !== []): ?><ul class="portfolio-social-list portfolio-contact-social" aria-label="More ways to connect"><?php foreach ($socialLinks as $link): ?><li><a href="<?php echo portfolioPresentationEscape($link['url']); ?>" rel="noopener noreferrer"><?php echo portfolioPresentationEscape($link['label']); ?><span aria-hidden="true">↗</span></a></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
        <div class="portfolio-contact-card">
            <?php if (($options['contact_sent'] ?? false) === true): ?><p class="portfolio-form-status" role="status">Message submitted successfully.</p><?php endif; ?>
            <?php if ($preview): ?><p class="portfolio-preview-note">The contact form is inactive in private preview.</p><?php endif; ?>
            <form method="post"<?php if ($contactAction !== ''): ?> action="<?php echo portfolioPresentationEscape($contactAction); ?>"<?php endif; ?>>
                <label for="portfolio-contact-name">Name</label><input id="portfolio-contact-name" type="text" name="name" maxlength="100" required autocomplete="name"<?php echo $preview ? ' disabled' : ''; ?>>
                <label for="portfolio-contact-email">Email</label><input id="portfolio-contact-email" type="email" name="email" maxlength="255" required autocomplete="email"<?php echo $preview ? ' disabled' : ''; ?>>
                <label for="portfolio-contact-message">Message</label><textarea id="portfolio-contact-message" name="message" maxlength="5000" required<?php echo $preview ? ' disabled' : ''; ?>></textarea>
                <button type="submit"<?php echo $preview ? ' disabled aria-disabled="true"' : ''; ?>>Send message <span aria-hidden="true">→</span></button>
            </form>
        </div>
    </div></div></section>
</main>

<footer class="portfolio-footer"><div class="portfolio-container portfolio-footer-grid">
    <div class="portfolio-footer-identity"><strong><?php echo portfolioPresentationEscape($name); ?></strong><?php if ($title !== ''): ?><span><?php echo portfolioPresentationEscape($title); ?></span><?php endif; ?></div>
    <nav aria-label="Footer portfolio links"><a href="#top">Home</a><?php if ($showProjects): ?><a href="#projects">Projects</a><?php endif; ?><?php if ($showSkills): ?><a href="#skills">Skills</a><?php endif; ?><a href="#contact">Contact</a></nav>
    <?php if ($socialLinks !== []): ?><div class="portfolio-footer-social"><?php foreach ($socialLinks as $link): ?><a href="<?php echo portfolioPresentationEscape($link['url']); ?>" rel="noopener noreferrer"><?php echo portfolioPresentationEscape($link['label']); ?></a><?php endforeach; ?></div><?php endif; ?>
    <p>&copy; <?php echo date('Y'); ?> <?php echo portfolioPresentationEscape($name); ?></p>
</div></footer>
</body>
</html>
<?php
}
