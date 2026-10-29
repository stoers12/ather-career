<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Native PHPUnit test for tests/Support/SplitSourceFile.php (ENG-07 helper). */
final class SplitSourceFileTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/split-source-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/lib', 0700, true);
        $this->write('lib/entry.php', "<?php\nrequire_once __DIR__ . '/entry_part.php';\nfunction entryOnly(): void {}\n");
        $this->write('lib/entry_part.php', "<?php\n// Loaded by entry.php.\nfunction partOnly(): void {}\n");
        $this->write('lib/unrelated.php', "<?php\nfunction unrelated(): void {}\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/lib/*.php') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->root . '/lib');
        rmdir($this->root);
    }

    public function testReturnsEntryFollowedByMarkedParts(): void
    {
        $source = SplitSourceFile::read('lib/entry.php', $this->root);

        self::assertStringStartsWith("<?php\nrequire_once __DIR__ . '/entry_part.php';", $source);
        self::assertStringContainsString('function partOnly()', $source);
        self::assertGreaterThan(strpos($source, 'function entryOnly()'), strpos($source, 'function partOnly()'));
    }

    public function testIgnoresSiblingsWithoutTheMarker(): void
    {
        self::assertStringNotContainsString('function unrelated()', SplitSourceFile::read('lib/entry.php', $this->root));
    }

    public function testRejectsAMarkedPartTheEntryDoesNotRequire(): void
    {
        $this->write('lib/entry_orphan.php', "<?php\n// Loaded by entry.php.\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('entry_orphan.php says it is loaded by lib/entry.php but is not required there.');
        SplitSourceFile::read('lib/entry.php', $this->root);
    }

    public function testRejectsAnUnreadableEntry(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lib/missing.php is unreadable.');
        @SplitSourceFile::read('lib/missing.php', $this->root);
    }

    private function write(string $relative, string $contents): void
    {
        file_put_contents($this->root . '/' . $relative, $contents);
    }
}
