<?php

declare(strict_types=1);

// Closed-shape validation of recommendation key and evidence-fingerprint inputs. Loaded by evidence_hub_recommendations.php.

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
        'add_skill_evidence' => ['canonical_id', 'has_project_evidence'],
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
        ['canonical_id', 'has_project_evidence'],
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
        'add_skill_evidence' => evidenceHubValidateSkillEvidenceFacts($facts),
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
    if (!in_array($value, ['add_first_project', 'complete_project_evidence', 'review_unmapped_technology', 'complete_portfolio_publication', 'add_skill_evidence'], true)) {
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
    if (!in_array($value, ['hub', 'project', 'technology', 'portfolio', 'skill'], true)) {
        evidenceHubRecommendationReject('unsupported_target_type');
    }
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

/** @param array<string, mixed> $facts */
function evidenceHubValidateSkillEvidenceFacts(array $facts): void
{
    evidenceHubValidateSkillCanonicalId($facts['canonical_id']);
    if ($facts['has_project_evidence'] !== false) {
        evidenceHubRecommendationReject('skill_project_evidence_invalid');
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
        'CHARACTER_RUN_SUSPECTED',
        'LOW_DISTINCT_TOKEN_RATIO',
        'DUPLICATED_ACROSS_FIELDS',
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
