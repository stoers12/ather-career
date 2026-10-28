<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../../includes/auth0_oidc.php';
require_once __DIR__ . '/../../includes/owner_session.php';

function claimCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function claimSame(mixed $expected, mixed $actual, string $message): void
{
    claimCheck($expected === $actual, $message);
}

/** @param mixed $claims */
function syntheticValidatedCallback(Auth0OidcConfiguration $configuration, mixed $claims): Auth0ValidatedIdentity
{
    $start = beginAuth0Authorization($configuration, 'https://issuer.example.test/authorize', null, 'google');
    parse_str((string) parse_url($start['url'], PHP_URL_QUERY), $query);
    claimSame('openid email', $query['scope'] ?? null, 'Google request did not ask for the email claim.');
    $identity = completeAuth0Authorization(
        $configuration,
        ['state' => $start['state'], 'code' => 'synthetic'],
        static fn (Auth0OidcConfiguration $config): Auth0ValidatedIdentity => new Auth0ValidatedIdentity(
            $config->issuer,
            'auth0|synthetic',
            is_array($claims) ? ($claims['email_verified'] ?? null) : null,
            auth0HasNonemptyEmailClaim(is_array($claims) ? ($claims['email'] ?? null) : null)
        )
    );
    claimSame('auth0|synthetic', $identity->subject, 'Validated identity changed.');

    return $identity;
}

