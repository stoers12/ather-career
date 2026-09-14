<?php

declare(strict_types=1);

final class EvidenceTextEvaluationTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_text_evaluator.php';

        if (!evidenceTextUnicodeRuntimeIsAvailable()) {
            throw new RuntimeException('Evidence Golden fixture binding requires ext-intl Normalizer, grapheme support, and mbstring.');
        }

        phase2AssertSame('1.0.0', EVIDENCE_TEXT_RULE_VERSION, 'Frozen text rule version changed.');
        self::goldenFixtureBindings();
        self::thresholdBoundaries();
        self::storageAndUnicodePolicy();
        self::placeholdersAndRepetition();
        self::acceptedLimitation();
    }

    private static function goldenFixtureBindings(): void
    {
        $fixtures = self::loadGoldenFixtures();
        self::validateGoldenFixtureContract($fixtures);
        self::fixtureContractMutationGuards($fixtures);
        phase2AssertSame([], $fixtures['text_fixture_execution']['descriptive_text_cases'] ?? null, 'All TEXT fixtures must be executable.');
        $profiles = $fixtures['text_generator_profiles'] ?? null;
        phase2Assert(is_array($profiles), 'Golden text generator profiles are missing.');

        foreach ($fixtures['threshold_boundary_matrix'] ?? [] as $matrix) {
            $matrixId = self::fixtureId($matrix);
            phase2AssertSame('executable', $matrix['execution'] ?? null, "{$matrixId} must be executable.");
            $profileName = $matrix['generator_profile'] ?? null;
            phase2Assert(is_string($profileName) && isset($profiles[$profileName]), "{$matrixId} has no generator profile.");
            foreach ($matrix['cases'] ?? [] as $case) {
                $case['field'] = $matrix['field'] ?? null;
                $case['generator_profile'] = $profileName;
                self::assertGoldenFixture($case, $profiles);
            }
        }

        $evaluations = [];
        foreach ($fixtures['text_policy_cases'] ?? [] as $case) {
            $result = self::assertGoldenFixture($case, $profiles);
            $evaluations[self::fixtureId($case)] = $result;
        }
        foreach ($fixtures['repetition_cases'] ?? [] as $case) {
            self::assertGoldenFixture($case, $profiles);
        }

        $equivalent = $evaluations['TEXT-NFC-DECOMPOSED'] ?? null;
        $composed = $evaluations['TEXT-NFC-COMPOSED'] ?? null;
        phase2Assert(is_array($equivalent) && is_array($composed), 'NFC Golden fixtures are missing.');
        phase2AssertSame(
            [$composed['content_graphemes'], $composed['useful_token_count'], $composed['distinct_token_count']],
            [$equivalent['content_graphemes'], $equivalent['useful_token_count'], $equivalent['distinct_token_count']],
            'NFC Golden fixture facts diverged.',
        );
    }

    /** @return array<string, mixed> */
    private static function loadGoldenFixtures(): array
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-hub-golden-fixtures.json');
        phase2Assert(is_string($contents), 'Golden text fixtures are unreadable.');
        $fixtures = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        phase2Assert(is_array($fixtures) && !array_is_list($fixtures), 'Golden text fixtures must decode to an object.');

        return $fixtures;
    }

    /**
     * The Golden fixture file is a test contract, not loose test data. Keep its
     * shape closed so a misspelled expectation cannot turn into an untested case.
     *
     * @param array<string, mixed> $fixtures
     */
    private static function validateGoldenFixtureContract(array $fixtures): void
    {
        $execution = $fixtures['text_fixture_execution'] ?? null;
        phase2Assert(is_array($execution), 'text_fixture_execution is required.');
        self::assertClosedObject($execution, ['threshold_boundary_matrix', 'text_policy_cases', 'repetition_cases', 'descriptive_text_cases'], 'text_fixture_execution');
        phase2AssertSame('all cases executable from their generator specification', $execution['threshold_boundary_matrix'] ?? null, 'text_fixture_execution.threshold_boundary_matrix is invalid.');
        phase2AssertSame('all cases executable from literal input, encoded bytes, or generator specification', $execution['text_policy_cases'] ?? null, 'text_fixture_execution.text_policy_cases is invalid.');
        phase2AssertSame('all cases executable from literal input or generator specification', $execution['repetition_cases'] ?? null, 'text_fixture_execution.repetition_cases is invalid.');
        phase2AssertSame([], $execution['descriptive_text_cases'] ?? null, 'text_fixture_execution.descriptive_text_cases must remain empty.');

        $profiles = $fixtures['text_generator_profiles'] ?? null;
        phase2Assert(is_array($profiles), 'text_generator_profiles is required.');
        self::assertClosedObject($profiles, ['latin_ascii_v1', 'arabic_letters_v1'], 'text_generator_profiles');
        foreach (['latin_ascii_v1', 'arabic_letters_v1'] as $profileName) {
            phase2Assert(array_key_exists($profileName, $profiles), "text_generator_profiles.{$profileName} is required.");
        }

        $fixtureIds = [];
        $matrices = $fixtures['threshold_boundary_matrix'] ?? null;
        phase2Assert(is_array($matrices) && $matrices !== [], 'threshold_boundary_matrix is required.');
        foreach ($matrices as $matrix) {
            phase2Assert(is_array($matrix), 'threshold_boundary_matrix must contain objects.');
            self::assertClosedObject($matrix, ['id', 'field', 'language', 'execution', 'generator_profile', 'cases'], 'threshold_boundary_matrix entry');
            $matrixId = self::requiredFixtureId($matrix, $fixtureIds);
            $field = self::validatedField($matrix['field'] ?? null, "{$matrixId}.field");
            phase2Assert(is_string($matrix['language'] ?? null) && $matrix['language'] !== '', "{$matrixId}.language is required.");
            phase2AssertSame('executable', $matrix['execution'] ?? null, "{$matrixId}.execution must be executable.");
            $profileName = $matrix['generator_profile'] ?? null;
            phase2Assert(is_string($profileName) && $profileName !== '' && isset($profiles[$profileName]), "{$matrixId}.generator_profile is unknown.");
            self::validateGeneratorProfile($profiles[$profileName], "{$matrixId}.generator_profile.{$profileName}");
            phase2Assert(is_array($matrix['cases'] ?? null) && $matrix['cases'] !== [], "{$matrixId}.cases are required.");
            foreach ($matrix['cases'] as $case) {
                phase2Assert(is_array($case), "{$matrixId}.cases must contain objects.");
                self::validateTextFixture($case, 'boundary', $field, $profileName, $fixtureIds);
            }
        }

        foreach (['text_policy_cases' => 'policy', 'repetition_cases' => 'repetition'] as $family => $subtype) {
            $cases = $fixtures[$family] ?? null;
            phase2Assert(is_array($cases) && $cases !== [], "{$family} is required.");
            foreach ($cases as $case) {
                phase2Assert(is_array($case), "{$family} must contain objects.");
                self::validateTextFixture($case, $subtype, null, null, $fixtureIds);
            }
        }
    }

    /** @param array<string, mixed> $profile */
    private static function validateGeneratorProfile(mixed $profile, string $path): void
    {
        phase2Assert(is_array($profile), "{$path} is required.");
        self::assertClosedObject($profile, ['alphabet', 'token_separator'], $path);
        $alphabet = $profile['alphabet'] ?? null;
        phase2Assert(is_array($alphabet) && array_is_list($alphabet) && count($alphabet) >= 2, "{$path}.alphabet must be a non-empty list.");
        foreach ($alphabet as $index => $symbol) {
            phase2Assert(is_string($symbol) && self::independentUnicodeScalarCount($symbol) === 1, "{$path}.alphabet.{$index} must be one Unicode scalar.");
        }
        phase2Assert(is_string($profile['token_separator'] ?? null), "{$path}.token_separator must be a string.");
    }

    /**
     * @param array<string, mixed> $fixture
     * @param array<string, bool> $fixtureIds
     */
    private static function validateTextFixture(array $fixture, string $subtype, ?string $inheritedField, ?string $generatorProfile, array &$fixtureIds): void
    {
        $allowed = match ($subtype) {
            'boundary' => ['id', 'sample', 'input_spec', 'boundary', 'position', 'calculated', 'expected'],
            'policy' => ['id', 'field', 'language', 'input', 'input_encoding', 'input_base64', 'input_spec', 'calculated', 'expected', 'analytical_copy', 'normalized_equivalent_to', 'accepted_limitation'],
            'repetition' => ['id', 'field', 'language', 'input', 'input_spec', 'calculated', 'expected', 'analytical_copy'],
            default => throw new LogicException("Unknown Golden fixture subtype {$subtype}."),
        };
        $candidateId = $fixture['id'] ?? null;
        phase2Assert(is_string($candidateId) && preg_match('/^TEXT-[A-Z0-9-]+$/', $candidateId) === 1, "{$subtype} fixture.id is invalid.");
        self::assertClosedObject($fixture, $allowed, "{$candidateId}");
        $id = self::requiredFixtureId($fixture, $fixtureIds);
        $field = $inheritedField ?? self::validatedField($fixture['field'] ?? null, "{$id}.field");
        if ($subtype === 'boundary') {
            phase2Assert(is_string($fixture['sample'] ?? null) && $fixture['sample'] !== '', "{$id}.sample is required.");
            phase2Assert(in_array($fixture['boundary'] ?? null, ['content_graphemes', 'useful_tokens', 'distinct_tokens'], true), "{$id}.boundary is invalid.");
            phase2Assert(in_array($fixture['position'] ?? null, ['below', 'at', 'above'], true), "{$id}.position is invalid.");
        } else {
            phase2Assert(is_string($fixture['language'] ?? null) && $fixture['language'] !== '', "{$id}.language is required.");
        }

        self::validateInputMode($fixture, $id, $generatorProfile);
        self::validateExpectedResult($fixture['expected'] ?? null, $id);
        $expected = $fixture['expected'];
        self::validateCalculatedFacts($fixture, $id, $subtype, $expected['storage_validity']);
        if (array_key_exists('analytical_copy', $fixture)) {
            phase2Assert($expected['storage_validity'] === 'valid' && is_string($fixture['analytical_copy']), "{$id}.analytical_copy is invalid.");
        }
        if (array_key_exists('normalized_equivalent_to', $fixture)) {
            phase2Assert(is_string($fixture['normalized_equivalent_to']) && $fixture['normalized_equivalent_to'] !== '', "{$id}.normalized_equivalent_to is invalid.");
        }
        if (array_key_exists('accepted_limitation', $fixture)) {
            phase2Assert(is_string($fixture['accepted_limitation']) && $fixture['accepted_limitation'] !== '', "{$id}.accepted_limitation is invalid.");
        }
        phase2Assert(in_array($field, ['problem_statement', 'personal_role', 'measurable_outcome'], true), "{$id}.field is invalid.");
    }

    /** @param array<string, mixed> $fixture */
    private static function validateInputMode(array $fixture, string $id, ?string $generatorProfile): void
    {
        $hasLiteral = array_key_exists('input', $fixture);
        $hasEncoded = array_key_exists('input_encoding', $fixture) || array_key_exists('input_base64', $fixture);
        $hasGenerated = array_key_exists('input_spec', $fixture);
        phase2Assert(($hasLiteral ? 1 : 0) + ($hasEncoded ? 1 : 0) + ($hasGenerated ? 1 : 0) === 1, "{$id}.input_mode must contain exactly one supported input mode.");

        if ($hasLiteral) {
            $input = $fixture['input'];
            phase2Assert(is_string($input) || (is_array($input) && array_is_list($input)), "{$id}.input has an unsupported primitive type.");
            return;
        }
        if ($hasEncoded) {
            phase2AssertSame('base64_invalid_utf8', $fixture['input_encoding'] ?? null, "{$id}.input_encoding is unsupported.");
            $encoded = $fixture['input_base64'] ?? null;
            phase2Assert(is_string($encoded) && $encoded !== '' && base64_decode($encoded, true) !== false, "{$id}.input_base64 is invalid.");
            return;
        }

        $specification = $fixture['input_spec'];
        phase2Assert(is_array($specification), "{$id}.input_spec must be an object.");
        self::validateInputSpecification($specification, $id, $generatorProfile);
    }

    /** @param array<string, mixed> $specification */
    private static function validateInputSpecification(array $specification, string $id, ?string $generatorProfile): void
    {
        $kind = $specification['kind'] ?? null;
        phase2Assert(is_string($kind) && $kind !== '', "{$id}.input_spec.kind is required.");
        $allowed = match ($kind) {
            'calibrated_token_stream', 'balanced_token_stream' => ['kind', 'content_graphemes', 'useful_tokens', 'distinct_tokens'],
            'repeat_scalar' => ['kind', 'scalar', 'count', 'sha256'],
            'dominant_unique_token_stream' => ['kind', 'dominant_token', 'dominant_count', 'other_token_prefix', 'other_unique_token_count'],
            default => throw new RuntimeException("{$id}.input_spec.kind is unsupported."),
        };
        self::assertClosedObject($specification, $allowed, "{$id}.input_spec");
        if (in_array($kind, ['calibrated_token_stream', 'balanced_token_stream'], true)) {
            phase2Assert(is_string($generatorProfile) && $generatorProfile !== '', "{$id}.generator_profile is required for {$kind}.");
            foreach (['content_graphemes', 'useful_tokens', 'distinct_tokens'] as $key) {
                self::positiveInteger($specification[$key] ?? null, "{$id}.input_spec.{$key}");
            }
            return;
        }
        if ($kind === 'repeat_scalar') {
            $scalar = $specification['scalar'] ?? null;
            phase2Assert(is_string($scalar) && self::independentUnicodeScalarCount($scalar) === 1, "{$id}.input_spec.scalar must be one Unicode scalar.");
            self::positiveInteger($specification['count'] ?? null, "{$id}.input_spec.count");
            phase2Assert(is_string($specification['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $specification['sha256']) === 1, "{$id}.input_spec.sha256 is invalid.");
            return;
        }
        foreach (['dominant_count', 'other_unique_token_count'] as $key) {
            self::positiveInteger($specification[$key] ?? null, "{$id}.input_spec.{$key}");
        }
        foreach (['dominant_token', 'other_token_prefix'] as $key) {
            phase2Assert(is_string($specification[$key] ?? null) && preg_match('/^[a-z]+$/', $specification[$key]) === 1, "{$id}.input_spec.{$key} is invalid.");
        }
    }

    /** @param array<string, mixed> $expected */
    private static function validateExpectedResult(mixed $expected, string $id): void
    {
        phase2Assert(is_array($expected), "{$id}.expected is required.");
        self::assertClosedObject($expected, ['storage_validity', 'evidence_status', 'reason_codes'], "{$id}.expected");
        $storage = $expected['storage_validity'] ?? null;
        $status = $expected['evidence_status'] ?? null;
        phase2Assert(in_array($storage, ['valid', 'invalid'], true), "{$id}.expected.storage_validity is invalid.");
        phase2Assert(in_array($status, ['unavailable', 'needs_attention', 'complete'], true), "{$id}.expected.evidence_status is invalid.");
        phase2Assert($storage !== 'invalid' || $status === 'unavailable', "{$id}.expected invalid storage must be unavailable evidence.");
        self::validateReasonCodeList($expected['reason_codes'] ?? null, "{$id}.expected.reason_codes");
    }

    /** @param array<string, mixed> $fixture */
    private static function validateCalculatedFacts(array $fixture, string $id, string $subtype, string $storageValidity): void
    {
        $hasCalculated = array_key_exists('calculated', $fixture);
        if ($storageValidity === 'invalid') {
            phase2Assert(!$hasCalculated, "{$id}.calculated is forbidden when storage is invalid.");
            return;
        }
        phase2Assert($hasCalculated && is_array($fixture['calculated']), "{$id}.calculated is required for valid storage.");
        $required = ['content_graphemes', 'useful_tokens', 'distinct_tokens'];
        if ($subtype === 'repetition') {
            $required[] = 'dominant_token_count';
            $required[] = 'dominant_token_bps';
        }
        self::assertClosedObject($fixture['calculated'], $required, "{$id}.calculated");
        foreach ($required as $key) {
            phase2Assert(array_key_exists($key, $fixture['calculated']), "{$id}.calculated.{$key} is required for {$subtype} valid storage.");
            phase2Assert(is_int($fixture['calculated'][$key]) && $fixture['calculated'][$key] >= 0, "{$id}.calculated.{$key} must be a non-negative integer.");
        }
        if ($subtype === 'repetition') {
            phase2Assert($fixture['calculated']['dominant_token_count'] <= $fixture['calculated']['useful_tokens'], "{$id}.calculated.dominant_token_count exceeds useful_tokens.");
            phase2Assert($fixture['calculated']['dominant_token_bps'] <= 10000, "{$id}.calculated.dominant_token_bps exceeds 10000.");
        }
    }

    /** @param list<string> $allowed */
    private static function assertClosedObject(array $object, array $allowed, string $path): void
    {
        foreach (array_keys($object) as $key) {
            phase2Assert(is_string($key) && in_array($key, $allowed, true), "{$path}.{$key} is not allowed.");
        }
    }

    /** @param array<string, mixed> $fixture @param array<string, bool> $fixtureIds */
    private static function requiredFixtureId(array $fixture, array &$fixtureIds): string
    {
        $id = $fixture['id'] ?? null;
        phase2Assert(is_string($id) && preg_match('/^TEXT-[A-Z0-9-]+$/', $id) === 1 && !isset($fixtureIds[$id]), 'TEXT fixture id must be unique and canonical.');
        $fixtureIds[$id] = true;
        return $id;
    }

    private static function validatedField(mixed $field, string $path): string
    {
        phase2Assert(is_string($field) && in_array($field, ['problem_statement', 'personal_role', 'measurable_outcome'], true), "{$path} is invalid.");
        return $field;
    }

    private static function validateReasonCodeList(mixed $reasonCodes, string $path): void
    {
        phase2Assert(is_array($reasonCodes) && array_is_list($reasonCodes) && $reasonCodes !== [], "{$path} is required.");
        phase2AssertSame(count($reasonCodes), count(array_unique($reasonCodes)), "{$path} contains duplicates.");
        $allowed = array_fill_keys(self::frozenReasonCodes(), true);
        foreach ($reasonCodes as $reasonCode) {
            phase2Assert(is_string($reasonCode) && isset($allowed[$reasonCode]), "{$path} contains an unknown code.");
        }
    }

    /** @return list<string> */
    private static function frozenReasonCodes(): array
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-contract-v1.schema.json');
        phase2Assert(is_string($contents), 'Evidence Hub schema is unreadable.');
        $schema = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $codes = $schema['$defs']['reason_code']['enum'] ?? null;
        phase2Assert(is_array($codes) && array_is_list($codes), 'Frozen reason-code enum is unavailable.');
        return $codes;
    }

    /** @param array<string, mixed> $fixtures */
    private static function fixtureContractMutationGuards(array $fixtures): void
    {
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'calculated.content_graphemes', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { unset($fixture['calculated']['content_graphemes']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'calculated.useful_tokens', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { unset($fixture['calculated']['useful_tokens']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'calculated.distinct_tokens', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { unset($fixture['calculated']['distinct_tokens']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'expected.evidence_status', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { unset($fixture['expected']['evidence_status']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'expected.storage_validity', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { unset($fixture['expected']['storage_validity']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'expected.reason_codes', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { unset($fixture['expected']['reason_codes']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-REPETITION-6000-BPS', 'calculated.dominant_token_bps', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-REPETITION-6000-BPS', static function (array &$fixture): void { unset($fixture['calculated']['dominant_token_bps']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-PLACEHOLDER-EN-EXACT', 'expected.reason_codes', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-PLACEHOLDER-EN-EXACT', static function (array &$fixture): void { unset($fixture['expected']['reason_codes']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MAX-PROBLEM', 'input_spec.sha256', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MAX-PROBLEM', static function (array &$fixture): void { unset($fixture['input_spec']['sha256']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MAX-PROBLEM', 'input_spec.count', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MAX-PROBLEM', static function (array &$fixture): void { $fixture['input_spec']['count'] = -1; });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'field', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { unset($fixture['field']); });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'input_mode', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { unset($fixture['input']); });
        });

        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'unexpected_top_level', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { $fixture['unexpected_top_level'] = true; });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'expected.unexpected', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { $fixture['expected']['unexpected'] = true; });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'calculated.unexpected', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void { $fixture['calculated']['unexpected'] = true; });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MAX-PROBLEM', 'input_spec.unexpected', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MAX-PROBLEM', static function (array &$fixture): void { $fixture['input_spec']['unexpected'] = true; });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-EN-PROBLEM-BOUNDARIES', 'latin_ascii_v1.unexpected', static function (array &$copy): void {
            $copy['text_generator_profiles']['latin_ascii_v1']['unexpected'] = true;
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-EN-PROBLEM-BOUNDARIES', 'generator_profile', static function (array &$copy): void {
            $copy['threshold_boundary_matrix'][0]['generator_profile'] = 'unknown_generator_profile';
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-EN-PROBLEM-BOUNDARIES', 'alphabet', static function (array &$copy): void {
            unset($copy['text_generator_profiles']['latin_ascii_v1']['alphabet']);
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-EN-PROBLEM-BOUNDARIES', 'alphabet', static function (array &$copy): void {
            $copy['text_generator_profiles']['latin_ascii_v1']['alphabet'] = 'not-a-list';
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MIXED-DIGITS', 'input_mode', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MIXED-DIGITS', static function (array &$fixture): void {
                $fixture['input_spec'] = ['kind' => 'repeat_scalar', 'scalar' => 'a', 'count' => 1, 'sha256' => str_repeat('0', 64)];
            });
        });
        self::assertFixtureContractRejects($fixtures, 'TEXT-MAX-PROBLEM', 'input_spec.kind', static function (array &$copy): void {
            self::mutateTextFixture($copy, 'TEXT-MAX-PROBLEM', static function (array &$fixture): void { $fixture['input_spec']['kind'] = 'unsupported'; });
        });
        self::assertReasonCodeSetSemantics();
    }

    /** @param array<string, mixed> $fixtures */
    private static function assertFixtureContractRejects(array $fixtures, string $fixtureId, string $property, callable $mutation): void
    {
        /** @var array<string, mixed> $copy */
        $copy = unserialize(serialize($fixtures), ['allowed_classes' => false]);
        $mutation($copy);
        try {
            self::validateGoldenFixtureContract($copy);
        } catch (RuntimeException $exception) {
            phase2Assert(str_contains($exception->getMessage(), $fixtureId), "{$fixtureId} malformed-fixture failure must identify its fixture.");
            phase2Assert(str_contains($exception->getMessage(), $property), "{$fixtureId} malformed-fixture failure must name {$property}.");
            return;
        }

        throw new RuntimeException("{$fixtureId} malformed fixture was accepted for {$property}.");
    }

    /** @param array<string, mixed> $fixtures */
    private static function mutateTextFixture(array &$fixtures, string $id, callable $mutation): void
    {
        foreach (['text_policy_cases', 'repetition_cases'] as $family) {
            foreach ($fixtures[$family] as $index => $_fixture) {
                if (($_fixture['id'] ?? null) === $id) {
                    $fixture =& $fixtures[$family][$index];
                    $mutation($fixture);
                    unset($fixture);
                    return;
                }
            }
        }
        foreach ($fixtures['threshold_boundary_matrix'] as $matrixIndex => $matrix) {
            foreach ($matrix['cases'] as $caseIndex => $case) {
                if (($case['id'] ?? null) === $id) {
                    $fixture =& $fixtures['threshold_boundary_matrix'][$matrixIndex]['cases'][$caseIndex];
                    $mutation($fixture);
                    unset($fixture);
                    return;
                }
            }
        }

        throw new RuntimeException("Golden fixture {$id} was not found.");
    }

    private static function assertReasonCodeSetSemantics(): void
    {
        self::assertExactReasonCodes(
            'TEXT-REASON-ORDER-SET',
            ['DISTINCT_TOKEN_THRESHOLD_NOT_MET', 'GRAPHEME_THRESHOLD_NOT_MET', 'USEFUL_TOKEN_THRESHOLD_NOT_MET'],
            ['GRAPHEME_THRESHOLD_NOT_MET', 'USEFUL_TOKEN_THRESHOLD_NOT_MET', 'DISTINCT_TOKEN_THRESHOLD_NOT_MET'],
        );
        self::assertReasonCodeComparisonFails('TEXT-REASON-MISSING', ['GRAPHEME_THRESHOLD_NOT_MET'], ['GRAPHEME_THRESHOLD_NOT_MET', 'USEFUL_TOKEN_THRESHOLD_NOT_MET']);
        self::assertReasonCodeComparisonFails('TEXT-REASON-ADDITIONAL', ['GRAPHEME_THRESHOLD_NOT_MET', 'USEFUL_TOKEN_THRESHOLD_NOT_MET'], ['GRAPHEME_THRESHOLD_NOT_MET']);
        self::assertReasonCodeComparisonFails('TEXT-REASON-DUPLICATE', ['GRAPHEME_THRESHOLD_NOT_MET', 'GRAPHEME_THRESHOLD_NOT_MET'], ['GRAPHEME_THRESHOLD_NOT_MET']);
        self::assertReasonCodeComparisonFails('TEXT-REASON-ACTUAL-DUPLICATE', ['GRAPHEME_THRESHOLD_NOT_MET'], ['GRAPHEME_THRESHOLD_NOT_MET', 'GRAPHEME_THRESHOLD_NOT_MET']);
        self::assertReasonCodeComparisonFails('TEXT-REASON-UNKNOWN', ['UNKNOWN_REASON_CODE'], ['GRAPHEME_THRESHOLD_NOT_MET']);
    }

    /** @param list<string> $expected @param list<string> $actual */
    private static function assertReasonCodeComparisonFails(string $fixtureId, array $expected, array $actual): void
    {
        try {
            self::assertExactReasonCodes($fixtureId, $expected, $actual);
        } catch (RuntimeException) {
            return;
        }

        throw new RuntimeException("{$fixtureId} invalid reason-code comparison was accepted.");
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $profiles @return array<string, mixed> */
    private static function assertGoldenFixture(array $fixture, array $profiles): array
    {
        $id = self::fixtureId($fixture);
        $field = $fixture['field'];
        $expected = $fixture['expected'];
        $calculated = $fixture['calculated'] ?? null;
        phase2Assert(is_string($field), "{$id} field must be validated before evaluation.");
        phase2Assert(is_array($expected), "{$id} expected result must be validated before evaluation.");

        $input = self::materializeGoldenInput($fixture, $profiles);
        $result = evaluateEvidenceText($field, $input);

        phase2AssertSame($expected['storage_validity'], $result['storage_validity'], "{$id} storage_validity mismatch.");
        phase2AssertSame($expected['evidence_status'], $result['evidence_status'], "{$id} evidence_status mismatch.");
        self::assertExactReasonCodes($id, $expected['reason_codes'], $result['reason_codes']);

        if (is_array($calculated)) {
            foreach ([
                'content_graphemes' => 'content_graphemes',
                'useful_tokens' => 'useful_token_count',
                'distinct_tokens' => 'distinct_token_count',
                'dominant_token_count' => 'dominant_token_count',
            ] as $fixtureKey => $resultKey) {
                if (array_key_exists($fixtureKey, $calculated)) {
                    phase2AssertSame($calculated[$fixtureKey], $result[$resultKey], "{$id} {$fixtureKey} mismatch.");
                }
            }
        }
        if (is_array($calculated) && array_key_exists('dominant_token_bps', $calculated)) {
            phase2Assert($result['useful_token_count'] > 0, "{$id} has a dominant-token BPS without tokens.");
            phase2AssertSame(
                $calculated['dominant_token_bps'],
                intdiv($result['dominant_token_count'] * 10000, $result['useful_token_count']),
                "{$id} dominant_token_bps mismatch.",
            );
        }
        if (isset($fixture['analytical_copy'])) {
            phase2AssertSame($fixture['analytical_copy'], $result['analytical_text'], "{$id} analytical_copy mismatch.");
        }

        return $result;
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $profiles */
    private static function materializeGoldenInput(array $fixture, array $profiles): mixed
    {
        if (array_key_exists('input', $fixture)) {
            return $fixture['input'];
        }
        if (($fixture['input_encoding'] ?? null) === 'base64_invalid_utf8') {
            $input = base64_decode((string) ($fixture['input_base64'] ?? ''), true);
            phase2Assert(is_string($input), self::fixtureId($fixture) . ' invalid UTF-8 bytes are not valid base64.');
            return $input;
        }

        $specification = $fixture['input_spec'] ?? null;
        phase2Assert(is_array($specification), self::fixtureId($fixture) . ' has no executable input.');
        return match ($specification['kind'] ?? null) {
            'calibrated_token_stream', 'balanced_token_stream' => self::materializeBoundaryTokenStream($fixture, $specification, $profiles),
            'repeat_scalar' => self::materializeRepeatedScalar($fixture, $specification),
            'dominant_unique_token_stream' => self::materializeDominantTokenStream($fixture, $specification),
            default => throw new RuntimeException(self::fixtureId($fixture) . ' has an unknown input generator.'),
        };
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $specification @param array<string, mixed> $profiles */
    private static function materializeBoundaryTokenStream(array $fixture, array $specification, array $profiles): string
    {
        $id = self::fixtureId($fixture);
        $profileName = $fixture['generator_profile'] ?? null;
        $profile = is_string($profileName) ? ($profiles[$profileName] ?? null) : null;
        phase2Assert(is_array($profile), "{$id} has no usable generator profile.");
        $alphabet = $profile['alphabet'] ?? null;
        $separator = $profile['token_separator'] ?? null;
        phase2Assert(is_array($alphabet) && is_string($separator), "{$id} generator profile is malformed.");

        $contentGraphemes = self::positiveInteger($specification['content_graphemes'] ?? null, "{$id} content_graphemes");
        $usefulTokens = self::positiveInteger($specification['useful_tokens'] ?? null, "{$id} useful_tokens");
        $distinctTokens = self::positiveInteger($specification['distinct_tokens'] ?? null, "{$id} distinct_tokens");
        phase2Assert($contentGraphemes >= $usefulTokens && $usefulTokens >= $distinctTokens && $distinctTokens >= 2, "{$id} boundary generator facts are impossible.");
        phase2Assert(count($alphabet) >= $distinctTokens, "{$id} generator alphabet is too small.");
        foreach ($alphabet as $symbol) {
            phase2Assert(is_string($symbol) && self::independentUnicodeScalarCount($symbol) === 1, "{$id} generator alphabet contains a non-scalar symbol.");
        }

        $tokens = [str_repeat((string) $alphabet[0], $contentGraphemes - $usefulTokens + 1)];
        for ($index = 1; $index < $usefulTokens; ++$index) {
            $tokens[] = (string) $alphabet[1 + (($index - 1) % ($distinctTokens - 1))];
        }
        $input = implode($separator, $tokens);
        phase2AssertSame($contentGraphemes, array_sum(array_map(self::independentUnicodeScalarCount(...), $tokens)), "{$id} generator content count mismatch.");
        phase2AssertSame($usefulTokens, count($tokens), "{$id} generator useful-token count mismatch.");
        phase2AssertSame($distinctTokens, count(array_unique($tokens)), "{$id} generator distinct-token count mismatch.");

        return $input;
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $specification */
    private static function materializeRepeatedScalar(array $fixture, array $specification): string
    {
        $id = self::fixtureId($fixture);
        $scalar = $specification['scalar'] ?? null;
        phase2Assert(is_string($scalar) && self::independentUnicodeScalarCount($scalar) === 1, "{$id} repeat_scalar must be one Unicode scalar.");
        $count = self::positiveInteger($specification['count'] ?? null, "{$id} scalar count");
        $input = str_repeat($scalar, $count);
        phase2AssertSame($count, self::independentUnicodeScalarCount($input), "{$id} generated scalar count mismatch.");
        phase2AssertSame($specification['sha256'] ?? null, hash('sha256', $input), "{$id} generated scalar hash mismatch.");

        return $input;
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $specification */
    private static function materializeDominantTokenStream(array $fixture, array $specification): string
    {
        $id = self::fixtureId($fixture);
        $dominantToken = $specification['dominant_token'] ?? null;
        $prefix = $specification['other_token_prefix'] ?? null;
        phase2Assert(is_string($dominantToken) && preg_match('/^[a-z]+$/', $dominantToken) === 1, "{$id} dominant token is malformed.");
        phase2Assert(is_string($prefix) && preg_match('/^[a-z]+$/', $prefix) === 1, "{$id} other-token prefix is malformed.");
        $dominantCount = self::positiveInteger($specification['dominant_count'] ?? null, "{$id} dominant count");
        $otherCount = self::positiveInteger($specification['other_unique_token_count'] ?? null, "{$id} other count");
        $tokens = array_fill(0, $dominantCount, $dominantToken);
        for ($index = 1; $index <= $otherCount; ++$index) {
            $tokens[] = $prefix . $index;
        }
        $calculated = $fixture['calculated'] ?? [];
        phase2AssertSame($dominantCount + $otherCount, $calculated['useful_tokens'] ?? null, "{$id} generator useful-token specification mismatch.");
        phase2AssertSame($otherCount + 1, $calculated['distinct_tokens'] ?? null, "{$id} generator distinct-token specification mismatch.");
        phase2AssertSame($dominantCount, $calculated['dominant_token_count'] ?? null, "{$id} generator dominant-token specification mismatch.");
        phase2AssertSame(intdiv($dominantCount * 10000, count($tokens)), $calculated['dominant_token_bps'] ?? null, "{$id} generator dominant-token BPS mismatch.");

        return implode(' ', $tokens);
    }

    /** @param list<string>|mixed $expected @param list<string> $actual */
    private static function assertExactReasonCodes(string $fixtureId, mixed $expected, array $actual): void
    {
        phase2Assert(is_array($expected) && array_is_list($expected), "{$fixtureId} reason_codes are malformed.");
        phase2AssertSame(count($expected), count(array_unique($expected)), "{$fixtureId} declares duplicate reason codes.");
        phase2AssertSame(count($actual), count(array_unique($actual)), "{$fixtureId} evaluator returned duplicate reason codes.");
        $allowed = array_fill_keys(self::frozenReasonCodes(), true);
        foreach (array_merge($expected, $actual) as $reasonCode) {
            phase2Assert(is_string($reasonCode) && isset($allowed[$reasonCode]), "{$fixtureId} has an unknown reason code.");
        }
        $expectedSet = $expected;
        $actualSet = $actual;
        sort($expectedSet, SORT_STRING);
        sort($actualSet, SORT_STRING);
        phase2AssertSame($expectedSet, $actualSet, "{$fixtureId} reason_codes mismatch.");
    }

    /** @param array<string, mixed> $fixture */
    private static function fixtureId(array $fixture): string
    {
        $id = $fixture['id'] ?? null;
        phase2Assert(is_string($id) && $id !== '', 'Golden fixture has no ID.');
        return $id;
    }

    private static function positiveInteger(mixed $value, string $label): int
    {
        phase2Assert(is_int($value) && $value > 0, "{$label} must be a positive integer.");
        return $value;
    }

    private static function independentUnicodeScalarCount(string $value): int
    {
        $count = preg_match_all('/[\s\S]/u', $value);
        if ($count === false) {
            throw new RuntimeException('Golden generator produced invalid UTF-8.');
        }

        return $count;
    }

    private static function thresholdBoundaries(): void
    {
        foreach ([
            'problem_statement' => [60, 8, 5],
            'personal_role' => [30, 5, 4],
            'measurable_outcome' => [20, 4, 3],
        ] as $field => [$graphemes, $tokens, $distinct]) {
            foreach (['en' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], 'ar' => ['ا', 'ب', 'ج', 'د', 'ه', 'و', 'ز', 'ح']] as $language => $alphabet) {
                foreach ([-1, 0, 1] as $offset) {
                    $result = evaluateEvidenceText($field, self::graphemeBoundaryText($alphabet, $graphemes + $offset, $tokens));
                    phase2AssertSame('1.0.0', $result['rule_version'], 'Evaluator result rule version changed.');
                    phase2AssertSame($graphemes + $offset, $result['content_graphemes'], "{$field}/{$language} grapheme boundary is unstable.");
                    phase2AssertSame($offset < 0, in_array('GRAPHEME_THRESHOLD_NOT_MET', $result['reason_codes'], true), "{$field}/{$language} grapheme reason is incorrect.");
                }
                foreach ([-1, 0, 1] as $offset) {
                    $result = evaluateEvidenceText($field, self::graphemeBoundaryText($alphabet, $graphemes + 12, $tokens + $offset));
                    phase2AssertSame($tokens + $offset, $result['useful_token_count'], "{$field}/{$language} useful-token boundary is unstable.");
                    phase2AssertSame($offset < 0, in_array('USEFUL_TOKEN_THRESHOLD_NOT_MET', $result['reason_codes'], true), "{$field}/{$language} useful-token reason is incorrect.");
                }
                foreach ([-1, 0, 1] as $offset) {
                    $result = evaluateEvidenceText($field, self::distinctBoundaryText($alphabet, max($tokens, $distinct + 3), $distinct + $offset));
                    phase2AssertSame($distinct + $offset, $result['distinct_token_count'], "{$field}/{$language} distinct-token boundary is unstable.");
                    phase2AssertSame($offset < 0, in_array('DISTINCT_TOKEN_THRESHOLD_NOT_MET', $result['reason_codes'], true), "{$field}/{$language} distinct-token reason is incorrect.");
                }
            }
        }

        foreach (['problem_statement' => 2000, 'personal_role' => 1500, 'measurable_outcome' => 1000] as $field => $maximum) {
            phase2AssertSame('valid', evaluateEvidenceText($field, str_repeat('a', $maximum))['storage_validity'], "{$field} maximum scalar input was rejected.");
            self::assertReason(evaluateEvidenceText($field, str_repeat('a', $maximum + 1)), 'MAXIMUM_LENGTH_EXCEEDED');
        }
    }

    private static function storageAndUnicodePolicy(): void
    {
        $nonScalar = evaluateEvidenceText('problem_statement', ['not a scalar']);
        phase2AssertSame('unavailable', $nonScalar['evidence_status'], 'Malformed storage must not appear as evidence attention.');
        self::assertReason($nonScalar, 'NON_SCALAR_INPUT');
        self::assertReason(evaluateEvidenceText('problem_statement', "\xC3\x28"), 'INVALID_UTF8');
        phase2AssertSame('unavailable', evaluateEvidenceText('problem_statement', null)['evidence_status'], 'Null optional evidence is not unavailable.');
        $empty = evaluateEvidenceText('problem_statement', " \t\r\n ");
        phase2AssertSame('unavailable', $empty['evidence_status'], 'Normalized-empty evidence is not unavailable.');
        self::assertReason($empty, 'EMPTY_NORMALIZED_VALUE');

        $composed = evaluateEvidenceText('measurable_outcome', 'Café 24 API users completed updates safely today');
        $decomposed = evaluateEvidenceText('measurable_outcome', "Cafe\u{0301} 24 API users completed updates safely today");
        phase2AssertSame([$composed['content_graphemes'], $composed['useful_token_count'], $composed['distinct_token_count']], [$decomposed['content_graphemes'], $decomposed['useful_token_count'], $decomposed['distinct_token_count']], 'NFC-equivalent inputs changed analytical facts.');
        phase2AssertSame('مرحبا', evaluateEvidenceText('personal_role', 'مــــرحبا')['analytical_text'], 'Tatweel inflated analytical text.');
        phase2AssertSame(evidenceTextTokenComparisonKey('كَتَبَ'), evidenceTextTokenComparisonKey('كتب'), 'Arabic diacritics changed token identity.');
        phase2AssertSame(3, evaluateEvidenceText('measurable_outcome', '١٢٣')['content_graphemes'], 'Arabic digits must count as content.');
        phase2AssertSame(3, evaluateEvidenceText('measurable_outcome', '123')['content_graphemes'], 'Latin digits must count as content.');
        phase2AssertSame(0, evaluateEvidenceText('measurable_outcome', '😀 !!!')['content_graphemes'], 'Emoji or punctuation counted as content.');
        phase2AssertSame('alpha beta gamma', evaluateEvidenceText('measurable_outcome', " alpha\tbeta\r\ngamma ")['analytical_text'], 'Permitted whitespace did not normalize deterministically.');

        $zwnj = evaluateEvidenceText('measurable_outcome', "می\u{200C}شود");
        phase2AssertSame("می\u{200C}شود", $zwnj['stored_value'], 'ZWNJ stored text was changed.');
        phase2AssertSame('میشود', $zwnj['analytical_text'], 'ZWNJ was not excluded from analytical evidence.');
        $zwj = evaluateEvidenceText('measurable_outcome', "a\u{200D}b");
        phase2AssertSame('ab', $zwj['analytical_text'], 'ZWJ was not excluded from analytical evidence.');
        foreach (["a\u{200B}b" => 'ZERO_WIDTH_CHARACTER', "a\u{2060}b" => 'ZERO_WIDTH_CHARACTER', "a\u{FEFF}b" => 'ZERO_WIDTH_CHARACTER', "a\x01b" => 'PROHIBITED_CONTROL_CHARACTER', "a\u{0085}b" => 'PROHIBITED_CONTROL_CHARACTER', "a\u{202E}b" => 'PROHIBITED_DIRECTIONALITY_CHARACTER', "a\u{2066}b" => 'PROHIBITED_DIRECTIONALITY_CHARACTER'] as $value => $reason) {
            self::assertReason(evaluateEvidenceText('measurable_outcome', $value), $reason);
        }
    }

    private static function placeholdersAndRepetition(): void
    {
        $placeholder = evaluateEvidenceText('problem_statement', "  TEST\t");
        phase2AssertSame('valid', $placeholder['storage_validity'], 'Placeholder storage must stay valid.');
        self::assertReason($placeholder, 'PLACEHOLDER_CONFIRMED');
        phase2Assert(!in_array('PLACEHOLDER_CONFIRMED', evaluateEvidenceText('problem_statement', 'test project documentation with useful details')['reason_codes'], true), 'Placeholder matching became a substring match.');
        self::assertReason(evaluateEvidenceText('personal_role', 'لاحقاً'), 'PLACEHOLDER_CONFIRMED');

        foreach ([[59, 100, false], [3, 5, true], [61, 100, true]] as [$dominant, $total, $expected]) {
            $result = evaluateEvidenceText('problem_statement', self::repetitionText('echo', 'other', $dominant, $total));
            phase2AssertSame($expected, in_array('REPETITION_SUSPECTED', $result['reason_codes'], true), "{$dominant}/{$total} repetition boundary is incorrect.");
        }
        self::assertReason(evaluateEvidenceText('personal_role', 'مشروع مشروع مشروع خطة تنفيذ'), 'REPETITION_SUSPECTED');
        self::assertReason(evaluateEvidenceText('personal_role', 'Build BUILD build plan delivery'), 'REPETITION_SUSPECTED');

        phase2AssertSame('complete', evaluateEvidenceText('measurable_outcome', 'رفعت سرعة التحميل 35 بالمئة وخفضت أخطاء الطلبات خلال أسبوعين')['evidence_status'], 'Concise quantified Arabic outcome must pass.');
        phase2AssertSame('complete', evaluateEvidenceText('measurable_outcome', 'Improved page speed by 35 percent and reduced request failures')['evidence_status'], 'Concise quantified English outcome must pass.');
    }

    private static function acceptedLimitation(): void
    {
        $nonsense = 'blorf quax zindle mivora plenum drask velora quinset ramblex torvi nexora';
        phase2AssertSame('complete', evaluateEvidenceText('problem_statement', $nonsense)['evidence_status'], 'Documented diverse-nonsense limitation changed unexpectedly.');
    }

    /** @param list<string> $alphabet */
    private static function graphemeBoundaryText(array $alphabet, int $contentGraphemes, int $tokenCount): string
    {
        if ($contentGraphemes < $tokenCount || $tokenCount < 1) {
            throw new LogicException('Synthetic threshold text is invalid.');
        }
        $tokens = [str_repeat($alphabet[0], $contentGraphemes - $tokenCount + 1)];
        for ($index = 1; $index < $tokenCount; ++$index) {
            $tokens[] = $alphabet[$index % count($alphabet)];
        }
        return implode(' ', $tokens);
    }

    /** @param list<string> $alphabet */
    private static function distinctBoundaryText(array $alphabet, int $tokenCount, int $distinctTokenCount): string
    {
        $tokens = [];
        for ($index = 0; $index < $tokenCount; ++$index) {
            $letter = $alphabet[$index % $distinctTokenCount];
            $tokens[] = str_repeat($letter, 12);
        }
        return implode(' ', $tokens);
    }

    private static function repetitionText(string $dominantToken, string $otherToken, int $dominantCount, int $totalCount): string
    {
        return implode(' ', [...array_fill(0, $dominantCount, $dominantToken), ...array_fill(0, $totalCount - $dominantCount, $otherToken)]);
    }

    /** @param array<string, mixed> $result */
    private static function assertReason(array $result, string $reason): void
    {
        phase2Assert(in_array($reason, $result['reason_codes'], true), "Expected {$reason}.");
    }
}
