<?php
declare(strict_types=1);
require_once __DIR__.'/includes/owner_session.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/owner_flow.php';
startOwnerSession();
httpRegisterExceptionBoundary('owner_onboarding.php');
// Legacy POST is retired before database work; no first creation or redirect.
httpRequireMethod(['GET','HEAD']);
try {
    $database=getDatabaseConnection();
    $user=requireOwnerAuthenticatedUser($database);
    $destination=ownerJourneyDestination($database,$user);
    if ($destination!=='/owner_onboarding.php') { httpRedirect($destination); }
    renderOwnerJourneyHolding();
} catch (AuthorizationDeniedException) { ownerAuthorizationDenied(); }
catch (PDOException | DatabaseConfigurationException) { renderOwnerJourneyHolding(); }
