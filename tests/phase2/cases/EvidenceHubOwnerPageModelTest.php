<?php

declare(strict_types=1);

final class EvidenceHubOwnerPageModelTest
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_model.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_presentation.php';
        self::staticContracts();
        self::fractionalEpochParsing();
        self::progressStages();
        self::publishedProgressCounts();
        self::readinessCopy();
        self::partialVisualFacts();
        if (evidenceTextUnicodeRuntimeIsAvailable() && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::scopedPersistenceAndPresentation();
        }
    }

    private static function staticContracts(): void
    {
        $root = PHASE2_REPOSITORY_ROOT . '/';
        phase2AssertSame('ab54f42a93b6e4eeacd97a2b416f261bf8ba3d280f2fecf205040d0aeecd7bd0', hash_file('sha256', $root . 'contracts/evidence-hub-contract-v1.schema.json'), 'Analytics v1 schema changed.');
        $route = file_get_contents($root . 'owner_project_evidence.php');
        $presentation = file_get_contents($root . 'includes/evidence_hub_owner_page_presentation.php');
        $editPresentation = file_get_contents($root . 'includes/owner_project_evidence_presentation.php');
        $model = file_get_contents($root . 'includes/evidence_hub_owner_page_model.php');
        $repository = file_get_contents($root . 'includes/project_evidence_repository.php');
        foreach (['requireOwnerPortfolioContext($database)', 'requireValidCsrfToken', 'runDatabaseTransaction', "httpRedirect('/owner/evidence-hub#project-' . \$projectId, 303)", 'renderOwnerProjectEvidenceForm'] as $required) {
            phase2Assert(str_contains($route, $required), "Evidence route lacks {$required}.");
        }
        foreach (['ownerRenderFormFeedback', 'ownerFieldAccessibilityAttributes', 'ownerEscapeHtml', 'Save evidence', 'Cancel'] as $required) {
            phase2Assert(str_contains($editPresentation, $required), "Evidence form lacks {$required}.");
        }
        phase2Assert(str_contains($repository, "'problem' => 'problem_statement'") && !str_contains(file_get_contents($root . 'database/migrations/012_project_updated_at.sql'), 'ADD COLUMN problem'), 'Problem storage boundary is not explicit.');
        phase2Assert(!str_contains($presentation, 'problem_statement') && !str_contains($presentation, 'reason_codes'), 'Storage name or reason codes leak into owner markup.');
        phase2Assert(str_contains($model, "'key' => \$field") && str_contains($presentation, 'id="project-<?php echo (int) $project[\'id\']; ?>"'), 'Logical field key or stable project fragment is missing.');
        // Editor anchors are defined once, by the writing guidance the editor renders.
        $guidance = file_get_contents($root . 'includes/evidence_writing_guidance.php');
        foreach (['#problem', '#personal-role', '#measurable-outcome'] as $anchor) {
            phase2Assert(str_contains($editPresentation . $presentation, $anchor) || str_contains($guidance, "'anchor' => '" . trim($anchor, '#') . "'"), "Evidence fragment {$anchor} is missing.");
        }
        phase2Assert(str_contains(file_get_contents($root . 'evidence_hub.js'), 'targetCard()') && str_contains(file_get_contents($root . 'evidence_hub.js'), 'revealFragment()'), 'Targeted project reveal is missing.');
        phase2Assert(str_contains(file_get_contents($root . 'docker/apache/production-vhost.conf'), '^/owner/projects/[1-9][0-9]*/evidence/?$'), 'Clean production evidence route is missing.');
        phase2Assert(str_contains(file_get_contents($root . 'docker/apache/development-vhost.conf'), '^/owner/projects/[1-9][0-9]*/evidence/?$'), 'Clean development evidence route is missing.');
    }

    private static function fractionalEpochParsing(): void
    {
        foreach ([
            [0, 0],
            [1720000000, 1720000000],
            ['0', 0],
            ['1720000000', 1720000000],
            ['1720000000.000000', 1720000000],
            ['1720000000.987654', 1720000000],
            ['0.999999', 0],
            [(string) PHP_INT_MAX . '.999999', PHP_INT_MAX],
        ] as [$input, $expected]) {
            phase2AssertSame($expected, evidenceHubProjectRecordedAtEpochSeconds($input), 'Valid project epoch was not converted to whole seconds.');
        }

        foreach ([
            null, false, -1, -1.0, 1.5, INF, NAN, [], '',
            '-1', '-1.000000', '01', '01.000000', ' 1720000000.000000',
            '1720000000.000000 ', '.5', '1.', '1..2', '1.0000000',
            '1e9', '1E+9', 'NaN', 'INF', (string) PHP_INT_MAX . '0.000000',
        ] as $input) {
            phase2AssertSame(null, evidenceHubProjectRecordedAtEpochSeconds($input), 'Malformed project epoch was accepted.');
        }

        $updated = evidenceHubProjectRecordedAtEpochSeconds('1720000000.000000');
        phase2AssertSame('2024-07-03', $updated === null ? null : gmdate('Y-m-d', $updated), 'Fractional MySQL epoch still produces an unavailable project update date.');
    }

    private static function progressStages(): void
    {
        $day = 86400;
        phase2AssertSame('no_projects', evidenceHubOwnerPageProgress(0, 0, [])['stage'], 'Zero stage changed.');
        phase2AssertSame('incomplete_first_project', evidenceHubOwnerPageProgress(3, 0, [])['stage'], 'Multiple incomplete projects have the wrong stage.');
        phase2AssertSame('single_project', evidenceHubOwnerPageProgress(3, 1, [100])['stage'], 'One complete project plus incomplete projects has the wrong stage.');
        phase2AssertSame('emerging_patterns', evidenceHubOwnerPageProgress(2, 2, [100, 100 + 40 * $day])['stage'], 'Two complete projects cannot qualify history.');
        phase2AssertSame('emerging_patterns', evidenceHubOwnerPageProgress(3, 3, [100, 100 + $day, 100 + 29 * $day])['stage'], 'Fewer than 30 days qualified.');
        phase2AssertSame('emerging_patterns', evidenceHubOwnerPageProgress(3, 3, [100, 100 + $day, 100 + 30 * $day - 1])['stage'], 'Less than 30 full days qualified.');
        phase2AssertSame('trend_ready', evidenceHubOwnerPageProgress(3, 3, [100, 100 + $day, 100 + 30 * $day])['stage'], 'Exactly 30 full days did not qualify.');
        phase2AssertSame('emerging_patterns', evidenceHubOwnerPageProgress(3, 3, [100, 100 + 40 * $day])['stage'], 'Invalid or missing third activity timestamp qualified.');
        phase2Assert(!str_contains(strtolower(evidenceHubOwnerPageProgress(3, 3, [100, 100 + $day])['label']), 'trend'), 'Insufficient history claims a trend.');
        phase2AssertSame(null, evidenceHubOwnerPageActivityText(null, null), 'Missing activity rendered a date.');
        phase2AssertSame('2026-09-11', evidenceHubOwnerPageActivityText('2026-09-11', '2026-09-11'), 'Equal activity dates rendered a range.');
        phase2AssertSame('2026-09-11 to 2026-10-12', evidenceHubOwnerPageActivityText('2026-09-11', '2026-10-12'), 'Different activity dates lost their range.');
        phase2AssertSame(null, evidenceHubOwnerPageActivityText(null, '2026-09-11'), 'Incomplete activity range rendered a date.');
        foreach ([0, 1, 2] as $count) {
            phase2AssertSame($count . ' mapped ' . ($count === 1 ? 'technology' : 'technologies'), evidenceHubOwnerCountPhrase($count, 'mapped technology', 'mapped technologies'), 'Mapped technology grammar is wrong.');
            phase2AssertSame($count . ' unmapped ' . ($count === 1 ? 'technology' : 'technologies'), evidenceHubOwnerCountPhrase($count, 'unmapped technology', 'unmapped technologies'), 'Unmapped technology grammar is wrong.');
            phase2AssertSame($count . ' ' . ($count === 1 ? 'project' : 'projects'), evidenceHubOwnerCountPhrase($count, 'project', 'projects'), 'Project grammar is wrong.');
        }
    }

    private static function publishedProgressCounts(): void
    {
        phase2AssertSame(null, evidenceHubOwnerPageProgress(3, 1, [])['published_project_count'], 'Three-argument callers must retain an unknown published count.');
        foreach ([[0, 0], [3, 0], [3, 1], [3, 3]] as [$total, $published]) {
            phase2AssertSame($published, evidenceHubOwnerPageProgress($total, 0, [], $published)['published_project_count'], 'The supplied published count was not retained.');
        }
        foreach ([[0, -1], [0, 1], [3, -1], [3, 4]] as [$total, $published]) {
            try {
                evidenceHubOwnerPageProgress($total, 0, [], $published);
                throw new RuntimeException('An impossible published project count was accepted.');
            } catch (InvalidArgumentException) {
                // Published projects must be a subset of the Owner's projects.
            }
        }
    }

    private static function readinessCopy(): void
    {
        $zeroCopy = 'No project evidence is recorded yet.';
        $partialCopy = 'No project has all three evidence fields complete yet. Continue recording the missing fields below.';
        $someReadyCopy = 'At least one project has all three evidence fields complete. Review the remaining projects below.';
        $allReadyCopy = 'Every eligible project has all three evidence fields complete. Keep them current.';
        $cases = [
            'no projects' => [[], null, 0, 'zero', $zeroCopy],
            'four missing' => [[0, 0, 0, 0], 0, 0, 'partial', $partialCopy],
            'one field' => [[1], 3333, 0, 'partial', $partialCopy],
            'two fields' => [[2], 6667, 0, 'partial', $partialCopy],
            'one ready three missing' => [[3, 0, 0, 0], 2500, 1, 'ready', $someReadyCopy],
            'two ready two missing' => [[3, 3, 0, 0], 5000, 2, 'ready', $someReadyCopy],
            'mixed evidence' => [[1, 2, 3, 0], 5000, 1, 'ready', $someReadyCopy],
            'four ready' => [[3, 3, 3, 3], 10000, 4, 'ready', $allReadyCopy],
            'one ready' => [[3], 10000, 1, 'ready', $allReadyCopy],
        ];
        $names = ['problem_statement', 'personal_role', 'measurable_outcome'];
        foreach ($cases as $name => [$counts, $expectedBps, $expectedCompleteProjects, $expectedState, $expectedCopy]) {
            $evaluated = [];
            $recommendationProjects = [];
            $pageProjects = [];
            foreach ($counts as $index => $completeFields) {
                $evaluations = [];
                $states = [];
                $pageFields = [];
                foreach ($names as $fieldIndex => $field) {
                    $status = $fieldIndex < $completeFields ? 'complete' : 'unavailable';
                    $reasons = $status === 'complete' ? [] : ['FIELD_NOT_AVAILABLE'];
                    $evaluations[$field] = ['evidence_field' => $field, 'evidence_status' => $status, 'reason_codes' => $reasons];
                    $states[$field] = $status;
                    $pageFields[] = [
                        'key' => $field === 'problem_statement' ? 'problem' : $field,
                        'label' => ['Problem', 'Personal role', 'Measurable outcome'][$fieldIndex],
                        'status' => $status === 'complete' ? 'complete' : 'missing',
                        'reason' => $status === 'complete' ? '' : 'Add a specific account of this evidence.',
                    ];
                }
                $evaluated[] = [
                    'project_ref' => $index + 1,
                    'recorded_at_epoch_seconds' => 1767225600,
                    'technology_storage_state' => 'valid',
                    'technology_storage_reason_codes' => [],
                    'technologies' => [],
                    'field_evaluations' => $evaluations,
                    'expected_evidence_fields' => 3,
                    'complete_evidence_fields' => $completeFields,
                    'project_has_complete_evidence' => $completeFields === 3,
                ];
                $recommendationProjects[] = [
                    'target_identity' => 'readiness-copy-project-' . $index,
                    'field_completeness_states' => $states,
                    'reason_codes' => $completeFields === 3 ? [] : ['FIELD_NOT_AVAILABLE'],
                ];
                $pageProjects[] = [
                    'id' => $index + 1,
                    'title' => 'Synthetic project ' . ($index + 1),
                    'status' => $completeFields === 3 ? 'complete' : ($completeFields === 0 ? 'missing' : 'needs_attention'),
                    'complete_count' => $completeFields,
                    'updated_at' => null,
                    'fields' => $pageFields,
                    'edit_url' => '/owner_projects.php?edit=' . ($index + 1),
                ];
            }
            $core = buildEvidenceHubOwnerCoreFromEvaluatedProjects($evaluated, 'published');
            $coverage = $core['documentation_coverage'];
            $progress = evidenceHubOwnerPageProgress(count($counts), $coverage['projects_with_complete_evidence'], []);
            $recommendations = buildEvidenceHubRecommendations([
                'tenant_scope_ref' => 'readiness-copy-tenant',
                'portfolio_target_identity' => 'readiness-copy-portfolio',
                'projects' => $recommendationProjects,
                'technology_mappings' => [],
                'portfolio_publication' => ['portfolio_published' => true, 'publication_prerequisites_met' => false],
            ], [], 1767225600, 'readiness-copy-synthetic-hmac');
            $incompleteCount = count(array_filter($counts, static fn (int $count): bool => $count < 3));
            $expectedRules = $counts === [] ? ['add_first_project'] : array_fill(0, min(3, $incompleteCount), 'complete_project_evidence');
            phase2AssertSame(count($counts) * 3, $coverage['expected_evidence_field_count'], "{$name}: expected fields changed.");
            phase2AssertSame(array_sum($counts), $coverage['complete_evidence_field_count'], "{$name}: complete fields changed.");
            phase2AssertSame($expectedBps, $coverage['coverage_bps'], "{$name}: coverage changed.");
            phase2AssertSame($expectedCompleteProjects, $progress['complete_project_count'], "{$name}: complete-project progress changed.");
            phase2AssertSame(count($counts), $progress['project_count'], "{$name}: eligible-project progress changed.");
            phase2AssertSame($expectedState, $core['maturity']['state'], "{$name}: maturity changed.");
            phase2AssertSame($expectedRules, array_column($recommendations, 'rule_id'), "{$name}: recommendations changed.");
            phase2AssertSame($expectedCopy, evidenceHubOwnerPageReadinessDetail($core['maturity']['state'], $progress['project_count'], $progress['complete_project_count']), "{$name}: supporting copy changed.");
            $contract = mapEvidenceHubContractV2($core, $recommendations);
            $model = ['projects' => $pageProjects, 'technologies' => evidenceHubOwnerPageTechnologies([]), 'technology_review' => evidenceHubOwnerPageTechnologyReview([], [], loadEvidenceHubOwnerTaxonomy(), static fn (string $digest): string => $digest), 'progress' => $progress, 'recommendations' => array_fill(0, count($recommendations), [])];
            ob_start();
            renderEvidenceHubOwnerPage($contract, $model);
            $html = (string) ob_get_clean();
            $summary = '<strong>' . evidenceHubOwnerPageReadinessLabel($expectedState) . '</strong><small>' . $expectedCopy . '</small>';
            phase2Assert(str_contains($html, $summary), "{$name}: rendered summary differs from the model.");
        }
    }

    private static function partialVisualFacts(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/tests/phase2/support/evidence-hub-partial-visual-facts.php';
        $facts = buildEvidenceHubPartialVisualFacts();
        $projects = $facts['projects'];
        $completedFromCards = array_sum(array_map(static fn (array $project): int => count(array_filter($project['fields'], static fn (array $field): bool => $field['status'] === 'complete')), $projects));
        phase2AssertSame(8, count($projects), 'Partial visual fixture does not contain eight projects.');
        phase2AssertSame($completedFromCards, $facts['documentation']['complete_evidence_field_count'], 'Documentation numerator differs from displayed field statuses.');
        phase2AssertSame(count($projects) * 3, $facts['documentation']['expected_evidence_field_count'], 'Documentation denominator differs from displayed cards.');
        phase2AssertSame(7, $completedFromCards, 'Partial visual field source changed.');
        phase2AssertSame(2917, $facts['documentation']['coverage_bps'], 'Partial visual coverage rounding changed.');
        phase2AssertSame(8, $facts['analytics_progress']['project_count'], 'Analytics progress scope differs from the fixture projects.');
        phase2AssertSame(1, $facts['progress']['complete_project_count'], 'Partial complete-project count changed.');
        phase2AssertSame('single_project', $facts['progress']['stage'], 'Partial progress stage changed.');
        phase2AssertSame('insufficient_history', $facts['progress']['trend_status'], 'One complete project incorrectly qualified a trend.');
        phase2AssertSame('2026-09-11', $facts['progress']['first_activity_at'], 'Partial progress date differs from its complete project.');
        phase2AssertSame($projects[7]['updated_at'], $facts['progress']['latest_activity_at'], 'Partial progress date differs from project ID 1.');
        phase2AssertSame(1, $facts['technologies']['overview']['mapped_technology_count'], 'Partial mapped technology count changed.');
        phase2AssertSame(1, $facts['technologies']['overview']['unmapped_technology_count'], 'Partial unmapped technology count changed.');
        phase2AssertSame(2, $facts['technologies']['overview']['unmapped_project_count'], 'Partial unmapped project count changed.');
        phase2AssertSame($facts['technologies']['overview']['mapped_technology_count'], $facts['technology_metric']['distinct_mapped_technology_count'], 'Mapped overview differs from deterministic technology aggregation.');
        phase2AssertSame($facts['technologies']['overview']['unmapped_technology_count'], $facts['technology_metric']['distinct_unmapped_technology_count'], 'Unmapped overview differs from deterministic technology aggregation.');
        phase2AssertSame(5, $facts['technologies']['mapped']['language'][0]['project_count'], 'Partial JavaScript project count changed.');
        phase2AssertSame(2, count($facts['technology_review']['unmapped'][0]['projects']), 'Partial unmapped technology project count changed.');
    }

    private static function scopedPersistenceAndPresentation(): void
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT NULL, is_published INTEGER NOT NULL DEFAULT 0); CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, is_published INTEGER NOT NULL DEFAULT 0, title TEXT NOT NULL, description TEXT NOT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NULL); INSERT INTO portfolios (id,owner_user_id) VALUES (10,1),(20,2)');
        $database->exec(phase2EvidenceHubTechnologyReviewSqliteSchema());
        $complete = [
            'problem' => 'Manual weekly reporting affected the team because six hours of reconciliation delayed operational decisions.',
            'personal_role' => 'I designed the reporting pipeline and implemented validation checks across each imported data source.',
            'measurable_outcome' => 'Reduced weekly reporting time from six hours to one hour.',
        ];
        $insert = $database->prepare('INSERT INTO projects (id,portfolio_id,title,description,problem_statement,personal_role,measurable_outcome,technologies,created_at,updated_at) VALUES (:id,:portfolio,:title,:description,:problem,:role,:outcome,:technologies,:created,:updated)');
        foreach ([
            [1,10,'First <script>project</script>','PRIVATE_DESCRIPTION_MARKER',$complete['problem'],$complete['personal_role'],$complete['measurable_outcome'],'["JavaScript","JS","Nebula Tool","Other Unknown"]','2026-01-01T00:00:00Z','2026-02-01T00:00:00Z'],
            [2,10,'Second project','Another description',null,null,null,'["JavaScript","Nebula Tool"]','2026-01-02T00:00:00Z','2026-02-01T00:00:00Z'],
            [9,20,'CROSS_TENANT_TITLE','CROSS_TENANT_DESCRIPTION',null,null,null,'["PrivateTech"]','2026-01-03T00:00:00Z','2026-09-01T00:00:00Z'],
        ] as $row) {
            $insert->execute(['id'=>$row[0], 'portfolio'=>$row[1], 'title'=>$row[2], 'description'=>$row[3], 'problem'=>$row[4], 'role'=>$row[5], 'outcome'=>$row[6], 'technologies'=>$row[7], 'created'=>$row[8], 'updated'=>$row[9]]);
        }
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $other = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 20);
        $database->exec('UPDATE projects SET is_published=1 WHERE id IN (1,9)');
        phase2AssertSame(null, findAuthorizedProjectEvidenceForEdit($database, $context, 9), 'Cross-tenant edit read was allowed.');
        phase2AssertSame(null, findAuthorizedProjectEvidenceForEdit($database, $other, 1), 'Cross-tenant edit read was allowed in reverse.');
        $record = findAuthorizedProjectEvidenceForEdit($database, $context, 1);
        phase2AssertSame($complete['problem'], $record['values']['problem'], 'Persisted problem_statement did not map to logical problem.');
        phase2Assert(!array_key_exists('problem_statement', $record['values']), 'Storage key escaped the read boundary.');
        $beforeEvaluation = projectEvidenceLogicalEvaluations($record['values'])['problem']['evidence_status'];
        $state = ['contract' => ['recommendations' => []]];
        $model = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, 'page-model-synthetic-hmac');
        phase2AssertSame(1, $model['progress']['published_project_count'], 'Owner A must count only their one published project.');
        phase2AssertSame('not_configured', $model['progress']['portfolio_publication_state'], 'Default fixture portfolios must remain not configured.');
        phase2AssertSame([2,1], array_column($model['projects'], 'id'), 'updated_at tie-break ordering changed.');
        phase2AssertSame(1, $model['progress']['complete_project_count'], 'Complete project count is wrong.');
        phase2AssertSame('single_project', $model['progress']['stage'], 'Progress stage is wrong.');
        phase2AssertSame('problem', $model['projects'][1]['fields'][0]['key'], 'Storage key escaped the page model.');
        phase2AssertSame(2, $model['technologies']['mapped']['language'][0]['project_count'], 'Canonical project count was not deduplicated per project.');
        // The Owner sees their own unknown labels, with the projects that contain them.
        $unmapped = $model['technology_review']['unmapped'];
        phase2AssertSame(['Nebula Tool', 'Other Unknown'], array_column($unmapped, 'raw_label'), 'Owner review must list each unknown label as written.');
        phase2AssertSame([1, 2], array_column($unmapped[0]['projects'], 'id'), 'Unmapped project count is wrong.');
        phase2AssertSame('/owner_projects.php?edit=1', $unmapped[0]['projects'][0]['edit_url'], 'Owner review must link to the project that holds the label.');
        phase2Assert(preg_match('/^[A-Za-z0-9_-]{20,}$/', $unmapped[0]['ref']) === 1 && !str_contains($serializedRefs = json_encode(array_column($unmapped, 'ref'), JSON_THROW_ON_ERROR), hash('sha256', 'nebula tool')), 'Owner review must address a label only through an opaque reference.');
        phase2AssertSame(['mapped_technology_count' => 1, 'unmapped_technology_count' => 2, 'unmapped_project_count' => 2], $model['technologies']['overview'], 'Private technology overview did not deduplicate technologies and projects.');
        $otherModel = buildAuthorizedEvidenceHubOwnerPageModel($database, $other, $state, 'page-model-synthetic-hmac');
        phase2AssertSame(1, $otherModel['progress']['published_project_count'], 'Owner B must count only their own published project.');
        phase2AssertSame(['mapped_technology_count' => 0, 'unmapped_technology_count' => 1, 'unmapped_project_count' => 1], $otherModel['technologies']['overview'], 'Cross-tenant technology counts leaked into another owner.');
        $serialized = json_encode($model, JSON_THROW_ON_ERROR);
        foreach (['CROSS_TENANT_TITLE','CROSS_TENANT_DESCRIPTION','PRIVATE_DESCRIPTION_MARKER','problem_statement','PrivateTech'] as $forbidden) {
            phase2Assert(!str_contains($serialized, $forbidden), "Private value {$forbidden} leaked from the model.");
        }
        $before = $database->query('SELECT updated_at FROM projects WHERE id=1')->fetchColumn();
        phase2Assert(saveAuthorizedProjectEvidence($database, $context, 1, $complete), 'Unchanged normalized save failed.');
        phase2AssertSame($before, $database->query('SELECT updated_at FROM projects WHERE id=1')->fetchColumn(), 'Unchanged evidence advanced updated_at.');
        $spaced = $complete;
        $spaced['problem'] = '  ' . $complete['problem'] . '  ';
        phase2Assert(saveAuthorizedProjectEvidence($database, $context, 1, $spaced), 'Equivalent normalized save failed.');
        phase2AssertSame($before, $database->query('SELECT updated_at FROM projects WHERE id=1')->fetchColumn(), 'Equivalent normalized evidence advanced updated_at.');
        $changed = $complete;
        $changed['problem'] .= ' The delay affected monthly review.';
        phase2Assert(saveAuthorizedProjectEvidence($database, $context, 1, $changed), 'Meaningful evidence save failed.');
        phase2AssertSame($changed['problem'], $database->query('SELECT problem_statement FROM projects WHERE id=1')->fetchColumn(), 'Logical problem was not persisted to problem_statement.');
        phase2Assert($before !== $database->query('SELECT updated_at FROM projects WHERE id=1')->fetchColumn(), 'Meaningful evidence save did not advance updated_at.');
        phase2AssertSame($beforeEvaluation, projectEvidenceLogicalEvaluations(findAuthorizedProjectEvidenceForEdit($database, $context, 1)['values'])['problem']['evidence_status'], 'Mapping changed deterministic evidence evaluation.');
        phase2Assert(!saveAuthorizedProjectEvidence($database, $context, 9, $complete), 'Cross-tenant write was accepted.');
        $columns = $database->query('PRAGMA table_info(projects)')->fetchAll(PDO::FETCH_ASSOC);
        phase2Assert(!in_array('problem', array_column($columns, 'name'), true), 'A duplicate problem column was created.');

        $fixtures = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-hub-golden-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
        $contract = null;
        foreach ($fixtures['positive_payloads'] as $case) {
            if ($case['id'] === 'PAYLOAD-PARTIAL') $contract = $case['payload'];
        }
        phase2Assert(is_array($contract), 'Partial contract fixture is missing.');
        ob_start();
        renderEvidenceHubOwnerPage($contract, $model);
        $html = (string) ob_get_clean();
        phase2Assert(str_contains($html, 'First &lt;script&gt;project&lt;/script&gt;'), 'Project title is not escaped.');
        phase2Assert(!str_contains($html, 'PRIVATE_DESCRIPTION_MARKER') && !str_contains($html, 'CROSS_TENANT_TITLE'), 'Owner markup leaked private or cross-tenant data.');
        phase2Assert(substr_count($html, 'class="evidence-hub-status-card"') === 3, 'Overview must have exactly three metrics.');
        phase2Assert(str_contains($html, '1 mapped technology') && str_contains($html, '2 unmapped technologies across 2 projects'), 'Overview technology units or grammar are wrong.');
        phase2Assert(str_contains($html, 'Review &amp; edit evidence') && str_contains($html, 'href="/owner/projects/2/evidence"'), 'Permanent project Evidence editor action is missing.');
        phase2Assert(!str_contains($html, 'FIELD_NOT_AVAILABLE') && !str_contains($html, 'problem_statement'), 'Raw evidence internals leaked to markup.');
        self::publishedProgressRendering($contract, $model);

        foreach ([
            [3, '2026-03-05T00:00:00Z', '2026-03-05T00:00:00Z'],
            [5, '2026-04-05T00:00:00Z', '2026-04-05T00:00:00Z'],
        ] as [$id, $created, $updated]) {
            $insert->execute(['id'=>$id, 'portfolio'=>10, 'title'=>'Dated project ' . $id, 'description'=>'Private', 'problem'=>$complete['problem'], 'role'=>$complete['personal_role'], 'outcome'=>$complete['measurable_outcome'], 'technologies'=>null, 'created'=>$created, 'updated'=>$updated]);
        }
        phase2AssertSame('trend_ready', buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, 'page-model-synthetic-hmac')['progress']['stage'], 'Three dated complete projects did not qualify history.');
        $database->exec("UPDATE projects SET updated_at='invalid' WHERE id=5");
        phase2AssertSame('emerging_patterns', buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, 'page-model-synthetic-hmac')['progress']['stage'], 'Invalid updated_at incorrectly fell back to created_at for history.');
        $database->exec('UPDATE projects SET updated_at=NULL WHERE id=5');
        phase2AssertSame('trend_ready', buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, 'page-model-synthetic-hmac')['progress']['stage'], 'Legacy null updated_at did not fall back to created_at.');
        $database->exec("UPDATE projects SET updated_at='invalid' WHERE id IN (1,3,5)");
        $invalidActivityModel = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, 'page-model-synthetic-hmac');
        phase2AssertSame('emerging_patterns', $invalidActivityModel['progress']['stage'], 'Invalid timestamps produced trend readiness.');
        phase2AssertSame(null, $invalidActivityModel['progress']['first_activity_at'], 'Invalid timestamps produced an activity date.');
        ob_start();
        renderEvidenceHubOwnerPage($contract, $invalidActivityModel);
        $invalidActivityHtml = (string) ob_get_clean();
        phase2Assert(!str_contains($invalidActivityHtml, 'Complete-project activity:'), 'Invalid activity date leaked into presentation.');
        phase2Assert(str_contains($invalidActivityHtml, 'There is not enough dated, complete project evidence'), 'Insufficient history explanation is missing.');
        self::publishedProjectStorage($database, $context, $state);
    }

    /** @param array<string, mixed> $contract @param array<string, mixed> $model */
    private static function publishedProgressRendering(array $contract, array $model): void
    {
        foreach ([
            [0, 0, 'unpublished', null, null, false],
            [3, null, 'unpublished', null, null, false],
            [3, 0, 'unpublished', 'Public projects: 0 of 3.', 'المشاريع المنشورة للزوار: 0 من 3.', false],
            [3, 0, 'not_configured', 'Public projects: 0 of 3.', 'المشاريع المنشورة للزوار: 0 من 3.', false],
            [3, 1, 'unpublished', 'Public projects: 1 of 3.', 'المشاريع المنشورة للزوار: 1 من 3.', true],
            [3, 1, 'published', 'Public projects: 1 of 3.', 'المشاريع المنشورة للزوار: 1 من 3.', false],
            [3, 1, 'not_configured', 'Public projects: 1 of 3.', 'المشاريع المنشورة للزوار: 1 من 3.', true],
            [1, 1, 'unpublished', 'Public projects: 1 of 1.', 'المشاريع المنشورة للزوار: 1 من 1.', true],
            [2, 2, 'unpublished', 'Public projects: 2 of 2.', 'المشاريع المنشورة للزوار: 2 من 2.', true],
            [3, 3, 'unpublished', 'Public projects: 3 of 3.', 'المشاريع المنشورة للزوار: 3 من 3.', true],
        ] as [$total, $published, $state, $english, $arabic, $showHelp]) {
            $model['progress'] = evidenceHubOwnerPageProgress($total, 0, [], $published);
            $model['progress']['portfolio_publication_state'] = $state;
            foreach (['en' => $english, 'ar' => $arabic] as $locale => $expected) {
                ob_start();
                renderEvidenceHubOwnerPage($contract, $model, [], '', ['kind' => 'none'], $locale);
                $html = (string) ob_get_clean();
                phase2Assert(preg_match('/<section\b[^>]*\bid="progress".*?<\/section>/s', $html, $matches) === 1, 'The progress section is missing.');
                $section = $matches[0];
                if ($expected === null) {
                    phase2Assert(!str_contains($section, 'Public projects:') && !str_contains($section, 'المشاريع المنشورة للزوار:'), "{$locale}: an unavailable or empty published count rendered.");
                } else {
                    phase2Assert(str_contains($section, '<p>' . $expected . '</p>'), "{$locale}: the published count wording or integer placeholders are wrong.");
                    phase2AssertSame(1, substr_count($html, $expected), "{$locale}: the published count must appear only in the progress section.");
                }
                $help = $locale === 'ar' ? 'تظهر المشاريع العامة للزوار فقط بعد نشر البورتفوليو.' : 'Public projects appear to visitors only after the portfolio is published.';
                phase2AssertSame($showHelp, str_contains($section, $help), "{$locale}: publication help must appear only for unpublished or unconfigured portfolios with public projects.");
            }
        }
    }

    /** @param array<string, mixed> $state */
    private static function publishedProjectStorage(PDO $database, AuthorizedPortfolioContext $context, array $state): void
    {
        $update = $database->prepare('UPDATE projects SET is_published=:published WHERE id=1');
        foreach ([-1, 2, 'invalid', 1.5] as $invalid) {
            $update->execute(['published' => $invalid]);
            $rejected = false;
            try {
                buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, 'page-model-synthetic-hmac');
            } catch (RuntimeException $exception) {
                $rejected = $exception->getMessage() === 'Evidence Hub project publication storage is invalid.';
            } finally {
                $update->execute(['published' => 1]);
            }
            phase2Assert($rejected, 'Malformed project publication storage was not rejected.');
        }
        $database->exec("UPDATE portfolios SET public_slug='owner-a', is_published=0 WHERE id=10");
        $model = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, 'page-model-synthetic-hmac');
        phase2AssertSame('unpublished', $model['progress']['portfolio_publication_state'], 'The configured unpublished state did not reach progress.');
        phase2AssertSame(1, $model['progress']['published_project_count'], 'An unpublished portfolio must retain its project publication flags.');
        $database->exec('UPDATE portfolios SET is_published=1 WHERE id=10');
        phase2AssertSame('published', buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state, 'page-model-synthetic-hmac')['progress']['portfolio_publication_state'], 'The configured published state did not reach progress.');
    }
}
