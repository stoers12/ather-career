<?php

declare(strict_types=1);

final class EvidenceHubRecommendationDispositionMySqlTest
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendation_dispositions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendations.php';

        if (getenv('EVIDENCE_HUB_R3_MYSQL_TEST') !== '1') {
            throw new RuntimeException('Evidence Hub R3 MySQL test requires explicit disposable-test authorization.');
        }
        $database = self::database();
        $database->exec("SET time_zone = '+00:00'");
        self::seed($database);
        self::repositoryAndConstraints($database);
    }

    private static function repositoryAndConstraints(PDO $database): void
    {
        $now = 1767225600;
        $ownerA = self::context(1, 10);
        $ownerB = self::context(2, 20);
        $foreign = self::context(1, 20);
        $candidate = self::currentRecommendation('a');

        $database->exec("SET time_zone = '+05:30'");
        storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerA, $candidate, 'snoozed', $now);
        $stored = listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA);
        phase2AssertSame(1, count($stored), 'MySQL same-tenant repository write was not readable.');
        phase2AssertSame(1768435200, $stored[0]['snoozed_until'], 'MySQL snooze expiry was not exactly fourteen injected UTC days.');
        phase2AssertSame('1768435200', (string) $database->query('SELECT UNIX_TIMESTAMP(snoozed_until) FROM recommendation_dispositions WHERE portfolio_id = 10')->fetchColumn(), 'MySQL stored an offset-dependent snooze expiry.');

        storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerA, $candidate, 'dismissed', $now);
        $replaced = listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA);
        phase2AssertSame(1, count($replaced), 'MySQL current-state replacement created history rows.');
        phase2AssertSame('dismissed', $replaced[0]['disposition'], 'MySQL dismissal did not replace current state.');
        phase2AssertSame(null, $replaced[0]['snoozed_until'], 'MySQL dismissed state retained a snooze timestamp.');

        $replacement = self::currentRecommendation('b', $candidate['recommendation_key']);
        storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerA, $replacement, 'snoozed', $now);
        $current = listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA);
        phase2AssertSame(1, count($current), 'MySQL replacement did not retain one current row.');
        phase2AssertSame($replacement['evidence_fingerprint'], $current[0]['evidence_fingerprint'], 'MySQL replacement did not retain the latest fingerprint.');

        storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerB, self::currentRecommendation('c'), 'dismissed', $now);
        phase2AssertSame(1, count(listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA)), 'MySQL read crossed a tenant boundary.');
        self::assertDenied(static fn (): array => listAuthorizedEvidenceHubRecommendationDispositions($database, $foreign), 'MySQL foreign context read was accepted.');
        self::assertDenied(static function () use ($database, $foreign, $now): void {
            storeAuthorizedEvidenceHubRecommendationDisposition($database, $foreign, self::currentRecommendation('d'), 'dismissed', $now);
        }, 'MySQL foreign context write was accepted.');

        self::assertConstraint(static function () use ($database): void {
            $database->exec("INSERT INTO recommendation_dispositions (portfolio_id, recommendation_key, rule_version, evidence_fingerprint, disposition, snoozed_until) VALUES (10, REPEAT('a', 64), '1.0.0', REPEAT('b', 64), 'active', NULL)");
        }, 'MySQL accepted a derived lifecycle state.');
        self::assertConstraint(static function () use ($database): void {
            $database->exec("INSERT INTO recommendation_dispositions (portfolio_id, recommendation_key, rule_version, evidence_fingerprint, disposition, snoozed_until) VALUES (10, REPEAT('c', 64), '1.0.0', REPEAT('d', 64), 'snoozed', NULL)");
        }, 'MySQL accepted a snooze without an expiry.');
        self::assertConstraint(static function () use ($database): void {
            $database->exec("INSERT INTO recommendation_dispositions (portfolio_id, recommendation_key, rule_version, evidence_fingerprint, disposition, snoozed_until) VALUES (10, REPEAT('e', 64), '1.0.0', REPEAT('f', 64), 'dismissed', '2026-01-15 00:00:00')");
        }, 'MySQL accepted a dismissal with an expiry.');
        self::assertConstraint(static function () use ($database): void {
            $database->exec("INSERT INTO recommendation_dispositions (portfolio_id, recommendation_key, rule_version, evidence_fingerprint, disposition, snoozed_until) VALUES (999, REPEAT('1', 64), '1.0.0', REPEAT('2', 64), 'dismissed', NULL)");
        }, 'MySQL accepted an unowned disposition portfolio.');

        $facts = self::recommendationFacts('needs_attention');
        $currentRecommendation = buildEvidenceHubRecommendations($facts, [], $now, 'r3-mysql-synthetic-hmac')[0];
        storeAuthorizedEvidenceHubRecommendationDisposition($database, $ownerA, $currentRecommendation, 'dismissed', $now);
        $dispositions = listAuthorizedEvidenceHubRecommendationDispositions($database, $ownerA);
        phase2AssertSame([], buildEvidenceHubRecommendations($facts, $dispositions, $now, 'r3-mysql-synthetic-hmac'), 'Matching MySQL dismissal did not suppress the current recommendation.');
        $changed = buildEvidenceHubRecommendations(self::recommendationFacts('unavailable'), $dispositions, $now, 'r3-mysql-synthetic-hmac');
        phase2AssertSame(1, count($changed), 'MySQL superseded fingerprint suppressed a current recommendation.');
        phase2AssertSame($currentRecommendation['recommendation_key'], $changed[0]['recommendation_key'], 'MySQL supersession changed recommendation identity.');
    }

    private static function seed(PDO $database): void
    {
        $users = $database->prepare('INSERT INTO users (id, oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:id, :issuer, :subject, \'active\', 1)');
        foreach ([1 => 'r3-synthetic-user-a', 2 => 'r3-synthetic-user-b'] as $id => $subject) {
            $users->execute(['id' => $id, 'issuer' => 'r3-test', 'subject' => $subject]);
        }
        $portfolios = $database->prepare('INSERT INTO portfolios (id, owner_user_id) VALUES (:id, :owner_user_id)');
        $portfolios->execute(['id' => 10, 'owner_user_id' => 1]);
        $portfolios->execute(['id' => 20, 'owner_user_id' => 2]);
    }

    private static function database(): PDO
    {
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $name) {
            if (!is_string(getenv($name)) || getenv($name) === '') {
                throw new RuntimeException('Evidence Hub R3 disposable MySQL configuration is incomplete.');
            }
        }
        $databaseName = getenv('DB_NAME');
        if (!is_string($databaseName) || preg_match('/^ather_career_test_[a-f0-9]{24}$/', $databaseName) !== 1) {
            throw new RuntimeException('Evidence Hub R3 test refuses a non-disposable database name.');
        }

        return new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . $databaseName . ';charset=utf8mb4',
            getenv('DB_USER'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    /** @return array<string, mixed> */
    private static function currentRecommendation(string $fingerprintSeed, ?string $key = null): array
    {
        return [
            'rule_id' => 'complete_project_evidence',
            'rule_version' => '1.0.0',
            'recommendation_key' => $key ?? evidenceHubRecommendationKey(['rule_id' => 'complete_project_evidence', 'target_type' => 'project', 'target_ref' => 'a1234567']),
            'evidence_fingerprint' => hash('sha256', 'mysql-evidence-fingerprint-r3-' . $fingerprintSeed),
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
            'tenant_scope_ref' => 'r3_mysql_tenant_scope',
            'portfolio_target_identity' => 'r3_mysql_portfolio_target',
            'projects' => [[
                'target_identity' => 'r3_mysql_project_target',
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

    private static function assertConstraint(callable $probe, string $message): void
    {
        try {
            $probe();
        } catch (PDOException) {
            return;
        }
        throw new RuntimeException($message);
    }
}
