<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/portfolio_presentation.php';

$variant = $argv[1] ?? 'fallback-many';
$profile = [
    'full_name' => 'Jordan Lee',
    'hero_headline' => 'Building useful software',
    'location' => 'Amman, Jordan',
];
$projects = [
    ['id' => 1, 'title' => 'Operations Dashboard', 'category' => 'Product Engineering', 'description' => 'A practical workspace that helps teams understand operational work at a glance and make decisions with shared context.', 'github_url' => 'https://github.example.test/operations', 'image_path' => null, 'technologies' => ['PHP', 'MySQL', 'Redis', 'Docker']],
    ['id' => 2, 'title' => 'Research Platform', 'category' => 'Data Systems', 'description' => 'A reliable platform for collecting, reviewing, and sharing research findings across distributed stakeholders.', 'github_url' => '', 'image_path' => null, 'technologies' => ['Python', 'Pandas', 'PostgreSQL', 'Airflow']],
    ['id' => 3, 'title' => 'Client Portal', 'category' => 'Product Engineering', 'description' => 'A secure self-service experience that makes account work clear, approachable, and dependable for clients.', 'github_url' => '', 'image_path' => null, 'technologies' => ['Laravel', 'TypeScript', 'Tailwind CSS', 'REST APIs']],
];
$additionalProjects = [
    ['id' => 4, 'title' => 'Service Operations Hub', 'category' => 'Operations', 'description' => 'A dependable workflow for coordinating operational handoffs, shared service context, and accountable follow-through.', 'github_url' => 'https://github.example.test/service-operations', 'image_path' => null, 'technologies' => ['PHP', 'Redis', 'Docker', 'Monitoring']],
    ['id' => 5, 'title' => 'Planning Workspace', 'category' => 'Product Engineering', 'description' => 'A clear planning workspace that connects priorities, delivery evidence, and practical decisions for teams.', 'github_url' => '', 'image_path' => null, 'technologies' => ['TypeScript', 'PostgreSQL', 'REST APIs', 'Accessibility']],
    ['id' => 6, 'title' => 'Knowledge Library', 'category' => 'Data Systems', 'description' => 'A structured knowledge library that helps distributed teams find reliable, current operational guidance.', 'github_url' => '', 'image_path' => null, 'technologies' => ['Python', 'Search', 'Docker', 'Data Quality']],
];
$skills = [
    ['skill_name' => 'PHP'], ['skill_name' => 'Laravel'], ['skill_name' => 'TypeScript'], ['skill_name' => 'Product Design'],
    ['skill_name' => 'Data Analysis'], ['skill_name' => 'PostgreSQL'], ['skill_name' => 'Docker'], ['skill_name' => 'Accessibility'],
    ['skill_name' => 'API Design'], ['skill_name' => 'Research Operations'], ['skill_name' => 'Systems Thinking'], ['skill_name' => 'Continuous Discovery'],
];

$withImages = static function (array $ids) use (&$projects): void {
    foreach ($projects as &$project) {
        if (in_array($project['id'], $ids, true)) {
            $project['image_path'] = 'portfolios/7/projects/project_' . $project['id'] . '.jpg';
        }
    }
    unset($project);
};

switch ($variant) {
    case 'fallback-many':
        break;
    case 'image-many':
        $withImages([1, 2, 3]);
        break;
    case 'mixed-many':
        $withImages([1, 3]);
        break;
    case 'one-few':
        $projects = array_slice($projects, 0, 1);
        $skills = array_slice($skills, 0, 2);
        break;
    case 'two-projects':
        $projects = array_slice($projects, 0, 2);
        break;
    case 'four-projects':
        $projects = [...$projects, $additionalProjects[0]];
        $withImages([1, 4]);
        break;
    case 'six-projects':
        $projects = [...$projects, ...$additionalProjects];
        $withImages([1, 4, 6]);
        break;
    case 'projects-only':
        $skills = [];
        break;
    case 'skills-only':
        $projects = [];
        break;
    case 'long':
        $withImages([1]);
        $projects[0]['title'] = 'International Operations and Service Reliability Collaboration Workspace';
        $projects[0]['description'] = 'A deliberately long, truthful description that explains how multidisciplinary teams coordinate incident response, review service health, prioritize customer impact, and steadily improve operational practices without losing the decisions and evidence that informed each change.';
        $projects[0]['technologies'] = ['Event-Driven Architecture and Observability', 'Internationalization and Accessibility', 'Long-Lived Domain Modelling', 'Continuous Delivery'];
        $skills[] = ['skill_name' => 'Cross-Functional Product and Engineering Collaboration'];
        break;
    case 'invalid-link':
        $projects[0]['github_url'] = 'javascript:alert(1)';
        break;
    default:
        fwrite(STDERR, "Unknown visual variant.\n");
        exit(2);
}

renderPortfolioPresentation($profile, $skills, $projects, [
    'preview' => true,
    'project_media_url' => static fn (int $projectId): string => "/synthetic-project-{$projectId}.svg",
]);
