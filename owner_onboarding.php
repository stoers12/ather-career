<?php
declare(strict_types=1);
require_once __DIR__.'/includes/owner_session.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/owner_flow.php';
startOwnerSession();
httpRegisterExceptionBoundary('owner_onboarding.php');
// Legacy POST is retired before database work; no first creation or redirect.
httpRequireMethod(['GET','HEAD']);
// Presentation only: fixed mode/language, no return URL or authority selector.
$query=(string)($_SERVER['QUERY_STRING'] ?? '');
if (preg_match('/\A(?:view=paused(?:&lang=(?:ar|en))?|lang=(?:ar|en)(?:&view=paused)?)?\z/D',$query)!==1) { httpAbortHtml(400,'Invalid onboarding view.'); }
$paused=($_GET['view'] ?? null)==='paused';
$locale=$_GET['lang'] ?? ($paused ? 'ar' : 'en');
try {
    $database=getDatabaseConnection();
    $user=requireOwnerAuthenticatedUser($database);
    $destination=ownerJourneyDestination($database,$user);
    if ($paused) { renderOwnerJourneyHolding($destination==='/owner_onboarding.php' ? 503 : 200,$destination,true,$locale); }
    if ($destination!=='/owner_onboarding.php') { httpRedirect($destination==='/owner_onboarding_editor.php' ? (isset($_GET['lang']) ? $destination.'?lang='.$locale : $destination) : $destination); }
    renderOwnerJourneyHolding(503,null,false,$locale);
} catch (AuthorizationDeniedException) { ownerAuthorizationDenied(); }
catch (PDOException | DatabaseConfigurationException) { renderOwnerJourneyHolding(503,null,$paused,$locale); }
