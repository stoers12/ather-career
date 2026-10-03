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
    'about_me' => 'A concise professional overview for a complete portfolio.',
    'location' => 'Amman, Jordan',
    'linkedin_url' => 'https://linkedin.example.test/jordan',
    'github_url' => 'https://github.example.test/jordan',
    'instagram_url' => 'https://instagram.example.test/jordan',
    'facebook_url' => 'https://facebook.example.test/jordan',
    'website_url' => 'https://jordan.example.test/',
];
$skills = [['skill_name' => 'PHP']];
$projects = [[
    'id' => 1,
    'title' => 'Operations Workspace',
    'category' => 'Product Engineering',
    'description' => 'A reliable workspace for practical delivery decisions.',
    'github_url' => '',
    'image_path' => null,
    'technologies' => ['PHP'],
]];
$options = [
    'experiences' => [[
        'experience_type' => 'employment',
        'role_title' => 'Data Specialist',
        'organization' => 'Atlas Research',
        'location' => 'Amman, Jordan',
        'start_month' => '2024-06',
        'end_month' => null,
        'is_current' => true,
        'description' => '',
    ]],
    'contact_action' => '/p/jordan/contact',
];

switch ($variant) {
    case 'full':
        break;
    case 'partial':
        $profile['about_me'] = '';
        $profile['github_url'] = '';
        $profile['instagram_url'] = '';
        $profile['facebook_url'] = '';
        $profile['website_url'] = 'javascript:alert(1)';
        $projects = [];
        $options['experiences'] = [];
        break;
    case 'minimal':
        $profile['about_me'] = '';
        $profile['linkedin_url'] = '';
        $profile['github_url'] = '';
        $profile['instagram_url'] = '';
        $profile['facebook_url'] = '';
        $profile['website_url'] = '';
        $skills = [];
        $projects = [];
        $options['experiences'] = [];
        break;
    case 'long-name':
        $profile['full_name'] = 'Jordan Alexandra Lee-Montgomery, International Operations and Service Reliability Collaboration Specialist';
        $profile['about_me'] = '';
        $profile['instagram_url'] = '';
        $profile['facebook_url'] = '';
        $profile['website_url'] = '';
        $projects = [];
        $options['experiences'] = [];
        break;
    case 'empty-name':
        $profile['full_name'] = '';
        $profile['about_me'] = '';
        $profile['linkedin_url'] = '';
        $profile['github_url'] = '';
        $profile['instagram_url'] = '';
        $profile['facebook_url'] = '';
        $profile['website_url'] = '';
        $skills = [];
        $projects = [];
        $options['experiences'] = [];
        break;
    case 'preview':
        $options['preview'] = true;
        break;
    default:
        fwrite(STDERR, "Unknown visual variant.\n");
        exit(2);
}

renderPortfolioPresentation($profile, $skills, $projects, $options);
