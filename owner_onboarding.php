<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/owner_layout.php';

startOwnerSession();
httpRegisterExceptionBoundary('owner_onboarding.php');
httpRequireMethod(['GET', 'HEAD', 'POST']);

$message = '';
$messageRole = 'status';
$canCreate = false;
$readinessMessage = 'We cannot confirm your email for this sign-in, so Portfolio creation is unavailable. You can use another account or log out.';
try {
    $database = getDatabaseConnection();
    $user = requireOwnerAuthenticatedUser($database);
    if (ownerHasPortfolio($database, $user)) {
        httpRedirect('owner.php');
    }

    $observation = readAuthorizedOwnerReadinessObservation($database);
    $canCreate = ($observation['email_verified'] ?? null) === true;
    if (!$canCreate) {
        $message = $readinessMessage;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);
        createOwnedPortfolio($database, $user);
        httpRedirect('owner.php');
    }
} catch (OwnerReadinessDeniedException) {
    http_response_code(403);
    $messageRole = 'alert';
    $message = $readinessMessage;
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'owner_onboarding.php', 'owner_portfolio_create');
    http_response_code(503);
    $canCreate = false;
    $messageRole = 'alert';
    $message = 'Portfolio creation is temporarily unavailable. Please try again later.';
}

ownerLayoutStart('Create Your Portfolio', '');
?>
<div class="admin-page-header">
    <div class="admin-page-header-copy">
        <p class="admin-eyebrow">Welcome</p>
        <h1 class="admin-page-title">Create your Portfolio</h1>
        <p class="admin-page-description">Your private workspace is ready to set up. It is not public or published. If this is the wrong account, use “Use another account” in the navigation before creating a Portfolio.</p>
    </div>
</div>
<?php if ($message !== ''): ?>
<p role="<?php echo $messageRole; ?>"><?php echo ownerEscapeHtml($message); ?></p>
<?php endif; ?>
<?php if ($canCreate): ?>
<form method="POST" action="owner_onboarding.php" class="profile-form" data-owner-form>
    <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
    <div class="form-actions"><button class="button-primary" type="submit" data-pending-label="Creating Portfolio…">Create Portfolio</button></div>
</form>
<?php endif; ?>
<?php ownerLayoutEnd();
