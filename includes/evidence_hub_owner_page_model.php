<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_aggregation.php';
require_once __DIR__ . '/evidence_hub_project_facts.php';
require_once __DIR__ . '/evidence_hub_technology_mapper.php';
require_once __DIR__ . '/evidence_hub_owner_recommendations.php';
require_once __DIR__ . '/project_evidence_repository.php';

/**
 * Private presentation facts. Raw evidence text stays in this server-side
 * calculation and is never returned to the renderer or a client payload.
 *
 * @param array<string, mixed> $recommendationState
 * @return array<string, mixed>
 */
function buildAuthorizedEvidenceHubOwnerPageModel(
    PDO $database,
    AuthorizedPortfolioContext $context,
    array $recommendationState,
): array {
    $driver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);
    $epoch = static function (string $column) use ($driver): string {
        return match ($driver) {
            'mysql' => 'UNIX_TIMESTAMP(projects.' . $column . ')',
            'sqlite' => "CASE WHEN projects.{$column} GLOB '*Z' OR projects.{$column} GLOB '*[+-][0-9][0-9]:[0-9][0-9]' THEN CAST(strftime('%s', projects.{$column}) AS INTEGER) ELSE NULL END",
            default => throw new RuntimeException('Evidence Hub project timestamp storage is unsupported.'),
        };
    };
    $statement = $database->prepare(
        'SELECT projects.id, projects.title, projects.problem_statement, projects.personal_role,
                projects.measurable_outcome, projects.technologies, projects.updated_at AS updated_storage,
                ' . $epoch('created_at') . ' AS created_epoch,
                ' . $epoch('updated_at') . ' AS updated_epoch
         FROM projects
         JOIN portfolios ON portfolios.id = projects.portfolio_id
         WHERE projects.portfolio_id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id'
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'authorized_user_id' => $context->userId,
    ]);

    $projects = [];
    $technologyFacts = [];
    $activity = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $id = authorizationPositiveInteger($record['id'] ?? null);
        $title = $record['title'] ?? null;
        if ($id === null || !is_string($title)) {
            throw new RuntimeException('Evidence Hub project presentation facts are invalid.');
        }
        $created = evidenceHubProjectRecordedAtEpochSeconds($record['created_epoch'] ?? null);
        $updated = evidenceHubProjectRecordedAtEpochSeconds($record['updated_epoch'] ?? null);
        $storage = parseProjectTechnologiesStorage($record['technologies'] ?? null);
        $logicalEvaluations = projectEvidenceLogicalEvaluationsFromStorage($record);
        $evaluated = evaluateEvidenceHubProjectDocumentation([
            'project_ref' => $id,
            'problem_statement' => $record['problem_statement'] ?? null,
            'personal_role' => $record['personal_role'] ?? null,
            'measurable_outcome' => $record['measurable_outcome'] ?? null,
            'recorded_at_epoch_seconds' => $created ?? 0,
            'technology_storage_state' => $storage['storage_state'],
            'technology_storage_reason_codes' => $storage['reason_codes'],
            'technologies' => $storage['labels'],
        ]);
        $fields = [];
        foreach (['problem' => 'Problem', 'personal_role' => 'Personal role', 'measurable_outcome' => 'Measurable outcome'] as $field => $label) {
            $evaluation = $logicalEvaluations[$field];
            $status = evidenceHubOwnerPageFieldStatus($evaluation);
            $fields[] = [
                'key' => $field,
                'label' => $label,
                'status' => $status,
                'reason' => $status === 'complete' ? '' : evidenceHubOwnerPageFieldReason($evaluation, $label),
            ];
        }
        $count = (int) $evaluated['complete_evidence_fields'];
        $hasInvalidField = in_array('invalid', array_column($fields, 'status'), true);
        $projects[] = [
            'id' => $id,
            'title' => $title,
            'status' => $hasInvalidField ? 'invalid' : ($count === 3 ? 'complete' : ($count === 0 ? 'missing' : 'needs_attention')),
            'complete_count' => $count,
            'updated_at' => $updated === null ? null : gmdate('Y-m-d', $updated),
            'updated_epoch' => $updated,
            'fields' => $fields,
            'edit_url' => '/owner_projects.php?edit=' . $id,
        ];
        $activityAt = $updated ?? (($record['updated_storage'] ?? null) === null ? $created : null);
        if ($count === 3 && $activityAt !== null) {
            $activity[] = $activityAt;
        }
        $technologyFacts[] = $storage;
    }
    usort($projects, static fn (array $left, array $right): int =>
        [-(int) ($left['updated_epoch'] ?? -1), -$left['id']]
        <=> [-(int) ($right['updated_epoch'] ?? -1), -$right['id']]
    );
    foreach ($projects as &$project) {
        unset($project['updated_epoch']);
    }
    unset($project);

    $model = [
        'projects' => $projects,
        'technologies' => evidenceHubOwnerPageTechnologies($technologyFacts),
        'progress' => evidenceHubOwnerPageProgress(count($projects), count(array_filter($projects, static fn (array $project): bool => $project['complete_count'] === 3)), $activity),
        'recommendations' => [],
    ];
    foreach ($recommendationState['contract']['recommendations'] as $recommendation) {
        $resolved = resolveAuthorizedEvidenceHubOwnerRecommendation($recommendationState, $recommendation['recommendation_key']);
        if ($resolved === null) {
            throw new RuntimeException('Evidence Hub recommendation association is unavailable.');
        }
        $projectId = $resolved['resource_associations']['project_refs'][0] ?? null;
        $associated = null;
        if (is_int($projectId)) {
            foreach ($projects as $project) {
                if ($project['id'] === $projectId) {
                    $associated = $project;
                    break;
                }
            }
        }
        $model['recommendations'][] = [
            'project_title' => $associated['title'] ?? null,
            'edit_url' => $associated === null ? null : ($recommendation['rule_id'] === 'complete_project_evidence'
                ? '/owner/projects/' . $associated['id'] . '/evidence'
                : $associated['edit_url']),
        ];
    }
    return $model;
}

