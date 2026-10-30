<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/owner_layout.php';
require_once dirname(__DIR__) . '/includes/transaction.php';
require_once dirname(__DIR__) . '/includes/project_evidence_repository.php';
require_once dirname(__DIR__) . '/includes/project_evidence_source_repository.php';
require_once dirname(__DIR__) . '/includes/project_evidence_view.php';
require_once dirname(__DIR__) . '/includes/evidence_hub_undo.php';
require_once dirname(__DIR__) . '/includes/evidence_hub_owner_page_model.php';
require_once dirname(__DIR__) . '/includes/evidence_hub_owner_page_presentation.php';
require_once dirname(__DIR__) . '/includes/owner_project_evidence_presentation.php';

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
$sources = [];
$sourceValues = ['title' => '', 'url' => '', 'note' => ''];
$sourceErrors = [];
// Server view mode (B-07): GET ?mode=write[&part=...] writes; anything else reads.
$view = projectEvidenceViewFromQuery($_GET);
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $project = findAuthorizedProjectEvidenceForEdit($database, $context, $projectId);
    if ($project === null) {
        httpAbortHtml(404, 'Project not found.');
    }
    $values = $project['values'];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && is_array($_POST) && array_key_exists('action', $_POST)) {
        // Evidence sources (B-06): their own forms, distinguished by `action`.
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $action = $_POST['action'];
        if ($action === 'add_source') {
            if (array_diff(array_keys($_POST), ['csrf_token', 'action', 'title', 'url', 'note']) !== []) {
                httpAbortHtml(422, 'Invalid evidence source submission.');
            }
            $validation = validateProjectEvidenceSourceSubmission($_POST);
            $sourceValues = $validation['values'];
            $sourceErrors = $validation['errors'];
            if ($validation['source'] !== null) {
                try {
                    $added = runDatabaseTransaction($database, static fn (): ?int => addAuthorizedProjectEvidenceSource($database, $context, $projectId, $validation['source']));
                } catch (ProjectEvidenceSourceLimitException) {
                    $added = false;
                    $sourceErrors['source_limit'] = 'This project already has 10 sources. Remove one before adding another.';
                }
                if ($added === null) {
                    httpAbortHtml(404, 'Project not found.');
                }
                if (is_int($added)) {
                    header('Cache-Control: no-store');
                    httpRedirect('/owner/projects/' . $projectId . '/evidence#sources', 303);
                }
            }
            $view = ['mode' => 'read', 'part' => null];
            http_response_code(422);
        } elseif ($action === 'remove_source') {
            $sourceId = authorizationPositiveInteger($_POST['source_id'] ?? null);
            if (array_diff(array_keys($_POST), ['csrf_token', 'action', 'source_id']) !== [] || $sourceId === null) {
                httpAbortHtml(422, 'Invalid evidence source submission.');
            }
            if (!removeAuthorizedProjectEvidenceSource($database, $context, $projectId, $sourceId)) {
                httpAbortHtml(404, 'Source not found.');
            }
            header('Cache-Control: no-store');
            httpRedirect('/owner/projects/' . $projectId . '/evidence#sources', 303);
        } else {
            httpAbortHtml(422, 'Invalid evidence source submission.');
        }
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
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
            $save = runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $context, $projectId, $submitted));
            if ($save === null) {
                httpAbortHtml(404, 'Project not found.');
            }
            rememberEvidenceHubUndo($context, $projectId, $save, time());
            header('Cache-Control: no-store');
            httpRedirect('/owner/evidence-hub#project-' . $projectId, 303);
        }
        $view = projectEvidenceViewAfterTextError($fieldErrors);
        http_response_code(422);
    }
    $sources = listAuthorizedProjectEvidenceSources($database, $context, $projectId);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_project_evidence.php', 'project_evidence_request');
    http_response_code(503);
    $databaseError = 'Project evidence is temporarily unavailable.';
}

ownerLayoutStart('Edit project evidence', 'evidence_hub', true);
if ($databaseError !== '' || !is_array($project)) {
    $locale = ownerLocale();
    ?><div class="evidence-hub-editor" dir="<?php echo evidenceHubDirection($locale); ?>"><h1><?php echo ownerEscapeHtml(evidenceHubText('Edit project evidence', $locale)); ?></h1><p role="alert"><?php echo ownerEscapeHtml(evidenceHubText($databaseError, $locale)); ?></p></div><?php
} else {
    renderOwnerProjectEvidencePage($project, $values, $fieldErrors, $sources, $sourceValues, $sourceErrors, $view, ownerLocale());
}
ownerLayoutEnd();
