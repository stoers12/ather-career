<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/owner_project_evidence_presentation.php';

final class EvidenceHubViewsPresentationTest extends TestCase
{
    /** @return array<string, string> */
    private function values(): array
    {
        return ['problem' => '<b>Retained & private</b>', 'personal_role' => 'A typed contribution', 'measurable_outcome' => 'An unfinished result'];
    }

    /** @param array{mode:string,part:?string} $view @param array<string,string> $errors */
    private function render(array $view, array $errors = [], int $sourceCount = 1): string
    {
        $_SESSION['csrf_token'] = str_repeat('a', 64);
        $values = $this->values();
        $sources = array_map(static fn (int $id): array => ['id' => $id, 'title' => 'Report <' . $id . '>', 'url' => 'https://example.com/' . $id . '?a=1&b=2', 'note' => 'Private & supporting'], range(1, $sourceCount));
        ob_start();
        renderOwnerProjectEvidencePage(['id' => 7, 'title' => '<script>title</script>', 'values' => $values], $values, $errors, $sources, ['title' => 'Retained <draft>', 'url' => 'invalid', 'note' => 'Private'], [], $view, 'en');
        return (string) ob_get_clean();
    }

    public function testReadContainsSavedTextAndOnlySourceMutationForms(): void
    {
        $html = $this->render(['mode' => 'read', 'part' => null]);
        self::assertStringNotContainsString('<textarea', $html);
        self::assertStringContainsString('name="action" value="add_source"', $html);
        self::assertStringContainsString('name="action" value="remove_source"', $html);
        self::assertStringContainsString('?mode=write&amp;part=personal_role', $html);
        self::assertStringContainsString('&lt;b&gt;Retained &amp; private&lt;/b&gt;', $html);
        self::assertStringContainsString('data-confirm="Remove this source?', $html);
        self::assertStringNotContainsString('<script>title</script>', $html);
    }

    public function testWriteKeepsOneExactTextFormAndNoSourceActions(): void
    {
        $html = $this->render(['mode' => 'write', 'part' => 'personal_role']);
        self::assertSame(1, substr_count($html, '<form '));
        preg_match_all('/<(?:input|textarea)\b[^>]*\bname="([^"]+)"/', $html, $matches);
        self::assertSame(['csrf_token', 'problem', 'personal_role', 'measurable_outcome'], $matches[1]);
        self::assertStringContainsString('data-initial-part="personal_role"', $html);
        self::assertStringContainsString('data-evidence-step-panel="sources-after-save"', $html);
        self::assertStringNotContainsString('value="add_source"', $html);
        self::assertStringNotContainsString('value="remove_source"', $html);
        self::assertStringContainsString('href="/owner/projects/7/evidence">Cancel', $html);
    }

    public function testTextRejectionRetainsValuesAndTheServerChosenPart(): void
    {
        $html = $this->render(['mode' => 'write', 'part' => 'personal_role'], ['personal_role' => 'Please enter text for this field.']);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('data-initial-part="personal_role"', $html);
        self::assertStringContainsString('A typed contribution</textarea>', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringNotContainsString('value="add_source"', $html);
    }

    public function testTenSourcesKeepRemovalAndHideAddition(): void
    {
        $html = $this->render(['mode' => 'read', 'part' => null], [], 10);
        self::assertSame(10, substr_count($html, 'value="remove_source"'));
        self::assertStringNotContainsString('value="add_source"', $html);
        self::assertStringContainsString('10 of 10 sources', $html);
    }
}
