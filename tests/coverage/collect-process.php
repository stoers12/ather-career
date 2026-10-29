<?php

declare(strict_types=1);

/*
 * CI coverage only. Loaded as auto_prepend_file by the phpunit-coverage job so a
 * standalone scripts/test/run-*.php process records its own line coverage
 * (PCOV) into PHASE2_COVERAGE_DIR as a .cov file; `phpcov merge` combines those
 * with PHPUnit's report. Inert unless both the variable and PCOV are present.
 */
(static function (): void {
    $directory = getenv('PHASE2_COVERAGE_DIR');
    if (!is_string($directory) || $directory === '' || !extension_loaded('pcov')) {
        return;
    }

    $root = dirname(__DIR__, 2);
    require_once $root . '/vendor/autoload.php';

    $filter = new SebastianBergmann\CodeCoverage\Filter();
    foreach (['src', 'includes', 'database'] as $source) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $source, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $filter->includeFile($file->getPathname());
            }
        }
    }

    $coverage = new SebastianBergmann\CodeCoverage\CodeCoverage(
        (new SebastianBergmann\CodeCoverage\Driver\Selector())->forLineCoverage($filter),
        $filter,
    );
    $coverage->start(basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? 'process')));
    register_shutdown_function(static function () use ($coverage, $directory): void {
        $coverage->stop();
        (new SebastianBergmann\CodeCoverage\Report\PHP())->process($coverage, $directory . '/' . bin2hex(random_bytes(8)) . '.cov');
    });
})();
