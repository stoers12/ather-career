<?php

declare(strict_types=1);

require_once __DIR__ . '/portfolio_presentation.php';

/**
 * Case study page template (frontend). Minimal working baseline from the backend
 * PR: the frontend slice FE-08 replaces this markup with the reference design.
 * It receives exactly projectCaseStudyView() (docs/contracts/public-project-page.md).
 * Visitors always see English.
 *
 * @param array<string, mixed> $page
 */
function renderPublicProjectPage(array $page): void
{
    $e = static fn (mixed $value): string => portfolioPresentationEscape($value);
    $project = $page['project'];
    $title = $project['title'] !== '' ? $project['title'] : 'Project';
    $owner = $page['owner']['name'] !== '' ? $page['owner']['name'] : ($page['preview'] ? 'Your Portfolio' : 'Portfolio');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $page['preview'] ? 'noindex,nofollow' : 'index,follow'; ?>">
    <meta name="color-scheme" content="dark">
    <title><?php echo $e($title); ?> — <?php echo $e($owner); ?></title>
    <link rel="icon" type="image/png" href="/assets/images/ather-navbar-logo.png">
    <?php if ($page['canonical_url'] !== null): ?><link rel="canonical" href="<?php echo $e($page['canonical_url']); ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?php echo $e(versionedAssetUrl('assets/css/portfolio.css')); ?>">
</head>
<body class="portfolio-page portfolio-case-page<?php echo $page['preview'] ? ' portfolio-preview-mode' : ''; ?>">
<a class="portfolio-skip-link" href="#portfolio-main">Skip to content</a>
<main id="portfolio-main" class="portfolio-case">
    <p><a href="<?php echo $e($page['portfolio_url']); ?>#projects">All projects</a></p>
    <?php if ($page['draft']): ?><p role="note">Draft: visitors cannot see this project until you publish it.</p><?php endif; ?>
    <article>
        <?php if ($project['image_url'] !== null): ?><img src="<?php echo $e($project['image_url']); ?>" alt="<?php echo $e($title); ?> project preview"><?php endif; ?>
        <?php if ($project['category'] !== ''): ?><p><?php echo $e($project['category']); ?></p><?php endif; ?>
        <h1><?php echo $e($title); ?></h1>
        <?php if ($project['description'] !== ''): ?><p><?php echo nl2br($e($project['description'])); ?></p><?php endif; ?>
        <?php if ($project['technologies'] !== []): ?><ul aria-label="Technologies used"><?php foreach ($project['technologies'] as $technology): ?><li><?php echo $e($technology); ?></li><?php endforeach; ?></ul><?php endif; ?>
        <section aria-labelledby="case-explore">
            <h2 id="case-explore">Explore the work</h2>
            <?php if ($project['live_url'] !== null): ?><a href="<?php echo $e($project['live_url']); ?>" rel="noopener noreferrer">View live project</a><?php endif; ?>
            <?php if ($project['github_url'] !== null): ?><a href="<?php echo $e($project['github_url']); ?>" rel="noopener noreferrer">Source code</a><?php endif; ?>
            <?php if ($project['live_url'] === null && $project['github_url'] === null): ?><p>No public link has been added for this project.</p><?php endif; ?>
        </section>
    </article>
    <p><?php echo $e($owner); ?><?php if ($page['owner']['professional_title'] !== ''): ?> · <?php echo $e($page['owner']['professional_title']); ?><?php endif; ?></p>
</main>
</body>
</html>
    <?php
}
