<?php

declare(strict_types=1);

final class EvidenceHubRecommendationDispositionTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendation_dispositions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendations.php';

        $fixtures = self::fixtures();
        self::staticContract();
        if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::runtimeRepository($fixtures);
        }
    }

    /** @param array<string, mixed> $fixtures */
    private static function runtimeRepository(array $fixtures): void
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec(
            'CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL);
             CREATE TABLE recommendation_dispositions (
                portfolio_id INTEGER NOT NULL,
                recommendation_key TEXT NOT NULL,
                rule_version TEXT NOT NULL,
                evidence_fingerprint TEXT NOT NULL,
                disposition TEXT NOT NULL CHECK (disposition IN (\'snoozed\', \'dismissed\')),
                snoozed_until TEXT NULL,
                PRIMARY KEY (portfolio_id, recommendation_key),
                CHECK ((disposition = \'snoozed\' AND snoozed_until IS NOT NULL) OR (disposition = \'dismissed\' AND snoozed_until IS NULL))
             );'
        );
        $database->exec('INSERT INTO portfolios (id, owner_user_id) VALUES (10, 1), (20, 2)');

        $ownerA = self::context(1, 10);
        $ownerB = self::context(2, 20);
        $foreign = self::context(1, 20);
        $candidate = self::currentRecommendation('a');
        $snooze = $fixtures['recommendation_repository_mapper']['snooze'];

        phase2AssertSame([], listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA), 'Same-tenant disposition listing did not begin empty.');
        storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerA, $candidate, 'snoozed', $snooze['injected_epoch_seconds']);
        $stored = listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA);
        phase2AssertSame([[
            'recommendation_key' => $candidate['recommendation_key'],
            'rule_version' => $candidate['rule_version'],
            'evidence_fingerprint' => $candidate['evidence_fingerprint'],
            'disposition' => 'snoozed',
            'snoozed_until' => $snooze['expected_snoozed_until_epoch_seconds'],
        ]], $stored, 'Same-tenant snooze storage or UTC expiry changed.');
        phase2AssertSame('2026-01-15 00:00:00', $database->query('SELECT snoozed_until FROM recommendation_dispositions WHERE portfolio_id = 10')->fetchColumn(), 'SQLite snooze storage was not normalized to UTC.');

        storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerA, $candidate, 'dismissed', $snooze['injected_epoch_seconds']);
        $replaced = listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA);
        phase2AssertSame(1, count($replaced), 'Current-state replacement created recommendation history.');
        phase2AssertSame('dismissed', $replaced[0]['disposition'], 'Dismissal did not replace the current state.');
        phase2AssertSame(null, $replaced[0]['snoozed_until'], 'Dismissal persisted a snooze value.');

        $replacement = self::currentRecommendation('b', $candidate['recommendation_key']);
        storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerA, $replacement, 'snoozed', $snooze['injected_epoch_seconds']);
        $current = listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA);
        phase2AssertSame(1, count($current), 'Replacing an existing disposition changed the current-state cardinality.');
        phase2AssertSame($replacement['evidence_fingerprint'], $current[0]['evidence_fingerprint'], 'Current-state replacement did not retain the current server fingerprint.');
        phase2AssertSame('snoozed', $current[0]['disposition'], 'Current-state replacement did not retain the snooze disposition.');

        storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerB, self::currentRecommendation('c'), 'dismissed', $snooze['injected_epoch_seconds']);
        phase2AssertSame(1, count(listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA)), 'Same-tenant read crossed into another portfolio.');
        self::assertDenied(static fn (): array => listAuthorizedEvidenceHubRecommendationDispositions($database, $foreign), 'Foreign AuthorizedPortfolioContext could read a disposition.');
        self::assertDenied(static function () use ($database, $foreign, $snooze): void {
            storeAuthorizedEvidenceHubRecommendationDisposition($database, $foreign, self::currentRecommendation('d'), 'dismissed', $snooze['injected_epoch_seconds']);
        }, 'Foreign AuthorizedPortfolioContext could write a disposition.');
        self::assertRepositoryFailure(static function () use ($database, $ownerA, $candidate, $snooze): void {
            storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerA, $candidate, 'active', $snooze['injected_epoch_seconds']);
        }, 'A derived lifecycle state was persisted.');

        self::matchingAndSupersession($database, $ownerA, $snooze['injected_epoch_seconds']);
    }

    private static function matchingAndSupersession(PDO $database, AuthorizedPortfolioContext $context, int $now): void
    {
        $facts = self::recommendationFacts('needs_attention');
        $current = buildEvidenceHubRecommendations($facts, [], $now, 'r3-synthetic-hmac-material')[0];
        storeAuthorizedEvidenceHubRecommendationDisposition($database, $context, $current, 'dismissed', $now);
        $dispositions = listAuthorizedEvidenceHubRecommendationDispositions($database, $context);
        phase2AssertSame([], buildEvidenceHubRecommendations($facts, $dispositions, $now, 'r3-synthetic-hmac-material'), 'Matching dismissal did not suppress the current recommendation.');

        $changed = self::recommendationFacts('unavailable');
        $visible = buildEvidenceHubRecommendations($changed, $dispositions, $now, 'r3-synthetic-hmac-material');
        phase2AssertSame(1, count($visible), 'A superseded fingerprint suppressed a current recommendation.');
        phase2AssertSame($current['recommendation_key'], $visible[0]['recommendation_key'], 'Supersession unexpectedly changed recommendation identity.');
        phase2Assert($current['evidence_fingerprint'] !== $visible[0]['evidence_fingerprint'], 'Supersession did not change the evidence fingerprint.');
    }

    private static function staticContract(): void
    {
        $source = self::read('includes/evidence_hub_recommendation_dispositions.php');
        foreach (['AuthorizedPortfolioContext $context', 'portfolio_id = :authorized_portfolio_id', 'owner_user_id = :authorized_user_id', 'recommendation_dispositions'] as $required) {
            phase2Assert(str_contains($source, $required), "Disposition repository is missing {$required}.");
        }
        foreach (['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER', 'owner_id', 'auth0', 'target_ref', 'project_id', 'raw_evidence'] as $forbidden) {
            phase2Assert(!str_contains($source, $forbidden), "Disposition repository accepts or stores forbidden {$forbidden}.");
        }
    }

    /** @return array<string, mixed> */
    private static function currentRecommendation(string $fingerprintSeed, ?string $key = null): array
    {
        return [
            'rule_id' => 'complete_project_evidence',
            'rule_version' => '1.0.0',
            'recommendation_key' => $key ?? evidenceHubRecommendationKey(['rule_id' => 'complete_project_evidence', 'target_type' => 'project', 'target_ref' => 'a1234567']),
            'evidence_fingerprint' => hash('sha256', 'evidence-fingerprint-r3-' . $fingerprintSeed),
            'lifecycle_state' => 'active',
            'priority_rank' => 2,
            'display_order' => 1,
            'reason_codes' => ['FIELD_NOT_AVAILABLE'],
            'target' => ['route' => 'owner_evidence_hub', 'action_id' => 'complete_project_evidence', 'opaque_target_ref' => 'a1234567'],
            'snoozed_until' => null,
        ];
    }

    /** @return array<string, mixed> */
    private static function recommendationFacts(string $state): array
    {
        return [
            'tenant_scope_ref' => 'r3_tenant_scope',
            'portfolio_target_identity' => 'r3_portfolio_target',
            'projects' => [[
                'target_identity' => 'r3_project_target',
                'field_completeness_states' => ['problem_statement' => $state, 'personal_role' => 'complete', 'measurable_outcome' => 'complete'],
                'reason_codes' => $state === 'unavailable' ? ['FIELD_NOT_AVAILABLE'] : ['GRAPHEME_THRESHOLD_NOT_MET'],
            ]],
            'technology_mappings' => [],
            'portfolio_publication' => ['portfolio_published' => true, 'publication_prerequisites_met' => true],
        ];
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

    private static function assertRepositoryFailure(callable $probe, string $message): void
    {
        try {
            $probe();
        } catch (EvidenceHubRecommendationDispositionException) {
            return;
        }
        throw new RuntimeException($message);
    }

    /** @return array<string, mixed> */
    private static function fixtures(): array
    {
        $decoded = json_decode(self::read('tests/phase2/fixtures/evidence-hub-golden-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
        phase2Assert(is_array($decoded), 'R3 fixtures are invalid.');
        return $decoded;
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");
        return $contents;
    }
}
