<?php

declare(strict_types=1);

final class EvidenceHubContractV7Test
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_core.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_model.php';
        self::schema();
        self::summaries();
        self::rejections();
        self::earlierVersions();
        self::authorizedPaths();
        self::rendering();
    }

    private static function schema(): void
    {
        $read = static fn (string $version): array => json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/contracts/evidence-hub-contract-' . $version . '.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $v6 = $read('v6');
        $v7 = $read('v7');
        phase2AssertSame(str_replace('contract-v6', 'contract-v7', $v6['$id']), $v7['$id'], 'V7 schema ID is wrong.');
        phase2AssertSame('evidence-hub-contract-v7', $v7['properties']['contract_id']['const'], 'V7 contract ID is wrong.');
        phase2AssertSame('7.0.0', $v7['properties']['schema_version']['const'], 'V7 schema version is wrong.');
        phase2AssertSame(['no_projects', 'incomplete_first_project', 'single_project', 'emerging_patterns'], $v7['$defs']['portfolio_progress']['properties']['stage']['enum'], 'The contract must never claim trend readiness.');
        phase2AssertSame(['BELOW_RECOMMENDED_OBSERVATIONS', 'EVIDENCE_INCOMPLETE', 'NO_PROJECTS', 'SINGLE_PROJECT'], $v7['$defs']['confidence_basis_code']['enum'], 'Confidence basis codes must be closed.');
        $detail = $v7['$defs']['documentation_coverage']['properties']['evidence_confidence_detail'];
        phase2AssertSame(false, $detail['additionalProperties'], 'Confidence detail must be a closed object.');
        phase2AssertSame(['observations', 'recommended_observations', 'basis_codes'], $detail['required'], 'Confidence detail fields are wrong.');
        phase2AssertSame(3, $detail['properties']['recommended_observations']['const'], 'Recommended observations must remain three.');
        phase2AssertSame(true, $detail['properties']['basis_codes']['uniqueItems'], 'Basis codes must be unique.');
        $restored = $v7;
        $restored['$id'] = $v6['$id'];
        $restored['properties']['contract_id']['const'] = $v6['properties']['contract_id']['const'];
        $restored['properties']['schema_version']['const'] = $v6['properties']['schema_version']['const'];
        $core = self::core([1]);
        $payload = mapEvidenceHubContractV7($core, []);
        foreach (['documentation_coverage' => ['evidence_confidence_detail'], 'technology_metric' => ['mapping_coverage_bps'], 'portfolio_progress' => ['stage', 'published_project_count']] as $metric => $fields) {
            $payloadKey = $metric === 'technology_metric' ? 'technology_evidence_map' : $metric;
            phase2AssertSame($v7['$defs'][$metric]['required'], array_keys($payload['metrics'][$payloadKey]), 'V7 field selection differs from schema order.');
            phase2AssertSame($v7['$defs'][$metric]['required'], array_keys($v7['$defs'][$metric]['properties']), 'V7 property and required order differ.');
            foreach ($fields as $field) {
                unset($restored['$defs'][$metric]['properties'][$field]);
                $restored['$defs'][$metric]['required'] = array_values(array_diff($restored['$defs'][$metric]['required'], [$field]));
            }
        }
        unset($restored['$defs']['confidence_basis_code']);
        phase2AssertSame($v6, $restored, 'V7 changed something beyond its identity, four fields, and basis-code definition.');
    }

    private static function summaries(): void
    {
        phase2AssertSame(3, EVIDENCE_HUB_RECOMMENDED_OBSERVATIONS, 'The shared observation threshold changed.');
        foreach ([
            [[], ['NO_PROJECTS']],
            [[3], ['SINGLE_PROJECT']],
            [[1], ['SINGLE_PROJECT', 'EVIDENCE_INCOMPLETE']],
            [[3, 3], ['BELOW_RECOMMENDED_OBSERVATIONS']],
            [[3, 1], ['EVIDENCE_INCOMPLETE', 'BELOW_RECOMMENDED_OBSERVATIONS']],
            [[3, 3, 3], []],
            [[3, 3, 1], ['EVIDENCE_INCOMPLETE']],
        ] as [$counts, $codes]) {
            sort($codes, SORT_STRING);
            $metric = mapEvidenceHubContractV7(self::core($counts), [])['metrics']['documentation_coverage'];
            phase2AssertSame(['observations' => count($counts), 'recommended_observations' => 3, 'basis_codes' => $codes], $metric['evidence_confidence_detail'], 'Confidence basis rules or byte-order sorting are wrong.');
            phase2AssertSame($counts === [] ? 'unavailable' : (min($counts) === 3 ? 'high' : 'low'), $metric['evidence_confidence'], 'The existing confidence string changed.');
        }
        foreach ([[[], 'no_projects'], [[0], 'incomplete_first_project'], [[3], 'single_project'], [[3, 3], 'emerging_patterns'], [[3, 3, 3], 'emerging_patterns']] as [$counts, $expected]) {
            $metric = mapEvidenceHubContractV7(self::core($counts), [])['metrics']['portfolio_progress'];
            phase2AssertSame($expected, $metric['stage'], 'V7 stage selection is wrong.');
            phase2AssertSame('not_available', $metric['trend_status'], 'The contract must not make trend claims.');
        }
        $taxonomy = loadEvidenceHubTechnologyTaxonomy('v5');
        $ownerMappings = [evidenceHubTechnologyLabelDigest('vue 3') => 'tech.vue'];
        foreach ([
            [[], null],
            [['HTML', 'vue 3', 'Private Future Tool'], 6667],
            [[...array_fill(0, 14, 'HTML'), ...array_fill(0, 13, 'vue 3'), ...array_fill(0, 3, 'Private Future Tool')], 9000],
            [['Private Future Tool'], 0],
            [['HTML'], 10000],
        ] as [$labels, $expected]) {
            $core = self::core([1]);
            $core['technology_evidence_map'] = summarizeEvidenceHubTechnologies([['storage_state' => 'valid', 'reason_codes' => [], 'labels' => $labels]], $taxonomy, $ownerMappings);
            phase2AssertSame($expected, mapEvidenceHubContractV7($core, [])['metrics']['technology_evidence_map']['mapping_coverage_bps'], 'Mapping coverage must include Owner mappings and use half-up rounding.');
        }
        $core = buildEvidenceHubOwnerCoreFromEvaluatedProjects([], 'unpublished');
        phase2AssertSame(null, $core['portfolio_progress']['published_project_count'], 'Missing publication data must remain unknown.');
        self::reject(static fn (): array => mapEvidenceHubContractV7($core, []), 'V7 accepted a missing published count.');
        foreach ([[-1, 1], [2, 1]] as [$published, $total]) {
            try {
                self::core(array_fill(0, $total, 0), $published);
                throw new RuntimeException('The core accepted an impossible published count.');
            } catch (InvalidArgumentException) {
                // Invalid supplied counts cannot enter a summary.
            }
        }
    }

    private static function rejections(): void
    {
        $valid = self::core([1]);
        foreach ([
            ['portfolio_progress', 'stage', 'trend_ready'], ['portfolio_progress', 'stage', 'unknown'],
            ['portfolio_progress', 'stage', null], ['portfolio_progress', 'stage', 'no_projects'],
            ['portfolio_progress', 'published_project_count', null], ['portfolio_progress', 'published_project_count', -1],
            ['portfolio_progress', 'published_project_count', 2], ['portfolio_progress', 'published_project_count', '0'],
            ['portfolio_progress', 'published_project_count', false],
            ['technology_evidence_map', 'mapping_coverage_bps', null], ['technology_evidence_map', 'mapping_coverage_bps', -1],
            ['technology_evidence_map', 'mapping_coverage_bps', 10001], ['technology_evidence_map', 'mapping_coverage_bps', 0],
            ['technology_evidence_map', 'mapping_coverage_bps', '6667'], ['technology_evidence_map', 'mapping_coverage_bps', 6667.0],
            ['documentation_coverage', 'evidence_confidence_detail', null],
        ] as [$metric, $field, $value]) {
            $core = $valid;
            $core[$metric][$field] = $value;
            self::reject(static fn (): array => mapEvidenceHubContractV7($core, []), "V7 accepted malformed {$field}.");
        }
        $detail = $valid['documentation_coverage']['evidence_confidence_detail'];
        foreach ([
            ['observations', 0], ['observations', '1'], ['recommended_observations', 4], ['recommended_observations', '3'],
            ['basis_codes', ['SINGLE_PROJECT', 'EVIDENCE_INCOMPLETE']], ['basis_codes', ['SINGLE_PROJECT', 'SINGLE_PROJECT']],
            ['basis_codes', []], ['basis_codes', ['UNKNOWN']], ['basis_codes', 'SINGLE_PROJECT'], ['extra', true],
        ] as [$field, $value]) {
            $core = $valid;
            $core['documentation_coverage']['evidence_confidence_detail'] = array_replace($detail, [$field => $value]);
            self::reject(static fn (): array => mapEvidenceHubContractV7($core, []), "V7 accepted malformed detail {$field}.");
        }
        foreach (['observations', 'recommended_observations', 'basis_codes'] as $field) {
            $core = $valid;
            unset($core['documentation_coverage']['evidence_confidence_detail'][$field]);
            self::reject(static fn (): array => mapEvidenceHubContractV7($core, []), 'V7 accepted an incomplete detail object.');
        }
        foreach (['portfolio_progress' => ['stage', 'published_project_count'], 'technology_evidence_map' => ['mapping_coverage_bps'], 'documentation_coverage' => ['evidence_confidence_detail']] as $metric => $fields) {
            foreach ($fields as $field) {
                $core = $valid;
                unset($core[$metric][$field]);
                self::reject(static fn (): array => mapEvidenceHubContractV7($core, []), 'V7 accepted a missing detail field.');
            }
        }
        $empty = self::core([]);
        $empty['technology_evidence_map']['mapping_coverage_bps'] = 0;
        self::reject(static fn (): array => mapEvidenceHubContractV7($empty, []), 'Zero occurrences require null mapping coverage.');
    }

    private static function earlierVersions(): void
    {
        $core = self::core([1]);
        $core['technology_evidence_map'] = summarizeEvidenceHubTechnologies([], loadEvidenceHubTechnologyTaxonomy('v1'));
        $changed = $core;
        $changed['portfolio_progress']['stage'] = 'trend_ready';
        $changed['portfolio_progress']['published_project_count'] = null;
        $changed['documentation_coverage']['evidence_confidence_detail'] = ['extra' => 'ignored by historical contracts'];
        $changed['technology_evidence_map']['mapping_coverage_bps'] = -1;
        foreach (['v1', 'v2', 'v3', 'v4', 'v5', 'v6'] as $version) {
            phase2AssertSame(json_encode(mapEvidenceHubContractVersion($core, [], $version), JSON_THROW_ON_ERROR), json_encode(mapEvidenceHubContractVersion($changed, [], $version), JSON_THROW_ON_ERROR), "{$version} published a new internal field.");
        }
    }

    private static function authorizedPaths(): void
    {
        $database = self::database();
        phase2AssertSame('v7', EVIDENCE_HUB_OWNER_CONTRACT_VERSION, 'EH-05 must make v7 live.');
        phase2AssertSame(['schema_version' => '7.0.0', 'taxonomy_version' => 'v5', 'owner_mappings' => true, 'evidence_detail' => true], evidenceHubContractVersionDefinition(EVIDENCE_HUB_OWNER_CONTRACT_VERSION), 'The live definition is wrong.');
        foreach ([[1, 10, 1], [2, 20, 2], [3, 30, 0]] as [$user, $portfolio, $published]) {
            $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($user), $portfolio);
            $contract = buildAuthorizedEvidenceHubOwnerContract($database, $context, []);
            phase2AssertSame('evidence-hub-contract-' . EVIDENCE_HUB_OWNER_CONTRACT_VERSION, $contract['contract_id'], 'The authorized builder must select the live contract.');
            phase2AssertSame($contract, mapEvidenceHubContractV7(buildAuthorizedEvidenceHubOwnerCore($database, $context), []), 'The live mapper must publish v7.');
            $model = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, ['contract' => ['recommendations' => []]], 'v7-test-synthetic-hmac');
            $progress = $contract['metrics']['portfolio_progress'];
            phase2AssertSame($published, $progress['published_project_count'], 'A published count crossed tenant scope.');
            phase2AssertSame($model['progress']['published_project_count'], $progress['published_project_count'], 'Contract and page publication counts differ.');
            phase2AssertSame($model['progress']['stage'] === 'trend_ready' ? 'emerging_patterns' : $model['progress']['stage'], $progress['stage'], 'Contract and page stages differ beyond the historical-context refinement.');
            if ($portfolio === 10) {
                phase2AssertSame('trend_ready', $model['progress']['stage'], 'The stage relation fixture must qualify page-only history.');
            }
            $state = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, 1767225600, 'v7-test-synthetic-hmac');
            phase2AssertSame($progress, $state['contract']['metrics']['portfolio_progress'], 'The recommendation path did not supply the publication count.');
        }
        $foreign = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 20);
        phase2AssertSame(0, loadAuthorizedEvidenceHubPublishedProjectCount($database, $foreign), 'The count query failed to join portfolio ownership.');
    }

    private static function rendering(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_presentation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/csrf.php';
        foreach ([[0, '[]', null], [1, '["HTML","PHP","Private Future Tool"]', 66], [2, '["HTML","PHP","Private Future Tool"]', 66], [3, '["HTML","PHP","Private Future Tool"]', 66], [1, '["Private Future Tool"]', 0], [1, '[]', null]] as [$count, $technologies, $percent]) {
            $database = self::database();
            $database->exec('DELETE FROM projects WHERE portfolio_id=10 AND id>' . $count);
            $update = $database->prepare('UPDATE projects SET technologies=:technologies WHERE portfolio_id=10');
            $update->execute(['technologies' => $technologies]);
            $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
            $contract = buildAuthorizedEvidenceHubOwnerContract($database, $context, []);
            $model = buildAuthorizedEvidenceHubOwnerPageModel($database, $context, ['contract' => ['recommendations' => []]], 'v7-test-synthetic-hmac');
            foreach (['en', 'ar'] as $locale) {
                ob_start();
                renderEvidenceHubOwnerPage($contract, $model, [], '', ['kind' => 'none'], $locale);
                $html = (string) ob_get_clean();
                if ($count > 0 && $count < 3) {
                    $expected = $locale === 'ar'
                        ? 'مبني على ' . ($count === 1 ? 'مشروع واحد' : 'مشروعان') . '؛ 3 مشاريع تعطي صورة أدق.'
                        : 'Based on ' . $count . ($count === 1 ? ' project' : ' projects') . '; 3 projects give a more reliable picture.';
                    phase2AssertSame(1, substr_count($html, '<p>' . $expected . '</p>'), "{$locale}: the sample-size line or counted phrases are wrong.");
                } else {
                    phase2Assert(!str_contains($html, 'give a more reliable picture.') && !str_contains($html, 'تعطي صورة أدق.'), "{$locale}: the sample-size line must be absent outside its observation bounds.");
                }
                $prefix = $locale === 'ar' ? 'التقنيات المعروفة: ' : 'Recognized technologies: ';
                phase2AssertSame($percent !== null, str_contains($html, $prefix), "{$locale}: recognition visibility must follow contract coverage nullability.");
                if ($percent !== null) {
                    phase2AssertSame(1, substr_count($html, '<p>' . $prefix . $percent . '%</p>'), "{$locale}: recognition must display the contract's truncated whole percent.");
                }
                $historical = mapEvidenceHubContractV6(buildAuthorizedEvidenceHubOwnerCore($database, $context), []);
                ob_start();
                renderEvidenceHubOwnerPage($historical, $model, [], '', ['kind' => 'none'], $locale);
                $oldHtml = (string) ob_get_clean();
                phase2Assert(!str_contains($oldHtml, $prefix) && !str_contains($oldHtml, 'give a more reliable picture.') && !str_contains($oldHtml, 'تعطي صورة أدق.'), 'The new lines must not be inferred from the page model for a historical contract.');
            }
        }
    }

    /** @param list<int> $counts @return array<string, mixed> */
    private static function core(array $counts, int $published = 0): array
    {
        $projects = [];
        foreach ($counts as $index => $complete) {
            $evaluations = [];
            foreach (evidenceTextFieldNames() as $fieldIndex => $field) {
                $done = $fieldIndex < $complete;
                $evaluations[$field] = ['evidence_field' => $field, 'evidence_status' => $done ? 'complete' : 'unavailable', 'reason_codes' => [$done ? 'TEXT_COMPLETE' : 'FIELD_NOT_AVAILABLE']];
            }
            $projects[] = ['project_ref' => $index + 1, 'recorded_at_epoch_seconds' => 1767225600 + $index * 15 * 86400,
                'field_evaluations' => $evaluations, 'expected_evidence_fields' => 3, 'complete_evidence_fields' => $complete, 'project_has_complete_evidence' => $complete === 3,
                'technology_storage_state' => 'valid', 'technology_storage_reason_codes' => [], 'technologies' => ['HTML', 'vue 3', 'Private Future Tool']];
        }
        return buildEvidenceHubOwnerCoreFromEvaluatedProjects($projects, 'unpublished', [evidenceHubTechnologyLabelDigest('vue 3') => 'tech.vue'], $published);
    }

    private static function database(): PDO
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT NULL, is_published INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE personal_info (portfolio_id INTEGER PRIMARY KEY, full_name TEXT NULL);
            CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, title TEXT NOT NULL, is_published INTEGER NOT NULL DEFAULT 0, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE TABLE recommendation_dispositions (portfolio_id INTEGER NOT NULL, recommendation_key TEXT NOT NULL, rule_version TEXT NOT NULL, evidence_fingerprint TEXT NOT NULL, disposition TEXT NOT NULL, snoozed_until TEXT NULL, PRIMARY KEY (portfolio_id, recommendation_key));');
        $database->exec(phase2EvidenceHubTechnologyReviewSqliteSchema());
        $database->exec("INSERT INTO portfolios VALUES (10,1,'owner-a',0),(20,2,'owner-b',0),(30,3,NULL,0)");
        $database->exec("INSERT INTO personal_info VALUES (10,'Owner A'),(20,'Owner B'),(30,'Owner C')");
        $insert = $database->prepare('INSERT INTO projects VALUES (:id,:portfolio,:title,:published,:problem,:role,:outcome,:technologies,:created,:updated)');
        foreach ([[1, 10, 1], [2, 10, 0], [3, 10, 0], [4, 20, 1], [5, 20, 1]] as [$id, $portfolio, $published]) {
            $date = gmdate('Y-m-d\TH:i:s\Z', 1767225600 + ($id - 1) * 15 * 86400);
            $insert->execute(['id' => $id, 'portfolio' => $portfolio, 'title' => 'Synthetic project ' . $id, 'published' => $published,
                'problem' => $portfolio === 10 ? 'architecture01 delivery02 structure03 evidence04 outcomes05 planning06 validation07 ownership08' : null,
                'role' => $portfolio === 10 ? 'ownership01 delivery02 review03 testing04 support05' : null,
                'outcome' => $portfolio === 10 ? 'reduced50 latency40 requests30 failures20' : null,
                'technologies' => '["HTML"]', 'created' => $date, 'updated' => $date]);
        }
        return $database;
    }

    private static function reject(callable $probe, string $message): void
    {
        try {
            $probe();
            throw new RuntimeException($message);
        } catch (EvidenceHubContractMappingException) {
            // Rejection must come from versioned contract validation.
        }
    }
}
