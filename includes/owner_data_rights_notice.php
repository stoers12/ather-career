<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_data_rights_copy.php';

/** Query values select fixed copy and are never reflected. */
function renderOwnerDataRightsAccountNotice(mixed $notice, string $locale, string $location): void
{
    $key = match (true) {
        $location === 'signin' && $notice === 'deletion_scheduled' => 'scheduled_notice',
        $location === 'dashboard' && $notice === 'deletion_cancelled' => 'cancelled_notice',
        default => null,
    };
    if ($key === null) {
        return;
    }
    $class = $location === 'signin' ? 'af-account-notice' : 'status-message';
    echo '<p class="' . $class . '" role="status" data-account-notice>'
        . htmlspecialchars(ownerDataRightsCopy($key, $locale), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
}
