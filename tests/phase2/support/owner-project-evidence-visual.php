<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../../includes/owner_project_evidence_presentation.php';

$status = static function (string $field, string $evidenceStatus, array $reasons): array {
    return ['evidence_field' => $field, 'storage_validity' => 'valid', 'evidence_status' => $evidenceStatus, 'reason_codes' => $reasons];
};
$project = ['id' => 7, 'title' => 'Customer reporting platform', 'values' => [
    'problem' => 'Manual weekly reporting affected the team because six hours of reconciliation delayed operational decisions.',
    'personal_role' => 'I designed the reporting pipeline and implemented validation checks across each imported data source.',
    'measurable_outcome' => '',
]];
$evaluations = [
    'problem' => $status('problem_statement', 'complete', ['TEXT_COMPLETE']),
    'personal_role' => $status('personal_role', 'needs_attention', ['USEFUL_TOKEN_THRESHOLD_NOT_MET']),
    'measurable_outcome' => $status('measurable_outcome', 'unavailable', ['EMPTY_NORMALIZED_VALUE']),
];
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
ownerLayoutStart('Edit project evidence', 'evidence_hub', true);
renderOwnerProjectEvidenceForm($project, $project['values'], [], $evaluations);
ownerLayoutEnd();
