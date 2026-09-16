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

startOwnerSession();
httpRegisterExceptionBoundary('owner_evidence_hub.php');
httpRequireMethod(['GET', 'HEAD']);

if (evidenceHubOwnerRouteHasTrailingSlash()) {
    header('Cache-Control: no-store');
    httpRedirect('/owner/evidence-hub', 302);
}

$contract = null;
$error = '';
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $state = buildConfiguredAuthorizedEvidenceHubOwnerRecommendationState($database, $context, time());
    $contract = $state['contract'];
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

ownerLayoutStart('Evidence Hub', 'evidence_hub');
if ($error !== '' || !is_array($contract)) {
    renderEvidenceHubOwnerSafeError();
} else {
    renderEvidenceHubOwnerPresentation($contract);
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
