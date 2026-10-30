<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OwnerExperiencesPresentationTest extends TestCase
{
    private function render(bool $editor, string $locale, string $success = ''): DOMXPath
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_actions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_layout.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_experiences_presentation.php';
        $saved = [$_GET, $_SESSION ?? [], $GLOBALS['owner_workspace_locale'] ?? null];
        $_GET = ['lang' => $locale]; $_SESSION = ['csrf_token' => str_repeat('a', 64)]; $GLOBALS['owner_workspace_locale'] = $locale;
        $experience = ['id' => 19, 'role_title' => '<img src=x onerror=alert(1)> "عنوان"', 'organization' => '<script>organization</script>', 'location' => '<b>Amman</b>', 'start_month' => '2026-09', 'end_month' => '', 'is_current' => true, 'experience_type' => 'internship', 'description' => '<script>description</script>'];
        $rows = [];
        foreach (EXPERIENCE_TYPE_VALUES as $i => $type) $rows[] = [...$experience, 'id' => 19 - $i, 'experience_type' => $type];
        ob_start();
        try {
            renderOwnerExperiencesPage($rows, $experience, $editor, 'edit', '2026-10', $success, '', [], []);
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean(); [$_GET, $_SESSION, $previousLocale] = $saved;
            if ($previousLocale === null) unset($GLOBALS['owner_workspace_locale']);
            else $GLOBALS['owner_workspace_locale'] = $previousLocale;
        }
        $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($dom);
    }

    public function testNativeEditorContractsAndHonestStatusInBothLanguages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $x = $this->render(true, $locale);
            self::assertSame(2, $x->query('//form[@method="POST" and @action="owner_experiences.php"]')->length);
            self::assertSame(0, $x->query('//form//form | //script | //*[@style] | //*[@onerror]')->length);
            foreach (['role_title', 'organization', 'location', 'experience_type', 'start_month', 'end_month', 'is_current', 'description'] as $field) self::assertSame(1, $x->query('//form[input[@name="action" and @value="update"]]//*[@name="' . $field . '"]')->length);
            foreach (['role_title', 'organization', 'experience_type', 'start_month'] as $field) self::assertSame(1, $x->query('//*[@id="' . $field . '" and @required]')->length);
            self::assertSame(0, $x->query('//*[@id="location" or @id="description" or @id="end_month"][@required]')->length);
            self::assertSame(1, $x->query('//input[@id="end_month" and @type="month" and @max="2026-10" and not(@disabled)]')->length);
            self::assertSame(1, $x->query('//textarea[@id="description" and @maxlength="1200"]')->length);
            $types = []; foreach ($x->query('//select/option[@value!=""]') as $option) { if ($option instanceof DOMElement) $types[] = $option->getAttribute('value'); }
            self::assertSame(EXPERIENCE_TYPE_VALUES, $types);
            self::assertSame(0, $x->query('//*[@class="owner-experience-saved"]')->length);
            self::assertSame(1, $x->query('//a[@href="owner_experiences.php"]')->length);
            self::assertStringContainsString('<img src=x onerror=alert(1)> "عنوان"', $x->evaluate('string(//form[@data-confirm]/@data-confirm)'));
            self::assertSame('<script>description</script>', $x->evaluate('string(//textarea)'));
            self::assertSame(1, $this->render(true, $locale, 'Experience saved.')->query('//*[@class="owner-experience-saved"]')->length);
        }
    }

    public function testListPreservesOrderAndEscapesEveryOwnerValue(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $x = $this->render(false, $locale);
            self::assertSame(5, $x->query('//article')->length);
            self::assertSame(0, $x->query('//script | //h2/img | //bdi/b | //bdi/script | //p/script | //*[@style] | //*[@onerror]')->length);
            self::assertSame('owner_experiences.php?edit=19', $x->evaluate('string((//article)[1]//a/@href)'));
            self::assertSame('owner_experiences.php?edit=15', $x->evaluate('string((//article)[5]//a/@href)'));
            foreach (EXPERIENCE_TYPE_VALUES as $type) self::assertSame(1, $x->query('//article[@data-experience-type="' . $type . '"]')->length);
            self::assertSame(5, $x->query('//article/h2[@dir="auto"] | //article/div/h2[@dir="auto"]')->length);
            self::assertSame('<img src=x onerror=alert(1)> "عنوان"', $x->evaluate('string((//article)[1]//h2)'));
            self::assertSame('2026-09 — ' . ($locale === 'ar' ? 'حتى الآن' : 'Present'), $x->evaluate('string((//article)[1]//*[@class="owner-experience-dates"])'));
        }
    }
}