try {
    startOwnerSession();
    $configuration = new Auth0OidcConfiguration('https://issuer.example.test/', 'issuer.example.test', 'synthetic-client', 'synthetic-secret', 'https://app.example.test/owner_oidc_callback.php');
    $callback = (string) file_get_contents(__DIR__ . '/../../owner_oidc_callback.php');
    $oidc = (string) file_get_contents(__DIR__ . '/../../includes/auth0_oidc.php');
    claimCheck(strpos($oidc, '$token->validate(') < strpos($oidc, '$emailVerifiedClaim = is_array($claims)'),
        'Claim extraction must follow signed-token validation.');
    claimCheck(strpos($oidc, '$token->validate(') < strpos($oidc, '$hasNonemptyEmail = auth0HasNonemptyEmailClaim('),
        'Email shape extraction must follow signed-token validation.');
    $resolution = strpos($callback, 'resolveAuth0InternalUser(');
    $establishment = strpos($callback, 'establishVerifiedInternalUserSession(');
    $observation = strpos($callback, 'recordOwnerEmailVerificationAfterLogin(');
    $classification = strpos($callback, 'auth0EmailVerifiedClassification(');
    $redirect = strpos($callback, 'httpRedirect($destination)');
    claimCheck($resolution !== false && $establishment !== false && $observation !== false && $classification !== false && $redirect !== false
        && $resolution < $establishment && $establishment < $observation && $observation < $classification && $classification < $redirect,
        'Callback identity, session, observation, or redirect order changed.');
    claimCheck(substr_count($callback, 'resetFailedOwnerCallbackSession();') === 3,
        'Rejected callback cleanup changed.');

    foreach ([
        [['email' => 'private@example.test', 'email_verified' => true], true],
        [['email_verified' => true], null],
        [['email' => '', 'email_verified' => true], null],
        [['email' => " \t\n", 'email_verified' => true], null],
        [['email' => "\u{00A0}", 'email_verified' => true], null],
        [['email' => 42, 'email_verified' => true], null],
        [['email' => [], 'email_verified' => true], null],
        [['email_verified' => false], false],
        [['email' => 'private@example.test'], null],
        [['email' => 'private@example.test', 'email_verified' => 'true'], null],
        [['email' => 'private@example.test', 'email_verified' => 1], null],
        [['email' => 'private@example.test', 'email_verified' => []], null],
        [null, null],
    ] as [$claims, $expected]) {
        $identity = syntheticValidatedCallback($configuration, $claims);
        establishVerifiedInternalUserSession(101, 7);
        recordOwnerEmailVerificationAfterLogin(101, 7, auth0EmailVerifiedClassification($identity->emailVerifiedClaim, $identity->hasNonemptyEmail));
        $record = readOwnerReadinessObservation(101, 7);
        claimCheck($record !== null, 'Authenticated callback did not create a session observation.');
        claimSame($expected, $record['email_verified'], 'Claim classification changed.');
        claimCheck($record['observed_at'] >= currentInternalUserSession()['authenticated_at'], 'Observation predates authentication.');
    }

    foreach ([
        ['email_verified' => true],
        ['email' => '', 'email_verified' => true],
        ['email' => " \t\n", 'email_verified' => true],
        ['email' => "\u{00A0}", 'email_verified' => true],
        ['email' => 42, 'email_verified' => true],
    ] as $claims) {
        recordOwnerEmailVerificationAfterLogin(101, 7, true);
        $identity = syntheticValidatedCallback($configuration, $claims);
        recordOwnerEmailVerificationAfterLogin(101, 7, auth0EmailVerifiedClassification($identity->emailVerifiedClaim, $identity->hasNonemptyEmail));
        claimSame(null, readOwnerReadinessObservation(101, 7)['email_verified'], 'Malformed email preserved a positive observation.');
    }

    recordOwnerEmailVerificationAfterLogin(101, 7, true);
    foreach ([false, null] as $replacement) {
        recordOwnerEmailVerificationAfterLogin(101, 7, $replacement);
        claimSame($replacement, readOwnerReadinessObservation(101, 7)['email_verified'], 'New nonpositive observation retained a positive.');
        recordOwnerEmailVerificationAfterLogin(101, 7, true);
    }
    $before = readOwnerReadinessObservation(101, 7)['observed_at'];
    refreshInternalSessionActivity(time());
    claimSame($before, readOwnerReadinessObservation(101, 7)['observed_at'], 'Activity refreshed observation time.');
    recordOwnerEmailVerificationAfterLogin(202, 7, true);
    claimSame(null, readOwnerReadinessObservation(101, 7), 'Failed observation retained a prior positive.');

    $identity = syntheticValidatedCallback($configuration, ['email' => 'private@example.test', 'email_verified' => true]);
    establishVerifiedInternalUserSession(101, 7);
    recordOwnerEmailVerificationAfterLogin(101, 7, auth0EmailVerifiedClassification($identity->emailVerifiedClaim, $identity->hasNonemptyEmail));
    claimCheck(!str_contains(serialize($_SESSION), 'private@example.test'), 'Raw email entered server session.');
    claimCheck(!str_contains(serialize($identity), 'private@example.test'), 'Raw email entered validated identity.');
    claimCheck(currentInternalUserSession()['internal_user_id'] === 101, 'Authenticated session identity changed.');

    $start = beginAuth0Authorization($configuration, 'https://issuer.example.test/authorize');
    try {
        completeAuth0Authorization($configuration, ['state' => $start['state'], 'code' => 'rejected'],
            static function (): never { throw new Auth0OidcException('token_validation_failed'); });
        throw new RuntimeException('Rejected validation unexpectedly returned.');
    } catch (Auth0OidcException) {
        beginFreshOwnerAccountSelectionSession();
    }
    claimSame(null, readOwnerReadinessObservation(101, 7), 'Rejected callback retained a positive observation.');
    claimSame(null, currentInternalUserSession(), 'Rejected callback retained authentication.');
    destroyOwnerSession();
    claimSame(null, readOwnerReadinessObservation(101, 7), 'Logout retained the observation.');
    echo "Owner readiness claim integration: PASS\n";
} catch (Throwable $exception) {
    if (session_status() === PHP_SESSION_ACTIVE) { destroyOwnerSession(); }
    fwrite(STDERR, 'Owner readiness claim integration: FAIL: ' . $exception->getMessage() . "\n");
    exit(1);
}
