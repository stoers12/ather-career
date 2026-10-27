<?php

declare(strict_types=1);

final class EvidenceHubSkillEvidenceTest
{
    private const HMAC = 'skill-evidence-synthetic-hmac';

    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_recommendations.php';
        self::cases();
        self::closedFacts();
        self::existingRuleStability();
        self::scopedSkills();
    }

    private static function cases(): void
    {
        $fixtures = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-hub-skill-evidence-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
        $taxonomy = loadEvidenceHubTechnologyTaxonomy('v5');
        $context = self::context(1, 10);
        foreach ($fixtures['cases'] as $case) {
            $projects = $case['storage'] === 'none' ? [] : [self::project($case['project_labels'], $case['storage'])];
            $mappings = [];
            foreach ($case['owner_mappings'] as $label => $id) {
                $mappings[evidenceHubTechnologyLabelDigest((string) normalizeEvidenceHubTechnologyLabel($label))] = $id;
            }
            $adapted = buildEvidenceHubOwnerRecommendationFacts($context, $projects, $taxonomy, ['portfolio_published' => true, 'publication_prerequisites_met' => true], $mappings, $case['skills']);
            $candidates = buildEvidenceHubRecommendationCandidates($adapted['facts'], self::HMAC);
            $skillCandidates = array_values(array_filter($candidates, static fn (array $candidate): bool => $candidate['rule_id'] === 'add_skill_evidence'));
            phase2AssertSame($case['expected_skill_candidates'], count($skillCandidates), "{$case['id']}: incorrect skill candidate count.");
            if ($skillCandidates !== []) {
                $candidate = $skillCandidates[0];
                phase2AssertSame(['1.0.0', 5, ['SKILL_PROJECT_EVIDENCE_MISSING'], 'add_skill_evidence'], [$candidate['rule_version'], $candidate['priority_rank'], $candidate['reason_codes'], $candidate['target']['action_id']], "{$case['id']}: skill rule metadata is wrong.");
                $identity = evidenceHubOwnerInternalTargetIdentity('skill', 'tech.react');
                phase2AssertSame([['target_identity' => $identity, 'canonical_id' => 'tech.react']], $adapted['facts']['skill_claims'], "{$case['id']}: the claim did not deduplicate by canonical technology.");
                phase2AssertSame(['target_type' => 'skill', 'project_refs' => [], 'canonical_id' => 'tech.react'], $adapted['target_associations'][$identity], "{$case['id']}: the internal association is wrong.");
                phase2AssertSame(evidenceHubOpaqueTargetRef(self::HMAC, $adapted['facts']['tenant_scope_ref'], 'skill', $identity), $candidate['target']['opaque_target_ref'], "{$case['id']}: the target type or scope is wrong.");
                $associations = evidenceHubOwnerRecommendationResourceAssociations($candidates, $adapted['target_associations'], $adapted['facts']['tenant_scope_ref'], self::HMAC);
                phase2AssertSame(['project_refs' => [], 'canonical_id' => 'tech.react'], $associations[$candidate['recommendation_key']], 'The canonical association did not survive private candidate resolution.');
            } else {
                phase2Assert(!array_key_exists('skill_claims', $adapted['facts']), "{$case['id']}: empty skill claims changed the historical facts shape.");
            }
            $serialized = json_encode([$adapted, $candidates], JSON_THROW_ON_ERROR);
            foreach ($case['skills'] as $skill) {
                if (trim($skill) !== '') {
                    phase2Assert(!str_contains($serialized, $skill), "{$case['id']}: raw skill text entered recommendation facts or output.");
                }
            }
        }
    }

    private static function closedFacts(): void
    {
        $base = buildEvidenceHubOwnerRecommendationFacts(self::context(1, 10), [], loadEvidenceHubTechnologyTaxonomy('v5'), ['portfolio_published' => true, 'publication_prerequisites_met' => true])['facts'];
        $invalidClaims = [
            'not a list' => ['bad' => []],
            'unknown key' => [['target_identity' => 'skill_target_1', 'canonical_id' => 'tech.react', 'raw_name' => 'React']],
            'unknown id syntax' => [['target_identity' => 'skill_target_1', 'canonical_id' => 'React']],
            'duplicate identity' => [['target_identity' => 'skill_target_1', 'canonical_id' => 'tech.react'], ['target_identity' => 'skill_target_1', 'canonical_id' => 'tech.vue']],
            'duplicate canonical id' => [['target_identity' => 'skill_target_1', 'canonical_id' => 'tech.react'], ['target_identity' => 'skill_target_2', 'canonical_id' => 'tech.react']],
            'out of order' => [['target_identity' => 'skill_target_2', 'canonical_id' => 'tech.react'], ['target_identity' => 'skill_target_1', 'canonical_id' => 'tech.vue']],
        ];
        foreach ($invalidClaims as $name => $claims) {
            $bad = $base;
            $bad['skill_claims'] = $claims;
            self::reject(static fn (): array => buildEvidenceHubRecommendationCandidates($bad, self::HMAC), "Invalid {$name} was accepted.");
        }
        $predicate = ['rule_id' => 'add_skill_evidence', 'rule_version' => '1.0.0', 'target_type' => 'skill', 'target_ref' => 'opaque-target', 'predicate_facts' => ['canonical_id' => 'tech.react', 'has_project_evidence' => false]];
        phase2AssertSame(64, strlen(evidenceHubEvidenceFingerprint($predicate)), 'Valid canonical skill predicate was rejected.');
        foreach ([['canonical_id' => 'React', 'has_project_evidence' => false], ['canonical_id' => 'tech.react', 'has_project_evidence' => true], ['has_projects' => false]] as $badFacts) {
            $bad = $predicate;
            $bad['predicate_facts'] = $badFacts;
            self::reject(static fn (): string => evidenceHubEvidenceFingerprint($bad), 'A malformed skill predicate was accepted.');
        }
        $foreignPredicate = $predicate;
        $foreignPredicate['rule_id'] = 'add_first_project';
        self::reject(static fn (): string => evidenceHubEvidenceFingerprint($foreignPredicate), 'Skill predicate keys were accepted by a different rule.');
    }

    private static function existingRuleStability(): void
    {
        phase2AssertSame('1.0.0', EVIDENCE_HUB_RECOMMENDATION_RULE_VERSION, 'The shared rule version must remain frozen.');
        $context = self::context(1, 10);
        $taxonomy = loadEvidenceHubTechnologyTaxonomy('v5');
        foreach ([[], [self::project(['Unknown Tool'], 'valid', false)]] as $projects) {
            $publication = ['portfolio_published' => false, 'publication_prerequisites_met' => true];
            $old = buildEvidenceHubOwnerRecommendationFacts($context, $projects, $taxonomy, $publication);
            $new = buildEvidenceHubOwnerRecommendationFacts($context, $projects, $taxonomy, $publication, [], ['React']);
            $oldCandidates = buildEvidenceHubRecommendationCandidates($old['facts'], self::HMAC);
            $newCandidates = buildEvidenceHubRecommendationCandidates($new['facts'], self::HMAC);
            $oldIds = array_column($oldCandidates, 'rule_id');
            foreach ($oldCandidates as $candidate) {
                $same = array_values(array_filter($newCandidates, static fn (array $item): bool => $item['recommendation_key'] === $candidate['recommendation_key']));
                phase2AssertSame([$candidate], $same, 'A pre-existing candidate changed after adding skill facts.');
                foreach (['dismissed', 'snoozed'] as $disposition) {
                    $record = ['recommendation_key' => $candidate['recommendation_key'], 'rule_version' => $candidate['rule_version'], 'evidence_fingerprint' => $candidate['evidence_fingerprint'], 'disposition' => $disposition, 'snoozed_until' => $disposition === 'snoozed' ? 2000 : null];
                    phase2AssertSame([], filterEvidenceHubRecommendationCandidates($same, [$record], 1000), "Existing {$candidate['rule_id']} {$disposition} stopped suppressing.");
                }
            }
            if ($projects === []) {
                phase2Assert(in_array('add_first_project', $oldIds, true), 'First-project stability fixture is incomplete.');
            } else {
                foreach (['complete_project_evidence', 'review_unmapped_technology', 'complete_portfolio_publication'] as $id) {
                    phase2Assert(in_array($id, $oldIds, true), "{$id} stability fixture is incomplete.");
                }
                $visible = filterEvidenceHubRecommendationCandidates($newCandidates, [], 1000);
                phase2Assert(!in_array('add_skill_evidence', array_column($visible, 'rule_id'), true), 'A priority-five skill displaced a higher-priority candidate.');
            }
        }
    }

    private static function scopedSkills(): void
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec("CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL); CREATE TABLE skills (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, skill_name TEXT NOT NULL, created_at TEXT NOT NULL); INSERT INTO portfolios VALUES (10,1),(20,2); INSERT INTO skills VALUES (1,10,'React','2026-01-01'),(2,20,'Vue','2026-01-01')");
        phase2AssertSame(['React'], array_column(listAuthorizedSkills($database, self::context(1, 10)), 'skill_name'), 'Owner A skill scope is wrong.');
        phase2AssertSame(['Vue'], array_column(listAuthorizedSkills($database, self::context(2, 20)), 'skill_name'), 'Owner B skill scope is wrong.');
        phase2AssertSame([], listAuthorizedSkills($database, self::context(1, 20)), 'A foreign context read another Owner’s skills.');
    }

    /** @param list<string> $labels @return array<string, mixed> */
    private static function project(array $labels, string $storage, bool $complete = true): array
    {
        $evaluations = [];
        foreach (evidenceTextFieldNames() as $field) {
            $evaluations[$field] = ['evidence_field' => $field, 'evidence_status' => $complete ? 'complete' : 'unavailable', 'reason_codes' => [$complete ? 'TEXT_COMPLETE' : 'FIELD_NOT_AVAILABLE']];
        }
        return ['project_ref' => 1, 'field_evaluations' => $evaluations, 'expected_evidence_fields' => 3, 'complete_evidence_fields' => $complete ? 3 : 0, 'project_has_complete_evidence' => $complete, 'technology_storage_state' => $storage, 'technology_storage_reason_codes' => $storage === 'valid' ? [] : ['TECHNOLOGY_STORAGE_INVALID'], 'technologies' => $labels];
    }

    private static function context(int $userId, int $portfolioId): AuthorizedPortfolioContext
    {
        return AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($userId), $portfolioId);
    }

    private static function reject(callable $probe, string $message): void
    {
        try {
            $probe();
            throw new RuntimeException($message);
        } catch (EvidenceHubRecommendationInvariantException) {
            // Facts and fingerprint inputs fail closed.
        }
    }
}
