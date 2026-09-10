<?php

declare(strict_types=1);

require_once __DIR__ . '/observability.php';

/** @param array<string, int|string|bool|null> $context */
function reportSecurityEvent(string $event, string $outcome, array $context = []): bool
{
    $record = [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'level' => 'notice',
        'event' => preg_replace('/[^a-z0-9_.-]/', '_', strtolower($event)) ?: 'unknown',
        'outcome' => preg_replace('/[^a-z0-9_.-]/', '_', strtolower($outcome)) ?: 'unknown',
    ];
    $requestId = runtimeActiveRequestId();
    if ($requestId !== null) {
        $record['request_id'] = $requestId;
    }
    $allowed = [
        'internal_user_id', 'portfolio_id', 'resource_type', 'reason', 'scope',
        'media_kind', 'stage', 'mime', 'bytes', 'width', 'height', 'pixels', 'processor', 'duration_ms',
    ];
    foreach ($allowed as $field) {
        if (!array_key_exists($field, $context)) continue;
        $value = $context[$field];
        if (is_int($value) || is_bool($value) || $value === null) {
            $record[$field] = $value;
        } elseif (is_string($value)) {
            $record[$field] = substr((string) preg_replace('/[^A-Za-z0-9_.:-]/', '_', $value), 0, 80);
        }
    }

    try {
        return error_log((string) json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    } catch (Throwable) {
        return false;
    }
}
