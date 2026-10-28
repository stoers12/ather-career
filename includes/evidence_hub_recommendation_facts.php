<?php

declare(strict_types=1);

// Validation of the scoped facts and stored dispositions the recommendation core accepts. Loaded by evidence_hub_recommendations.php.

/** @return array<string, mixed> */
function evidenceHubValidateRecommendationFacts(array $facts): array
{
    evidenceHubAssertNoRecommendationPrivateFields($facts, 'private_field_forbidden');
    $required = ['tenant_scope_ref', 'portfolio_target_identity', 'projects', 'technology_mappings', 'portfolio_publication'];
    if (array_key_exists('skill_claims', $facts)) {
        $required[] = 'skill_claims';
    }
    evidenceHubRequireExactObjectKeys($facts, $required, 'scoped_facts_shape');
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

    $skillClaims = $facts['skill_claims'] ?? [];
    if (!is_array($skillClaims) || !array_is_list($skillClaims)) {
        evidenceHubRecommendationReject('skill_claims_must_be_list');
    }
    $skillTargets = [];
    $skillIds = [];
    $lastTarget = null;
    foreach ($skillClaims as $claim) {
        if (!is_array($claim) || array_is_list($claim)) {
            evidenceHubRecommendationReject('skill_claim_shape');
        }
        evidenceHubRequireExactObjectKeys($claim, ['target_identity', 'canonical_id'], 'skill_claim_shape');
        evidenceHubValidateOpaqueInputText($claim['target_identity'], 'skill_target_identity');
        evidenceHubValidateSkillCanonicalId($claim['canonical_id']);
        if (isset($skillTargets[$claim['target_identity']]) || isset($skillIds[$claim['canonical_id']])
            || ($lastTarget !== null && strcmp($lastTarget, $claim['target_identity']) >= 0)) {
            evidenceHubRecommendationReject('skill_claims_not_unique_or_sorted');
        }
        $skillTargets[$claim['target_identity']] = true;
        $skillIds[$claim['canonical_id']] = true;
        $lastTarget = $claim['target_identity'];
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
        'skill_claims' => $skillClaims,
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
        'CHARACTER_RUN_SUSPECTED',
        'LOW_DISTINCT_TOKEN_RATIO',
        'DUPLICATED_ACROSS_FIELDS',
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

function evidenceHubValidateSkillCanonicalId(mixed $canonicalId): void
{
    if (!is_string($canonicalId) || preg_match('/^tech\.[a-z0-9][a-z0-9._-]*$/D', $canonicalId) !== 1) {
        evidenceHubRecommendationReject('skill_canonical_id_invalid');
    }
}
