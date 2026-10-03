<?php

declare(strict_types=1);

final class ExperienceCapabilityTest
{
    public static function run(TestEnvironment $environment): void
    {
        $migration = self::read('database/migrations/007_experiences.sql');
        $experience = self::read('includes/experience.php');
        $scopedData = self::read('includes/portfolio_scoped_data.php');
        $ownerActions = self::read('includes/owner_actions.php');
        $ownerRoute = self::read('owner_experiences.php');
        $publicLifecycle = self::read('includes/public_lifecycle.php');
        $publicRoute = self::read('public_portfolio.php');
        $presentation = self::read('includes/portfolio_presentation.php');
        $header = self::read('includes/portfolio_presentation.php');

        foreach ([
            'CREATE TABLE experiences',
            'portfolio_id INT UNSIGNED NOT NULL',
            'experience_type VARCHAR(16)',
            'role_title VARCHAR(120)',
            'organization VARCHAR(160)',
            'location VARCHAR(160) NULL',
            'start_month CHAR(7)',
            'end_month CHAR(7)',
            'is_current TINYINT(1) NOT NULL DEFAULT 0',
            'description TEXT NULL',
            'created_at TIMESTAMP',
            'updated_at TIMESTAMP',
            'idx_experiences_portfolio_display',
            'fk_experiences_portfolio',
            'ON UPDATE RESTRICT ON DELETE RESTRICT',
            'chk_experiences_type',
            'chk_experiences_current_end',
            'chk_experiences_chronology',
        ] as $required) {
            phase2Assert(str_contains($migration, $required), "Experience migration is missing {$required}.");
        }
        phase2Assert(!str_contains($migration, 'sort_order') && !str_contains($migration, 'duration') && !str_contains($migration, 'years_'), 'Experience migration added unsupported ordering or duration data.');

        foreach ([
            "const EXPERIENCE_TYPE_VALUES = ['employment', 'training', 'internship', 'volunteer', 'leadership']",
            'function normalizeExperienceMonth',
            'function experienceValidationReferenceMonth',
            'function validateExperienceValues',
            'function authorizedExperienceValues',
            'checkdate',
            'End month cannot be earlier than start month.',
            'Start month cannot be later than the current month.',
            'End month cannot be later than the current month.',
            'End month must be empty for a current role.',
            'EXPERIENCE_DESCRIPTION_MAX_LENGTH = 1200',
            'utf8FieldLengthError',
        ] as $required) {
            phase2Assert(str_contains($experience, $required), "Experience validation is missing {$required}.");
        }
        phase2Assert(!str_contains($experience, 'date_create') && !str_contains($experience, 'DateTime') && !str_contains($experience, 'duration'), 'Experience data must preserve month precision without duration logic.');

        foreach ([
            'listAuthorizedExperiences',
            'findAuthorizedExperience',
            'createAuthorizedExperience',
            'updateAuthorizedExperience',
            'deleteAuthorizedExperience',
            'WHERE portfolio_id = :authorized_portfolio_id',
            'WHERE id = :resource_id',
            'AND portfolio_id = :authorized_portfolio_id',
            'ORDER BY start_month DESC, id DESC',
        ] as $required) {
            phase2Assert(str_contains($scopedData, $required), "Experience scoped data access is missing {$required}.");
        }
        phase2Assert(str_contains($ownerActions, 'handleAuthorizedExperienceAction') && str_contains($ownerActions, '?string $referenceMonth = null') && str_contains($ownerActions, 'findAuthorizedExperience($database, $context, $experienceId)') && str_contains($ownerActions, 'deleteAuthorizedExperience($database, $context, $experienceId)'), 'Experience owner mutations must be deterministic and scope records through the owner Portfolio.');
        phase2Assert(!str_contains($ownerActions, '$editingExperience[\'end_month\'] = \'\''), 'Experience validation errors must preserve an entered end month for correction.');
        phase2Assert(str_contains($ownerRoute, 'requireOwnerPortfolioContext($database)') && str_contains($ownerRoute, 'requireValidCsrfToken') && str_contains($ownerRoute, '$experienceMaximumMonth = experienceValidationReferenceMonth()') && str_contains($ownerRoute, 'type="month"') && substr_count($ownerRoute, 'max="<?php echo ownerEscapeHtml($experienceMaximumMonth); ?>"') === 2 && str_contains($ownerRoute, 'name="is_current"'), 'Experience owner route is missing owner authority, CSRF, bounded month controls, or current state.');
        phase2Assert(!str_contains($ownerRoute, 'sort_order') && !str_contains($ownerRoute, 'years of experience'), 'Experience owner route added unsupported controls.');

        phase2Assert(str_contains($publicLifecycle, 'function listPublicExperiences(PDO $database, PublicReadContext $context): array') && str_contains($publicLifecycle, 'WHERE portfolio_id = :public_portfolio_id') && str_contains($publicLifecycle, 'ORDER BY start_month DESC, id DESC') && !str_contains($publicLifecycle, 'ORDER BY is_current DESC'), 'Experience public read path is not safely Portfolio-scoped and deterministic.');
        phase2Assert(str_contains($publicRoute, 'listPublicExperiences($database, $context)') && str_contains($publicRoute, "'experiences' => \$experiences"), 'Public Portfolio route does not pass the structured Experience collection to presentation.');
        phase2Assert(str_contains($presentation, 'function portfolioPresentationExperiences(array $experiences): array') && str_contains($presentation, '$presentationExperiences = portfolioPresentationExperiences('), 'Presentation-ready Experience mapping is missing.');
        phase2Assert(str_contains($presentation, 'href="#experience" data-portfolio-section="experience">Experience</a>') && !str_contains($header, 'portfolio-nav-placeholder" aria-disabled="true" title="Coming soon">Experience</span>'), 'Experience navigation must use the implemented section anchor contract.');
        phase2Assert(!str_contains($presentation, 'Experience timeline') && !str_contains($presentation, 'Years Experience'), 'Experience presentation must not add duration claims.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/experience.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/portfolio_presentation.php';

        phase2AssertSame('2025-06', normalizeExperienceMonth('2025-06'), 'A valid calendar month was rejected.');
        phase2AssertSame(null, normalizeExperienceMonth(' 2025-06 '), 'Whitespace-padded month input must not be silently normalized.');
        phase2AssertSame('2026-09', experienceValidationReferenceMonth('2026-09'), 'The deterministic Experience validation reference month was not preserved.');
        foreach (['2025-00', '2025-13', '2025-6', '2025-06-01', 'not-a-month'] as $invalidMonth) {
            phase2AssertSame(null, normalizeExperienceMonth($invalidMonth), "Invalid month was accepted: {$invalidMonth}.");
        }

        $valid = [
            'experience_type' => 'internship',
            'role_title' => 'Data Intern',
            'organization' => 'Example Organization',
            'location' => 'Amman',
            'start_month' => '2025-06',
            'end_month' => '2025-09',
            'is_current' => false,
            'description' => 'Plain text only.',
        ];
        phase2AssertSame([], validateExperienceValues($valid, '2026-09'), 'A valid Experience record did not validate.');
        phase2AssertSame('2025-09', authorizedExperienceValues($valid, '2026-09')['end_month'], 'Validated Experience end month was not preserved.');
        phase2Assert(validateExperienceValues([...$valid, 'experience_type' => 'contractor'], '2026-09') !== [], 'Unsupported Experience type was accepted.');
        phase2Assert(validateExperienceValues([...$valid, 'start_month' => '2025-15'], '2026-09') !== [], 'Invalid start month was accepted.');
        phase2Assert(validateExperienceValues([...$valid, 'end_month' => '2025-05'], '2026-09') !== [], 'End month before start month was accepted.');
        phase2Assert(validateExperienceValues([...$valid, 'end_month' => '', 'is_current' => false], '2026-09') !== [], 'Ended Experience without an end month was accepted.');
        phase2AssertSame([], validateExperienceValues([...$valid, 'start_month' => '2026-09', 'end_month' => '2026-09'], '2026-09'), 'Current-month completed Experience was rejected.');
        phase2AssertSame([], validateExperienceValues([...$valid, 'start_month' => '2026-09', 'end_month' => '', 'is_current' => true], '2026-09'), 'Current-month current Experience was rejected.');
        phase2Assert(str_contains(implode(' ', validateExperienceValues([...$valid, 'start_month' => '2026-10'], '2026-09')), 'Start month cannot be later'), 'Future start month was accepted.');
        phase2Assert(validateExperienceValues([...$valid, 'start_month' => '2026-13'], '2026-09') !== [], 'Impossible start month was accepted.');
        phase2Assert(validateExperienceValues([...$valid, 'start_month' => '2026/09'], '2026-09') !== [], 'Malformed start month was accepted.');
        phase2Assert(str_contains(implode(' ', validateExperienceValues([...$valid, 'end_month' => '2026-10'], '2026-09')), 'End month cannot be later'), 'Future completed end month was accepted.');
        phase2Assert(validateExperienceValues([...$valid, 'end_month' => 'September 2026'], '2026-09') !== [], 'Malformed end month was accepted.');
        phase2Assert(str_contains(implode(' ', validateExperienceValues([...$valid, 'is_current' => true, 'end_month' => '2026-09'], '2026-09')), 'End month must be empty'), 'Current Experience accepted a supplied end month.');
        $currentValues = authorizedExperienceValues([...$valid, 'is_current' => true, 'end_month' => ''], '2026-09');
        phase2AssertSame(null, $currentValues['end_month'], 'Current Experience did not normalize an empty end month to null.');
        phase2AssertSame(1, $currentValues['is_current'], 'Current Experience flag was not normalized.');
        phase2Assert(validateExperienceValues([...$valid, 'role_title' => str_repeat('م', EXPERIENCE_ROLE_TITLE_MAX_LENGTH + 1)], '2026-09') !== [], 'Multibyte role title over the bound was accepted.');

        $mapped = portfolioPresentationExperiences([[
            'id' => 77,
            'portfolio_id' => 88,
            'experience_type' => 'internship',
            'role_title' => '<Data Intern>',
            'organization' => 'Example',
            'location' => '',
            'start_month' => '2025-06',
            'end_month' => '2025-09',
            'is_current' => false,
            'description' => '<script>alert(1)</script>',
            'created_at' => '2025-01-01',
        ]]);
        phase2AssertSame([[
            'experience_type' => 'internship',
            'role_title' => '<Data Intern>',
            'organization' => 'Example',
            'location' => null,
            'start_month' => '2025-06',
            'end_month' => '2025-09',
            'is_current' => false,
            'description' => '<script>alert(1)</script>',
        ]], $mapped, 'Presentation mapping exposed storage metadata or transformed untrusted text into markup semantics.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
