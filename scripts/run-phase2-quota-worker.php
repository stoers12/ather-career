<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1' || count($argv) !== 4) exit(2);
require_once __DIR__ . '/../includes/storage.php';
$portfolioId = (int) $argv[1];
$bytes = (int) $argv[2];
$filename = $argv[3];
try {
    withPortfolioQuotaReservation($portfolioId, $bytes, static function () use ($portfolioId, $bytes, $filename): void {
        $directory = portfolioStorageDirectory($portfolioId, true);
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        $handle = fopen($path, 'x+b');
        if ($handle === false || !ftruncate($handle, $bytes)) throw new RuntimeException('Quota worker write failed.');
        fclose($handle);
    });
    echo "allowed\n";
} catch (PortfolioQuotaExceededException) {
    echo "denied\n";
} catch (Throwable) {
    echo "failed\n";
}
