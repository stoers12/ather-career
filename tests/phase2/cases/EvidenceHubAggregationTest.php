<?php

declare(strict_types=1);

final class EvidenceHubAggregationTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_core.php';
        requireEvidenceTextUnicodeRuntime();
        self::documentationAndMaturity();
        self::progress();
        self::taxonomyAndMapping();
        if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::ownerScopeIntegration();
        }
        self::staticNonDisclosure();
    }

    private static function documentationAndMaturity(): void
    {
        $none = aggregateEvidenceHubDocumentationCoverage([]);
        phase2AssertSame('unavailable', $none['status'], 'No-project coverage status changed.');
        phase2AssertSame(null, $none['coverage_bps'], 'No-project coverage denominator must remain null.');
        phase2AssertSame(['NO_PROJECTS'], $none['reason_codes'], 'No-project coverage reason changed.');
        phase2AssertSame('zero', evidenceHubMaturityState([]), 'No projects must produce zero maturity.');

        foreach ([0 => 0, 1 => 3333, 2 => 6667, 3 => 10000] as $completeFields => $expectedBps) {
            $projects = [self::projectDocumentation($completeFields)];
            $coverage = aggregateEvidenceHubDocumentationCoverage($projects);
            phase2AssertSame($expectedBps, $coverage['coverage_bps'], "{$completeFields}/3 coverage BPS changed.");
            phase2AssertSame($completeFields === 3 ? 'ready' : 'needs_attention', $coverage['status'], 'Coverage status changed.');
        }

        $fourOfSix = [self::projectDocumentation(3), self::projectDocumentation(1, 2)];
        $coverage = aggregateEvidenceHubDocumentationCoverage($fourOfSix);
        phase2AssertSame(6667, $coverage['coverage_bps'], '4/6 must use half-up integer BPS.');
        phase2AssertSame('ready', evidenceHubMaturityState($fourOfSix), 'One complete project must make maturity ready.');

        $partial = [self::projectDocumentation(1), self::projectDocumentation(2, 2)];
        phase2AssertSame('partial', evidenceHubMaturityState($partial), 'Individual complete fields must not make maturity ready.');
        $invalid = self::projectDocumentation(2, 3, ['measurable_outcome' => "bad\u{200B}value"]);
        phase2AssertSame('invalid', $invalid['field_evaluations']['measurable_outcome']['storage_validity'], 'Synthetic corrupted storage must retain invalid state.');
        phase2AssertSame(2, $invalid['complete_evidence_fields'], 'Invalid stored evidence must count as incomplete.');
    }

    private static function progress(): void
    {
        $none = summarizeEvidenceHubPortfolioProgress([], 'not_configured');
        phase2AssertSame('unavailable', $none['status'], 'No-project progress status changed.');
        phase2AssertSame(null, $none['recorded_activity_span_days'], 'No-project span must be null.');
        phase2AssertSame(['HISTORY_NOT_TRACKED'], $none['reason_codes'], 'Progress must retain HISTORY_NOT_TRACKED.');

        $sameDay = summarizeEvidenceHubPortfolioProgress([self::projectDocumentation(0, 1)], 'unpublished');
        phase2AssertSame(0, $sameDay['recorded_activity_span_days'], 'A single recorded project must have a zero-day span.');
        phase2AssertSame('not_available', $sameDay['trend_status'], 'Trend status must remain unavailable.');
        phase2AssertSame(null, $sameDay['trend_direction'], 'Trend direction must remain null.');

        $dated = [
            self::projectDocumentation(0, 1, [], '2026-01-01 00:00:00'),
            self::projectDocumentation(3, 2, [], '2026-03-01 00:00:00'),
            self::projectDocumentation(0, 3, [], '2026-05-01 00:00:00'),
        ];
        $progress = summarizeEvidenceHubPortfolioProgress($dated, 'published');
        phase2AssertSame('2026-01-01T00:00:00Z', $progress['first_project_recorded_at'], 'First recorded timestamp must be formatted in UTC.');
        phase2AssertSame('2026-05-01T00:00:00Z', $progress['latest_project_recorded_at'], 'Latest recorded timestamp must be formatted in UTC.');
        phase2AssertSame(120, $progress['recorded_activity_span_days'], 'Recorded activity span changed.');
        phase2AssertSame(1, $progress['projects_with_complete_evidence'], 'Complete-project progress count changed.');
        phase2AssertSame('published', $progress['portfolio_publication_state'], 'Publication fact must remain independent from documentation.');
    }

    private static function taxonomyAndMapping(): void
    {
        $taxonomy = loadEvidenceHubTechnologyTaxonomy();
        phase2AssertSame('v1', $taxonomy['taxonomy_version'], 'Frozen taxonomy version changed.');
        phase2AssertSame(17, count($taxonomy['entries']), 'Frozen taxonomy must have exactly 17 entries.');

        foreach ([
            '  js  ' => 'tech.javascript',
            'Java' => 'tech.java',
            'C++' => 'tech.cpp',
            'react native' => 'tech.react-native',
            'Microsoft   SQL Server' => 'tech.sql-server',
            'sql' => 'tech.sql',
            'JQUERY' => 'tech.jquery',
        ] as $label => $id) {
            $mapping = mapEvidenceHubTechnologyLabel($label, $taxonomy);
            phase2AssertSame('mapped', $mapping['mapping_state'], "{$label} must map exactly.");
            phase2AssertSame($id, $mapping['canonical_id'], "{$label} mapped to the wrong canonical ID.");
        }
        $jquery = mapEvidenceHubTechnologyLabel('jQuery', $taxonomy);
        phase2AssertSame('library', $jquery['category'], 'jQuery must remain a library.');
        phase2AssertSame('tech.jquery', $jquery['canonical_id'], 'jQuery must remain independent from JavaScript.');
        $jqueryEntry = array_values(array_filter($taxonomy['entries'], static fn (array $entry): bool => $entry['technology_id'] === 'tech.jquery'));
        phase2AssertSame(false, $jqueryEntry[0]['deprecated'], 'jQuery must not be globally deprecated.');
        phase2AssertSame(false, $jqueryEntry[0]['merged'], 'jQuery must not be globally merged.');
        foreach (['JavaScriptish', 'ava', 'SQLServer', "Јava"] as $nearMiss) {
            $mapping = mapEvidenceHubTechnologyLabel($nearMiss, $taxonomy);
            phase2AssertSame('unmapped', $mapping['mapping_state'], "{$nearMiss} must not fuzzy-map.");
            phase2AssertSame(null, $mapping['canonical_id'], "{$nearMiss} must not receive a canonical ID.");
        }
        phase2AssertSame(null, mapEvidenceHubTechnologyLabel(" \t\r\n ", $taxonomy), 'Empty normalized labels must not become invented technologies.');
        phase2AssertSame(normalizeEvidenceHubTechnologyLabel("React\u{00A0}Native"), normalizeEvidenceHubTechnologyLabel('  react native  '), 'NFC/whitespace/case normalization changed.');

        $source = file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-taxonomy-v1.json');
        phase2Assert(is_string($source), 'Frozen taxonomy source is unreadable.');
        try {
            parseEvidenceHubTechnologyTaxonomy(str_replace('"JS"', '"Java"', $source));
            throw new RuntimeException('Taxonomy alias collision was accepted.');
        } catch (EvidenceHubTaxonomyException) {
        }

        $summary = summarizeEvidenceHubTechnologies([['JS', 'jQuery'], ['NebulaTool', 'SQL']], $taxonomy);
        phase2AssertSame('needs_attention', $summary['status'], 'Unmapped technology must need attention.');
        phase2AssertSame(['TECHNOLOGY_UNMAPPED'], $summary['reason_codes'], 'Unmapped technology reason changed.');
        phase2AssertSame(['tech.javascript', 'tech.jquery', 'tech.sql', null], array_column($summary['mappings'], 'canonical_id'), 'Technology mapping order changed.');
        $aliasOccurrences = summarizeEvidenceHubTechnologies([['JS'], ['JavaScript']], $taxonomy);
        phase2AssertSame(['tech.javascript', 'tech.javascript'], array_column($aliasOccurrences['mappings'], 'canonical_id'), 'Valid aliases must remain separate technology occurrences.');
    }

    private static function ownerScopeIntegration(): void
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec(
            'CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT NULL, is_published INTEGER NOT NULL);
             CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL);'
        );
        $database->exec("INSERT INTO portfolios (id, owner_user_id, public_slug, is_published) VALUES (10, 1, 'owner-a', 1), (20, 2, 'owner-b', 0);");
        $aProblem = self::completeText('problem_statement');
        $aRole = self::completeText('personal_role');
        $aOutcome = self::completeText('measurable_outcome');
        $database->prepare('INSERT INTO projects (id, portfolio_id, problem_statement, personal_role, measurable_outcome, technologies, created_at) VALUES (100, 10, :problem, :role, :outcome, :technologies, :created_at)')->execute([
            'problem' => $aProblem, 'role' => $aRole, 'outcome' => $aOutcome, 'technologies' => '["JS", "jQuery"]', 'created_at' => '2026-01-01 00:00:00',
        ]);
        $database->prepare('INSERT INTO projects (id, portfolio_id, problem_statement, personal_role, measurable_outcome, technologies, created_at) VALUES (200, 20, :problem, NULL, NULL, :technologies, :created_at)')->execute([
            'problem' => 'PRIVATE_EVIDENCE_MARKER', 'technologies' => '["NebulaTool"]', 'created_at' => '2026-05-01 00:00:00',
        ]);
        $ownerA = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $ownerB = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 20);
        $factsA = loadAuthorizedEvidenceHubProjectFacts($database, $ownerA);
        $factsB = loadAuthorizedEvidenceHubProjectFacts($database, $ownerB);
        phase2AssertSame(1, count($factsA), 'Owner A facts crossed tenant scope.');
        phase2AssertSame(100, $factsA[0]['project_ref'], 'Owner A received the wrong project.');
        phase2AssertSame(1, count($factsB), 'Owner B facts crossed tenant scope.');

        $resultA = buildAuthorizedEvidenceHubOwnerCore($database, $ownerA);
        phase2AssertSame(1, $resultA['documentation_coverage']['project_count'], 'Owner B changed Owner A coverage.');
        phase2AssertSame('ready', $resultA['maturity']['state'], 'Owner A complete project must make maturity ready.');
        phase2AssertSame('published', $resultA['portfolio_progress']['portfolio_publication_state'], 'Owner A publication fact changed.');
        phase2Assert(!str_contains(json_encode($resultA, JSON_THROW_ON_ERROR), 'PRIVATE_EVIDENCE_MARKER'), 'Cross-tenant private evidence entered Owner A aggregation.');
        phase2Assert(!array_key_exists('project_ref', $resultA), 'Client-shaped Owner core result exposed a project reference.');
    }

    private static function staticNonDisclosure(): void
    {
        $publicSources = ['includes/public_lifecycle.php', 'public_projects_json.php', 'public/p_projects.php', 'portfolio.js'];
        foreach ($publicSources as $source) {
            $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $source);
            phase2Assert(is_string($contents), "{$source} is unreadable.");
            phase2Assert(!str_contains($contents, 'evidence_hub_owner_core'), "{$source} exposes the Owner Evidence Hub core.");
        }
        $core = file_get_contents(PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_core.php');
        phase2Assert(is_string($core) && !preg_match('/\$_(?:GET|POST|REQUEST|COOKIE|SERVER)/', $core), 'Owner core must not accept request authority.');
        foreach (['Dockerfile', 'Dockerfile.production'] as $imageDefinition) {
            $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $imageDefinition);
            phase2Assert(is_string($contents) && str_contains($contents, 'COPY .'), "{$imageDefinition} does not include the frozen taxonomy source.");
        }
    }

    /** @param array<string, string> $overrides */
    private static function projectDocumentation(int $completeFields, int $projectRef = 1, array $overrides = [], string $createdAt = '2026-01-01 00:00:00'): array
    {
        $fields = evidenceTextFieldNames();
        $project = [
            'project_ref' => $projectRef,
            'created_at' => $createdAt,
            'technologies' => [],
        ];
        foreach ($fields as $index => $field) {
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
}
