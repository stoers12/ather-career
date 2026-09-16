<?php

declare(strict_types=1);

const EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY_ENVIRONMENT_VARIABLE = 'EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY';

final class EvidenceHubHmacConfigurationException extends RuntimeException
{
}

/**
 * Reads the stable Evidence Hub key at the application configuration boundary.
 * This function deliberately performs no caching so isolated tests and future
 * process boundaries cannot inherit a previous configuration value.
 */
function evidenceHubOpaqueTargetHmacKeyFromEnvironment(): string
{
    $configured = getenv(EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY_ENVIRONMENT_VARIABLE);
    if (!is_string($configured)) {
        throw new EvidenceHubHmacConfigurationException('configuration_missing');
    }

    return evidenceHubOpaqueTargetHmacKeyFromValue($configured);
}

/**
 * Validates and decodes a boundary value. The value is intentionally accepted
 * separately from the environment reader so tests can supply independent
 * synthetic configurations without mutating a cached singleton.
 */
function evidenceHubOpaqueTargetHmacKeyFromValue(mixed $configured): string
{
    if (!is_string($configured)) {
        throw new EvidenceHubHmacConfigurationException('configuration_missing');
    }
    if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $configured) !== 1) {
        throw new EvidenceHubHmacConfigurationException('configuration_invalid');
    }

    $decoded = hex2bin($configured);
    if ($decoded === false || strlen($decoded) !== 32) {
        throw new EvidenceHubHmacConfigurationException('configuration_invalid');
    }

    return $decoded;
}
