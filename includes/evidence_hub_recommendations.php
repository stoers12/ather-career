<?php

declare(strict_types=1);

const EVIDENCE_HUB_RECOMMENDATION_RULE_VERSION = '1.0.0';
const EVIDENCE_HUB_RECOMMENDATION_SNOOZE_SECONDS = 1209600;

final class EvidenceHubRecommendationInvariantException extends RuntimeException
{
}

/** @return list<array<string, mixed>> */
function buildEvidenceHubRecommendations(
    array $scopedFacts,
    array $dispositions,
    int $calculationTimeEpochSeconds,
    string $opaqueTargetHmacMaterial,
): array {
    if ($calculationTimeEpochSeconds < 0) {
        evidenceHubRecommendationReject('calculation_time_invalid');
    }

    return filterEvidenceHubRecommendationCandidates(
        buildEvidenceHubRecommendationCandidates($scopedFacts, $opaqueTargetHmacMaterial),
        $dispositions,
        $calculationTimeEpochSeconds,
    );
}

/** @return list<array<string, mixed>> */
function buildEvidenceHubRecommendationCandidates(array $scopedFacts, string $opaqueTargetHmacMaterial): array
{
    if ($opaqueTargetHmacMaterial === '') {
        evidenceHubRecommendationReject('opaque_hmac_material_required');
    }

    $facts = evidenceHubValidateRecommendationFacts($scopedFacts);
    $candidates = [];

    if ($facts['projects'] === []) {
        $candidates[] = evidenceHubRecommendationCandidate(
            $opaqueTargetHmacMaterial,
            $facts['tenant_scope_ref'],
            'add_first_project',
            'hub',
            'hub',
            ['has_projects' => false],
            ['NO_PROJECTS'],
        );
    }

    foreach ($facts['projects'] as $project) {
        $states = $project['field_completeness_states'];
        if (evidenceHubProjectEvidenceIsComplete($states)) {
            continue;
        }
        $reasonCodes = evidenceHubProjectRecommendationReasonCodes($states, $project['reason_codes']);
        $candidates[] = evidenceHubRecommendationCandidate(
            $opaqueTargetHmacMaterial,
            $facts['tenant_scope_ref'],
            'complete_project_evidence',
            'project',
            $project['target_identity'],
            [
                'field_completeness_states' => $states,
                'reason_codes' => $reasonCodes,
            ],
            $reasonCodes,
        );
    }

    foreach ($facts['technology_mappings'] as $mapping) {
        if ($mapping['mapping_state'] !== 'unmapped') {
            continue;
        }
        $candidates[] = evidenceHubRecommendationCandidate(
            $opaqueTargetHmacMaterial,
            $facts['tenant_scope_ref'],
            'review_unmapped_technology',
            'technology',
            $mapping['target_identity'],
            [
                'mapping_state' => 'unmapped',
                'normalized_unmapped_label_digest' => $mapping['normalized_unmapped_label_digest'],
            ],
            ['TECHNOLOGY_UNMAPPED'],
        );
    }

    if ($facts['projects'] !== []
        && !$facts['portfolio_publication']['portfolio_published']
        && $facts['portfolio_publication']['publication_prerequisites_met']) {
        $candidates[] = evidenceHubRecommendationCandidate(
            $opaqueTargetHmacMaterial,
            $facts['tenant_scope_ref'],
            'complete_portfolio_publication',
            'portfolio',
            $facts['portfolio_target_identity'],
            [
                'has_projects' => true,
                'portfolio_published' => false,
                'publication_prerequisites_met' => true,
            ],
            ['PORTFOLIO_NOT_PUBLISHED'],
        );
    }

    return $candidates;
}

/** @param list<array<string, mixed>> $candidates @param list<array<string, mixed>> $dispositions
 * @return list<array<string, mixed>>
 */
