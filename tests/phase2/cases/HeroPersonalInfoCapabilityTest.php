<?php

declare(strict_types=1);

final class HeroPersonalInfoCapabilityTest
{
    public static function run(TestEnvironment $environment): void
    {
        $migration = self::read('database/migrations/008_personal_info_hero_headline.sql');
        $validation = self::read('includes/validation.php');
        $ownerRoute = self::read('owner_profile.php');
        $ownerActions = self::read('includes/owner_actions.php');
        $scopedData = self::read('includes/portfolio_scoped_data.php');
        $publicData = self::read('includes/public_lifecycle.php');
        $presentation = self::read('includes/portfolio_presentation.php');

        phase2Assert(str_contains($migration, 'ADD COLUMN hero_headline VARCHAR(180) NULL DEFAULT NULL'), 'Hero headline migration must be additive, nullable, and default-free.');
        phase2Assert(str_contains($validation, "'hero_headline' => 180") && str_contains($validation, "'work_description' => 1200"), 'Hero headline or Professional summary UTF-8 limits are missing.');
        phase2Assert(str_contains($ownerRoute, 'name="hero_headline"') && str_contains($ownerRoute, 'Describe what you build, solve, or contribute') && str_contains($ownerRoute, 'Professional summary') && str_contains($ownerRoute, 'Add supporting context for your public Hero.'), 'Owner Personal Info does not expose the approved Hero fields and guidance.');
        phase2Assert(str_contains($ownerActions, "'hero_headline' => 'Hero headline'") && str_contains($ownerActions, "'work_description' => 'Professional summary'"), 'Owner validation does not map Hero field errors safely.');
        phase2Assert(str_contains($scopedData, "'hero_headline'") && str_contains($publicData, 'professional_title, hero_headline, location'), 'Hero headline is missing from owner-scoped or public reads.');
        phase2Assert(str_contains($presentation, "['hero_headline', 'professional_title', 'full_name']") && str_contains($presentation, '$heroSummary = $workDescription;') && str_contains($presentation, '$aboutNarrative = $aboutMe;') && !str_contains($presentation, 'Turning Data into'), 'Public Hero precedence or summary separation is incomplete.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/portfolio_presentation.php';
        phase2AssertSame('Explicit value', portfolioPresentationHeroHeadline(['hero_headline' => ' Explicit value ', 'professional_title' => 'Title', 'full_name' => 'Name']), 'Explicit Hero headline must win.');
        phase2AssertSame('Title', portfolioPresentationHeroHeadline(['hero_headline' => '', 'professional_title' => 'Title', 'full_name' => 'Name']), 'Professional title must be the first truthful fallback.');
        phase2AssertSame('Name', portfolioPresentationHeroHeadline(['professional_title' => '', 'full_name' => 'Name']), 'Full name must be the final user-owned fallback.');

        ob_start();
        renderPortfolioPresentation(['full_name' => '<Name>', 'hero_headline' => '<Build safely>', 'about_me' => 'About only', 'work_description' => 'Summary only'], [], []);
        $rendered = ob_get_clean();
        phase2Assert(is_string($rendered) && str_contains($rendered, '<h1 id="portfolio-title">&lt;Build safely&gt;</h1>') && str_contains($rendered, 'portfolio-hero-summary') && str_contains($rendered, 'Summary only') && str_contains($rendered, 'About only'), 'Hero headline, summary, About separation, or escaping failed.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Name', 'professional_title' => 'Title', 'about_me' => 'About remains'], [], []);
        $withoutSummary = ob_get_clean();
        phase2Assert(is_string($withoutSummary) && !str_contains($withoutSummary, 'portfolio-hero-summary') && str_contains($withoutSummary, 'About remains'), 'Missing Professional summary must not reuse About content in the Hero.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
