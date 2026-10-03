<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/portfolio_presentation.php';

$variant = $argv[1] ?? 'three-public';
$profile = [
    'full_name' => 'Jordan Lee',
    'hero_headline' => 'Building useful software',
    'linkedin_url' => 'https://linkedin.example.test/jordan',
    'github_url' => 'https://github.example.test/jordan',
    'website_url' => 'https://jordan.example.test/',
];
$experiences = [
    ['experience_type' => 'training', 'role_title' => 'Data Analyst Trainee', 'organization' => 'Parachute 16', 'location' => 'Amman, Jordan', 'start_month' => '2025-09', 'end_month' => '2026-02', 'is_current' => false, 'description' => 'Completed hands-on analysis training across reporting, research, and stakeholder communication.'],
    ['experience_type' => 'internship', 'role_title' => 'Product Operations Intern', 'organization' => 'Northstar Systems', 'location' => '', 'start_month' => '2025-03', 'end_month' => '2025-08', 'is_current' => false, 'description' => 'Supported operational reviews and maintained clear documentation for delivery teams.'],
    ['experience_type' => 'employment', 'role_title' => 'Junior Data Specialist', 'organization' => 'Atlas Research Collective', 'location' => 'Remote', 'start_month' => '2024-06', 'end_month' => null, 'is_current' => true, 'description' => 'Build reliable reporting workflows and turn research findings into practical decisions.'],
];
$options = ['experiences' => $experiences, 'contact_action' => '/p/jordan/contact'];

$paginationExperiences = $experiences;
for ($index = 4; $index <= 11; ++$index) {
    $paginationExperiences[] = [
        'experience_type' => $index % 2 === 0 ? 'employment' : 'training',
        'role_title' => "Experience {$index}",
        'organization' => "Organization {$index}",
        'location' => $index % 2 === 0 ? 'Amman, Jordan' : '',
        'start_month' => sprintf('202%d-%02d', 6 - intdiv($index, 7), 13 - $index),
        'end_month' => sprintf('202%d-%02d', 6 - intdiv($index, 7), 13 - $index),
        'is_current' => false,
        'description' => "Experience {$index} description remains visible through the progressive fallback.",
    ];
}
usort($paginationExperiences, static fn (array $left, array $right): int => $right['start_month'] <=> $left['start_month']);

switch ($variant) {
    case 'three-public':
        break;
    case 'no-descriptions':
        foreach ($experiences as &$experience) {
            $experience['description'] = null;
        }
        unset($experience);
        $options['experiences'] = $experiences;
        break;
    case 'long-content':
        $experiences[0]['role_title'] = 'International Operations and Service Reliability Collaboration Specialist';
        $experiences[0]['organization'] = 'Cross-Functional Product and Engineering Collaboration Office';
        $experiences[0]['location'] = 'Amman, Jordan — Regional Delivery and Research Operations';
        $experiences[0]['description'] = "A deliberately long, truthful description that explains how multidisciplinary teams coordinate research, document delivery decisions, review service health, and improve operational practices without losing the evidence behind each change.\nSecond line remains visible and escaped safely.";
        $options['experiences'] = $experiences;
        break;
    case 'one-current':
        $options['experiences'] = [$experiences[2]];
        break;
    case 'pagination-six':
        $options['experiences'] = array_slice($paginationExperiences, 0, 6);
        break;
    case 'pagination-eleven':
        $options['experiences'] = $paginationExperiences;
        break;
    case 'no-experience':
        $options['experiences'] = [];
        break;
    case 'validation-errors':
        $options['contact_values'] = ['name' => '<Sender>', 'email' => 'invalid-email', 'message' => '<Message>'];
        $options['contact_field_errors'] = ['name' => 'Enter your name.', 'email' => 'Enter a valid email address.', 'message' => 'Enter a message.'];
        $options['contact_form_error'] = 'Please correct the highlighted fields and try again.';
        break;
    case 'preview':
        $options['preview'] = true;
        break;
    case 'social-minimal':
        $profile = ['full_name' => 'Jordan Lee', 'hero_headline' => 'Building useful software', 'github_url' => 'https://github.example.test/jordan', 'instagram_url' => 'javascript:alert(1)'];
        break;
    default:
        fwrite(STDERR, "Unknown visual variant.\n");
        exit(2);
}

renderPortfolioPresentation($profile, [], [], $options);