/** @param array<string, mixed> $evaluation */
function evidenceHubOwnerPageFieldStatus(array $evaluation): string
{
    if ($evaluation['storage_validity'] === 'invalid') {
        return 'invalid';
    }
    return match ($evaluation['evidence_status']) {
        'complete' => 'complete',
        'needs_attention' => 'needs_attention',
        'unavailable' => 'missing',
        default => throw new RuntimeException('Evidence Hub field status is unsupported.'),
    };
}

/** @param array<string, mixed> $evaluation */
function evidenceHubOwnerPageFieldReason(array $evaluation, string $label): string
{
    $messages = [
        'FIELD_NOT_AVAILABLE' => 'Add a specific account of this evidence.',
        'EMPTY_NORMALIZED_VALUE' => 'Add a specific account of this evidence.',
        'PLACEHOLDER_CONFIRMED' => 'Replace placeholder text with details from the project.',
        'GRAPHEME_THRESHOLD_NOT_MET' => 'Add more detail about this part of the project.',
        'USEFUL_TOKEN_THRESHOLD_NOT_MET' => 'Add more useful words about this part of the project.',
        'DISTINCT_TOKEN_THRESHOLD_NOT_MET' => 'Add more varied details about this part of the project.',
        'REPETITION_SUSPECTED' => 'Replace repeated wording with specific details.',
        'NON_SCALAR_INPUT' => 'Review and save this field again.',
        'INVALID_UTF8' => 'Review and save this field again.',
        'MAXIMUM_LENGTH_EXCEEDED' => 'Shorten this field and save it again.',
        'PROHIBITED_CONTROL_CHARACTER' => 'Remove unsupported characters and save this field again.',
        'ZERO_WIDTH_CHARACTER' => 'Remove invisible characters and save this field again.',
        'PROHIBITED_DIRECTIONALITY_CHARACTER' => 'Remove unsupported directional characters and save this field again.',
    ];
    $reasons = $evaluation['reason_codes'] ?? [];
    if (!is_array($reasons) || $reasons === []) {
        throw new RuntimeException('Evidence Hub field reason is unavailable.');
    }
    foreach ($reasons as $reason) {
        if (!isset($messages[$reason])) {
            throw new RuntimeException('Evidence Hub field reason is unsupported.');
        }
    }
    return $messages[$reasons[0]];
}

