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
        self::rendering();
    }

    private static function cases(): void
    {
        $fixtures = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/Support/fixtures/evidence-hub-skill-evidence-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
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
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL); CREATE TABLE recommendation_dispositions (portfolio_id INTEGER NOT NULL, recommendation_key TEXT NOT NULL, rule_version TEXT NOT NULL, evidence_fingerprint TEXT NOT NULL, disposition TEXT NOT NULL, snoozed_until TEXT NULL, PRIMARY KEY (portfolio_id,recommendation_key)); INSERT INTO portfolios VALUES (10,1)');
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
                    storeAuthorizedEvidenceHubRecommendationDisposition($database, $context, $candidate, $disposition, 1000);
                    $stored = listAuthorizedEvidenceHubRecommendationDispositions($database, $context);
                    phase2AssertSame([], filterEvidenceHubRecommendationCandidates($same, $stored, 1000), "Stored {$candidate['rule_id']} {$disposition} stopped suppressing.");
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
        $facts = buildEvidenceHubOwnerRecommendationFacts($context, [], $taxonomy, ['portfolio_published' => true, 'publication_prerequisites_met' => true], [], ['React'])['facts'];
        $skill = array_values(array_filter(buildEvidenceHubRecommendationCandidates($facts, self::HMAC), static fn (array $candidate): bool => $candidate['rule_id'] === 'add_skill_evidence'));
        foreach (['snoozed', 'dismissed'] as $disposition) {
            storeAuthorizedEvidenceHubRecommendationDisposition($database, $context, $skill[0], $disposition, 1000);
            $reevaluated = array_values(array_filter(buildEvidenceHubRecommendationCandidates($facts, self::HMAC), static fn (array $candidate): bool => $candidate['rule_id'] === 'add_skill_evidence'));
            phase2AssertSame($skill[0], $reevaluated[0], 'Reevaluating a skill changed its key or fingerprint.');
            phase2AssertSame([], filterEvidenceHubRecommendationCandidates($reevaluated, listAuthorizedEvidenceHubRecommendationDispositions($database, $context), 1000), "Stored skill {$disposition} stopped suppressing.");
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

    private static function rendering(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_presentation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/csrf.php';
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec("CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT NULL, is_published INTEGER NOT NULL DEFAULT 0); CREATE TABLE personal_info (portfolio_id INTEGER PRIMARY KEY, full_name TEXT NULL); CREATE TABLE skills (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, skill_name TEXT NOT NULL, created_at TEXT NOT NULL); CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, title TEXT NOT NULL, is_published INTEGER NOT NULL DEFAULT 0, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NULL); CREATE TABLE recommendation_dispositions (portfolio_id INTEGER NOT NULL, recommendation_key TEXT NOT NULL, rule_version TEXT NOT NULL, evidence_fingerprint TEXT NOT NULL, disposition TEXT NOT NULL, snoozed_until TEXT NULL, PRIMARY KEY (portfolio_id,recommendation_key)); INSERT INTO portfolios VALUES (10,1,NULL,0),(20,2,NULL,0); INSERT INTO personal_info VALUES (10,'Owner A'),(20,'Owner B'); INSERT INTO skills VALUES (1,10,'React.js','2026-01-01'),(2,20,'Vue','2026-01-01')");
        $database->exec(phase2EvidenceHubTechnologyReviewSqliteSchema());
        $context = self::context(1, 10);
        $state = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, 1767225600, self::HMAC);
        $model = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, self::HMAC);
        $index = array_search('add_skill_evidence', array_column($state['contract']['recommendations'], 'rule_id'), true);
        phase2Assert(is_int($index), 'The skill recommendation is not visible in its synthetic Owner page.');
        phase2AssertSame(['project_title' => null, 'edit_url' => null, 'skill_display_name' => 'React'], $model['recommendations'][$index], 'The page must resolve the canonical taxonomy display name.');
        $tokens = array_fill(0, count($state['contract']['recommendations']), ['snooze' => 'synthetic-snooze-token', 'dismiss' => 'synthetic-dismiss-token']);
        foreach (['en', 'ar'] as $locale) {
            ob_start();
            renderEvidenceHubOwnerPage($state['contract'], $model, $tokens, '', ['kind' => 'none'], $locale);
            $html = (string) ob_get_clean();
            $title = $locale === 'ar' ? 'أثبت مهارة بمشروع' : 'Show evidence for a skill';
            $review = $locale === 'ar' ? 'راجع المهارات' : 'Review skills';
            phase2Assert(str_contains($html, $title) && str_contains($html, '<bdi dir="auto">React</bdi>'), "{$locale}: canonical skill context is missing or not isolated.");
            phase2Assert(str_contains($html, 'href="/owner_projects.php?add=1"') && str_contains($html, 'href="/owner_profile.php#skills">' . $review . '</a>'), "{$locale}: skill recommendation links are missing.");
            phase2AssertSame(1, substr_count($html, 'href="/owner_profile.php#skills">'), "{$locale}: the secondary skill link escaped its card.");
            phase2Assert(!str_contains($html, 'React.js'), "{$locale}: raw skill text was rendered instead of the taxonomy name.");
            $skill = $state['contract']['recommendations'][$index];
            phase2Assert(!str_contains($html, $skill['target']['opaque_target_ref']) && !str_contains($html, $skill['recommendation_key']) && !str_contains($html, $skill['evidence_fingerprint']), "{$locale}: private recommendation identity was rendered.");
            phase2Assert(str_contains($html, 'name="action" value="snooze"') && str_contains($html, 'name="action" value="dismiss"'), "{$locale}: recommendation controls are missing.");
            $escapedModel = $model;
            $escapedModel['recommendations'][$index]['skill_display_name'] = '<script>unsafe</script>';
            ob_start();
            renderEvidenceHubOwnerPage($state['contract'], $escapedModel, $tokens, '', ['kind' => 'none'], $locale);
            $escaped = (string) ob_get_clean();
            phase2Assert(str_contains($escaped, '<bdi dir="auto">&lt;script&gt;unsafe&lt;/script&gt;</bdi>') && !str_contains($escaped, '<script>unsafe</script>'), "{$locale}: skill display text was not escaped.");
        }
        $other = self::context(2, 20);
        $otherState = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $other, 1767225600, self::HMAC);
        $otherModel = buildAuthorizedEvidenceHubOwnerPageModel($database, $other, $otherState, self::HMAC);
        $otherIndex = array_search('add_skill_evidence', array_column($otherState['contract']['recommendations'], 'rule_id'), true);
        phase2Assert(is_int($otherIndex) && $otherModel['recommendations'][$otherIndex]['skill_display_name'] === 'Vue.js', 'Owner B received a foreign skill context.');
        foreach (['handlers/public_projects_json.php', 'public/index.php', 'config/routes.php', 'includes/public_lifecycle.php', 'public/assets/js/portfolio.js'] as $file) {
            $source = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $file);
            phase2Assert(!str_contains($source, 'add_skill_evidence') && !str_contains($source, 'evidence_hub_owner_recommendations'), "{$file}: a private recommendation reached a visitor route.");
        }
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
