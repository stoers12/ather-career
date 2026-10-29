<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HandlerArchitectureGuardTest extends TestCase
{
    public function testGuardScansHandlersAndRefusesRootPhp(): void
    {
        $root = sys_get_temp_dir() . '/ather-handler-guard-' . bin2hex(random_bytes(12));
        $files = ['.env.example', 'docker-compose.production.yml', 'Dockerfile', 'docker-compose.yml', '.github/workflows/ci.yml', 'docker/apache/vhost.conf'];
        try {
            foreach ([...$files, 'handlers/fixture.php'] as $file) {
                if (!is_dir(dirname($root . '/' . $file))) {
                    mkdir(dirname($root . '/' . $file), 0700, true);
                }
                file_put_contents($root . '/' . $file, '');
            }
            self::assertSame([], StaticArchitectureGuards::check($root));

            file_put_contents($root . '/handlers/fixture.php', "<?php\n// super_admin\n");
            self::assertSame(['handlers/fixture.php contains a prohibited platform-authority token.'], StaticArchitectureGuards::check($root));
            file_put_contents($root . '/handlers/fixture.php', '');

            file_put_contents($root . '/stray.php', "<?php\n");
            self::assertSame(['stray.php must not be a PHP file at the repository root.'], StaticArchitectureGuards::check($root));
            unlink($root . '/stray.php');
            self::assertSame([], StaticArchitectureGuards::check($root));
        } finally {
            foreach ([...$files, 'handlers/fixture.php', 'stray.php'] as $file) {
                if (is_file($root . '/' . $file)) {
                    unlink($root . '/' . $file);
                }
            }
            foreach (['.github/workflows', '.github', 'docker/apache', 'docker', 'handlers', ''] as $directory) {
                if (is_dir($root . '/' . $directory)) {
                    rmdir($root . '/' . $directory);
                }
            }
        }
    }

    public function testGeneratedEntriesUseHandlerPathsAndPreserveEntryNames(): void
    {
        $root = sys_get_temp_dir() . '/ather-handler-entries-' . bin2hex(random_bytes(12));
        mkdir($root, 0700);
        try {
            RouteContract::writeEntryScripts($root, ['owner_oidc_callback.php', 'p.php']);
            self::assertSame("<?php\n\nrequire dirname(__DIR__) . '/app/handlers/owner_oidc_callback.php';\n", file_get_contents($root . '/owner_oidc_callback.php'));
            self::assertSame("<?php\n\nrequire dirname(__DIR__) . '/app/handlers/public_portfolio.php';\n", file_get_contents($root . '/p.php'));
        } finally {
            foreach (glob($root . '/*.php') ?: [] as $path) {
                unlink($path);
            }
            rmdir($root);
        }
    }
}
