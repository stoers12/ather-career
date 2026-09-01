<?php

declare(strict_types=1);

final class StaticArchitectureGuards
{
    /** @return list<string> */
    public static function check(string $repositoryRoot): array
    {
        $failures = [];
        $repositoryRoot = realpath($repositoryRoot) ?: '';
        if ($repositoryRoot === '' || !is_dir($repositoryRoot)) {
            return ['repository root is unavailable.'];
        }

        foreach (self::productionPhpFiles($repositoryRoot) as $path) {
            $contents = file_get_contents($path);
            if (!is_string($contents)) {
                $failures[] = self::relative($repositoryRoot, $path) . ' is unreadable.';
                continue;
            }
            if (preg_match('/ATHERCAR_TEST_(?:MODE|AUTH|RUN_ID|DB_NAME|STORAGE_ROOT|COMPOSE_PROJECT)/', $contents)) {
                $failures[] = self::relative($repositoryRoot, $path) . ' references a test-only configuration key.';
            }
            if (preg_match('/\b(?:super_admin|platform_admin|master_session|emergency_password)\b/i', $contents)) {
                $failures[] = self::relative($repositoryRoot, $path) . ' contains a prohibited platform-authority token.';
            }
            if (preg_match('/\b(?:delete|hard_delete)[_-]?(?:portfolio|account)\b/i', $contents)) {
                $failures[] = self::relative($repositoryRoot, $path) . ' contains a prohibited hard-delete token.';
            }
        }

        foreach (['.env.example', 'docker-compose.production.yml', 'Dockerfile.production'] as $relativePath) {
            $contents = self::read($repositoryRoot, $relativePath, $failures);
            if ($contents !== null && str_contains($contents, 'ATHERCAR_TEST_')) {
                $failures[] = "{$relativePath} must not enable test-only configuration.";
            }
        }

        foreach (['.env.example', 'docker-compose.yml', 'docker-compose.production.yml', '.github/workflows/ci.yml'] as $relativePath) {
            $contents = self::read($repositoryRoot, $relativePath, $failures);
            if ($contents !== null && preg_match('/careerfit|career_fit/i', $contents)) {
                $failures[] = "{$relativePath} contains a CareerFit coupling.";
            }
        }

        $vhost = self::read($repositoryRoot, 'docker/apache/production-vhost.conf', $failures);
        if ($vhost !== null && preg_match('/Alias\s+\/(?:uploads|tenant-media|media)\/|Alias\s+\S*ATHERCAR_STORAGE_ROOT/i', $vhost)) {
            $failures[] = 'production vhost maps tenant media storage directly.';
        }

        return array_values(array_unique($failures));
    }

    /** @return list<string> */
    private static function productionPhpFiles(string $repositoryRoot): array
    {
        $paths = [];
        foreach (['includes', 'config', 'classes', 'api', 'public'] as $directory) {
            $root = $repositoryRoot . DIRECTORY_SEPARATOR . $directory;
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $paths[] = $file->getPathname();
                }
            }
        }
        foreach (glob($repositoryRoot . DIRECTORY_SEPARATOR . '*.php') ?: [] as $path) {
            $paths[] = $path;
        }

        sort($paths);
        return array_values(array_unique($paths));
    }

    /** @param list<string> $failures */
    private static function read(string $repositoryRoot, string $relativePath, array &$failures): ?string
    {
        $path = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            $failures[] = "{$relativePath} is unreadable.";
            return null;
        }

        return $contents;
    }

    private static function relative(string $repositoryRoot, string $path): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($repositoryRoot) + 1));
    }
}
