<?php

declare(strict_types=1);

require_once __DIR__ . '/portfolio_copy.php';

function renderPortfolioWordmark(string $class): void
{
    ?><span class="<?php echo portfolioPresentationEscape($class); ?> portfolio-wordmark" aria-hidden="true"><span>A</span>THER</span><?php
}

function renderPortfolioThemeControl(): void
{
    ?><button class="portfolio-theme-toggle" type="button" data-portfolio-theme aria-label="<?php echo portfolioCopy('theme'); ?>" data-dark-label="<?php echo portfolioCopy('theme'); ?>" data-light-label="<?php echo portfolioCopy('lightTheme'); ?>" hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 14a8 8 0 0 1-10-10 8 8 0 1 0 10 10Z"/></svg></button><?php
}

/** Two-letter mark from the real project title, letters and digits only; '' when there are none. */
function portfolioProjectInitials(string $title): string
{
    preg_match_all('/[\p{L}\p{N}]+/u', $title, $words);
    $initials = '';
    foreach (array_slice($words[0], 0, 2) as $word) {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return $initials;
}

/**
 * Cover for a project without an image, with the reference's title-initials mark from
 * real data only. The category is not repeated here: the card already shows it once.
 * Decorative, since the card carries the title as text.
 */
function renderPortfolioNeutralCover(string $class, string $title): void
{
    $initials = portfolioProjectInitials($title);
    ?><div class="<?php echo portfolioPresentationEscape($class); ?> portfolio-neutral-cover" aria-hidden="true"><?php if ($initials !== ''): ?><span class="portfolio-cover-mark" dir="ltr"><bdi><?php echo portfolioPresentationEscape($initials); ?></bdi> / ATHER</span><?php endif; ?></div><?php
}

/** The preview's locale never changes the visitor-facing document language. */
function renderPortfolioPreviewBar(?bool $published): void
{
    require_once __DIR__ . '/owner_dashboard_copy.php';
    $locale = ownerLocale();
    $state = $published === null ? 'unavailable' : ($published ? 'published' : 'private');
    ?>
    <aside class="portfolio-preview-bar" lang="<?php echo $locale; ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>" aria-label="<?php echo portfolioCopy('preview', $locale); ?>">
        <div class="portfolio-container portfolio-preview-bar-inner"><span><?php echo portfolioCopy('preview', $locale); ?> · <strong><?php echo portfolioCopy($state, $locale); ?></strong></span><a href="/owner.php"><?php echo portfolioCopy('back', $locale); ?> <?php echo portfolioPresentationActionIcon('arrow'); ?></a></div>
    </aside>
    <?php
}
