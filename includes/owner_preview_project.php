<?php

declare(strict_types=1);

require_once __DIR__ . '/portfolio_scoped_data.php';
require_once __DIR__ . '/public_project_page.php';

/**
 * Owner preview of one case study page (B-05): any Project of the owner's Portfolio,
 * published or draft. Null (404) for a missing or foreign id.
 *
 * @return ?array<string, mixed> see projectCaseStudyView()
 */
function ownerPreviewProjectPageData(PDO $database, AuthorizedPortfolioContext $context, mixed $projectIdCandidate): ?array
{
    $projectId = authorizationPositiveInteger($projectIdCandidate);
    $project = $projectId === null ? null : findAuthorizedProject($database, $context, $projectId);
    if ($project === null) {
        return null;
    }

    return projectCaseStudyView($project, loadAuthorizedPersonalInfo($database, $context) ?? [], [
        'portfolio' => '/owner_preview.php',
        'media' => "/owner_media.php?type=project&id={$projectId}",
        'canonical' => null,
    ], true);
}
