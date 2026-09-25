<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/evidence_hub_recommendations.php';
require_once __DIR__ . '/../../../includes/owner_layout.php';
require_once __DIR__ . '/../../../includes/evidence_hub_owner_presentation.php';

$contents = file_get_contents(__DIR__ . '/../fixtures/evidence-hub-golden-fixtures.json');
if (!is_string($contents)) {
    throw new RuntimeException('Evidence Hub visual fixture is unavailable.');
}
$fixtures = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
$variant = PHP_SAPI === 'cli' ? ($argv[1] ?? 'actions') : ($_GET['variant'] ?? 'actions');
if (!is_string($variant) || !in_array($variant, ['actions', 'partial', 'contexts', 'ready', 'multiple', 'error'], true)) {
    throw new InvalidArgumentException('Unknown Evidence Hub visual variant.');
}
$payload = null;
foreach ($fixtures['positive_payloads'] as $case) {
    $payloadId = match ($variant) {
        'partial', 'contexts' => 'PAYLOAD-PARTIAL',
        'ready', 'multiple' => 'PAYLOAD-READY',
        default => 'PAYLOAD-ZERO',
    };
    if (($case['id'] ?? null) === $payloadId) {
        $payload = $case['payload'];
        break;
    }
}
if (!is_array($payload)) {
    throw new RuntimeException('Evidence Hub visual payload is unavailable.');
}
if ($variant === 'actions') {
    $payload['recommendations'] = buildEvidenceHubRecommendations([
        'tenant_scope_ref' => 'visual_tenant',
        'portfolio_target_identity' => 'visual_portfolio',
        'projects' => [],
        'technology_mappings' => [],
        'portfolio_publication' => ['portfolio_published' => false, 'publication_prerequisites_met' => true],
    ], [], 1767225600, 'visual-synthetic-hmac');
}
if ($variant === 'contexts') {
    $payload['recommendations'] = buildEvidenceHubRecommendations([
        'tenant_scope_ref' => 'visual_context_tenant',
        'portfolio_target_identity' => 'visual_context_portfolio',
        'projects' => [[
            'target_identity' => 'visual_context_project',
            'field_completeness_states' => ['problem_statement' => 'unavailable', 'personal_role' => 'unavailable', 'measurable_outcome' => 'unavailable'],
            'reason_codes' => [],
        ]],
        'technology_mappings' => [[
            'target_identity' => 'visual_context_technology',
            'mapping_state' => 'unmapped',
            'normalized_unmapped_label_digest' => str_repeat('c', 64),
        ]],
        'portfolio_publication' => ['portfolio_published' => false, 'publication_prerequisites_met' => true],
    ], [], 1767225600, 'visual-context-synthetic-hmac');
}
if ($variant === 'multiple') {
    $mapping = $payload['metrics']['technology_evidence_map']['mappings'][0];
    $payload['metrics']['technology_evidence_map']['mappings'][] = array_replace($mapping, ['raw_label' => ' TS ', 'canonical_id' => 'tech.typescript', 'canonical_key' => 'typescript', 'display_name' => 'TypeScript']);
    $payload['metrics']['technology_evidence_map']['mappings'][] = array_replace($mapping, ['raw_label' => ' PHP ', 'canonical_id' => 'tech.php', 'canonical_key' => 'php', 'display_name' => 'PHP']);
    $payload['metrics']['technology_evidence_map']['technology_occurrence_count'] = 3;
    $payload['metrics']['technology_evidence_map']['mapped_occurrence_count'] = 3;
    $payload['metrics']['technology_evidence_map']['distinct_technology_count'] = 3;
    $payload['metrics']['technology_evidence_map']['distinct_mapped_technology_count'] = 3;
}

$_SESSION = ['csrf_token' => str_repeat('a', 64)];
ownerLayoutStart('Evidence Hub', 'evidence_hub', true);
if ($variant === 'error') {
    renderEvidenceHubOwnerSafeError();
} else {
    renderEvidenceHubOwnerPresentation($payload);
}
ownerLayoutEnd();
if (PHP_SAPI !== 'cli' && ($_GET['assert'] ?? null) === '1') {
    ?>
<script>
window.addEventListener('load', () => {
    const failures = [];
    const evidence = document.querySelector('a[href="/owner/evidence-hub"]');
    const recommendation = document.querySelector('[aria-label="Evidence Hub recommendation"] a');
    const skip = document.querySelector('.admin-skip-link');
    if (evidence?.getAttribute('aria-current') !== 'page') failures.push('active-navigation');
    if (recommendation?.getAttribute('href') !== '/owner_projects.php?add=1') failures.push('generic-action');
    if (document.documentElement.lang !== 'en' || document.documentElement.dir !== 'ltr') failures.push('english-ltr');
    if (document.documentElement.scrollWidth > innerWidth) failures.push('reflow');
    skip?.focus();
    skip?.click();
    if (location.hash !== '#main-content') failures.push('skip-link');
    document.documentElement.dataset.browserAssertions = failures.length === 0 ? 'PASS' : failures.join(',');
});
</script>
    <?php
}