function filterEvidenceHubRecommendationCandidates(array $candidates, array $dispositions, int $calculationTimeEpochSeconds): array
{
    if ($calculationTimeEpochSeconds < 0) {
        evidenceHubRecommendationReject('calculation_time_invalid');
    }
    $dispositionByKey = evidenceHubValidateRecommendationDispositions($dispositions);
    $visible = [];
    foreach ($candidates as $candidate) {
        $disposition = $dispositionByKey[$candidate['recommendation_key']] ?? null;
        if (is_array($disposition) && evidenceHubRecommendationIsSuppressed($candidate, $disposition, $calculationTimeEpochSeconds)) {
            continue;
        }
        $visible[] = $candidate;
    }

    usort($visible, 'evidenceHubCompareRecommendations');
    $visible = array_slice($visible, 0, 3);
    foreach ($visible as $index => $candidate) {
        $candidate['display_order'] = $index + 1;
        $visible[$index] = $candidate;
    }

    return $visible;
}

function evidenceHubOpaqueTargetRef(
    string $opaqueTargetHmacMaterial,
    string $tenantScopeRef,
    string $targetType,
    string $targetIdentity,
): string {
    if ($opaqueTargetHmacMaterial === '') {
        evidenceHubRecommendationReject('opaque_hmac_material_required');
    }
    evidenceHubValidateRecommendationTargetType($targetType);
    evidenceHubValidateOpaqueInputText($tenantScopeRef, 'tenant_scope_ref');
    evidenceHubValidateOpaqueInputText($targetIdentity, 'target_identity');

    $digest = hash_hmac('sha256', evidenceHubRecommendationCanonicalJson([
        'domain' => 'evidence_hub_opaque_target_ref_v1',
        'tenant_scope_ref' => $tenantScopeRef,
        'target_type' => $targetType,
        'target_identity' => $targetIdentity,
    ]), $opaqueTargetHmacMaterial);

    return chr(ord('a') + hexdec($digest[0])) . substr($digest, 1);
}

function evidenceHubRecommendationSnoozeUntil(int $calculationTimeEpochSeconds): int
{
    if ($calculationTimeEpochSeconds < 0 || $calculationTimeEpochSeconds > PHP_INT_MAX - EVIDENCE_HUB_RECOMMENDATION_SNOOZE_SECONDS) {
        evidenceHubRecommendationReject('calculation_time_invalid');
    }

    return $calculationTimeEpochSeconds + EVIDENCE_HUB_RECOMMENDATION_SNOOZE_SECONDS;
}

function evidenceHubRecommendationKey(mixed $input): string
{
    evidenceHubValidateRecommendationKeyInput($input);
    return hash('sha256', evidenceHubRecommendationCanonicalJson($input));
}

function evidenceHubEvidenceFingerprint(mixed $input): string
{
    evidenceHubValidateRecommendationFingerprintInput($input);
    return hash('sha256', evidenceHubRecommendationCanonicalJson($input));
}

