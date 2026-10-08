<?php

declare(strict_types=1);

// Apache and Owner HTTPS apply the CSP. The exact configured issuer is an
// allowed form-action destination so Chromium can follow the POST's OIDC
// redirect without allowing unrelated cross-origin form submissions.

function edgeSecuritySensitiveResponse(): void
{
    header('Cache-Control: no-store');
}
