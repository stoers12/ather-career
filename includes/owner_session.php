<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

const OWNER_SESSION_NAME = 'portfolio_owner_session';

function startOwnerSession(): void
{
    startApplicationSession(OWNER_SESSION_NAME);
}

function destroyOwnerSession(): void
{
    destroyInternalUserSession();
}
