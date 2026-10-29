<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../includes/evidence_hub_owner_recommendations.php';
require_once __DIR__ . '/../../includes/evidence_hub_owner_page_model.php';
require_once __DIR__ . '/../../includes/evidence_hub_owner_page_presentation.php';
require_once __DIR__ . '/../../includes/owner_layout.php';
require_once __DIR__ . '/../../includes/csrf.php';

$locale = ($_GET['lang'] ?? null) === 'ar' ? 'ar' : 'en';
$context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
$taxonomy = loadEvidenceHubOwnerTaxonomy();
$facts = buildEvidenceHubOwnerRecommendationFacts($context, [], $taxonomy, ['portfolio_published' => false, 'publication_prerequisites_met' => true], [], ['React.js']);
$candidates = buildEvidenceHubRecommendationCandidates($facts['facts'], 'skill-visual-synthetic-hmac');
$skill = array_values(array_filter($candidates, static fn (array $candidate): bool => $candidate['rule_id'] === 'add_skill_evidence'));
$recommendations = filterEvidenceHubRecommendationCandidates($skill, [], 1767225600);
$contract = mapEvidenceHubOwnerContract(buildEvidenceHubOwnerCoreFromEvaluatedProjects([], 'not_configured', [], 0), $recommendations);
$model = [
    'projects' => [],
    'technologies' => evidenceHubOwnerPageTechnologies([]),
    'technology_review' => evidenceHubOwnerPageTechnologyReview([], [], $taxonomy, static fn (string $digest): string => $digest),
    'progress' => evidenceHubOwnerPageProgress(0, 0, [], 0),
    'recommendations' => [['project_title' => null, 'edit_url' => null, 'skill_display_name' => evidenceHubTaxonomyEntryById($taxonomy, 'tech.react')['display_name']]],
];
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
ownerLayoutStart('Evidence Hub', 'evidence_hub', true, ['locale' => $locale]);
renderEvidenceHubOwnerPage($contract, $model, [['snooze' => str_repeat('b', 43), 'dismiss' => str_repeat('c', 43)]], '', ['kind' => 'none'], $locale);
ownerLayoutEnd();
