<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OwnerProjectsPresentationTest extends TestCase
{
    private function render(bool $editor, string $locale): DOMXPath
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_actions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_layout.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_projects_presentation.php';
        $saved = [$_GET, $_SESSION ?? [], $GLOBALS['owner_workspace_locale'] ?? null];
        $_GET = ['lang' => $locale];
        $_SESSION = ['csrf_token' => str_repeat('a', 64)];
        $GLOBALS['owner_workspace_locale'] = $locale;
        $project = ['id' => 19, 'title' => '<img src=x onerror=alert(1)> "عنوان"', 'category' => '<script>category</script>', 'description' => '<script>description</script>', 'technologies' => ['<b>PHP</b>', 'SQL', 'CSS', 'JS'], 'live_url' => 'https://example.test/?a="b"', 'github_url' => '', 'image_path' => null, 'is_published' => 0];
        ob_start();
        try {
            renderOwnerProjectsPage([$project], [...$project, 'technologies' => implode("\n", $project['technologies'])], false, $editor, 'edit', '', '', $editor ? ['Invalid URL.'] : [], $editor ? ['live_url' => 'Invalid URL.'] : []);
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            [$_GET, $_SESSION, $previousLocale] = $saved;
            if ($previousLocale === null) unset($GLOBALS['owner_workspace_locale']);
            else $GLOBALS['owner_workspace_locale'] = $previousLocale;
        }
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($dom);
    }

    public function testEditorRetainsOneCompleteNativeMultipartContractAndEscapesValues(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $x = $this->render(true, $locale);
            self::assertSame(1, $x->query('//form[@method="POST" and @action="owner_projects.php" and @enctype="multipart/form-data"]')->length);
            self::assertSame(3, $x->query('//form/section[@data-project-step and not(@hidden)]')->length);
            foreach (['title', 'category', 'description', 'technologies', 'github_url', 'live_url', 'project_image', 'csrf_token', 'action', 'id', 'remove_image'] as $field) {
                // remove_image is conditional on an existing image, tested in the browser upload gate.
                self::assertSame($field === 'remove_image' ? 0 : 1, $x->query('//form//*[@name="' . $field . '"]')->length, $field);
            }
            foreach (['title', 'category', 'description'] as $field) self::assertSame(1, $x->query('//*[@id="' . $field . '" and @required]')->length);
            self::assertSame(0, $x->query('//*[@name="github_url" or @name="live_url" or @name="technologies"][@required]')->length);
            self::assertSame(1, $x->query('//input[@name="live_url" and @type="url" and @maxlength="' . PROJECT_LIVE_URL_MAX_LENGTH . '" and @aria-invalid="true" and contains(@aria-describedby,"owner-field-error-live_url")]')->length);
            self::assertSame('https://example.test/?a="b"', $x->evaluate('string(//input[@name="live_url"]/@value)'));
            self::assertSame('<img src=x onerror=alert(1)> "عنوان"', $x->evaluate('string(//input[@name="title"]/@value)'));
            self::assertSame(0, $x->query('//script | //*[@onerror] | //*[@style]')->length);
            self::assertSame(1, $x->query('//a[@href="owner_publication.php"]')->length);
            self::assertSame(1, $x->query('//textarea[@name="technologies" and @rows="6"]')->length);
            self::assertSame($locale === 'ar' ? 'اختيار صورة' : 'Choose image', $x->evaluate('string(//label[@class="button-secondary owner-project-file-trigger"])'));
            self::assertSame(1, $x->query('//input[@type="file" and @aria-labelledby="project-image-label" and not(@hidden) and not(@tabindex="-1")]')->length);
            self::assertStringContainsString('{filename}', $x->evaluate('string(//input[@type="file"]/@data-project-image-selected)'));
            self::assertStringContainsString('{maximum}', $x->evaluate('string(//input[@type="file"]/@data-project-image-too-large)'));

        }
    }

    public function testListEscapesConfirmAndSearchAndKeepsIndependentNativePublication(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $x = $this->render(false, $locale);
            self::assertSame(0, $x->query('//script | //h2/img | //p/script | //bdi/b | //*[@onerror] | //*[@style]')->length);
            self::assertStringContainsString('<img src=x onerror=alert(1)> "عنوان"', $x->evaluate('string(//form[@data-confirm]/@data-confirm)'));
            self::assertStringContainsString('JS', $x->evaluate('string(//article/@data-search)'));
            self::assertSame(1, $x->query('//div[@data-project-tools and @hidden]')->length);
            self::assertSame(1, $x->query('//article//details//form[input[@name="action" and @value="set_project_publication"]]')->length);
            self::assertSame(0, $x->query('//form//form')->length);
            self::assertSame(1, $x->query('//a[@href="/owner/projects/19/evidence"]')->length);
            self::assertSame(1, $x->query('//a[@href="/owner_preview.php?project=19"]')->length);
        }
    }
}
