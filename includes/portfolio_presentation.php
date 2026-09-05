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

function portfolioPresentationHeroHeadline(array $profile): string
{
    foreach (['hero_headline', 'professional_title', 'full_name'] as $field) {
        $value = portfolioPresentationValue($profile, $field);
        if ($value !== '') {
            return $value;
        }
    }

    return 'Portfolio';
}

function portfolioPresentationPhoneAction(string $phone): string
{
    // Only conventional numeric formatting is dialable; keep other input as text.
    if (preg_match('/^\+?(?:[0-9]+|\([0-9]+\))(?:[ .-]?(?:[0-9]+|\([0-9]+\)))*$/D', $phone) !== 1) {
        return '';
    }
    $number = str_replace([' ', '.', '-', '(', ')'], '', $phone);
    return preg_match('/^\+?[0-9]{7,15}$/D', $number) === 1 ? 'tel:' . $number : '';
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

/** @return list<array{experience_type: string, role_title: string, organization: string, location: string|null, start_month: string, end_month: string|null, is_current: bool, description: string|null}> */
function portfolioPresentationExperiences(array $experiences): array
{
    $presentationExperiences = [];
    foreach ($experiences as $experience) {
        if (!is_array($experience)) {
            continue;
        }

        $presentationExperiences[] = [
            'experience_type' => isset($experience['experience_type']) ? trim((string) $experience['experience_type']) : '',
            'role_title' => isset($experience['role_title']) ? trim((string) $experience['role_title']) : '',
            'organization' => isset($experience['organization']) ? trim((string) $experience['organization']) : '',
            'location' => isset($experience['location']) && trim((string) $experience['location']) !== '' ? trim((string) $experience['location']) : null,
            'start_month' => isset($experience['start_month']) ? trim((string) $experience['start_month']) : '',
            'end_month' => isset($experience['end_month']) && trim((string) $experience['end_month']) !== '' ? trim((string) $experience['end_month']) : null,
            'is_current' => ($experience['is_current'] ?? false) === true || ($experience['is_current'] ?? false) === 1 || ($experience['is_current'] ?? false) === '1',
            'description' => isset($experience['description']) && trim((string) $experience['description']) !== '' ? trim((string) $experience['description']) : null,
        ];
    }

    return $presentationExperiences;
}

function portfolioPresentationExperienceTypeLabel(mixed $type): string
{
    return match ($type) {
        'employment' => 'Employment',
        'training' => 'Training',
        'internship' => 'Internship',
        'volunteer' => 'Volunteer',
        'leadership' => 'Leadership',
        default => 'Experience',
    };
}

function portfolioPresentationExperienceMonth(mixed $value): ?string
{
    if (!is_string($value) || preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $matches) !== 1) {
        return null;
    }

    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    return $months[(int) $matches[2]] . ' ' . $matches[1];
}

function portfolioPresentationExperienceDateRange(array $experience): string
{
    $start = portfolioPresentationExperienceMonth($experience['start_month'] ?? null);
    if ($start === null) {
        return '';
    }

    if (($experience['is_current'] ?? false) === true) {
        return $start . ' — Present';
    }

    $end = portfolioPresentationExperienceMonth($experience['end_month'] ?? null);
    return $end === null ? $start : $start . ' — ' . $end;
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
        $metrics[] = ['key' => 'projects', 'value' => count($projects), 'label' => 'Projects'];
    }
    if ($skillCount > 0) {
        $metrics[] = ['key' => 'skills', 'value' => $skillCount, 'label' => 'Core Skills'];
    }
    if ($categories !== []) {
        $metrics[] = ['key' => 'categories', 'value' => count($categories), 'label' => 'Focus Areas'];
    }

    return $metrics;
}

