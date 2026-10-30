<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../Legacy/bootstrap.php';
require_once __DIR__ . '/../Legacy/cases/EvidenceHubContractV7Test.php';
require_once __DIR__ . '/../../includes/evidence_hub_owner_recommendations.php';
require_once __DIR__ . '/../../includes/evidence_hub_owner_page_model.php';
require_once __DIR__ . '/../../includes/evidence_hub_owner_page_presentation.php';
require_once __DIR__ . '/../../includes/owner_layout.php';
require_once __DIR__ . '/../../includes/project_evidence_view.php';
require_once __DIR__ . '/../../includes/csrf.php';

$database = (new ReflectionMethod(EvidenceHubContractV7Test::class,'database'))->invoke(null);
$paragraph = 'Readers could not locate available books because inconsistent catalogue labels and disconnected reservation records made the library portal difficult to navigate.';
$database->prepare('UPDATE projects SET measurable_outcome=:value WHERE id=1 AND portfolio_id=10')->execute(['value'=>'زاد الأداء كثيراً جداااااااااااااا']);
$database->prepare('UPDATE projects SET problem_statement=:value WHERE id=2 AND portfolio_id=10')->execute(['value'=>implode(' ',array_fill(0,5,'The app was slow today'))]);
$database->prepare('UPDATE projects SET problem_statement=:problem,personal_role=:role,measurable_outcome=:outcome WHERE id=3 AND portfolio_id=10')->execute(['problem'=>$paragraph,'role'=>$paragraph,'outcome'=>$paragraph]);
$context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1),10);
$state = buildAuthorizedEvidenceHubOwnerRecommendationState($database,$context,1767225600,'text-rules-visual-synthetic-hmac');
$model = buildAuthorizedEvidenceHubOwnerPageModel($database,$context,$state,'text-rules-visual-synthetic-hmac');
$locale = ($_GET['lang'] ?? null) === 'ar' ? 'ar' : 'en';
$_SESSION = ['csrf_token'=>str_repeat('a',64)];
ownerLayoutStart('Evidence Hub','evidence_hub',true,['locale'=>$locale]);
$model['technology_review'] ??= ['unmapped' => [], 'owner_mapped' => [], 'proposals' => [], 'categories' => [], 'map_options' => []];
$model['skill_gaps'] ??= [];
foreach ($model['projects'] as &$visualProject) {
    $visualUrls = projectEvidenceViewUrls($visualProject['id']);
    $visualProject += ['evidence_read_url' => $visualUrls['read'], 'evidence_write_url' => $visualUrls['write'], 'sources_url' => $visualUrls['sources'], 'sources' => []];
}
unset($visualProject);
renderEvidenceHubOwnerPage($state['contract'],$model,[],'',['kind'=>'none'],$locale);
ownerLayoutEnd();
