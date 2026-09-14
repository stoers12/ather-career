<?php

declare(strict_types=1);

final class EvidenceHubContractTest
{
    public static function run(TestEnvironment $environment): void
    {
        $schema = self::json('contracts/evidence-hub-contract-v1.schema.json');
        $taxonomy = self::json('contracts/evidence-hub-taxonomy-v1.json');
        $fixtures = self::json('tests/phase2/fixtures/evidence-hub-golden-fixtures.json');
        $document = self::text('docs/EVIDENCE_HUB_0_LITE.md');

        self::schema($schema);
        self::taxonomy($taxonomy);
        self::fixtures($fixtures, $schema, $taxonomy);
        self::privacyAndScope($document, $fixtures);
    }

    /** @return array<string, mixed> */
    private static function json(string $path): array
    {
        try {
            $decoded = json_decode(self::text($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("{$path} is not valid JSON.", 0, $exception);
        }
        phase2Assert(is_array($decoded) && !array_is_list($decoded), "{$path} must contain an object.");
        return $decoded;
    }

    private static function schema(array $schema): void
    {
        phase2AssertSame('1.0.0', $schema['properties']['schema_version']['const'] ?? null, 'Schema version must remain 1.0.0.');
        self::closed($schema, 'root');
        phase2AssertSame(0, $schema['$defs']['basis_points']['minimum'] ?? null, 'BPS minimum changed.');
        phase2AssertSame(10000, $schema['$defs']['basis_points']['maximum'] ?? null, 'BPS maximum changed.');
        phase2AssertSame(['zero', 'partial', 'ready'], $schema['$defs']['maturity']['properties']['state']['enum'] ?? null, 'Maturity states are incomplete.');
        phase2AssertSame('not_available', $schema['$defs']['portfolio_progress']['properties']['trend_status']['const'] ?? null, 'v1 trend must be unavailable.');
        phase2Assert(array_key_exists('const', $schema['$defs']['portfolio_progress']['properties']['trend_direction'] ?? []) && $schema['$defs']['portfolio_progress']['properties']['trend_direction']['const'] === null, 'v1 trend direction must be null.');
        phase2AssertSame(3, $schema['properties']['recommendations']['maxItems'] ?? null, 'Visible recommendations must be capped at three.');
        phase2AssertSame(['mapped', 'unmapped'], $schema['$defs']['technology_mapping']['properties']['mapping_state']['enum'] ?? null, 'Technology v1 must not expose manual review.');
        phase2Assert(str_contains((string) ($schema['$defs']['confidence']['description'] ?? ''), 'never competence probability'), 'Confidence meaning is unsafe.');
        phase2Assert(isset($schema['$defs']['documentation_coverage']['allOf']), 'Documentation null-BPS condition is missing.');
        phase2Assert(isset($schema['$defs']['technology_mapping']['allOf']), 'Technology mapped/unmapped conditions are missing.');
    }

    private static function closed(array $node, string $path): void
    {
        if (($node['type'] ?? null) === 'object') {
            phase2Assert(($node['additionalProperties'] ?? null) === false, "{$path} must reject unknown properties.");
        }
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                self::closed($value, $path . '.' . $key);
            }
        }
    }

