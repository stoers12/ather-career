<?php

declare(strict_types=1);

final class EvidenceHubContractTest
{
    private const CONTRACT = 'contracts/evidence-hub-contract-v1.schema.json';
    private const TAXONOMY = 'contracts/evidence-hub-taxonomy-v1.json';
    private const FIXTURES = 'tests/phase2/fixtures/evidence-hub-golden-fixtures.json';

    public static function run(TestEnvironment $environment): void
    {
        $schema = self::readJson(self::CONTRACT);
        $taxonomy = self::readJson(self::TAXONOMY);
        $fixtureSet = self::readJson(self::FIXTURES);
        $document = self::readText('docs/EVIDENCE_HUB_0_LITE.md');

        self::assertSchema($schema);
        self::assertTaxonomy($taxonomy);
        self::assertFixtures($fixtureSet, $schema, $taxonomy);
        self::assertTenantBoundary($document, $fixtureSet);
        self::assertScopeAndNaming($document);
    }

    /** @return array<string, mixed> */
    private static function readJson(string $relativePath): array
    {
        $contents = self::readText($relativePath);
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("{$relativePath} is not valid JSON.", 0, $exception);
        }

        phase2Assert(is_array($decoded) && !array_is_list($decoded), "{$relativePath} must decode to an object.");

