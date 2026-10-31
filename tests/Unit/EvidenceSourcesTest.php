<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Evidence sources (B-06, docs/contracts/evidence-sources.md). Contract v10:
 * EvidenceHubContractV10Test. MySQL and HTTP behavior: check-evidence-sources-http.php.
 */
final class EvidenceSourcesTest extends TestCase
{
    private PDO $database;
    private AuthorizedPortfolioContext $owner;

    public static function setUpBeforeClass(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/csrf.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_project_evidence_presentation.php';
    }

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->database->exec(
            "CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, title TEXT NOT NULL);
             CREATE TABLE project_evidence_sources (id INTEGER PRIMARY KEY AUTOINCREMENT, portfolio_id INTEGER NOT NULL, project_id INTEGER NOT NULL,
                title TEXT NOT NULL, url TEXT NOT NULL, note TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
             INSERT INTO projects VALUES (1, 10, 'Mine'), (2, 10, 'Also mine'), (3, 20, 'Foreign');"
        );
        $this->owner = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidSubmissions(): iterable
    {
        yield 'empty title' => [['title' => '   ', 'url' => 'https://example.test'], 'title'];
        yield 'long title' => [['title' => str_repeat('a', 121), 'url' => 'https://example.test'], 'title'];
        yield 'structured title' => [['title' => ['x'], 'url' => 'https://example.test'], 'title'];
        yield 'missing url' => [['title' => 'Report'], 'url'];
        yield 'javascript url' => [['title' => 'Report', 'url' => 'javascript:alert(1)'], 'url'];
        yield 'ftp url' => [['title' => 'Report', 'url' => 'ftp://example.test/file'], 'url'];
        yield 'local url' => [['title' => 'Report', 'url' => 'http://localhost:8080/admin'], 'url'];
        yield 'loopback url' => [['title' => 'Report', 'url' => 'http://127.0.0.1/'], 'url'];
        yield 'long url' => [['title' => 'Report', 'url' => 'https://example.test/' . str_repeat('a', 480)], 'url'];
        yield 'long note' => [['title' => 'Report', 'url' => 'https://example.test', 'note' => str_repeat('n', 301)], 'note'];
    }

    /** @param array<string, mixed> $post */
    #[DataProvider('invalidSubmissions')]
    public function testInvalidSubmissionsAreRejectedPerField(array $post, string $field): void
    {
        $result = validateProjectEvidenceSourceSubmission($post);

        self::assertNull($result['source']);
        self::assertArrayHasKey($field, $result['errors']);
    }

    public function testValidSubmissionIsTrimmedAndAnEmptyNoteIsNull(): void
    {
        $result = validateProjectEvidenceSourceSubmission(['title' => '  Power BI report ', 'url' => ' https://app.powerbi.com/view?r=1 ', 'note' => '  ']);

        self::assertSame([], $result['errors']);
        self::assertSame(['title' => 'Power BI report', 'url' => 'https://app.powerbi.com/view?r=1', 'note' => null], $result['source']);
    }

    public function testAddListAndRemoveStayInsideTheOwnersProject(): void
    {
        $id = $this->add(1, 'Report');
        $this->add(2, 'Other project');
        $this->database->exec("INSERT INTO project_evidence_sources (portfolio_id, project_id, title, url) VALUES (20, 3, 'Foreign', 'https://example.test/f')");

        self::assertSame([['id' => $id, 'title' => 'Report', 'url' => 'https://example.test/report', 'note' => null]], listAuthorizedProjectEvidenceSources($this->database, $this->owner, 1));
        self::assertSame([], listAuthorizedProjectEvidenceSources($this->database, $this->owner, 3), 'Another Portfolio\'s sources are invisible');
        self::assertSame([1, 2], array_keys(listAuthorizedPortfolioEvidenceSources($this->database, $this->owner)));

        $this->database->beginTransaction();
        self::assertNull(addAuthorizedProjectEvidenceSource($this->database, $this->owner, 3, ['title' => 'x', 'url' => 'https://example.test', 'note' => null]), 'A foreign project is not found');
        $this->database->commit();

        self::assertFalse(removeAuthorizedProjectEvidenceSource($this->database, $this->owner, 1, 3), 'A foreign source is not removed');
        self::assertFalse(removeAuthorizedProjectEvidenceSource($this->database, $this->owner, 2, $id), 'A source is removed only through its own project');
        self::assertTrue(removeAuthorizedProjectEvidenceSource($this->database, $this->owner, 1, $id));
        self::assertSame([], listAuthorizedProjectEvidenceSources($this->database, $this->owner, 1));
    }

    public function testTheTenthSourceIsTheLast(): void
    {
        for ($i = 1; $i <= PROJECT_EVIDENCE_SOURCE_LIMIT; ++$i) {
            $this->add(1, 'Source ' . $i);
        }
        $this->expectException(ProjectEvidenceSourceLimitException::class);
        $this->add(1, 'Eleventh');
    }

    public function testAddingRequiresTheCallersTransaction(): void
    {
        $this->expectException(LogicException::class);
        addAuthorizedProjectEvidenceSource($this->database, $this->owner, 1, ['title' => 'x', 'url' => 'https://example.test', 'note' => null]);
    }

    public function testTemplateEscapesOwnerTextAndHidesTheFormAtTheLimit(): void
    {
        $_SESSION = ['csrf_token' => str_repeat('a', 64)];
        $sources = [['id' => 7, 'title' => '<b>Report</b>', 'url' => 'https://example.test/r?a=1&b=2', 'note' => '"quoted" note']];

        ob_start();
        renderOwnerProjectEvidenceSources(1, $sources, ['title' => '', 'url' => '', 'note' => ''], [], 'ar');
        $html = (string) ob_get_clean();
        self::assertStringContainsString('&lt;b&gt;Report&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>Report', $html);
        self::assertStringContainsString('href="https://example.test/r?a=1&amp;b=2" rel="noopener noreferrer"', $html);
        self::assertStringContainsString('&quot;quoted&quot; note', $html);
        self::assertStringContainsString('name="action" value="add_source"', $html);
        self::assertStringContainsString('name="source_id" value="7"', $html);
        self::assertStringContainsString('المصادر', $html, 'Arabic owner copy');
        self::assertStringContainsString('لم تُفحص', $html, 'Sources are labelled as unverified');

        $full = array_map(static fn (int $id): array => ['id' => $id, 'title' => 'S' . $id, 'url' => 'https://example.test/' . $id, 'note' => null], range(1, PROJECT_EVIDENCE_SOURCE_LIMIT));
        ob_start();
        renderOwnerProjectEvidenceSources(1, $full, ['title' => '', 'url' => '', 'note' => ''], ['source_limit' => 'This project already has 10 sources. Remove one before adding another.'], 'en');
        $html = (string) ob_get_clean();
        self::assertStringNotContainsString('value="add_source"', $html, 'No add form once the limit is reached');
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('10 of 10 sources', $html);
    }

    public function testEveryNewOwnerStringHasArabicCopy(): void
    {
        $presentation = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/includes/owner_project_evidence_presentation.php');
        $start = strpos($presentation, 'function renderOwnerProjectEvidenceSources');
        $end = strpos($presentation, 'function renderOwnerProjectEvidenceStarter');
        self::assertNotFalse($start);
        preg_match_all("/\\\$e\\('((?:[^'\\\\]|\\\\.)*)'/", substr($presentation, $start, $end - $start), $matches);
        $repository = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/includes/project_evidence_source_repository.php');
        preg_match_all("/'((?:Please|Keep|Give)[^']*)'/", $repository, $errors);
        $handler = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/handlers/owner_project_evidence.php');
        preg_match_all("/'(This project already has[^']*)'/", $handler, $limit);
        $strings = array_unique([...$matches[1], ...$errors[1], ...$limit[1], 'Add a source', 'Manage sources', 'Add a source to your evidence', 'Evidence sources']);
        self::assertGreaterThan(15, count($strings));
        foreach ($strings as $english) {
            self::assertArrayHasKey(stripslashes($english), evidenceHubArabicCopy(), "Missing Arabic copy: {$english}");
        }
    }

    public function testSourcesNeverReachAPublicRead(): void
    {
        foreach (['includes/public_lifecycle.php', 'includes/public_project_page.php', 'includes/public_project_presentation.php', 'includes/portfolio_presentation.php',
            'handlers/public_portfolio.php', 'handlers/public_projects_json.php', 'handlers/public_project.php', 'handlers/owner_preview.php'] as $file) {
            $source = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $file);
            self::assertStringNotContainsString('project_evidence_source', $source, "{$file} reads evidence sources");
            self::assertStringNotContainsString('EvidenceSource', $source, "{$file} reads evidence sources");
        }
    }

