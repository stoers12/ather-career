<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../includes/owner_actions.php';
require_once __DIR__ . '/../../includes/owner_layout.php';
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
$locale = ownerLocale();
$_COOKIE['ather_owner_locale'] = $locale;
$_SERVER['REQUEST_URI'] = '/owner_projects.php?lang=' . $locale;
$ownerName = 'Sami Ahmad';
$projects = [];
foreach ([['HR Dashboard', 'Data Analysis', ['Python', 'Excel', 'Power BI', 'SQL', 'pandas']], ['Portfolio Platform View', 'Full Stack Development', ['PHP', 'Docker', 'JavaScript', 'CSS', 'MySQL']], ['CareerFit AI', 'Machine Learning / AI', ['Python', 'FastAPI', 'PostgreSQL']], ['Core Methodologies: LSA & TF-IDF', 'Machine Learning', ['Python', 'Scikit-learn', 'NumPy']]] as $i => [$title, $category, $technologies]) {
    $projects[] = ['id' => $i + 1, 'title' => $title, 'category' => $category, 'description' => 'An end-to-end training project. I prepared a sample dataset, then built an interactive dashboard to support practical decisions.', 'technologies' => $technologies, 'live_url' => '', 'github_url' => '', 'image_path' => null, 'is_published' => $i < 3 ? 1 : 0];
}
$state = $_GET['state'] ?? '';
$formMode = $state === 'new' ? 'add' : 'edit';
$showProjectForm = in_array($state, ['edit', 'new', 'error'], true);
$editingProject = $formMode === 'add' ? projectFormDefaults() : $projects[0];
if ($formMode === 'edit') $editingProject['technologies'] = implode("\n", $editingProject['technologies']);
$portfolioPublished = true;
$fieldErrors = $state === 'error' ? ['live_url' => 'Please enter a valid HTTP or HTTPS URL.'] : [];
$formErrors = array_values($fieldErrors);
if ($state === 'error') $editingProject['live_url'] = 'ftp://example.invalid/demo';
if ($state === 'empty') $projects = [];
$pageMessage = $databaseError = '';
require __DIR__ . '/../../includes/owner_projects_presentation.php';
ownerLayoutStart('Projects', 'projects', true, ['account_name' => $ownerName]);
renderOwnerProjectsPage($projects, $editingProject, $portfolioPublished, $showProjectForm, $formMode, $pageMessage, $databaseError, $formErrors, $fieldErrors);
ownerLayoutEnd();
