<?php
require_once __DIR__ . '/includes/admin_session.php';
require_once __DIR__ . '/includes/csrf.php';

startAdminSession();
requireAdminAuthentication();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/project_actions.php';

function escapeProjectAdminHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$projects = [];
$formErrors = [];
$pageMessage = '';
$databaseError = '';
$formMode = 'add';
$editingProject = projectFormDefaults();

try {
    $database = getDatabaseConnection();

    $pageMessage = takeProjectSuccessFlash();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $result = handleProjectAction($database, $_POST, $_FILES);
        $formErrors = $result['errors'];
        $formMode = $result['form_mode'];
        $editingProject = $result['editing_project'];
        if ($result['redirect'] !== null) {
            header('Location: ' . $result['redirect']);
            exit;
        }
    } elseif (isset($_GET['edit'])) {
        $projectId = projectActionId($_GET['edit']);
        if ($projectId === null) {
            $formErrors[] = 'Please provide a valid project ID.';
        } else {
            $statement = $database->prepare('SELECT id, title, category, description, github_url, image_path FROM projects WHERE id = :id');
            $statement->execute(['id' => $projectId]);
            $selectedProject = $statement->fetch();
            if ($selectedProject === false) {
                $formErrors[] = 'Project not found.';
            } else {
                $editingProject = $selectedProject;
                $formMode = 'edit';
            }
        }
    }

    $statement = $database->prepare('SELECT id, title, category, description, github_url, image_path FROM projects ORDER BY created_at ASC, id ASC');
    $statement->execute();
    $projects = $statement->fetchAll();
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'projects.php', 'project_request');
    http_response_code(503);
    $databaseError = 'Project management is temporarily unavailable.';
}

