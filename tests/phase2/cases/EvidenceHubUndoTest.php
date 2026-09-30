<?php

declare(strict_types=1);

final class EvidenceHubUndoTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_undo.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_presentation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/csrf.php';
        self::routeGuards();
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('Isolated SQLite Undo fixtures are unavailable.');
        }
        $database = self::database();
        $owner = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $other = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 20);
        $before = self::values('Original');
        $after = self::values('Saved');
        $_SESSION = [];
        $saved = runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $owner, 1, $after));
        phase2AssertSame(true, $saved['changed'] ?? null, 'A changed save did not produce an Undo snapshot.');
        phase2AssertSame($before, $saved['previous'], 'Undo did not capture all three exact previous fields.');
        rememberEvidenceHubUndo($owner, 1, $saved, 1000);
        $page = takeImmediateEvidenceHubUndoFeedback($owner, 1000);
        phase2AssertSame('saved', $page['kind'], 'Immediate PRG page did not show Undo.');
        phase2Assert(preg_match('/\A[a-f0-9]{64}\z/D', $page['token']) === 1, 'Undo token is not cryptographically opaque.');
        $contract = self::contract();
        $model = buildAuthorizedEvidenceHubOwnerPageModel($database, $owner, ['contract' => ['recommendations' => []]]);
        ob_start();
        renderEvidenceHubOwnerPage($contract, $model, [], '', $page);
        $html = (string) ob_get_clean();
        phase2Assert(str_contains($html, 'Evidence saved successfully') && str_contains($html, 'Undo last evidence update'), 'Immediate success panel is missing.');
        phase2Assert(str_contains($html, 'method="POST"') && str_contains($html, 'name="undo_token"'), 'Undo is not POST-only.');
        phase2Assert(!str_contains($html, $before['problem']) && !str_contains($html, $before['personal_role']) && !str_contains($html, $before['measurable_outcome']), 'Prior Evidence leaked into HTML.');
        phase2AssertSame('none', takeImmediateEvidenceHubUndoFeedback($owner, 1001)['kind'], 'Refresh resurrected the Undo form.');
        phase2AssertSame('unavailable', executeImmediateEvidenceHubUndo($database, $owner, $page['token'], 1001), 'Refresh did not revoke the immediate action.');

        $saved = runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $owner, 1, self::values('Latest')));
        rememberEvidenceHubUndo($owner, 1, $saved, 2000);
        $token = takeImmediateEvidenceHubUndoFeedback($owner, 2000)['token'];
        phase2AssertSame('unavailable', executeImmediateEvidenceHubUndo($database, $other, $token, 2000), 'Cross-owner token was accepted.');
        phase2AssertSame('unavailable', executeImmediateEvidenceHubUndo($database, $owner, str_repeat('a', 64), 2000), 'Tampered token was accepted.');
        phase2AssertSame('undone', executeImmediateEvidenceHubUndo($database, $owner, $token, 2000), 'Direct Undo did not restore Evidence.');
        phase2AssertSame($after, findAuthorizedProjectEvidenceForEdit($database, $owner, 1)['values'], 'Undo did not restore all three saved fields exactly.');
        phase2AssertSame('unavailable', executeImmediateEvidenceHubUndo($database, $owner, $token, 2000), 'One-time token was reused.');
        $_SESSION[EVIDENCE_HUB_UNDO_FEEDBACK_KEY] = 'undone';
        phase2AssertSame('undone', takeImmediateEvidenceHubUndoFeedback($owner, 2001)['kind'], 'Undo completion feedback was lost.');
        ob_start();
        renderEvidenceHubOwnerPage($contract, $model, [], '', ['kind' => 'undone']);
        $undoneHtml = (string) ob_get_clean();
        phase2Assert(str_contains($undoneHtml, 'Evidence update undone') && str_contains($undoneHtml, 'The previous Evidence values were restored safely.') && !str_contains($undoneHtml, 'Undo last evidence update'), 'Undo completion panel retained its action.');

        $saved = runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $owner, 1, self::values('Conflict')));
        rememberEvidenceHubUndo($owner, 1, $saved, 3000);
        $token = takeImmediateEvidenceHubUndoFeedback($owner, 3000)['token'];
        $database->exec("UPDATE projects SET problem_statement = 'Changed after save', updated_at = '2099-01-01T00:00:00Z' WHERE id = 1");
        phase2AssertSame('conflict', executeImmediateEvidenceHubUndo($database, $owner, $token, 3000), 'Concurrent change was overwritten.');
        phase2AssertSame('Changed after save', findAuthorizedProjectEvidenceForEdit($database, $owner, 1)['values']['problem'], 'Conflict changed the row.');
        phase2AssertSame('unavailable', executeImmediateEvidenceHubUndo($database, $owner, $token, 3000), 'Conflict token remained reusable.');

        $saved = runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $owner, 1, self::values('Expiry')));
        rememberEvidenceHubUndo($owner, 1, $saved, 4000);
        $expiredToken = takeImmediateEvidenceHubUndoFeedback($owner, 4000)['token'];
        phase2AssertSame('unavailable', executeImmediateEvidenceHubUndo($database, $owner, $expiredToken, 4601), 'Expired token was accepted.');
        $first = runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $owner, 1, self::values('First')));
        rememberEvidenceHubUndo($owner, 1, $first, 5000);
        $oldToken = takeImmediateEvidenceHubUndoFeedback($owner, 5000)['token'];
        $newer = runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $owner, 1, self::values('Newer')));
        rememberEvidenceHubUndo($owner, 1, $newer, 5001);
        phase2AssertSame('unavailable', executeImmediateEvidenceHubUndo($database, $owner, $oldToken, 5001), 'Newer save retained an old Undo token.');
        $newToken = takeImmediateEvidenceHubUndoFeedback($owner, 5001)['token'];
        phase2AssertSame('undone', executeImmediateEvidenceHubUndo($database, $owner, $newToken, 5001), 'Newer save Undo failed.');

        $current = findAuthorizedProjectEvidenceForEdit($database, $owner, 1)['values'];
        $noop = runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $owner, 1, $current));
        phase2AssertSame(false, $noop['changed'], 'No-op save created a snapshot.');
        rememberEvidenceHubUndo($owner, 1, $noop, 6000);
        phase2AssertSame('saved_without_undo', takeImmediateEvidenceHubUndoFeedback($owner, 6000)['kind'], 'No-op save exposed Undo.');
        phase2AssertSame(null, $_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY] ?? null, 'No-op save retained raw previous Evidence.');
        try {
            runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $owner, 1, ['problem' => "Invalid\0text", 'personal_role' => '', 'measurable_outcome' => '']));
            throw new RuntimeException('Validation failure was accepted.');
        } catch (InvalidArgumentException) {
            phase2AssertSame($current, findAuthorizedProjectEvidenceForEdit($database, $owner, 1)['values'], 'Validation failure changed Evidence.');
        }
        $database->exec("CREATE TRIGGER block_evidence_update BEFORE UPDATE ON projects WHEN NEW.id = 1 BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        try {
            runDatabaseTransaction($database, static fn (): ?array => saveAuthorizedProjectEvidenceWithUndo($database, $owner, 1, self::values('Failed')));
            throw new RuntimeException('Database failure was accepted.');
        } catch (PDOException) {
            phase2AssertSame($current, findAuthorizedProjectEvidenceForEdit($database, $owner, 1)['values'], 'Database failure was not rolled back.');
            phase2AssertSame(null, $_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY] ?? null, 'Database failure created Undo.');
        }
        $database->exec('DROP TRIGGER block_evidence_update');
        $_SESSION = [];
    }

    private static function routeGuards(): void
    {
        $route = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/owner_evidence_hub.php');
        $editor = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/owner_project_evidence.php');
        $session = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/includes/session.php');
        phase2Assert(str_contains($route, 'requireOwnerPortfolioContext($database)') && str_contains($route, 'requireValidCsrfToken') && str_contains($route, "=== 'POST'") && str_contains($route, 'executeImmediateEvidenceHubUndo') && str_contains($route, "httpRedirect('/owner/evidence-hub', 303)"), 'Undo route lost Owner, CSRF, POST, or PRG guards.');
        phase2Assert(str_contains($editor, 'rememberEvidenceHubUndo') && str_contains($editor, 'runDatabaseTransaction'), 'Editor save is not transactional or page scoped.');
        phase2Assert(str_contains($session, "'httponly' => true") && str_contains($session, "'samesite' => 'Lax'") && str_contains($session, 'session_name($sessionName)'), 'Owner cookie must carry only a protected session identifier.');
        phase2Assert(!str_contains($route . $editor, 'localStorage') && !str_contains($route . $editor, 'sessionStorage') && !str_contains($route . $editor, 'confirm('), 'Undo uses browser storage or a confirmation dialog.');
    }

    /** @return array{problem:string,personal_role:string,measurable_outcome:string} */
    private static function values(string $tag): array
    {
        return [
            'problem' => $tag . ': Manual weekly reporting delayed operational decisions because the team reconciled multiple sources by hand.',
            'personal_role' => $tag . ': I designed the reporting pipeline and implemented validation checks across each imported data source.',
            'measurable_outcome' => $tag . ': Reduced weekly reporting time from six hours to one hour for the operations team.',
        ];
    }

    private static function database(): PDO
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL); CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, title TEXT NOT NULL, description TEXT NOT NULL, problem_statement TEXT NULL, personal_role TEXT NULL, measurable_outcome TEXT NULL, technologies TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NULL); INSERT INTO portfolios VALUES (10,1),(20,2)');
        $values = self::values('Original');
        $statement = $database->prepare('INSERT INTO projects (id,portfolio_id,title,description,problem_statement,personal_role,measurable_outcome,technologies,created_at,updated_at) VALUES (1,10,\'Synthetic project\',\'Private\',:problem,:personal_role,:measurable_outcome,\'["Excel"]\',\'2026-01-01T00:00:00Z\',\'2026-01-02T00:00:00Z\')');
        $statement->execute($values);
        return $database;
    }

    /** @return array<string,mixed> */
    private static function contract(): array
    {
        $fixtures = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-hub-golden-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($fixtures['positive_payloads'] as $case) {
            if ($case['id'] === 'PAYLOAD-PARTIAL') return $case['payload'];
        }
        throw new RuntimeException('Synthetic contract is unavailable.');
    }
}
