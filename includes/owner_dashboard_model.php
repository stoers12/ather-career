<?php

declare(strict_types=1);

require_once __DIR__ . '/portfolio_scoped_data.php';
require_once __DIR__ . '/public_lifecycle.php';
require_once __DIR__ . '/owner_publication_presentation.php';

/**
 * Dashboard overview from the owner's real projects (redesign ق-33). Pure: the
 * rows come from the authorized repositories in ownerDashboardPageData().
 *
 * "Written parts" counts which of the three evidence fields (problem, role,
 * outcome) contain text. It describes what the owner has written, not evidence
 * quality: Evidence Hub's evaluator remains the only quality judgement.
 *
 * @param list<array{id: int|string, title: string, category: string, is_published: int|string}> $projects newest first
 * @param list<array{id: int|string, problem_statement: ?string, personal_role: ?string, measurable_outcome: ?string}> $evidence
 * @return array{
 *     total: int, published: int, drafts: int, needs_writing: int,
 *     recent: list<array{id: int, title: string, category: string, published: bool, written_parts: int}>
 * }
 */
function ownerDashboardOverview(array $projects, array $evidence, int $recentLimit = 4): array
{
    $writtenParts = [];
    foreach ($evidence as $row) {
        $writtenParts[(int) $row['id']] = count(array_filter(
            [$row['problem_statement'] ?? null, $row['personal_role'] ?? null, $row['measurable_outcome'] ?? null],
            static fn (?string $text): bool => $text !== null && trim($text) !== ''
        ));
    }
    $overview = ['total' => count($projects), 'published' => 0, 'drafts' => 0, 'needs_writing' => 0, 'recent' => []];
    foreach ($projects as $project) {
        $id = (int) $project['id'];
        $published = (int) $project['is_published'] === 1;
        $parts = $writtenParts[$id] ?? 0;
        $overview[$published ? 'published' : 'drafts']++;
        if ($parts < 3) {
            $overview['needs_writing']++;
        }
        if (count($overview['recent']) < $recentLimit) {
            $overview['recent'][] = ['id' => $id, 'title' => (string) $project['title'], 'category' => (string) $project['category'], 'published' => $published, 'written_parts' => $parts];
        }
    }

    return $overview;
}

/**
 * The single next step the dashboard proposes, in priority order.
 *
 * @param array{total: int, drafts: int, needs_writing: int} $overview
 * @return 'first_project'|'write_evidence'|'publish_drafts'|'preview'
 */
function ownerDashboardNextStep(array $overview): string
{
    return match (true) {
        $overview['total'] === 0 => 'first_project',
        $overview['needs_writing'] > 0 => 'write_evidence',
        $overview['drafts'] > 0 => 'publish_drafts',
        default => 'preview',
    };
}

/**
 * Everything the Overview template shows, read through the authorized repositories.
 * Contract: docs/contracts/owner-dashboard.md.
 *
 * @return array{
 *     owner_name: string, first_name: string,
 *     overview: array{total: int, published: int, drafts: int, needs_writing: int, recent: list<array{id: int, title: string, category: string, published: bool, written_parts: int}>},
 *     next_step: 'first_project'|'write_evidence'|'publish_drafts'|'preview',
 *     publication_state: 'no_slug'|'reserved'|'offline'|'published',
 *     public_url: ?string
 * }
 */
function ownerDashboardPageData(PDO $database, AuthorizedPortfolioContext $context): array
{
    $overview = ownerDashboardOverview(listAuthorizedProjects($database, $context), listAuthorizedProjectEvidence($database, $context), 3);
    $profile = loadAuthorizedPersonalInfo($database, $context);
    $ownerName = trim((string) ($profile['full_name'] ?? ''));
    $publication = ownedPublicLifecycleState($database, $context);
    $publicationState = ownerPublicationViewState($publication);

    return [
        'owner_name' => $ownerName,
        'first_name' => $ownerName === '' ? '' : (preg_split('/\s+/u', $ownerName)[0] ?? ''),
        'overview' => $overview,
        'next_step' => ownerDashboardNextStep($overview),
        'publication_state' => $publicationState,
        'public_url' => $publicationState === 'published' ? ownerPublicationPublicUrl($publication) : null,
    ];
}
