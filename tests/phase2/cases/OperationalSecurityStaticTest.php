<?php

declare(strict_types=1);

final class OperationalSecurityStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $session = self::read('includes/session.php');
        $operations = self::read('includes/operational_security.php');
        $storage = self::read('includes/storage.php');
        $account = self::read('includes/account_operations.php');
        $logging = self::read('includes/security_events.php');
        $backup = self::read('scripts/backup-production.sh');
        $restore = self::read('scripts/restore-production.sh');
        $contact = self::read('public_contact.php');
        $login = self::read('login.php');
        $all = $session . $operations . $storage . $account . $logging . $backup . $restore;

        phase2Assert(str_contains($session, 'INTERNAL_SESSION_IDLE_TIMEOUT_SECONDS = 1800') && str_contains($session, 'INTERNAL_SESSION_ABSOLUTE_LIFETIME_SECONDS = 43200'), 'P2J-08 session policy is incorrect.');
        phase2Assert(str_contains($operations, 'OWNER_UPLOAD_RATE_LIMIT_ATTEMPTS = 20') && substr_count($operations, '900') >= 2, 'P2J-08 owner limiter policy is incorrect.');
        phase2Assert(str_contains($contact, 'PUBLIC_CONTACT_RATE_LIMIT_ATTEMPTS = 3') && str_contains($contact, 'PUBLIC_CONTACT_RATE_LIMIT_WINDOW_SECONDS = 900'), 'P2J-08 changed the contact limiter policy.');
        phase2Assert(str_contains($login, 'LOGIN_RATE_LIMIT_ATTEMPTS = 5') && str_contains($login, 'LOGIN_RATE_LIMIT_WINDOW_SECONDS = 300'), 'P2J-08 changed the legacy login limiter policy.');
        phase2Assert(str_contains($storage, 'PORTFOLIO_STORAGE_QUOTA_BYTES = 104857600') && str_contains($storage, 'LOCK_EX'), 'P2J-08 quota policy/locking is incomplete.');
        phase2Assert(str_contains(self::read('includes/profile_actions.php'), 'PROFILE_PIXEL_CEILING = 8000000') && str_contains(self::read('includes/project_actions.php'), 'PROJECT_PIXEL_CEILING = 8000000'), 'P2J-08 calibrated image ceilings are missing.');
        phase2Assert(str_contains($account, 'authz_version = authz_version + 1') && !preg_match('/DELETE\s+FROM\s+(?:users|portfolios)/i', $all), 'P2J-08 account transition is not versioned or introduced hard delete.');
        phase2Assert(str_contains($logging, 'JSON_THROW_ON_ERROR') && !preg_match('/session.?id|cookie|password|token|message.?body|authorization/i', $logging), 'P2J-08 security logger includes sensitive fields.');
        phase2Assert(str_contains($backup, 'private-storage.tar.gz') && str_contains($backup, 'recovery_pair_id') && str_contains($restore, 'Recovery-pair manifest mismatch'), 'P2J-08 paired recovery contract is incomplete.');
        phase2Assert(!preg_match('/X-Forwarded-For|Forwarded/i', self::read('includes/rate_limit.php')), 'P2J-08 introduced proxy-header trust.');
        phase2Assert(!preg_match('/OIDC|OAuth|PKCE|Auth0|Cognito|Entra/i', $all), 'P2J-08 introduced P2J-09 provider work.');
    }

    private static function read(string $path): string
    {
        $value = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $path);
        phase2Assert(is_string($value), "{$path} is unreadable.");
        return $value;
    }
}
