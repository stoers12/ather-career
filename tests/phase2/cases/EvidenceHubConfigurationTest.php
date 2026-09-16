<?php

declare(strict_types=1);

final class EvidenceHubConfigurationTest
{
    public static function run(TestEnvironment $environment): void
    {
        $configurationPath = PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_configuration.php';
        require_once $configurationPath;

        $fixtures = self::json('tests/phase2/fixtures/evidence-hub-hmac-fixtures.json');
        self::validKeyDecodesToExpectedBytes($fixtures);
        self::invalidValuesRejectWithoutDisclosure($fixtures);
        self::environmentBoundaryDoesNotCache($fixtures);
        self::recommendationCoreReceivesInjectedBytes($fixtures);
        self::recommendationCoreHasNoEnvironmentAccess();
        self::configurationBoundaryHasNoOutputSideEffects();
        self::productionGateReferencesConfigurationBoundary();
    }

    /** @param array<string, mixed> $fixtures */
    private static function validKeyDecodesToExpectedBytes(array $fixtures): void
    {
        $hex = $fixtures['valid_hex'] ?? null;
        $expectedHex = $fixtures['expected_bytes_hex'] ?? null;
        phase2Assert(is_string($hex) && is_string($expectedHex), 'HMAC fixture values are missing.');

        $bytes = evidenceHubOpaqueTargetHmacKeyFromValue($hex);
        phase2AssertSame(32, strlen($bytes), 'A valid HMAC key must decode to exactly 32 bytes.');
        phase2AssertSame($expectedHex, bin2hex($bytes), 'The HMAC key did not decode to the expected literal bytes.');
    }

    /** @param array<string, mixed> $fixtures */
    private static function invalidValuesRejectWithoutDisclosure(array $fixtures): void
    {
        $valid = (string) ($fixtures['valid_hex'] ?? '');
        $invalid = [
            'missing' => false,
            'empty' => '',
            'short' => substr($valid, 0, 62),
            'long' => $valid . '00',
            'leading whitespace' => " {$valid}",
            'trailing whitespace' => "{$valid} ",
            'embedded whitespace' => substr($valid, 0, 32) . ' ' . substr($valid, 32),
            'non-hex' => substr($valid, 0, 63) . 'g',
        ];
        foreach ($invalid as $label => $value) {
            try {
                evidenceHubOpaqueTargetHmacKeyFromValue($value);
                throw new RuntimeException("HMAC {$label} input was accepted.");
            } catch (EvidenceHubHmacConfigurationException $exception) {
                $message = $exception->getMessage();
                phase2Assert(!str_contains($message, $valid), "HMAC {$label} error disclosed key material.");
                phase2Assert(!str_contains($message, '00010203'), "HMAC {$label} error disclosed a key prefix.");
            }
        }
    }

    /** @param array<string, mixed> $fixtures */
    private static function environmentBoundaryDoesNotCache(array $fixtures): void
    {
        $first = (string) ($fixtures['valid_hex'] ?? '');
        $second = (string) ($fixtures['second_valid_hex'] ?? '');
        $previous = getenv('EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY');
        try {
            putenv('EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY=' . $first);
            phase2AssertSame(hex2bin($first), evidenceHubOpaqueTargetHmacKeyFromEnvironment(), 'Environment HMAC key was not decoded.');
            putenv('EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY=' . $second);
            phase2AssertSame(hex2bin($second), evidenceHubOpaqueTargetHmacKeyFromEnvironment(), 'HMAC configuration was cached across independent test values.');
            putenv('EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY=');
            self::expectConfigurationFailure(static fn (): string => evidenceHubOpaqueTargetHmacKeyFromEnvironment(), 'Missing environment HMAC key was accepted.');
        } finally {
            if ($previous === false) {
                putenv('EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY');
            } else {
                putenv('EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY=' . $previous);
            }
        }
    }

    /** @param array<string, mixed> $fixtures */
    private static function recommendationCoreReceivesInjectedBytes(array $fixtures): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendations.php';
        $key = evidenceHubOpaqueTargetHmacKeyFromValue((string) ($fixtures['valid_hex'] ?? ''));
        $facts = [
            'tenant_scope_ref' => 'tenant_alpha',
            'portfolio_target_identity' => 'portfolio_alpha',
            'projects' => [],
            'technology_mappings' => [],
            'portfolio_publication' => ['portfolio_published' => false, 'publication_prerequisites_met' => true],
        ];
        $first = buildEvidenceHubRecommendations($facts, [], 1767225600, $key);
        $second = buildEvidenceHubRecommendations($facts, [], 1767225600, $key);
        phase2AssertSame($first, $second, 'Injected HMAC key did not produce repeatable recommendation references.');
        phase2Assert($first !== [], 'Synthetic core fixture did not produce a recommendation.');
        phase2AssertSame(64, strlen((string) ($first[0]['target']['opaque_target_ref'] ?? '')), 'Recommendation target reference shape changed.');
    }

    private static function recommendationCoreHasNoEnvironmentAccess(): void
    {
        $source = file_get_contents(PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendations.php');
        phase2Assert(is_string($source), 'Recommendation core source is unreadable.');
        phase2Assert(!preg_match('/\bgetenv\s*\(|\$_ENV\b|\$_SERVER\b|\btime\s*\(|new\s+PDO\b|file_get_contents\s*\(/i', $source), 'Pure recommendation core gained environment, clock, storage, or database access.');
    }

    private static function configurationBoundaryHasNoOutputSideEffects(): void
    {
        $source = file_get_contents(PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_configuration.php');
        phase2Assert(is_string($source), 'HMAC configuration source is unreadable.');
        phase2Assert(!preg_match('/\b(trim|str_pad|random_bytes|error_log|var_dump|print_r|serialize|json_encode)\s*\(|\b(echo|print)\b|\$_ENV\b|\$_SERVER\b/i', $source), 'HMAC configuration boundary trims, generates, logs, serializes, prints, or reads request environment state.');
        phase2Assert(!str_contains($source, '0001020304050607'), 'HMAC configuration source contains synthetic key material.');
    }

    private static function productionGateReferencesConfigurationBoundary(): void
    {
        $source = file_get_contents(PHASE2_REPOSITORY_ROOT . '/scripts/check-production-security.php');
        phase2Assert(is_string($source), 'Production security gate source is unreadable.');
        phase2Assert(str_contains($source, 'evidenceHubOpaqueTargetHmacKeyFromEnvironment'), 'Production security gate does not validate the Evidence Hub HMAC configuration.');
        phase2Assert(str_contains($source, 'missing or invalid'), 'Production security gate HMAC failure message is not safe and generic.');
        phase2Assert(!str_contains($source, 'EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY='), 'Production security gate source contains a configured HMAC value.');
    }

    /** @param callable():mixed $operation */
    private static function expectConfigurationFailure(callable $operation, string $message): void
    {
        try {
            $operation();
        } catch (EvidenceHubHmacConfigurationException) {
            return;
        }
        throw new RuntimeException($message);
    }

    /** @return array<string, mixed> */
    private static function json(string $relativePath): array
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        if (!is_string($contents)) {
            throw new RuntimeException("Fixture {$relativePath} is unreadable.");
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException("Fixture {$relativePath} is not an object.");
        }

        return $decoded;
    }
}
