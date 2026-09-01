<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/owner_flow.php';
require_once __DIR__ . '/includes/media_access.php';

function ownerMediaNotFound(): never
{
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

startOwnerSession();

try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $descriptor = ownerMediaDescriptor($database, $context, $_GET['type'] ?? null, $_GET['id'] ?? null);
    if ($descriptor === null) ownerMediaNotFound();
    streamPrivateMedia($descriptor);
} catch (AuthorizationDeniedException | PrivateStorageConfigurationException | PDOException | DatabaseConfigurationException) {
    ownerMediaNotFound();
}
