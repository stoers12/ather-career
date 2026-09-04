<?php

declare(strict_types=1);

final class LibvipsImageProcessorTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/profile_actions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/project_actions.php';
        phase2Assert(is_file(PORTFOLIO_IMAGE_PROCESSOR_EXECUTABLE) && is_executable(PORTFOLIO_IMAGE_PROCESSOR_EXECUTABLE), 'libvips CLI is not installed in the test runtime.');

        $storageRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'libvips-processor';
        $publicRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'libvips-public';
        phase2Assert(mkdir($storageRoot, 0700) && mkdir($publicRoot, 0700), 'libvips test roots could not be created.');
        $priorStorageRoot = getenv('ATHERCAR_STORAGE_ROOT');
        $priorDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        putenv('ATHERCAR_STORAGE_ROOT=' . $storageRoot);
        $_SERVER['DOCUMENT_ROOT'] = $publicRoot;

        try {
            $source = $environment->storageRoot . DIRECTORY_SEPARATOR . 'vips-source.jpg';
            self::createJpeg($source, 1200, 800);
            $oriented = $environment->storageRoot . DIRECTORY_SEPARATOR . 'vips-oriented.jpg';
            self::injectOrientationEight($source, $oriented);
            $original = copyFileToPrivateMedia($oriented, 911, 'profile_original', 'orientation.jpg');
            phase2Assert(is_string($original), 'Orientation source could not be staged.');
            $derivative = generateProfilePresentationImage($original, 911);
            $dimensions = is_string($derivative) ? getimagesize((string) resolvePrivateMediaPath($derivative, 911, 'profile_presentation')) : false;
            phase2Assert(is_array($dimensions) && $dimensions[0] === 640 && $dimensions[1] === 960, 'libvips did not normalize EXIF orientation.');

            $alpha = $environment->storageRoot . DIRECTORY_SEPARATOR . 'vips-alpha.png';
            self::createAlphaPng($alpha, 1200, 800);
            $projectOriginal = copyFileToPrivateMedia($alpha, 911, 'projects', 'alpha.png');
            $projectDerivative = is_string($projectOriginal) ? generateProjectPresentationImage($projectOriginal, 911) : null;
            $projectPath = is_string($projectDerivative) ? resolvePrivateMediaPath($projectDerivative, 911, 'project_presentation') : null;
            $info = is_string($projectPath) ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $mime = $info === false || !is_string($projectPath) ? false : finfo_file($info, $projectPath);
            if ($info !== false) finfo_close($info);
            phase2Assert($mime === 'image/png', 'PNG derivative lost its expected MIME.');
            $alphaDerivative = is_string($projectPath) ? @imagecreatefrompng($projectPath) : false;
            $alphaColor = $alphaDerivative === false ? false : imagecolorsforindex($alphaDerivative, imagecolorat($alphaDerivative, 0, 0));
            if ($alphaDerivative !== false) imagedestroy($alphaDerivative);
            phase2Assert(is_array($alphaColor) && ($alphaColor['alpha'] ?? 0) > 0, 'PNG derivative lost its source transparency.');

            $progressive = $environment->storageRoot . DIRECTORY_SEPARATOR . 'progressive.jpg';
            self::createProgressiveJpeg($progressive, 1200, 800);
            phase2Assert(jpegSourceIsProgressive($progressive) === true && portfolioImagePixelCeiling('image/jpeg', $progressive) === PORTFOLIO_PROGRESSIVE_JPEG_PIXEL_CEILING, 'Progressive JPEG policy is not enforced.');

            $highResolution = $environment->storageRoot . DIRECTORY_SEPARATOR . 'high-resolution.jpg';
            self::createLargeJpeg($highResolution, 6240, 4160);
            phase2Assert(profileImageDimensionsAreSafe([6240, 4160], 'image/jpeg', $highResolution), 'The 25.96 MP baseline JPEG class must be inside the Profile ingestion policy.');
            phase2Assert(projectImageDimensionsAreSafe([6240, 4160], 'image/jpeg', $highResolution), 'The 25.96 MP baseline JPEG class must be inside the Project ingestion policy.');
            $highProfileOriginal = copyFileToPrivateMedia($highResolution, 913, 'profile_original', 'high-resolution.jpg');
            $highProjectOriginal = copyFileToPrivateMedia($highResolution, 913, 'projects', 'high-resolution.jpg');
            $highProfileDerivative = is_string($highProfileOriginal) ? generateProfilePresentationImage($highProfileOriginal, 913) : null;
            $highProjectDerivative = is_string($highProjectOriginal) ? generateProjectPresentationImage($highProjectOriginal, 913) : null;
            $highProfileDimensions = is_string($highProfileDerivative) ? getimagesize((string) resolvePrivateMediaPath($highProfileDerivative, 913, 'profile_presentation')) : false;
            $highProjectDimensions = is_string($highProjectDerivative) ? getimagesize((string) resolvePrivateMediaPath($highProjectDerivative, 913, 'project_presentation')) : false;
            phase2Assert(is_array($highProfileDimensions) && max($highProfileDimensions[0], $highProfileDimensions[1]) === PROFILE_PRESENTATION_MAX_DIMENSION, 'The 25.96 MP Profile source was not normalized to 960 px.');
            phase2Assert(is_array($highProjectDimensions) && max($highProjectDimensions[0], $highProjectDimensions[1]) === PROJECT_PRESENTATION_MAX_DIMENSION, 'The 25.96 MP Project source was not normalized to 1600 px.');

            self::assertFailureControls($environment, $source, $storageRoot);
        } finally {
            self::restoreEnvironment('ATHERCAR_STORAGE_ROOT', $priorStorageRoot);
            if ($priorDocumentRoot === null) unset($_SERVER['DOCUMENT_ROOT']); else $_SERVER['DOCUMENT_ROOT'] = $priorDocumentRoot;
        }
    }

    private static function assertFailureControls(TestEnvironment $environment, string $source, string $storageRoot): void
    {
        $malformed = $environment->storageRoot . DIRECTORY_SEPARATOR . 'vips-malformed.jpg';
        $bytes = file_get_contents($source);
        phase2Assert(is_string($bytes) && file_put_contents($malformed, substr($bytes, 0, 256)) !== false && @getimagesize($malformed) !== false, 'Malformed JPEG fixture could not retain safe source metadata.');
        $original = copyFileToPrivateMedia($malformed, 912, 'profile_original', 'failure.jpg');
        phase2Assert(is_string($original), 'Failure fixture could not be staged.');
        phase2Assert(generateProfilePresentationResult($original, 912)['reason'] === 'NORMALIZATION_FAILED', 'Non-zero processor exit was not contained.');

        $timeoutStarted = microtime(true);
        $process = proc_open(['/bin/sleep', '1'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, ['PATH' => '/usr/bin:/bin'], ['bypass_shell' => true]);
        phase2Assert(is_resource($process), 'Timeout fixture could not start.');
        fclose($pipes[0]);
        phase2Assert(closePortfolioImageProcessor($process, $pipes, true) !== 0 && microtime(true) - $timeoutStarted < 0.5, 'Processor timeout termination did not stop the child promptly.');

        $lock = fopen($storageRoot . DIRECTORY_SEPARATOR . '.image-normalization.lock', 'c+');
        phase2Assert(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'Lock fixture could not be acquired.');
        $lockStarted = microtime(true);
        phase2Assert(generateProfilePresentationResult($original, 912)['reason'] === 'NORMALIZATION_RESOURCE_LIMIT' && microtime(true) - $lockStarted < 1.5, 'Normalization lock wait was not bounded.');
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    private static function createJpeg(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 160));
        phase2Assert(imagejpeg($image, $path, 88), 'JPEG fixture write failed.');
        imagedestroy($image);
    }

    private static function createAlphaPng(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 20, 80, 160, 80));
        phase2Assert(imagepng($image, $path, 7), 'Alpha PNG fixture write failed.');
        imagedestroy($image);
    }

    private static function createProgressiveJpeg(string $path, int $width, int $height): void
    {
        $source = $path . '.source.jpg';
        $process = proc_open([PORTFOLIO_IMAGE_PROCESSOR_EXECUTABLE, 'black', $source, (string) $width, (string) $height], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, ['PATH' => '/usr/bin:/bin', 'VIPS_CONCURRENCY' => '1', 'VIPS_BLOCK_UNTRUSTED' => '1'], ['bypass_shell' => true]);
        phase2Assert(is_resource($process), 'Progressive source processor could not start.');
        fclose($pipes[0]); foreach ([$pipes[1], $pipes[2]] as $pipe) { stream_get_contents($pipe); fclose($pipe); }
        phase2Assert(proc_close($process) === 0, 'Progressive source processor failed.');
        $process = proc_open([PORTFOLIO_IMAGE_PROCESSOR_EXECUTABLE, 'jpegsave', $source, $path, '--Q', '84', '--interlace'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, ['PATH' => '/usr/bin:/bin', 'VIPS_CONCURRENCY' => '1', 'VIPS_BLOCK_UNTRUSTED' => '1'], ['bypass_shell' => true]);
        phase2Assert(is_resource($process), 'Progressive JPEG processor could not start.');
        fclose($pipes[0]); foreach ([$pipes[1], $pipes[2]] as $pipe) { stream_get_contents($pipe); fclose($pipe); }
        phase2Assert(proc_close($process) === 0 && is_file($path), 'Progressive JPEG fixture write failed.');
        @unlink($source);
    }

    private static function createLargeJpeg(string $path, int $width, int $height): void
    {
        $process = proc_open([PORTFOLIO_IMAGE_PROCESSOR_EXECUTABLE, 'black', $path, (string) $width, (string) $height], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, ['PATH' => '/usr/bin:/bin', 'VIPS_CONCURRENCY' => '1', 'VIPS_BLOCK_UNTRUSTED' => '1'], ['bypass_shell' => true]);
        phase2Assert(is_resource($process), 'High-resolution JPEG fixture processor could not start.');
        fclose($pipes[0]);
        foreach ([$pipes[1], $pipes[2]] as $pipe) {
            stream_get_contents($pipe);
            fclose($pipe);
        }
        phase2Assert(proc_close($process) === 0 && is_file($path), 'High-resolution JPEG fixture write failed.');
    }

    private static function injectOrientationEight(string $source, string $destination): void
    {
        $bytes = file_get_contents($source);
        $exif = "Exif\x00\x00MM\x00*\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01\x00\x08\x00\x00\x00\x00\x00\x00";
        $segment = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
        phase2Assert(is_string($bytes) && file_put_contents($destination, substr($bytes, 0, 2) . $segment . substr($bytes, 2)) !== false, 'Orientation fixture write failed.');
    }

    private static function restoreEnvironment(string $name, string|false $value): void
    {
        $value === false ? putenv($name) : putenv($name . '=' . $value);
    }
}
