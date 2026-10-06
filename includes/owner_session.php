<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/evidence_hub_undo_lifecycle.php';

const OWNER_SESSION_NAME = 'portfolio_owner_session';

function startOwnerSession(): void
{
    startApplicationSession(OWNER_SESSION_NAME);
    if (PHP_SAPI !== 'cli' && currentInternalUserSession() !== null) {
        expireEvidenceHubUndoForOwnerRequest(
            basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')),
            (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            explode('?', (string) ($_SERVER['REQUEST_URI'] ?? ''), 2)[0],
            is_array($_POST) ? $_POST : []
        );
    }
}

function destroyOwnerSession(): void
{
    destroyInternalUserSession();
}

function beginFreshOwnerAccountSelectionSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException('Account selection requires an active session.');
    }

    // Retire the authenticated session before creating a separate anonymous
    // session for the new one-time OIDC transaction.
    destroyOwnerSession();
    session_id('');
    startOwnerSession();
    if (!session_regenerate_id(true)) {
        destroyOwnerSession();
        throw new RuntimeException('Could not rotate the account-selection session.');
    }
}
