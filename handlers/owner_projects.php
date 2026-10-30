<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/error_reporting.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/owner_actions.php';
require_once dirname(__DIR__) . '/includes/portfolio_scoped_data.php';
require_once dirname(__DIR__) . '/includes/owner_layout.php';
require_once dirname(__DIR__) . '/includes/operational_security.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_projects.php');
httpRequireMethod(['GET', 'HEAD', 'POST']);

$projects = [];
$formErrors = [];
$fieldErrors = [];
$pageMessage = '';
$databaseError = '';
$formMode = 'add';
$editingProject = projectFormDefaults();
$portfolioPublished = false;

try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $ownerName = (string) ((loadAuthorizedPersonalInfo($database, $context) ?: [])['full_name'] ?? '');
    $pageMessage = takeProjectSuccessFlash();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $mayMutate = true;
        $hasUpload = isset($_FILES['project_image']) && (($_FILES['project_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
        if ($hasUpload) {
            try {
                $limit = consumeOwnerUploadRateLimit($context);
                if (!$limit['allowed']) {
                    reportRateLimitDenial('owner_upload', $context);
                    http_response_code(429);
                    header('Retry-After: ' . $limit['retry_after']);
                    $formErrors[] = 'Please wait before uploading another image.';
                    $mayMutate = false;
                }
            } catch (Throwable $exception) {
                reportApplicationError($exception, 'owner_projects.php', 'owner_upload_rate_limit');
                http_response_code(503);
                $formErrors[] = 'Image uploads are temporarily unavailable.';
                $mayMutate = false;
            }
        }
        if ($mayMutate) {
            $result = handleAuthorizedProjectAction($database, $context, $_POST, $_FILES);
            $formErrors = $result['errors'];
            $fieldErrors = $result['field_errors'];
            $formMode = $result['form_mode'];
            $editingProject = $result['editing_project'];
            if ($result['redirect'] !== null) {
                httpRedirect($result['redirect']);
            }
            if ($formErrors !== []) {
                http_response_code($result['status']);
            }
        }
    } elseif (isset($_GET['edit'])) {
        $projectId = ownerActionId($_GET['edit']);
        $selectedProject = $projectId === null ? null : findAuthorizedProject($database, $context, $projectId);
        if ($selectedProject === null) {
            $formErrors[] = 'Project not found.';
        } else {
            $editingProject = $selectedProject;
            $editingProject['technologies'] = projectTechnologiesFormValue($selectedProject['technologies'] ?? []);
            $formMode = 'edit';
        }
    }

    $projects = listAuthorizedProjects($database, $context);
    $portfolioPublished = ownedPublicLifecycleState($database, $context)['is_published'] === 1;
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_projects.php', 'owner_project_request');
    http_response_code(503);
    $databaseError = 'Project management is temporarily unavailable.';
}

$showProjectForm = $formMode === 'edit' || $formErrors !== [] || isset($_GET['add']);
require __DIR__ . '/../includes/owner_projects_presentation.php';
ownerLayoutStart('Projects', 'projects', true, ['account_name' => $ownerName ?? '']);
renderOwnerProjectsPage($projects, $editingProject, $portfolioPublished, $showProjectForm, $formMode, $pageMessage, $databaseError, $formErrors, $fieldErrors);
ownerLayoutEnd();
