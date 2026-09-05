<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/portfolio_presentation.php';

$variant = $argv[1] ?? 'full';
$profile = [
    'full_name' => 'Jordan Lee',
    'hero_headline' => 'Building useful software',
    'about_me' => "I design reliable digital products that turn complex work into clear, practical experiences.\nMy focus is thoughtful systems, measurable outcomes, and long-term collaboration.",
    'location' => 'Amman, Jordan',
];
$projects = [
    ['id' => 1, 'title' => 'Operations Dashboard', 'category' => 'Product Engineering', 'description' => 'A concise project summary.', 'github_url' => '', 'image_path' => null],
    ['id' => 2, 'title' => 'Research Platform', 'category' => 'Data Systems', 'description' => 'A concise project summary.', 'github_url' => '', 'image_path' => null],
    ['id' => 3, 'title' => 'Client Portal', 'category' => 'Product Engineering', 'description' => 'A concise project summary.', 'github_url' => '', 'image_path' => null],
    ['id' => 4, 'title' => 'Service Console', 'category' => 'Product Engineering', 'description' => 'A concise project summary.', 'github_url' => '', 'image_path' => null],
];
$skills = [
    ['skill_name' => 'PHP'],
    ['skill_name' => 'Product Design'],
    ['skill_name' => 'Data Analysis'],
];

switch ($variant) {
    case 'one':
        $projects = [];
        $skills = [['skill_name' => 'PHP']];
        break;
    case 'two':
        $skills = [];
        break;
    case 'long':
        $profile['about_me'] = "I help multidisciplinary teams make consequential decisions with evidence, care, and a practical understanding of how people use technology in complex environments.\nThe work brings together product strategy, accessible interface design, dependable engineering practices, and continuous learning from customers across long-running programmes.";
        $profile['location'] = 'Amman, Jordan — serving distributed teams across the Middle East and Europe';
        break;
    case 'no-location':
        $profile['location'] = '';
        break;
    case 'no-about':
        $profile['about_me'] = '';
        break;
    case 'full':
        break;
    default:
        fwrite(STDERR, "Unknown visual variant.\n");
        exit(2);
}

renderPortfolioPresentation($profile, $skills, $projects, ['preview' => true]);
