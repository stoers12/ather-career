<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/media_access.php';

function publicMediaNotFound(): never
{
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

try {
    $database = getDatabaseConnection();
    $context = resolvePublicReadContext($database, $_GET['slug'] ?? null);
    if ($context === null) publicMediaNotFound();
    $descriptor = publicMediaDescriptor($database, $context, $_GET['type'] ?? null, $_GET['id'] ?? null);
    if ($descriptor === null) publicMediaNotFound();
    streamPrivateMedia($descriptor);
} catch (PrivateStorageConfigurationException | PDOException | DatabaseConfigurationException) {
    publicMediaNotFound();
}