function portfolioPresentationSocialIcon(string $label): string
{
    return match ($label) {
        'LinkedIn' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.2 8.2v9.6M6.2 5.1v.1M10.7 17.8v-5.3a2.7 2.7 0 0 1 5.4 0v5.3M10.7 12.9c.4-1.2 1.3-2 2.8-2 1.6 0 2.6 1 2.6 3v3.9"/></svg>',
        'GitHub' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9.2 19.4c-4.1 1.2-4.1-2.1-5.8-2.6M15 19.4v-2.2a2.1 2.1 0 0 0-.6-1.7c2.1-.2 4.3-1 4.3-4.6a3.6 3.6 0 0 0-1-2.6 3.4 3.4 0 0 0-.1-2.6s-.8-.3-2.7 1a9.2 9.2 0 0 0-4.9 0c-1.9-1.3-2.7-1-2.7-1a3.4 3.4 0 0 0-.1 2.6 3.6 3.6 0 0 0-1 2.6c0 3.6 2.2 4.4 4.3 4.6a2.1 2.1 0 0 0-.6 1.7v2.2"/></svg>',
        'Instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="4" width="16" height="16" rx="4"/><circle cx="12" cy="12" r="3.5"/><path d="M17.4 6.6h.1"/></svg>',
        'Facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M13.5 20v-7h2.7l.4-3h-3.1V8.1c0-.9.3-1.5 1.6-1.5h1.7V4a22 22 0 0 0-2.5-.1c-2.5 0-4.2 1.5-4.2 4.3V10H7.3v3h2.8v7"/></svg>',
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
 *     contact_sent?: bool,
 *     contact_values?: array{name?: string, email?: string, message?: string},
 *     contact_field_errors?: array<string, string>,
 *     contact_form_error?: string,
 *     experiences?: list<array<string, mixed>>
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
    $heroHeadline = portfolioPresentationHeroHeadline($profile);
    $location = portfolioPresentationValue($profile, 'location');
    $aboutMe = portfolioPresentationValue($profile, 'about_me');
    $workDescription = portfolioPresentationValue($profile, 'work_description');
    $heroSummary = $workDescription;
    $aboutNarrative = $aboutMe;
    $email = portfolioPresentationValue($profile, 'email');
    $emailAction = filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? 'mailto:' . str_replace('%40', '@', rawurlencode($email)) : '';
    $phone = portfolioPresentationValue($profile, 'phone_primary');
    $phoneAction = portfolioPresentationPhoneAction($phone);
    $socialLinks = portfolioPresentationSocialLinks($profile);
    $heroSocialActions = portfolioPresentationHeroSocialActions($profile, $emailAction);
    $metrics = portfolioPresentationMetrics($projects, $skills);
    $featuredProjects = portfolioPresentationFeaturedProjects($projects);
    // Keep the public Experience data in a safe, presentation-ready shape so
    // the renderer does not need a second public query.
    $presentationExperiences = portfolioPresentationExperiences(
        isset($options['experiences']) && is_array($options['experiences']) ? $options['experiences'] : [],
    );
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
    $contactValues = isset($options['contact_values']) && is_array($options['contact_values']) ? $options['contact_values'] : [];
    $contactFieldErrors = isset($options['contact_field_errors']) && is_array($options['contact_field_errors']) ? $options['contact_field_errors'] : [];
    $contactFormError = isset($options['contact_form_error']) && is_string($options['contact_form_error']) ? $options['contact_form_error'] : '';
    $contactName = isset($contactValues['name']) && is_string($contactValues['name']) ? $contactValues['name'] : '';
    $contactEmail = isset($contactValues['email']) && is_string($contactValues['email']) ? $contactValues['email'] : '';
    $contactMessage = isset($contactValues['message']) && is_string($contactValues['message']) ? $contactValues['message'] : '';
    $contactNameError = isset($contactFieldErrors['name']) && is_string($contactFieldErrors['name']) ? $contactFieldErrors['name'] : '';
    $contactEmailError = isset($contactFieldErrors['email']) && is_string($contactFieldErrors['email']) ? $contactFieldErrors['email'] : '';
    $contactMessageError = isset($contactFieldErrors['message']) && is_string($contactFieldErrors['message']) ? $contactFieldErrors['message'] : '';
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
        <a class="portfolio-brand" href="#top" aria-label="ATHER, home">
            <img class="portfolio-brand-logo" src="/assets/images/ather-navbar-logo.png" width="880" height="155" alt="">
        </a>
        <nav class="portfolio-nav-links" aria-label="Portfolio sections">
            <a class="is-current" href="#top" data-portfolio-section="top" aria-current="page">Home</a>
            <?php if ($showAbout): ?><a href="#about" data-portfolio-section="about">About</a><?php endif; ?>
            <?php if ($showProjects): ?><a href="#projects" data-portfolio-section="projects">Projects</a><?php endif; ?>
            <a href="#experience" data-portfolio-section="experience">Experience</a>
            <?php if ($showSkills): ?><a href="#skills" data-portfolio-section="skills">Skills</a><?php endif; ?>
            <span class="portfolio-nav-placeholder" aria-disabled="true" title="Coming soon">Insights</span>
            <a href="#contact" data-portfolio-section="contact">Contact</a>
        </nav>
        <div class="portfolio-header-actions">
            <a class="portfolio-header-cta" href="#contact"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3.5 6.5A2.5 2.5 0 0 1 6 4h12a2.5 2.5 0 0 1 2.5 2.5v11A2.5 2.5 0 0 1 18 20H6a2.5 2.5 0 0 1-2.5-2.5v-11Z"/><path d="m4.5 6 6.07 5.06a2.23 2.23 0 0 0 2.86 0L19.5 6"/></svg><span>Let’s Connect</span></a>
            <button class="portfolio-menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="portfolio-mobile-nav"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
        </div>
    </div>
</header>

<div class="portfolio-mobile-nav-layer" aria-hidden="true" inert>
    <button class="portfolio-mobile-nav-backdrop" type="button" aria-label="Close navigation" tabindex="-1"></button>
    <aside class="portfolio-mobile-nav-panel" aria-label="Portfolio mobile navigation">
        <div class="portfolio-mobile-nav-panel-heading"><span>Navigation</span><button class="portfolio-mobile-nav-close" type="button" aria-label="Close navigation"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
        <nav class="portfolio-mobile-nav-links" id="portfolio-mobile-nav" aria-label="Portfolio mobile sections">
            <a class="is-current" href="#top" data-portfolio-section="top" aria-current="page">Home</a>
            <?php if ($showAbout): ?><a href="#about" data-portfolio-section="about">About</a><?php endif; ?>
            <?php if ($showProjects): ?><a href="#projects" data-portfolio-section="projects">Projects</a><?php endif; ?>
            <a href="#experience" data-portfolio-section="experience">Experience</a>
            <?php if ($showSkills): ?><a href="#skills" data-portfolio-section="skills">Skills</a><?php endif; ?>
            <span class="portfolio-mobile-nav-placeholder" aria-disabled="true">Insights <small>Coming soon</small></span>
            <a href="#contact" data-portfolio-section="contact">Contact</a>
        </nav>
    </aside>
</div>

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
                <h1 id="portfolio-title"><?php echo portfolioPresentationEscape($heroHeadline); ?></h1>
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
                        <?php if ($location !== ''): ?><p class="portfolio-hero-profile-location"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20s6-5.1 6-11a6 6 0 1 0-12 0c0 5.9 6 11 6 11Z"/><circle cx="12" cy="9" r="2"/></svg><?php echo portfolioPresentationEscape($location); ?></p><?php endif; ?>
                        <?php if ($phone !== '' || $email !== ''): ?>
                            <div class="portfolio-hero-profile-contact">
                                <?php foreach ([['Phone', $phone, $phoneAction], ['Email', $email, $emailAction]] as [$label, $value, $action]): ?>
                                    <?php if ($value === '') continue; ?>
                                    <div class="portfolio-hero-profile-contact-row">
                                        <?php if ($label === 'Email'): ?><?php echo portfolioPresentationSocialIcon('Email'); ?><?php else: ?><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3h4l2 5-3 2a15 15 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2C10 21 3 14 3 5a2 2 0 0 1 2-2Z"/></svg><?php endif; ?>
                                        <div><small><?php echo $label; ?></small><?php if ($action !== ''): ?><a dir="ltr" href="<?php echo portfolioPresentationEscape($action); ?>" aria-label="<?php echo portfolioPresentationEscape($label . ': ' . $value); ?>"><?php echo portfolioPresentationEscape($value); ?></a><?php else: ?><span dir="ltr"><?php echo portfolioPresentationEscape($value); ?></span><?php endif; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <?php if ($metrics !== []): ?>
        <section class="portfolio-metrics" aria-label="Portfolio metrics">
            <div class="portfolio-container">
                <ul class="portfolio-metrics-strip" style="--portfolio-metric-count: <?php echo count($metrics); ?>; --portfolio-metric-compact-count: <?php echo min(2, count($metrics)); ?>">
                    <?php foreach ($metrics as $metric): ?>
                        <li class="portfolio-metric"><span class="portfolio-metric-icon"><?php echo portfolioPresentationMetricIcon($metric['key']); ?></span><span><strong><?php echo $metric['value']; ?></strong><small><?php echo portfolioPresentationEscape($metric['label']); ?></small></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($showAbout): ?>
        <section class="portfolio-section portfolio-about" id="about" aria-labelledby="about-title"><div class="portfolio-container"><div class="portfolio-about-card">
            <div class="portfolio-about-heading"><p class="portfolio-section-kicker">ABOUT</p><h2 id="about-title">Professional Overview</h2></div>
            <div class="portfolio-about-copy"><p><?php echo nl2br(portfolioPresentationEscape($aboutNarrative)); ?></p></div>
        </div></div></section>
    <?php endif; ?>

    <?php if ($showProjects || $showSkills): ?>
        <div class="portfolio-section portfolio-work"><div class="portfolio-container portfolio-work-content">
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
                                <?php if ($hasProjectImage): ?>
                                    <div class="portfolio-project-visual">
                                        <img src="<?php echo portfolioPresentationEscape($projectImageUrl); ?>" alt="<?php echo portfolioPresentationEscape($projectTitle); ?> project preview" loading="lazy">
                                    </div>
                                <?php endif; ?>
                                <div class="portfolio-project-body">
                                    <?php if (!$hasProjectImage || $category !== ''): ?><div class="portfolio-project-identity"><?php if (!$hasProjectImage): ?><span class="portfolio-project-fallback" aria-hidden="true"><?php echo portfolioPresentationProjectFallbackIcon(); ?></span><?php endif; ?><?php if ($category !== ''): ?><p class="portfolio-project-category"><?php echo portfolioPresentationEscape($category); ?></p><?php endif; ?></div><?php endif; ?>
                                    <h3><?php echo portfolioPresentationEscape($projectTitle); ?></h3>
                                    <?php if (isset($project['description']) && trim((string) $project['description']) !== ''): ?><p class="portfolio-project-description"><?php echo nl2br(portfolioPresentationEscape($project['description'])); ?></p><?php endif; ?>
                                    <?php if ($projectTechnologies !== []): ?><ul class="portfolio-project-technologies" aria-label="Technologies used"><?php foreach ($projectTechnologies as $technology): ?><li><?php echo portfolioPresentationEscape($technology); ?></li><?php endforeach; ?></ul><?php endif; ?>
                                    <?php if ($projectLink !== null): ?><a class="portfolio-project-link" href="<?php echo portfolioPresentationEscape($projectLink); ?>" rel="noopener noreferrer" aria-label="View <?php echo portfolioPresentationEscape($projectTitle); ?> on GitHub">View on GitHub <?php echo portfolioPresentationSocialIcon('GitHub'); ?></a><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($showSkills): ?>
                <section class="portfolio-skills-region" id="skills" aria-labelledby="portfolio-skills-heading">
                    <header class="portfolio-section-heading portfolio-skills-heading"><h2 id="portfolio-skills-heading">Skills &amp; Technologies</h2></header>
                    <div class="portfolio-skills-panel">
                        <ul class="portfolio-skills-list"><?php foreach ($skills as $skill): $skillName = isset($skill['skill_name']) ? trim((string) $skill['skill_name']) : ''; ?><?php if ($skillName !== ''): ?><li><button class="portfolio-skill-control" type="button" aria-pressed="false"><?php echo portfolioPresentationEscape($skillName); ?></button></li><?php endif; ?><?php endforeach; ?></ul>
                    </div>
                </section>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>

    <div class="portfolio-closing-region"><div class="portfolio-container portfolio-closing-layout<?php echo $presentationExperiences === [] ? ' portfolio-closing-layout--contact-only' : ''; ?>">
        <?php if ($presentationExperiences !== []): ?>
            <section class="portfolio-section portfolio-closing-panel portfolio-experience" id="experience" aria-labelledby="experience-title">
                <header class="portfolio-section-heading"><h2 id="experience-title">Experience</h2></header>
                <ol class="portfolio-experience-list">
                    <?php foreach ($presentationExperiences as $experience):
                        $experienceType = portfolioPresentationExperienceTypeLabel($experience['experience_type']);
                        $experienceRole = $experience['role_title'];
                        $experienceOrganization = $experience['organization'];
                        $experienceLocation = $experience['location'] ?? null;
                        $experienceDateRange = portfolioPresentationExperienceDateRange($experience);
                        $experienceIsCurrent = $experience['is_current'] === true;
                        $experienceDescription = $experience['description'] ?? null;
                        ?>
                        <li class="portfolio-experience-item<?php echo $experienceIsCurrent ? ' portfolio-experience-item--current' : ''; ?>">
                            <article class="portfolio-experience-content">
                                <div class="portfolio-experience-topline"><p class="portfolio-experience-type"><?php echo portfolioPresentationEscape($experienceType); ?></p><?php if ($experienceDateRange !== ''): ?><p class="portfolio-experience-date"><?php echo portfolioPresentationEscape($experienceDateRange); ?></p><?php endif; ?></div>
                                <?php if ($experienceRole !== ''): ?><h3><?php echo portfolioPresentationEscape($experienceRole); ?></h3><?php endif; ?>
                                <?php if ($experienceOrganization !== '' || $experienceLocation !== null): ?><p class="portfolio-experience-organization"><?php if ($experienceOrganization !== ''): ?><span class="portfolio-experience-organization-name"><?php echo portfolioPresentationEscape($experienceOrganization); ?><?php if ($experienceLocation !== null): ?><span aria-hidden="true">&nbsp;·</span><?php endif; ?></span><?php endif; ?><?php if ($experienceLocation !== null): ?><span><?php echo portfolioPresentationEscape($experienceLocation); ?></span><?php endif; ?></p><?php endif; ?>
                                <?php if ($experienceDescription !== null): ?><p class="portfolio-experience-description"><?php echo nl2br(portfolioPresentationEscape($experienceDescription)); ?></p><?php endif; ?>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>

        <section class="portfolio-section portfolio-closing-panel portfolio-contact" id="contact" aria-labelledby="contact-title">
            <div class="portfolio-contact-copy"><p class="portfolio-section-kicker">CONTACT</p><h2 id="contact-title">Let’s start a conversation.</h2><p>Have a project, role, or collaboration opportunity in mind? Send a message through this Portfolio.</p>
                <?php if ($socialLinks !== []): ?><div class="portfolio-contact-social"><p class="portfolio-contact-social-label">Connect</p><ul class="portfolio-social-list" aria-label="Connect with <?php echo portfolioPresentationEscape($name); ?>"><?php foreach ($socialLinks as $link): ?><li><a href="<?php echo portfolioPresentationEscape($link['url']); ?>" aria-label="<?php echo portfolioPresentationEscape($link['label'] . ' for ' . $name); ?>" rel="noopener noreferrer"><?php echo portfolioPresentationSocialIcon($link['label']); ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
            </div>
            <div class="portfolio-contact-card">
                <h3 id="portfolio-contact-form-title">Send a message</h3>
                <?php if (($options['contact_sent'] ?? false) === true): ?><p class="portfolio-form-status" role="status">Message submitted successfully.</p><?php endif; ?>
                <?php if ($preview): ?><p class="portfolio-preview-note portfolio-preview-note--contact" role="status"><?php echo portfolioPresentationSocialIcon('Email'); ?><span>The contact form is inactive in private preview.</span></p><?php endif; ?>
                <?php if ($contactFormError !== ''): ?><p class="portfolio-contact-form-error" role="alert"><?php echo portfolioPresentationEscape($contactFormError); ?></p><?php endif; ?>
                <form method="post"<?php if ($contactAction !== ''): ?> action="<?php echo portfolioPresentationEscape($contactAction); ?>#contact"<?php endif; ?>>
                    <div class="portfolio-contact-field<?php echo $contactNameError !== '' ? ' portfolio-contact-field--invalid' : ''; ?>"><label for="portfolio-contact-name">Name</label><input id="portfolio-contact-name" type="text" name="name" maxlength="100" required autocomplete="name" value="<?php echo portfolioPresentationEscape($contactName); ?>"<?php echo $contactNameError !== '' ? ' aria-invalid="true" aria-describedby="portfolio-contact-name-error"' : ''; ?><?php echo $preview ? ' disabled' : ''; ?>><?php if ($contactNameError !== ''): ?><p class="portfolio-contact-field-error" id="portfolio-contact-name-error"><?php echo portfolioPresentationEscape($contactNameError); ?></p><?php endif; ?></div>
                    <div class="portfolio-contact-field<?php echo $contactEmailError !== '' ? ' portfolio-contact-field--invalid' : ''; ?>"><label for="portfolio-contact-email">Email</label><input id="portfolio-contact-email" type="email" name="email" maxlength="255" required autocomplete="email" value="<?php echo portfolioPresentationEscape($contactEmail); ?>"<?php echo $contactEmailError !== '' ? ' aria-invalid="true" aria-describedby="portfolio-contact-email-error"' : ''; ?><?php echo $preview ? ' disabled' : ''; ?>><?php if ($contactEmailError !== ''): ?><p class="portfolio-contact-field-error" id="portfolio-contact-email-error"><?php echo portfolioPresentationEscape($contactEmailError); ?></p><?php endif; ?></div>
                    <div class="portfolio-contact-field portfolio-contact-field--message<?php echo $contactMessageError !== '' ? ' portfolio-contact-field--invalid' : ''; ?>"><label for="portfolio-contact-message">Message</label><textarea id="portfolio-contact-message" name="message" maxlength="5000" required<?php echo $contactMessageError !== '' ? ' aria-invalid="true" aria-describedby="portfolio-contact-message-error"' : ''; ?><?php echo $preview ? ' disabled' : ''; ?>><?php echo portfolioPresentationEscape($contactMessage); ?></textarea><?php if ($contactMessageError !== ''): ?><p class="portfolio-contact-field-error" id="portfolio-contact-message-error"><?php echo portfolioPresentationEscape($contactMessageError); ?></p><?php endif; ?></div>
                    <button type="submit"<?php echo $preview ? ' disabled aria-disabled="true"' : ''; ?>><span>Send message</span><?php echo portfolioPresentationSocialIcon('Email'); ?></button>
                </form>
            </div>
        </section>
    </div></div>
</main>

<footer class="portfolio-footer">
    <div class="portfolio-container portfolio-footer-main">
        <section class="portfolio-footer-identity" aria-labelledby="portfolio-footer-identity-heading">
            <div class="portfolio-footer-identity-heading">
                <span class="portfolio-footer-mark" aria-hidden="true"><?php echo portfolioPresentationEscape($initials); ?></span>
                <div><strong id="portfolio-footer-identity-heading"><?php echo portfolioPresentationEscape($name); ?></strong><?php if ($title !== ''): ?><span><?php echo portfolioPresentationEscape($title); ?></span><?php endif; ?></div>
            </div>
            <?php if ($heroSummary !== ''): ?><p class="portfolio-footer-summary"><?php echo nl2br(portfolioPresentationEscape($heroSummary)); ?></p><?php endif; ?>
            <?php if ($socialLinks !== []): ?>
                <ul class="portfolio-footer-social" aria-label="<?php echo portfolioPresentationEscape($name); ?> social links">
                    <?php foreach ($socialLinks as $link): ?><li><a href="<?php echo portfolioPresentationEscape($link['url']); ?>" aria-label="<?php echo portfolioPresentationEscape($link['label'] . ' profile for ' . $name); ?>" rel="noopener noreferrer"><?php echo portfolioPresentationSocialIcon($link['label']); ?></a></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <nav class="portfolio-footer-group portfolio-footer-nav" aria-labelledby="portfolio-footer-quick-links-heading">
            <h2 id="portfolio-footer-quick-links-heading">Quick Links</h2>
            <ul><li><a href="#top">Home</a></li><?php if ($showAbout): ?><li><a href="#about">About</a></li><?php endif; ?><?php if ($showProjects): ?><li><a href="#projects">Projects</a></li><?php endif; ?><?php if ($presentationExperiences !== []): ?><li><a href="#experience">Experience</a></li><?php endif; ?><?php if ($showSkills): ?><li><a href="#skills">Skills</a></li><?php endif; ?><li><a href="#contact">Contact</a></li></ul>
        </nav>

        <section class="portfolio-footer-group portfolio-footer-resources" aria-labelledby="portfolio-footer-resources-heading">
            <h2 id="portfolio-footer-resources-heading">Resources</h2>
            <ul>
                <li><span aria-disabled="true">Resume</span></li>
                <li><span aria-disabled="true">Certifications</span></li>
                <li><span aria-disabled="true">Insights</span></li>
                <li><span aria-disabled="true">Case Studies</span></li>
            </ul>
            <p>Planned resources</p>
        </section>

        <section class="portfolio-footer-group portfolio-footer-connect" aria-labelledby="portfolio-footer-connect-heading">
            <h2 id="portfolio-footer-connect-heading">Connect</h2>
            <ul>
                <?php if ($location !== ''): ?><li class="portfolio-footer-location"><span>Location</span><strong><?php echo portfolioPresentationEscape($location); ?></strong></li><?php endif; ?>
                <li><a href="#contact">Send a message <span aria-hidden="true">→</span></a></li>
                <?php foreach ($socialLinks as $link): ?><li><a href="<?php echo portfolioPresentationEscape($link['url']); ?>" rel="noopener noreferrer"><?php echo portfolioPresentationEscape($link['label']); ?> <span aria-hidden="true">↗</span></a></li><?php endforeach; ?>
            </ul>
        </section>

        <section class="portfolio-footer-group portfolio-footer-updates" aria-labelledby="portfolio-footer-updates-heading">
            <h2 id="portfolio-footer-updates-heading">Stay Updated</h2>
            <p>Portfolio updates are not available yet.</p>
            <div class="portfolio-footer-subscribe">
                <input type="email" placeholder="Email address" aria-label="Email address" aria-describedby="portfolio-footer-subscribe-status" disabled>
                <button type="button" aria-describedby="portfolio-footer-subscribe-status" disabled>Subscribe</button>
            </div>
            <small id="portfolio-footer-subscribe-status">Coming soon</small>
        </section>
    </div>
    <div class="portfolio-container portfolio-footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> <?php echo portfolioPresentationEscape($name); ?>. All rights reserved.</p>
        <a href="#top">Back to top <span aria-hidden="true">↑</span></a>
    </div>
</footer>
</body>
</html>
<?php
}
