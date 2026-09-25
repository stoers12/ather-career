<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/owner_layout.php';
require_once __DIR__ . '/includes/transaction.php';
require_once __DIR__ . '/includes/project_evidence_repository.php';
require_once __DIR__ . '/includes/evidence_hub_owner_page_model.php';
require_once __DIR__ . '/includes/evidence_hub_owner_page_presentation.php';
require_once __DIR__ . '/includes/owner_project_evidence_presentation.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_project_evidence.php');
httpRequireMethod(['GET', 'POST']);

$path = explode('?', (string) ($_SERVER['REQUEST_URI'] ?? ''), 2)[0];
if (preg_match('@^/owner/projects/([1-9][0-9]*)/evidence(/?)$@D', $path, $matches) !== 1) {
    httpAbortHtml(404, 'Project not found.');
}
$projectId = authorizationPositiveInteger($matches[1]);
if ($projectId === null) {
    httpAbortHtml(404, 'Project not found.');
}
if ($matches[2] === '/') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        httpAbortHtml(409, 'Use the canonical evidence address.');
    }
    httpRedirect('/owner/projects/' . $projectId . '/evidence', 302);
}

$fieldErrors = [];
$databaseError = '';
$project = null;
$values = [];
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $project = findAuthorizedProjectEvidenceForEdit($database, $context, $projectId);
    if ($project === null) {
        httpAbortHtml(404, 'Project not found.');
    }
    $values = $project['values'];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $expected = ['csrf_token', 'problem', 'personal_role', 'measurable_outcome'];
        if (!is_array($_POST) || array_diff(array_keys($_POST), $expected) !== [] || array_diff($expected, array_keys($_POST)) !== []) {
            httpAbortHtml(422, 'Invalid evidence submission.');
        }
        $submitted = [];
        foreach (projectEvidenceLogicalToStorageFields() as $logical => $storage) {
            if (!is_string($_POST[$logical])) {
                $fieldErrors[$logical] = 'Please enter text for this field.';
                $submitted[$logical] = '';
            } else {
                $submitted[$logical] = $_POST[$logical];
                $evaluation = evaluateEvidenceText($storage, $submitted[$logical]);
                if ($evaluation['storage_validity'] !== 'valid') {
                    $fieldErrors[$logical] = evidenceHubOwnerPageFieldReason($evaluation, $logical);
                }
            }
        }
        $values = $submitted;
        if ($fieldErrors === []) {
            $saved = runDatabaseTransaction($database, static fn (): bool => saveAuthorizedProjectEvidence($database, $context, $projectId, $submitted));
            if (!$saved) {
                httpAbortHtml(404, 'Project not found.');
            }
            $_SESSION['owner_evidence_saved'] = true;
            httpRedirect('/owner/evidence-hub#project-' . $projectId, 303);
        }
        http_response_code(422);
    }
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_project_evidence.php', 'project_evidence_request');
    http_response_code(503);
    $databaseError = 'Project evidence is temporarily unavailable.';
}

ownerLayoutStart('Edit project evidence', 'evidence_hub', true);
if ($databaseError !== '' || !is_array($project)) {
    ?><div class="evidence-hub-editor"><h1>Edit project evidence</h1><p role="alert"><?php echo ownerEscapeHtml($databaseError); ?></p></div><?php
} else {
    renderOwnerProjectEvidenceForm($project, $values, $fieldErrors);
}
ownerLayoutEnd();
