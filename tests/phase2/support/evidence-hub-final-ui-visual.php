<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/evidence_hub_owner_core.php';
require_once __DIR__ . '/../../../includes/evidence_hub_owner_page_model.php';
require_once __DIR__ . '/../../../includes/evidence_hub_owner_page_presentation.php';
require_once __DIR__ . '/../../../includes/evidence_hub_recommendations.php';
require_once __DIR__ . '/../../../includes/owner_layout.php';

$input = PHP_SAPI === 'cli' ? array_combine(['scenario', 'recommendations', 'feedback'], array_pad(array_slice($argv, 1), 3, '')) : $_GET;
$scenario = $input['scenario'] ?? 'complete';
$count = $input['recommendations'] ?? '0';
$feedback = $input['feedback'] ?? 'none';
if (!in_array($scenario, ['complete', 'mixed'], true)
    || !in_array($count, ['0', '1', '2', '3'], true)
    || !in_array($feedback, ['none', 'saved', 'undone', 'conflict', 'unavailable'], true)) {
    http_response_code(404);
    exit;
}

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec('CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL); CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, title TEXT NOT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL); INSERT INTO portfolios VALUES (10,1)');
$complete = [
    'problem_statement' => 'Manual weekly reporting delayed operational decisions because the team reconciled multiple sources by hand.',
    'personal_role' => 'I designed the reporting pipeline and implemented validation checks across each imported data source.',
    'measurable_outcome' => 'Reduced weekly reporting time from six hours to one hour for the operations team.',
];
$technologies = [
    ['Python', 'Pandas', 'NumPy', 'Scikit-learn', 'Matplotlib', 'Seaborn', 'Joblib', 'Excel'],
    ['Python', 'FastAPI', 'PostgreSQL', 'pgvector', 'SentenceTransformers', 'Redis', 'Celery', 'Groq', 'Docker'],
    ['php', 'Docker', 'Css', 'Js', 'Git', 'Github'],
    ['Power bi', 'MySql', 'Dax Equation', 'MS Excel'],
];
$insert = $database->prepare('INSERT INTO projects (id,portfolio_id,title,problem_statement,personal_role,measurable_outcome,technologies,created_at,updated_at) VALUES (:id,10,:title,:problem_statement,:personal_role,:measurable_outcome,:technologies,:created_at,:updated_at)');
foreach ([1, 2, 3, 4] as $index) {
    $values = $complete;
    if ($scenario === 'mixed' && $index === 2) $values = ['problem_statement' => null, 'personal_role' => null, 'measurable_outcome' => null];
    if ($scenario === 'mixed' && $index === 3) $values['measurable_outcome'] = null;
    $insert->execute([
        'id' => $index,
        'title' => 'Project ' . $index,
        ...$values,
        'technologies' => json_encode($technologies[$index - 1], JSON_THROW_ON_ERROR),
        'created_at' => '2026-01-0' . $index . 'T00:00:00Z',
        'updated_at' => '2026-09-0' . (5 - $index) . 'T00:00:00Z',
    ]);
}
$context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
$evaluated = loadAuthorizedEvidenceHubOwnerEvaluatedProjects($database, $context);
$core = buildEvidenceHubOwnerCoreFromEvaluatedProjects($evaluated, 'not_configured');
$recommendations = [];
if ($count !== '0') {
    $recommendations = buildEvidenceHubRecommendations([
        'tenant_scope_ref' => 'visual_final_tenant',
        'portfolio_target_identity' => 'visual_final_portfolio',
        'projects' => [[
            'target_identity' => 'visual_final_project',
            'field_completeness_states' => ['problem_statement' => 'unavailable', 'personal_role' => 'unavailable', 'measurable_outcome' => 'unavailable'],
            'reason_codes' => [],
        ]],
        'technology_mappings' => [[
            'target_identity' => 'visual_final_technology',
            'mapping_state' => 'unmapped',
            'normalized_unmapped_label_digest' => str_repeat('c', 64),
        ]],
        'portfolio_publication' => ['portfolio_published' => false, 'publication_prerequisites_met' => true],
    ], [], 1767225600, 'visual-final-synthetic-hmac');
    $recommendations = array_slice($recommendations, 0, (int) $count);
}
$contract = mapEvidenceHubContractV4($core, $recommendations);
$model = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, ['contract' => ['recommendations' => []]]);
foreach ($recommendations as $recommendation) {
    $model['recommendations'][] = [
        'project_title' => 'Project 2',
        'edit_url' => $recommendation['rule_id'] === 'complete_project_evidence' ? '/owner/projects/2/evidence' : '/owner_projects.php?edit=2',
    ];
}
$tokens = array_fill(0, count($recommendations), ['snooze' => str_repeat('a', 64), 'dismiss' => str_repeat('b', 64)]);
$undoFeedback = ['kind' => $feedback];
if ($feedback === 'saved') $undoFeedback['token'] = str_repeat('c', 64);
$_SESSION = ['csrf_token' => str_repeat('d', 64)];
ownerLayoutStart('Evidence Hub', 'evidence_hub', true);
renderEvidenceHubOwnerPage($contract, $model, $tokens, '', $undoFeedback);
ownerLayoutEnd();
