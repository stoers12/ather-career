<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Overview data for the redesigned dashboard (decision ق-33, docs/contracts/owner-dashboard.md). */
final class OwnerDashboardModelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_dashboard_model.php';
    }

    private static function project(int $id, bool $published, string $title = 'Project'): array
    {
        return ['id' => (string) $id, 'title' => $title . ' ' . $id, 'category' => 'Data', 'is_published' => $published ? '1' : '0'];
    }

    private static function evidence(int $id, ?string $problem, ?string $role, ?string $outcome): array
    {
        return ['id' => (string) $id, 'problem_statement' => $problem, 'personal_role' => $role, 'measurable_outcome' => $outcome];
    }

    public function testCountsComeFromTheOwnersRows(): void
    {
        $overview = ownerDashboardOverview(
            [self::project(3, true), self::project(2, false), self::project(1, true)],
            [self::evidence(3, 'P', 'R', 'O'), self::evidence(2, 'P', null, ''), self::evidence(1, null, null, null)],
        );

        self::assertSame(['total' => 3, 'published' => 2, 'drafts' => 1, 'needs_writing' => 2], array_diff_key($overview, ['recent' => true]));
        self::assertSame([3, 1, 0], array_column($overview['recent'], 'written_parts'));
        self::assertSame([3, 2, 1], array_column($overview['recent'], 'id'), 'Recent keeps the repository order: newest first');
        self::assertSame([true, false, true], array_column($overview['recent'], 'published'));
    }

    public function testWhitespaceIsNotWrittenEvidence(): void
    {
        $overview = ownerDashboardOverview([self::project(1, true)], [self::evidence(1, "  \n", 'Role', "\t")]);

        self::assertSame(1, $overview['recent'][0]['written_parts']);
        self::assertSame(1, $overview['needs_writing']);
    }

    public function testAProjectWithoutAnEvidenceRowNeedsWriting(): void
    {
        $overview = ownerDashboardOverview([self::project(7, false)], []);

        self::assertSame(0, $overview['recent'][0]['written_parts']);
        self::assertSame(1, $overview['needs_writing']);
    }

    public function testRecentIsLimitedButCountsAreNot(): void
    {
        $projects = array_map(static fn (int $id): array => self::project($id, true), range(6, 1));
        $overview = ownerDashboardOverview($projects, [], 3);

        self::assertCount(3, $overview['recent']);
        self::assertSame(6, $overview['total']);
    }

    public function testOwnerTextIsReturnedUnchangedForTheTemplateToEscape(): void
    {
        $overview = ownerDashboardOverview([self::project(1, true, '<script>alert(1)</script>')], []);

        self::assertSame('<script>alert(1)</script> 1', $overview['recent'][0]['title']);
    }

    public function testNextStepFollowsPriorityOrder(): void
    {
        self::assertSame('first_project', ownerDashboardNextStep(['total' => 0, 'drafts' => 0, 'needs_writing' => 0]));
        self::assertSame('write_evidence', ownerDashboardNextStep(['total' => 2, 'drafts' => 1, 'needs_writing' => 1]));
        self::assertSame('publish_drafts', ownerDashboardNextStep(['total' => 2, 'drafts' => 1, 'needs_writing' => 0]));
        self::assertSame('preview', ownerDashboardNextStep(['total' => 2, 'drafts' => 0, 'needs_writing' => 0]));
    }
}
