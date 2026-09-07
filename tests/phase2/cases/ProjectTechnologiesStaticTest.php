<?php

declare(strict_types=1);

final class ProjectTechnologiesStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $migration = self::read('database/migrations/006_project_technologies.sql');
        $technologies = self::read('includes/project_technologies.php');
        $scopedData = self::read('includes/portfolio_scoped_data.php');
        $lifecycle = self::read('includes/public_lifecycle.php');
        $ownerActions = self::read('includes/owner_actions.php');
        $projectActions = self::read('includes/project_actions.php');
        $ownerForm = self::read('owner_projects.php');

        phase2Assert(trim($migration) === 'ALTER TABLE projects ADD COLUMN technologies JSON NULL AFTER image_path;', 'S05C migration must add only the nullable native JSON Project technologies column.');
        phase2Assert(str_contains($technologies, 'PROJECT_TECHNOLOGIES_MAXIMUM = 12') && str_contains($technologies, 'PROJECT_TECHNOLOGY_MAX_LENGTH = 60') && str_contains($technologies, 'normalizeProjectTechnologies') && str_contains($technologies, 'projectTechnologiesFromStorage') && str_contains($technologies, 'JSON_THROW_ON_ERROR'), 'S05C technologies must have one bounded normalization and safe decode contract.');
        phase2Assert(str_contains($scopedData, 'technologies, created_at') && str_contains($scopedData, 'projectTechnologiesFromStorage') && str_contains($lifecycle, 'technologies, created_at') && str_contains($lifecycle, 'projectTechnologiesFromStorage'), 'S05C authorized and public Project mappings must select and safely decode technologies.');
        phase2Assert(str_contains($ownerActions, 'normalizeProjectTechnologies($technologiesInput, $errors)') && str_contains($projectActions, 'validateProjectImageUpload($file)') && str_contains($ownerForm, 'name="technologies"') && str_contains($ownerForm, 'One technology per line · Up to'), 'S05C Owner Project flow must retain one technologies contract and the shared Project validation helpers.');
        phase2Assert(!str_contains($ownerActions, 'listAuthorizedSkills'), 'S05C must not infer per-Project technologies from Portfolio-wide skills.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/project_technologies.php';
        $errors = [];
        phase2AssertSame(['Python', 'pandas', 'Scikit-learn'], normalizeProjectTechnologies(" Python\n pandas\nPYTHON\n\nScikit-learn ", $errors), 'S05C normalization must trim, remove blanks, de-duplicate case-insensitively, and preserve first-entered order.');
        phase2AssertSame([], $errors, 'S05C valid normalization emitted an unexpected error.');

        $errors = [];
        phase2AssertSame(null, normalizeProjectTechnologies(implode("\n", array_map(static fn (int $index): string => 'Tool ' . $index, range(1, 13))), $errors), 'S05C must reject more than 12 unique technologies.');
        phase2Assert($errors !== [], 'S05C count overflow must produce a validation error.');
        $errors = [];
        phase2AssertSame(null, normalizeProjectTechnologies(str_repeat('a', 61), $errors), 'S05C must reject labels longer than 60 UTF-8 characters.');
        phase2AssertSame(null, projectTechnologiesToStorage([]), 'S05C empty technologies must persist as NULL.');
        phase2AssertSame(['Café'], projectTechnologiesFromStorage('["Café"]'), 'S05C must preserve valid UTF-8 labels.');
        phase2AssertSame([], projectTechnologiesFromStorage('{"technology":"Python"}'), 'S05C malformed or unexpected stored JSON must fail closed.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
