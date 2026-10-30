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

startOwnerSession();
httpRegisterExceptionBoundary('owner_experiences.php');
httpRequireMethod(['GET', 'HEAD', 'POST']);

$experiences = [];
$formErrors = [];
$fieldErrors = [];
$pageMessage = '';
$databaseError = '';
$formMode = 'add';
$editingExperience = experienceFormDefaults();
$experienceMaximumMonth = experienceValidationReferenceMonth();

try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $ownerName = (string) ((loadAuthorizedPersonalInfo($database, $context) ?: [])['full_name'] ?? '');
    $pageMessage = takeExperienceSuccessFlash();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $result = handleAuthorizedExperienceAction($database, $context, $_POST);
        $formErrors = $result['errors'];
        $fieldErrors = $result['field_errors'];
        $formMode = $result['form_mode'];
        $editingExperience = $result['editing_experience'];
        if ($result['redirect'] !== null) {
            httpRedirect($result['redirect']);
        }
        if ($formErrors !== []) {
            http_response_code($result['status']);
        }
    } elseif (isset($_GET['edit'])) {
        $experienceId = experienceActionId($_GET['edit']);
        $selectedExperience = $experienceId === null ? null : findAuthorizedExperience($database, $context, $experienceId);
        if ($selectedExperience === null) {
            $formErrors[] = 'Experience record not found.';
        } else {
            $editingExperience = $selectedExperience;
            $editingExperience['location'] = (string) ($selectedExperience['location'] ?? '');
            $editingExperience['end_month'] = (string) ($selectedExperience['end_month'] ?? '');
            $editingExperience['description'] = (string) ($selectedExperience['description'] ?? '');
            $formMode = 'edit';
        }
    }

    $experiences = listAuthorizedExperiences($database, $context);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_experiences.php', 'owner_experience_request');
    http_response_code(503);
    $databaseError = 'Experience management is temporarily unavailable.';
}

$showExperienceForm = $formMode === 'edit' || $formErrors !== [] || isset($_GET['add']);
require __DIR__ . '/../includes/owner_experiences_presentation.php';
ownerLayoutStart('Experience', 'experiences', true, ['account_name' => $ownerName ?? '']);
renderOwnerExperiencesPage($experiences, $editingExperience, $showExperienceForm, $formMode, $experienceMaximumMonth, $pageMessage, $databaseError, $formErrors, $fieldErrors);
ownerLayoutEnd();
