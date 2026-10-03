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
        $safeAccessLog = self::read('docker/apache/safe-access-log.conf');
        $developmentDockerfile = self::read('Dockerfile');
        $productionDockerfile = self::read('Dockerfile.production');
        $developmentVhost = self::read('docker/apache/development-vhost.conf');
        $productionVhost = self::read('docker/apache/production-vhost.conf');

        phase2Assert(str_contains($oidc, "'code_challenge_method' => 'S256'") && str_contains($oidc, 'random_bytes(64)'), 'Auth0 PKCE S256 transaction is missing.');
        phase2Assert(str_contains($oidc, 'AUTH0_AUTH_TRANSACTION_TTL_SECONDS') && str_contains($oidc, 'unset($_SESSION[AUTH0_AUTH_TRANSACTION_KEY])'), 'Auth0 callback transaction is not bounded and one-time.');
        phase2Assert(str_contains($oidc, 'Auth0\\SDK\\Token') && (str_contains($oidc, '->verify()->validate(') || (str_contains($oidc, '$token->verify();') && str_contains($oidc, '$token->validate('))), 'Auth0 signed ID token validation is missing.');
        phase2Assert(str_contains($oidc, 'hash_equals($configuration->issuer, $issuer)') && str_contains($identity, 'hash_equals($configuration->issuer, $identity->issuer)'), 'Auth0 issuer binding is not exact.');
        phase2Assert(str_contains($identity, 'WHERE oidc_subject = :subject') && !preg_match('/email|name|nickname|username/i', $identity), 'Auth0 identity lookup is not subject-only.');
        phase2Assert(str_contains($start, "consumeRateLimit('oidc_start', rateLimitClientIp()") && !preg_match('/X-Forwarded-For|Forwarded/i', $start), 'OIDC start limiter is not REMOTE_ADDR-only.');
        phase2Assert(str_contains($callback, 'establishVerifiedInternalUserSession') && str_contains($callback, 'destroyInternalUserSession'), 'Auth0 session establishment or denial cleanup is missing.');
        phase2Assert(str_contains($guard, 'auth0ProductionConfigurationFailures'), 'Production guard does not validate Auth0 configuration.');
        phase2Assert(str_contains($safeAccessLog, '%m %H') && !preg_match('/%[hUqr]|%\{(?:Cookie|Authorization|Referer|User-agent)\}i/', $safeAccessLog)
            && str_contains($developmentDockerfile, 'a2disconf other-vhosts-access-log')
            && str_contains($productionDockerfile, 'a2disconf other-vhosts-access-log')
            && str_contains($developmentVhost, 'CustomLog ${APACHE_LOG_DIR}/access.log ather_safe')
            && str_contains($productionVhost, 'CustomLog ${APACHE_LOG_DIR}/access.log ather_safe'), 'Apache callback logging can expose a request query string.');
        phase2Assert(!preg_match('/password_verify|\$_POST\[.password|localStorage|sessionStorage|refresh_token/i', $start . $callback . $oidc), 'P2J-09 introduced an unsafe browser or password auth path.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/auth0_oidc.php';
        $allowedReasons = [
            'token_transport_failed',
            'token_http_rejected',
            'token_response_invalid',
            'jwks_fetch_failed',
            'jwt_signature_failed',
            'issuer_failed',
            'audience_failed',
            'nonce_failed',
            'expiry_failed',
        ];
        phase2Assert(function_exists('auth0TokenValidationSafeReason'), 'Auth0 token-validation safe reason mapper is missing.');
        phase2Assert(defined('AUTH0_TOKEN_VALIDATION_SAFE_REASONS'), 'Auth0 token-validation safe reason allow-list is missing.');
        if (function_exists('auth0TokenValidationSafeReason')) {
            $mappedReasons = array_map('auth0TokenValidationSafeReason', $allowedReasons);
            phase2AssertSame($allowedReasons, $mappedReasons, 'Auth0 token-validation reason mapping changed.');
            phase2AssertSame('token_validation_failed', auth0TokenValidationSafeReason('arbitrary_exception_message'), 'Unknown Auth0 token-validation failures must use the generic reason.');
        }
        $providerErrorMap = [
            'invalid_request' => 'token_http_invalid_request',
            'invalid_client' => 'token_http_invalid_client',
            'invalid_grant' => 'token_http_invalid_grant',
            'unauthorized_client' => 'token_http_unauthorized_client',
            'unsupported_grant_type' => 'token_http_unsupported_grant_type',
            'invalid_scope' => 'token_http_invalid_scope',
        ];
        phase2Assert(function_exists('auth0TokenHttpRejectionReason'), 'Auth0 token HTTP rejection reason mapper is missing.');
        phase2Assert(defined('AUTH0_TOKEN_HTTP_ERROR_REASONS'), 'Auth0 token HTTP error allow-list is missing.');
        if (function_exists('auth0TokenHttpRejectionReason')) {
            foreach ($providerErrorMap as $providerError => $safeReason) {
                phase2AssertSame($safeReason, auth0TokenHttpRejectionReason($providerError), 'OAuth provider error mapping changed.');
            }
            foreach ([null, '', 1, [], new stdClass(), 'unknown_error', 'token_http_invalid_client'] as $invalidProviderError) {
                phase2AssertSame('token_http_rejected_other', auth0TokenHttpRejectionReason($invalidProviderError), 'Unknown OAuth provider errors must use the bounded fallback reason.');
            }
        }
        if (!class_exists('Auth0\\SDK\\Exception\\NetworkException')) {
            eval('namespace Auth0\\SDK\\Exception; final class NetworkException extends \\RuntimeException {}');
        }
        if (!class_exists('Auth0\\SDK\\Exception\\InvalidTokenException')) {
            eval('namespace Auth0\\SDK\\Exception; final class InvalidTokenException extends \\RuntimeException {}');
        }
        $networkException = new \Auth0\SDK\Exception\NetworkException('https://hostile.invalid/?token=secret');
        phase2AssertSame('jwks_fetch_failed', auth0TokenValidationExceptionReason($networkException, 'jwks'), 'JWKS network failures must use the bounded safe reason.');
        phase2AssertSame('jwt_signature_failed', auth0TokenValidationExceptionReason(new RuntimeException('signature nonce issuer audience'), 'jwks'), 'Unknown JWKS failures must fail closed as signature failures.');
        $claimCases = [
            ['Issuer (iss) claim invalid', 'issuer_failed'],
            ['Audience (aud) claim invalid', 'audience_failed'],
            ['Authorized Party (azp) claim invalid', 'audience_failed'],
            ['Nonce (nonce) claim invalid', 'nonce_failed'],
            ['Expiration Time (exp) invalid', 'expiry_failed'],
            ['', 'token_validation_failed'],
            ['ISSUER (ISS) CLAIM invalid', 'token_validation_failed'],
            ['Issuer (iss) claim; Audience (aud) claim; token=secret@example.invalid', 'issuer_failed'],
            [str_repeat('x', 12000) . ' https://hostile.invalid/?cookie=opaque', 'token_validation_failed'],
        ];
        foreach ($claimCases as [$message, $expectedReason]) {
            $reason = auth0TokenValidationExceptionReason(new \Auth0\SDK\Exception\InvalidTokenException($message), 'claims');
            phase2AssertSame($expectedReason, $reason, 'Token claim failure classification changed.');
            phase2Assert(in_array($reason, AUTH0_TOKEN_VALIDATION_SAFE_REASONS, true) || $reason === 'token_validation_failed', 'Token classifier returned an unbounded reason.');
            phase2Assert(!str_contains($reason, 'secret') && !str_contains($reason, 'invalid') && !str_contains($reason, 'http'), 'Token classifier leaked diagnostic text.');
        }
        phase2AssertSame('token_validation_failed', auth0TokenValidationExceptionReason(new RuntimeException('Issuer (iss) claim'), 'claims'), 'Unexpected exception types must use the generic safe reason.');
        phase2Assert(!preg_match('/error_description|error_uri/i', $oidc), 'OAuth provider diagnostic mapping must not expose descriptions or URIs.');
        phase2Assert(!preg_match('/error_log|reportSecurityEvent|json_encode\s*\([^\n]*(?:exception|token|code|state|nonce|cookie|header)/i', $oidc), 'Auth0 token-validation classification must not log sensitive payloads.');
    }

    private static function read(string $path): string
    {
        $value = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $path);
        phase2Assert(is_string($value), "{$path} is unreadable.");
        return $value;
    }
}
