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
        self::fixtures($fixtures, $schema);
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

    /** @param array<string, mixed> $schema */
    private static function schema(array $schema): void
    {
        phase2AssertSame('1.0.0', $schema['properties']['schema_version']['const'] ?? null, 'Schema version must remain 1.0.0.');
        self::closed($schema, 'root');
        phase2AssertSame(0, $schema['$defs']['basis_points']['minimum'] ?? null, 'BPS minimum changed.');
        phase2AssertSame(10000, $schema['$defs']['basis_points']['maximum'] ?? null, 'BPS maximum changed.');
        phase2AssertSame(['zero', 'partial', 'ready'], $schema['$defs']['maturity']['properties']['state']['enum'] ?? null, 'Maturity states are incomplete.');
        phase2AssertSame('1.0.0', $schema['$defs']['field_evaluation']['properties']['rule_version']['const'] ?? null, 'Field rule version must be frozen at 1.0.0.');
        phase2Assert(in_array('PROHIBITED_DIRECTIONALITY_CHARACTER', $schema['$defs']['reason_code']['enum'] ?? [], true), 'Directionality rejection needs a stable reason code.');
        phase2AssertSame('not_available', $schema['$defs']['portfolio_progress']['properties']['trend_status']['const'] ?? null, 'v1 trend must remain unavailable.');
        $trendDirection = $schema['$defs']['portfolio_progress']['properties']['trend_direction'] ?? [];
        phase2Assert(is_array($trendDirection) && array_key_exists('const', $trendDirection), 'v1 trend direction must be explicit.');
        phase2AssertSame(null, $trendDirection['const'], 'v1 trend direction must remain null.');
        phase2AssertSame(3, $schema['properties']['recommendations']['maxItems'] ?? null, 'Visible recommendations must be capped at three.');
        $recommendation = $schema['$defs']['recommendation'] ?? [];
        phase2Assert(in_array('recommendation_key', $recommendation['required'] ?? [], true), 'Recommendation key must be required.');
        phase2AssertSame('^[a-f0-9]{64}$', $recommendation['properties']['recommendation_key']['pattern'] ?? null, 'Recommendation key must be a lowercase SHA-256 digest.');
        phase2AssertSame('active', $recommendation['properties']['lifecycle_state']['const'] ?? null, 'Visible recommendations must be active.');
        phase2Assert(array_key_exists('const', $recommendation['properties']['snoozed_until'] ?? []) && $recommendation['properties']['snoozed_until']['const'] === null, 'Visible active recommendations cannot be snoozed.');
        phase2AssertSame(['add_first_project', 'complete_project_evidence', 'review_unmapped_technology', 'complete_portfolio_publication'], $recommendation['properties']['rule_id']['enum'] ?? null, 'Recommendation v1 catalog must be exact.');
        phase2Assert(in_array('PORTFOLIO_NOT_PUBLISHED', $schema['$defs']['reason_code']['enum'] ?? [], true), 'Publication recommendation reason must be controlled.');
        phase2AssertSame(['mapped', 'unmapped'], $schema['$defs']['technology_mapping']['properties']['mapping_state']['enum'] ?? null, 'Technology v1 must not expose manual review.');
        phase2Assert(in_array('TECHNOLOGY_STORAGE_INVALID', $schema['$defs']['reason_code']['enum'] ?? [], true), 'Malformed technology storage needs a stable reason code.');
        foreach (['technology_occurrence_count', 'mapped_occurrence_count', 'unmapped_occurrence_count', 'distinct_technology_count', 'distinct_mapped_technology_count', 'distinct_unmapped_technology_count', 'invalid_technology_storage_project_count'] as $field) {
            phase2Assert(in_array($field, $schema['$defs']['technology_metric']['required'] ?? [], true), "Technology summary {$field} must be required.");
            phase2AssertSame(0, $schema['$defs']['technology_metric']['properties'][$field]['minimum'] ?? null, "Technology summary {$field} must be non-negative.");
        }
        phase2Assert(str_contains((string) ($schema['$defs']['confidence']['description'] ?? ''), 'never competence probability'), 'Confidence meaning is unsafe.');
    }

    /** @param array<string, mixed> $node */
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

    /** @param array<string, mixed> $taxonomy */
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
        }
        foreach (['js' => 'tech.javascript', 'java' => 'tech.java', 'c' => 'tech.c', 'c++' => 'tech.cpp', 'c#' => 'tech.csharp', 'react' => 'tech.react', 'react native' => 'tech.react-native', 'sql' => 'tech.sql', 'microsoft sql server' => 'tech.sql-server', 'jquery' => 'tech.jquery'] as $alias => $id) {
            phase2AssertSame($id, $aliases[$alias] ?? null, "Collision guard failed for {$alias}.");
        }
        $jquery = array_values(array_filter($entries, static fn (array $entry): bool => ($entry['technology_id'] ?? null) === 'tech.jquery'))[0] ?? [];
        phase2AssertSame('library', $jquery['category'] ?? null, 'jQuery must remain a library.');
        phase2AssertSame(false, $jquery['deprecated'] ?? null, 'jQuery is not globally deprecated.');
        phase2AssertSame(false, $jquery['merged'] ?? null, 'jQuery must not merge into JavaScript.');
    }

    /** @param array<string, mixed> $fixtures @param array<string, mixed> $schema */
    private static function fixtures(array $fixtures, array $schema): void
    {
        phase2AssertSame('1.0.0', $fixtures['schema_version'] ?? null, 'Fixtures must use schema 1.0.0.');
        phase2AssertSame('1.0.0', $fixtures['text_rule_version'] ?? null, 'Text fixtures must use frozen rule version 1.0.0.');
        $rules = $fixtures['text_rules'] ?? null;
        phase2Assert(is_array($rules), 'Frozen text rules are missing.');
        foreach ([
            'problem_statement' => [2000, 60, 8, 5],
            'personal_role' => [1500, 30, 5, 4],
            'measurable_outcome' => [1000, 20, 4, 3],
        ] as $field => $expected) {
            $rule = $rules[$field] ?? null;
            phase2Assert(is_array($rule), "Rule {$field} is missing.");
            phase2AssertSame($expected, [
                $rule['maximum_unicode_scalars'] ?? null,
                $rule['minimum_content_graphemes'] ?? null,
                $rule['minimum_useful_tokens'] ?? null,
                $rule['minimum_distinct_tokens'] ?? null,
            ], "Frozen thresholds changed for {$field}.");
        }
        phase2AssertSame([
            'lorem ipsum', 'lorem ipsum dolor sit amet', 'todo', 'tbd', 'coming soon', 'test', 'placeholder', 'n/a',
            'لاحقاً', 'لاحقا', 'قريباً', 'قريبا', 'تجريبي', 'اختبار', 'غير متوفر',
        ], $fixtures['placeholder_vocabulary'] ?? null, 'Frozen placeholder vocabulary changed.');
        phase2AssertSame([], $fixtures['text_fixture_execution']['descriptive_text_cases'] ?? null, 'Frozen TEXT fixtures must remain executable.');

        $reasonCodes = array_fill_keys($schema['$defs']['reason_code']['enum'], true);
        self::assertReasonCodes($fixtures, $reasonCodes, 'fixtures');
        $ids = [];
        foreach ($fixtures['threshold_boundary_matrix'] ?? [] as $matrix) {
            phase2Assert(is_array($matrix), 'Threshold matrix must contain objects.');
            $field = $matrix['field'] ?? null;
            $language = $matrix['language'] ?? null;
            phase2Assert(isset($rules[$field]) && in_array($language, ['ar', 'en'], true), 'Boundary matrix field or language is invalid.');
            $rule = $rules[$field];
            $seen = [];
            foreach ($matrix['cases'] ?? [] as $case) {
                self::uniqueId($ids, $case['id'] ?? null);
                $boundary = $case['boundary'] ?? null;
                $position = $case['position'] ?? null;
                phase2Assert(in_array($boundary, ['content_graphemes', 'useful_tokens', 'distinct_tokens'], true), 'Unknown text boundary.');
                phase2Assert(in_array($position, ['below', 'at', 'above'], true), 'Unknown boundary position.');
                $seen[$boundary . ':' . $position] = true;
                $calculated = $case['calculated'] ?? [];
                phase2Assert(is_array($calculated), 'Boundary calculated facts are missing.');
                $minimum = match ($boundary) {
                    'content_graphemes' => $rule['minimum_content_graphemes'],
                    'useful_tokens' => $rule['minimum_useful_tokens'],
                    'distinct_tokens' => $rule['minimum_distinct_tokens'],
                };
                $actual = $calculated[$boundary] ?? null;
                phase2AssertSame($minimum + match ($position) { 'below' => -1, 'at' => 0, 'above' => 1 }, $actual, 'Text boundary facts are not independent.');
                foreach (($case['expected']['reason_codes'] ?? []) as $reason) {
                    phase2Assert(isset($reasonCodes[$reason]), "Fixture {$case['id']} references unknown reason {$reason}.");
                }
            }
            foreach (['content_graphemes', 'useful_tokens', 'distinct_tokens'] as $boundary) {
                foreach (['below', 'at', 'above'] as $position) {
                    phase2Assert(isset($seen[$boundary . ':' . $position]), "{$field}/{$language} lacks {$boundary}/{$position} coverage.");
                }
            }
        }
        phase2AssertSame(6, count($fixtures['threshold_boundary_matrix'] ?? []), 'Every field must have Arabic and English boundary coverage.');

        foreach (['text_policy_cases', 'repetition_cases', 'documentation_aggregations', 'hub_states', 'technology_mappings', 'portfolio_progress', 'technology_storage_cases', 'technology_aggregations', 'recommendation_lifecycle', 'recommendation_fingerprints', 'tenant_denials', 'positive_payloads', 'negative_payloads'] as $family) {
            phase2Assert(isset($fixtures[$family]) && is_array($fixtures[$family]) && $fixtures[$family] !== [], "Fixture family {$family} is missing.");
            foreach ($fixtures[$family] as $record) {
                if (is_array($record) && array_key_exists('id', $record)) {
                    self::uniqueId($ids, $record['id']);
                }
            }
        }
        foreach (['TEXT-CONCISE-OUTCOME-EN', 'TEXT-CONCISE-OUTCOME-AR', 'TEXT-MIXED-DIGITS', 'TEXT-ARABIC-PUNCTUATION', 'TEXT-EMOJI-PADDING', 'TEXT-PUNCTUATION-PADDING', 'TEXT-NFC-COMPOSED', 'TEXT-NFC-DECOMPOSED', 'TEXT-TATWEEL-REMOVED', 'TEXT-DIACRITIC-TOKEN-IDENTITY', 'TEXT-WHITESPACE-NORMALIZED', 'TEXT-ALLOW-TAB-LF-CR', 'TEXT-REJECT-C0', 'TEXT-REJECT-C1', 'TEXT-REJECT-ZWSP', 'TEXT-REJECT-WORD-JOINER', 'TEXT-REJECT-BOM', 'TEXT-REJECT-BIDI-OVERRIDE', 'TEXT-REJECT-BIDI-ISOLATE', 'TEXT-ALLOW-ZWNJ', 'TEXT-ALLOW-ZWJ', 'TEXT-PLACEHOLDER-EN-NORMALIZED', 'TEXT-PLACEHOLDER-EN-EXACT', 'TEXT-PLACEHOLDER-AR', 'TEXT-PLACEHOLDER-AR-EXACT', 'TEXT-PLACEHOLDER-EMBEDDED', 'TEXT-MAX-PROBLEM', 'TEXT-MAX-PLUS-ONE-PROBLEM', 'TEXT-MAX-ROLE', 'TEXT-MAX-PLUS-ONE-ROLE', 'TEXT-MAX-OUTCOME', 'TEXT-MAX-PLUS-ONE-OUTCOME', 'TEXT-REPETITION-5900-BPS', 'TEXT-REPETITION-6000-BPS', 'TEXT-REPETITION-6100-BPS', 'TEXT-REPETITION-TATWEEL', 'TEXT-REPETITION-DIACRITIC', 'REC-ACTIVE', 'REC-SNOOZED', 'REC-DISMISSED', 'REC-RESOLVED', 'REC-SUPERSEDED'] as $required) {
            phase2Assert(isset($ids[$required]), "Required frozen calibration fixture {$required} is missing.");
        }

        foreach ($fixtures['text_policy_cases'] as $case) {
            foreach (($case['expected']['reason_codes'] ?? []) as $reason) {
                phase2Assert(isset($reasonCodes[$reason]), "Text policy fixture {$case['id']} references unknown reason {$reason}.");
            }
        }
        $policy = [];
        foreach ($fixtures['text_policy_cases'] as $case) {
            $policy[$case['id']] = $case;
        }
        foreach (['TEXT-CONCISE-OUTCOME-EN', 'TEXT-CONCISE-OUTCOME-AR'] as $id) {
            $case = $policy[$id];
            $rule = $rules[$case['field']];
            phase2Assert(($case['calculated']['content_graphemes'] >= $rule['minimum_content_graphemes']) && ($case['calculated']['useful_tokens'] >= $rule['minimum_useful_tokens']) && ($case['calculated']['distinct_tokens'] >= $rule['minimum_distinct_tokens']), "{$id} must pass all approved outcome thresholds.");
            phase2AssertSame('complete', $case['expected']['evidence_status'], "{$id} must be complete.");
        }
        phase2AssertSame($policy['TEXT-NFC-COMPOSED']['calculated'], $policy['TEXT-NFC-DECOMPOSED']['calculated'], 'NFC-equivalent inputs must declare identical facts.');
        phase2AssertSame('مشروع', $policy['TEXT-TATWEEL-REMOVED']['analytical_copy'] ?? null, 'Tatweel must be removed from the analytical copy.');
        foreach (['TEXT-ALLOW-TAB-LF-CR', 'TEXT-ALLOW-ZWNJ', 'TEXT-ALLOW-ZWJ'] as $id) {
            phase2AssertSame('valid', $policy[$id]['expected']['storage_validity'], "{$id} must remain valid storage.");
        }
        foreach (['TEXT-REJECT-C0', 'TEXT-REJECT-C1', 'TEXT-REJECT-ZWSP', 'TEXT-REJECT-WORD-JOINER', 'TEXT-REJECT-BOM', 'TEXT-REJECT-BIDI-OVERRIDE', 'TEXT-REJECT-BIDI-ISOLATE'] as $id) {
            phase2AssertSame('invalid', $policy[$id]['expected']['storage_validity'], "{$id} must reject prohibited input.");
        }
        foreach ($fixtures['repetition_cases'] as $case) {
            $facts = $case['calculated'] ?? [];
            $expected = (($facts['dominant_token_count'] ?? 0) * 10000 >= ($facts['useful_tokens'] ?? 0) * 6000) && ($facts['useful_tokens'] ?? 0) >= 5;
            phase2AssertSame($expected, in_array('REPETITION_SUSPECTED', $case['expected']['reason_codes'] ?? [], true), "Repetition integer boundary failed for {$case['id']}.");
        }
        self::coverage($fixtures['documentation_aggregations']);
        self::maturity($fixtures['hub_states']);
        self::technologyStorage($fixtures['technology_storage_cases'], $fixtures['technology_aggregations']);
        self::recommendations($fixtures['recommendation_lifecycle'], $fixtures['recommendation_fingerprints']);
        self::recommendationV1($fixtures['recommendation_contract_v1'] ?? null);
        phase2Assert(count($fixtures['positive_payloads']) >= 3 && count($fixtures['negative_payloads']) >= 5, 'Schema-shaped positive and negative cases are incomplete.');
    }

    private static function recommendationV1(mixed $contract): void
    {
        phase2Assert(is_array($contract), 'Recommendation v1 contract fixtures are missing.');
        $catalog = $contract['rule_catalog'] ?? null;
        phase2Assert(is_array($catalog) && count($catalog) === 4, 'Recommendation v1 must freeze exactly four rules.');
        phase2AssertSame(['add_first_project', 'complete_project_evidence', 'review_unmapped_technology', 'complete_portfolio_publication'], array_column($catalog, 'rule_id'), 'Recommendation rule order changed.');
        phase2AssertSame([1, 2, 3, 4], array_column($catalog, 'priority_rank'), 'Recommendation priority ranks changed.');
        $identity = $contract['identity_cases'][0] ?? null;
        phase2Assert(is_array($identity), 'Recommendation identity fixture is missing.');
        phase2AssertSame($identity['expected_sha256'] ?? null, hash('sha256', self::canonicalJson($identity['canonical_input'] ?? [])), 'Recommendation key fixture is not independent canonical SHA-256.');
        foreach ($contract['publication_cases'] ?? [] as $case) {
            phase2AssertSame(($case['has_projects'] && !$case['portfolio_published'] && $case['publication_prerequisites_met']), $case['expected'], 'Publication eligibility must use only frozen prerequisites.');
        }
        $visibility = $contract['visibility_cases'][0] ?? [];
        phase2AssertSame(3, $visibility['expected_visible_count'] ?? null, 'Hidden candidates must not consume visible slots.');
        phase2AssertSame([1, 2, 3], $visibility['expected_display_orders'] ?? null, 'Visible display order must be dense.');
    }

    /** @param array<int, mixed> $storageCases @param array<int, mixed> $aggregationCases */
    private static function technologyStorage(array $storageCases, array $aggregationCases): void
    {
        $ids = [];
        foreach ($storageCases as $case) {
            phase2Assert(is_array($case) && is_string($case['id'] ?? null), 'Technology storage fixture is invalid.');
            $ids[$case['id']] = true;
            phase2Assert(in_array($case['expected_storage_state'] ?? null, ['valid', 'invalid'], true), "{$case['id']} storage state is invalid.");
            phase2Assert(is_array($case['expected_reason_codes'] ?? null) && is_array($case['expected_labels'] ?? null), "{$case['id']} storage facts are incomplete.");
            if (($case['expected_storage_state'] ?? null) === 'invalid') {
                phase2AssertSame(['TECHNOLOGY_STORAGE_INVALID'], $case['expected_reason_codes'], "{$case['id']} invalid storage reason changed.");
                phase2AssertSame([], $case['expected_labels'], "{$case['id']} must not retain partial labels.");
            }
        }
        foreach ($aggregationCases as $case) {
            phase2Assert(is_array($case) && is_array($case['storage_case_ids'] ?? null), 'Technology aggregation fixture is invalid.');
            foreach ($case['storage_case_ids'] as $storageId) {
                phase2Assert(isset($ids[$storageId]), "{$case['id']} references an unknown technology storage fixture.");
            }
            foreach (['technology_occurrence_count', 'mapped_occurrence_count', 'unmapped_occurrence_count', 'distinct_technology_count', 'distinct_mapped_technology_count', 'distinct_unmapped_technology_count', 'invalid_technology_storage_project_count'] as $field) {
                phase2Assert(is_int($case[$field] ?? null) && $case[$field] >= 0, "{$case['id']} {$field} must be a non-negative integer.");
            }
            phase2AssertSame($case['technology_occurrence_count'], $case['mapped_occurrence_count'] + $case['unmapped_occurrence_count'], "{$case['id']} occurrence invariant changed.");
            phase2AssertSame($case['distinct_technology_count'], $case['distinct_mapped_technology_count'] + $case['distinct_unmapped_technology_count'], "{$case['id']} distinct invariant changed.");
        }
    }

    /** @param array<string, bool> $ids */
    private static function uniqueId(array &$ids, mixed $id): void
    {
        phase2Assert(is_string($id) && $id !== '' && !isset($ids[$id]), 'Fixture IDs must be unique and non-empty.');
        $ids[$id] = true;
    }

    /** @param array<string, bool> $reasonCodes */
    private static function assertReasonCodes(mixed $value, array $reasonCodes, string $path): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if (in_array($key, ['reason_codes', 'expected_reason_codes'], true)) {
                phase2Assert(is_array($item), "{$path}.{$key} must be an array.");
                foreach ($item as $reason) {
                    phase2Assert(is_string($reason) && isset($reasonCodes[$reason]), "{$path}.{$key} references an unknown reason.");
                }
            } elseif ($key === 'expected_reason') {
                phase2Assert(is_string($item) && isset($reasonCodes[$item]), "{$path}.expected_reason references an unknown reason.");
            }
            self::assertReasonCodes($item, $reasonCodes, $path . '.' . $key);
        }
    }

    /** @param array<int, mixed> $fixtures */
    private static function coverage(array $fixtures): void
    {
        foreach ($fixtures as $fixture) {
            $projects = $fixture['project_count'];
            if ($projects === 0) {
                phase2AssertSame(null, $fixture['expected_bps'], 'No-project coverage must be null.');
                continue;
            }
            phase2AssertSame($projects * 3, $fixture['expected_fields'], 'Every eligible project must contribute three fields.');
            phase2AssertSame((int) floor(($fixture['complete_fields'] / $fixture['expected_fields']) * 10000 + 0.5), $fixture['expected_bps'], 'Coverage BPS must use half-up rounding.');
        }
    }

    /** @param array<int, mixed> $fixtures */
    private static function maturity(array $fixtures): void
    {
        foreach ($fixtures as $fixture) {
            $expected = $fixture['project_count'] === 0 ? 'zero' : ($fixture['complete_project_count'] > 0 ? 'ready' : 'partial');
            phase2AssertSame($expected, $fixture['expected_state'], 'Hub maturity fixture violates the approved predicate.');
        }
    }

    /** @param array<int, mixed> $lifecycle @param array<int, mixed> $fingerprints */
    private static function recommendations(array $lifecycle, array $fingerprints): void
    {
        $states = array_column($lifecycle, 'expected_lifecycle');
        foreach (['active', 'snoozed', 'dismissed', 'resolved', 'superseded'] as $state) {
            phase2Assert(in_array($state, $states, true), "Recommendation lifecycle {$state} is not covered.");
        }
        $byId = [];
        foreach ($fingerprints as $fixture) {
            $byId[$fixture['id']] = $fixture;
            if (isset($fixture['canonical_input'])) {
                $input = $fixture['canonical_input'];
                phase2AssertSame(['predicate_facts', 'rule_id', 'rule_version', 'target_ref', 'target_type'], array_keys($input), 'Fingerprint input keys must be canonical and allow-listed.');
                $allowedFacts = match ($input['rule_id']) {
                    'add_first_project' => ['has_projects'],
                    'complete_project_evidence' => ['field_completeness_states', 'reason_codes', 'target_ref'],
                    'review_unmapped_technology' => ['mapping_state', 'normalized_unmapped_label_digest', 'target_ref'],
                    'complete_portfolio_publication' => ['has_projects', 'portfolio_published', 'publication_prerequisites_met'],
                    default => [],
                };
                phase2Assert($allowedFacts !== [] && array_diff(array_keys($input['predicate_facts']), $allowedFacts) === [], 'Fingerprint predicate facts are not allow-listed.');
                phase2AssertSame($fixture['expected_sha256'] ?? null, self::fingerprint($fixture['canonical_input']), 'Fingerprint fixture hash is not canonical or stable.');
            }
        }
        phase2AssertSame(self::fingerprint($byId['REC-FINGERPRINT-STABLE']['canonical_input']), self::fingerprint($byId['REC-FINGERPRINT-IRRELEVANT-CHANGE']['canonical_input']), 'Irrelevant facts must not affect the fingerprint.');
        phase2Assert(self::fingerprint($byId['REC-FINGERPRINT-STABLE']['canonical_input']) !== self::fingerprint($byId['REC-FINGERPRINT-RELEVANT-CHANGE']['canonical_input']), 'Relevant predicate change must supersede the fingerprint.');
        phase2AssertSame(['raw_evidence_text', 'raw_technology_label', 'email', 'auth0_subject', 'cookie', 'token', 'filesystem_path', 'showcase_identity', 'database_id'], $byId['REC-FINGERPRINT-EXCLUSIONS']['prohibited_field_names'] ?? null, 'Fingerprint exclusions changed.');
    }

    /** @param array<string, mixed> $value */
    private static function fingerprint(array $value): string
    {
        return hash('sha256', self::canonicalJson($value));
    }

    private static function canonicalJson(mixed $value): string
    {
        self::assertNoFloat($value);
        $canonical = self::sortCanonical($value);
        return json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function assertNoFloat(mixed $value): void
    {
        phase2Assert(!is_float($value), 'Canonical fingerprint input must not contain floating-point values.');
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertNoFloat($item);
            }
        }
    }

    private static function sortCanonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'sortCanonical'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortCanonical($item);
        }
        return $value;
    }

    /** @param array<string, mixed> $fixtures */
    private static function privacyAndScope(string $document, array $fixtures): void
    {
        $encoded = json_encode($fixtures, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        phase2Assert(!preg_match('/@[a-z0-9.-]+\.[a-z]{2,}/i', $encoded), 'Fixtures must not include emails.');
        phase2Assert(!preg_match('/(secret|password|api[_-]?key|bearer\s+)/i', $encoded), 'Fixtures must not include secrets.');
        foreach (['Frozen text evidence rule v1.0.0', 'Unicode scalar values', 'U+2066–U+2069', 'dominant_token_count × 10000', 'Long, diverse, syntactically valid nonsense', 'Momen Portfolio is protected showcase data', 'Cache-Control: no-store'] as $required) {
            phase2Assert(str_contains($document, $required), "Documentation is missing {$required}.");
        }
        $positivePayloads = json_encode($fixtures['positive_payloads'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        phase2Assert(!preg_match('/\b(owner_id|portfolio_id|user_id|authz_version|auth0|email|raw_text|fingerprint_text)\b/i', $positivePayloads), 'Positive contract payloads must not expose tenant authority or raw evidence.');
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
