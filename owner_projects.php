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
require_once __DIR__ . '/includes/operational_security.php';

startOwnerSession();

$projects = [];
$formErrors = [];
$pageMessage = '';
$databaseError = '';
$formMode = 'add';
$editingProject = projectFormDefaults();

try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
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
            $formMode = $result['form_mode'];
            $editingProject = $result['editing_project'];
            if ($result['redirect'] !== null) {
                header('Location: ' . $result['redirect'], true, 303);
                exit;
            }
        }
    } elseif (isset($_GET['edit'])) {
        $projectId = ownerActionId($_GET['edit']);
        $selectedProject = $projectId === null ? null : findAuthorizedProject($database, $context, $projectId);
        if ($selectedProject === null) {
            $formErrors[] = 'Project not found.';
        } else {
            $editingProject = $selectedProject;
            $formMode = 'edit';
        }
    }

    $projects = listAuthorizedProjects($database, $context);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_projects.php', 'owner_project_request');
    http_response_code(503);
    $databaseError = 'Project management is temporarily unavailable.';
}

$showProjectForm = $formMode === 'edit' || $formErrors !== [] || isset($_GET['add']);
ownerLayoutStart('Projects', 'projects');
?>
<div class="admin-page-header"><div class="admin-page-header-copy"><p class="admin-eyebrow">Content</p><h1 class="admin-page-title">Projects</h1><p class="admin-page-description">Add, edit, and remove projects from your private Portfolio workspace.</p></div><div class="admin-page-header-actions"><a class="button-primary" href="owner_projects.php?add=1">+ Add Project</a><a class="button-secondary" href="owner_preview.php#projects">Private Preview</a></div></div>
<?php if ($databaseError !== ''): ?><p class="status-message error" role="alert"><?php echo ownerEscapeHtml($databaseError); ?></p><?php endif; ?>
<?php if ($pageMessage !== ''): ?><p class="status-message" role="status"><?php echo ownerEscapeHtml($pageMessage); ?></p><?php endif; ?>
<?php if ($formErrors !== []): ?><ul class="status-message error" role="alert"><?php foreach ($formErrors as $error): ?><li><?php echo ownerEscapeHtml($error); ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($showProjectForm): ?><div class="project-form-card"><div class="form-card-heading"><h2><?php echo $formMode === 'edit' ? 'Edit Project' : 'Add New Project'; ?></h2></div><form class="project-form" method="POST" action="owner_projects.php" enctype="multipart/form-data"><input type="hidden" name="action" value="<?php echo $formMode === 'edit' ? 'update' : 'add'; ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $editingProject['id']; ?>"><?php endif; ?><div class="form-grid"><label class="form-field" for="title"><span>Title</span><input type="text" id="title" name="title" value="<?php echo ownerEscapeHtml((string) $editingProject['title']); ?>" maxlength="<?php echo PROJECT_TITLE_MAX_LENGTH; ?>" required></label><label class="form-field" for="category"><span>Category</span><input type="text" id="category" name="category" value="<?php echo ownerEscapeHtml((string) $editingProject['category']); ?>" maxlength="<?php echo PROJECT_CATEGORY_MAX_LENGTH; ?>" required></label><label class="form-field form-field-full" for="description"><span>Description</span><textarea id="description" name="description" required><?php echo ownerEscapeHtml((string) $editingProject['description']); ?></textarea></label><label class="form-field form-field-full" for="github_url"><span>GitHub URL</span><input type="url" id="github_url" name="github_url" value="<?php echo ownerEscapeHtml((string) $editingProject['github_url']); ?>" maxlength="<?php echo PROJECT_GITHUB_URL_MAX_LENGTH; ?>" required></label><label class="form-field form-field-full" for="project_image"><span>Project Image (optional)</span><input type="file" id="project_image" name="project_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label><?php if ($formMode === 'edit' && $editingProject['image_path'] !== null): ?><label class="checkbox-field"><input type="checkbox" name="remove_image" value="1"> <span>Remove current image</span></label><?php endif; ?></div><div class="form-actions"><a class="button-secondary" href="owner_projects.php">Cancel</a><button class="button-primary" type="submit"><?php echo $formMode === 'edit' ? 'Update Project' : 'Add Project'; ?></button></div></form></div><?php endif; ?>
<div class="section-heading-row project-list-heading"><div><h2>Existing projects</h2><span class="muted"><?php echo count($projects); ?> <?php echo count($projects) === 1 ? 'project' : 'projects'; ?></span></div></div>
<?php if ($projects === []): ?><div class="empty-state admin-empty"><strong>No projects yet</strong><span>Add a project to start building your showcase.</span></div><?php endif; ?>
<div class="admin-project-grid"><?php foreach ($projects as $project): ?><article><?php if ((string) ($project['image_path'] ?? '') !== ''): ?><img class="project-thumbnail" src="owner_media.php?type=project&amp;id=<?php echo (int) $project['id']; ?>" alt="<?php echo ownerEscapeHtml((string) $project['title']); ?>"><?php endif; ?><div class="project-card-content"><h3><?php echo ownerEscapeHtml((string) $project['title']); ?></h3><p class="project-category"><?php echo ownerEscapeHtml((string) $project['category']); ?></p><p><?php echo ownerEscapeHtml((string) $project['description']); ?></p><div class="card-actions"><a class="project-edit" href="owner_projects.php?edit=<?php echo (int) $project['id']; ?>">Edit</a><form method="POST" action="owner_projects.php"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="id" value="<?php echo (int) $project['id']; ?>"><button type="submit" class="button-danger">Delete</button></form></div></div></article><?php endforeach; ?></div>
<?php ownerLayoutEnd();
