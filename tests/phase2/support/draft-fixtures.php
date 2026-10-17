<?php
declare(strict_types=1);

// Reuse only the existing guarded test namespace and private session helpers.
require_once __DIR__ . '/attestation-fixtures.php';
require_once __DIR__ . '/../../../includes/onboarding_drafts.php';

function draftTestOperation(int $version): string
{
    return $version . ':' . bin2hex(random_bytes(16));
}

function draftTestConnection(string $class = PDO::class): PDO
{
    return new $class('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4',
        getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
}
