<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_layout.php';
require_once __DIR__ . '/public_url.php';

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

function ownerPublicationUrlDisplay(?string $publicUrl): void
{
    if ($publicUrl === null) {
        return;
    }
    ?>
    <div class="publication-url-block">
        <p class="publication-url-label">Public Portfolio URL</p>
        <p class="publication-public-url" id="publication-public-url"><code><?php echo ownerEscapeHtml($publicUrl); ?></code></p>
    </div>
    <?php
}

/** @param array{public_slug: string|null, is_published: int, published_at: string|null} $state */
function renderOwnerPublicationPresentation(array $state, ?string $publicUrl): void
{
    $viewState = ownerPublicationViewState($state);
    $slug = ownerPublicationStateSlug($state);
    ?>
    <section class="publication-card" aria-labelledby="publication-status-title">
        <?php if ($viewState === 'published'): ?>
            <div class="publication-status publication-status--live" role="status"><div><p class="admin-eyebrow">Live</p><h2 id="publication-status-title">Your Portfolio is live</h2><p>Changes saved while published become publicly visible immediately.</p></div></div>
            <?php ownerPublicationUrlDisplay($publicUrl); ?>
            <?php if ($publicUrl !== null): ?>
                <div class="publication-actions">
                    <a class="button-secondary" href="<?php echo ownerEscapeHtml($publicUrl); ?>" target="_blank" rel="noopener noreferrer">View Portfolio</a>
                    <button class="button-secondary publication-copy-link" type="button" hidden data-copy-public-url="publication-public-url" aria-describedby="publication-copy-feedback">Copy Link</button>
                </div>
                <p class="publication-copy-feedback" id="publication-copy-feedback" role="status" aria-live="polite"></p>
            <?php endif; ?>
            <form class="publication-destructive-action" method="POST" action="owner_publication.php" data-confirm="Take this Portfolio offline? Visitors will no longer be able to open its public link until you publish it again." data-confirm-title="Unpublish Portfolio?" data-confirm-action="Unpublish"><input type="hidden" name="action" value="unpublish"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><button class="button-danger" type="submit">Unpublish</button></form>
        <?php elseif ($viewState === 'offline'): ?>
            <div class="publication-status publication-status--offline" role="status"><div><p class="admin-eyebrow">Offline</p><h2 id="publication-status-title">Your public link is offline</h2><p>Your permanent public address is preserved. Publish again to make the Portfolio public.</p></div></div>
            <?php ownerPublicationUrlDisplay($publicUrl); ?>
            <form class="publication-primary-action" method="POST" action="owner_publication.php"><input type="hidden" name="action" value="publish"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><button class="button-primary" type="submit">Publish Portfolio</button></form>
        <?php elseif ($viewState === 'reserved'): ?>
            <div class="publication-status" role="status"><div><p class="admin-eyebrow">Address reserved</p><h2 id="publication-status-title">Your public address is reserved</h2><p>This address is not live until you publish the Portfolio.</p></div></div>
            <?php ownerPublicationUrlDisplay($publicUrl); ?>
            <form class="profile-form publication-slug-form" method="POST" action="owner_publication.php">
                <input type="hidden" name="action" value="set_slug"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                <label class="form-field" for="public_slug"><span>Public slug</span><input id="public_slug" type="text" name="public_slug" value="<?php echo ownerEscapeHtml((string) $slug); ?>" minlength="3" maxlength="64" pattern="[a-z0-9]+(-[a-z0-9]+)*" required></label>
                <div class="form-actions"><button class="button-secondary" type="submit">Update public slug</button></div>
            </form>
            <form class="publication-primary-action" method="POST" action="owner_publication.php"><input type="hidden" name="action" value="publish"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><button class="button-primary" type="submit">Publish Portfolio</button></form>
        <?php else: ?>
            <div class="publication-status" role="status"><div><p class="admin-eyebrow">Draft</p><h2 id="publication-status-title">Choose your public address</h2><p>Reserve a permanent public slug before publishing your Portfolio.</p></div></div>
            <form class="profile-form publication-slug-form" method="POST" action="owner_publication.php">
                <input type="hidden" name="action" value="set_slug"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
                <label class="form-field" for="public_slug"><span>Public slug</span><input id="public_slug" type="text" name="public_slug" value="" minlength="3" maxlength="64" pattern="[a-z0-9]+(-[a-z0-9]+)*" required></label>
                <div class="form-actions"><button class="button-primary" type="submit">Save public slug</button></div>
            </form>
        <?php endif; ?>
    </section>
    <?php
}
