<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1') {
    fwrite(STDERR, "Image calibration requires explicit disposable test mode.\n");
    exit(1);
}

function calibrationRssKilobytes(): int
{
    $status = @file_get_contents('/proc/self/status');
    return is_string($status) && preg_match('/^VmHWM:\s+([0-9]+)\s+kB$/m', $status, $matches) === 1 ? (int) $matches[1] : 0;
}

if (($argv[1] ?? '') === '--generate' && count($argv) === 6) {
    [$width, $height] = array_map('intval', explode('x', $argv[3], 2));
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) throw new RuntimeException('Calibration allocation failed.');
    imagefill($image, 0, 0, imagecolorallocate($image, 22, 90, 140));
    $ok = $argv[4] === 'jpeg' ? imagejpeg($image, $argv[2], 82) : imagepng($image, $argv[2], 9);
    imagedestroy($image);
    if (!$ok) throw new RuntimeException('Calibration image write failed.');
    echo json_encode(['format' => $argv[4], 'width' => $width, 'height' => $height, 'bytes' => filesize($argv[2])], JSON_THROW_ON_ERROR) . "\n";
    exit;
}

if (($argv[1] ?? '') !== '--measure' || count($argv) !== 4 || !in_array($argv[3], ['profile', 'project'], true)) {
    fwrite(STDERR, "Usage: --generate FILE WIDTHxHEIGHT jpeg|png marker | --measure FILE profile|project\n");
    exit(2);
}

$path = $argv[2];
$before = calibrationRssKilobytes();
$mime = mime_content_type($path);
$image = $mime === 'image/jpeg' ? imagecreatefromjpeg($path) : ($mime === 'image/png' ? imagecreatefrompng($path) : false);
if ($image === false) throw new RuntimeException('Calibration decode failed.');
$width = imagesx($image);
$height = imagesy($image);
if ($argv[3] === 'profile') {
    $scale = min(1, 960 / max($width, $height));
    $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
    if ($target === false) throw new RuntimeException('Calibration presentation allocation failed.');
    imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
    imagedestroy($target);
}
$during = calibrationRssKilobytes();
imagedestroy($image);

echo json_encode([
    'runtime' => PHP_VERSION,
    'gd' => gd_info()['GD Version'] ?? 'unknown',
    'memory_limit' => ini_get('memory_limit'),
    'format' => $mime,
    'path' => $argv[3],
    'width' => $width,
    'height' => $height,
    'pixels' => $width * $height,
    'encoded_bytes' => filesize($path),
    'rss_before_kib' => $before,
    'rss_peak_kib' => $during,
    'rss_delta_kib' => max(0, $during - $before),
    'php_peak_bytes' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
