<?php

declare(strict_types=1);

require_once __DIR__ . '/http.php';

const ONBOARDING_ATTESTATION_HTTP_MAX_BYTES = 4096;

function onboardingAttestationHttpEnabled(): bool
{
    return getenv('ATHERCAR_ONBOARDING_ATTESTATION_HTTP_ENABLED') === '1';
}

function onboardingAttestationHttpError(int $status, string $code, string $message): never
{
    httpJsonResponse($status, ['success' => false, 'code' => $code, 'error' => $message]);
}

function onboardingAttestationHttpUnavailable(): never
{
    // A response failure may follow COMMIT. Do not assert absence or replay.
    reportSecurityEvent('onboarding_attestation_http', 'denied', ['reason' => 'dependency_failure']);
    onboardingAttestationHttpError(503, 'temporarily_unavailable', 'Unable to confirm the result. Read the current evidence before submitting again.');
}

/** Only this flat envelope is accepted; descriptors/authority never come here. */
function onboardingAttestationHttpInput(string $raw): stdClass
{
    $input = json_decode($raw, false, 4, JSON_THROW_ON_ERROR);
    if (!$input instanceof stdClass) { throw new InvalidArgumentException(); }
    $keys = array_keys((array) $input); sort($keys, SORT_STRING);
    if ($keys !== ['age_18_or_older', 'presentation_token', 'privacy_notice_acknowledged', 'terms_accepted']
        || !is_string($input->presentation_token) || preg_match('/\A[a-f0-9]{64}\z/D', $input->presentation_token) !== 1
        || $input->age_18_or_older !== true || $input->terms_accepted !== true || $input->privacy_notice_acknowledged !== true) {
        throw new InvalidArgumentException();
    }
    // json_decode accepts duplicate/escaped-equivalent keys. Consume whole JSON
    // strings so punctuation inside a string is never mistaken for a member.
    preg_match_all('/("(?:[^"\\\\]|\\\\.)*")(\s*:)?/s', $raw, $tokens, PREG_SET_ORDER);
    $seen = [];
    foreach ($tokens as $token) {
        if (($token[2] ?? '') === '') { continue; }
        $key = json_decode($token[1], false, 4, JSON_THROW_ON_ERROR);
        if (isset($seen[$key])) { throw new InvalidArgumentException(); }
        $seen[$key] = true;
    }
    return $input;
}