    private static function taxonomy(array $taxonomy): void
    {
        phase2AssertSame('normalized_exact_nfc_latin_casefold', $taxonomy['match_policy'] ?? null, 'Taxonomy matching policy changed.');
        $entries = $taxonomy['entries'] ?? null;
        phase2Assert(is_array($entries) && array_is_list($entries) && count($entries) === 17, 'Taxonomy must remain the bounded 17-entry v1 set.');
        $ids = [];
        $aliases = [];
        foreach ($entries as $entry) {
            phase2Assert(is_array($entry), 'Taxonomy entry must be an object.');
            $id = $entry['technology_id'] ?? null;
            phase2Assert(is_string($id) && preg_match('/^tech\.[a-z0-9-]+$/', $id) === 1 && !isset($ids[$id]), 'Technology IDs must be unique and stable.');
            $ids[$id] = true;
            foreach ($entry['aliases'] ?? [] as $alias) {
                phase2Assert(is_string($alias), 'Alias must be text.');
                $key = self::aliasKey($alias);
                phase2Assert(!isset($aliases[$key]), "Normalized alias collision for {$alias}.");
                $aliases[$key] = $id;
            }
            if (($entry['deprecated'] ?? false) || ($entry['merged'] ?? false)) {
                phase2Assert(is_string($entry['replaced_by_id'] ?? null) && isset($ids[$entry['replaced_by_id']]), 'Deprecated or merged metadata must name an explicit taxonomy entry.');
            }
        }
        foreach (['js' => 'tech.javascript', 'java' => 'tech.java', 'c' => 'tech.c', 'c++' => 'tech.cpp', 'c#' => 'tech.csharp', 'react' => 'tech.react', 'react native' => 'tech.react-native', 'sql' => 'tech.sql', 'microsoft sql server' => 'tech.sql-server', 'jquery' => 'tech.jquery'] as $alias => $id) {
            phase2AssertSame($id, $aliases[$alias] ?? null, "Collision guard failed for {$alias}.");
        }
        $jquery = array_values(array_filter($entries, static fn (array $entry): bool => ($entry['technology_id'] ?? null) === 'tech.jquery'))[0] ?? [];
        phase2AssertSame('library', $jquery['category'] ?? null, 'jQuery must remain a library.');
        phase2AssertSame(false, $jquery['deprecated'] ?? null, 'jQuery is not globally deprecated.');
        phase2AssertSame(false, $jquery['merged'] ?? null, 'jQuery must not merge into JavaScript.');
    }

    private static function fixtures(array $fixtures, array $schema, array $taxonomy): void
    {
        phase2AssertSame('1.0.0', $fixtures['schema_version'] ?? null, 'Fixtures must use schema 1.0.0.');
        foreach (['text_field_evaluations', 'documentation_aggregations', 'hub_states', 'technology_mappings', 'portfolio_progress', 'recommendation_lifecycle', 'tenant_denials', 'positive_payloads', 'negative_payloads'] as $family) {
            phase2Assert(isset($fixtures[$family]) && is_array($fixtures[$family]) && $fixtures[$family] !== [], "Fixture family {$family} is missing.");
        }
        $reasonCodes = array_fill_keys($schema['$defs']['reason_code']['enum'], true);
        $ids = [];
        foreach ($fixtures as $family => $records) {
            if (!is_array($records) || !array_is_list($records)) {
                continue;
            }
            foreach ($records as $record) {
                phase2Assert(is_array($record) && is_string($record['id'] ?? null), "{$family} record lacks an ID.");
                phase2Assert(!isset($ids[$record['id']]), "Fixture ID {$record['id']} is duplicated.");
                $ids[$record['id']] = true;
                foreach (($record['expected']['reason_codes'] ?? $record['reason_codes'] ?? []) as $reason) {
                    phase2Assert(isset($reasonCodes[$reason]), "Fixture {$record['id']} references unknown reason {$reason}.");
                }
            }
        }
        foreach (['TEXT-EN-PROBLEM-STATEMENT-AT', 'TEXT-AR-PROBLEM-STATEMENT-AT', 'TEXT-DIACRITICS-NFC', 'TEXT-NFC-EQUIVALENT', 'TEXT-TATWEEL', 'DOC-METRIC-4-OF-6', 'HUB-STATE-READY', 'TECH-JQUERY', 'PROGRESS-RECORDED-DATES', 'REC-RESOLVED', 'TENANT-PROJECT', 'PAYLOAD-READY', 'NEG-MAPPED-NULL'] as $id) {
            phase2Assert(isset($ids[$id]), "Required calibration fixture {$id} is missing.");
        }
        foreach ($fixtures['documentation_aggregations'] as $fixture) {
            $projects = $fixture['project_count'];
            $complete = $fixture['complete_fields'];
            $expected = $fixture['expected_fields'];
            if ($projects === 0) {
                phase2AssertSame(null, $fixture['expected_bps'], 'No-project coverage must be null.');
                continue;
            }
            phase2AssertSame($projects * 3, $expected, 'Every eligible project must contribute three fields.');
            phase2AssertSame((int) floor(($complete / $expected) * 10000 + 0.5), $fixture['expected_bps'], 'Coverage BPS must use half-up rounding.');
        }
        foreach ($fixtures['hub_states'] as $fixture) {
            $expected = $fixture['project_count'] === 0 ? 'zero' : ($fixture['complete_project_count'] > 0 ? 'ready' : 'partial');
            phase2AssertSame($expected, $fixture['expected_state'], 'Hub maturity fixture violates the approved predicate.');
        }
        foreach ($fixtures['technology_mappings'] as $fixture) {
            phase2Assert(($fixture['expected_state'] === 'mapped') === ($fixture['canonical_id'] !== null), 'Mapped and unmapped technology fixture states must agree with canonical_id.');
        }
        foreach ($fixtures['portfolio_progress'] as $fixture) {
            phase2AssertSame('not_available', $fixture['trend_status'], 'Progress fixtures must not claim a trend.');
            phase2AssertSame(null, $fixture['trend_direction'], 'Progress fixtures must not claim trend direction.');
        }
        phase2Assert(count($fixtures['positive_payloads']) >= 3 && count($fixtures['negative_payloads']) >= 5, 'Schema-shaped positive and negative cases are incomplete.');
        foreach ($fixtures['positive_payloads'] as $fixture) {
            $payload = $fixture['payload'] ?? null;
            phase2Assert(is_array($payload), "Positive payload {$fixture['id']} is not schema-shaped.");
            phase2AssertSame('evidence-hub-contract-v1', $payload['contract_id'] ?? null, "Positive payload {$fixture['id']} has the wrong contract ID.");
            phase2AssertSame('1.0.0', $payload['schema_version'] ?? null, "Positive payload {$fixture['id']} has the wrong schema version.");
            phase2Assert(is_array($payload['metrics'] ?? null) && is_array($payload['recommendations'] ?? null), "Positive payload {$fixture['id']} omits controlled response objects.");
        }
        foreach ($fixtures['negative_payloads'] as $fixture) {
            phase2Assert(is_array($fixture['payload_fragment'] ?? null) && is_string($fixture['violation'] ?? null), "Negative payload {$fixture['id']} is not reviewable.");
        }
    }

