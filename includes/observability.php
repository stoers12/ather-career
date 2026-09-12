<?php

declare(strict_types=1);

/*
 * Apache mod_unique_id owns the externally visible correlation identifier so
 * that PHP and Apache-generated errors carry the same value. Inbound
 * request-ID headers are ignored: this deployment has no configured trusted
 * proxy boundary, and accepting them would make logs and response headers
 * depend on untrusted input.
 */
const RUNTIME_APACHE_REQUEST_ID_PATTERN = '/^[A-Za-z0-9@_-]{16,128}$/D';

/** @var array{started_at: float, request_id: string, route: string, low_noise: bool, completed: bool, outcome: string|null, reason: string|null}|null */
function &runtimeRequestContext(): ?array
{
    static $context = null;

    return $context;
}

function runtimeGeneratedRequestId(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable) {
        // This fallback never uses client, session, authentication, or request
        // data. It is only used if the platform CSPRNG is unavailable.
        return substr(hash('sha256', microtime(true) . ':' . getmypid() . ':' . uniqid('', true)), 0, 32);
    }
}

function runtimeRequestId(): string
{
    $apacheRequestId = $_SERVER['UNIQUE_ID'] ?? null;
    if (is_string($apacheRequestId) && preg_match(RUNTIME_APACHE_REQUEST_ID_PATTERN, $apacheRequestId) === 1) {
        return $apacheRequestId;
    }

    // CLI tests and deliberately minimal non-Apache contexts retain a
    // process-generated correlation value. Apache remains the sole HTTP
    // response-header emitter in deployed web requests.
    return runtimeGeneratedRequestId();
}

function runtimeRouteCategory(string $route): string
{
    return match ($route) {
        'health' => 'health',
        'ready.php' => 'readiness',
        'index.php' => 'landing',
        'public_portfolio.php' => 'public_portfolio',
        'public_projects_json.php' => 'public_projects_json',
        'public_contact.php' => 'public_contact',
        'public_media.php' => 'public_media',
        'owner_login.php' => 'owner_login',
        'owner_logout.php' => 'owner_logout',
        'owner_oidc_callback.php' => 'owner_oidc_callback',
        'owner.php' => 'owner_dashboard',
        'owner_profile.php', 'owner_projects.php', 'owner_experiences.php', 'owner_messages.php', 'owner_onboarding.php', 'owner_preview.php', 'owner_publication.php' => 'owner_workflow',
        default => 'application',
    };
}

function runtimeStartRequest(string $route): string
{
    $context =& runtimeRequestContext();
    if ($context !== null) {
        return $context['request_id'];
    }

    $requestId = runtimeRequestId();
    $routeCategory = runtimeRouteCategory($route);
    $context = [
        'started_at' => microtime(true),
        'request_id' => $requestId,
        'route' => $routeCategory,
        'low_noise' => in_array($routeCategory, ['health', 'readiness'], true),
        'completed' => false,
        'outcome' => null,
        'reason' => null,
    ];

    register_shutdown_function('runtimeCompleteRequest');

    return $requestId;
}

function runtimeActiveRequestId(): ?string
{
    $context =& runtimeRequestContext();

    return $context['request_id'] ?? null;
}

function runtimeSafeLogValue(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9_.:-]/', '_', $value);

    return substr($value ?? 'unknown', 0, 80);
}

function runtimeSetRequestOutcome(string $outcome, ?string $reason = null): void
{
    $context =& runtimeRequestContext();
    if ($context === null) {
        return;
    }

    $context['outcome'] = runtimeSafeLogValue($outcome);
    $context['reason'] = $reason === null ? null : runtimeSafeLogValue($reason);
}

function runtimeRequestOutcomeForStatus(int $status): string
{
    if ($status >= 500) {
        return 'server_error';
    }
    if ($status >= 400) {
        return 'client_rejection';
    }
    if ($status >= 300) {
        return 'redirect';
    }

    return 'success';
}

/** @param array<string, bool|int|string|null> $record */
function runtimeWriteStructuredRecord(array $record): bool
{
    try {
        return error_log((string) json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    } catch (Throwable) {
        return false;
    }
}

function runtimeCompleteRequest(): void
{
    $context =& runtimeRequestContext();
    if ($context === null || $context['completed']) {
        return;
    }
    $context['completed'] = true;

    $status = http_response_code();
    if (!is_int($status) || $status < 100 || $status > 599) {
        $status = 500;
    }

    $fatal = error_get_last();
    $fatalTypes = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR];
    if (is_array($fatal) && in_array($fatal['type'] ?? null, $fatalTypes, true)) {
        $context['outcome'] = 'server_error';
        $context['reason'] = 'fatal_runtime_error';
        if ($status < 500) {
            $status = 500;
        }
    }

    $outcome = $context['outcome'] ?? runtimeRequestOutcomeForStatus($status);
    if ($context['low_noise'] && $status < 400 && $outcome === 'success') {
        return;
    }

    $record = [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'level' => $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info'),
        'event' => 'request_complete',
        'request_id' => $context['request_id'],
        'method' => runtimeSafeLogValue((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
        'route' => $context['route'],
        'status' => $status,
        'duration_ms' => max(0, (int) round((microtime(true) - $context['started_at']) * 1000)),
        'outcome' => $outcome,
    ];
    if ($context['reason'] !== null) {
        $record['reason'] = $context['reason'];
    }
    runtimeWriteStructuredRecord($record);
}
