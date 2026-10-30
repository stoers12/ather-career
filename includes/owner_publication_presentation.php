<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_layout.php';
require_once __DIR__ . '/public_url.php';
require_once __DIR__ . '/owner_publication_copy.php';

/** @param array{public_slug: string|null, is_published: int, published_at: string|null} $state */
function ownerPublicationStateSlug(array $state): ?string
{
    $candidate = $state['public_slug'] ?? null;
    $slug = normalizePublicSlug($candidate);

    if (!is_string($candidate) || $slug === null || $slug !== $candidate || in_array($slug, PUBLIC_SLUG_RESERVED, true)) {
        return null;
    }

    return $slug;
}

/** @param array{public_slug: string|null, is_published: int, published_at: string|null} $state */
function ownerPublicationViewState(array $state): string
{
    if (ownerPublicationStateSlug($state) === null) {
        return 'no_slug';
    }
    if ($state['is_published'] === 1) {
        return 'published';
    }

    return $state['published_at'] === null ? 'reserved' : 'offline';
}

/** @param array{public_slug: string|null, is_published: int, published_at: string|null} $state */
function ownerPublicationPublicUrl(array $state): ?string
{
    $slug = ownerPublicationStateSlug($state);

    return $slug === null ? null : publicPortfolioUrl($slug);
}

function ownerPublicationUrlDisplay(?string $publicUrl, bool $allowCopy = false): void
{
    if ($publicUrl === null) {
        return;
    }
    $copy = static fn (string $key): string => ownerEscapeHtml(ownerPublicationCopy($key, (string) ($GLOBALS['owner_workspace_locale'] ?? 'en')));
    ?>
    <div class="publication-url-block">
        <p class="publication-url-label"><?php echo $copy('url'); ?></p>
        <div class="publication-url-row"><p class="publication-public-url" id="publication-public-url" dir="ltr"><code><?php echo ownerEscapeHtml($publicUrl); ?></code></p><?php if ($allowCopy): ?><button class="button-secondary publication-copy-link" type="button" hidden data-copy-public-url="publication-public-url" data-copy-success="<?php echo $copy('copied'); ?>" data-copy-failure="<?php echo $copy('copy_failed'); ?>" aria-describedby="publication-copy-feedback"><?php echo evidenceHubOwnerIcon('copy'); ?><?php echo $copy('copy'); ?></button><?php endif; ?></div>
        <?php if ($allowCopy): ?><p class="publication-copy-feedback" id="publication-copy-feedback" role="status" aria-live="polite" aria-atomic="true"></p><?php endif; ?>
    </div>
    <?php
}

/** @param array{public_slug: string|null, is_published: int, published_at: string|null} $state
 * @param array<string, string> $fieldErrors */
