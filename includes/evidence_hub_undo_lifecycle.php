<?php

declare(strict_types=1);

const EVIDENCE_HUB_UNDO_SESSION_KEY = 'evidence_hub_last_evidence_undo';

/**
 * The save redirects to one Evidence Hub GET. After that GET presents Undo,
 * only its authenticated, CSRF-protected POST may keep the snapshot alive.
 * This runs at the shared Owner session boundary, before any Owner page renders.
 *
 * @param array<string,mixed> $post
 */
function expireEvidenceHubUndoForOwnerRequest(string $script, string $method, string $path, array $post): void
{
    if (!isset($_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY])) {
        return;
    }

    // Private media is an Owner route but can load in the background while
    // the immediate post-save page is open. Other non-HTML routes do not call
    // startOwnerSession().
    if ($script === 'owner_media.php') {
        return;
    }

    $record = $_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY];
    $hub = $script === 'owner_evidence_hub.php' && $path === '/owner/evidence-hub';
    if ($hub && $method === 'GET' && is_array($record) && ($record['display_pending'] ?? null) === true) {
        return;
    }

    if ($hub && $method === 'POST' && is_array($record)
        && ($record['display_pending'] ?? null) === false
        && ($post['action'] ?? null) === 'undo_evidence'
        && is_string($post['undo_token'] ?? null)
        && is_string($record['token_hash'] ?? null)
        && hash_equals($record['token_hash'], hash('sha256', $post['undo_token']))
        && is_string($post['csrf_token'] ?? null)
        && is_string($_SESSION['csrf_token'] ?? null)
        && hash_equals($_SESSION['csrf_token'], $post['csrf_token'])) {
        return;
    }

    unset($_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY]);
}
