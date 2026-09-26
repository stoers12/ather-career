<?php

declare(strict_types=1);

final class EvidenceHubContractMapperTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_contract_mapper.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_core.php';

        $fixtures = self::fixtures();
        $schema = self::schema();
        self::positivePayloads($fixtures, $schema);
        self::recommendationOutputMapping($fixtures, $schema);
        self::negativeInputs($fixtures);
        self::versionTwoMapping($fixtures, self::schemaV2());
        if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::ownerCoreIntegration(self::schemaV2());
        }
        self::staticDisclosureGuard();
    }

    /** @param array<string, mixed> $fixtures @param array<string, mixed> $schema */
    private static function positivePayloads(array $fixtures, array $schema): void
    {
        foreach ($fixtures['positive_payloads'] as $case) {
            $payload = $case['payload'];
            $actual = mapEvidenceHubContractV1([
                'maturity' => $payload['maturity'],
                'documentation_coverage' => $payload['metrics']['documentation_coverage'],
                'technology_evidence_map' => $payload['metrics']['technology_evidence_map'],
                'portfolio_progress' => $payload['metrics']['portfolio_progress'],
            ], $payload['recommendations']);
            phase2AssertSame($payload, $actual, "{$case['id']}: mapper changed a frozen positive envelope.");
            self::assertFrozenSchemaConformance($schema, $actual, $case['id']);
            phase2AssertSame($actual, mapEvidenceHubContractV1([
                'maturity' => $payload['maturity'],
                'documentation_coverage' => $payload['metrics']['documentation_coverage'],
                'technology_evidence_map' => $payload['metrics']['technology_evidence_map'],
                'portfolio_progress' => $payload['metrics']['portfolio_progress'],
            ], $payload['recommendations']), "{$case['id']}: mapper output is not deterministic.");
        }
    }

    /** @param array<string, mixed> $fixtures @param array<string, mixed> $schema */
    private static function recommendationOutputMapping(array $fixtures, array $schema): void
    {
        $partial = $fixtures['positive_payloads'][1]['payload'];
        $core = [
            'maturity' => $partial['maturity'],
            'documentation_coverage' => $partial['metrics']['documentation_coverage'],
            'technology_evidence_map' => $partial['metrics']['technology_evidence_map'],
            'portfolio_progress' => $partial['metrics']['portfolio_progress'],
            'internal_project_ref' => 99,
            'sensitive_project_text' => 'must-not-map',
        ];
        $recommendationCase = self::recommendationCase($fixtures, 'REC-CORE-FILTER-BEFORE-CAP');
        $actual = mapEvidenceHubContractV1($core, $recommendationCase['expected_recommendations']);
        phase2AssertSame($recommendationCase['expected_recommendations'], $actual['recommendations'], 'Mapper recalculated, reordered, or renumbered R2 recommendations.');
        phase2AssertSame([1, 2, 3], array_column($actual['recommendations'], 'display_order'), 'Mapper did not preserve dense display order after R2 filtering.');
        phase2Assert(!str_contains(json_encode($actual, JSON_THROW_ON_ERROR), 'must-not-map'), 'Mapper exposed a non-allow-listed core field.');
        self::assertFrozenSchemaConformance($schema, $actual, 'R3 recommendation mapping');
    }

    /** @param array<string, mixed> $fixtures */
    private static function negativeInputs(array $fixtures): void
    {
        $payload = $fixtures['positive_payloads'][0]['payload'];
        $baseCore = [
            'maturity' => $payload['maturity'],
            'documentation_coverage' => $payload['metrics']['documentation_coverage'],
            'technology_evidence_map' => $payload['metrics']['technology_evidence_map'],
            'portfolio_progress' => $payload['metrics']['portfolio_progress'],
        ];
        unset($baseCore['documentation_coverage']);
        self::assertMappingFailure(static fn (): array => mapEvidenceHubContractV1($baseCore, []), 'Mapper accepted unavailable documentation_coverage input.');

        $recommendation = self::recommendationCase($fixtures, 'REC-CORE-PROJECT-EVIDENCE')['expected_recommendations'][0];
        unset($recommendation['recommendation_key']);
        self::assertMappingFailure(static fn (): array => mapEvidenceHubContractV1([
            'maturity' => $payload['maturity'],
            'documentation_coverage' => $payload['metrics']['documentation_coverage'],
            'technology_evidence_map' => $payload['metrics']['technology_evidence_map'],
            'portfolio_progress' => $payload['metrics']['portfolio_progress'],
        ], [$recommendation]), 'Mapper accepted a recommendation without its identity key.');

        $recommendation = self::recommendationCase($fixtures, 'REC-CORE-PROJECT-EVIDENCE')['expected_recommendations'][0];
        $recommendation['lifecycle_state'] = 'dismissed';
        self::assertMappingFailure(static fn (): array => mapEvidenceHubContractV1([
            'maturity' => $payload['maturity'],
            'documentation_coverage' => $payload['metrics']['documentation_coverage'],
            'technology_evidence_map' => $payload['metrics']['technology_evidence_map'],
            'portfolio_progress' => $payload['metrics']['portfolio_progress'],
        ], [$recommendation]), 'Mapper accepted a derived recommendation lifecycle state.');

        phase2AssertSame([
            'NEG-EXTRA-FIELD', 'NEG-UNAVAILABLE-BPS', 'NEG-MAPPED-NULL', 'NEG-UNMAPPED-ID', 'NEG-READY-ZERO',
        ], array_column($fixtures['negative_payloads'], 'id'), 'Frozen negative payload fixtures changed.');
        $unavailable = $payload;
        $unavailable['metrics']['documentation_coverage']['coverage_bps'] = 0;
        self::assertMappingFailure(static fn (): array => mapEvidenceHubContractV1([
            'maturity' => $unavailable['maturity'],
            'documentation_coverage' => $unavailable['metrics']['documentation_coverage'],
            'technology_evidence_map' => $unavailable['metrics']['technology_evidence_map'],
            'portfolio_progress' => $unavailable['metrics']['portfolio_progress'],
        ], []), 'Mapper accepted the unavailable numeric-coverage negative payload.');

        $technologyPayload = $fixtures['positive_payloads'][2]['payload'];
        $mappedNull = $technologyPayload;
        $mappedNull['metrics']['technology_evidence_map']['mappings'][0]['canonical_id'] = null;
        self::assertMappingFailure(static fn (): array => mapEvidenceHubContractV1([
            'maturity' => $mappedNull['maturity'],
            'documentation_coverage' => $mappedNull['metrics']['documentation_coverage'],
            'technology_evidence_map' => $mappedNull['metrics']['technology_evidence_map'],
            'portfolio_progress' => $mappedNull['metrics']['portfolio_progress'],
        ], []), 'Mapper accepted the mapped-null negative payload.');

        $unmappedId = $technologyPayload;
        $unmappedId['metrics']['technology_evidence_map']['mappings'][0]['mapping_state'] = 'unmapped';
        self::assertMappingFailure(static fn (): array => mapEvidenceHubContractV1([
            'maturity' => $unmappedId['maturity'],
            'documentation_coverage' => $unmappedId['metrics']['documentation_coverage'],
            'technology_evidence_map' => $unmappedId['metrics']['technology_evidence_map'],
            'portfolio_progress' => $unmappedId['metrics']['portfolio_progress'],
        ], []), 'Mapper accepted the unmapped-identifier negative payload.');

        $readyZero = $payload;
        $readyZero['maturity']['state'] = 'ready';
        self::assertMappingFailure(static fn (): array => mapEvidenceHubContractV1([
            'maturity' => $readyZero['maturity'],
            'documentation_coverage' => $readyZero['metrics']['documentation_coverage'],
            'technology_evidence_map' => $readyZero['metrics']['technology_evidence_map'],
            'portfolio_progress' => $readyZero['metrics']['portfolio_progress'],
        ], []), 'Mapper accepted the ready-zero negative payload.');

        $extra = $payload;
        $extra['metrics']['documentation_coverage']['owner_id'] = 'excluded';
        $extra['metrics']['technology_evidence_map']['mappings'] = [[
            'raw_label' => 'Synthetic',
            'mapping_state' => 'unmapped',
            'taxonomy_version' => 'v1',
            'canonical_id' => null,
            'canonical_key' => null,
            'display_name' => null,
            'category' => null,
            'internal_target' => 'excluded',
        ]];
        $mapped = mapEvidenceHubContractV1([
            'maturity' => $extra['maturity'],
            'documentation_coverage' => $extra['metrics']['documentation_coverage'],
            'technology_evidence_map' => $extra['metrics']['technology_evidence_map'],
            'portfolio_progress' => $extra['metrics']['portfolio_progress'],
        ], []);
        phase2Assert(!array_key_exists('owner_id', $mapped['metrics']['documentation_coverage']), 'Mapper did not exclude the extra-field negative payload.');
        phase2Assert(!array_key_exists('internal_target', $mapped['metrics']['technology_evidence_map']['mappings'][0]), 'Mapper did not exclude a nested non-allow-listed field.');
    }

    /** @param array<string, mixed> $fixtures @param array<string, mixed> $schema */
    private static function versionTwoMapping(array $fixtures, array $schema): void
    {
        $payload = $fixtures['positive_payloads'][2]['payload'];
        foreach ($payload['metrics']['technology_evidence_map']['mappings'] as &$mapping) {
            $mapping['taxonomy_version'] = 'v2';
        }
        unset($mapping);
        $core = [
            'maturity' => $payload['maturity'],
            'documentation_coverage' => $payload['metrics']['documentation_coverage'],
            'technology_evidence_map' => $payload['metrics']['technology_evidence_map'],
            'portfolio_progress' => $payload['metrics']['portfolio_progress'],
        ];
        $actual = mapEvidenceHubContractV2($core, $payload['recommendations']);
        phase2AssertSame('evidence-hub-contract-v2', $actual['contract_id'], 'V2 mapper selected the wrong contract.');
        phase2AssertSame('2.0.0', $actual['schema_version'], 'V2 mapper selected the wrong schema version.');
        phase2AssertSame($schema['required'], array_keys($actual), 'V2 mapper changed the envelope shape.');
        $bad = $core;
        $bad['technology_evidence_map']['mappings'][0]['taxonomy_version'] = 'v1';
        self::assertMappingFailure(static fn (): array => mapEvidenceHubContractV2($bad, []), 'V2 mapper accepted a v1 mapping.');
        $bad = $core;
        $bad['technology_evidence_map']['mappings'][0]['category'] = 'unsupported';
        self::assertMappingFailure(static fn (): array => mapEvidenceHubContractV2($bad, []), 'V2 mapper accepted an unsupported category.');
    }

    /** @param array<string, mixed> $schema */
    private static function ownerCoreIntegration(array $schema): void
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT NULL, is_published INTEGER NOT NULL); CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL);');
        $database->exec("INSERT INTO portfolios (id, owner_user_id, public_slug, is_published) VALUES (10, 1, NULL, 0), (20, 2, 'foreign', 1)");
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $actual = buildAuthorizedEvidenceHubOwnerContract($database, $context, []);
        phase2AssertSame([], $actual['recommendations'], 'Owner-core contract integration changed supplied R2 output.');
        phase2AssertSame('evidence-hub-contract-v2', $actual['contract_id'], 'Owner core did not select v2.');
        phase2AssertSame('2.0.0', $actual['schema_version'], 'Owner core v2 schema version is wrong.');
        phase2AssertSame($schema['required'], array_keys($actual), 'Owner core v2 envelope shape changed.');
    }

    private static function staticDisclosureGuard(): void
    {
        $source = self::read('includes/evidence_hub_contract_mapper.php');
        foreach (['contract_id', 'schema_version', 'documentation_coverage', 'technology_evidence_map', 'portfolio_progress'] as $required) {
            phase2Assert(str_contains($source, $required), "Contract mapper is missing the allow-listed {$required} field.");
        }
        foreach (['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER', 'email', 'auth0', 'filesystem_path', 'private_media_path'] as $forbidden) {
            phase2Assert(!str_contains($source, $forbidden), "Contract mapper exposes forbidden {$forbidden} data.");
        }
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $payload */
    private static function assertFrozenSchemaConformance(array $schema, array $payload, string $caseId): void
    {
        phase2AssertSame($schema['required'], array_keys($payload), "{$caseId}: root envelope field order or allow-list changed.");
        phase2AssertSame('evidence-hub-contract-v1', $payload['contract_id'], "{$caseId}: contract ID changed.");
        phase2AssertSame('1.0.0', $payload['schema_version'], "{$caseId}: schema version changed.");
        phase2Assert(in_array($payload['maturity']['state'], ['zero', 'partial', 'ready'], true), "{$caseId}: maturity does not conform.");
        phase2AssertSame('1.0.0', $payload['maturity']['version'], "{$caseId}: maturity version does not conform.");
        phase2AssertSame(['documentation_coverage', 'technology_evidence_map', 'portfolio_progress'], array_keys($payload['metrics']), "{$caseId}: metrics allow-list changed.");
        phase2Assert(count($payload['recommendations']) <= 3, "{$caseId}: schema recommendation cap was exceeded.");
        foreach ($payload['recommendations'] as $index => $recommendation) {
            phase2AssertSame(['rule_id', 'rule_version', 'recommendation_key', 'evidence_fingerprint', 'lifecycle_state', 'priority_rank', 'display_order', 'reason_codes', 'target', 'snoozed_until'], array_keys($recommendation), "{$caseId}: recommendation allow-list changed.");
            phase2AssertSame('active', $recommendation['lifecycle_state'], "{$caseId}: visible recommendation has a derived lifecycle state.");
            phase2AssertSame(null, $recommendation['snoozed_until'], "{$caseId}: visible recommendation has persisted snooze state.");
            phase2AssertSame($index + 1, $recommendation['display_order'], "{$caseId}: display order is not dense.");
        }
        self::assertNoPrivateFields($payload);
    }

    private static function assertNoPrivateFields(mixed $value): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            phase2Assert(!in_array($key, ['owner_id', 'user_id', 'portfolio_id', 'project_id', 'email', 'auth0_subject', 'filesystem_path', 'private_media_path', 'secret'], true), "Schema payload exposed prohibited {$key}.");
            self::assertNoPrivateFields($item);
        }
    }

    /** @param array<string, mixed> $fixtures @return array<string, mixed> */
    private static function recommendationCase(array $fixtures, string $id): array
    {
        foreach ($fixtures['recommendation_core']['cases'] as $case) {
            if (($case['id'] ?? null) === $id) {
                return $case;
            }
        }
        throw new RuntimeException("Recommendation fixture {$id} is unavailable.");
    }

    private static function assertMappingFailure(callable $probe, string $message): void
    {
        try {
            $probe();
        } catch (EvidenceHubContractMappingException) {
            return;
        }
        throw new RuntimeException($message);
    }

    /** @return array<string, mixed> */
    private static function fixtures(): array
    {
        $decoded = json_decode(self::read('tests/phase2/fixtures/evidence-hub-golden-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
        phase2Assert(is_array($decoded), 'R3 mapper fixtures are invalid.');
        return $decoded;
    }

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        $decoded = json_decode(self::read('contracts/evidence-hub-contract-v1.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        phase2Assert(is_array($decoded), 'Frozen Evidence Hub schema is invalid.');
        return $decoded;
    }

    /** @return array<string, mixed> */
    private static function schemaV2(): array
    {
        $decoded = json_decode(self::read('contracts/evidence-hub-contract-v2.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        phase2Assert(is_array($decoded), 'Evidence Hub v2 schema is invalid.');
        return $decoded;
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");
        return $contents;
    }
}
