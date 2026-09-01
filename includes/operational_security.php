<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/security_events.php';

const OWNER_UPLOAD_RATE_LIMIT_ATTEMPTS = 20;
const OWNER_UPLOAD_RATE_LIMIT_WINDOW_SECONDS = 900;
const OWNER_PUBLICATION_RATE_LIMIT_ATTEMPTS = 20;
const OWNER_PUBLICATION_RATE_LIMIT_WINDOW_SECONDS = 900;

/** @return array{allowed: bool, retry_after: int} */
function consumeOwnerUploadRateLimit(AuthorizedPortfolioContext $context): array
{
    return consumeRateLimit(
        'owner_upload',
        $context->userId . ':' . $context->portfolioId,
        OWNER_UPLOAD_RATE_LIMIT_ATTEMPTS,
        OWNER_UPLOAD_RATE_LIMIT_WINDOW_SECONDS,
    );
}

/** @return array{allowed: bool, retry_after: int} */
function consumeOwnerPublicationRateLimit(AuthorizedPortfolioContext $context): array
{
    return consumeRateLimit(
        'owner_publication',
        (string) $context->portfolioId,
        OWNER_PUBLICATION_RATE_LIMIT_ATTEMPTS,
        OWNER_PUBLICATION_RATE_LIMIT_WINDOW_SECONDS,
    );
}

function reportRateLimitDenial(string $scope, AuthorizedPortfolioContext $context): void
{
    reportSecurityEvent('rate_limit_denial', 'denied', [
        'scope' => $scope,
        'internal_user_id' => $context->userId,
        'portfolio_id' => $context->portfolioId,
        'reason' => 'threshold_exceeded',
    ]);
}
