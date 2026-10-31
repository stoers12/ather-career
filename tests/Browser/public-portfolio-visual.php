<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../includes/public_project_presentation.php';
require_once __DIR__ . '/../../includes/public_project_page.php';

$variant = $argv[1] ?? 'public';
$_GET['lang'] = 'ar';
$_COOKIE['ather_owner_locale'] = 'ar';
$profile = [
    'full_name' => 'Sami Ahmad', 'professional_title' => 'AI Engineer',
    'hero_headline' => 'Intelligent systems. Meaningful impact.',
    'work_description' => 'I build reliable AI applications and practical software that solve real problems.',
    'about_me' => "I build accessible software with thoughtful teams.\nClear systems make complex work easier.",
    'location' => 'Amman, Jordan', 'github_url' => 'https://github.com/example',
    'linkedin_url' => 'https://www.linkedin.com/in/example',
];
$projects = [
    ['id' => 1, 'title' => 'Operations Dashboard', 'category' => 'Data Analysis', 'description' => "Workforce insights, clearly presented.\nA practical reporting workspace.", 'technologies' => ['Python', 'Excel', 'Power BI', 'SQL', 'Pandas'], 'live_url' => 'https://example.org/demo?view=public&mode=read', 'github_url' => 'https://github.com/example/dashboard', 'image_path' => 'synthetic.jpg', 'is_published' => 1],
    ['id' => 2, 'title' => 'Community Platform', 'category' => 'Software', 'description' => 'A professional platform built around useful work.', 'technologies' => ['PHP', 'Docker', 'JavaScript', 'MySQL'], 'image_path' => null, 'github_url' => '', 'live_url' => '', 'is_published' => 1],
    ['id' => 3, 'title' => 'Research Toolkit', 'category' => 'Machine Learning', 'description' => 'Reproducible experiments for clear decisions.', 'technologies' => ['Python', 'NumPy'], 'image_path' => null, 'live_url' => 'javascript:alert(1)', 'is_published' => 1],
    ['id' => 4, 'title' => 'Service Console', 'category' => 'Product Engineering', 'description' => 'Connecting teams through practical software.', 'technologies' => ['PHP', 'SQL'], 'image_path' => null, 'is_published' => 1],
];
$experiences = [];
foreach (['AI Engineering', 'Data Analysis', 'Software Engineer', 'Community Mentor', 'Research Assistant', 'Training'] as $i => $role) {
    $experiences[] = ['experience_type' => 'employment', 'role_title' => $role, 'organization' => 'Example Studio', 'location' => 'Amman', 'start_month' => '2026-09', 'end_month' => null, 'is_current' => true, 'description' => $i === 0 ? 'Building dependable tools for the team.' : null];
}
$preview = str_starts_with($variant, 'preview') || $variant === 'draft';
if (str_contains($variant, 'case') || $variant === 'draft') {
    $project = $projects[$variant === 'case-empty' ? 1 : 0];
    if ($variant === 'draft') $project['is_published'] = 0;
    renderPublicProjectPage(projectCaseStudyView($project, $profile, [
        'portfolio' => $preview ? '/owner_preview.php' : '/p/synthetic-owner',
        'media' => $preview ? '/owner_media.php?type=project&id=1' : '/p/synthetic-owner/media/project/1',
        'canonical' => $preview ? null : 'https://portfolio.example.test/p/synthetic-owner/projects/' . $project['id'],
    ], $preview, $variant !== 'preview-case-private'));
    exit;
}
$options = [
    'preview' => $preview, 'portfolio_published' => $variant !== 'preview-private',
    'canonical_url' => 'https://portfolio.example.test/p/synthetic-owner',
    'project_media_url' => static fn (int $id): string => $preview ? '/owner_media.php?type=project&id=' . $id : '/p/synthetic-owner/media/project/' . $id,
    'project_case_url' => static fn (int $id): string => $preview ? '/owner_preview.php?project=' . $id : '/p/synthetic-owner/projects/' . $id,
    'contact_action' => '/p/synthetic-owner/contact', 'experiences' => $experiences,
];
if ($variant === 'error') {
    $options['contact_values'] = ['name' => '<Visitor>', 'email' => 'invalid', 'message' => "Hello\nA useful conversation."];
    $options['contact_field_errors'] = ['email' => 'Enter a valid email address.'];
    $options['contact_form_error'] = 'Please correct the highlighted fields and try again.';
}
if ($variant === 'success') $options['contact_sent'] = true;
if ($variant === 'long') {
    $profile['full_name'] = str_repeat('LongProfessionalName', 8);
    $profile['email'] = str_repeat('long', 40) . '@example.test';
    $profile['phone_primary'] = '+962 79 000 0000';
    $projects[0]['title'] = str_repeat('LongProjectName', 12);
}
renderPortfolioPresentation($profile, [['skill_name' => 'Python'], ['skill_name' => 'REST API']], $projects, $options);
