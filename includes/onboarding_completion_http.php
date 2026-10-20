<?php

declare(strict_types=1);

require_once __DIR__ . '/http.php';

const ONBOARDING_COMPLETION_HTTP_MAX_BYTES = 4096;

function onboardingCompletionHttpEnabled(): bool
{
    return getenv('ATHERCAR_ONBOARDING_COMPLETION_HTTP_ENABLED') === '1';
}

function onboardingCompletionHttpError(int $status, string $code, string $message): never
{
    httpJsonResponse($status, ['success' => false, 'code' => $code, 'error' => $message]);
}

function onboardingCompletionHttpUnavailable(): never
{
    // A response failure may follow COMMIT. Do not assert absence or replay.
    reportSecurityEvent('onboarding_completion_http', 'denied', ['reason' => 'dependency_failure']);
    onboardingCompletionHttpError(503, 'temporarily_unavailable', 'Unable to confirm the result. Read your completion receipt before deciding what to do next.');
}

/** Transport only: saved fields, trusted bindings and receipts stay in the core. */
function onboardingCompletionHttpInput(string $raw): stdClass
{
    $input = json_decode($raw, false, 4, JSON_THROW_ON_ERROR);
    if (!$input instanceof stdClass || !is_string($input->action ?? null)
        || !is_int($input->expectedVersion ?? null) || $input->expectedVersion < 1
        || $input->expectedVersion > ONBOARDING_DRAFT_MAX_VERSION) { throw new InvalidArgumentException(); }
    $keys = array_keys((array) $input); sort($keys, SORT_STRING);
    if ($input->action === 'review') {
        if ($keys !== ['action', 'expectedVersion']) { throw new InvalidArgumentException(); }
    } elseif ($input->action === 'complete') {
        if ($keys !== ['acknowledge_private_draft', 'action', 'confirm_private_creation', 'expectedVersion', 'operationId', 'reviewToken']
            || !is_string($input->operationId) || preg_match('/\A'.$input->expectedVersion.':[a-f0-9]{32}\z/D', $input->operationId) !== 1
            || !is_string($input->reviewToken) || preg_match('/\A[a-f0-9]{64}\z/D', $input->reviewToken) !== 1
            || $input->confirm_private_creation !== true || $input->acknowledge_private_draft !== true) { throw new InvalidArgumentException(); }
    } else { throw new InvalidArgumentException(); }
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
