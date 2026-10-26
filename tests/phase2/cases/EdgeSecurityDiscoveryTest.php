<?php

declare(strict_types=1);

final class EdgeSecurityDiscoveryTest
{
    public static function run(TestEnvironment $environment): void
    {
        $root = $environment->storageRoot . '/edge-security-discovery';
        $included = [
            'index.php',
            'includes/security.php',
            'config/application.php',
            'public/route.php',
            'scripts/command.php',
            '.well-known/application.php',
            'includes/.hidden/application.php',
            '.worktrees-live/application.php',
            '.worktrees.php',
            'public/vendor/application.php',
            'includes/tests/application.php',
        ];
        $excluded = [
            '.worktrees/copy/index.php',
            '.worktrees/copy/tests/security.php',
            '.worktrees/copy/vendor/library.php',
            'includes/.worktrees/copy/application.php',
            'tests/security.php',
            'vendor/library.php',
        ];
        foreach (array_merge($included, $excluded) as $path) {
            $directory = dirname($root . '/' . $path);
            phase2Assert(is_dir($directory) || mkdir($directory, 0700, true), 'Could not create discovery fixture directory.');
            $contents = "<?php\n// fixture: {$path}\n";
            if (in_array($path, $excluded, true)) {
                $contents .= "header('X-Frame-Options: DENY');\n";
            }
            phase2Assert(file_put_contents($root . '/' . $path, $contents) !== false, 'Could not write discovery fixture.');
        }

        $discover = new ReflectionMethod(EdgeSecurityStaticTest::class, 'runtimePhpSources');
        $assertOwnership = new ReflectionMethod(EdgeSecurityStaticTest::class, 'assertRuntimeHeaderOwnership');
        $sources = $discover->invoke(null, $root);
        foreach ($included as $path) {
            phase2Assert(str_contains($sources, "// fixture: {$path}\n"), "Intended application PHP source {$path} was excluded.");
        }
        foreach ($excluded as $path) {
            phase2Assert(!str_contains($sources, "// fixture: {$path}\n"), "Excluded PHP source {$path} was scanned.");
        }
        $assertOwnership->invoke(null, 'X-Frame-Options', $sources);

        foreach (['includes/security.php', '.well-known/application.php'] as $path) {
            $fixture = $root . '/' . $path;
            $original = file_get_contents($fixture);
            phase2Assert(is_string($original), 'Could not read discovery fixture.');
            phase2Assert(file_put_contents($fixture, $original . "header('X-Frame-Options: DENY');\n") !== false, 'Could not write violation fixture.');
            try {
                $violation = null;
                try {
                    $assertOwnership->invoke(null, 'X-Frame-Options', $discover->invoke(null, $root));
                } catch (RuntimeException $exception) {
                    $violation = $exception->getMessage();
                }
                phase2AssertSame('PHP runtime source emits Apache-owned X-Frame-Options.', $violation, "Existing security assertion did not detect the violation in {$path}.");
            } finally {
                phase2Assert(file_put_contents($fixture, $original) !== false, 'Could not restore run-owned fixture.');
            }
        }
    }
}
