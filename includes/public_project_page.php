<?php

declare(strict_types=1);

require_once __DIR__ . '/public_lifecycle.php';
require_once __DIR__ . '/public_url.php';
require_once __DIR__ . '/portfolio_presentation.php';

/**
 * One project's case study page (B-05). The contract is docs/contracts/public-project-page.md.
 * Links are already checked here, so templates never see a raw stored URL.
 *
 * @param array<string, mixed> $project a row from findPublicProject() or findAuthorizedProject()
 * @param array<string, mixed> $profile
 * @param array{portfolio: string, media: string, canonical: ?string} $urls
 * @return array{
 *     preview: bool,
 *     draft: bool,
 *     owner: array{name: string, professional_title: string},
 *     project: array{id: int, title: string, category: string, description: string, technologies: list<string>,
 *         live_url: ?string, github_url: ?string, image_url: ?string},
 *     portfolio_url: string,
 *     canonical_url: ?string
 * }
 */
function projectCaseStudyView(array $project, array $profile, array $urls, bool $preview): array
{
    $text = static fn (array $row, string $field): string => is_string($row[$field] ?? null) ? trim($row[$field]) : '';
    $technologies = is_array($project['technologies'] ?? null)
        ? array_values(array_filter($project['technologies'], 'is_string'))
        : [];

    return [
        'preview' => $preview,
        'draft' => $preview && (int) ($project['is_published'] ?? 0) !== 1,
        'owner' => ['name' => $text($profile, 'full_name'), 'professional_title' => $text($profile, 'professional_title')],
        'project' => [
            'id' => (int) $project['id'],
            'title' => $text($project, 'title'),
            'category' => $text($project, 'category'),
            'description' => $text($project, 'description'),
            'technologies' => $technologies,
            'live_url' => portfolioPresentationProjectLink($project, 'live_url'),
            'github_url' => portfolioPresentationProjectLink($project, 'github_url'),
            'image_url' => $text($project, 'image_path') !== '' ? $urls['media'] : null,
        ],
        'portfolio_url' => $urls['portfolio'],
        'canonical_url' => $preview ? null : $urls['canonical'],
    ];
}

/**
 * Visitor view: null (404) unless the slug names a published Portfolio of an active
 * account with a professional name, and the id names one of its published Projects.
 *
 * @return ?array<string, mixed> see projectCaseStudyView()
 */
function publicProjectPageData(PDO $database, mixed $slugCandidate, mixed $projectIdCandidate): ?array
{
    $slug = normalizePublicSlug($slugCandidate);
    $projectId = authorizationPositiveInteger($projectIdCandidate);
    if ($slug === null || $projectId === null) {
        return null;
    }
    $context = resolvePublicReadContext($database, $slug);
    if ($context === null) {
        return null;
    }
    $profile = loadPublicPersonalInfo($database, $context);
    if ($profile === null || trim((string) $profile['full_name']) === '') {
        return null;
    }
    $project = findPublicProject($database, $context, $projectId);
    if ($project === null) {
        return null;
    }
    $encodedSlug = rawurlencode($slug);
    try {
        $canonical = publicPortfolioUrl($slug) . "/projects/{$projectId}";
    } catch (PublicUrlConfigurationException) {
        $canonical = null; // The page still works; only the canonical link is omitted.
    }

    return projectCaseStudyView($project, $profile, [
        'portfolio' => "/p/{$encodedSlug}",
        'media' => "/p/{$encodedSlug}/media/project/{$projectId}",
        'canonical' => $canonical,
    ], false);
}
