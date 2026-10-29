<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/^ather_career_test_[a-f0-9]{24}$/', (string) getenv('DB_NAME')) !== 1
    || getenv('DB_NAME') !== getenv('ATHERCAR_TEST_DB_NAME')) { exit(2); }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/onboarding_attestations.php';

function attestationTestStartSession(): string
{
    $root = sys_get_temp_dir() . '/attestation-session-' . bin2hex(random_bytes(12));
    if (!mkdir($root, 0700)) { throw new RuntimeException('Private session directory unavailable.'); }
    session_save_path($root);
    session_start(['use_cookies' => 0, 'cache_limiter' => '']);
    return $root;
}
function attestationTestEndSession(string $root): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; session_destroy(); }
    foreach (glob($root . '/sess_*') ?: [] as $file) { unlink($file); }
    rmdir($root);
}

function attestationTestBundle(string $version = 'test-v1', string $language = 'en'): OnboardingAttestationBundle
{
    $document = static fn (string $id): OnboardingDocumentDescriptor => new OnboardingDocumentDescriptor(
        $id, $version, hash('sha256', 'SYNTHETIC TEST ONLY:' . $id . ':' . $version . ':' . $language), $language,
    );
    return new OnboardingAttestationBundle($document('test-age'), $document('test-terms'), $document('test-privacy'));
}
function attestationTestSession(int $id): AuthenticatedUserContext
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_id(''); session_start(['use_cookies' => 0, 'cache_limiter' => '']); }
    $_SESSION = [INTERNAL_USER_SESSION_KEY => ['internal_user_id' => $id, 'authz_version' => 1, 'authenticated_at' => time(), 'last_activity_at' => time()]];
    return AuthenticatedUserContext::fromValidatedUser($id);
}
function attestationTestAffirmations(): array
{
    return ['age_18_or_older' => true, 'terms_accepted' => true, 'privacy_notice_acknowledged' => true];
}
