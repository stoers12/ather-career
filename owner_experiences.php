<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/owner_actions.php';
require_once __DIR__ . '/includes/portfolio_scoped_data.php';
require_once __DIR__ . '/includes/owner_layout.php';

startOwnerSession();

$experiences = [];
$formErrors = [];
$pageMessage = '';
$databaseError = '';
$formMode = 'add';
$editingExperience = experienceFormDefaults();

try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $pageMessage = takeExperienceSuccessFlash();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $result = handleAuthorizedExperienceAction($database, $context, $_POST);
        $formErrors = $result['errors'];
        $formMode = $result['form_mode'];
        $editingExperience = $result['editing_experience'];
        if ($result['redirect'] !== null) {
            header('Location: ' . $result['redirect'], true, 303);
            exit;
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
ownerLayoutStart('Experience', 'experiences');
?>
<div class="admin-page-header"><div class="admin-page-header-copy"><p class="admin-eyebrow">Content</p><h1 class="admin-page-title">Experience</h1><p class="admin-page-description">Add, edit, and remove experience records from your private Portfolio workspace.</p></div><div class="admin-page-header-actions"><a class="button-primary" href="owner_experiences.php?add=1">+ Add Experience</a><a class="button-secondary" href="owner_preview.php">Private Preview</a></div></div>
<?php if ($databaseError !== ''): ?><p class="status-message error" role="alert"><?php echo ownerEscapeHtml($databaseError); ?></p><?php endif; ?>
<?php if ($pageMessage !== ''): ?><p class="status-message" role="status"><?php echo ownerEscapeHtml($pageMessage); ?></p><?php endif; ?>
<?php if ($formErrors !== []): ?><ul class="status-message error" role="alert"><?php foreach ($formErrors as $error): ?><li><?php echo ownerEscapeHtml($error); ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($showExperienceForm): ?><div class="project-form-card"><div class="form-card-heading"><h2><?php echo $formMode === 'edit' ? 'Edit Experience' : 'Add Experience'; ?></h2></div><form class="project-form" method="POST" action="owner_experiences.php"><input type="hidden" name="action" value="<?php echo $formMode === 'edit' ? 'update' : 'add'; ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $editingExperience['id']; ?>"><?php endif; ?><div class="form-grid"><label class="form-field" for="experience_type"><span>Type</span><select id="experience_type" name="experience_type" required><option value="">Select type</option><?php foreach (experienceTypeOptions() as $value => $label): ?><option value="<?php echo ownerEscapeHtml($value); ?>"<?php echo (string) $editingExperience['experience_type'] === $value ? ' selected' : ''; ?>><?php echo ownerEscapeHtml($label); ?></option><?php endforeach; ?></select></label><label class="form-field" for="role_title"><span>Role / Title</span><input type="text" id="role_title" name="role_title" value="<?php echo ownerEscapeHtml((string) $editingExperience['role_title']); ?>" maxlength="<?php echo EXPERIENCE_ROLE_TITLE_MAX_LENGTH; ?>" required></label><label class="form-field" for="organization"><span>Organization</span><input type="text" id="organization" name="organization" value="<?php echo ownerEscapeHtml((string) $editingExperience['organization']); ?>" maxlength="<?php echo EXPERIENCE_ORGANIZATION_MAX_LENGTH; ?>" required></label><label class="form-field" for="location"><span>Location <small>(optional)</small></span><input type="text" id="location" name="location" value="<?php echo ownerEscapeHtml((string) $editingExperience['location']); ?>" maxlength="<?php echo EXPERIENCE_LOCATION_MAX_LENGTH; ?>"></label><label class="form-field" for="start_month"><span>Start month</span><input type="month" id="start_month" name="start_month" value="<?php echo ownerEscapeHtml((string) $editingExperience['start_month']); ?>" required></label><label class="form-field" for="end_month"><span>End month</span><input type="month" id="end_month" name="end_month" value="<?php echo ownerEscapeHtml((string) $editingExperience['end_month']); ?>" aria-describedby="end-month-help"></label><label class="checkbox-field form-field-full"><input type="checkbox" name="is_current" value="1"<?php echo experienceIsCurrent($editingExperience['is_current'] ?? false) ? ' checked' : ''; ?>> <span>Currently here</span></label><small id="end-month-help" class="form-hint form-field-full">End month is required unless this is your current role.</small><label class="form-field form-field-full" for="description"><span>Description <small>(optional)</small></span><textarea id="description" name="description" maxlength="<?php echo EXPERIENCE_DESCRIPTION_MAX_LENGTH; ?>"><?php echo ownerEscapeHtml((string) $editingExperience['description']); ?></textarea></label></div><div class="form-actions"><a class="button-secondary" href="owner_experiences.php">Cancel</a><button class="button-primary" type="submit"><?php echo $formMode === 'edit' ? 'Update Experience' : 'Add Experience'; ?></button></div></form></div><?php endif; ?>
<div class="section-heading-row project-list-heading"><div><h2>Existing experience</h2><span class="muted"><?php echo count($experiences); ?> <?php echo count($experiences) === 1 ? 'record' : 'records'; ?></span></div></div>
<?php if ($experiences === []): ?><div class="empty-state admin-empty"><strong>No experience records yet</strong><span>Add an experience record when you are ready.</span></div><?php endif; ?>
<div class="admin-project-grid"><?php foreach ($experiences as $experience): ?><article><div class="project-card-content"><h3><?php echo ownerEscapeHtml((string) $experience['role_title']); ?></h3><p class="project-category"><?php echo ownerEscapeHtml(experienceTypeOptions()[(string) $experience['experience_type']] ?? (string) $experience['experience_type']); ?></p><p><?php echo ownerEscapeHtml((string) $experience['organization']); ?><?php if ((string) ($experience['location'] ?? '') !== ''): ?> · <?php echo ownerEscapeHtml((string) $experience['location']); ?><?php endif; ?></p><p><?php echo ownerEscapeHtml((string) $experience['start_month']); ?> – <?php echo experienceIsCurrent($experience['is_current'] ?? false) ? 'Current' : ownerEscapeHtml((string) $experience['end_month']); ?></p><?php if ((string) ($experience['description'] ?? '') !== ''): ?><p><?php echo ownerEscapeHtml((string) $experience['description']); ?></p><?php endif; ?><div class="card-actions"><a class="project-edit" href="owner_experiences.php?edit=<?php echo (int) $experience['id']; ?>">Edit</a><form method="POST" action="owner_experiences.php"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="id" value="<?php echo (int) $experience['id']; ?>"><button type="submit" class="button-danger">Delete</button></form></div></div></article><?php endforeach; ?></div>
<?php ownerLayoutEnd();