    private static function privacyAndScope(string $document, array $fixtures): void
    {
        $encoded = json_encode($fixtures, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        phase2Assert(!preg_match('/@[a-z0-9.-]+\.[a-z]{2,}/i', $encoded), 'Fixtures must not include emails.');
        phase2Assert(!preg_match('/(secret|password|api[_-]?key|bearer\s+)/i', $encoded), 'Fixtures must not include secrets.');
        foreach (['problem_statement', 'Momen Portfolio is protected showcase data', 'ext-intl', 'Cache-Control: no-store', 'At most three recommendations'] as $required) {
            phase2Assert(str_contains($document, $required), "Documentation is missing {$required}.");
        }
        $ambiguousField = 'pro' . 'blem';
        phase2Assert(!preg_match('/\\b' . $ambiguousField . '\\b(?!_statement)/', $document), 'Ambiguous legacy evidence-field terminology remains in documentation.');
        $positivePayloads = json_encode($fixtures['positive_payloads'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        phase2Assert(!preg_match('/\b(owner_id|portfolio_id|user_id|authz_version|auth0|email|raw_text|fingerprint_text)\b/i', $positivePayloads), 'Positive contract payloads must not expose tenant authority or raw evidence.');
        $recommendations = json_encode($fixtures['recommendation_lifecycle'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        phase2Assert(!preg_match('/\b(input|raw_text|email|auth0|token|cookie|path)\b/i', $recommendations), 'Recommendation fixtures must not expose raw evidence or private identifiers.');
    }

    private static function aliasKey(string $alias): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $alias)));
    }

    private static function text(string $path): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $path);
        phase2Assert(is_string($contents), "{$path} is unreadable.");
        return $contents;
    }
}
