<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../includes/owner_actions.php';
require_once __DIR__ . '/../../includes/owner_layout.php';
require_once __DIR__ . '/../../includes/owner_experiences_presentation.php';
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
$locale = ownerLocale();
$_COOKIE['ather_owner_locale'] = $locale;
$_SERVER['REQUEST_URI'] = '/owner_experiences.php?lang=' . $locale;
$experiences = [];
foreach ([['internship', 'AI Engineering', 'Example Studio', 'Amman', '2026-09', ''], ['employment', 'Data Analysis', 'Example University', 'Amman', '2026-09', ''], ['employment', 'CEO', 'Example Studio', 'Amman', '2026-06', ''], ['training', 'Data Analysis', 'Example Academy', 'Amman, Jordan', '2025-09', '2026-02'], ['leadership', 'Ambassador', 'Example Institute', 'Jordan', '2025-04', '2026-03'], ['volunteer', 'Student', 'Example Learning', 'Online', '2024-08', '2025-02']] as $i => [$type, $role, $organization, $location, $start, $end]) {
    $experiences[] = ['id' => $i + 1, 'experience_type' => $type, 'role_title' => $role, 'organization' => $organization, 'location' => $location, 'start_month' => $start, 'end_month' => $end, 'is_current' => $end === '', 'description' => ''];
}
$state = $_GET['state'] ?? '';
$showExperienceForm = in_array($state, ['edit', 'add', 'error', 'conflict', 'saved'], true);
$editingExperience = $state === 'add' ? experienceFormDefaults() : $experiences[0];
$formMode = $state === 'add' ? 'add' : 'edit';
$fieldErrors = [];
if ($state === 'error') {
    $editingExperience['organization'] = '';
    $fieldErrors = ['organization' => 'Organization is required.'];
} elseif ($state === 'conflict') {
    $editingExperience['end_month'] = '2026-10';
    $fieldErrors = ['end_month' => 'End month must be empty for a current role.'];
}
if ($state === 'empty') $experiences = [];
$formErrors = array_values($fieldErrors);
$pageMessage = $state === 'saved' ? 'Experience record updated successfully.' : '';
ownerLayoutStart('Experience', 'experiences', true, ['account_name' => 'Sami Ahmad']);
renderOwnerExperiencesPage($experiences, $editingExperience, $showExperienceForm, $formMode, '2026-10', $pageMessage, '', $formErrors, $fieldErrors);
ownerLayoutEnd();
