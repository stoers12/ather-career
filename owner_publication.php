<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/public_lifecycle.php';
require_once __DIR__ . '/includes/owner_publication_presentation.php';
require_once __DIR__ . '/includes/operational_security.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_publication.php');
httpRequireMethod(['GET', 'HEAD', 'POST']);

$state = null;
$message = '';
$errors = [];
try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        $action = $_POST['action'] ?? null;
        if (in_array($action, ['set_slug', 'publish', 'unpublish'], true)) {
            try {
                $limit = consumeOwnerPublicationRateLimit($context);
            } catch (Throwable $exception) {
                reportApplicationError($exception, 'owner_publication.php', 'owner_publication_rate_limit');
                http_response_code(503);
                $errors[] = 'Publication changes are temporarily unavailable.';
                $limit = null;
            }
            if (is_array($limit) && !$limit['allowed']) {
                reportRateLimitDenial('owner_publication', $context);
                http_response_code(429);
                header('Retry-After: ' . $limit['retry_after']);
                $errors[] = 'Please wait before changing publication settings.';
                $limit = null;
            }
            if ($limit === null) {
                $state = ownedPublicLifecycleState($database, $context);
            }
        } else {
            $limit = [];
        }
        if ($action === 'set_slug' && $limit !== null) {
            setOwnedPublicSlug($database, $context, $_POST['public_slug'] ?? null);
            httpRedirect('owner_publication.php?slug_saved=1');
        }
        if ($action === 'publish' && $limit !== null) {
            publishOwnedPortfolio($database, $context);
            httpRedirect('owner_publication.php?published=1');
        }
        if ($action === 'unpublish' && $limit !== null) {
            unpublishOwnedPortfolio($database, $context);
            httpRedirect('owner_publication.php?unpublished=1');
        }

        if (!in_array($action, ['set_slug', 'publish', 'unpublish'], true)) {
            http_response_code(400);
            $errors[] = 'Invalid publication action.';
        }
    } else {
        if (isset($_GET['slug_saved'])) $message = 'Public slug saved.';
        if (isset($_GET['published'])) $message = 'Portfolio published.';
        if (isset($_GET['unpublished'])) $message = 'Portfolio unpublished.';
    }
    $state = ownedPublicLifecycleState($database, $context);
} catch (PublicLifecycleValidationException | PublicLifecycleConflictException $exception) {
    $errors[] = $exception->getMessage();
    if (isset($database, $context)) {
        $state = ownedPublicLifecycleState($database, $context);
    }
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_publication.php', 'owner_publication_request');
    http_response_code(503);
    $errors[] = 'Publication settings are temporarily unavailable.';
}

$publicUrl = null;
if (is_array($state) && ownerPublicationStateSlug($state) !== null) {
    try {
        $publicUrl = ownerPublicationPublicUrl($state);
    } catch (PublicUrlConfigurationException $exception) {
        reportApplicationError($exception, 'owner_publication.php', 'owner_publication_public_url');
        http_response_code(503);
        $errors[] = 'PUBLIC_BASE_URL configuration is unavailable.';
    }
}

ownerLayoutStart('Publication', 'publication');
?>
<div class="admin-page-header">
    <div class="admin-page-header-copy"><p class="admin-eyebrow">Publication</p><h1 class="admin-page-title">Public Portfolio</h1><p class="admin-page-description">Choose the permanent public address, then publish when your profile is ready.</p></div>
    <div class="admin-page-header-actions"><a class="button-secondary" href="owner_preview.php">Private Preview</a></div>
</div>
<?php if ($message !== ''): ?><p class="status-message" role="status"><?php echo ownerEscapeHtml($message); ?></p><?php endif; ?>
<?php if ($errors !== []): ?><ul class="status-message error" role="alert"><?php foreach ($errors as $error): ?><li><?php echo ownerEscapeHtml($error); ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (is_array($state)): renderOwnerPublicationPresentation($state, $publicUrl); endif; ?>
<?php ownerLayoutEnd();
