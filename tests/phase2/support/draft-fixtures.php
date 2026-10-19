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

/** CLI-only legacy fixture: represent a structurally valid pre-catalog save.
 * Never add a compatibility switch to the production writer to seed test data.
 */
function draftTestSeedHistorical(PDO $db, int $userId, int $version, string $section, array $patch, array $fields): array
{
    $patch = onboardingDraftValidatePatch($section, $patch);
    $payload = onboardingDraftEncode($fields);
    onboardingDraftDecode($payload);
    $expectedVersion = $version - 1;
    $operationId = draftTestOperation($expectedVersion);
    $digest = hash('sha256', json_encode([$expectedVersion, $section, (object) $patch], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), true);
    $db->prepare('INSERT INTO onboarding_drafts (user_id,version,payload,last_section,last_operation_id,last_operation_digest,created_at,updated_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))')
        ->execute([$userId, $version, $payload, $section, $operationId, $digest]);
    return ['expectedVersion' => $expectedVersion, 'operationId' => $operationId, 'section' => $section, 'fields' => $patch];
}
