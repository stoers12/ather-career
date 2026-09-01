<?php

declare(strict_types=1);

final class Auth0OidcStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $oidc = self::read('includes/auth0_oidc.php');
        $identity = self::read('includes/auth0_identity.php');
        $start = self::read('owner_login.php');
        $callback = self::read('owner_oidc_callback.php');
        $guard = self::read('scripts/check-production-security.php');

        phase2Assert(str_contains($oidc, "'code_challenge_method' => 'S256'") && str_contains($oidc, 'random_bytes(64)'), 'Auth0 PKCE S256 transaction is missing.');
        phase2Assert(str_contains($oidc, 'AUTH0_AUTH_TRANSACTION_TTL_SECONDS') && str_contains($oidc, 'unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY])'), 'Auth0 callback transaction is not bounded and one-time.');
        phase2Assert(str_contains($oidc, 'Auth0\\SDK\\Token') && str_contains($oidc, '->verify()->validate('), 'Auth0 signed ID token validation is missing.');
        phase2Assert(str_contains($oidc, 'hash_equals($configuration->issuer, $issuer)') && str_contains($identity, 'hash_equals($configuration->issuer, $identity->issuer)'), 'Auth0 issuer binding is not exact.');
        phase2Assert(str_contains($identity, 'WHERE oidc_subject = :subject') && !preg_match('/email|name|nickname|username/i', $identity), 'Auth0 identity lookup is not subject-only.');
        phase2Assert(str_contains($start, "consumeRateLimit('oidc_start', rateLimitClientIp()") && !preg_match('/X-Forwarded-For|Forwarded/i', $start), 'OIDC start limiter is not REMOTE_ADDR-only.');
        phase2Assert(str_contains($callback, 'establishVerifiedInternalUserSession') && str_contains($callback, 'destroyInternalUserSession'), 'Auth0 session establishment or denial cleanup is missing.');
        phase2Assert(str_contains($guard, 'auth0ProductionConfigurationFailures'), 'Production guard does not validate Auth0 configuration.');
        phase2Assert(!preg_match('/password_verify|\$_POST\[.password|localStorage|sessionStorage|refresh_token/i', $start . $callback . $oidc), 'P2J-09 introduced an unsafe browser or password auth path.');
    }

    private static function read(string $path): string
    {
        $value = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $path);
        phase2Assert(is_string($value), "{$path} is unreadable.");
        return $value;
    }
}