function evidenceHubRecommendationCanonicalJson(mixed $value): string
{
    evidenceHubAssertRecommendationNoFloat($value);
    evidenceHubAssertRecommendationUtf8($value);
    return json_encode(
        evidenceHubSortRecommendationCanonical($value),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
}

/** @return array<string, mixed> */
function evidenceHubValidateRecommendationFacts(array $facts): array
{
    evidenceHubAssertNoRecommendationPrivateFields($facts, 'private_field_forbidden');
    evidenceHubRequireExactObjectKeys($facts, [
        'tenant_scope_ref',
        'portfolio_target_identity',
        'projects',
        'technology_mappings',
        'portfolio_publication',
    ], 'scoped_facts_shape');
    evidenceHubValidateOpaqueInputText($facts['tenant_scope_ref'], 'tenant_scope_ref');
    evidenceHubValidateOpaqueInputText($facts['portfolio_target_identity'], 'portfolio_target_identity');
    if (!is_array($facts['projects']) || !array_is_list($facts['projects'])) {
        evidenceHubRecommendationReject('projects_must_be_list');
    }
    if (!is_array($facts['technology_mappings']) || !array_is_list($facts['technology_mappings'])) {
        evidenceHubRecommendationReject('technology_mappings_must_be_list');
    }

    $projects = [];
    $projectTargets = [];
    foreach ($facts['projects'] as $project) {
        if (!is_array($project) || array_is_list($project)) {
            evidenceHubRecommendationReject('project_shape');
        }
        evidenceHubRequireExactObjectKeys($project, ['target_identity', 'field_completeness_states', 'reason_codes'], 'project_shape');
        evidenceHubValidateOpaqueInputText($project['target_identity'], 'project_target_identity');
        if (isset($projectTargets[$project['target_identity']])) {
            evidenceHubRecommendationReject('project_target_identity_duplicate');
        }
        $projectTargets[$project['target_identity']] = true;
        $projects[] = [
            'target_identity' => $project['target_identity'],
            'field_completeness_states' => evidenceHubValidateProjectCompletenessStates($project['field_completeness_states']),
            'reason_codes' => evidenceHubValidateProjectFactReasonCodes($project['reason_codes']),
        ];
    }

    $mappings = [];
    $technologyTargets = [];
    foreach ($facts['technology_mappings'] as $mapping) {
        if (!is_array($mapping) || array_is_list($mapping)) {
            evidenceHubRecommendationReject('technology_mapping_shape');
        }
        evidenceHubRequireExactObjectKeys($mapping, ['target_identity', 'mapping_state', 'normalized_unmapped_label_digest'], 'technology_mapping_shape');
        evidenceHubValidateOpaqueInputText($mapping['target_identity'], 'technology_target_identity');
        if (isset($technologyTargets[$mapping['target_identity']])) {
            evidenceHubRecommendationReject('technology_target_identity_duplicate');
        }
        $technologyTargets[$mapping['target_identity']] = true;
        if (!is_string($mapping['mapping_state']) || !in_array($mapping['mapping_state'], ['mapped', 'unmapped'], true)) {
            evidenceHubRecommendationReject('mapping_state_invalid');
        }
        if (!is_string($mapping['normalized_unmapped_label_digest']) || preg_match('/^[a-f0-9]{64}$/D', $mapping['normalized_unmapped_label_digest']) !== 1) {
            evidenceHubRecommendationReject('normalized_unmapped_label_digest_invalid');
        }
        $mappings[] = [
            'target_identity' => $mapping['target_identity'],
            'mapping_state' => $mapping['mapping_state'],
            'normalized_unmapped_label_digest' => $mapping['normalized_unmapped_label_digest'],
        ];
    }

    if (!is_array($facts['portfolio_publication']) || array_is_list($facts['portfolio_publication'])) {
        evidenceHubRecommendationReject('portfolio_publication_shape');
    }
    evidenceHubRequireExactObjectKeys($facts['portfolio_publication'], ['portfolio_published', 'publication_prerequisites_met'], 'portfolio_publication_shape');
    foreach (['portfolio_published', 'publication_prerequisites_met'] as $field) {
        if (!is_bool($facts['portfolio_publication'][$field])) {
            evidenceHubRecommendationReject('portfolio_publication_boolean_required');
        }
    }

    return [
        'tenant_scope_ref' => $facts['tenant_scope_ref'],
        'portfolio_target_identity' => $facts['portfolio_target_identity'],
        'projects' => $projects,
        'technology_mappings' => $mappings,
        'portfolio_publication' => $facts['portfolio_publication'],
    ];
}

/** @return array<string, array<string, mixed>> */
function evidenceHubValidateRecommendationDispositions(array $dispositions): array
{
    if (!array_is_list($dispositions)) {
        evidenceHubRecommendationReject('dispositions_must_be_list');
    }
    $byKey = [];
    foreach ($dispositions as $disposition) {
        if (!is_array($disposition) || array_is_list($disposition)) {
            evidenceHubRecommendationReject('disposition_shape');
        }
        evidenceHubRequireExactObjectKeys($disposition, [
            'recommendation_key',
            'rule_version',
            'evidence_fingerprint',
            'disposition',
            'snoozed_until',
        ], 'disposition_shape');
        if (!is_string($disposition['recommendation_key']) || preg_match('/^[a-f0-9]{64}$/D', $disposition['recommendation_key']) !== 1) {
            evidenceHubRecommendationReject('disposition_recommendation_key_invalid');
        }
        if (isset($byKey[$disposition['recommendation_key']])) {
            evidenceHubRecommendationReject('disposition_recommendation_key_duplicate');
        }
        if (!is_string($disposition['rule_version']) || preg_match('/^\d+\.\d+\.\d+$/D', $disposition['rule_version']) !== 1) {
            evidenceHubRecommendationReject('disposition_rule_version_invalid');
        }
        if (!is_string($disposition['evidence_fingerprint']) || preg_match('/^[a-f0-9]{64}$/D', $disposition['evidence_fingerprint']) !== 1) {
            evidenceHubRecommendationReject('disposition_evidence_fingerprint_invalid');
        }
        if (!is_string($disposition['disposition']) || !in_array($disposition['disposition'], ['snoozed', 'dismissed'], true)) {
            evidenceHubRecommendationReject('disposition_value_invalid');
        }
        if ($disposition['disposition'] === 'snoozed') {
            if (!is_int($disposition['snoozed_until']) || $disposition['snoozed_until'] < 0) {
                evidenceHubRecommendationReject('disposition_snoozed_until_invalid');
            }
        } elseif ($disposition['snoozed_until'] !== null) {
            evidenceHubRecommendationReject('disposition_dismissed_snooze_invalid');
        }
        $byKey[$disposition['recommendation_key']] = $disposition;
    }

    return $byKey;
}

/** @param array<string, string> $states @param list<string> $factReasonCodes @return list<string> */
function evidenceHubProjectRecommendationReasonCodes(array $states, array $factReasonCodes): array
{
    $allowed = [
        'FIELD_NOT_AVAILABLE',
        'GRAPHEME_THRESHOLD_NOT_MET',
        'USEFUL_TOKEN_THRESHOLD_NOT_MET',
        'DISTINCT_TOKEN_THRESHOLD_NOT_MET',
        'REPETITION_SUSPECTED',
        'PLACEHOLDER_CONFIRMED',
    ];
    $selected = [];
    foreach (['problem_statement', 'personal_role', 'measurable_outcome'] as $field) {
        if ($states[$field] === 'unavailable') {
            $selected['FIELD_NOT_AVAILABLE'] = true;
            continue;
        }
        if ($states[$field] === 'needs_attention') {
            foreach ($factReasonCodes as $reasonCode) {
                if (in_array($reasonCode, $allowed, true)) {
                    $selected[$reasonCode] = true;
                }
            }
        }
    }
    $reasonCodes = [];
    foreach ($allowed as $reasonCode) {
        if (isset($selected[$reasonCode])) {
            $reasonCodes[] = $reasonCode;
        }
    }

    return $reasonCodes;
}

/** @param array<string, string> $states */
function evidenceHubProjectEvidenceIsComplete(array $states): bool
{
    return $states['problem_statement'] === 'complete'
        && $states['personal_role'] === 'complete'
        && $states['measurable_outcome'] === 'complete';
}

/** @param array<string, mixed> $predicateFacts @param list<string> $reasonCodes @return array<string, mixed> */
function evidenceHubRecommendationCandidate(
    string $opaqueTargetHmacMaterial,
    string $tenantScopeRef,
    string $ruleId,
    string $targetType,
    string $targetIdentity,
    array $predicateFacts,
    array $reasonCodes,
): array {
    $targetRef = evidenceHubOpaqueTargetRef($opaqueTargetHmacMaterial, $tenantScopeRef, $targetType, $targetIdentity);
    $ruleVersion = EVIDENCE_HUB_RECOMMENDATION_RULE_VERSION;
    $recommendationKey = evidenceHubRecommendationKey([
        'rule_id' => $ruleId,
        'target_type' => $targetType,
        'target_ref' => $targetRef,
    ]);
    $fingerprint = evidenceHubEvidenceFingerprint([
        'rule_id' => $ruleId,
        'rule_version' => $ruleVersion,
        'target_type' => $targetType,
        'target_ref' => $targetRef,
        'predicate_facts' => $predicateFacts,
    ]);

    return [
        'rule_id' => $ruleId,
        'rule_version' => $ruleVersion,
        'recommendation_key' => $recommendationKey,
        'evidence_fingerprint' => $fingerprint,
        'lifecycle_state' => 'active',
        'priority_rank' => evidenceHubRecommendationPriority($ruleId),
        'display_order' => 0,
        'reason_codes' => $reasonCodes,
        'target' => [
            'route' => 'owner_evidence_hub',
            'action_id' => $ruleId,
            'opaque_target_ref' => $targetRef,
        ],
        'snoozed_until' => null,
    ];
}

/** @param array<string, mixed> $candidate @param array<string, mixed> $disposition */
function evidenceHubRecommendationIsSuppressed(array $candidate, array $disposition, int $calculationTimeEpochSeconds): bool
{
    if ($candidate['rule_version'] !== $disposition['rule_version']
        || $candidate['evidence_fingerprint'] !== $disposition['evidence_fingerprint']) {
        return false;
    }
    if ($disposition['disposition'] === 'dismissed') {
        return true;
    }

    return $calculationTimeEpochSeconds < $disposition['snoozed_until'];
}

/** @param array<string, mixed> $left @param array<string, mixed> $right */
function evidenceHubCompareRecommendations(array $left, array $right): int
{
    $priority = $left['priority_rank'] <=> $right['priority_rank'];
    if ($priority !== 0) {
        return $priority;
    }
    $rule = strcmp($left['rule_id'], $right['rule_id']);
    if ($rule !== 0) {
        return $rule;
    }
    $target = strcmp($left['target']['opaque_target_ref'], $right['target']['opaque_target_ref']);
    if ($target !== 0) {
        return $target;
    }

    return strcmp($left['recommendation_key'], $right['recommendation_key']);
}

function evidenceHubRecommendationPriority(string $ruleId): int
{
    return match ($ruleId) {
        'add_first_project' => 1,
        'complete_project_evidence' => 2,
        'review_unmapped_technology' => 3,
        'complete_portfolio_publication' => 4,
        default => throw new EvidenceHubRecommendationInvariantException('unsupported_rule_id'),
    };
}

function evidenceHubValidateRecommendationKeyInput(mixed $input): void
{
    evidenceHubAssertRecommendationNoFloat($input);
    evidenceHubAssertRecommendationUtf8($input);
    evidenceHubAssertNoRecommendationPrivateFields($input, 'unauthorized_property');
    if (!is_array($input) || array_is_list($input)) {
        evidenceHubRecommendationReject('closed_object_required');
    }
    $required = ['rule_id', 'target_ref', 'target_type'];
    foreach ($required as $field) {
        if (!array_key_exists($field, $input)) {
            evidenceHubRecommendationReject('missing_' . $field);
        }
    }
    foreach (array_keys($input) as $field) {
        if (in_array($field, $required, true)) {
            continue;
        }
        evidenceHubRecommendationReject(in_array($field, ['predicate_facts', 'rule_version'], true) ? 'unauthorized_property' : 'unknown_property');
    }
    evidenceHubValidateRecommendationRuleId($input['rule_id']);
    evidenceHubValidateOpaqueTargetRef($input['target_ref']);
    evidenceHubValidateRecommendationTargetType($input['target_type']);
}

function evidenceHubValidateRecommendationFingerprintInput(mixed $input): void
{
    evidenceHubAssertRecommendationNoFloat($input);
    evidenceHubAssertRecommendationUtf8($input);
    evidenceHubAssertNoRecommendationPrivateFields($input, 'private_field_forbidden');
    if (!is_array($input) || array_is_list($input)) {
        evidenceHubRecommendationReject('closed_object_required');
    }
    $required = ['predicate_facts', 'rule_id', 'rule_version', 'target_ref', 'target_type'];
    foreach ($required as $field) {
        if (!array_key_exists($field, $input)) {
            evidenceHubRecommendationReject('missing_' . $field);
        }
    }
    foreach (array_keys($input) as $field) {
        if (!in_array($field, $required, true)) {
            evidenceHubRecommendationReject('unknown_top_level_property');
        }
    }
    evidenceHubValidateRecommendationRuleId($input['rule_id']);
    evidenceHubValidateRuleVersion($input['rule_version']);
    evidenceHubValidateOpaqueTargetRef($input['target_ref']);
    evidenceHubValidateRecommendationTargetType($input['target_type']);

    $facts = $input['predicate_facts'];
    if (!is_array($facts) || (array_is_list($facts) && $facts !== [])) {
        evidenceHubRecommendationReject('predicate_facts_must_be_object');
    }
    if ($facts === []) {
        evidenceHubRecommendationReject('missing_required_predicate_key');
    }
    $allowed = match ($input['rule_id']) {
        'add_first_project' => ['has_projects'],
        'complete_project_evidence' => ['field_completeness_states', 'reason_codes'],
        'review_unmapped_technology' => ['mapping_state', 'normalized_unmapped_label_digest'],
        'complete_portfolio_publication' => ['has_projects', 'portfolio_published', 'publication_prerequisites_met'],
    };
    $factKeys = array_keys($facts);
    if (in_array('target_ref', $factKeys, true)) {
        evidenceHubRecommendationReject('target_ref_forbidden_in_predicate_facts');
    }
    foreach ([
        ['has_projects'],
        ['field_completeness_states', 'reason_codes'],
        ['mapping_state', 'normalized_unmapped_label_digest'],
        ['has_projects', 'portfolio_published', 'publication_prerequisites_met'],
    ] as $otherAllowed) {
        if ($otherAllowed !== $allowed && evidenceHubSameRecommendationKeySet($factKeys, $otherAllowed)) {
            evidenceHubRecommendationReject('predicate_facts_belong_to_different_rule');
        }
    }
    if (array_diff($factKeys, $allowed) !== []) {
        evidenceHubRecommendationReject('unknown_predicate_key');
    }
    if (array_diff($allowed, $factKeys) !== []) {
        evidenceHubRecommendationReject('missing_required_predicate_key');
    }

    match ($input['rule_id']) {
        'add_first_project' => evidenceHubValidateBooleanFact($facts['has_projects']),
        'complete_project_evidence' => evidenceHubValidateCompleteProjectFacts($facts),
        'review_unmapped_technology' => evidenceHubValidateUnmappedTechnologyFacts($facts),
        'complete_portfolio_publication' => evidenceHubValidatePublicationFacts($facts),
    };
}

function evidenceHubValidateRecommendationRuleId(mixed $value): void
{
    if (is_array($value)) {
        evidenceHubRecommendationReject('nested_object_forbidden');
    }
    if (!is_string($value)) {
        evidenceHubRecommendationReject('rule_id_must_be_string');
    }
    if ($value === '') {
        evidenceHubRecommendationReject('empty_rule_id');
    }
    if (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/D', $value) !== 1) {
        evidenceHubRecommendationReject('malformed_rule_id');
    }
    if (!in_array($value, ['add_first_project', 'complete_project_evidence', 'review_unmapped_technology', 'complete_portfolio_publication'], true)) {
        evidenceHubRecommendationReject('unsupported_rule_id');
    }
}

function evidenceHubValidateRuleVersion(mixed $value): void
{
    if (is_array($value)) {
        evidenceHubRecommendationReject('nested_object_forbidden');
    }
    if (!is_string($value)) {
        evidenceHubRecommendationReject('rule_version_must_be_string');
    }
    if (preg_match('/^\d+\.\d+\.\d+$/D', $value) !== 1) {
        evidenceHubRecommendationReject('malformed_rule_version');
    }
}

function evidenceHubValidateOpaqueTargetRef(mixed $value): void
{
    if (is_array($value)) {
        evidenceHubRecommendationReject('nested_object_forbidden');
    }
    if (!is_string($value)) {
        evidenceHubRecommendationReject('target_ref_must_be_string');
    }
    if ($value === '') {
        evidenceHubRecommendationReject('empty_target_ref');
    }
    if (preg_match('/^(?:hub_home|opaque-[a-z0-9]+(?:-[a-z0-9]+)*|[a-z][a-z0-9_]{7,63})$/D', $value) !== 1) {
        evidenceHubRecommendationReject('malformed_target_ref');
    }
}

function evidenceHubValidateRecommendationTargetType(mixed $value): void
{
    if (is_array($value)) {
        evidenceHubRecommendationReject('nested_object_forbidden');
    }
    if (!is_string($value)) {
        evidenceHubRecommendationReject('target_type_must_be_string');
    }
    if ($value === '') {
        evidenceHubRecommendationReject('empty_target_type');
    }
    if (!in_array($value, ['hub', 'project', 'technology', 'portfolio'], true)) {
        evidenceHubRecommendationReject('unsupported_target_type');
    }
}

/** @return array<string, string> */
function evidenceHubValidateProjectCompletenessStates(mixed $states): array
{
    if (!is_array($states) || array_is_list($states) || !evidenceHubSameRecommendationKeySet(array_keys($states), ['problem_statement', 'personal_role', 'measurable_outcome'])) {
        evidenceHubRecommendationReject('field_completeness_states_shape');
    }
    foreach (['problem_statement', 'personal_role', 'measurable_outcome'] as $field) {
        if (!is_string($states[$field]) || !in_array($states[$field], ['unavailable', 'needs_attention', 'complete'], true)) {
            evidenceHubRecommendationReject('field_completeness_state_invalid');
        }
    }

    return $states;
}

/** @return list<string> */
function evidenceHubValidateProjectFactReasonCodes(mixed $reasonCodes): array
{
    if (!is_array($reasonCodes) || !array_is_list($reasonCodes)) {
        evidenceHubRecommendationReject('reason_codes_must_be_list');
    }
    $allowed = [
        'FIELD_NOT_AVAILABLE',
        'NON_SCALAR_INPUT',
        'INVALID_UTF8',
        'EMPTY_NORMALIZED_VALUE',
        'PROHIBITED_CONTROL_CHARACTER',
        'PROHIBITED_DIRECTIONALITY_CHARACTER',
        'ZERO_WIDTH_CHARACTER',
        'MAXIMUM_LENGTH_EXCEEDED',
        'GRAPHEME_THRESHOLD_NOT_MET',
        'USEFUL_TOKEN_THRESHOLD_NOT_MET',
        'DISTINCT_TOKEN_THRESHOLD_NOT_MET',
        'REPETITION_SUSPECTED',
        'PLACEHOLDER_CONFIRMED',
        'TEXT_COMPLETE',
    ];
    foreach ($reasonCodes as $reasonCode) {
        if (!is_string($reasonCode) || !in_array($reasonCode, $allowed, true)) {
            evidenceHubRecommendationReject('project_reason_code_invalid');
        }
    }
    if (count($reasonCodes) !== count(array_unique($reasonCodes, SORT_STRING))) {
        evidenceHubRecommendationReject('reason_codes_must_be_unique');
    }

    return $reasonCodes;
}

/** @param array<string, mixed> $facts */
function evidenceHubValidateCompleteProjectFacts(array $facts): void
{
    evidenceHubValidateProjectCompletenessStates($facts['field_completeness_states']);
    evidenceHubValidateRecommendationReasonCodes($facts['reason_codes']);
}

/** @param array<string, mixed> $facts */
function evidenceHubValidateUnmappedTechnologyFacts(array $facts): void
{
    if ($facts['mapping_state'] !== 'unmapped') {
        evidenceHubRecommendationReject('mapping_state_must_be_unmapped');
    }
    if (!is_string($facts['normalized_unmapped_label_digest']) || preg_match('/^[a-f0-9]{64}$/D', $facts['normalized_unmapped_label_digest']) !== 1) {
        evidenceHubRecommendationReject('normalized_unmapped_label_digest_invalid');
    }
}

/** @param array<string, mixed> $facts */
function evidenceHubValidatePublicationFacts(array $facts): void
{
    foreach (['has_projects', 'portfolio_published', 'publication_prerequisites_met'] as $field) {
        evidenceHubValidateBooleanFact($facts[$field]);
    }
}

function evidenceHubValidateBooleanFact(mixed $value): void
{
    if (!is_bool($value)) {
        evidenceHubRecommendationReject('boolean_required');
    }
}

function evidenceHubValidateRecommendationReasonCodes(mixed $value): void
{
    if (!is_array($value) || !array_is_list($value)) {
        evidenceHubRecommendationReject('reason_codes_must_be_list');
    }
    $allowed = [
        'FIELD_NOT_AVAILABLE',
        'GRAPHEME_THRESHOLD_NOT_MET',
        'USEFUL_TOKEN_THRESHOLD_NOT_MET',
        'DISTINCT_TOKEN_THRESHOLD_NOT_MET',
        'REPETITION_SUSPECTED',
        'PLACEHOLDER_CONFIRMED',
    ];
    foreach ($value as $reasonCode) {
        if (!is_string($reasonCode) || !in_array($reasonCode, $allowed, true)) {
            evidenceHubRecommendationReject('reason_code_not_controlled');
        }
    }
    if (count($value) !== count(array_unique($value, SORT_STRING))) {
        evidenceHubRecommendationReject('reason_codes_must_be_unique');
    }
}

/** @param list<int|string> $left @param list<int|string> $right */
function evidenceHubSameRecommendationKeySet(array $left, array $right): bool
{
    sort($left, SORT_STRING);
    sort($right, SORT_STRING);
    return $left === $right;
}

function evidenceHubValidateOpaqueInputText(mixed $value, string $field): void
{
    if (!is_string($value) || $value === '') {
        evidenceHubRecommendationReject($field . '_invalid');
    }
    evidenceHubAssertRecommendationUtf8($value);
}

/** @param array<string, mixed> $value @param list<string> $required */
function evidenceHubRequireExactObjectKeys(array $value, array $required, string $category): void
{
    if (array_is_list($value) || !evidenceHubSameRecommendationKeySet(array_keys($value), $required)) {
        evidenceHubRecommendationReject($category);
    }
}

function evidenceHubAssertRecommendationNoFloat(mixed $value): void
{
    if (is_float($value)) {
        evidenceHubRecommendationReject('floating_point_forbidden');
    }
    if (is_array($value)) {
        foreach ($value as $item) {
            evidenceHubAssertRecommendationNoFloat($item);
        }
    }
}

function evidenceHubAssertRecommendationUtf8(mixed $value): void
{
    if (is_string($value) && @preg_match('//u', $value) !== 1) {
        evidenceHubRecommendationReject('invalid_utf8');
    }
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            if (is_string($key) && @preg_match('//u', $key) !== 1) {
                evidenceHubRecommendationReject('invalid_utf8');
            }
            evidenceHubAssertRecommendationUtf8($item);
        }
    }
}

function evidenceHubAssertNoRecommendationPrivateFields(mixed $value, string $category): void
{
    $prohibited = [
        'raw_evidence_text',
        'raw_technology_label',
        'normalized_technology_label',
        'email',
        'auth0_subject',
        'cookie',
        'token',
        'filesystem_path',
        'private_media_path',
        'showcase_identity',
        'database_id',
        'owner_id',
        'user_id',
        'portfolio_id',
        'project_id',
    ];
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $key => $item) {
        if (is_string($key) && in_array($key, $prohibited, true)) {
            evidenceHubRecommendationReject($category);
        }
        evidenceHubAssertNoRecommendationPrivateFields($item, $category);
    }
}

function evidenceHubSortRecommendationCanonical(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('evidenceHubSortRecommendationCanonical', $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = evidenceHubSortRecommendationCanonical($item);
    }

    return $value;
}

function evidenceHubRecommendationReject(string $category): never
{
    throw new EvidenceHubRecommendationInvariantException($category);
}
