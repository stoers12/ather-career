<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../../includes/portfolio_presentation.php';
$variant = $argv[1] ?? 'image';
$profile = [
    'full_name' => 'Alex Morgan',
    'hero_headline' => 'Building useful software',
    'location' => 'Amman, Jordan',
    'phone_primary' => '+962 79 123 4567',
    'email' => 'alex@example.org',
];
if ($variant === 'long' || $variant === 'fallback') {
    $profile['full_name'] = 'Alexandra Morgan عبدالرحمن';
    $profile['location'] = 'Amman — المملكة الأردنية الهاشمية';
    $profile['email'] = 'alexandra.morgan.professional.contact@international-portfolio.example.org';
}
if ($variant === 'empty') {
    $profile['phone_primary'] = ' ';
    $profile['email'] = '';
}
renderPortfolioPresentation($profile, [], [], [
    'preview' => true,
    'profile_media_url' => $variant === 'fallback' || $variant === 'empty' ? '' : '/synthetic-portrait.svg',
]);
