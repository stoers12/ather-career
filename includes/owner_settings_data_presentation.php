<?php

declare(strict_types=1);

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/owner_data_rights_copy.php';

/**
 * @param array{export_url: string, reauthenticate_url: string, deletion_url: string,
 *     recently_authenticated: bool, recent_until: ?int, confirmation_phrase: string,
 *     grace_days: int, notice: ?string} $rights
 */
function renderOwnerSettingsDataSection(array $rights, string $locale): void
{
    $copy = static fn (string $key): string => ownerEscapeHtml(ownerDataRightsCopy($key, $locale));
    $token = ownerEscapeHtml(getCsrfToken());
    $confirmationError = $rights['notice'] === 'confirmation';
    ?>
<section class="owner-settings-card" id="data" data-owner-settings-panel="data" aria-labelledby="data-title">
    <header><h2 id="data-title"><?php echo $copy('title'); ?></h2><p><?php echo $copy('intro'); ?></p></header>
    <?php if (in_array($rights['notice'], ['reauthenticate', 'confirmation'], true)): ?>
    <p class="status-message error" id="data-error" role="alert"><?php echo $copy($rights['notice']); ?></p>
    <?php endif; ?>
    <?php if (!$rights['recently_authenticated']): ?>
    <p class="owner-data-description"><?php echo $copy('identity'); ?></p>
    <form method="post" action="<?php echo ownerEscapeHtml($rights['reauthenticate_url']); ?>" data-owner-form>
        <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
        <input type="hidden" name="return" value="data">
        <button class="button-primary" type="submit"><?php echo $copy('confirm'); ?></button>
    </form>
    <?php else: ?>
    <div class="owner-data-download">
        <h3><?php echo $copy('download'); ?></h3>
        <p class="owner-data-description" id="data-download-hint"><?php echo $copy('zip'); ?></p>
        <form method="post" action="<?php echo ownerEscapeHtml($rights['export_url']); ?>" data-owner-form>
            <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
            <button class="button-secondary" type="submit" aria-describedby="data-download-hint"><?php echo $copy('download'); ?></button>
        </form>
    </div>
    <div class="owner-data-danger">
        <h3><?php echo $copy('delete'); ?></h3>
        <p class="owner-data-description" id="data-delete-warning"><?php echo ownerEscapeHtml(sprintf(ownerDataRightsCopy('warning', $locale), $rights['grace_days'])); ?></p>
        <details class="owner-data-confirmation"<?php echo $confirmationError ? ' open' : ''; ?>>
            <summary><?php echo $copy('review'); ?></summary>
            <form method="post" action="<?php echo ownerEscapeHtml($rights['deletion_url']); ?>" data-owner-form aria-describedby="data-delete-warning">
                <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
                <input type="hidden" name="action" value="request">
                <label for="data-confirmation"><?php echo $copy('phrase_before'); ?> <bdi dir="auto"><?php echo ownerEscapeHtml($rights['confirmation_phrase']); ?></bdi> <?php echo $copy('phrase_after'); ?></label>
                <input id="data-confirmation" type="text" name="confirmation" required dir="auto" autocomplete="off" autocapitalize="none" spellcheck="false"<?php echo $confirmationError ? ' aria-invalid="true" aria-describedby="data-error"' : ''; ?>>
                <button class="button-danger owner-data-delete" type="submit"><?php echo $copy('request'); ?></button>
            </form>
        </details>
    </div>
    <?php endif; ?>
</section>
    <?php
}
