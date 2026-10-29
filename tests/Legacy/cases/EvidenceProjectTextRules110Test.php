<?php

declare(strict_types=1);

final class EvidenceProjectTextRules110Test
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/project_evidence_repository.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_aggregation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/tests/Support/evidence-text-110-fixtures.php';
        foreach (evidenceText110ProjectFixtures() as $id => $fixture) {
            $live = evaluateEvidenceProjectTexts($fixture['values'], '1.1.0');
            $old = evaluateEvidenceProjectTexts($fixture['values'], '1.0.0');
            $flagged = [];
            foreach (evidenceTextFieldNames() as $field) {
                phase2AssertSame(evaluateEvidenceText($field,$fixture['values'][$field],'1.0.0'), $old[$field], "{$id}: cross-field checking changed 1.0.0.");
                $individual = evaluateEvidenceText($field,$fixture['values'][$field],'1.1.0');
                $unchanged = $live[$field];
                $unchanged['evidence_status'] = $individual['evidence_status'];
                $unchanged['reason_codes'] = $individual['reason_codes'];
                phase2AssertSame($individual, $unchanged, "{$id}: duplication changed counts, storage, or output shape.");
                if (in_array('DUPLICATED_ACROSS_FIELDS',$live[$field]['reason_codes'],true)) {
                    phase2AssertSame('complete', $individual['evidence_status'], "{$id}: an incomplete field acquired a duplication reason.");
                    phase2AssertSame('needs_attention', $live[$field]['evidence_status'], "{$id}: duplicate retained complete status.");
                    $flagged[] = $field;
                }
            }
            phase2AssertSame($fixture['duplicate_fields'], $flagged, "{$id}: wrong duplicate fields.");
            $project = ['project_ref' => 1,'recorded_at_epoch_seconds' => 1767225600,'technology_storage_state' => 'valid','technology_storage_reason_codes' => [],'technologies' => []] + $fixture['values'];
            $hub = evaluateEvidenceHubProjectDocumentation($project,'1.1.0');
            $editor = projectEvidenceLogicalEvaluations(projectEvidenceLogicalValuesFromStorage($fixture['values']));
            foreach (projectEvidenceLogicalToStorageFields() as $logical => $storage) {
                phase2AssertSame($live[$storage], $editor[$logical], "{$id}: editor and project evaluator disagree.");
                foreach ($hub['field_evaluations'][$storage] as $key => $value) phase2AssertSame($live[$storage][$key], $value, "{$id}: Hub and editor disagree on {$key}.");
                phase2Assert(!array_key_exists('analytical_text',$hub['field_evaluations'][$storage]) && !array_key_exists('stored_value',$hub['field_evaluations'][$storage]), "{$id}: raw evidence entered Hub facts.");
            }
        }
        $empty = evaluateEvidenceProjectTexts(array_fill_keys(evidenceTextFieldNames(),''));
        foreach ($empty as $result) phase2AssertSame('unavailable',$result['evidence_status'],'Empty equality must not create duplication.');
        $prefix = ['architecture','development','deployment','migration','observation','validation'];
        $role = implode(' ',array_merge($prefix,['maintenance']));
        foreach ([6 => true, 5 => false] as $common => $expected) {
            $problem = implode(' ',array_merge(array_slice($prefix,0,$common),['delivery','operation','documentation']));
            $result = evaluateEvidenceProjectTexts(['problem_statement'=>$problem,'personal_role'=>$role,'measurable_outcome'=>null]);
            phase2AssertSame($expected,in_array('DUPLICATED_ACROSS_FIELDS',$result['personal_role']['reason_codes'],true),'Containment must include exactly four of five shingles and exclude three of five.');
        }
    }
}
