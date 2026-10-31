<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApachePerformanceConfigTest extends TestCase
{
    private static function read(string $path): string
    {
        $value = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($value, "{$path} is unreadable.");

        return $value;
    }

    public function testMeasuredWorkerPoolTuningShipsInTheImage(): void
    {
        $config = self::read('docker/apache/performance.conf');
        $dockerfile = self::read('Dockerfile');

        self::assertMatchesRegularExpression('/^KeepAliveTimeout 1$/m', $config);
        self::assertMatchesRegularExpression('/^\s+StartServers 15$/m', $config);
        self::assertMatchesRegularExpression('/^\s+MinSpareServers 15$/m', $config);
        self::assertMatchesRegularExpression('/^\s+MaxSpareServers 30$/m', $config);
        self::assertStringNotContainsString('MaxRequestWorkers', $config, 'The worker ceiling stays at the default sized for the 512 MiB limit.');
        self::assertStringContainsString(
            "COPY docker/apache/performance.conf /etc/apache2/conf-enabled/zzz-portfolio-performance.conf\n",
            $dockerfile,
        );
    }

    public function testLoadTestTargetsOnlyTheDisposableSyntheticPortfolio(): void
    {
        $script = self::read('tests/Load/public-visitor.js');
        $runner = self::read('scripts/ops/run-load-test.ps1');

        self::assertStringContainsString('/^production-smoke-[a-f0-9]{24}$/', $script);
        self::assertStringNotContainsString("'POST'", $script);
        self::assertStringNotContainsString('http.post', $script);
        self::assertStringContainsString('$project = "ather_production_smoke_$suffix"', $runner);
        self::assertStringContainsString("'down', '--volumes', '--remove-orphans'", $runner);
        self::assertStringNotContainsString('portfolio_course', str_replace("'portfolio_course-web-1'", '', $runner));
    }
}
