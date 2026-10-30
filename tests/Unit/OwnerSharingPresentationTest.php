<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OwnerSharingPresentationTest extends TestCase
{
    /** @return array{DOMXPath, string} */
    private function render(string $locale, callable $render): array
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_publication_presentation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_messages_presentation.php';
        $saved = [$_GET, $_SESSION ?? [], $GLOBALS['owner_workspace_locale'] ?? null];
        $_GET = ['lang' => $locale]; $_SESSION = ['csrf_token' => str_repeat('a', 64)]; $GLOBALS['owner_workspace_locale'] = $locale;
        ob_start();
        try { $render(); $html = (string) ob_get_contents(); }
        finally {
            ob_end_clean(); [$_GET, $_SESSION, $previous] = $saved;
            if ($previous === null) unset($GLOBALS['owner_workspace_locale']);
            else $GLOBALS['owner_workspace_locale'] = $previous;
        }
        $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return [new DOMXPath($dom), $html];
    }

    public function testPublicationStatesKeepExactNativeActionsAndRetainedEscapedSlug(): void
    {
        foreach (['ar', 'en'] as $locale) foreach (['no_slug', 'reserved', 'published', 'offline', 'configuration'] as $state) {
            [$x] = $this->render($locale, static function () use ($state): void {
                $record = ['public_slug' => $state === 'no_slug' ? null : 'synthetic-owner', 'is_published' => in_array($state, ['published', 'configuration'], true) ? 1 : 0, 'published_at' => in_array($state, ['published', 'offline', 'configuration'], true) ? '2026-10-01' : null];
                renderOwnerPublicationPage($record, in_array($state, ['no_slug', 'configuration'], true) ? null : 'https://example.test/p/synthetic-owner', '', [], [], '<script>retained</script>');
            });
            $actions = []; foreach ($x->query('//form/input[@name="action"]') as $n) { if ($n instanceof DOMElement) $actions[] = $n->getAttribute('value'); }
            self::assertSame(match ($state) { 'no_slug' => ['set_slug'], 'reserved' => ['set_slug', 'publish'], 'offline' => ['publish'], default => ['unpublish'] }, $actions);
            self::assertSame(count($actions), $x->query('//form[@method="POST" and @action="owner_publication.php"]/input[@name="csrf_token"]')->length);
            self::assertSame($state === 'published' ? 1 : 0, $x->query('//a[@target="_blank" and @rel="noopener noreferrer"]')->length);
            self::assertSame(0, $x->query('//script | //*[@style] | //form//form')->length);
            if (in_array($state, ['no_slug', 'reserved'], true)) self::assertSame('<script>retained</script>', $x->evaluate('string(//input[@name="public_slug"]/@value)'));
        }
    }

    public function testMessagesEscapeContentKeepLineBreaksAndEncodeMailto(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $message = ['id' => 19, 'name' => '<img onerror="alert(1)">', 'email' => 'sender+tag@example.test', 'message' => "<script>body</script>\nSecond line & text", 'read_at' => null];
            [$x] = $this->render($locale, static fn () => renderOwnerMessagesPage([$message], $message, '', true));
            self::assertSame(0, $x->query('//script | //img | //*[@onerror] | //*[@style] | //textarea')->length);
            self::assertSame($message['name'], $x->evaluate('string(//h2[@id="selected-message-title"])'));
            self::assertSame($message['message'], $x->evaluate('string(//p[@class="owner-message-body"])'));
            self::assertSame('mailto:sender%2Btag%40example.test?subject=' . rawurlencode(ownerMessagesCopy('reply_subject', $locale)), $x->evaluate('string(//a[starts-with(@href,"mailto:")]/@href)'));
            self::assertSame(1, $x->query('//a[@data-message-state="unread" and @aria-current="true"]')->length);
            self::assertSame(1, $x->query('//form[@method="POST" and @action="owner_messages.php"]/input[@name="action" and @value="mark_unread"]')->length);
            self::assertSame(1, $x->query('//form/input[@name="id" and @value="19"]')->length);
        }
    }
}
