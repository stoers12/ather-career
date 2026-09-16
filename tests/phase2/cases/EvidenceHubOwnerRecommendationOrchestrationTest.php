<?php

declare(strict_types=1);

final class EvidenceHubOwnerRecommendationOrchestrationTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_recommendations.php';

        $fixtures = self::fixtures();
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            return;
        }

        $database = self::database();
        $ownerA = self::context(1, 10);
        $ownerB = self::context(2, 20);
        $foreign = self::context(1, 20);
        $now = $fixtures['calculation_time_epoch_seconds'];
        $key = hex2bin($fixtures['opaque_target_hmac_key_hex']);
        phase2Assert(is_int($now) && is_string($key), 'Owner recommendation fixture is invalid.');

        $state = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $ownerA, $now, $key);
        self::assertAllRulesAndVisibleOutput($state, $fixtures);
        self::assertFirstProjectRule($database, self::context(3, 30), $now, $key);
        self::assertFullResolutionAndAssociations($state);
        self::assertDeterminism($database, $ownerA, $now, $key, $state);
        self::assertTenantIsolation($database, $ownerB, $foreign, $now, $key, $state);
        self::assertDispositionLifecycle($database, $ownerA, $now, $key, $state);
        self::assertNoContractDisclosure($state);
    }

    private static function assertFirstProjectRule(PDO $database, AuthorizedPortfolioContext $context, int $now, string $key): void
    {
        $state = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, $now, $key);
        phase2AssertSame(['add_first_project'], array_column($state['current_candidates'], 'rule_id'), 'The no-project Owner scope did not adapt through the first-project rule.');
    }

    /** @param array<string, mixed> $state @param array<string, mixed> $fixtures */
    private static function assertAllRulesAndVisibleOutput(array $state, array $fixtures): void
    {
        $allRuleIds = array_values(array_unique(array_map(static fn (array $candidate): string => $candidate['rule_id'], $state['current_candidates'])));
        sort($allRuleIds, SORT_STRING);
        phase2AssertSame([
            'complete_portfolio_publication',
            'complete_project_evidence',
            'review_unmapped_technology',
        ], $allRuleIds, 'Owner adapter did not adapt scoped facts through all applicable recommendation rules.');
        phase2AssertSame($fixtures['expected_visible_rule_ids'], array_column($state['contract']['recommendations'], 'rule_id'), 'Visible owner output diverged from the frozen ordering and cap.');
        phase2AssertSame([1, 2, 3], array_column($state['contract']['recommendations'], 'display_order'), 'Visible owner output lost dense display order.');
    }

    /** @param array<string, mixed> $state */
    private static function assertFullResolutionAndAssociations(array $state): void
    {
        phase2Assert(count($state['current_candidates']) > count($state['contract']['recommendations']), 'The owner adapter did not retain candidates beyond the visible cap.');
        $resolved = null;
        foreach ($state['current_candidates'] as $candidate) {
            if ($candidate['rule_id'] !== 'review_unmapped_technology') {
                continue;
            }
            $candidateResolution = resolveAuthorizedEvidenceHubOwnerRecommendation($state, $candidate['recommendation_key']);
            if (is_array($candidateResolution) && $candidateResolution['resource_associations']['project_refs'] === [101, 102]) {
                $resolved = $candidateResolution;
                break;
            }
        }
        phase2Assert(is_array($resolved), 'An unmapped technology did not retain all owned project associations.');
        phase2AssertSame('review_unmapped_technology', $resolved['candidate']['rule_id'], 'A multi-project technology association resolved to the wrong candidate type.');
        phase2AssertSame(null, resolveAuthorizedEvidenceHubOwnerRecommendation($state, str_repeat('a', 64)), 'Unknown recommendation selector was accepted.');
    }

    /** @param array<string, mixed> $state */
    private static function assertDeterminism(PDO $database, AuthorizedPortfolioContext $context, int $now, string $key, array $state): void
    {
        phase2AssertSame($state, buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, $now, $key), 'Identical injected inputs did not produce deterministic owner recommendation state.');
    }

    /** @param array<string, mixed> $state */
    private static function assertTenantIsolation(PDO $database, AuthorizedPortfolioContext $ownerB, AuthorizedPortfolioContext $foreign, int $now, string $key, array $state): void
    {
        $ownerBState = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $ownerB, $now, $key);
        $ownerBKey = $ownerBState['current_candidates'][0]['recommendation_key'] ?? null;
        phase2Assert(is_string($ownerBKey), 'Synthetic second tenant has no recommendation candidate.');
        phase2AssertSame(null, resolveAuthorizedEvidenceHubOwnerRecommendation($state, $ownerBKey), 'A cross-tenant selector resolved in the first tenant state.');
        self::assertDenied(static fn (): array => buildAuthorizedEvidenceHubOwnerRecommendationState($database, $foreign, $now, $key), 'Foreign Owner context could build recommendation state.');
    }

    /** @param array<string, mixed> $state */
    private static function assertDispositionLifecycle(PDO $database, AuthorizedPortfolioContext $context, int $now, string $key, array $state): void
    {
        $candidate = $state['contract']['recommendations'][0] ?? null;
        phase2Assert(is_array($candidate) && $candidate['rule_id'] === 'complete_project_evidence', 'Synthetic visible project recommendation is unavailable.');
        $resolvedBefore = resolveAuthorizedEvidenceHubOwnerRecommendation($state, $candidate['recommendation_key']);
        phase2Assert(is_array($resolvedBefore) && count($resolvedBefore['resource_associations']['project_refs']) === 1, 'Visible project recommendation has no owned resource association.');
        $projectRef = $resolvedBefore['resource_associations']['project_refs'][0];
        storeAuthorizedEvidenceHubRecommendationDisposition($database, $context, $candidate, 'snoozed', $now);
        $suppressed = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, $now, $key);
        phase2Assert(!in_array($candidate['recommendation_key'], array_column($suppressed['contract']['recommendations'], 'recommendation_key'), true), 'Matching snooze did not suppress a visible recommendation.');
        $expired = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, evidenceHubRecommendationSnoozeUntil($now) + 1, $key);
        phase2Assert(in_array($candidate['recommendation_key'], array_column($expired['contract']['recommendations'], 'recommendation_key'), true), 'Expired snooze continued suppressing the current recommendation.');

        $database->exec('UPDATE projects SET problem_statement = NULL, personal_role = NULL, measurable_outcome = NULL WHERE id = ' . $projectRef);
        $superseded = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, $now, $key);
        $current = resolveAuthorizedEvidenceHubOwnerRecommendation($superseded, $candidate['recommendation_key']);
        phase2Assert(is_array($current), 'A current recommendation key did not resolve after an evidence-state change.');
        phase2Assert($candidate['evidence_fingerprint'] !== $current['candidate']['evidence_fingerprint'], 'Evidence-state change did not supersede the candidate fingerprint.');
        $database->exec('DELETE FROM projects WHERE id = ' . $projectRef);
        $ineligible = buildAuthorizedEvidenceHubOwnerRecommendationState($database, $context, $now, $key);
        phase2AssertSame(null, resolveAuthorizedEvidenceHubOwnerRecommendation($ineligible, $candidate['recommendation_key']), 'No-longer-eligible selector was accepted.');
    }

    /** @param array<string, mixed> $state */
    private static function assertNoContractDisclosure(array $state): void
    {
        $serialized = json_encode($state['contract'], JSON_THROW_ON_ERROR);
        foreach (['PRIVATE_EVIDENCE_MARKER', 'opaque_target_identity', 'resource_associations'] as $forbidden) {
            phase2Assert(!str_contains($serialized, $forbidden), "Mapped contract disclosed internal {$forbidden} data.");
        }
        self::assertNoInternalContractKeys($state['contract']);
    }

    private static function assertNoInternalContractKeys(mixed $value): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            phase2Assert(!in_array($key, ['project_ref', 'project_id', 'portfolio_id', 'target_identity', 'resource_associations'], true), "Mapped contract disclosed internal {$key} data.");
            self::assertNoInternalContractKeys($item);
        }
    }

    /** @return array<string, mixed> */
    private static function database(): PDO
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec(
            'CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT NULL, is_published INTEGER NOT NULL);
             CREATE TABLE personal_info (portfolio_id INTEGER PRIMARY KEY, full_name TEXT NULL);
             CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL);
             CREATE TABLE recommendation_dispositions (portfolio_id INTEGER NOT NULL, recommendation_key TEXT NOT NULL, rule_version TEXT NOT NULL, evidence_fingerprint TEXT NOT NULL, disposition TEXT NOT NULL CHECK (disposition IN (\'snoozed\', \'dismissed\')), snoozed_until TEXT NULL, PRIMARY KEY (portfolio_id, recommendation_key));'
        );
        $database->exec("INSERT INTO portfolios (id, owner_user_id, public_slug, is_published) VALUES (10, 1, 'owner-a', 0), (20, 2, 'owner-b', 0), (30, 3, 'owner-c', 0);");
        $database->exec("INSERT INTO personal_info (portfolio_id, full_name) VALUES (10, 'Owner A'), (20, 'Owner B'), (30, 'Owner C');");
        $insert = $database->prepare('INSERT INTO projects (id, portfolio_id, problem_statement, personal_role, measurable_outcome, technologies, created_at) VALUES (:id, :portfolio_id, :problem_statement, :personal_role, :measurable_outcome, :technologies, :created_at)');
        foreach ([
            [101, 10, null, self::completeText('personal_role'), self::completeText('measurable_outcome'), '["Nebula Tool"]'],
            [102, 10, self::completeText('problem_statement'), null, self::completeText('measurable_outcome'), '["Nebula Tool"]'],
            [103, 10, self::completeText('problem_statement'), self::completeText('personal_role'), null, '["Quasar Tool"]'],
            [201, 20, null, null, null, '["Foreign Tool"]'],
        ] as [$id, $portfolioId, $problem, $role, $outcome, $technologies]) {
            $insert->execute(['id' => $id, 'portfolio_id' => $portfolioId, 'problem_statement' => $problem, 'personal_role' => $role, 'measurable_outcome' => $outcome, 'technologies' => $technologies, 'created_at' => '2026-01-01T00:00:00Z']);
        }

        return $database;
    }

    private static function completeText(string $field): string
    {
        return match ($field) {
            'problem_statement' => 'architecture01 delivery02 structure03 evidence04 outcomes05 planning06 validation07 ownership08',
            'personal_role' => 'ownership01 delivery02 review03 testing04 support05',
            'measurable_outcome' => 'reduced50 latency40 requests30 failures20',
            default => throw new LogicException('Unsupported synthetic evidence field.'),
        };
    }

    private static function context(int $userId, int $portfolioId): AuthorizedPortfolioContext
    {
        return AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($userId), $portfolioId);
    }

    private static function assertDenied(callable $probe, string $message): void
    {
        try {
            $probe();
        } catch (AuthorizationDeniedException) {
            return;
        }
        throw new RuntimeException($message);
    }

    /** @return array<string, mixed> */
    private static function fixtures(): array
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-hub-owner-recommendation-fixtures.json');
        if (!is_string($contents)) {
            throw new RuntimeException('Owner recommendation fixture is unreadable.');
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Owner recommendation fixture is invalid.');
        }

        return $decoded;
    }
}
