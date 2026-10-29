<?php

declare(strict_types=1);

final class EvidenceHubTechnologyReviewTest
{
    private const HMAC = 'technology-review-synthetic-hmac';

    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_technology_review_actions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_model.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_presentation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/csrf.php';

        self::ownerMappingSemantics();
        self::summaryCounts();
        self::recommendationsFollowMappings();
        self::contractVersionFive();
        self::proposalValidation();
        self::scopedActions();
        self::presentation();
        self::migrations();
    }

    private static function ownerMappingSemantics(): void
    {
        $taxonomy = loadEvidenceHubOwnerTaxonomy();
        $digest = static fn (string $label): string => evidenceHubTechnologyLabelDigest((string) normalizeEvidenceHubTechnologyLabel($label));
        $mapped = mapEvidenceHubTechnologyLabel('ReactJS', $taxonomy, [$digest('ReactJS') => 'tech.react']);
        phase2AssertSame(['owner_mapped', 'tech.react', 'React', 'framework'], [$mapped['mapping_state'], $mapped['canonical_id'], $mapped['display_name'], $mapped['category']], 'An Owner mapping did not resolve its label.');
        phase2AssertSame('owner_mapped', mapEvidenceHubTechnologyLabel('  reactjs ', $taxonomy, [$digest('ReactJS') => 'tech.react'])['mapping_state'], 'An Owner mapping must apply to every normalized spelling of its label.');
        phase2AssertSame('mapped', mapEvidenceHubTechnologyLabel('React', $taxonomy, [$digest('React') => 'tech.excel'])['mapping_state'], 'A taxonomy alias must win over an Owner mapping.');
        phase2AssertSame('tech.react', mapEvidenceHubTechnologyLabel('React', $taxonomy, [$digest('React') => 'tech.excel'])['canonical_id'], 'An Owner mapping overrode a taxonomy alias.');
        phase2AssertSame('unmapped', mapEvidenceHubTechnologyLabel('ReactJS', $taxonomy, [$digest('ReactJS') => 'tech.retired-entry'])['mapping_state'], 'A mapping to an entry absent from the taxonomy must be ignored.');
        phase2AssertSame('unmapped', mapEvidenceHubTechnologyLabel('ReactJS', $taxonomy)['mapping_state'], 'Mappings must be explicit inputs.');
    }

    private static function summaryCounts(): void
    {
        $taxonomy = loadEvidenceHubOwnerTaxonomy();
        $mappings = [evidenceHubTechnologyLabelDigest('reactjs') => 'tech.react'];
        $facts = [
            ['storage_state' => 'valid', 'reason_codes' => [], 'labels' => ['React', 'ReactJS', 'Raect']],
            ['storage_state' => 'valid', 'reason_codes' => [], 'labels' => ['ReactJS']],
        ];
        $summary = summarizeEvidenceHubTechnologies($facts, $taxonomy, $mappings);
        phase2AssertSame([4, 1, 2, 1], [$summary['technology_occurrence_count'], $summary['mapped_occurrence_count'], $summary['owner_mapped_occurrence_count'], $summary['unmapped_occurrence_count']], 'Owner-mapped occurrences are miscounted.');
        phase2AssertSame([2, 1, 1], [$summary['distinct_technology_count'], $summary['distinct_mapped_technology_count'], $summary['distinct_unmapped_technology_count']], 'React reached by alias and by Owner mapping must be one distinct technology.');
        phase2AssertSame(['TECHNOLOGY_MAPPED', 'TECHNOLOGY_OWNER_MAPPED', 'TECHNOLOGY_UNMAPPED'], $summary['reason_codes'], 'Owner mapping reason code is missing.');
        $resolved = summarizeEvidenceHubTechnologies([['storage_state' => 'valid', 'reason_codes' => [], 'labels' => ['ReactJS']]], $taxonomy, $mappings);
        phase2AssertSame('ready', $resolved['status'], 'A fully mapped technology set must be ready.');
        phase2AssertSame(0, summarizeEvidenceHubTechnologies($facts, $taxonomy)['owner_mapped_occurrence_count'], 'Without mappings nothing may be Owner-mapped.');
    }

    private static function recommendationsFollowMappings(): void
    {
        $taxonomy = loadEvidenceHubOwnerTaxonomy();
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $project = ['project_ref' => 1, 'technology_storage_state' => 'valid', 'technologies' => ['ReactJS', 'Raect'], 'field_evaluations' => self::completeEvaluations(), 'expected_evidence_fields' => 3, 'complete_evidence_fields' => 3, 'project_has_complete_evidence' => true, 'reason_codes' => []];
        $publication = ['portfolio_published' => true, 'publication_prerequisites_met' => false];
        $count = static function (array $mappings) use ($context, $project, $taxonomy, $publication): int {
            $facts = buildEvidenceHubOwnerRecommendationFacts($context, [$project], $taxonomy, $publication, $mappings)['facts'];
            return count(array_filter(buildEvidenceHubRecommendationCandidates($facts, self::HMAC), static fn (array $candidate): bool => $candidate['rule_id'] === 'review_unmapped_technology'));
        };
        phase2AssertSame(2, $count([]), 'Each unknown label must have one review recommendation.');
        phase2AssertSame(1, $count([evidenceHubTechnologyLabelDigest('reactjs') => 'tech.react']), 'An Owner-mapped label must resolve its review recommendation.');
        // Unchanged rule version keeps existing dispositions and fingerprints valid.
        phase2AssertSame('1.0.0', EVIDENCE_HUB_RECOMMENDATION_RULE_VERSION, 'Owner mappings must not change the recommendation rule version.');
    }

    private static function contractVersionFive(): void
    {
        $taxonomy = loadEvidenceHubTechnologyTaxonomy('v4');
        $core = buildEvidenceHubOwnerCoreFromEvaluatedProjects([[
            'project_ref' => 1, 'recorded_at_epoch_seconds' => 1767225600, 'technology_storage_state' => 'valid', 'technology_storage_reason_codes' => [],
            'technologies' => ['React', 'ReactJS', 'Raect'], 'field_evaluations' => self::completeEvaluations(), 'expected_evidence_fields' => 3, 'complete_evidence_fields' => 3, 'project_has_complete_evidence' => true,
        ]], 'published', [evidenceHubTechnologyLabelDigest('reactjs') => 'tech.react']);
        // V5 semantics are frozen over v4, independently of the live Owner selection.
        $core['technology_evidence_map'] = summarizeEvidenceHubTechnologies([
            ['storage_state' => 'valid', 'reason_codes' => [], 'labels' => ['React', 'ReactJS', 'Raect']],
        ], $taxonomy, [evidenceHubTechnologyLabelDigest('reactjs') => 'tech.react']);
        $contract = mapEvidenceHubContractV5($core, []);
        phase2AssertSame(['evidence-hub-contract-v5', '5.0.0'], [$contract['contract_id'], $contract['schema_version']], 'The v5 mapper must keep the frozen v5 identity.');
        phase2AssertSame('v4', $taxonomy['taxonomy_version'], 'Contract v5 publishes taxonomy v4.');
        $metric = $contract['metrics']['technology_evidence_map'];
        $schema = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-contract-v5.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        phase2AssertSame($schema['$defs']['technology_metric']['required'], array_keys($metric), 'V5 technology metric fields differ from the v5 schema order.');
        phase2AssertSame(['mapped', 'owner_mapped', 'unmapped'], array_column($metric['mappings'], 'mapping_state'), 'V5 must publish each mapping state.');
        phase2AssertSame(1, $metric['owner_mapped_occurrence_count'], 'V5 Owner-mapped count is wrong.');

        try {
            mapEvidenceHubContractV4($core, []);
            throw new RuntimeException('Contract v4 accepted an Owner-mapped technology.');
        } catch (EvidenceHubContractMappingException) {
            // Frozen contracts can never carry the v5 state.
        }
        $tampered = $core;
        ++$tampered['technology_evidence_map']['owner_mapped_occurrence_count'];
        try {
            mapEvidenceHubContractV5($tampered, []);
            throw new RuntimeException('Contract v5 accepted counts that disagree with its mappings.');
        } catch (EvidenceHubContractMappingException) {
            // Every occurrence is accounted for by exactly one state.
        }
        try {
            mapEvidenceHubContractVersion($core, [], 'v999');
            throw new RuntimeException('An undefined contract version was accepted.');
        } catch (EvidenceHubContractMappingException) {
            // Versions are an explicit table.
        }

        $v4 = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-contract-v4.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        phase2AssertSame(['evidence-hub-contract-v5', '5.0.0', 'v4'], [$schema['properties']['contract_id']['const'], $schema['properties']['schema_version']['const'], $schema['$defs']['technology_mapping']['properties']['taxonomy_version']['const']], 'V5 schema identity is wrong.');
        foreach (['maturity', 'documentation_coverage', 'portfolio_progress', 'recommendation', 'field_evaluation', 'confidence'] as $definition) {
            phase2AssertSame($v4['$defs'][$definition] ?? null, $schema['$defs'][$definition] ?? null, "V5 changed the frozen {$definition} definition.");
        }
        phase2AssertSame(array_values(array_diff($schema['$defs']['reason_code']['enum'], ['TECHNOLOGY_OWNER_MAPPED'])), $v4['$defs']['reason_code']['enum'], 'V5 may only add the Owner-mapped reason code.');
    }

    private static function proposalValidation(): void
    {
        $taxonomy = loadEvidenceHubOwnerTaxonomy();
        $valid = evidenceHubValidateTechnologyProposal('  Laravel   Framework ', 'framework', " Used for\r\nthe API ", $taxonomy);
        phase2AssertSame([], $valid['errors'], 'A valid proposal was rejected.');
        phase2AssertSame(['proposed_label' => 'Laravel Framework', 'category' => 'framework', 'note' => "Used for\nthe API"], $valid['values'], 'Proposal text was not normalized.');
        phase2AssertSame(null, evidenceHubValidateTechnologyProposal('Vue', 'framework', '   ', $taxonomy)['values']['note'], 'A blank note must be stored as null.');
        foreach ([
            [['', 'framework', null], 'proposed_label'],
            [[['Vue'], 'framework', null], 'proposed_label'],
            [[str_repeat('x', 61), 'framework', null], 'proposed_label'],
            [['react', 'framework', null], 'proposed_label'],
            [['Vue', 'frameworks', null], 'category'],
            [['Vue', 'framework', str_repeat('n', 281)], 'note'],
            [['Vue', 'framework', "bad\u{202E}note"], 'note'],
            [["Vue\u{0007}", 'framework', null], 'proposed_label'],
        ] as [$input, $field]) {
            $errors = evidenceHubValidateTechnologyProposal($input[0], $input[1], $input[2], $taxonomy)['errors'];
            phase2Assert(isset($errors[$field]), "Proposal validation did not reject {$field}.");
        }
        phase2Assert(!evidenceHubTechnologyMappingTargetIsValid($taxonomy, 'tech.unknown') && !evidenceHubTechnologyMappingTargetIsValid($taxonomy, ['tech.react']) && evidenceHubTechnologyMappingTargetIsValid($taxonomy, 'tech.react'), 'Mapping targets must be current taxonomy entries.');
        $options = evidenceHubTechnologyMappingOptions($taxonomy);
        phase2AssertSame(array_values(array_filter($taxonomy['category_order'], static fn (string $category): bool => isset($options[$category]))), array_keys($options), 'Mapping choices must follow taxonomy category order.');
        phase2AssertSame(count(array_filter($taxonomy['entries'], static fn (array $entry): bool => !$entry['deprecated'] && !$entry['merged'])), array_sum(array_map('count', $options)), 'Every current taxonomy entry must be a mapping choice.');
    }

    private static function scopedActions(): void
    {
        $database = self::database();
        $ownerA = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $ownerB = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 20);
        $act = static fn (AuthorizedPortfolioContext $context, array $request): array => executeEvidenceHubTechnologyReviewAction($database, $context, self::HMAC, $request + ['csrf_token' => 'checked-by-route']);
        $refA = static fn (string $label): string => evidenceHubTechnologyReviewRef(self::HMAC, $ownerA, evidenceHubTechnologyLabelDigest((string) normalizeEvidenceHubTechnologyLabel($label)));

        // Recommendation target references are never rendered: recommendation keys derive from them.
        $digest = evidenceHubTechnologyLabelDigest('reactjs');
        $recommendationRef = evidenceHubOpaqueTargetRef(self::HMAC, evidenceHubOwnerInternalTargetIdentity('tenant', '10'), 'technology', evidenceHubOwnerInternalTargetIdentity('technology', $digest));
        phase2Assert($refA('ReactJS') !== $recommendationRef, 'A review form reference must not equal a recommendation target reference.');
        phase2Assert($refA('ReactJS') !== evidenceHubTechnologyReviewRef(self::HMAC, $ownerB, $digest), 'A review form reference must be bound to its Portfolio.');
        phase2Assert($refA('ReactJS') !== evidenceHubTechnologyReviewRef('another-synthetic-hmac', $ownerA, $digest), 'A review form reference must depend on the HMAC key.');
        phase2AssertSame('unavailable', $act($ownerB, ['action' => 'map_technology', 'technology_ref' => $refA('ReactJS'), 'canonical_id' => 'tech.react'])['status'], 'A foreign reference resolved for another Portfolio.');
        phase2AssertSame('unavailable', $act($ownerA, ['action' => 'map_technology', 'technology_ref' => $refA('Not In Any Project'), 'canonical_id' => 'tech.react'])['status'], 'A reference to an absent label resolved.');
        phase2AssertSame('invalid', $act($ownerA, ['action' => 'map_technology', 'technology_ref' => $refA('ReactJS'), 'canonical_id' => 'tech.unknown'])['status'], 'An unknown mapping target was accepted.');
        phase2AssertSame('invalid', $act($ownerA, ['action' => 'map_technology', 'technology_ref' => $refA('ReactJS'), 'canonical_id' => 'tech.react', 'portfolio_id' => '20'])['status'], 'An unexpected field was accepted.');
        phase2AssertSame('invalid', $act($ownerA, ['action' => 'map_technology', 'technology_ref' => $refA('ReactJS')])['status'], 'A missing field was accepted.');
        phase2AssertSame('unavailable', $act($ownerA, ['action' => 'unmap_technology', 'technology_ref' => $refA('ReactJS')])['status'], 'Unmapping a label that is not Owner-mapped succeeded.');

        phase2AssertSame(['status' => 'done', 'feedback' => 'Technology mapped.'], $act($ownerA, ['action' => 'map_technology', 'technology_ref' => $refA('ReactJS'), 'canonical_id' => 'tech.react']), 'A valid mapping failed.');
        phase2AssertSame(['status' => 'done', 'feedback' => 'Technology mapped.'], $act($ownerA, ['action' => 'map_technology', 'technology_ref' => $refA('ReactJS'), 'canonical_id' => 'tech.react']), 'Repeating a mapping must be idempotent.');
        phase2AssertSame([evidenceHubTechnologyLabelDigest('reactjs') => 'tech.react'], loadAuthorizedTechnologyOwnerMappings($database, $ownerA), 'The mapping was not stored once for its Portfolio.');
        phase2AssertSame([], loadAuthorizedTechnologyOwnerMappings($database, $ownerB), 'A mapping crossed into another Portfolio.');
        phase2AssertSame(1, buildAuthorizedEvidenceHubOwnerCore($database, $ownerA)['technology_evidence_map']['owner_mapped_occurrence_count'], 'The stored mapping did not reach the Owner core.');
        phase2AssertSame(0, buildAuthorizedEvidenceHubOwnerCore($database, $ownerB)['technology_evidence_map']['owner_mapped_occurrence_count'], 'Owner A mapping changed Owner B analytics.');
        phase2AssertSame('unavailable', $act($ownerA, ['action' => 'propose_technology', 'technology_ref' => $refA('ReactJS'), 'proposed_label' => 'ReactJS', 'category' => 'framework', 'note' => ''])['status'], 'A mapped label must not be proposed.');
        phase2AssertSame(['status' => 'done', 'feedback' => 'Technology mapping removed.'], $act($ownerA, ['action' => 'unmap_technology', 'technology_ref' => $refA('ReactJS')]), 'Unmapping failed.');
        phase2AssertSame([], loadAuthorizedTechnologyOwnerMappings($database, $ownerA), 'Unmapping must restore the unmapped state exactly.');

        $rejected = $act($ownerA, ['action' => 'propose_technology', 'technology_ref' => $refA('Raect'), 'proposed_label' => 'React', 'category' => 'framework', 'note' => '']);
        phase2AssertSame(['rejected', ['proposed_label' => 'This technology is already in the list. Map it instead.']], [$rejected['status'], $rejected['form']['errors']], 'A proposal for a known technology was not rejected with a field error.');
        phase2AssertSame('done', $act($ownerA, ['action' => 'propose_technology', 'technology_ref' => $refA('Raect'), 'proposed_label' => 'Raect Toolkit', 'category' => 'library', 'note' => 'Internal fork.'])['status'], 'A valid proposal failed.');
        $duplicate = $act($ownerA, ['action' => 'propose_technology', 'technology_ref' => $refA('Raect'), 'proposed_label' => 'Raect Toolkit', 'category' => 'library', 'note' => '']);
        phase2AssertSame(['proposed_label' => 'A proposal for this technology is already pending.'], $duplicate['form']['errors'] ?? null, 'A second pending proposal for one label was accepted.');
        $proposals = listAuthorizedTechnologyProposals($database, $ownerA);
        phase2AssertSame([['Raect Toolkit', 'library', 'Internal fork.', 'pending']], array_map(static fn (array $proposal): array => [$proposal['proposed_label'], $proposal['category'], $proposal['note'], $proposal['state']], $proposals), 'The proposal was not stored as pending.');
        phase2AssertSame([], listAuthorizedTechnologyProposals($database, $ownerB), 'A proposal crossed into another Portfolio.');
        phase2AssertSame('unavailable', $act($ownerB, ['action' => 'withdraw_technology_proposal', 'proposal_id' => (string) $proposals[0]['id']])['status'], 'A foreign proposal was withdrawn.');
        phase2AssertSame('invalid', $act($ownerA, ['action' => 'withdraw_technology_proposal', 'proposal_id' => '0'])['status'], 'An invalid proposal ID was accepted.');
        phase2AssertSame('done', $act($ownerA, ['action' => 'withdraw_technology_proposal', 'proposal_id' => (string) $proposals[0]['id']])['status'], 'Withdrawing a proposal failed.');
        phase2AssertSame('unavailable', $act($ownerA, ['action' => 'withdraw_technology_proposal', 'proposal_id' => (string) $proposals[0]['id']])['status'], 'A withdrawn proposal was withdrawn again.');
        phase2AssertSame('done', $act($ownerA, ['action' => 'propose_technology', 'technology_ref' => $refA('Raect'), 'proposed_label' => 'Raect Toolkit', 'category' => 'library', 'note' => ''])['status'], 'A label could not be proposed again after a withdrawal.');
        phase2AssertSame('invalid', $act($ownerA, ['action' => 'snooze', 'technology_ref' => $refA('Raect')])['status'], 'A non-technology action reached the technology review.');
    }

    private static function presentation(): void
    {
        $taxonomy = loadEvidenceHubOwnerTaxonomy();
        $items = evidenceHubTechnologyReviewItems([
            ['id' => 3, 'title' => 'Second <b>project</b>', 'technology_storage' => ['storage_state' => 'valid', 'labels' => ['<script>x</script>', 'ReactJS']]],
            ['id' => 1, 'title' => 'First project', 'technology_storage' => ['storage_state' => 'valid', 'labels' => ['<SCRIPT>x</SCRIPT>']]],
            ['id' => 2, 'title' => 'Invalid storage', 'technology_storage' => ['storage_state' => 'invalid', 'labels' => []]],
        ], $taxonomy, [evidenceHubTechnologyLabelDigest('reactjs') => 'tech.react']);
        phase2AssertSame(['unmapped', 'owner_mapped'], array_values(array_column($items, 'mapping_state')), 'Unmapped labels must come before Owner-mapped labels.');
        $unknown = array_values($items)[0];
        phase2AssertSame(['<SCRIPT>x</SCRIPT>', [1, 3]], [$unknown['raw_label'], array_column($unknown['projects'], 'id')], 'Review items must merge spellings deterministically and list projects in order.');

        $review = evidenceHubOwnerPageTechnologyReview($items, [], $taxonomy, static fn (string $digest): string => 'ref-' . substr($digest, 0, 6));
        foreach (['en', 'ar'] as $locale) {
            ob_start();
            renderEvidenceHubOwnerTechnologyReview($review, $locale, ['ref' => $review['unmapped'][0]['ref'], 'values' => ['proposed_label' => 'Typed name', 'category' => 'tool', 'note' => null], 'errors' => ['category' => 'Choose a category.']]);
            $html = (string) ob_get_clean();
            phase2Assert(str_contains($html, '&lt;SCRIPT&gt;x&lt;/SCRIPT&gt;') && !str_contains($html, '<SCRIPT>') && str_contains($html, 'Second &lt;b&gt;project&lt;/b&gt;'), "{$locale}: Owner labels and titles are not escaped.");
            phase2Assert(!str_contains($html, evidenceHubTechnologyLabelDigest('<script>x</script>')), "{$locale}: a label digest reached the page.");
            phase2Assert(str_contains($html, 'href="/owner_projects.php?edit=1"') && str_contains($html, 'href="/owner_projects.php?edit=3"'), "{$locale}: fix-in-project links are missing.");
            phase2Assert(str_contains($html, '<details class="evidence-hub-technology-propose" open>') && str_contains($html, 'value="Typed name"') && str_contains($html, 'aria-invalid="true"'), "{$locale}: a rejected proposal did not keep its values and errors.");
            phase2AssertSame(3, substr_count($html, 'name="technology_ref"'), "{$locale}: one unmapped label needs map and propose forms, and one Owner mapping an unmap form.");
            phase2Assert(str_contains($html, $locale === 'ar' ? 'اختر فئة.' : 'Choose a category.'), "{$locale}: the proposal error is not localized.");
        }
    }

    private static function migrations(): void
    {
        foreach (['023_technology_owner_mappings' => 'technology_owner_mappings', '024_technology_proposals' => 'technology_proposals'] as $file => $table) {
            $migration = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/database/migrations/' . $file . '.sql');
            $rollback = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/database/rollback/' . $file . '.sql');
            foreach (["CREATE TABLE {$table}", 'portfolio_id INT UNSIGNED NOT NULL', 'REFERENCES portfolios (id)', 'ON DELETE RESTRICT', "label_digest REGEXP '^[a-f0-9]{64}$'", 'ENGINE=InnoDB'] as $required) {
                phase2Assert(str_contains($migration, $required), "Migration {$file} lacks {$required}.");
            }
            phase2AssertSame("DROP TABLE {$table};", trim((string) preg_replace('/^\s*--.*$/m', '', $rollback)), "Rollback {$file} must drop only its table.");
            phase2Assert(!preg_match('/\b(raw_label|label_text)\b/i', $file === '023_technology_owner_mappings' ? $migration : ''), 'Owner mappings must store only the label digest.');
        }
        $proposals = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/database/migrations/024_technology_proposals.sql');
        phase2Assert(str_contains($proposals, "CASE WHEN state = 'pending' THEN label_digest ELSE NULL END") && str_contains($proposals, 'UNIQUE KEY uq_technology_proposals_pending (portfolio_id, pending_label_digest)'), 'At most one pending proposal per label must be enforced by the database.');
        foreach (EVIDENCE_HUB_TAXONOMY_V4_CATEGORIES as $category) {
            phase2Assert(str_contains($proposals, "'{$category}'"), "Proposal categories must accept {$category}.");
        }
    }

    private static function database(): PDO
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT NULL, is_published INTEGER NOT NULL); CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, is_published INTEGER NOT NULL DEFAULT 0, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL);');
        $database->exec(phase2EvidenceHubTechnologyReviewSqliteSchema());
        $database->exec("INSERT INTO portfolios VALUES (10, 1, 'owner-a', 1), (20, 2, 'owner-b', 0)");
        $database->exec("INSERT INTO projects (id, portfolio_id, technologies, created_at) VALUES (1, 10, '[\"React\", \"ReactJS\", \"Raect\"]', '2026-01-01T00:00:00Z'), (2, 20, '[\"ReactJS\"]', '2026-01-01T00:00:00Z')");

        return $database;
    }

    /** @return array<string, array<string, mixed>> */
    private static function completeEvaluations(): array
    {
        $evaluations = [];
        foreach (evidenceTextFieldNames() as $field) {
            $evaluations[$field] = ['evidence_field' => $field, 'evidence_status' => 'complete', 'reason_codes' => ['TEXT_COMPLETE']];
        }

        return $evaluations;
    }
}
