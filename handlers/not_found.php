<?php

declare(strict_types=1);

// Rendered for every path the route table does not know (ENG-09, owner decision
// 2026-10-29). The front controller has already set status 404. Visitors may
// land here, so the page is always English. It reads no session or database
// and writes no application log line, so bots probing random paths stay cheap
// and quiet; Apache's access log still records every request.
require_once dirname(__DIR__) . '/includes/presentation.php';
require_once dirname(__DIR__) . '/includes/landing_icons.php';

header('Content-Type: text/html; charset=utf-8');
header('Content-Language: en');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="light dark">
    <title>Page not found · Ather</title>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/not_found.css'); ?>">
</head>
<body>
<main class="nf-page" id="not-found">
    <a class="nf-brand" href="/"><?php echo landingIcon('fingerprint'); ?><span>Ather</span></a>
    <section class="nf-card" aria-labelledby="nf-title">
        <p class="nf-code">Error 404</p>
        <h1 id="nf-title">This page doesn't exist</h1>
        <p class="nf-lead">The link may be mistyped, or the page may have moved. Check the address, or start again from the home page.</p>
        <div class="nf-actions">
            <a class="nf-action nf-action-primary" href="/">Go to the home page</a>
            <a class="nf-action" href="/signin.php">Sign in</a>
        </div>
    </section>
</main>
</body>
</html>
