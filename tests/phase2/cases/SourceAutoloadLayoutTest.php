<?php

declare(strict_types=1);

/** Enforces the src/ migration rule in docs/ARCHITECTURE.md. */
final class SourceAutoloadLayoutTest
{
    private const PILOT_WRAPPERS = [
        'includes/evidence_text_policy.php',
        'includes/evidence_text_normalization.php',
        'includes/evidence_text_evaluator.php',
        'includes/evidence_hub_aggregation.php',
    ];

    public static function run(TestEnvironment $environment): void
    {
        $root = realpath(PHASE2_REPOSITORY_ROOT);
        phase2Assert(is_string($root), 'Repository root is unavailable.');

        $composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        phase2AssertSame(['Ather\\' => 'src/'], $composer['autoload']['psr-4'] ?? null, 'composer.json must map only Ather\\ to src/.');

        $classes = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            phase2Assert($file->getExtension() === 'php', "{$relative} is not a PHP class file.");
            $source = (string) file_get_contents($file->getPathname());
            $directory = str_replace('/', '\\', dirname(substr($relative, strlen('src/'))));
            $namespace = 'Ather' . ($directory === '.' ? '' : '\\' . $directory);
            $class = $file->getBasename('.php');

            phase2Assert(str_starts_with($source, "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n"), "{$relative} must declare strict types and namespace {$namespace}.");
            phase2Assert(preg_match_all('/^(?:final |abstract )?(?:class|interface|trait|enum) /m', $source) === 1, "{$relative} must declare exactly one class-like.");
            phase2Assert(preg_match('/^final class ' . preg_quote($class, '/') . '\b/m', $source) === 1, "{$relative} must declare final class {$class}.");
            phase2Assert(preg_match('/\b(?:require|include)(?:_once)?\b/', $source) !== 1, "{$relative} must rely on the autoloader, not require/include.");
            $classes[] = $namespace . '\\' . $class;
        }
        phase2Assert(count($classes) >= 6, 'The Evidence Hub pilot classes are missing from src/.');

        require_once $root . '/includes/autoload.php';
        foreach ($classes as $class) {
            phase2Assert(class_exists($class), "{$class} is not autoloadable.");
        }

        foreach (self::PILOT_WRAPPERS as $relative) {
            $source = (string) file_get_contents($root . '/' . $relative);
            phase2Assert(!str_contains($source, 'class_alias'), "{$relative} must not alias classes; import them instead.");
            phase2Assert(preg_match('/^(?:final |abstract )?class /m', $source) !== 1, "{$relative} must not declare classes.");
            $functions = preg_match_all('/^function \w+\(/m', $source);
            $delegating = preg_match_all('/^function \w+\([^\n]*\n\{\n    (?:return )?[A-Z]\w+::\w+\([^\n]*\);\n\}$/m', $source);
            phase2Assert($functions > 0 && $functions === $delegating, "{$relative} functions must delegate to src/ in one line.");
        }
    }
}