$activePage = 'projects';
$showProjectForm = $formMode === 'edit' || $formErrors !== [] || isset($_GET['add']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - My Portfolio</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js" defer></script>
</head>
<body>
<div class="admin-layout">
    <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
    <main class="admin-content">
        <section>
            <div class="admin-page-header">
                <div class="admin-page-header-copy">
                    <p class="admin-eyebrow">Content</p>
                    <h1 class="admin-page-title">Projects</h1>
                    <p class="admin-page-description">Add, edit, and remove projects shown on your public portfolio.</p>
                </div>
                <div class="admin-page-header-actions">
                    <button class="button-primary" type="button" data-project-form-toggle aria-expanded="<?php echo $showProjectForm ? 'true' : 'false'; ?>" aria-controls="project-form-card">+ Add Project</button>
                    <a class="button-secondary" href="index.php#projects" target="_blank" rel="noopener">Preview Portfolio ↗</a>
                </div>
            </div>
            <?php if ($databaseError !== ''): ?><p class="status-message error" role="alert"><?php echo escapeProjectAdminHtml($databaseError); ?></p><?php endif; ?>
            <?php if ($pageMessage !== ''): ?><p class="status-message" data-toast role="status"><?php echo escapeProjectAdminHtml($pageMessage); ?></p><?php endif; ?>
            <?php if ($formErrors !== []): ?><ul class="status-message error" role="alert"><?php foreach ($formErrors as $error): ?><li><?php echo escapeProjectAdminHtml($error); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <div id="project-form-card" class="project-form-card" data-project-form<?php echo $showProjectForm ? '' : ' hidden'; ?>>
                <div class="form-card-heading"><h2><?php echo $formMode === 'edit' ? 'Edit Project' : 'Add New Project'; ?></h2><p><?php echo $formMode === 'edit' ? 'Update the details displayed on your public portfolio.' : 'Add a project that will appear on your public portfolio.'; ?></p></div>
                <form class="project-form" method="POST" action="projects.php" enctype="multipart/form-data" id="project-form">
                    <input type="hidden" name="action" value="<?php echo $formMode === 'edit' ? 'update' : 'add'; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeProjectAdminHtml(getCsrfToken()); ?>">
                    <?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $editingProject['id']; ?>"><?php endif; ?>
                    <div class="form-grid">
                        <label class="form-field" for="title"><span>Title</span><input type="text" id="title" name="title" value="<?php echo escapeProjectAdminHtml((string) $editingProject['title']); ?>" required></label>
                        <label class="form-field" for="category"><span>Category</span><input type="text" id="category" name="category" value="<?php echo escapeProjectAdminHtml((string) $editingProject['category']); ?>" required></label>
                        <label class="form-field form-field-full" for="description"><span>Description</span><textarea id="description" name="description" required><?php echo escapeProjectAdminHtml((string) $editingProject['description']); ?></textarea></label>
                        <label class="form-field form-field-full" for="github_url"><span>GitHub URL</span><input type="url" id="github_url" name="github_url" value="<?php echo escapeProjectAdminHtml((string) $editingProject['github_url']); ?>" required></label>
                        <div class="form-field form-field-full">
                            <span class="field-label">Project Image <em>(optional)</em></span>
                            <input class="visually-hidden" type="file" id="project_image" name="project_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-project-image-input aria-describedby="project-image-help project-image-status">
                            <button class="button-secondary file-picker" type="button" data-file-trigger="project_image">Choose Image</button>
                            <span id="project-image-help" class="form-hint">JPG, PNG or WEBP · Maximum 2 MB</span>
                            <span id="project-image-status" class="file-selection-status" aria-live="polite"></span>
                        </div>
                        <?php if ($formMode === 'edit' && $editingProject['image_path'] !== null): ?>
                            <div class="current-project-image form-field-full"><img class="project-image admin-preview" src="<?php echo escapeProjectAdminHtml($editingProject['image_path']); ?>" alt="Current image for <?php echo escapeProjectAdminHtml((string) $editingProject['title']); ?>"><label class="checkbox-field"><input type="checkbox" name="remove_image" value="1"> <span>Remove current image</span></label></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-actions">
                        <?php if ($formMode === 'edit'): ?><a class="button-secondary" href="projects.php">Cancel</a><?php else: ?><button class="button-secondary" type="button" data-project-form-cancel>Cancel</button><?php endif; ?>
                        <button class="button-primary" type="submit"><?php echo $formMode === 'edit' ? 'Update Project' : 'Add Project'; ?></button>
                    </div>
                </form>
            </div>
            <div class="section-heading-row project-list-heading"><div><h2>Existing projects</h2><span class="muted"><?php echo count($projects); ?> <?php echo count($projects) === 1 ? 'project' : 'projects'; ?></span></div><div class="search-field"><label class="visually-hidden" for="project-search">Search projects</label><input id="project-search" type="search" placeholder="Search projects…" data-project-search></div></div>
            <?php if ($projects === []): ?><div class="empty-state admin-empty"><strong>No projects yet</strong><span>Add a project above to start building your showcase.</span></div><?php endif; ?>
            <div class="admin-project-grid" data-project-list><?php foreach ($projects as $project): ?>
                <article data-project-card data-search="<?php echo escapeProjectAdminHtml(strtolower($project['title'] . ' ' . $project['category'] . ' ' . $project['description'])); ?>">
                    <div class="project-thumbnail<?php echo $project['image_path'] !== null ? ' has-image' : ' no-image'; ?>">
                        <?php if ($project['image_path'] !== null): ?><img src="<?php echo escapeProjectAdminHtml($project['image_path']); ?>" alt="<?php echo escapeProjectAdminHtml($project['title']); ?>"><?php else: ?><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="8.5" cy="9" r="1.5"></circle><path d="m4 18 5-5 3.5 3.5 2.5-2.5L20 19"></path></svg><span>No image</span><?php endif; ?>
                    </div>
                    <div class="project-card-content">
                        <h3><?php echo escapeProjectAdminHtml($project['title']); ?></h3>
                        <p class="project-category"><?php echo escapeProjectAdminHtml($project['category']); ?></p>
                        <p><?php echo escapeProjectAdminHtml($project['description']); ?></p>
                        <div class="card-actions"><div class="card-primary-actions"><a class="project-edit" href="projects.php?edit=<?php echo (int) $project['id']; ?>">Edit</a><a class="project-github" href="<?php echo escapeProjectAdminHtml($project['github_url']); ?>" target="_blank" rel="noopener noreferrer">View on GitHub ↗</a></div>
                        <form method="POST" action="projects.php" data-confirm="Delete this project? This cannot be undone.">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="csrf_token" value="<?php echo escapeProjectAdminHtml(getCsrfToken()); ?>">
                            <input type="hidden" name="id" value="<?php echo (int) $project['id']; ?>">
                            <button type="submit" class="button-danger project-delete">Delete</button>
                        </form></div>
                    </div>
                </article>
            <?php endforeach; ?></div>
        </section>
    </main>
</div>
</body>
</html>