function renderOwnerPublicationPresentation(array $state, ?string $publicUrl, array $fieldErrors = [], ?string $submittedSlug = null): void
{
    $viewState = ownerPublicationViewState($state);
    $slug = ownerPublicationStateSlug($state);
    $copy = static fn (string $key): string => ownerEscapeHtml(ownerPublicationCopy($key, (string) ($GLOBALS['owner_workspace_locale'] ?? 'en')));
    ?>
    <section class="publication-card" data-publication-state="<?php echo $viewState; ?>" aria-labelledby="publication-status-title">
        <div class="publication-status publication-status--<?php echo $viewState; ?>" role="status"><?php echo evidenceHubOwnerIcon($viewState === 'published' ? 'circle-check' : 'owner-send'); ?><div><h2 id="publication-status-title"><?php echo $copy($viewState . '_title'); ?></h2><p><?php echo $copy($viewState . '_hint'); ?></p></div></div>
        <?php ownerPublicationUrlDisplay($publicUrl, $viewState === 'published'); ?>
        <?php if ($viewState === 'no_slug' || $viewState === 'reserved'): ?>
        <form class="publication-slug-form" method="POST" action="owner_publication.php" data-owner-form>
            <input type="hidden" name="action" value="set_slug"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
            <div class="form-field"><label for="public_slug"><?php echo $copy('slug'); ?> <em><?php echo $copy('required'); ?></em><span class="visually-hidden"><?php ownerRenderRequiredIndicator(); ?></span></label><input id="public_slug" type="text" name="public_slug" dir="ltr" value="<?php echo ownerEscapeHtml($submittedSlug ?? (string) $slug); ?>" minlength="3" maxlength="64" pattern="[a-z0-9]+(-[a-z0-9]+)*" required<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'public_slug', 'public-slug-help'); ?>><small class="form-hint" id="public-slug-help"><?php echo $copy('slug_hint'); ?></small><?php ownerRenderFieldError($fieldErrors, 'public_slug'); ?></div>
            <button class="<?php echo $viewState === 'no_slug' ? 'button-primary' : 'button-secondary'; ?>" type="submit" data-pending-label="<?php echo $copy('saving_slug'); ?>"><?php echo $copy($viewState === 'reserved' ? 'update_slug' : 'save_slug'); ?></button>
        </form>
        <?php endif; ?>
        <div class="publication-actions"><a class="button-primary" href="/owner_preview.php"><?php echo evidenceHubOwnerIcon('eye'); ?><?php echo $copy('preview'); ?></a><a class="button-secondary" href="/owner_projects.php"><?php echo $copy('projects'); ?></a>
            <?php if ($viewState === 'published' && $publicUrl !== null): ?><a class="button-link" href="<?php echo ownerEscapeHtml($publicUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo $copy('view'); ?></a><?php endif; ?>
        </div>
        <p class="publication-private-note"><?php echo evidenceHubOwnerIcon('lock-keyhole'); ?><?php echo $copy('private'); ?></p>
        <?php if ($viewState === 'reserved' || $viewState === 'offline'): ?>
        <form class="publication-primary-action" method="POST" action="owner_publication.php" data-owner-form data-confirm="<?php echo $copy('publish_confirm'); ?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><button class="button-primary" type="submit" data-pending-label="<?php echo $copy('publishing'); ?>"><?php echo $copy('publish'); ?></button></form>
        <?php elseif ($viewState === 'published'): ?>
        <div class="publication-stop"><div><h3><?php echo $copy('stop_title'); ?></h3><p><?php echo $copy('stop_hint'); ?></p></div><form class="publication-destructive-action" method="POST" action="owner_publication.php" data-owner-form data-confirm="<?php echo $copy('unpublish_confirm'); ?>" data-confirm-title="<?php echo $copy('unpublish_title'); ?>" data-confirm-action="<?php echo $copy('unpublish'); ?>"><input type="hidden" name="action" value="unpublish"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><button class="button-danger" type="submit" data-pending-label="<?php echo $copy('unpublishing'); ?>"><?php echo $copy('unpublish'); ?></button></form></div>
        <?php endif; ?>
    </section>
    <?php
}

/** @param array{public_slug: string|null, is_published: int, published_at: string|null}|null $state
 * @param list<string> $errors @param array<string, string> $fieldErrors */
function renderOwnerPublicationPage(?array $state, ?string $publicUrl, string $message, array $errors, array $fieldErrors, ?string $submittedSlug): void
{
    $copy = static fn (string $key): string => ownerEscapeHtml(ownerPublicationCopy($key, (string) ($GLOBALS['owner_workspace_locale'] ?? 'en')));
    ?>
    <div class="owner-publication"><header class="admin-page-header"><div class="admin-page-header-copy"><h1><?php echo $copy('title'); ?></h1><p class="admin-page-description"><?php echo $copy('subtitle'); ?></p></div></header>
    <?php ownerRenderFormFeedback($message, $errors, $fieldErrors); ?>
    <?php if ($state !== null): renderOwnerPublicationPresentation($state, $publicUrl, $fieldErrors, $submittedSlug); endif; ?>
    </div>
    <?php
}
