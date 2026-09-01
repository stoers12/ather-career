<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/presentation.php';
require_once __DIR__ . '/includes/public_lifecycle.php';

function publicPortfolioNotFound(): never
{
    http_response_code(404);
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Portfolio not found</title></head><body><h1>Portfolio not found.</h1></body></html>';
    exit;
}

header('Cache-Control: no-store');

try {
    $database = getDatabaseConnection();
    $context = resolvePublicReadContext($database, $_GET['slug'] ?? null);
    if ($context === null) {
        publicPortfolioNotFound();
    }
    $profile = loadPublicPersonalInfo($database, $context);
    if ($profile === null || trim((string) $profile['full_name']) === '') {
        publicPortfolioNotFound();
    }
    $skills = listPublicSkills($database, $context);
    $projects = listPublicProjects($database, $context);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'public_portfolio.php', 'public_portfolio_load');
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Portfolio unavailable</title></head><body><h1>Portfolio temporarily unavailable.</h1></body></html>';
    exit;
}

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index,follow">
    <title><?php echo $escape($profile['full_name']); ?> - Portfolio</title>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('style.css'); ?>">
</head>
<body>
<main>
    <header><h1><?php echo $escape($profile['full_name']); ?></h1><?php if ((string) $profile['professional_title'] !== ''): ?><p><?php echo $escape($profile['professional_title']); ?></p><?php endif; ?></header>
    <?php if ((string) $profile['profile_image_path'] !== ''): ?><img src="<?php echo $escape($profile['profile_image_path']); ?>" alt="<?php echo $escape($profile['full_name']); ?>"><?php endif; ?>
    <?php if ((string) $profile['location'] !== ''): ?><p><?php echo $escape($profile['location']); ?></p><?php endif; ?>
    <?php if ((string) $profile['about_me'] !== '' || (string) $profile['work_description'] !== ''): ?><section><h2>About</h2><p><?php echo $escape((string) ($profile['about_me'] ?: $profile['work_description'])); ?></p></section><?php endif; ?>
    <section><h2>Skills</h2><?php if ($skills === []): ?><p>No skills listed.</p><?php else: ?><ul><?php foreach ($skills as $skill): ?><li><?php echo $escape($skill['skill_name']); ?></li><?php endforeach; ?></ul><?php endif; ?></section>
    <section><h2>Projects</h2><?php if ($projects === []): ?><p>No projects listed.</p><?php else: ?><div><?php foreach ($projects as $project): ?><article><h3><?php echo $escape($project['title']); ?></h3><p><?php echo $escape($project['category']); ?></p><p><?php echo $escape($project['description']); ?></p><?php if ((string) $project['github_url'] !== ''): ?><p><a href="<?php echo $escape($project['github_url']); ?>" rel="noopener noreferrer">Project link</a></p><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?></section>
</main>
</body>
</html>
