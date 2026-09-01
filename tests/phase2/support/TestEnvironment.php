<?php

declare(strict_types=1);

/**
 * Creates a run-owned namespace for Phase 2 tests. This class deliberately
 * does not connect to MySQL or application storage in P2J-00; later tests may
 * use the generated identifiers only after creating disposable resources.
 */
final class TestEnvironment
{
    private const TEST_MODE = '1';
    private const DATABASE_PREFIX = 'ather_career_test_';
    private const COMPOSE_PREFIX = 'ather-career-test-';
    private const STORAGE_DIRECTORY = 'ather-career-phase2-tests';
    private const MARKER_FILE = '.ather-career-test-run.json';

    private bool $tornDown = false;

    private function __construct(
        public readonly string $runId,
        public readonly string $databaseName,
        public readonly string $composeProject,
        public readonly string $namespaceRoot,
        public readonly string $storageRoot,
    ) {
    }

    public static function create(): self
    {
        self::assertSafeEnvironment(self::processEnvironment());

        try {
            $runId = bin2hex(random_bytes(12));
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not create a safe test run identifier.', 0, $exception);
        }

        $baseRoot = self::baseRoot();
        if (!is_dir($baseRoot) && !mkdir($baseRoot, 0700, true) && !is_dir($baseRoot)) {
            throw new RuntimeException('Could not create the Phase 2 test storage base.');
        }

        $resolvedBaseRoot = realpath($baseRoot);
        if ($resolvedBaseRoot === false || !is_dir($resolvedBaseRoot)) {
            throw new RuntimeException('Phase 2 test storage base is unavailable.');
        }

        $namespaceRoot = $resolvedBaseRoot . DIRECTORY_SEPARATOR . $runId;
        if (!mkdir($namespaceRoot, 0700) || !mkdir($namespaceRoot . DIRECTORY_SEPARATOR . 'storage', 0700)) {
            throw new RuntimeException('Could not create a run-owned Phase 2 test namespace.');
        }

        $environment = new self(
            $runId,
            self::DATABASE_PREFIX . $runId,
            self::COMPOSE_PREFIX . $runId,
            $namespaceRoot,
            $namespaceRoot . DIRECTORY_SEPARATOR . 'storage',
        );

        $environment->writeMarker();
        $environment->publishRunEnvironment();

        return $environment;
    }

    /**
     * Validates the process before it receives any generated test namespace.
     * It intentionally rejects a production-like database name even though
     * P2J-00 itself never opens a database connection.
     *
     * @param array<string, string|false> $environment
     */
    public static function assertSafeEnvironment(array $environment): void
    {
        if (($environment['APP_ENV'] ?? false) !== 'test' || ($environment['ATHERCAR_TEST_MODE'] ?? false) !== self::TEST_MODE) {
            throw new RuntimeException('Phase 2 tests require APP_ENV=test and ATHERCAR_TEST_MODE=1.');
        }

        foreach (['DB_NAME', 'PORTFOLIO_DB_NAME', 'ATHERCAR_TEST_DB_NAME'] as $name) {
            $value = $environment[$name] ?? false;
            if ($value === false || $value === '') {
                continue;
            }
            if (!preg_match('/^ather_career_test_[a-z0-9_]+$/', $value)) {
                throw new RuntimeException("Phase 2 tests refuse unsafe database target configured by {$name}.");
            }
        }

        foreach ($environment as $name => $value) {
            if ($value === false || $value === '' || !preg_match('/(?:DB|DATABASE|STORAGE|COMPOSE|SECRET|PATH)/', $name)) {
                continue;
            }
            if (stripos($value, 'careerfit') !== false || stripos($value, 'career_fit') !== false) {
                throw new RuntimeException("Phase 2 tests refuse CareerFit-coupled configuration in {$name}.");
            }
        }
    }

    public function createProbeFile(string $name, string $contents): string
    {
        if (!preg_match('/^[a-z0-9_-]{1,40}\.txt$/', $name)) {
            throw new InvalidArgumentException('Test probe name is invalid.');
        }

        $path = $this->storageRoot . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write a test-owned probe file.');
        }

        return $path;
    }

    public function tearDown(): void
    {
        if ($this->tornDown) {
            return;
        }

        $baseRoot = realpath(self::baseRoot());
        if ($baseRoot === false || dirname($this->namespaceRoot) !== $baseRoot || basename($this->namespaceRoot) !== $this->runId) {
            throw new RuntimeException('Refusing unsafe Phase 2 test teardown target.');
        }

        $markerPath = $this->namespaceRoot . DIRECTORY_SEPARATOR . self::MARKER_FILE;
        $marker = is_file($markerPath) ? file_get_contents($markerPath) : false;
        $metadata = is_string($marker) ? json_decode($marker, true) : null;
        if (!is_array($metadata)
            || ($metadata['created_by'] ?? null) !== 'ather-career-phase2'
            || ($metadata['run_id'] ?? null) !== $this->runId
            || ($metadata['database_name'] ?? null) !== $this->databaseName
            || ($metadata['compose_project'] ?? null) !== $this->composeProject) {
            throw new RuntimeException('Refusing teardown of a namespace not owned by this Phase 2 test run.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->namespaceRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if ($entry->isLink() || $entry->isFile()) {
                if (!unlink($path)) {
                    throw new RuntimeException('Could not remove a run-owned test file.');
                }
                continue;
            }
            if ($entry->isDir() && !rmdir($path)) {
                throw new RuntimeException('Could not remove a run-owned test directory.');
            }
        }

        if (!rmdir($this->namespaceRoot)) {
            throw new RuntimeException('Could not remove the run-owned test namespace.');
        }

        $this->tornDown = true;
    }

    public function wasTornDown(): bool
    {
        return $this->tornDown && !file_exists($this->namespaceRoot);
    }

    private static function baseRoot(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::STORAGE_DIRECTORY;
    }

    /** @return array<string, string|false> */
    private static function processEnvironment(): array
    {
        $environment = getenv();
        if (!is_array($environment)) {
            throw new RuntimeException('Could not inspect the test process environment.');
        }

        foreach (['APP_ENV', 'ATHERCAR_TEST_MODE', 'DB_NAME', 'PORTFOLIO_DB_NAME', 'ATHERCAR_TEST_DB_NAME'] as $name) {
            $environment[$name] = $environment[$name] ?? false;
        }

        /** @var array<string, string|false> $environment */
        return $environment;
    }

    private function writeMarker(): void
    {
        $marker = json_encode([
            'created_by' => 'ather-career-phase2',
            'run_id' => $this->runId,
            'database_name' => $this->databaseName,
            'compose_project' => $this->composeProject,
        ], JSON_THROW_ON_ERROR);

        if (file_put_contents($this->namespaceRoot . DIRECTORY_SEPARATOR . self::MARKER_FILE, $marker, LOCK_EX) === false) {
            throw new RuntimeException('Could not mark the Phase 2 test namespace.');
        }
    }

    private function publishRunEnvironment(): void
    {
        putenv('ATHERCAR_TEST_RUN_ID=' . $this->runId);
        putenv('ATHERCAR_TEST_DB_NAME=' . $this->databaseName);
        putenv('ATHERCAR_TEST_STORAGE_ROOT=' . $this->storageRoot);
        putenv('ATHERCAR_TEST_COMPOSE_PROJECT=' . $this->composeProject);
    }
}
