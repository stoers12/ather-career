<?php

declare(strict_types=1);

final class EvidenceHubTimestampMySqlTest
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_core.php';
        if (getenv('EVIDENCE_HUB_TIMESTAMP_MYSQL_TEST') !== '1') {
            throw new RuntimeException('Evidence Hub MySQL timestamp test requires explicit disposable-test authorization.');
        }
        $database = self::database();
        self::prepareSchema($database);
        self::seedUtcInstants($database);

        $utcFacts = self::factsForSession($database, '+00:00', 'UTC');
        $offsetFacts = self::factsForSession($database, '+05:30', 'Pacific/Auckland');
        phase2AssertSame(array_column($utcFacts, 'recorded_at_epoch_seconds'), array_column($offsetFacts, 'recorded_at_epoch_seconds'), 'MySQL session or PHP timezone changed an absolute project instant.');
        phase2AssertSame([1767310200, 1767312900], array_column($utcFacts, 'recorded_at_epoch_seconds'), 'MySQL UTC epoch conversion changed.');

        $projects = [self::projectFact($utcFacts[0]['recorded_at_epoch_seconds']), self::projectFact($utcFacts[1]['recorded_at_epoch_seconds'])];
        $progress = summarizeEvidenceHubPortfolioProgress($projects, 'unpublished');
        phase2AssertSame('2026-01-01T23:30:00Z', $progress['first_project_recorded_at'], 'MySQL first instant was not normalized to UTC.');
        phase2AssertSame('2026-01-02T00:15:00Z', $progress['latest_project_recorded_at'], 'MySQL latest instant was not normalized to UTC.');
        phase2AssertSame(0, $progress['recorded_activity_span_days'], 'Cross-midnight interval under 24 hours must remain zero.');
    }

    private static function database(): PDO
    {
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $name) {
            if (!is_string(getenv($name)) || getenv($name) === '') {
                throw new RuntimeException('Evidence Hub disposable MySQL configuration is incomplete.');
            }
        }
        $databaseName = getenv('DB_NAME');
        if (!is_string($databaseName) || preg_match('/^ather_career_test_[a-z0-9_]+$/', $databaseName) !== 1) {
            throw new RuntimeException('Evidence Hub timestamp test refuses a non-disposable database name.');
        }

        return new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . $databaseName . ';charset=utf8mb4',
            getenv('DB_USER'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private static function prepareSchema(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS projects');
        $database->exec('DROP TABLE IF EXISTS portfolios');
        $database->exec('CREATE TABLE portfolios (id BIGINT PRIMARY KEY, owner_user_id BIGINT NOT NULL, public_slug VARCHAR(64) NULL, is_published TINYINT NOT NULL)');
        $database->exec('CREATE TABLE projects (id BIGINT PRIMARY KEY, portfolio_id BIGINT NOT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TIMESTAMP NOT NULL)');
    }

    private static function seedUtcInstants(PDO $database): void
    {
        $database->exec("SET time_zone = '+00:00'");
        $database->exec("INSERT INTO portfolios (id, owner_user_id, public_slug, is_published) VALUES (10, 1, 'synthetic-owner', 0)");
        $statement = $database->prepare('INSERT INTO projects (id, portfolio_id, created_at) VALUES (:id, 10, :recorded_at)');
        $statement->execute(['id' => 1, 'recorded_at' => '2026-01-01 23:30:00']);
        $statement->execute(['id' => 2, 'recorded_at' => '2026-01-02 00:15:00']);
    }

    /** @return list<array<string, mixed>> */
    private static function factsForSession(PDO $database, string $sessionTimezone, string $phpTimezone): array
    {
        $previousTimezone = date_default_timezone_get();
        try {
            date_default_timezone_set($phpTimezone);
            $database->exec("SET time_zone = '{$sessionTimezone}'");
            $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);

            return loadAuthorizedEvidenceHubProjectFacts($database, $context);
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    /** @return array<string, mixed> */
    private static function projectFact(int $epoch): array
    {
        $evaluations = [];
        foreach (evidenceTextFieldNames() as $field) {
            $evaluations[$field] = ['evidence_field' => $field, 'evidence_status' => 'unavailable', 'reason_codes' => ['FIELD_NOT_AVAILABLE']];
        }

        return [
            'field_evaluations' => $evaluations,
            'expected_evidence_fields' => 3,
            'complete_evidence_fields' => 0,
            'project_has_complete_evidence' => false,
            'recorded_at_epoch_seconds' => $epoch,
        ];
    }
}