        return $decoded;
    }

    private static function assertSchema(array $schema): void
    {
        phase2AssertSame('evidence-hub-contract-v1', $schema['properties']['contract_id']['const'] ?? null, 'Evidence Hub contract identifier is not exact.');
        phase2AssertSame('1.0.0', $schema['properties']['schema_version']['const'] ?? null, 'Evidence Hub schema version is not exact.');
        self::assertClosedObjects($schema, 'root');

        $basisPoints = $schema['$defs']['basis_points'] ?? [];
        phase2AssertSame('integer', $basisPoints['type'] ?? null, 'Basis points must be integers.');
        phase2AssertSame(0, $basisPoints['minimum'] ?? null, 'Basis points must not be negative.');
        phase2AssertSame(10000, $basisPoints['maximum'] ?? null, 'Basis points must not exceed 10000.');

        $coldStates = $schema['$defs']['cold_start']['properties']['state']['enum'] ?? [];
        phase2AssertSame(['zero', 'partial', 'ready'], $coldStates, 'Zero, Partial, and Ready must be explicit contract states.');
        phase2AssertSame(['unavailable', 'low', 'medium', 'high'], $schema['$defs']['evidence_confidence']['enum'] ?? [], 'Evidence confidence enum changed unexpectedly.');
        phase2Assert(str_contains((string) ($schema['$defs']['evidence_confidence']['description'] ?? ''), 'never probability of competence'), 'Evidence confidence must not claim competence probability.');

        $recommendation = $schema['$defs']['recommendation'] ?? [];
        phase2AssertSame(['active', 'snoozed', 'dismissed', 'resolved', 'superseded'], $recommendation['properties']['lifecycle_state']['enum'] ?? [], 'Recommendation lifecycle enum is incomplete.');
        phase2AssertSame(['owner_evidence_hub'], $schema['$defs']['recommendation_target']['properties']['route']['enum'] ?? [], 'Recommendation targets must use a route allow-list.');
        phase2Assert(isset($schema['$defs']['technology_mapping']['properties']['raw_label']), 'Technology raw labels must be preserved in the output contract.');
    }

    private static function assertClosedObjects(array $node, string $path): void
    {
        if (($node['type'] ?? null) === 'object') {
            phase2Assert(($node['additionalProperties'] ?? null) === false, "Controlled object {$path} must reject unknown fields.");
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                self::assertClosedObjects($value, $path . '.' . (string) $key);
            }
        }
    }

    private static function assertTaxonomy(array $taxonomy): void
    {
        phase2AssertSame('evidence-hub-technology-taxonomy', $taxonomy['taxonomy_id'] ?? null, 'Technology taxonomy identity is invalid.');
        phase2AssertSame('v1', $taxonomy['taxonomy_version'] ?? null, 'Technology taxonomy version is invalid.');
        phase2AssertSame('exact_nfc_only', $taxonomy['match_policy'] ?? null, 'Technology taxonomy must forbid fuzzy matching.');
        phase2Assert(isset($taxonomy['entries']) && is_array($taxonomy['entries']) && array_is_list($taxonomy['entries']), 'Technology taxonomy entries are missing.');

        $ids = [];
        $aliases = [];
        $byId = [];
        foreach ($taxonomy['entries'] as $entry) {
            phase2Assert(is_array($entry), 'Technology entry must be an object.');
            foreach (['technology_id', 'canonical_key', 'display_name', 'category', 'aliases', 'deprecated', 'merged', 'replaced_by_id'] as $field) {
                phase2Assert(array_key_exists($field, $entry), "Technology entry is missing {$field}.");
            }
            $id = $entry['technology_id'];
            phase2Assert(is_string($id) && preg_match('/^tech\.[a-z0-9-]+$/', $id) === 1, 'Technology ID is not stable.');
            phase2Assert(!isset($ids[$id]), "Technology ID {$id} is duplicated.");
            $ids[$id] = true;
            $byId[$id] = $entry;
            phase2Assert(is_array($entry['aliases']) && $entry['aliases'] !== [], "Technology {$id} has no exact aliases.");
            foreach ($entry['aliases'] as $alias) {
                phase2Assert(is_string($alias) && $alias !== '', "Technology {$id} contains an invalid alias.");
                phase2Assert(!isset($aliases[$alias]), "Exact alias collision for {$alias}.");
                $aliases[$alias] = $id;
            }
        }

        foreach ($byId as $id => $entry) {
            $replacement = $entry['replaced_by_id'];
            phase2Assert($replacement === null || (is_string($replacement) && isset($byId[$replacement])), "Technology {$id} has an unknown replacement ID.");
            if (($entry['merged'] ?? false) === true) {
                phase2Assert($replacement !== null, "Merged technology {$id} requires replaced_by_id.");
            }
        }

        foreach (['JS' => 'tech.javascript', 'Java' => 'tech.java', 'C' => 'tech.c', 'C++' => 'tech.cpp', 'C#' => 'tech.csharp', 'React' => 'tech.react', 'React Native' => 'tech.react-native', 'SQL' => 'tech.sql', 'SQL Server' => 'tech.sql-server'] as $alias => $expectedId) {
            phase2AssertSame($expectedId, $aliases[$alias] ?? null, "Taxonomy collision guard failed for {$alias}.");
        }
        phase2Assert(!isset($aliases['javascript']), 'Case-altered JavaScript must not map without an explicit exact alias.');
    }

    private static function assertFixtures(array $fixtureSet, array $schema, array $taxonomy): void
    {
        phase2AssertSame('evidence-hub-golden-fixtures-v1', $fixtureSet['fixture_set'] ?? null, 'Fixture set identity is invalid.');
        phase2AssertSame('evidence-hub-contract-v1', $fixtureSet['contract_id'] ?? null, 'Fixture set uses a different contract.');
        phase2AssertSame('1.0.0', $fixtureSet['schema_version'] ?? null, 'Fixture set uses a different schema version.');
        $fixtures = $fixtureSet['fixtures'] ?? null;
        phase2Assert(is_array($fixtures) && array_is_list($fixtures) && count($fixtures) >= 30 && count($fixtures) <= 50, 'Evidence Hub must provide 30 to 50 golden fixtures.');

        $reasonCodes = array_fill_keys($schema['$defs']['reason_code']['enum'] ?? [], true);
        $taxonomyIds = [];
        foreach ($taxonomy['entries'] as $entry) {
            $taxonomyIds[$entry['technology_id']] = true;
        }

        $ids = [];
        $kinds = [];
        $states = [];
        $fixtureText = json_encode($fixtureSet, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        phase2Assert(!preg_match('/@[a-z0-9.-]+\.[a-z]{2,}/i', $fixtureText), 'Fixtures must not contain email addresses.');
        phase2Assert(!preg_match('/(secret|password|api[_-]?key|bearer\s+)/i', $fixtureText), 'Fixtures must not contain secrets.');

        foreach ($fixtures as $fixture) {
            phase2Assert(is_array($fixture), 'Fixture must be an object.');
            $id = $fixture['id'] ?? null;
            phase2Assert(is_string($id) && preg_match('/^(DOC|TECH|PROGRESS|TENANT)-[0-9]{2}-[a-z0-9-]+$/', $id) === 1, 'Fixture ID format is invalid.');
            phase2Assert(!isset($ids[$id]), "Fixture ID {$id} is duplicated.");
            $ids[$id] = true;
            $kind = $fixture['kind'] ?? null;
            phase2Assert(is_string($kind), "Fixture {$id} has no kind.");
            $kinds[$kind] = true;
            $expected = $fixture['expected'] ?? null;
            phase2Assert(is_array($expected), "Fixture {$id} has no independent expected result.");
            foreach (['state', 'metric_status', 'expected_bps', 'confidence', 'reason_codes', 'recommendation_eligibility'] as $field) {
                phase2Assert(array_key_exists($field, $expected), "Fixture {$id} expected result is missing {$field}.");
            }
            phase2Assert(in_array($expected['state'], ['zero', 'partial', 'ready'], true), "Fixture {$id} has an invalid cold-start state.");
            $states[$expected['state']] = true;
            phase2Assert(in_array($expected['metric_status'], ['unavailable', 'needs_attention', 'ready'], true), "Fixture {$id} has an invalid metric status.");
            phase2Assert(in_array($expected['confidence'], ['unavailable', 'low', 'medium', 'high'], true), "Fixture {$id} has an invalid confidence level.");
            phase2Assert(is_bool($expected['recommendation_eligibility']), "Fixture {$id} recommendation eligibility must be boolean.");
            phase2Assert($expected['expected_bps'] === null || (is_int($expected['expected_bps']) && $expected['expected_bps'] >= 0 && $expected['expected_bps'] <= 10000), "Fixture {$id} has invalid BPS.");
            phase2Assert(is_array($expected['reason_codes']), "Fixture {$id} reason codes must be an array.");
            foreach ($expected['reason_codes'] as $reasonCode) {
                phase2Assert(is_string($reasonCode) && isset($reasonCodes[$reasonCode]), "Fixture {$id} references unknown reason code {$reasonCode}.");
            }
            if (array_key_exists('technology_id', $expected) && $expected['technology_id'] !== null) {
                phase2Assert(isset($taxonomyIds[$expected['technology_id']]), "Fixture {$id} references unknown technology ID.");
            }
        }

        foreach (['text_evidence', 'technology_mapping', 'portfolio_progress', 'tenant_isolation'] as $kind) {
            phase2Assert(isset($kinds[$kind]), "Required fixture kind {$kind} is absent.");
        }
        foreach (['zero', 'partial', 'ready'] as $state) {
            phase2Assert(isset($states[$state]), "Required cold-start fixture state {$state} is absent.");
        }
        foreach (['DOC-04-invalid-utf8', 'DOC-05-placeholder', 'DOC-06-lorem', 'DOC-07-repeated-characters', 'DOC-08-repeated-words', 'DOC-17-arabic', 'DOC-18-mixed-arabic-english', 'PROGRESS-01-no-projects', 'PROGRESS-05-trend-ready', 'PROGRESS-06-portfolio-unpublished', 'PROGRESS-07-portfolio-published', 'TENANT-01-owner-a-project-b'] as $requiredId) {
            phase2Assert(isset($ids[$requiredId]), "Required evidence fixture {$requiredId} is absent.");
        }
    }

    private static function assertTenantBoundary(string $document, array $fixtureSet): void
    {
        phase2Assert(str_contains($document, 'Verified Owner Session → Internal User ID → Tenant Scope → Tenant-Scoped Repository → Pure Analytics Core → Contract Mapper → Owner Presenter'), 'Tenant flow is incomplete.');
        phase2Assert(str_contains($document, 'Cache-Control: no-store'), 'Future private response cache policy is missing.');
        foreach (['query/body `owner_id`', 'body/query `portfolio_id`', 'request Auth0 subject', 'forwarded headers', 'inbound request IDs'] as $forbiddenAuthority) {
            phase2Assert(str_contains($document, $forbiddenAuthority), "Tenant threat model omits {$forbiddenAuthority}.");
        }
        foreach ($fixtureSet['fixtures'] as $fixture) {
            if (($fixture['kind'] ?? null) === 'tenant_isolation') {
                phase2AssertSame(['TENANT_AUTHORITY_REJECTED'], $fixture['expected']['reason_codes'] ?? null, 'Tenant fixture must reject request-provided authority.');
            }
        }
    }

    private static function assertScopeAndNaming(string $document): void
    {
        foreach (['Evidence Hub', 'مركز الأدلة', 'تحليلات مسارك', 'EVIDENCE-HUB-0 LITE', 'EVIDENCE-HUB-1A CORE', 'EVIDENCE-HUB-1B UI', '/owner/evidence-hub'] as $requiredName) {
            phase2Assert(str_contains($document, $requiredName), "Official Evidence Hub name {$requiredName} is missing.");
        }
        phase2Assert(!str_contains($document, 'Ather ' . 'Insights'), 'Obsolete Insights naming must not be introduced.');
        foreach (['owner.php', 'owner_projects.php', 'public/owner.php', 'public/owner_projects.php', 'includes'] as $source) {
            $path = PHASE2_REPOSITORY_ROOT . '/' . $source;
            if (is_file($path)) {
                $contents = file_get_contents($path);
                phase2Assert(is_string($contents) && !str_contains($contents, '/owner/evidence-hub'), 'This package must not add the Evidence Hub runtime route.');
            }
        }
        $composer = self::readText('composer.json');
        phase2AssertSame(4, count(json_decode($composer, true, 32, JSON_THROW_ON_ERROR)['require'] ?? []), 'Evidence Hub must not add a dependency.');
    }

    private static function readText(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
