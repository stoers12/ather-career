<?php

declare(strict_types=1);

require_once __DIR__ . '/portfolio_presentation.php';

/** @param array<string, mixed> $page Exactly projectCaseStudyView(), B-05/B-08. */
function renderPublicProjectPage(array $page): void
{
    $e = static fn (mixed $value): string => portfolioPresentationEscape($value);
    $project = $page['project'];
    $title = $project['title'] !== '' ? $project['title'] : portfolioCopy('project');
    $owner = $page['owner']['name'] !== '' ? $page['owner']['name'] : portfolioCopy('portfolio');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $page['preview'] ? 'noindex,nofollow' : 'index,follow'; ?>">
    <meta name="color-scheme" content="light dark">
    <title><?php echo $e($title); ?> — <?php echo $e($owner); ?></title>
    <link rel="icon" type="image/png" href="/assets/images/ather-navbar-logo.png">
    <?php if (!$page['preview'] && $page['canonical_url'] !== null): ?><link rel="canonical" href="<?php echo $e($page['canonical_url']); ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?php echo $e(versionedAssetUrl('assets/css/portfolio.css')); ?>">
    <script src="<?php echo $e(versionedAssetUrl('assets/js/portfolio.js')); ?>" defer></script>
</head>
<body class="portfolio-page portfolio-case-page<?php echo $page['preview'] ? ' portfolio-preview-mode' : ''; ?>">
<a class="portfolio-skip-link" href="#portfolio-main"><?php echo portfolioCopy('skip'); ?></a>
<?php if ($page['preview']) { renderPortfolioPreviewBar($page['portfolio_published']); } ?>
<header class="portfolio-header">
    <div class="portfolio-header-inner portfolio-case-header">
        <a class="portfolio-brand" href="<?php echo $e($page['portfolio_url']); ?>" aria-label="<?php echo $e($owner); ?>"><?php renderPortfolioWordmark('portfolio-brand-logo'); ?></a>
        <div class="portfolio-case-owner"><bdi dir="auto"><?php echo $e($owner); ?></bdi><?php if ($page['owner']['professional_title'] !== ''): ?><span dir="auto"><?php echo $e($page['owner']['professional_title']); ?></span><?php endif; ?></div>
        <div class="portfolio-header-actions"><?php renderPortfolioThemeControl(); ?><a class="portfolio-header-cta" href="<?php echo $e($page['portfolio_url']); ?>#contact"><?php echo portfolioCopy('touch'); ?> <?php echo portfolioPresentationActionIcon('arrow'); ?></a></div>
    </div>
</header>
<main id="portfolio-main" class="portfolio-case">
    <p><a class="portfolio-case-back" href="<?php echo $e($page['portfolio_url']); ?>#projects"><?php echo portfolioPresentationActionIcon('arrow'); ?><?php echo portfolioCopy('allProjects'); ?></a></p>
    <?php if ($page['preview'] && $page['draft']): ?><p class="portfolio-preview-note portfolio-draft-note" role="note"><?php echo portfolioCopy('draft'); ?></p><?php endif; ?>
    <?php if ($page['preview'] && !$page['portfolio_published']): ?><p class="portfolio-preview-note" role="note"><?php echo portfolioCopy('privatePortfolio'); ?></p><?php endif; ?>
    <article>
        <?php if ($project['image_url'] !== null): ?><div class="portfolio-case-cover"><img src="<?php echo $e($project['image_url']); ?>" alt="<?php echo $e(sprintf(portfolioCopy('projectPreview'), $title)); ?>"></div><?php else: renderPortfolioNeutralCover('portfolio-case-cover', $title); endif; ?>
        <?php if ($project['category'] !== ''): ?><p class="portfolio-project-category" dir="auto"><?php echo $e($project['category']); ?></p><?php endif; ?>
        <h1 id="portfolio-title" dir="auto"><?php echo $e($title); ?></h1>
        <?php if ($project['description'] !== ''): ?><p class="portfolio-case-copy" dir="auto"><?php echo nl2br($e($project['description'])); ?></p><?php endif; ?>
        <?php if ($project['technologies'] !== []): ?><ul class="portfolio-project-technologies" aria-label="<?php echo portfolioCopy('technologies'); ?>"><?php foreach ($project['technologies'] as $technology): ?><li><bdi dir="auto"><?php echo $e($technology); ?></bdi></li><?php endforeach; ?></ul><?php endif; ?>
        <section class="portfolio-case-section" aria-labelledby="case-explore">
            <h2 id="case-explore"><?php echo portfolioCopy('exploreWork'); ?></h2>
            <div class="portfolio-case-actions">
                <?php if ($project['live_url'] !== null): ?><a class="portfolio-button portfolio-button-primary" href="<?php echo $e($project['live_url']); ?>" rel="noopener noreferrer"><?php echo portfolioCopy('live'); ?> <?php echo portfolioPresentationActionIcon('arrow'); ?></a><?php endif; ?>
                <?php if ($project['github_url'] !== null): ?><a class="portfolio-button portfolio-button-secondary" href="<?php echo $e($project['github_url']); ?>" rel="noopener noreferrer"><?php echo portfolioCopy('sourceCode'); ?> <?php echo portfolioPresentationSocialIcon('GitHub'); ?></a><?php endif; ?>
                <?php if ($project['live_url'] === null && $project['github_url'] === null): ?><p><?php echo portfolioCopy('noLinks'); ?></p><?php endif; ?>
            </div>
        </section>
    </article>
</main>
<footer class="portfolio-footer">
    <div class="portfolio-container portfolio-case-footer"><a href="<?php echo $e($page['portfolio_url']); ?>" aria-label="<?php echo $e($owner); ?>"><?php renderPortfolioWordmark('portfolio-footer-logo'); ?></a><p><bdi dir="auto"><?php echo $e($owner); ?></bdi><?php if ($page['owner']['professional_title'] !== ''): ?> · <bdi dir="auto"><?php echo $e($page['owner']['professional_title']); ?></bdi><?php endif; ?></p></div>
</footer>
</body>
</html>
    <?php
}
