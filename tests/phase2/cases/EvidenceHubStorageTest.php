<?php

declare(strict_types=1);

final class EvidenceHubStorageTest
{
    public static function run(TestEnvironment $environment): void
    {
        self::staticContract();
        require_once PHASE2_REPOSITORY_ROOT . '/includes/portfolio_scoped_data.php';
        if (!evidenceTextUnicodeRuntimeIsAvailable() || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            return;
        }

        self::disposablePersistence();
    }

    private static function staticContract(): void
    {
        $migration = self::read('database/migrations/010_project_evidence_fields.sql');
        $rollback = self::read('database/rollback/010_project_evidence_fields.sql');
        $scopedData = self::read('includes/portfolio_scoped_data.php');
        $evaluator = self::read('includes/evidence_text_evaluator.php');
        $publicJson = self::read('includes/public_lifecycle.php');
        $developmentImage = self::read('Dockerfile');
        $productionImage = self::read('Dockerfile.production');

        foreach (['problem_statement TEXT NULL DEFAULT NULL', 'personal_role TEXT NULL DEFAULT NULL', 'measurable_outcome TEXT NULL DEFAULT NULL'] as $column) {
            phase2Assert(str_contains($migration, $column), 'Evidence migration does not add the approved nullable field.');
        }
        phase2Assert(str_contains($rollback, 'DROP COLUMN measurable_outcome') && str_contains($rollback, 'DROP COLUMN personal_role') && str_contains($rollback, 'DROP COLUMN problem_statement'), 'Disposable rollback companion is incomplete.');
        phase2Assert(str_contains($scopedData, 'projectEvidenceStorageValues') && str_contains($scopedData, 'listAuthorizedProjectEvidence'), 'Private evidence persistence boundary is missing.');
        foreach (['owner_id', 'portfolio_id', 'user_id', 'auth0', '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER'] as $forbiddenAuthority) {
            phase2Assert(!str_contains($evaluator, $forbiddenAuthority), "Evaluator accepts forbidden tenant authority {$forbiddenAuthority}.");
        }
        phase2Assert(!str_contains($publicJson, 'problem_statement') && !str_contains($publicJson, 'personal_role') && !str_contains($publicJson, 'measurable_outcome'), 'Public project mapper exposes private evidence.');
        foreach ([$developmentImage, $productionImage] as $image) {
            phase2Assert(str_contains($image, 'libicu-dev') && str_contains($image, 'mbstring intl'), 'Maintained PHP image lacks ext-intl.');
        }
    }

    private static function disposablePersistence(): void
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, portfolio_id INTEGER NOT NULL, title TEXT, category TEXT, description TEXT, github_url TEXT, image_path TEXT, technologies TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $otherContext = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 20);

        $legacyId = createAuthorizedProject($database, $context, 'Legacy', 'Web', 'Unchanged description', '', null, []);
        phase2AssertSame('Unchanged description', (string) $database->query("SELECT description FROM projects WHERE id = {$legacyId}")->fetchColumn(), 'Legacy project creation changed when evidence was omitted.');
        $database->exec('ALTER TABLE projects ADD COLUMN problem_statement TEXT NULL; ALTER TABLE projects ADD COLUMN personal_role TEXT NULL; ALTER TABLE projects ADD COLUMN measurable_outcome TEXT NULL;');

        $maximum = str_repeat('a', 2000);
        $id = createAuthorizedProject($database, $context, 'Private evidence', 'Web', 'Existing description', '', null, [], [
            'problem_statement' => $maximum,
            'personal_role' => null,
            'measurable_outcome' => 'Improved page speed by 35 percent and reduced request failures',
        ]);
        $stored = $database->query("SELECT problem_statement, personal_role, measurable_outcome FROM projects WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
        phase2AssertSame($maximum, $stored['problem_statement'], 'Maximum valid evidence was not stored exactly.');
        phase2AssertSame(null, $stored['personal_role'], 'Explicit null evidence was not persisted.');
        phase2Assert(updateAuthorizedProject($database, $context, $id, 'Private evidence', 'Web', 'Existing description', '', null, [], ['measurable_outcome' => null]), 'Scoped evidence update failed.');
        phase2AssertSame(null, $database->query("SELECT measurable_outcome FROM projects WHERE id = {$id}")->fetchColumn(), 'Explicit null update was not persisted.');
        phase2Assert(!updateAuthorizedProject($database, $otherContext, $id, 'Foreign', 'Web', 'No', '', null, [], ['problem_statement' => 'foreign update']), 'Cross-tenant evidence update was accepted.');

        $records = listAuthorizedProjectEvidence($database, $context);
        phase2AssertSame(2, count($records), 'Private evidence listing lost Owner-scoped records.');
        phase2AssertSame([], listAuthorizedProjectEvidence($database, $otherContext), 'Private evidence listing crossed tenant scope.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");
        return $contents;
    }
}
