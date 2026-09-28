<?php

declare(strict_types=1);

final class EvidenceHubAggregationTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_core.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_model.php';
        requireEvidenceTextUnicodeRuntime();
        $fixtures = self::fixtures();
        self::documentationAndMaturity($fixtures);
        self::progress($fixtures);
        self::technologyStorageAndSummary($fixtures);
        self::taxonomyAndMapping();
        self::taxonomyV2();
        self::taxonomyV3();
        if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::ownerScopeIntegration();
        }
        self::staticNonDisclosure();
    }

    /** @param array<string, mixed> $fixtures */
    private static function documentationAndMaturity(array $fixtures): void
    {
        $none = aggregateEvidenceHubDocumentationCoverage([]);
        phase2AssertSame('unavailable', $none['status'], 'No-project coverage status changed.');
        phase2AssertSame(null, $none['coverage_bps'], 'No-project coverage denominator must remain null.');
        phase2AssertSame(['NO_PROJECTS'], $none['reason_codes'], 'No-project coverage reason changed.');
        phase2AssertSame('zero', evidenceHubMaturityState([]), 'No projects must produce zero maturity.');

        foreach ([0 => 0, 1 => 3333, 2 => 6667, 3 => 10000] as $completeFields => $expectedBps) {
            $coverage = aggregateEvidenceHubDocumentationCoverage([self::projectDocumentation($completeFields)]);
            phase2AssertSame($expectedBps, $coverage['coverage_bps'], "{$completeFields}/3 coverage BPS changed.");
        }
        foreach ([[3, 1], [1, 0], [2, 0, 0], [3, 2, 0]] as $counts) {
            $projects = [];
            foreach ($counts as $index => $count) {
                $projects[] = self::projectDocumentation($count, $index + 1);
            }
            $coverage = aggregateEvidenceHubDocumentationCoverage($projects);
            $complete = array_sum($counts);
            $expected = count($counts) * 3;
            $expectedBps = match ([$complete, $expected]) {
                [4, 6] => 6667,
                [1, 6] => 1667,
                [2, 9] => 2222,
                [5, 9] => 5556,
            };
            phase2AssertSame($expectedBps, $coverage['coverage_bps'], "{$complete}/{$expected} half-up BPS changed.");
        }

        $distributed = [self::projectDocumentation(2, 1), self::projectDocumentation(1, 2)];
        phase2AssertSame('partial', evidenceHubMaturityState($distributed), 'Distributed complete fields must remain partial.');
        $readyBelowFull = [self::projectDocumentation(3, 1), self::projectDocumentation(1, 2)];
        phase2AssertSame('ready', evidenceHubMaturityState($readyBelowFull), 'One 3/3 project must make maturity ready below 10000 BPS.');
        phase2AssertSame(6667, aggregateEvidenceHubDocumentationCoverage($readyBelowFull)['coverage_bps'], 'Ready must not require 10000 BPS.');

        $invalidStored = self::projectDocumentation(2, 1, ['measurable_outcome' => "bad\u{200B}value"]);
        phase2AssertSame('invalid', $invalidStored['field_evaluations']['measurable_outcome']['storage_validity'], 'Synthetic invalid storage must retain invalid status.');
        phase2AssertSame(2, $invalidStored['complete_evidence_fields'], 'Invalid stored evidence must count as incomplete.');

        $base = self::projectDocumentation(2);
        $invalidProbes = [
            'associative project collection' => static function () use ($base): void { aggregateEvidenceHubDocumentationCoverage(['project_a' => $base]); },
            'negative complete count' => static function () use ($base): void { $project = $base; $project['complete_evidence_fields'] = -1; aggregateEvidenceHubDocumentationCoverage([$project]); },
            'complete count above expected' => static function () use ($base): void { $project = $base; $project['complete_evidence_fields'] = 4; aggregateEvidenceHubDocumentationCoverage([$project]); },
            'seven complete fields across six expected' => static function () use ($base): void { $first = $base; $first['complete_evidence_fields'] = 4; aggregateEvidenceHubDocumentationCoverage([$first, self::projectDocumentation(3, 2)]); },
            'numeric count string' => static function () use ($base): void { $project = $base; $project['complete_evidence_fields'] = '2'; aggregateEvidenceHubDocumentationCoverage([$project]); },
            'declared count differs from evaluations' => static function () use ($base): void { $project = $base; $project['complete_evidence_fields'] = 1; aggregateEvidenceHubDocumentationCoverage([$project]); },
            'expected count changed' => static function () use ($base): void { $project = $base; $project['expected_evidence_fields'] = 4; aggregateEvidenceHubDocumentationCoverage([$project]); },
            'complete flag on 2 of 3' => static function () use ($base): void { $project = $base; $project['project_has_complete_evidence'] = true; aggregateEvidenceHubDocumentationCoverage([$project]); },
            'incomplete flag on 3 of 3' => static function (): void { $project = self::projectDocumentation(3); $project['project_has_complete_evidence'] = false; aggregateEvidenceHubDocumentationCoverage([$project]); },
            'unknown field state' => static function () use ($base): void { $project = $base; $project['field_evaluations']['problem_statement']['evidence_status'] = 'invented'; aggregateEvidenceHubDocumentationCoverage([$project]); },
            'missing field' => static function () use ($base): void { $project = $base; unset($project['field_evaluations']['personal_role']); aggregateEvidenceHubDocumentationCoverage([$project]); },
            'extra evidence field' => static function () use ($base): void { $project = $base; $project['field_evaluations']['unknown_field'] = $project['field_evaluations']['problem_statement']; aggregateEvidenceHubDocumentationCoverage([$project]); },
            'duplicate-shaped field list' => static function () use ($base): void { $project = $base; $project['field_evaluations'] = array_values($project['field_evaluations']); aggregateEvidenceHubDocumentationCoverage([$project]); },
        ];
        foreach ($invalidProbes as $name => $probe) {
            self::assertInvariant($probe, "Aggregation accepted {$name}.");
        }
        self::assertInvariant(static function () use ($base): void { $project = $base; $project['complete_evidence_fields'] = -1; evidenceHubMaturityState([$project]); }, 'Invalid project facts influenced maturity.');
        self::assertInvariant(static fn (): string => evidenceHubMaturityState(['project_a' => $base]), 'Associative project facts influenced maturity.');

        foreach ($fixtures['documentation_aggregations'] as $fixture) {
            if (($fixture['project_count'] ?? null) === 0) {
                continue;
            }
            phase2AssertSame(($fixture['project_count'] ?? 0) * 3, $fixture['expected_fields'] ?? null, "{$fixture['id']} must retain three expected fields per project.");
        }
    }

    /** @param array<string, mixed> $fixtures */
    private static function progress(array $fixtures): void
    {
        $none = summarizeEvidenceHubPortfolioProgress([], 'not_configured');
        phase2AssertSame('unavailable', $none['status'], 'No-project progress status changed.');
        phase2AssertSame(null, $none['recorded_activity_span_days'], 'No-project span must be null.');
        phase2AssertSame(['HISTORY_NOT_TRACKED'], $none['reason_codes'], 'Progress must retain HISTORY_NOT_TRACKED.');

        foreach ($fixtures['portfolio_progress'] as $fixture) {
            if (($fixture['project_count'] ?? null) === 0) {
                continue;
            }
            $first = self::utcEpoch($fixture['first_project_recorded_at']);
            $latest = self::utcEpoch($fixture['latest_project_recorded_at']);
            $projects = [];
            for ($index = 1; $index <= $fixture['project_count']; ++$index) {
                $projects[] = self::projectDocumentation(0, $index, [], $index === 1 ? $first : $latest);
            }
            $progress = summarizeEvidenceHubPortfolioProgress($projects, $fixture['portfolio_publication_state']);
            phase2AssertSame($fixture['project_count'], $progress['project_count'], "{$fixture['id']} project count changed.");
            phase2AssertSame($fixture['recorded_activity_span_days'], $progress['recorded_activity_span_days'], "{$fixture['id']} elapsed-time span changed.");
            phase2AssertSame($fixture['first_project_recorded_at'], $progress['first_project_recorded_at'], "{$fixture['id']} first UTC instant changed.");
            phase2AssertSame($fixture['latest_project_recorded_at'], $progress['latest_project_recorded_at'], "{$fixture['id']} latest UTC instant changed.");
            phase2AssertSame('not_available', $progress['trend_status'], "{$fixture['id']} invented a trend.");
            phase2AssertSame(null, $progress['trend_direction'], "{$fixture['id']} invented a trend direction.");
        }
        phase2AssertSame(0, evidenceHubRecordedActivitySpanDays(self::utcEpoch('2026-01-01T23:30:00Z'), self::utcEpoch('2026-01-02T00:15:00Z')), 'Cross-midnight elapsed span must remain zero.');
        phase2AssertSame(1, evidenceHubRecordedActivitySpanDays(self::utcEpoch('2026-01-01T00:00:00Z'), self::utcEpoch('2026-01-02T00:00:00Z')), 'Exact 24 hours must be one day.');
        self::assertInvariant(static fn (): int => evidenceHubRecordedActivitySpanDays(10, 9), 'Reversed instants were accepted.');
        self::assertInvariant(static fn (): array => summarizeEvidenceHubPortfolioProgress(['project_a' => self::projectDocumentation(0)], 'unpublished'), 'Associative project facts influenced progress.');
        self::assertInvariant(static function (): array { $project = self::projectDocumentation(0); $project['recorded_at_epoch_seconds'] = -1; return summarizeEvidenceHubPortfolioProgress([$project], 'unpublished'); }, 'Negative project instant was accepted.');
        self::assertInvariant(static function (): array { $project = self::projectDocumentation(0); $project['recorded_at_epoch_seconds'] = '1767225600'; return summarizeEvidenceHubPortfolioProgress([$project], 'unpublished'); }, 'String project instant was accepted.');
    }

    /** @param array<string, mixed> $fixtures */
    private static function technologyStorageAndSummary(array $fixtures): void
    {
        $byId = [];
        foreach ($fixtures['technology_storage_cases'] as $fixture) {
            $parsed = parseProjectTechnologiesStorage($fixture['stored_value']);
            phase2AssertSame($fixture['expected_storage_state'], $parsed['storage_state'], "{$fixture['id']} storage state changed.");
            phase2AssertSame($fixture['expected_reason_codes'], $parsed['reason_codes'], "{$fixture['id']} storage reason changed.");
            phase2AssertSame($fixture['expected_labels'], $parsed['labels'], "{$fixture['id']} parsed labels changed.");
            if ($fixture['expected_storage_state'] === 'invalid' && is_string($fixture['stored_value']) && $fixture['stored_value'] !== '') {
                phase2Assert(!str_contains(json_encode($parsed, JSON_THROW_ON_ERROR), $fixture['stored_value']), "{$fixture['id']} exposed raw malformed storage.");
            }
            $byId[$fixture['id']] = $parsed;
        }
        foreach ($fixtures['technology_aggregations'] as $fixture) {
            $facts = [];
            foreach ($fixture['storage_case_ids'] as $caseId) {
                phase2Assert(isset($byId[$caseId]), "{$fixture['id']} references an unknown storage case.");
                $parsed = $byId[$caseId];
                $facts[] = ['storage_state' => $parsed['storage_state'], 'reason_codes' => $parsed['reason_codes'], 'labels' => $parsed['labels']];
            }
            $summary = summarizeEvidenceHubTechnologies($facts, loadEvidenceHubTechnologyTaxonomy('v1'));
            phase2AssertSame($fixture['expected_status'], $summary['status'], "{$fixture['id']} technology status changed.");
            phase2AssertSame($fixture['expected_reason_codes'], $summary['reason_codes'], "{$fixture['id']} technology reason changed.");
            foreach (['technology_occurrence_count', 'mapped_occurrence_count', 'unmapped_occurrence_count', 'distinct_technology_count', 'distinct_mapped_technology_count', 'distinct_unmapped_technology_count', 'invalid_technology_storage_project_count'] as $key) {
                phase2AssertSame($fixture[$key], $summary[$key], "{$fixture['id']} {$key} changed.");
            }
            phase2AssertSame($summary['technology_occurrence_count'], $summary['mapped_occurrence_count'] + $summary['unmapped_occurrence_count'], "{$fixture['id']} occurrence invariant changed.");
            phase2AssertSame($summary['distinct_technology_count'], $summary['distinct_mapped_technology_count'] + $summary['distinct_unmapped_technology_count'], "{$fixture['id']} distinct invariant changed.");
            phase2Assert(!str_contains(json_encode($summary, JSON_THROW_ON_ERROR), '"stored_value"'), "{$fixture['id']} exposed raw technology storage.");
        }
        $taxonomy = loadEvidenceHubTechnologyTaxonomy('v1');
        self::assertInvariant(static fn (): array => summarizeEvidenceHubTechnologies(['project_a' => ['storage_state' => 'valid', 'reason_codes' => [], 'labels' => []]], $taxonomy), 'Associative technology storage facts were accepted.');
        self::assertInvariant(static fn (): array => summarizeEvidenceHubTechnologies([['storage_state' => 'invalid', 'reason_codes' => [], 'labels' => []]], $taxonomy), 'Invalid technology storage lost its non-sensitive reason.');
        self::assertInvariant(static fn (): array => summarizeEvidenceHubTechnologies([['storage_state' => 'valid', 'reason_codes' => [], 'labels' => ['JS', 4]]], $taxonomy), 'Invalid technology label member was accepted.');
    }

    private static function taxonomyAndMapping(): void
    {
        $taxonomy = loadEvidenceHubTechnologyTaxonomy('v1');
        phase2AssertSame('v1', $taxonomy['taxonomy_version'], 'Frozen taxonomy version changed.');
        phase2AssertSame(17, count($taxonomy['entries']), 'Frozen taxonomy must have exactly 17 entries.');
        foreach (['  js  ' => 'tech.javascript', 'Java' => 'tech.java', 'C++' => 'tech.cpp', 'react native' => 'tech.react-native', 'Microsoft   SQL Server' => 'tech.sql-server', 'sql' => 'tech.sql', 'JQUERY' => 'tech.jquery'] as $label => $id) {
            $mapping = mapEvidenceHubTechnologyLabel($label, $taxonomy);
            phase2AssertSame('mapped', $mapping['mapping_state'], "{$label} must map exactly.");
            phase2AssertSame($id, $mapping['canonical_id'], "{$label} mapped to the wrong canonical ID.");
        }
        $jquery = mapEvidenceHubTechnologyLabel('jQuery', $taxonomy);
        phase2AssertSame('library', $jquery['category'], 'jQuery must remain a library.');
        foreach (['JavaScriptish', 'ava', 'SQLServer', "Јava"] as $nearMiss) {
            $mapping = mapEvidenceHubTechnologyLabel($nearMiss, $taxonomy);
            phase2AssertSame('unmapped', $mapping['mapping_state'], "{$nearMiss} must not fuzzy-map.");
            phase2AssertSame(null, $mapping['canonical_id'], "{$nearMiss} must not receive a canonical ID.");
        }
        phase2AssertSame(null, mapEvidenceHubTechnologyLabel(" \t\r\n ", $taxonomy), 'Empty normalized labels must not become occurrences.');
        $source = file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-taxonomy-v1.json');
        phase2Assert(is_string($source), 'Frozen taxonomy source is unreadable.');
        self::assertTaxonomyInvariant(static fn (): array => parseEvidenceHubTechnologyTaxonomy(str_replace('"JS"', '"Java"', $source), 'v1'), 'A normalized taxonomy alias collision was accepted.');
    }

    private static function taxonomyV2(): void
    {
        $v1 = loadEvidenceHubTechnologyTaxonomy('v1');
        $v2 = loadEvidenceHubTechnologyTaxonomy('v2');
        phase2AssertSame('v1', $v1['taxonomy_version'], 'Frozen v1 taxonomy did not load independently.');
        phase2AssertSame(17, count($v1['entries']), 'Frozen v1 entry count changed.');
        phase2AssertSame('v2', $v2['taxonomy_version'], 'Frozen v2 taxonomy did not load independently.');
        phase2AssertSame(31, count($v2['entries']), 'V2 entry count is wrong.');
        phase2AssertSame($v1['entries'], array_slice($v2['entries'], 0, 17), 'V2 changed a frozen v1 canonical entry.');
        phase2AssertSame(
            ['database', 'framework', 'language', 'library', 'platform', 'runtime', 'stylesheet', 'technique', 'tool'],
            $v2['category_order'],
            'V2 category ordering changed.'
        );
        foreach ([
            'Css' => ['tech.css', 'CSS', 'stylesheet'],
            'Dax Equation' => ['tech.dax', 'DAX', 'language'],
            'Git' => ['tech.git', 'Git', 'tool'],
            'Github' => ['tech.github', 'GitHub', 'platform'],
            'Joblib' => ['tech.joblib', 'Joblib', 'library'],
            'LSA' => ['tech.lsa', 'Latent Semantic Analysis (LSA)', 'technique'],
            'MLR' => ['tech.mlr', 'Multiple Linear Regression (MLR)', 'technique'],
            'Matplotlib' => ['tech.matplotlib', 'Matplotlib', 'library'],
            'NumPy' => ['tech.numpy', 'NumPy', 'library'],
            'Pandas' => ['tech.pandas', 'Pandas', 'library'],
            'Power bi' => ['tech.power-bi', 'Power BI', 'platform'],
            'Scikit-learn' => ['tech.scikit-learn', 'Scikit-learn', 'library'],
            'Seaborn' => ['tech.seaborn', 'Seaborn', 'library'],
            'TF-IDF' => ['tech.tf-idf', 'TF-IDF', 'technique'],
        ] as $label => [$id, $display, $category]) {
            $mapping = mapEvidenceHubTechnologyLabel($label, $v2);
            phase2AssertSame([$id, $display, $category], [$mapping['canonical_id'], $mapping['display_name'], $mapping['category']], "{$label} did not resolve to its reviewed v2 entry.");
            phase2AssertSame(null, mapEvidenceHubTechnologyLabel($label, $v1)['canonical_id'], "{$label} unexpectedly mapped in frozen v1.");
        }
        foreach (['php' => 'tech.php', 'Docker' => 'tech.docker', 'Js' => 'tech.javascript', 'Python' => 'tech.python', 'MySql' => 'tech.mysql'] as $label => $id) {
            phase2AssertSame($id, mapEvidenceHubTechnologyLabel($label, $v1)['canonical_id'], "Existing {$label} v1 mapping changed.");
            phase2AssertSame($id, mapEvidenceHubTechnologyLabel($label, $v2)['canonical_id'], "Existing {$label} v2 mapping changed.");
        }
        foreach (['Linear Regression', 'Pandas, NumPy, Scikit-learn, Matplotlib, Seaborn, Joblib', 'Future Unknown', 'JavaScriptish'] as $unknown) {
            phase2AssertSame('unmapped', mapEvidenceHubTechnologyLabel($unknown, $v2)['mapping_state'], "{$unknown} was guessed or split.");
        }
        phase2AssertSame('tech.mlr', mapEvidenceHubTechnologyLabel('Multiple Linear Regression', $v2)['canonical_id'], 'Unambiguous MLR alias failed.');
        phase2AssertSame('tech.github', mapEvidenceHubTechnologyLabel("  GITHUB\t", $v2)['canonical_id'], 'Whitespace and case normalization failed.');
        phase2AssertSame(normalizeEvidenceHubTechnologyLabel("Cafe\u{301}"), normalizeEvidenceHubTechnologyLabel('Café'), 'NFC comparison behavior changed.');
        $source = file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-taxonomy-v2.json');
        phase2Assert(is_string($source), 'V2 taxonomy source is unreadable.');
        phase2AssertSame(parseEvidenceHubTechnologyTaxonomy($source, 'v2'), parseEvidenceHubTechnologyTaxonomy(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $source)), 'v2'), 'V2 JSON differs across LF and CRLF loading.');
        $labels = [
            18 => ['Python', 'Pandas, NumPy, Scikit-learn, Matplotlib, Seaborn, Joblib'],
            19 => ['Python', 'Scikit-learn', 'Pandas', 'NumPy', 'Matplotlib', 'Seaborn', 'Joblib', 'TF-IDF', 'LSA', 'Linear Regression'],
            20 => ['php', 'Docker', 'Css', 'Js', 'Git', 'Github'],
            21 => ['Power bi', 'MySql', 'Dax Equation'],
        ];
        $facts = [];
        $technologyFacts = [];
        foreach ($labels as $projectId => $items) {
            $facts[] = self::projectDocumentation($projectId === 20 ? 3 : 0, $projectId, ['technologies' => $items]);
            $technologyFacts[] = ['storage_state' => 'valid', 'reason_codes' => [], 'labels' => $items];
        }
        $coverage = aggregateEvidenceHubDocumentationCoverage($facts);
        phase2AssertSame([3, 12, 2500, 1], [$coverage['complete_evidence_field_count'], $coverage['expected_evidence_field_count'], $coverage['coverage_bps'], $coverage['projects_with_complete_evidence']], 'Technology v2 changed Evidence completion.');
        $summary = summarizeEvidenceHubTechnologies($technologyFacts, $v2);
        phase2AssertSame([21, 18, 2], [$summary['technology_occurrence_count'], $summary['distinct_mapped_technology_count'], $summary['distinct_unmapped_technology_count']], 'Pre-edit v2 technology totals are wrong.');
        $overview = evidenceHubOwnerPageTechnologies($technologyFacts);
        phase2AssertSame(['mapped_technology_count' => 18, 'unmapped_technology_count' => 2, 'unmapped_project_count' => 2], $overview['overview'], 'V2 page model totals are wrong.');
        phase2AssertSame(array_values(array_filter($v2['category_order'], static fn (string $category): bool => isset($overview['mapped'][$category]))), array_keys($overview['mapped']), 'V2 page-model category order is unstable.');
        phase2AssertSame(['Unmapped technology 1', 'Unmapped technology 2'], array_column($overview['unmapped'], 'label'), 'V2 unknown labels exposed raw values.');
        phase2AssertSame(1, evidenceHubOwnerPageProgress(4, 1, [100])['complete_project_count'], 'Technology v2 changed portfolio progress.');
    }

    private static function taxonomyV3(): void
    {
        $v2 = loadEvidenceHubTechnologyTaxonomy('v2');
        $v3 = loadEvidenceHubTechnologyTaxonomy('v3');
        phase2AssertSame('v3', $v3['taxonomy_version'], 'V3 taxonomy did not load.');
        phase2AssertSame(37, count($v3['entries']), 'V3 entry count is wrong.');
        phase2AssertSame($v2['entries'], array_slice($v3['entries'], 0, 31), 'V3 changed a frozen v2 entry.');
        phase2AssertSame($v2['category_order'], $v3['category_order'], 'V3 category order changed.');
        foreach ([
            'FastAPI' => ['tech.fastapi', 'FastAPI', 'framework'],
            'pgvector' => ['tech.pgvector', 'pgvector', 'database'],
            'SentenceTransformers' => ['tech.sentence-transformers', 'SentenceTransformers', 'library'],
            'Redis' => ['tech.redis', 'Redis', 'database'],
            'Celery' => ['tech.celery', 'Celery', 'framework'],
            'Groq' => ['tech.groq', 'Groq', 'platform'],
        ] as $label => $expected) {
            $mapping = mapEvidenceHubTechnologyLabel($label, $v3);
            phase2AssertSame($expected, [$mapping['canonical_id'], $mapping['display_name'], $mapping['category']], "V3 {$label} mapping changed.");
            phase2AssertSame('unmapped', mapEvidenceHubTechnologyLabel($label, $v2)['mapping_state'], "V2 unexpectedly knows {$label}.");
            phase2AssertSame($mapping['canonical_id'], mapEvidenceHubTechnologyLabel(strtoupper($label), $v3)['canonical_id'], "V3 case alias failed for {$label}.");
        }
        foreach (['Fast API' => 'tech.fastapi', 'pg vector' => 'tech.pgvector', 'Sentence Transformers' => 'tech.sentence-transformers'] as $alias => $id) {
            phase2AssertSame($id, mapEvidenceHubTechnologyLabel($alias, $v3)['canonical_id'], "V3 spacing alias failed for {$alias}.");
        }
        foreach (['GitHub Actions', 'Graph RAG', 'Linear Regression', 'Pandas, NumPy', 'Future Unknown'] as $unknown) {
            phase2AssertSame('unmapped', mapEvidenceHubTechnologyLabel($unknown, $v3)['mapping_state'], "V3 guessed {$unknown}.");
        }
        $project18 = ['Python', 'Pandas', 'NumPy', 'Scikit-learn', 'Matplotlib', 'Seaborn', 'Joblib'];
        $project19 = ['Python', 'FastAPI', 'PostgreSQL', 'pgvector', 'SentenceTransformers', 'Redis', 'Celery', 'Groq', 'Docker'];
        $project20 = ['php', 'Docker', 'Css', 'Js', 'Git', 'Github'];
        $project21 = ['Power bi', 'MySql', 'Dax Equation'];
        $facts = array_map(static fn (array $labels): array => ['storage_state' => 'valid', 'reason_codes' => [], 'labels' => $labels], [$project18, $project19, $project20, $project21]);
        $summary = summarizeEvidenceHubTechnologies($facts, $v3);
        phase2AssertSame([25, 23, 0], [$summary['technology_occurrence_count'], $summary['distinct_mapped_technology_count'], $summary['distinct_unmapped_technology_count']], 'Future Project 19 technology totals changed.');
        $page = evidenceHubOwnerPageTechnologies($facts);
        phase2AssertSame([], $page['unmapped'], 'V3 future portfolio exposed unknown labels.');
        phase2AssertSame(array_values(array_filter($v3['category_order'], static fn (string $category): bool => isset($page['mapped'][$category]))), array_keys($page['mapped']), 'V3 category order is unstable.');
        $unknownPage = evidenceHubOwnerPageTechnologies([['storage_state' => 'valid', 'reason_codes' => [], 'labels' => ['Future Unknown']]]);
        phase2AssertSame(['Unmapped technology 1'], array_column($unknownPage['unmapped'], 'label'), 'V3 unknown label is not privacy-safe.');
    }

    private static function ownerScopeIntegration(): void
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT NULL, is_published INTEGER NOT NULL); CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL);');
        $database->exec("INSERT INTO portfolios (id, owner_user_id, public_slug, is_published) VALUES (10, 1, 'owner-a', 1), (20, 2, 'owner-b', 0);");
        $database->prepare('INSERT INTO projects (id, portfolio_id, problem_statement, personal_role, measurable_outcome, technologies, created_at) VALUES (100, 10, :problem, :role, :outcome, :technologies, :created_at)')->execute(['problem' => self::completeText('problem_statement'), 'role' => self::completeText('personal_role'), 'outcome' => self::completeText('measurable_outcome'), 'technologies' => '["JS", "jQuery"]', 'created_at' => '2026-01-01T00:00:00Z']);
        $database->prepare('INSERT INTO projects (id, portfolio_id, problem_statement, personal_role, measurable_outcome, technologies, created_at) VALUES (200, 20, :problem, NULL, NULL, :technologies, :created_at)')->execute(['problem' => 'PRIVATE_EVIDENCE_MARKER', 'technologies' => '["NebulaTool"]', 'created_at' => '2026-05-01T00:00:00Z']);
        $ownerA = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $ownerB = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 20);
        $factsA = loadAuthorizedEvidenceHubProjectFacts($database, $ownerA);
        $factsB = loadAuthorizedEvidenceHubProjectFacts($database, $ownerB);
        phase2AssertSame(1, count($factsA), 'Owner A facts crossed tenant scope.');
        phase2AssertSame(1, count($factsB), 'Owner B facts crossed tenant scope.');
        phase2AssertSame(self::utcEpoch('2026-01-01T00:00:00Z'), $factsA[0]['recorded_at_epoch_seconds'], 'SQLite test boundary did not provide a UTC instant.');
        $resultA = buildAuthorizedEvidenceHubOwnerCore($database, $ownerA);
        phase2AssertSame(1, $resultA['documentation_coverage']['project_count'], 'Owner B changed Owner A coverage.');
        phase2AssertSame('ready', $resultA['maturity']['state'], 'Owner A complete project must make maturity ready.');
        phase2AssertSame('published', $resultA['portfolio_progress']['portfolio_publication_state'], 'Owner A publication fact changed.');
        phase2AssertSame(2, $resultA['technology_evidence_map']['technology_occurrence_count'], 'Owner A technology occurrences changed.');
        phase2Assert(!str_contains(json_encode($resultA, JSON_THROW_ON_ERROR), 'PRIVATE_EVIDENCE_MARKER'), 'Cross-tenant private evidence entered Owner A aggregation.');
        phase2Assert(!array_key_exists('project_ref', $resultA), 'Client-shaped Owner core result exposed a project reference.');
        $database->exec("UPDATE projects SET created_at = '2026-01-01T03:30:00+03:30' WHERE id = 100");
        $previousTimezone = date_default_timezone_get();
        try {
            date_default_timezone_set('Pacific/Auckland');
            $offsetFacts = loadAuthorizedEvidenceHubProjectFacts($database, $ownerA);
        } finally {
            date_default_timezone_set($previousTimezone);
        }
        phase2AssertSame(self::utcEpoch('2026-01-01T00:00:00Z'), $offsetFacts[0]['recorded_at_epoch_seconds'], 'SQLite explicit offset did not preserve its absolute instant.');
        $database->exec("INSERT INTO projects (id, portfolio_id, created_at) VALUES (101, 10, '2026-01-02 00:00:00')");
        self::assertRuntimeFailure(fn (): array => loadAuthorizedEvidenceHubProjectFacts($database, $ownerA), 'Timezone-less SQLite storage was relabeled as UTC.');
    }

    private static function staticNonDisclosure(): void
    {
        foreach (['includes/public_lifecycle.php', 'public_projects_json.php', 'public/p_projects.php', 'portfolio.js'] as $source) {
            $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $source);
            phase2Assert(is_string($contents), "{$source} is unreadable.");
            phase2Assert(!str_contains($contents, 'evidence_hub_owner_core'), "{$source} exposes the Owner Evidence Hub core.");
        }
        $core = file_get_contents(PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_core.php');
        phase2Assert(is_string($core) && !preg_match('/\$_(?:GET|POST|REQUEST|COOKIE|SERVER)/', $core), 'Owner core must not accept request authority.');
    }

    /** @param array<string, mixed> $overrides */
    private static function projectDocumentation(int $completeFields, int $projectRef = 1, array $overrides = [], int $recordedAtEpochSeconds = 1767225600): array
    {
        $project = ['project_ref' => $projectRef, 'recorded_at_epoch_seconds' => $recordedAtEpochSeconds, 'technology_storage_state' => 'valid', 'technology_storage_reason_codes' => [], 'technologies' => []];
        foreach (evidenceTextFieldNames() as $index => $field) {
            $project[$field] = $index < $completeFields ? self::completeText($field) : null;
        }
        foreach ($overrides as $field => $value) {
            $project[$field] = $value;
        }

        return evaluateEvidenceHubProjectDocumentation($project);
    }

    private static function completeText(string $field): string
    {
        return match ($field) {
            'problem_statement' => 'architecture01 delivery02 structure03 evidence04 outcomes05 planning06 validation07 ownership08',
            'personal_role' => 'ownership01 delivery02 review03 testing04 support05',
            'measurable_outcome' => 'reduced50 latency40 requests30 failures20',
            default => throw new LogicException('Unsupported test evidence field.'),
        };
    }

    private static function utcEpoch(string $timestamp): int
    {
        return (new DateTimeImmutable($timestamp))->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
    }

    private static function assertInvariant(callable $probe, string $message): void
    {
        try {
            $probe();
        } catch (EvidenceHubAggregationInvariantException) {
            return;
        }
        throw new RuntimeException($message);
    }

    private static function assertTaxonomyInvariant(callable $probe, string $message): void
    {
        try {
            $probe();
        } catch (EvidenceHubTaxonomyException) {
            return;
        }
        throw new RuntimeException($message);
    }

    private static function assertRuntimeFailure(callable $probe, string $message): void
    {
        try {
            $probe();
        } catch (RuntimeException) {
            return;
        }
        throw new RuntimeException($message);
    }

    /** @return array<string, mixed> */
    private static function fixtures(): array
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-hub-golden-fixtures.json');
        if (!is_string($contents)) {
            throw new RuntimeException('Evidence Hub aggregation fixtures are unreadable.');
        }
        $fixtures = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($fixtures)) {
            throw new RuntimeException('Evidence Hub aggregation fixtures are invalid.');
        }

        return $fixtures;
    }
}