    public function testHandlerChecksCsrfBeforeAnySourceWrite(): void
    {
        $handler = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/handlers/owner_project_evidence.php');
        $order = [
            'requireOwnerPortfolioContext($database)',
            "array_key_exists('action', \$_POST)",
            "requireValidCsrfToken(\$_POST['csrf_token'] ?? null);",
            "if (\$action === 'add_source') {",
            'addAuthorizedProjectEvidenceSource($database, $context, $projectId',
            "} elseif (\$action === 'remove_source') {",
            'removeAuthorizedProjectEvidenceSource($database, $context, $projectId, $sourceId)',
        ];
        $position = -1;
        foreach ($order as $step) {
            $next = strpos($handler, $step, $position + 1);
            self::assertNotFalse($next, "Missing: {$step}");
            $position = $next;
        }
    }

    public function testMigrationIsAdditiveAndCascadesWithItsProject(): void
    {
        $sql = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/database/migrations/027_project_evidence_sources.sql');
        self::assertStringContainsString('CREATE TABLE project_evidence_sources (', $sql);
        self::assertStringContainsString('REFERENCES projects (id)' . "\n" . '        ON UPDATE RESTRICT ON DELETE CASCADE', $sql);
        self::assertStringNotContainsString('ALTER TABLE', $sql);
        self::assertStringNotContainsString('DROP', $sql);
        $ledger = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/Integration/check-restored-startup-idempotence.sh');
        self::assertStringContainsString(",026,027", $ledger);
    }

    private function add(int $projectId, string $title): int
    {
        $this->database->beginTransaction();
        try {
            $id = addAuthorizedProjectEvidenceSource($this->database, $this->owner, $projectId, ['title' => $title, 'url' => 'https://example.test/' . strtolower(strtok($title, ' ')), 'note' => null]);
            $this->database->commit();
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
        self::assertIsInt($id);

        return $id;
    }
}
