<?php

declare(strict_types=1);

require_once __DIR__ . '/http.php';

const ONBOARDING_DRAFT_HTTP_MAX_BYTES = 32768;

function onboardingDraftHttpEnabled(): bool
{
    return getenv('ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED') === '1';
}

function onboardingDraftHttpError(int $status, string $code, string $message): never
{
    httpJsonResponse($status, ['success' => false, 'code' => $code, 'error' => $message]);
}

function onboardingDraftHttpUnavailable(): never
{
    // Classify the failure without logging the exception, SQL, input or draft.
    reportSecurityEvent('onboarding_draft_http', 'denied', ['reason' => 'dependency_failure']);
    onboardingDraftHttpError(503, 'temporarily_unavailable', 'Draft is temporarily unavailable.');
}

/** Decode only the transport envelope; candidate validation belongs to the core. */
function onboardingDraftHttpInput(string $raw): stdClass
{
    $input = json_decode($raw, false, 8, JSON_THROW_ON_ERROR);
    if (!$input instanceof stdClass) { throw new InvalidArgumentException(); }

    // json_decode accepts duplicate keys. Refuse ambiguity, including escaped
    // equivalents. JSON was validated above; consume whole strings so braces
    // and colons inside private text never participate in this scan.
    preg_match_all('/("(?:[^"\\\\]|\\\\.)*")(\s*:)?|([{}\[\]])/s', $raw, $tokens, PREG_SET_ORDER);
    $stack = [];
    foreach ($tokens as $token) {
        if (($token[1] ?? '') !== '') {
            if (($token[2] ?? '') !== '') {
                $key = json_decode($token[1], false, 8, JSON_THROW_ON_ERROR);
                $index = count($stack) - 1;
                if (isset($stack[$index][$key])) { throw new InvalidArgumentException(); }
                $stack[$index][$key] = true;
            }
        } elseif (in_array($token[3], ['{', '['], true)) {
            $stack[] = [];
        } else {
            array_pop($stack);
        }
    }

    $keys = array_keys((array) $input); sort($keys);
    if ($keys !== ['expectedVersion', 'fields', 'operationId', 'section']
        || !is_int($input->expectedVersion) || !is_string($input->operationId)
        || !is_string($input->section) || !$input->fields instanceof stdClass) {
        throw new InvalidArgumentException();
    }
    return $input;
}
