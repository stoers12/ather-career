<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/owner_layout.php';

startOwnerSession();

$error = '';
try {
    $database = getDatabaseConnection();
    $user = requireOwnerAuthenticatedUser($database);
    if (ownerHasPortfolio($database, $user)) {
        header('Location: owner.php', true, 303);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        createOwnedPortfolio($database, $user);
        header('Location: owner.php', true, 303);
        exit;
    }
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_onboarding.php', 'owner_portfolio_create');
    http_response_code(503);
    $error = 'Your Portfolio could not be created right now.';
}

ownerLayoutStart('Create Your Portfolio', '');
?>
<div class="admin-page-header">
    <div class="admin-page-header-copy">
        <p class="admin-eyebrow">Welcome</p>
        <h1 class="admin-page-title">Create your Portfolio</h1>
        <p class="admin-page-description">Your private workspace is ready to set up. It is not public or published.</p>
    </div>
</div>
<?php if ($error !== ''): ?><p class="status-message error" role="alert"><?php echo ownerEscapeHtml($error); ?></p><?php endif; ?>
<form method="POST" action="owner_onboarding.php" class="profile-form">
    <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
    <div class="form-actions"><button class="button-primary" type="submit">Create Portfolio</button></div>
</form>
<?php ownerLayoutEnd();
