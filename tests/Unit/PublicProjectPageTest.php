<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Case study page (B-05, docs/contracts/public-project-page.md). HTTP behavior: run-phase2-publication-lifecycle-e2e.php. */
final class PublicProjectPageTest extends TestCase
{
    private PDO $database;

    public static function setUpBeforeClass(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_preview_project.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/public_project_presentation.php';
    }

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->database->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY, account_status TEXT NOT NULL);
             CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER NOT NULL, public_slug TEXT, is_published INTEGER NOT NULL);
             CREATE TABLE personal_info (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL UNIQUE, full_name TEXT, professional_title TEXT, hero_headline TEXT, location TEXT, about_me TEXT,
                work_description TEXT, linkedin_url TEXT, github_url TEXT, instagram_url TEXT, facebook_url TEXT, website_url TEXT, profile_image_path TEXT,
                email TEXT, public_email_visible INTEGER NOT NULL DEFAULT 0, phone_primary TEXT, public_phone_visible INTEGER NOT NULL DEFAULT 0,
                phone_secondary TEXT, updated_at TEXT, profile_version INTEGER, country_code TEXT, preferred_language TEXT, professional_status TEXT, specialization TEXT);
             CREATE TABLE projects (id INTEGER PRIMARY KEY, portfolio_id INTEGER NOT NULL, is_published INTEGER NOT NULL DEFAULT 0, title TEXT, category TEXT,
                description TEXT, github_url TEXT, live_url TEXT, image_path TEXT, technologies TEXT, created_at TEXT NOT NULL);
             INSERT INTO users (id, account_status) VALUES (1, 'active'), (2, 'active'), (3, 'disabled');
             INSERT INTO portfolios (id, owner_user_id, public_slug, is_published) VALUES (10, 1, 'jane-doe', 1), (20, 2, 'other-owner', 1), (30, 3, 'disabled-owner', 1);
             INSERT INTO personal_info (portfolio_id, full_name, professional_title, email) VALUES
                (10, 'Jane Doe', 'Data Analyst', 'private@example.test'), (20, 'Other Owner', '', NULL), (30, 'Disabled Owner', '', NULL);
             INSERT INTO projects (id, portfolio_id, is_published, title, category, description, github_url, live_url, image_path, technologies, created_at) VALUES
                (100, 10, 1, 'HR <Dashboard>', 'Data', 'Line one\nLine two', 'https://github.com/jane/hr', 'https://app.powerbi.com/view?r=1', 'portfolios/10/projects/p.png', '[\"Power BI\", \"SQL\"]', '2026-01-02'),
                (101, 10, 1, 'No links', '', '', 'javascript:alert(1)', 'http://127.0.0.1/admin', NULL, '[]', '2026-01-01'),
                (102, 10, 0, 'Draft', 'Data', 'Not yet', NULL, 'https://draft.example.test/', NULL, '[]', '2026-01-03'),
                (200, 20, 1, 'Other tenant', 'Web', 'Foreign', NULL, NULL, NULL, '[]', '2026-01-04'),
                (300, 30, 1, 'Disabled', 'Web', 'Disabled account', NULL, NULL, NULL, '[]', '2026-01-05');"
        );
    }

    public function testPublishedProjectGivesTheContractShapeWithCheckedLinks(): void
    {
        $page = publicProjectPageData($this->database, 'jane-doe', '100');

        self::assertNotNull($page);
        self::assertSame(['preview', 'draft', 'owner', 'project', 'portfolio_url', 'canonical_url'], array_keys($page));
        self::assertSame(['id', 'title', 'category', 'description', 'technologies', 'live_url', 'github_url', 'image_url'], array_keys($page['project']));
        self::assertSame(['name' => 'Jane Doe', 'professional_title' => 'Data Analyst'], $page['owner'], 'Only the name and title; no contact details');
        self::assertSame(100, $page['project']['id']);
        self::assertSame(['Power BI', 'SQL'], $page['project']['technologies']);
        self::assertSame('https://app.powerbi.com/view?r=1', $page['project']['live_url']);
        self::assertSame('https://github.com/jane/hr', $page['project']['github_url']);
        self::assertSame('/p/jane-doe/media/project/100', $page['project']['image_url'], 'Images go through the scoped public media route');
        self::assertSame('/p/jane-doe', $page['portfolio_url']);
        self::assertFalse($page['preview']);
        self::assertFalse($page['draft']);
        self::assertStringNotContainsString('portfolios/10', json_encode($page, JSON_THROW_ON_ERROR), 'No storage key reaches the page');
    }

    public function testUnsafeOrLocalLinksAndMissingImageBecomeNull(): void
    {
        $page = publicProjectPageData($this->database, 'jane-doe', 101);

        self::assertNotNull($page);
        self::assertNull($page['project']['github_url']);
        self::assertNull($page['project']['live_url'], 'Local addresses never reach visitors');
        self::assertNull($page['project']['image_url']);
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function hiddenProjects(): iterable
    {
        yield 'draft project' => ['jane-doe', '102'];
        yield 'another portfolio\'s project through this slug' => ['jane-doe', '200'];
        yield 'disabled account' => ['disabled-owner', '300'];
        yield 'unknown id' => ['jane-doe', '999'];
        yield 'unknown slug' => ['nobody-here', '100'];
        yield 'malformed slug' => ['Jane_Doe', '100'];
        yield 'zero id' => ['jane-doe', '0'];
        yield 'array id' => ['jane-doe', ['100']];
        yield 'missing id' => ['jane-doe', null];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hiddenProjects')]
    public function testEverythingElseIsNotFound(mixed $slug, mixed $id): void
    {
        self::assertNull(publicProjectPageData($this->database, $slug, $id));
    }

    public function testUnpublishedPortfolioOrMissingNameHidesEveryProject(): void
    {
        $this->database->exec('UPDATE portfolios SET is_published = 0 WHERE id = 10');
        self::assertNull(publicProjectPageData($this->database, 'jane-doe', '100'));
        $this->database->exec("UPDATE portfolios SET is_published = 1 WHERE id = 10; UPDATE personal_info SET full_name = '  ' WHERE portfolio_id = 10");
        self::assertNull(publicProjectPageData($this->database, 'jane-doe', '100'), 'Same rule as the portfolio page');
    }

    public function testOwnerPreviewShowsOwnDraftsButNeverAnotherOwnersProject(): void
    {
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);

        $draft = ownerPreviewProjectPageData($this->database, $context, '102');
        self::assertNotNull($draft);
        self::assertTrue($draft['preview']);
        self::assertTrue($draft['draft']);
        self::assertNull($draft['canonical_url']);
        self::assertSame('/owner_preview.php', $draft['portfolio_url']);
        self::assertSame('https://draft.example.test/', $draft['project']['live_url']);

        $published = ownerPreviewProjectPageData($this->database, $context, '100');
        self::assertNotNull($published);
        self::assertFalse($published['draft']);
        self::assertSame('/owner_media.php?type=project&id=100', $published['project']['image_url']);

        self::assertNull(ownerPreviewProjectPageData($this->database, $context, '200'), 'Foreign project');
        self::assertNull(ownerPreviewProjectPageData($this->database, $context, 'x'));
    }

    public function testBaselineTemplateEscapesContentAndOpensLinksSafely(): void
    {
        ob_start();
        renderPublicProjectPage(publicProjectPageData($this->database, 'jane-doe', '100') ?? []);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('<html lang="en">', $html, 'Visitors always see English');
        self::assertStringContainsString('HR &lt;Dashboard&gt;', $html);
        self::assertStringNotContainsString('<Dashboard>', $html);
        self::assertStringContainsString('Line one<br />', $html);
        self::assertStringContainsString('href="https://app.powerbi.com/view?r=1" rel="noopener noreferrer"', $html);
        self::assertStringContainsString('href="/p/jane-doe#projects"', $html);
        self::assertStringNotContainsString('private@example.test', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('style=', $html, 'CSP: no inline styles');

        ob_start();
        renderPublicProjectPage(publicProjectPageData($this->database, 'jane-doe', '101') ?? []);
        $html = (string) ob_get_clean();
        self::assertStringContainsString('No public link has been added for this project.', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('127.0.0.1', $html);

        ob_start();
        renderPublicProjectPage(ownerPreviewProjectPageData($this->database, AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10), '102') ?? []);
        $html = (string) ob_get_clean();
        self::assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        self::assertStringContainsString('Draft: visitors cannot see this project until you publish it.', $html);
        self::assertStringNotContainsString('rel="canonical"', $html);
    }
}
