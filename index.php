<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/presentation.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="A platform for professional portfolios.">
    <title>Portfolio Platform</title>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('style.css'); ?>">
</head>
<body>
<main class="container">
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow">Portfolio Platform</p>
            <h1>Professional portfolios, published by their owners.</h1>
            <p class="hero-description">Sign in to manage your Portfolio or visit a published Portfolio at its shared public address.</p>
            <div class="hero-actions"><a class="btn btn-primary" href="owner_login.php">Owner sign in</a></div>
        </div>
    </section>
</main>
</body>
</html>
