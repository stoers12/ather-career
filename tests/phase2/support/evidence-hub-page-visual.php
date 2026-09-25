<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/evidence_hub_recommendations.php';
require_once __DIR__ . '/../../../includes/owner_layout.php';
require_once __DIR__ . '/../../../includes/evidence_hub_owner_page_presentation.php';
require_once __DIR__ . '/../../../includes/evidence_hub_contract_mapper.php';
require_once __DIR__ . '/evidence-hub-partial-visual-facts.php';

$variant = PHP_SAPI === 'cli' ? ($argv[1] ?? 'partial') : ($_GET['variant'] ?? 'partial');
if (!in_array($variant, ['zero', 'partial', 'ready'], true)) {
    http_response_code(404);
    exit;
}
$fixtures = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/evidence-hub-golden-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
$payloadId = ['zero' => 'PAYLOAD-ZERO', 'partial' => 'PAYLOAD-PARTIAL', 'ready' => 'PAYLOAD-READY'][$variant];
$contract = null;
foreach ($fixtures['positive_payloads'] as $case) {
    if ($case['id'] === $payloadId) $contract = $case['payload'];
}
if (!is_array($contract)) throw new RuntimeException('Visual fixture payload is unavailable.');

$missingFields = [
    ['key' => 'problem', 'label' => 'Problem', 'status' => 'missing', 'reason' => 'Add a specific account of this evidence.'],
    ['key' => 'personal_role', 'label' => 'Personal role', 'status' => 'missing', 'reason' => 'Add a specific account of this evidence.'],
    ['key' => 'measurable_outcome', 'label' => 'Measurable outcome', 'status' => 'missing', 'reason' => 'Add a specific account of this evidence.'],
];
$completeFields = array_map(static fn (array $field): array => array_replace($field, ['status' => 'complete', 'reason' => '']), $missingFields);
$projects = [];
if ($variant === 'ready') {
    $total = 3;
    for ($id = $total; $id >= 1; --$id) {
        $complete = true;
        $fields = $complete ? $completeFields : $missingFields;
        if (!$complete && $id % 2 === 0) {
            $fields[0] = $completeFields[0];
        }
        $count = count(array_filter($fields, static fn (array $field): bool => $field['status'] === 'complete'));
        $projects[] = [
            'id' => $id,
            'title' => $id === $total ? 'Customer reporting platform' : 'Project ' . $id,
            'status' => $count === 3 ? 'complete' : ($count === 0 ? 'missing' : 'needs_attention'),
            'complete_count' => $count,
            'updated_at' => '2026-09-' . str_pad((string) (10 + $id), 2, '0', STR_PAD_LEFT),
            'fields' => $fields,
            'edit_url' => '/owner_projects.php?edit=' . $id,
        ];
    }
}
$partialFacts = $variant === 'partial' ? buildEvidenceHubPartialVisualFacts() : null;
if ($partialFacts !== null) {
    $projects = $partialFacts['projects'];
}
$progress = $partialFacts['progress'] ?? match ($variant) {
    'zero' => evidenceHubOwnerPageProgress(0, 0, []),
    'ready' => evidenceHubOwnerPageProgress(3, 3, [1767225600, 1769904000, 1772582400]),
};
$model = [
    'projects' => $projects,
    'technologies' => $partialFacts['technologies'] ?? ($variant === 'zero' ? evidenceHubOwnerPageTechnologies([]) : [
        'mapped' => ['language' => [['key' => 'javascript', 'label' => 'JavaScript', 'category' => 'language', 'project_count' => 3]]],
        'unmapped' => [],
        'overview' => ['mapped_technology_count' => 1, 'unmapped_technology_count' => 0, 'unmapped_project_count' => 0],
    ]),
    'progress' => $progress,
    'recommendations' => [],
];
if ($variant === 'partial') {
    $contract['recommendations'] = buildEvidenceHubRecommendations([
        'tenant_scope_ref' => 'visual_page_tenant',
        'portfolio_target_identity' => 'visual_page_portfolio',
        'projects' => [[
            'target_identity' => 'visual_page_project',
            'field_completeness_states' => ['problem_statement' => 'unavailable', 'personal_role' => 'unavailable', 'measurable_outcome' => 'unavailable'],
            'reason_codes' => [],
        ]],
        'technology_mappings' => [[
            'target_identity' => 'visual_page_technology',
            'mapping_state' => 'unmapped',
            'normalized_unmapped_label_digest' => str_repeat('c', 64),
        ]],
        'portfolio_publication' => ['portfolio_published' => false, 'publication_prerequisites_met' => true],
    ], [], 1767225600, 'visual-page-synthetic-hmac');
    foreach ($contract['recommendations'] as $recommendation) {
        $model['recommendations'][] = [
            'project_title' => $recommendation['rule_id'] === 'complete_portfolio_publication' ? null : 'Customer reporting platform',
            'edit_url' => match ($recommendation['rule_id']) {
                'complete_project_evidence' => '/owner/projects/8/evidence',
                'review_unmapped_technology' => '/owner_projects.php?edit=8',
                default => null,
            },
        ];
    }
    $contract = mapEvidenceHubContractV1([
        'maturity' => ['state' => 'partial', 'version' => EVIDENCE_HUB_CONTRACT_VERSION],
        'documentation_coverage' => $partialFacts['documentation'],
        'technology_evidence_map' => $partialFacts['technology_metric'],
        'portfolio_progress' => $partialFacts['analytics_progress'],
    ], $contract['recommendations']);
}
$tokens = array_fill(0, count($contract['recommendations']), ['snooze' => 'visual-snooze-token', 'dismiss' => 'visual-dismiss-token']);
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
ownerLayoutStart('Evidence Hub', 'evidence_hub', true);
renderEvidenceHubOwnerPage($contract, $model, $tokens);
ownerLayoutEnd();
