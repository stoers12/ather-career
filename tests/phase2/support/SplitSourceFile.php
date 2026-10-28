<?php

declare(strict_types=1);

/**
 * Static tests read a split include as one source: the entry file followed by
 * every sibling part whose header says "Loaded by <entry>." (ENG-07). Negative
 * guards therefore keep covering code moved into a part.
 */
final class SplitSourceFile
{
    public static function read(string $entryRelativePath): string
    {
        $entryPath = PHASE2_REPOSITORY_ROOT . '/' . $entryRelativePath;
        $entry = file_get_contents($entryPath);
        phase2Assert(is_string($entry) && $entry !== '', "{$entryRelativePath} is unreadable.");

        $marker = 'Loaded by ' . basename($entryRelativePath) . '.';
        $sources = [$entry];
        $paths = glob(dirname($entryPath) . '/*.php') ?: [];
        sort($paths, SORT_STRING);
        foreach ($paths as $path) {
            if (basename($path) === basename($entryPath)) {
                continue;
            }
            $part = file_get_contents($path);
            phase2Assert(is_string($part), basename($path) . ' is unreadable.');
            if (!str_contains($part, $marker)) {
                continue;
            }
            phase2Assert(
                str_contains($entry, "require_once __DIR__ . '/" . basename($path) . "';"),
                basename($path) . " says it is loaded by {$entryRelativePath} but is not required there."
            );
            $sources[] = $part;
        }

        return implode("\n", $sources);
    }
}
