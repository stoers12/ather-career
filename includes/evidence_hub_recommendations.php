<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_recommendation_facts.php';
require_once __DIR__ . '/evidence_hub_recommendation_key_inputs.php';
require_once __DIR__ . '/evidence_hub_recommendation_canonical.php';

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

    foreach ($facts['skill_claims'] ?? [] as $claim) {
        $candidates[] = evidenceHubRecommendationCandidate(
            $opaqueTargetHmacMaterial,
            $facts['tenant_scope_ref'],
            'add_skill_evidence',
            'skill',
            $claim['target_identity'],
            ['canonical_id' => $claim['canonical_id'], 'has_project_evidence' => false],
            ['SKILL_PROJECT_EVIDENCE_MISSING'],
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

/** @param array<string, string> $states @param list<string> $factReasonCodes @return list<string> */
function evidenceHubProjectRecommendationReasonCodes(array $states, array $factReasonCodes): array
{
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
        'add_skill_evidence' => 5,
        default => throw new EvidenceHubRecommendationInvariantException('unsupported_rule_id'),
    };
}
