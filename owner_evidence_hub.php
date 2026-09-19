<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/owner_layout.php';
require_once __DIR__ . '/includes/evidence_hub_owner_presentation.php';
require_once __DIR__ . '/includes/evidence_hub_owner_recommendations.php';
require_once __DIR__ . '/includes/evidence_hub_recommendation_action_tokens.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_evidence_hub.php');
httpRequireMethod(['GET', 'HEAD', 'POST']);

if (evidenceHubOwnerRouteHasTrailingSlash()) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        httpAbortHtml(409, 'لم تعد التوصية متاحة. أعد تحميل مركز الأدلة.');
    }
    header('Cache-Control: no-store');
    httpRedirect('/owner/evidence-hub', 302);
}

$contract = null;
$actionTokens = [];
$feedback = '';
$error = '';
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $request = evidenceHubOwnerRecommendationActionRequest();
        $tokenRecord = consumeEvidenceHubRecommendationActionToken($context, $request['action'], $request['action_token']);
        if ($tokenRecord === null || !executeAuthorizedEvidenceHubRecommendationAction($database, $context, $tokenRecord, time())) {
            httpAbortHtml(409, 'لم تعد التوصية متاحة. أعد تحميل مركز الأدلة.');
        }
        setEvidenceHubRecommendationActionFeedback($request['action'] === 'snooze' ? 'تم تأجيل التوصية لمدة 14 يومًا.' : 'تم تجاهل التوصية.');
        header('Cache-Control: no-store');
        httpRedirect('/owner/evidence-hub', 303);
    }
    $state = buildConfiguredAuthorizedEvidenceHubOwnerRecommendationState($database, $context, time());
    $contract = $state['contract'];
    $feedback = takeEvidenceHubRecommendationActionFeedback();
    foreach ($contract['recommendations'] as $recommendation) {
        $resolved = resolveAuthorizedEvidenceHubOwnerRecommendation($state, $recommendation['recommendation_key']);
        if (!is_array($resolved)) {
            throw new RuntimeException('Evidence Hub visible recommendation resolution failed.');
        }
        $actionTokens[] = [
            'snooze' => issueEvidenceHubRecommendationActionToken($context, $resolved['candidate'], 'snooze'),
            'dismiss' => issueEvidenceHubRecommendationActionToken($context, $resolved['candidate'], 'dismiss'),
        ];
    }
} catch (EvidenceHubHmacConfigurationException $exception) {
    reportApplicationError($exception, 'owner_evidence_hub.php', 'owner_evidence_hub_configuration');
    http_response_code(503);
    $error = 'Evidence Hub is temporarily unavailable.';
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_evidence_hub.php', 'owner_evidence_hub_load');
    http_response_code(503);
    $error = 'Evidence Hub is temporarily unavailable.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    httpSetHtmlResponse(http_response_code());
    exit;
}

ownerLayoutStart('مركز الأدلة', 'evidence_hub');
if ($error !== '' || !is_array($contract)) {
    renderEvidenceHubOwnerSafeError();
} else {
    renderEvidenceHubOwnerPresentation($contract, $actionTokens, $feedback);
}

/** @return array{action:string, action_token:string} */
function evidenceHubOwnerRecommendationActionRequest(): array
{
    requireValidCsrfToken($_POST['csrf_token'] ?? null);
    $expected = ['csrf_token', 'action', 'action_token'];
    if (!is_array($_POST) || array_is_list($_POST) || array_diff(array_keys($_POST), $expected) !== [] || array_diff($expected, array_keys($_POST)) !== []) {
        httpAbortHtml(422, 'إجراء التوصية غير صالح.');
    }
    $action = $_POST['action'];
    $token = $_POST['action_token'];
    if (!is_string($action) || !in_array($action, ['snooze', 'dismiss'], true) || !is_string($token)) {
        httpAbortHtml(422, 'إجراء التوصية غير صالح.');
    }
    return ['action' => $action, 'action_token' => $token];
}
ownerLayoutEnd();

function evidenceHubOwnerRouteHasTrailingSlash(): bool
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? null;
    if (!is_string($requestUri)) {
        return false;
    }
    $path = explode('?', $requestUri, 2)[0];

    return $path === '/owner/evidence-hub/';
}
