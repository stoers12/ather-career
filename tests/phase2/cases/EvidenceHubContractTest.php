<?php

declare(strict_types=1);

final class EvidenceHubContractTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendations.php';

        $schema = self::json('contracts/evidence-hub-contract-v1.schema.json');
        $taxonomy = self::json('contracts/evidence-hub-taxonomy-v1.json');
        $fixtures = self::json('tests/phase2/fixtures/evidence-hub-golden-fixtures.json');
        $document = self::text('docs/EVIDENCE_HUB_0_LITE.md');

        self::schema($schema);
        self::taxonomy($taxonomy);
        self::versionTwo(self::json('contracts/evidence-hub-contract-v2.schema.json'), self::json('contracts/evidence-hub-taxonomy-v2.json'), $schema, $taxonomy);
        self::fixtures($fixtures, $schema);
        self::privacyAndScope($document, $fixtures);
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $taxonomy @param array<string, mixed> $v1Schema @param array<string, mixed> $v1Taxonomy */
    private static function versionTwo(array $schema, array $taxonomy, array $v1Schema, array $v1Taxonomy): void
    {
        phase2AssertSame('evidence-hub-contract-v2', $schema['properties']['contract_id']['const'] ?? null, 'V2 contract ID is wrong.');
        phase2AssertSame('2.0.0', $schema['properties']['schema_version']['const'] ?? null, 'V2 contract version is wrong.');
        phase2AssertSame('v2', $schema['$defs']['technology_mapping']['properties']['taxonomy_version']['const'] ?? null, 'V2 taxonomy version is wrong.');
        $categories = ['database', 'framework', 'language', 'library', 'platform', 'runtime', 'stylesheet', 'technique', 'tool'];
        phase2AssertSame([...$categories, null], $schema['$defs']['technology_mapping']['properties']['category']['enum'] ?? null, 'V2 category enum is wrong.');
        phase2AssertSame('v2', $taxonomy['taxonomy_version'] ?? null, 'V2 taxonomy identity is wrong.');
        phase2AssertSame($categories, $taxonomy['category_order'] ?? null, 'V2 category order is wrong.');
        phase2AssertSame(31, count($taxonomy['entries'] ?? []), 'V2 must contain 31 bounded entries.');
        phase2AssertSame($v1Taxonomy['entries'], array_slice($taxonomy['entries'], 0, 17), 'V2 changed a v1 entry.');
        phase2AssertSame($v1Schema['$defs']['documentation_coverage'], $schema['$defs']['documentation_coverage'], 'V2 changed frozen documentation semantics.');
        phase2AssertSame($v1Schema['$defs']['portfolio_progress'], $schema['$defs']['portfolio_progress'], 'V2 changed frozen progress semantics.');
        self::closed($schema, 'v2');
        $source = self::text('contracts/evidence-hub-contract-v2.schema.json');
        $crlf = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $source));
        phase2AssertSame($schema, json_decode($crlf, true, 512, JSON_THROW_ON_ERROR), 'V2 schema loading differs by checkout line endings.');
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

        foreach (['text_policy_cases', 'repetition_cases', 'documentation_aggregations', 'hub_states', 'technology_mappings', 'portfolio_progress', 'technology_storage_cases', 'technology_aggregations', 'recommendation_lifecycle', 'recommendation_fingerprints', 'recommendation_keys', 'recommendation_key_rejections', 'recommendation_fingerprint_rejections', 'recommendation_identity_separation', 'tenant_denials', 'positive_payloads', 'negative_payloads'] as $family) {
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

        self::recommendationFixtureFamilies($fixtures);
        self::recommendationDispositionStorage($fixtures['recommendation_disposition_storage'] ?? null, $ids);
        self::recommendationCore($fixtures['recommendation_core'] ?? null, $fixtures, $ids);

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
        self::recommendationIdentityFixtures(
            $fixtures['recommendation_keys'],
            $fixtures['recommendation_key_rejections'],
            $fixtures['recommendation_fingerprints'],
            $fixtures['recommendation_fingerprint_rejections'],
            $fixtures['recommendation_identity_separation'],
        );
        phase2Assert(count($fixtures['positive_payloads']) >= 3 && count($fixtures['negative_payloads']) >= 5, 'Schema-shaped positive and negative cases are incomplete.');
    }

    /** @param array<string, bool> $ids */
    private static function recommendationDispositionStorage(mixed $storage, array &$ids): void
    {
        phase2Assert(is_array($storage), 'Recommendation disposition storage fixtures are missing.');
        phase2AssertSame('recommendation_dispositions', $storage['table'] ?? null, 'Disposition table name changed.');
        phase2AssertSame(['portfolio_id', 'recommendation_key', 'rule_version', 'evidence_fingerprint', 'disposition', 'snoozed_until'], $storage['columns'] ?? null, 'Disposition storage columns changed.');
        phase2AssertSame(['portfolio_id', 'recommendation_key'], $storage['primary_key'] ?? null, 'Disposition primary key must be tenant-scoped current state.');
        phase2AssertSame([
            'column' => 'portfolio_id',
            'references_table' => 'portfolios',
            'references_column' => 'id',
            'on_update' => 'RESTRICT',
            'on_delete' => 'RESTRICT',
        ], $storage['foreign_key'] ?? null, 'Disposition foreign key must remain restrictive.');
        phase2AssertSame([
            'recommendation_key' => ['length' => 64, 'character_set' => 'ascii', 'collation' => 'ascii_bin'],
            'evidence_fingerprint' => ['length' => 64, 'character_set' => 'ascii', 'collation' => 'ascii_bin'],
        ], $storage['hash_columns'] ?? null, 'Disposition hashes must remain ASCII binary-safe SHA-256 values.');
        phase2AssertSame(['snoozed', 'dismissed'], $storage['allowed_dispositions'] ?? null, 'Disposition values changed.');

        $policy = $storage['snooze_policy'] ?? null;
        phase2Assert(is_array($policy), 'Disposition snooze policy is missing.');
        phase2AssertSame([14, 1209600, 'UTC'], [$policy['days'] ?? null, $policy['seconds'] ?? null, $policy['timezone'] ?? null], 'Disposition snooze policy must be fourteen UTC days.');
        phase2Assert(is_string($policy['injected_at'] ?? null) && is_string($policy['expected_snoozed_until'] ?? null), 'Disposition snooze timestamps are missing.');
        phase2Assert(preg_match('/Z$/D', $policy['injected_at']) === 1 && preg_match('/Z$/D', $policy['expected_snoozed_until']) === 1, 'Disposition snooze timestamps must be UTC.');
        $injectedAt = new DateTimeImmutable($policy['injected_at']);
        $expectedUntil = new DateTimeImmutable($policy['expected_snoozed_until']);
        phase2AssertSame($policy['seconds'], $expectedUntil->getTimestamp() - $injectedAt->getTimestamp(), 'Disposition snoozed_until must be injected UTC time plus exactly fourteen days.');
        phase2AssertSame($policy['expected_snoozed_until'], $injectedAt->add(new DateInterval('P14D'))->format('Y-m-d\\TH:i:s\\Z'), 'Disposition snooze calendar policy changed.');

        $matchingCases = $storage['matching_cases'] ?? null;
        phase2Assert(is_array($matchingCases) && array_is_list($matchingCases) && count($matchingCases) === 3, 'Disposition matching fixtures are incomplete.');
        foreach ($matchingCases as $case) {
            phase2Assert(is_array($case), 'Disposition matching fixture is invalid.');
            self::uniqueId($ids, $case['id'] ?? null);
            foreach (['stored_recommendation_key', 'stored_evidence_fingerprint', 'candidate_recommendation_key', 'candidate_evidence_fingerprint'] as $field) {
                phase2Assert(is_string($case[$field] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $case[$field]) === 1, "{$case['id']}: {$field} must be a lowercase SHA-256 value.");
            }
            $expectedSuppressed = ($case['stored_recommendation_key'] === $case['candidate_recommendation_key'])
                && ($case['stored_rule_version'] === $case['candidate_rule_version'])
                && ($case['stored_evidence_fingerprint'] === $case['candidate_evidence_fingerprint']);
            phase2AssertSame($expectedSuppressed, $case['expected_suppressed'] ?? null, "{$case['id']}: disposition match must require key, rule version, and fingerprint.");
        }

        phase2AssertSame([
            'context_type' => 'AuthorizedPortfolioContext',
            'list_predicate' => 'portfolio_id = :authorized_portfolio_id',
            'item_predicate' => 'portfolio_id = :authorized_portfolio_id AND recommendation_key = :recommendation_key',
            'write_portfolio_source' => 'authorized_portfolio_id',
            'forbidden_request_authority' => ['owner_id', 'user_id', 'auth0', '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER'],
        ], $storage['tenant_scope'] ?? null, 'Disposition repository tenant scope changed.');
        $crossTenantCases = $storage['cross_tenant_denial_cases'] ?? null;
        phase2Assert(is_array($crossTenantCases) && array_is_list($crossTenantCases) && count($crossTenantCases) === 2, 'Disposition cross-tenant denial fixtures are incomplete.');
        foreach ($crossTenantCases as $case) {
            phase2Assert(is_array($case), 'Disposition cross-tenant denial fixture is invalid.');
            self::uniqueId($ids, $case['id'] ?? null);
            phase2AssertSame(false, $case['expected_found'] ?? $case['expected_allowed'] ?? null, "{$case['id']}: cross-tenant disposition access must be denied.");
            phase2Assert(($case['authorized_portfolio_id'] ?? null) !== ($case['stored_portfolio_id'] ?? $case['target_portfolio_id'] ?? null), "{$case['id']}: denial fixture must use a foreign portfolio.");
        }
        phase2AssertSame(['personal_data', 'project_text', 'target_ref', 'target_id', 'project_id', 'rule_id', 'raw_evidence', 'raw_technology_label', 'owner_id', 'user_id'], $storage['forbidden_stored_fields'] ?? null, 'Disposition storage privacy exclusions changed.');
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
            phase2Assert(is_array($fixture) && is_string($fixture['id'] ?? null), 'Recommendation fingerprint fixture IDs are required.');
            phase2Assert(!isset($byId[$fixture['id']]), "Duplicate recommendation fingerprint fixture {$fixture['id']}.");
            $byId[$fixture['id']] = $fixture;
        }
        phase2AssertSame(['raw_evidence_text', 'raw_technology_label', 'email', 'auth0_subject', 'cookie', 'token', 'filesystem_path', 'showcase_identity', 'database_id'], $byId['REC-FINGERPRINT-EXCLUSIONS']['prohibited_field_names'] ?? null, 'Fingerprint exclusions changed.');
    }

    /** @param array<string, mixed> $fixtures */
    private static function recommendationFixtureFamilies(array $fixtures): void
    {
        foreach ([
            'recommendation_keys' => 11,
            'recommendation_key_rejections' => 33,
            'recommendation_fingerprint_rejections' => 45,
            'recommendation_identity_separation' => 5,
        ] as $family => $expectedCount) {
            $records = $fixtures[$family] ?? null;
            phase2Assert(is_array($records) && array_is_list($records) && $records !== [], "{$family} must be a non-empty list.");
            phase2AssertSame($expectedCount, count($records), "{$family} count changed.");
        }

        $fingerprints = $fixtures['recommendation_fingerprints'] ?? null;
        phase2Assert(is_array($fingerprints) && array_is_list($fingerprints) && $fingerprints !== [], 'recommendation_fingerprints must be a non-empty list.');
        $positiveFingerprints = array_values(array_filter(
            $fingerprints,
            static fn (mixed $fixture): bool => is_array($fixture) && array_key_exists('canonical_input', $fixture),
        ));
        phase2AssertSame(19, count($positiveFingerprints), 'Positive recommendation_fingerprints count changed.');
    }

    /** @param array<string, mixed> $fixtures @param array<string, bool> $ids */
    private static function recommendationCore(mixed $core, array $fixtures, array &$ids): void
    {
        phase2Assert(is_array($core), 'Recommendation core fixtures are missing.');
        $material = $core['synthetic_hmac_material'] ?? null;
        phase2Assert(is_string($material) && $material !== '', 'Recommendation core HMAC material is required.');

        $factsById = [];
        $factSets = $core['fact_sets'] ?? null;
        phase2Assert(is_array($factSets) && array_is_list($factSets), 'Recommendation core fact sets must be a list.');
        phase2AssertSame(5, count($factSets), 'Recommendation core fact-set count changed.');
        foreach ($factSets as $factSet) {
            $id = self::requireFixtureFields($factSet, ['facts'], 'recommendation_core.fact_sets');
            self::uniqueId($ids, $id);
            phase2Assert(is_array($factSet['facts']) && !array_is_list($factSet['facts']), "{$id}: scoped facts must be an object.");
            $factsById[$id] = $factSet['facts'];
        }

        $opaqueTargetCases = $core['opaque_target_cases'] ?? null;
        phase2Assert(is_array($opaqueTargetCases) && array_is_list($opaqueTargetCases), 'Recommendation core opaque-target cases must be a list.');
        phase2AssertSame(3, count($opaqueTargetCases), 'Recommendation core opaque-target case count changed.');
        $opaqueReferences = [];
        foreach ($opaqueTargetCases as $case) {
            $id = self::requireFixtureFields($case, ['tenant_scope_ref', 'target_type', 'target_identity', 'expected_target_ref'], 'recommendation_core.opaque_target_cases');
            self::uniqueId($ids, $id);
            foreach (['tenant_scope_ref', 'target_type', 'target_identity', 'expected_target_ref'] as $field) {
                phase2Assert(is_string($case[$field]) && $case[$field] !== '', "{$id}: {$field} is required.");
            }
            phase2Assert(preg_match('/^[a-z][a-z0-9_]{7,63}$/D', $case['expected_target_ref']) === 1, "{$id}: expected target reference must satisfy the schema.");
            $actual = evidenceHubOpaqueTargetRef($material, $case['tenant_scope_ref'], $case['target_type'], $case['target_identity']);
            phase2AssertSame($case['expected_target_ref'], $actual, "{$id}: opaque target reference changed.");
            phase2AssertSame($actual, evidenceHubOpaqueTargetRef($material, $case['tenant_scope_ref'], $case['target_type'], $case['target_identity']), "{$id}: opaque target reference is not repeatable.");
            phase2Assert(!str_contains($actual, $case['target_identity']), "{$id}: opaque target reference leaks its target identity.");
            $opaqueReferences[] = $actual;
        }
        phase2AssertSame(3, count(array_unique($opaqueReferences, SORT_STRING)), 'Opaque target references must separate tenant and target type domains.');

        $cases = $core['cases'] ?? null;
        phase2Assert(is_array($cases) && array_is_list($cases), 'Recommendation core cases must be a list.');
        phase2AssertSame(12, count($cases), 'Recommendation core case count changed.');
        $executed = 0;
        foreach ($cases as $case) {
            $id = self::requireFixtureFields($case, ['facts_fixture_id', 'dispositions', 'calculation_time_epoch_seconds', 'expected_recommendations'], 'recommendation_core.cases');
            self::uniqueId($ids, $id);
            phase2Assert(is_string($case['facts_fixture_id']) && isset($factsById[$case['facts_fixture_id']]), "{$id}: scoped fact set is missing.");
            phase2Assert(is_array($case['dispositions']) && array_is_list($case['dispositions']), "{$id}: dispositions must be a list.");
            phase2Assert(is_int($case['calculation_time_epoch_seconds']) && $case['calculation_time_epoch_seconds'] >= 0, "{$id}: calculation time must be a non-negative UTC epoch.");
            phase2Assert(is_array($case['expected_recommendations']) && array_is_list($case['expected_recommendations']), "{$id}: expected recommendations must be a list.");
            $actual = buildEvidenceHubRecommendations($factsById[$case['facts_fixture_id']], $case['dispositions'], $case['calculation_time_epoch_seconds'], $material);
            phase2AssertSame($case['expected_recommendations'], $actual, "{$id}: deterministic recommendation output changed.");
            phase2AssertSame($actual, buildEvidenceHubRecommendations($factsById[$case['facts_fixture_id']], $case['dispositions'], $case['calculation_time_epoch_seconds'], $material), "{$id}: recommendation output is not repeatable.");
            foreach ($actual as $recommendation) {
                phase2AssertSame(['rule_id', 'rule_version', 'recommendation_key', 'evidence_fingerprint', 'lifecycle_state', 'priority_rank', 'display_order', 'reason_codes', 'target', 'snoozed_until'], array_keys($recommendation), "{$id}: recommendation shape changed.");
                phase2AssertSame('active', $recommendation['lifecycle_state'], "{$id}: core must emit active visible recommendations only.");
                phase2AssertSame(null, $recommendation['snoozed_until'], "{$id}: core must not manufacture a stored snooze state.");
                phase2Assert(preg_match('/^[a-f0-9]{64}$/D', $recommendation['recommendation_key']) === 1, "{$id}: recommendation key must be a SHA-256 digest.");
                phase2Assert(preg_match('/^[a-f0-9]{64}$/D', $recommendation['evidence_fingerprint']) === 1, "{$id}: evidence fingerprint must be a SHA-256 digest.");
                phase2Assert(preg_match('/^[a-z][a-z0-9_]{7,63}$/D', $recommendation['target']['opaque_target_ref']) === 1, "{$id}: opaque target reference must satisfy the schema.");
            }
            ++$executed;
        }
        phase2AssertSame(12, $executed, 'Not all recommendation core fixtures executed.');

        phase2AssertSame(1768435200, evidenceHubRecommendationSnoozeUntil(1767225600), 'Recommendation snooze duration must remain exactly fourteen UTC days.');
        self::executeRecommendationCoreIdentityCompatibility($fixtures);
    }

    /** @param array<string, mixed> $fixtures */
    private static function executeRecommendationCoreIdentityCompatibility(array $fixtures): void
    {
        foreach ($fixtures['recommendation_keys'] as $fixture) {
            $id = self::requireFixtureFields($fixture, ['canonical_input', 'expected_sha256'], 'recommendation_core.recommendation_keys');
            phase2AssertSame($fixture['expected_sha256'], evidenceHubRecommendationKey($fixture['canonical_input']), "{$id}: core recommendation-key contract changed.");
        }
        foreach ($fixtures['recommendation_key_rejections'] as $fixture) {
            $id = self::requireFixtureFields($fixture, ['input', 'expected_rejection'], 'recommendation_core.recommendation_key_rejections');
            self::expectRecommendationRejection($id, $fixture['expected_rejection'], static fn (): string => evidenceHubRecommendationKey($fixture['input']));
        }
        foreach ($fixtures['recommendation_fingerprints'] as $fixture) {
            if (!is_array($fixture) || !array_key_exists('canonical_input', $fixture)) {
                continue;
            }
            $id = self::requireFixtureFields($fixture, ['canonical_input', 'expected_sha256'], 'recommendation_core.recommendation_fingerprints');
            phase2AssertSame($fixture['expected_sha256'], evidenceHubEvidenceFingerprint($fixture['canonical_input']), "{$id}: core evidence-fingerprint contract changed.");
        }
        foreach ($fixtures['recommendation_fingerprint_rejections'] as $fixture) {
            $id = self::requireFixtureFields($fixture, ['input', 'expected_rejection'], 'recommendation_core.recommendation_fingerprint_rejections');
            self::expectRecommendationRejection($id, $fixture['expected_rejection'], static function () use ($fixture): void {
                evidenceHubEvidenceFingerprint($fixture['input']);
            });
        }
        self::expectRecommendationRejection(
            'REC-CORE-KEY-DIRECT-INVALID-UTF8',
            'invalid_utf8',
            static fn (): string => evidenceHubRecommendationKey([
                'rule_id' => 'add_first_project',
                'target_ref' => "opaque-target-\xC3\x28",
                'target_type' => 'hub',
            ]),
        );
        self::expectRecommendationRejection(
            'REC-CORE-FINGERPRINT-DIRECT-RECURSIVE-FLOAT',
            'floating_point_forbidden',
            static fn (): string => evidenceHubEvidenceFingerprint([
                'predicate_facts' => [
                    'field_completeness_states' => [
                        'problem_statement' => 1.5,
                        'personal_role' => 'complete',
                        'measurable_outcome' => 'complete',
                    ],
                    'reason_codes' => ['FIELD_NOT_AVAILABLE'],
                ],
                'rule_id' => 'complete_project_evidence',
                'rule_version' => '1.0.0',
                'target_ref' => 'opaque-project-alpha',
                'target_type' => 'project',
            ]),
        );
    }

    /** @param array<int, mixed> $keys @param array<int, mixed> $keyRejections @param array<int, mixed> $fingerprints @param array<int, mixed> $fingerprintRejections @param array<int, mixed> $separations */
    private static function recommendationIdentityFixtures(array $keys, array $keyRejections, array $fingerprints, array $fingerprintRejections, array $separations): void
    {
        $keyById = self::executeRecommendationKeys($keys);
        self::executeRecommendationKeyRejections($keyRejections);
        $fingerprintById = self::executeRecommendationFingerprints($fingerprints);
        self::executeRecommendationFingerprintRejections($fingerprintRejections);
        self::executeRecommendationSeparations($separations, $keyById, $fingerprintById);
    }

    /** @param array<int, mixed> $keys @return array<string, array<string, mixed>> */
    private static function executeRecommendationKeys(array $keys): array
    {
        phase2AssertSame(11, count($keys), 'Recommendation-key positive fixture count changed.');
        $byId = [];
        foreach ($keys as $fixture) {
            $id = self::requireFixtureFields($fixture, ['canonical_input', 'canonical_json', 'expected_sha256'], 'recommendation_keys');
            phase2Assert(!isset($byId[$id]), "{$id}: duplicate recommendation-key fixture.");
            phase2Assert(is_string($fixture['canonical_json']), "{$id}: canonical_json must be a string.");
            phase2Assert(is_string($fixture['expected_sha256']), "{$id}: expected_sha256 must be a string.");
            phase2Assert(preg_match('/^[a-f0-9]{64}$/D', $fixture['expected_sha256']) === 1, "{$id}: expected_sha256 format mismatch.");
            self::validateRecommendationKeyInput($fixture['canonical_input']);
            $canonicalJson = self::canonicalJson($fixture['canonical_input']);
            phase2AssertSame($canonicalJson, $fixture['canonical_json'], "{$id}: canonical_json mismatch.");
            phase2AssertSame($fixture['expected_sha256'], self::recommendationKey($fixture['canonical_input']), "{$id}: recommendation_key hash mismatch.");
            $byId[$id] = $fixture;
        }
        self::assertFixtureHashComparisons($byId, 'recommendation_key');
        return $byId;
    }

    /** @param array<int, mixed> $fixtures */
    private static function executeRecommendationKeyRejections(array $fixtures): void
    {
        phase2AssertSame(33, count($fixtures), 'Recommendation-key rejection fixture count changed.');
        $executed = 0;
        foreach ($fixtures as $fixture) {
            $id = self::requireFixtureFields($fixture, ['input', 'expected_rejection'], 'recommendation_key_rejections');
            phase2Assert(is_string($fixture['expected_rejection']) && $fixture['expected_rejection'] !== '', "{$id}: expected_rejection is required.");
            self::expectRecommendationRejection($id, $fixture['expected_rejection'], fn (): string => self::recommendationKey($fixture['input']));
            ++$executed;
        }
        phase2AssertSame(33, $executed, 'Not all recommendation-key rejection fixtures executed.');
        self::expectRecommendationRejection(
            'REC-KEY-DIRECT-INVALID-UTF8',
            'invalid_utf8',
            static fn (): string => self::recommendationKey([
                'rule_id' => 'add_first_project',
                'target_ref' => "opaque-target-\xC3\x28",
                'target_type' => 'hub',
            ]),
        );
    }

    /** @param array<int, mixed> $fingerprints @return array<string, array<string, mixed>> */
    private static function executeRecommendationFingerprints(array $fingerprints): array
    {
        $positive = array_values(array_filter(
            $fingerprints,
            static fn (mixed $fixture): bool => is_array($fixture) && array_key_exists('canonical_input', $fixture),
        ));
        phase2AssertSame(19, count($positive), 'Positive recommendation_fingerprints count changed.');
        $byId = [];
        foreach ($positive as $fixture) {
            $id = self::requireFixtureFields($fixture, ['canonical_input', 'canonical_json', 'expected_sha256'], 'recommendation_fingerprints');
            phase2Assert(!isset($byId[$id]), "{$id}: duplicate recommendation-fingerprint fixture.");
            phase2Assert(is_string($fixture['canonical_json']), "{$id}: canonical_json must be a string.");
            phase2Assert(is_string($fixture['expected_sha256']), "{$id}: expected_sha256 must be a string.");
            phase2Assert(preg_match('/^[a-f0-9]{64}$/D', $fixture['expected_sha256']) === 1, "{$id}: expected_sha256 format mismatch.");
            self::validateRecommendationFingerprintInput($fixture['canonical_input']);
            $canonicalJson = self::canonicalJson($fixture['canonical_input']);
            phase2AssertSame($canonicalJson, $fixture['canonical_json'], "{$id}: canonical_json mismatch.");
            phase2AssertSame($fixture['expected_sha256'], self::fingerprint($fixture['canonical_input']), "{$id}: evidence_fingerprint hash mismatch.");
            $byId[$id] = $fixture;
        }
        self::assertFixtureHashComparisons($byId, 'evidence_fingerprint');
        foreach ([
            ['REC-FINGERPRINT-STABLE', 'REC-FINGERPRINT-IDENTICAL-INPUT', true],
            ['REC-FINGERPRINT-STABLE', 'REC-FINGERPRINT-SHUFFLED-TOP-LEVEL', true],
            ['REC-FINGERPRINT-COMPLETE-STABLE', 'REC-FINGERPRINT-SHUFFLED-PREDICATE', true],
            ['REC-FINGERPRINT-STABLE', 'REC-FINGERPRINT-RELEVANT-CHANGE', false],
            ['REC-FINGERPRINT-STABLE', 'REC-FINGERPRINT-RULE-VERSION-CHANGE', false],
            ['REC-FINGERPRINT-STABLE', 'REC-FINGERPRINT-RULE-ID-CHANGE', false],
            ['REC-FINGERPRINT-STABLE', 'REC-FINGERPRINT-TARGET-TYPE-CHANGE', false],
            ['REC-FINGERPRINT-STABLE', 'REC-FINGERPRINT-TARGET-REF-CHANGE', false],
            ['REC-FINGERPRINT-COMPLETE-STABLE', 'REC-FINGERPRINT-COMPLETE-FIELD-CHANGE', false],
            ['REC-FINGERPRINT-COMPLETE-STABLE', 'REC-FINGERPRINT-COMPLETE-REASON-CHANGE', false],
            ['REC-FINGERPRINT-UNMAPPED-STABLE', 'REC-FINGERPRINT-UNMAPPED-CHANGE', false],
            ['REC-FINGERPRINT-PUBLICATION-STABLE', 'REC-FINGERPRINT-PUBLISHED-CHANGE', false],
            ['REC-FINGERPRINT-PUBLICATION-STABLE', 'REC-FINGERPRINT-PREREQUISITE-CHANGE', false],
            ['REC-FINGERPRINT-STABLE', 'REC-FINGERPRINT-IRRELEVANT-CHANGE', true],
        ] as [$left, $right, $same]) {
            self::assertFixtureHashRelation($byId, $left, $right, $same, 'evidence_fingerprint');
        }
        return $byId;
    }

    /** @param array<int, mixed> $fixtures */
    private static function executeRecommendationFingerprintRejections(array $fixtures): void
    {
        phase2AssertSame(45, count($fixtures), 'Recommendation-fingerprint rejection fixture count changed.');
        $executed = 0;
        foreach ($fixtures as $fixture) {
            $id = self::requireFixtureFields($fixture, ['input', 'expected_rejection'], 'recommendation_fingerprint_rejections');
            phase2Assert(is_string($fixture['expected_rejection']) && $fixture['expected_rejection'] !== '', "{$id}: expected_rejection is required.");
            self::expectRecommendationRejection($id, $fixture['expected_rejection'], static function () use ($fixture): void {
                self::validateRecommendationFingerprintInput($fixture['input']);
            });
            ++$executed;
        }
        phase2AssertSame(45, $executed, 'Not all recommendation-fingerprint rejection fixtures executed.');
        $invalidUtf8 = "\xC3\x28";
        self::expectRecommendationRejection(
            'REC-FP-DIRECT-TOP-LEVEL-INVALID-UTF8',
            'invalid_utf8',
            static function () use ($invalidUtf8): void {
                self::validateRecommendationFingerprintInput([
                    'predicate_facts' => ['has_projects' => false],
                    'rule_id' => 'add_first_project',
                    'rule_version' => $invalidUtf8,
                    'target_ref' => 'hub_home',
                    'target_type' => 'hub',
                ]);
            },
        );
        self::expectRecommendationRejection(
            'REC-FP-DIRECT-NESTED-INVALID-UTF8',
            'invalid_utf8',
            static function () use ($invalidUtf8): void {
                self::validateRecommendationFingerprintInput([
                    'predicate_facts' => [
                        'field_completeness_states' => [
                            'problem_statement' => $invalidUtf8,
                            'personal_role' => 'complete',
                            'measurable_outcome' => 'complete',
                        ],
                        'reason_codes' => ['FIELD_NOT_AVAILABLE'],
                    ],
                    'rule_id' => 'complete_project_evidence',
                    'rule_version' => '1.0.0',
                    'target_ref' => 'opaque-project-alpha',
                    'target_type' => 'project',
                ]);
            },
        );
    }

    /** @param array<int, mixed> $separations @param array<string, array<string, mixed>> $keys @param array<string, array<string, mixed>> $fingerprints */
    private static function executeRecommendationSeparations(array $separations, array $keys, array $fingerprints): void
    {
        phase2AssertSame(5, count($separations), 'Recommendation identity separation fixture count changed.');
        $executed = 0;
        foreach ($separations as $fixture) {
            $id = self::requireFixtureFields($fixture, ['base_key_fixture_id', 'variant_key_fixture_id', 'base_fingerprint_fixture_id', 'variant_fingerprint_fixture_id', 'expected_recommendation_key_equal', 'expected_evidence_fingerprint_equal'], 'recommendation_identity_separation');
            foreach (['base_key_fixture_id', 'variant_key_fixture_id', 'base_fingerprint_fixture_id', 'variant_fingerprint_fixture_id'] as $reference) {
                phase2Assert(is_string($fixture[$reference]) && $fixture[$reference] !== '', "{$id}: {$reference} is required.");
            }
            phase2Assert(is_bool($fixture['expected_recommendation_key_equal']), "{$id}: key equality expectation is required.");
            phase2Assert(is_bool($fixture['expected_evidence_fingerprint_equal']), "{$id}: fingerprint equality expectation is required.");
            phase2Assert(array_key_exists($fixture['base_key_fixture_id'], $keys), "{$id}: missing base key fixture reference.");
            phase2Assert(array_key_exists($fixture['variant_key_fixture_id'], $keys), "{$id}: missing variant key fixture reference.");
            phase2Assert(array_key_exists($fixture['base_fingerprint_fixture_id'], $fingerprints), "{$id}: missing base fingerprint fixture reference.");
            phase2Assert(array_key_exists($fixture['variant_fingerprint_fixture_id'], $fingerprints), "{$id}: missing variant fingerprint fixture reference.");
            phase2AssertSame($fixture['expected_recommendation_key_equal'], hash_equals($keys[$fixture['base_key_fixture_id']]['expected_sha256'], $keys[$fixture['variant_key_fixture_id']]['expected_sha256']), "{$id}: recommendation_key equality mismatch.");
            phase2AssertSame($fixture['expected_evidence_fingerprint_equal'], hash_equals($fingerprints[$fixture['base_fingerprint_fixture_id']]['expected_sha256'], $fingerprints[$fixture['variant_fingerprint_fixture_id']]['expected_sha256']), "{$id}: evidence_fingerprint equality mismatch.");
            ++$executed;
        }
        phase2AssertSame(5, $executed, 'Not all recommendation identity separation fixtures executed.');
    }

    /** @param array<string, array<string, mixed>> $fixtures */
    private static function assertFixtureHashComparisons(array $fixtures, string $label): void
    {
        foreach ($fixtures as $id => $fixture) {
            foreach (['expected_same_as' => true, 'expected_different_from' => false] as $comparison => $expected) {
                if (!array_key_exists($comparison, $fixture)) {
                    continue;
                }
                phase2Assert(is_string($fixture[$comparison]) && $fixture[$comparison] !== '', "{$id}: {$comparison} reference is required.");
                phase2Assert(array_key_exists($fixture[$comparison], $fixtures), "{$id}: {$comparison} reference is missing.");
                phase2AssertSame($expected, hash_equals($fixture['expected_sha256'], $fixtures[$fixture[$comparison]]['expected_sha256']), "{$id}: {$label} comparison mismatch.");
            }
        }
    }

    /** @param array<string, array<string, mixed>> $fixtures */
    private static function assertFixtureHashRelation(array $fixtures, string $left, string $right, bool $same, string $label): void
    {
        phase2Assert(array_key_exists($left, $fixtures) && array_key_exists($right, $fixtures), "{$left}: {$label} comparison fixture is missing.");
        phase2AssertSame($same, hash_equals($fixtures[$left]['expected_sha256'], $fixtures[$right]['expected_sha256']), "{$left}: {$label} comparison with {$right} mismatch.");
    }

    /** @param array<string, mixed> $fixture @param list<string> $required */
    private static function requireFixtureFields(mixed $fixture, array $required, string $family): string
    {
        phase2Assert(is_array($fixture) && !array_is_list($fixture), "{$family} fixture must be an object.");
        phase2Assert(array_key_exists('id', $fixture) && is_string($fixture['id']) && $fixture['id'] !== '', "{$family} fixture id is required.");
        $id = $fixture['id'];
        foreach ($required as $field) {
            phase2Assert(array_key_exists($field, $fixture), "{$id}: required fixture field {$field} is missing.");
        }
        return $id;
    }

    private static function expectRecommendationRejection(string $id, string $expected, callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException $exception) {
            phase2AssertSame($expected, $exception->getMessage(), "{$id}: rejection category mismatch.");
            return;
        }
        phase2Assert(false, "{$id}: input was accepted; expected {$expected}.");
    }

    private static function recommendationKey(mixed $input): string
    {
        self::validateRecommendationKeyInput($input);
        return hash('sha256', self::canonicalJson($input));
    }

    private static function validateRecommendationKeyInput(mixed $input): void
    {
        self::assertNoIdentityFloat($input);
        self::assertIdentityUtf8($input);
        self::assertNoProhibitedIdentityFields($input, 'unauthorized_property');
        if (!is_array($input) || array_is_list($input)) {
            self::rejectRecommendation('closed_object_required');
        }
        $required = ['rule_id', 'target_ref', 'target_type'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $input)) {
                self::rejectRecommendation('missing_' . $field);
            }
        }
        foreach (array_keys($input) as $field) {
            if (in_array($field, $required, true)) {
                continue;
            }
            self::rejectRecommendation(in_array($field, ['predicate_facts', 'rule_version'], true) ? 'unauthorized_property' : 'unknown_property');
        }
        self::validateRecommendationRuleId($input['rule_id']);
        self::validateOpaqueTargetRef($input['target_ref']);
        self::validateRecommendationTargetType($input['target_type']);
    }

    private static function validateRecommendationFingerprintInput(mixed $input): void
    {
        self::assertNoIdentityFloat($input);
        self::assertIdentityUtf8($input);
        self::assertNoProhibitedIdentityFields($input, 'private_field_forbidden');
        if (!is_array($input) || array_is_list($input)) {
            self::rejectRecommendation('closed_object_required');
        }
        $required = ['predicate_facts', 'rule_id', 'rule_version', 'target_ref', 'target_type'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $input)) {
                self::rejectRecommendation('missing_' . $field);
            }
        }
        foreach (array_keys($input) as $field) {
            if (!in_array($field, $required, true)) {
                self::rejectRecommendation('unknown_top_level_property');
            }
        }
        self::validateRecommendationRuleId($input['rule_id']);
        self::validateRuleVersion($input['rule_version']);
        self::validateOpaqueTargetRef($input['target_ref']);
        self::validateRecommendationTargetType($input['target_type']);

        $facts = $input['predicate_facts'];
        if (!is_array($facts) || (array_is_list($facts) && $facts !== [])) {
            self::rejectRecommendation('predicate_facts_must_be_object');
        }
        if ($facts === []) {
            self::rejectRecommendation('missing_required_predicate_key');
        }
        $rule = $input['rule_id'];
        $allowed = match ($rule) {
            'add_first_project' => ['has_projects'],
            'complete_project_evidence' => ['field_completeness_states', 'reason_codes'],
            'review_unmapped_technology' => ['mapping_state', 'normalized_unmapped_label_digest'],
            'complete_portfolio_publication' => ['has_projects', 'portfolio_published', 'publication_prerequisites_met'],
        };
        $factKeys = array_keys($facts);
        if (in_array('target_ref', $factKeys, true)) {
            self::rejectRecommendation('target_ref_forbidden_in_predicate_facts');
        }
        foreach ([
            ['has_projects'],
            ['field_completeness_states', 'reason_codes'],
            ['mapping_state', 'normalized_unmapped_label_digest'],
            ['has_projects', 'portfolio_published', 'publication_prerequisites_met'],
        ] as $otherAllowed) {
            if ($otherAllowed !== $allowed && self::sameKeySet($factKeys, $otherAllowed)) {
                self::rejectRecommendation('predicate_facts_belong_to_different_rule');
            }
        }
        if (array_diff($factKeys, $allowed) !== []) {
            self::rejectRecommendation('unknown_predicate_key');
        }
        if (array_diff($allowed, $factKeys) !== []) {
            self::rejectRecommendation('missing_required_predicate_key');
        }

        match ($rule) {
            'add_first_project' => self::validateBooleanFact($facts['has_projects']),
            'complete_project_evidence' => self::validateCompleteProjectFacts($facts),
            'review_unmapped_technology' => self::validateUnmappedTechnologyFacts($facts),
            'complete_portfolio_publication' => self::validatePublicationFacts($facts),
        };
    }

    private static function validateRecommendationRuleId(mixed $value): void
    {
        if (is_array($value)) {
            self::rejectRecommendation('nested_object_forbidden');
        }
        if (!is_string($value)) {
            self::rejectRecommendation('rule_id_must_be_string');
        }
        if ($value === '') {
            self::rejectRecommendation('empty_rule_id');
        }
        if (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/D', $value) !== 1) {
            self::rejectRecommendation('malformed_rule_id');
        }
        if (!in_array($value, ['add_first_project', 'complete_project_evidence', 'review_unmapped_technology', 'complete_portfolio_publication'], true)) {
            self::rejectRecommendation('unsupported_rule_id');
        }
    }

    private static function validateRuleVersion(mixed $value): void
    {
        if (is_array($value)) {
            self::rejectRecommendation('nested_object_forbidden');
        }
        if (!is_string($value)) {
            self::rejectRecommendation('rule_version_must_be_string');
        }
        if (preg_match('/^\d+\.\d+\.\d+$/D', $value) !== 1) {
            self::rejectRecommendation('malformed_rule_version');
        }
    }

    private static function validateOpaqueTargetRef(mixed $value): void
    {
        if (is_array($value)) {
            self::rejectRecommendation('nested_object_forbidden');
        }
        if (!is_string($value)) {
            self::rejectRecommendation('target_ref_must_be_string');
        }
        if ($value === '') {
            self::rejectRecommendation('empty_target_ref');
        }
        if (preg_match('/^(?:hub_home|opaque-[a-z0-9]+(?:-[a-z0-9]+)*)$/D', $value) !== 1) {
            self::rejectRecommendation('malformed_target_ref');
        }
    }

    private static function validateRecommendationTargetType(mixed $value): void
    {
        if (is_array($value)) {
            self::rejectRecommendation('nested_object_forbidden');
        }
        if (!is_string($value)) {
            self::rejectRecommendation('target_type_must_be_string');
        }
        if ($value === '') {
            self::rejectRecommendation('empty_target_type');
        }
        if (!in_array($value, ['hub', 'project', 'technology', 'portfolio'], true)) {
            self::rejectRecommendation('unsupported_target_type');
        }
    }

    private static function validateBooleanFact(mixed $value): void
    {
        if (!is_bool($value)) {
            self::rejectRecommendation('boolean_required');
        }
    }

    /** @param array<string, mixed> $facts */
    private static function validateCompleteProjectFacts(array $facts): void
    {
        $states = $facts['field_completeness_states'];
        if (!is_array($states) || array_is_list($states) || !self::sameKeySet(array_keys($states), ['problem_statement', 'personal_role', 'measurable_outcome'])) {
            self::rejectRecommendation('field_completeness_states_shape');
        }
        foreach (['problem_statement', 'personal_role', 'measurable_outcome'] as $field) {
            if (!is_string($states[$field]) || !in_array($states[$field], ['unavailable', 'needs_attention', 'complete'], true)) {
                self::rejectRecommendation('field_completeness_state_invalid');
            }
        }
        self::validateReasonCodes($facts['reason_codes']);
    }

    /** @param array<string, mixed> $facts */
    private static function validateUnmappedTechnologyFacts(array $facts): void
    {
        if ($facts['mapping_state'] !== 'unmapped') {
            self::rejectRecommendation('mapping_state_must_be_unmapped');
        }
        if (!is_string($facts['normalized_unmapped_label_digest']) || preg_match('/^[a-f0-9]{64}$/D', $facts['normalized_unmapped_label_digest']) !== 1) {
            self::rejectRecommendation('normalized_unmapped_label_digest_invalid');
        }
    }

    /** @param array<string, mixed> $facts */
    private static function validatePublicationFacts(array $facts): void
    {
        foreach (['has_projects', 'portfolio_published', 'publication_prerequisites_met'] as $field) {
            self::validateBooleanFact($facts[$field]);
        }
    }

    private static function validateReasonCodes(mixed $value): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            self::rejectRecommendation('reason_codes_must_be_list');
        }
        $allowed = ['FIELD_NOT_AVAILABLE', 'GRAPHEME_THRESHOLD_NOT_MET', 'USEFUL_TOKEN_THRESHOLD_NOT_MET', 'DISTINCT_TOKEN_THRESHOLD_NOT_MET', 'REPETITION_SUSPECTED', 'PLACEHOLDER_CONFIRMED'];
        foreach ($value as $reason) {
            if (!is_string($reason) || !in_array($reason, $allowed, true)) {
                self::rejectRecommendation('reason_code_not_controlled');
            }
        }
        if (count($value) !== count(array_unique($value, SORT_STRING))) {
            self::rejectRecommendation('reason_codes_must_be_unique');
        }
    }

    /** @param list<int|string> $left @param list<int|string> $right */
    private static function sameKeySet(array $left, array $right): bool
    {
        sort($left, SORT_STRING);
        sort($right, SORT_STRING);
        return $left === $right;
    }

    private static function assertNoIdentityFloat(mixed $value): void
    {
        if (is_float($value)) {
            self::rejectRecommendation('floating_point_forbidden');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertNoIdentityFloat($item);
            }
        }
    }

    private static function assertIdentityUtf8(mixed $value): void
    {
        if (is_string($value) && @preg_match('//u', $value) !== 1) {
            self::rejectRecommendation('invalid_utf8');
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && @preg_match('//u', $key) !== 1) {
                    self::rejectRecommendation('invalid_utf8');
                }
                self::assertIdentityUtf8($item);
            }
        }
    }

    private static function assertNoProhibitedIdentityFields(mixed $value, string $category): void
    {
        $prohibited = ['raw_evidence_text', 'raw_technology_label', 'normalized_technology_label', 'email', 'auth0_subject', 'cookie', 'token', 'filesystem_path', 'private_media_path', 'showcase_identity', 'database_id', 'owner_id', 'user_id', 'portfolio_id', 'project_id'];
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, $prohibited, true)) {
                self::rejectRecommendation($category);
            }
            self::assertNoProhibitedIdentityFields($item, $category);
        }
    }

    private static function rejectRecommendation(string $category): void
    {
        throw new RuntimeException($category);
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
