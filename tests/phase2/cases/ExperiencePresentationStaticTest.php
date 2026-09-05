<?php

declare(strict_types=1);

final class ExperiencePresentationStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $presentation = self::read('includes/portfolio_presentation.php');
        $stylesheet = self::read('portfolio.css');
        $script = self::read('portfolio.js');

        foreach ([
            'portfolioPresentationExperienceTypeLabel',
            'portfolioPresentationExperienceMonth',
            'portfolioPresentationExperienceDateRange',
            'id="experience"',
            'portfolio-experience-list',
            'portfolio-closing-layout',
            'portfolio-experience-topline',
            'portfolio-experience-date',
            '<ol class="portfolio-experience-list"',
            'portfolio-experience-item--current',
            'portfolio-experience-description',
            'PORTFOLIO_EXPERIENCE_PAGE_SIZE = 5',
            'data-experience-page-size',
            'data-experience-pagination',
            'aria-live="polite"',
            'Older experiences',
            'Newer experiences',
        ] as $required) {
            phase2Assert(str_contains($presentation, $required), "Experience presentation is missing {$required}.");
        }
        foreach (['.portfolio-closing-layout', 'align-items: stretch', 'height: 100%', '.portfolio-experience-list', '.portfolio-experience-item::before', '.portfolio-experience-item:not(.portfolio-experience-item--visible-last)::after', '.portfolio-experience-content', '.portfolio-experience-topline', '.portfolio-experience-date', '.portfolio-experience-pagination', '.portfolio-experience-pagination[hidden] { display: none; }', 'margin-top: auto'] as $required) {
            phase2Assert(str_contains($stylesheet, $required), "Experience styling is missing {$required}.");
        }
        phase2Assert(!str_contains($stylesheet, '.portfolio-experience-list::before'), 'Experience must not use a list-wide connector that extends below the final marker.');
        phase2Assert(str_contains($script, 'initializeExperiencePagination') && str_contains($script, 'items.length <= pageSize') && str_contains($script, 'item.hidden = !visible') && str_contains($script, 'portfolio-experience-item--visible-last') && str_contains($script, 'Showing ${start + 1}–${end} of ${items.length}') && str_contains($script, 'pagination.addEventListener(\'click\''), 'Experience pagination must be a progressive, delegated enhancement with an accurate live range.');
        phase2Assert(str_contains($presentation, 'href="#experience" data-portfolio-section="experience">Experience</a>'), 'Experience navigation must use the generic implemented-section anchor contract.');
        phase2Assert(!str_contains($presentation, 'portfolio-nav-placeholder" aria-disabled="true" title="Coming soon">Experience</span>'), 'Experience navigation must no longer be disabled after acceptance.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/portfolio_presentation.php';

        foreach ([
            'employment' => 'Employment',
            'training' => 'Training',
            'internship' => 'Internship',
            'volunteer' => 'Volunteer',
            'leadership' => 'Leadership',
            'unsupported' => 'Experience',
        ] as $type => $label) {
            phase2Assert(portfolioPresentationExperienceTypeLabel($type) === $label, 'Experience type labels must use only supported labels and fail closed.');
        }

        $experiences = [
            [
                'experience_type' => 'employment',
                'role_title' => '<Data Analyst>',
                'organization' => 'Northstar Analytics',
                'location' => 'Amman, Jordan',
                'start_month' => '2025-09',
                'end_month' => null,
                'is_current' => true,
                'description' => 'Analyze <data> and reporting workflows.',
            ],
            [
                'experience_type' => 'internship',
                'role_title' => 'Machine Learning Intern',
                'organization' => 'Vertex Labs',
                'location' => null,
                'start_month' => '2025-03',
                'end_month' => '2025-08',
                'is_current' => false,
                'description' => null,
            ],
            [
                'experience_type' => 'training',
                'role_title' => 'Data Analytics Trainee',
                'organization' => 'Digital Skills Academy',
                'location' => 'Remote',
                'start_month' => '2024-10',
                'end_month' => '2025-02',
                'is_current' => false,
                'description' => 'Training description',
            ],
        ];

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Experience Owner'], [], [], ['experiences' => $experiences]);
        $rendered = ob_get_clean();
        phase2Assert(is_string($rendered), 'Experience presentation did not render output.');
        phase2Assert(substr_count($rendered, 'portfolio-experience-item') >= 3, 'All Experience records must render in the semantic list.');
        phase2Assert(str_contains($rendered, 'Employment') && str_contains($rendered, 'Internship') && str_contains($rendered, 'Training'), 'Supported Experience types must use safe human labels.');
        phase2Assert(str_contains($rendered, '&lt;Data Analyst&gt;') && str_contains($rendered, 'Analyze &lt;data&gt;'), 'Experience text must be escaped in public presentation.');
        phase2Assert(str_contains($rendered, 'Sep 2025 — Present') && str_contains($rendered, 'Mar 2025 — Aug 2025') && str_contains($rendered, 'Oct 2024 — Feb 2025'), 'Experience month precision must format deterministically.');
        phase2Assert(!preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{1,2}, \d{4}\b/', $rendered), 'Experience presentation must not fabricate day precision.');
        phase2Assert(!str_contains($rendered, 'months') && !str_contains($rendered, 'years'), 'Experience presentation must not calculate duration.');
        phase2Assert(preg_match('/portfolio-experience-topline.*?Employment.*?Sep 2025 — Present/s', $rendered) === 1 && preg_match('/Northstar Analytics<span aria-hidden="true">&nbsp;·<\/span><\/span>\s*<span>Amman, Jordan<\/span>/', $rendered) === 1 && preg_match('/Vertex Labs<\/span>\s*<\/p>/', $rendered) === 1, 'Experience date placement or optional organization/location separators are incorrect.');
        phase2Assert(substr_count($rendered, 'Present') === 1, 'Present must render only for an explicitly current Experience.');
        phase2Assert(str_contains($rendered, 'aria-labelledby="experience-title"') && str_contains($rendered, '<ol class="portfolio-experience-list" id="portfolio-experience-list" data-experience-page-size="5">') && !str_contains($rendered, 'data-experience-pagination'), 'Experience section must preserve semantic heading/list structure without pagination for five or fewer records.');
        $experienceSection = preg_match('/<section class="portfolio-section portfolio-closing-panel portfolio-experience".*?<\/section>/s', $rendered, $sectionMatch) === 1 ? $sectionMatch[0] : '';
        phase2Assert(!str_contains($experienceSection, '<a ') && !str_contains($experienceSection, '<img '), 'Experience presentation must not invent links or logos.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'One Experience'], [], [], ['experiences' => [$experiences[0]]]);
        $singleRendered = ob_get_clean();
        phase2Assert(is_string($singleRendered) && substr_count($singleRendered, 'portfolio-experience-item') >= 1 && str_contains($stylesheet, '.portfolio-experience-item:not(.portfolio-experience-item--visible-last)::after'), 'A single Experience must render without requiring a connector beyond its only marker.');

        $paginationExperiences = [];
        for ($index = 1; $index <= 11; ++$index) {
            $paginationExperiences[] = [...$experiences[0], 'role_title' => "Role {$index}", 'start_month' => '2025-09'];
        }
        foreach ([5, 6, 10, 11] as $count) {
            ob_start();
            renderPortfolioPresentation(['full_name' => 'Pagination Owner'], [], [], ['experiences' => array_slice($paginationExperiences, 0, $count)]);
            $paginationRendered = ob_get_clean();
            phase2Assert(is_string($paginationRendered) && substr_count($paginationRendered, 'portfolio-experience-content') === $count, "All {$count} Experience records must remain in the no-JavaScript fallback.");
            if ($count <= 5) {
                phase2Assert(!str_contains($paginationRendered, 'data-experience-pagination'), "Experience pagination controls must be omitted for {$count} records.");
            } else {
                phase2Assert(str_contains($paginationRendered, 'data-experience-pagination hidden') && str_contains($paginationRendered, 'aria-controls="portfolio-experience-list"') && !preg_match('/<li[^>]+hidden/', $paginationRendered), "Experience pagination controls must enhance, not replace, the {$count}-record fallback.");
            }
        }

        ob_start();
        renderPortfolioPresentation(['full_name' => 'No Experience'], [], [], ['experiences' => []]);
        $emptyRendered = ob_get_clean();
        phase2Assert(is_string($emptyRendered) && !str_contains($emptyRendered, 'id="experience"') && !str_contains($emptyRendered, 'portfolio-experience-list') && str_contains($emptyRendered, 'portfolio-closing-layout--contact-only'), 'Empty Experience collections must omit the Experience panel while expanding Contact.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
