<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    http_response_code(404);
    exit;
}

// The gate copies this support-only file to the disposable document root for
// one POST, then removes it. It must never execute from its tracked location.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/owner_session.php';

function evidenceHubBootstrapFail(int $status = 404): never
{
    http_response_code($status);
    header('Cache-Control: no-store');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || getenv('APP_ENV') !== 'test'
    || getenv('ATHERCAR_TEST_MODE') !== '1'
    || getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP') !== '1') {
    evidenceHubBootstrapFail($_SERVER['REQUEST_METHOD'] === 'POST' ? 404 : 405);
}

$providedNonce = $_SERVER['HTTP_X_EVIDENCE_HUB_BOOTSTRAP_NONCE'] ?? null;
$issuer = getenv('EXPECTED_OIDC_ISSUER');
$stateDirectory = getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_STATE_DIR');
$bindings = [
    ['nonce' => getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_NONCE_A'), 'subject' => getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_SUBJECT_A'), 'marker' => 'a.used'],
    ['nonce' => getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_NONCE_B'), 'subject' => getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_SUBJECT_B'), 'marker' => 'b.used'],
];
$binding = null;
foreach ($bindings as $candidate) {
    if (is_string($candidate['nonce']) && strlen($candidate['nonce']) >= 32 && is_string($providedNonce) && hash_equals($candidate['nonce'], $providedNonce)) {
        $binding = $candidate;
        break;
    }
}
if ($binding === null
    || !is_string($issuer) || $issuer === ''
    || !is_string($binding['subject']) || $binding['subject'] === ''
    || !is_string($stateDirectory) || !str_starts_with($stateDirectory, '/var/lib/ather-career/bootstrap-state/') || !is_dir($stateDirectory)) {
    evidenceHubBootstrapFail();
}
$marker = $stateDirectory . '/' . $binding['marker'];
$markerHandle = @fopen($marker, 'x');
if (!is_resource($markerHandle)) evidenceHubBootstrapFail();

try {
    $statement = getDatabaseConnection()->prepare(
        'SELECT id, authz_version FROM users WHERE oidc_issuer = :issuer AND oidc_subject = :subject AND account_status = "active" LIMIT 1'
    );
    $statement->execute(['issuer' => $issuer, 'subject' => $binding['subject']]);
    $owner = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($owner) || (int) $owner['id'] < 1 || (int) $owner['authz_version'] < 1) {
        throw new RuntimeException('bootstrap owner is unavailable');
    }
    startOwnerSession();
    establishVerifiedInternalUserSession((int) $owner['id'], (int) $owner['authz_version']);
    session_write_close();
    if (!fclose($markerHandle)) throw new RuntimeException('bootstrap nonce could not be consumed');
    $markerHandle = null;
    http_response_code(204);
    header('Cache-Control: no-store');
} catch (Throwable $exception) {
    error_log('Evidence Hub Web-SAPI bootstrap failed: ' . $exception::class);
    if (is_resource($markerHandle)) fclose($markerHandle);
    @unlink($marker);
    if (session_status() === PHP_SESSION_ACTIVE) destroyOwnerSession();
    evidenceHubBootstrapFail();
}
