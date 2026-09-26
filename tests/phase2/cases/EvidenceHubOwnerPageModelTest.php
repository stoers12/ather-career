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
        foreach (['#problem', '#personal-role', '#measurable-outcome'] as $anchor) {
            phase2Assert(str_contains($editPresentation . $presentation, $anchor) || str_contains($editPresentation, trim($anchor, '#')), "Evidence fragment {$anchor} is missing.");
        }
        phase2Assert(str_contains(file_get_contents($root . 'evidence_hub.js'), 'targetCard()') && str_contains(file_get_contents($root . 'evidence_hub.js'), 'expanded = true'), 'Targeted project reveal is missing.');
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
        phase2AssertSame(2, $facts['technologies']['unmapped'][0]['project_count'], 'Partial unmapped technology project count changed.');
    }

    private static function scopedPersistenceAndPresentation(): void
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL); CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, title TEXT NOT NULL, description TEXT NOT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NULL); INSERT INTO portfolios VALUES (10,1),(20,2)');
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
        phase2AssertSame(null, findAuthorizedProjectEvidenceForEdit($database, $context, 9), 'Cross-tenant edit read was allowed.');
        phase2AssertSame(null, findAuthorizedProjectEvidenceForEdit($database, $other, 1), 'Cross-tenant edit read was allowed in reverse.');
        $record = findAuthorizedProjectEvidenceForEdit($database, $context, 1);
        phase2AssertSame($complete['problem'], $record['values']['problem'], 'Persisted problem_statement did not map to logical problem.');
        phase2Assert(!array_key_exists('problem_statement', $record['values']), 'Storage key escaped the read boundary.');
        $beforeEvaluation = projectEvidenceLogicalEvaluations($record['values'])['problem']['evidence_status'];
        $state = ['contract' => ['recommendations' => []]];
        $model = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state);
        phase2AssertSame([2,1], array_column($model['projects'], 'id'), 'updated_at tie-break ordering changed.');
        phase2AssertSame(1, $model['progress']['complete_project_count'], 'Complete project count is wrong.');
        phase2AssertSame('single_project', $model['progress']['stage'], 'Progress stage is wrong.');
        phase2AssertSame('problem', $model['projects'][1]['fields'][0]['key'], 'Storage key escaped the page model.');
        phase2AssertSame(2, $model['technologies']['mapped']['language'][0]['project_count'], 'Canonical project count was not deduplicated per project.');
        phase2AssertSame(2, $model['technologies']['unmapped'][0]['project_count'], 'Unmapped project count is wrong.');
        phase2AssertSame(['mapped_technology_count' => 1, 'unmapped_technology_count' => 2, 'unmapped_project_count' => 2], $model['technologies']['overview'], 'Private technology overview did not deduplicate technologies and projects.');
        $otherModel = buildAuthorizedEvidenceHubOwnerPageModel($database, $other, $state);
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
        phase2Assert(str_contains($html, '/owner/projects/2/evidence#problem') && str_contains($html, '/owner/projects/2/evidence#personal-role') && str_contains($html, '/owner/projects/2/evidence#measurable-outcome'), 'Field edit links do not target the approved anchors.');
        phase2Assert(!str_contains($html, 'FIELD_NOT_AVAILABLE') && !str_contains($html, 'problem_statement'), 'Raw evidence internals leaked to markup.');

        foreach ([
            [3, '2026-03-05T00:00:00Z', '2026-03-05T00:00:00Z'],
            [5, '2026-04-05T00:00:00Z', '2026-04-05T00:00:00Z'],
        ] as [$id, $created, $updated]) {
            $insert->execute(['id'=>$id, 'portfolio'=>10, 'title'=>'Dated project ' . $id, 'description'=>'Private', 'problem'=>$complete['problem'], 'role'=>$complete['personal_role'], 'outcome'=>$complete['measurable_outcome'], 'technologies'=>null, 'created'=>$created, 'updated'=>$updated]);
        }
        phase2AssertSame('trend_ready', buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state)['progress']['stage'], 'Three dated complete projects did not qualify history.');
        $database->exec("UPDATE projects SET updated_at='invalid' WHERE id=5");
        phase2AssertSame('emerging_patterns', buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state)['progress']['stage'], 'Invalid updated_at incorrectly fell back to created_at for history.');
        $database->exec('UPDATE projects SET updated_at=NULL WHERE id=5');
        phase2AssertSame('trend_ready', buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state)['progress']['stage'], 'Legacy null updated_at did not fall back to created_at.');
        $database->exec("UPDATE projects SET updated_at='invalid' WHERE id IN (1,3,5)");
        $invalidActivityModel = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, $state);
        phase2AssertSame('emerging_patterns', $invalidActivityModel['progress']['stage'], 'Invalid timestamps produced trend readiness.');
        phase2AssertSame(null, $invalidActivityModel['progress']['first_activity_at'], 'Invalid timestamps produced an activity date.');
        ob_start();
        renderEvidenceHubOwnerPage($contract, $invalidActivityModel);
        $invalidActivityHtml = (string) ob_get_clean();
        phase2Assert(!str_contains($invalidActivityHtml, 'Complete-project activity:'), 'Invalid activity date leaked into presentation.');
        phase2Assert(str_contains($invalidActivityHtml, 'There is not enough dated, complete project evidence'), 'Insufficient history explanation is missing.');
    }
}