/** @param list<array<string, mixed>> $technologyFacts @return array{mapped: array<string, list<array<string, mixed>>>, unmapped: list<array<string, mixed>>, overview: array{mapped_technology_count: int, unmapped_technology_count: int, unmapped_project_count: int}} */
function evidenceHubOwnerPageTechnologies(array $technologyFacts): array
{
    $taxonomy = loadEvidenceHubTechnologyTaxonomy('v2');
    $mapped = [];
    $unmapped = [];
    $unmappedProjectCount = 0;
    foreach ($technologyFacts as $fact) {
        if ($fact['storage_state'] !== 'valid') {
            continue;
        }
        $projectMapped = [];
        $projectUnmapped = [];
        foreach ($fact['labels'] as $label) {
            $entry = mapEvidenceHubTechnologyLabel($label, $taxonomy);
            if ($entry === null) {
                continue;
            }
            if ($entry['mapping_state'] === 'mapped') {
                $key = $entry['canonical_key'];
                $projectMapped[$key] = ['key' => $key, 'label' => $entry['display_name'], 'category' => $entry['category']];
            } else {
                $normalized = normalizeEvidenceHubTechnologyLabel($label);
                if ($normalized !== null) {
                    $projectUnmapped[$normalized] = true;
                }
            }
        }
        foreach ($projectMapped as $entry) {
            $key = $entry['key'];
            if (!isset($mapped[$key])) {
                $mapped[$key] = $entry + ['project_count' => 0];
            }
            ++$mapped[$key]['project_count'];
        }
        foreach (array_keys($projectUnmapped) as $key) {
            $unmapped[$key] = ($unmapped[$key] ?? 0) + 1;
        }
        if ($projectUnmapped !== []) {
            ++$unmappedProjectCount;
        }
    }
    $mappedTechnologyCount = count($mapped);
    $unmappedTechnologyCount = count($unmapped);
    $categories = [];
    foreach ($mapped as $entry) {
        $categories[$entry['category']][] = $entry;
    }
    $order = array_flip($taxonomy['category_order']);
    uksort($categories, static fn (string $left, string $right): int => $order[$left] <=> $order[$right]);
    foreach ($categories as &$entries) {
        usort($entries, static fn (array $a, array $b): int => [$a['label'], $a['key']] <=> [$b['label'], $b['key']]);
    }
    unset($entries);
    ksort($unmapped, SORT_STRING);
    $unknown = [];
    $ordinal = 0;
    foreach ($unmapped as $count) {
        ++$ordinal;
        $unknown[] = ['label' => 'Unmapped technology ' . $ordinal, 'project_count' => $count];
    }
    return [
        'mapped' => $categories,
        'unmapped' => $unknown,
        'overview' => [
            'mapped_technology_count' => $mappedTechnologyCount,
            'unmapped_technology_count' => $unmappedTechnologyCount,
            'unmapped_project_count' => $unmappedProjectCount,
        ],
    ];
}

/** @param list<int> $completeActivity @return array<string, mixed> */
function evidenceHubOwnerPageProgress(int $projectCount, int $completeCount, array $completeActivity): array
{
    sort($completeActivity, SORT_NUMERIC);
    $first = $completeActivity[0] ?? null;
    $latest = $completeActivity === [] ? null : $completeActivity[count($completeActivity) - 1];
    $trendReady = $completeCount >= 3 && count($completeActivity) >= 3
        && $first !== null && $latest !== null && $latest - $first >= 30 * 24 * 60 * 60;
    $stage = match (true) {
        $projectCount === 0 => 'no_projects',
        $completeCount === 0 => 'incomplete_first_project',
        $completeCount === 1 => 'single_project',
        $trendReady => 'trend_ready',
        default => 'emerging_patterns',
    };
    $labels = [
        'no_projects' => 'Start with a project',
        'incomplete_first_project' => 'Complete your first evidence-ready project',
        'single_project' => 'One evidence-ready project',
        'emerging_patterns' => 'Evidence across projects',
        'trend_ready' => 'Historical context available',
    ];
    return [
        'stage' => $stage,
        'label' => $labels[$stage],
        'project_count' => $projectCount,
        'complete_project_count' => $completeCount,
        'published_project_count' => null,
        'first_activity_at' => $first === null ? null : gmdate('Y-m-d', $first),
        'latest_activity_at' => $latest === null ? null : gmdate('Y-m-d', $latest),
        'trend_status' => $trendReady ? 'available' : ($projectCount === 0 ? 'not_available' : 'insufficient_history'),
        'history_explanation' => $trendReady ? 'Complete projects span at least 30 full days.' : 'There is not enough dated, complete project evidence to show historical trend context.',
    ];
}
