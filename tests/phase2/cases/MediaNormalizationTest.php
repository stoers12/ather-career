<?php

declare(strict_types=1);

final class MediaNormalizationTest
{
    public static function run(TestEnvironment $environment): void
    {
        phase2Assert(extension_loaded('gd'), 'Media normalization requires GD.');
        require_once PHASE2_REPOSITORY_ROOT . '/includes/profile_actions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/project_actions.php';

        $storageRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'media-normalization';
        $publicRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'media-normalization-public';
        phase2Assert(mkdir($storageRoot, 0700) && mkdir($publicRoot, 0700), 'Media normalization test roots could not be created.');
        $priorStorageRoot = getenv('ATHERCAR_STORAGE_ROOT');
        $priorDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        putenv('ATHERCAR_STORAGE_ROOT=' . $storageRoot);
        $_SERVER['DOCUMENT_ROOT'] = $publicRoot;

        try {
            $standard = $environment->storageRoot . DIRECTORY_SEPARATOR . 'standard.jpg';
            $phone = $environment->storageRoot . DIRECTORY_SEPARATOR . 'phone.jpg';
            self::createJpeg($standard, 800, 600);
            self::padTo($standard, 500 * 1024);
            self::createJpeg($phone, 4032, 3024);
            self::padTo($phone, (int) ceil(2.3 * 1024 * 1024));

            phase2Assert(is_array(validateProfileImageUpload(self::upload($standard))), 'A standard Profile image was rejected.');
            phase2Assert(is_array(validateProjectImageUpload(self::upload($standard))), 'A standard Project image was rejected.');
            phase2Assert(is_array(validateProfileImageUpload(self::upload($phone))), 'A 2.3 MB 4032×3024 Profile image was rejected.');
            phase2Assert(is_array(validateProjectImageUpload(self::upload($phone))), 'A 2.3 MB 4032×3024 Project image was rejected.');
            phase2Assert(profileImageDimensionsAreSafe([4032, 3024], 'image/jpeg', $phone) && projectImageDimensionsAreSafe([4032, 3024], 'image/jpeg', $phone), 'Common 12.2 MP phone dimensions must be inside the ingestion ceiling.');

            $profileOriginal = copyFileToPrivateMedia($phone, 901, 'profile_original', 'phone.jpg');
            $projectOriginal = copyFileToPrivateMedia($phone, 901, 'projects', 'phone.jpg');
            phase2Assert(is_string($profileOriginal) && is_string($projectOriginal), 'High-resolution originals were not retained privately.');
            $profileDerivative = generateProfilePresentationImage($profileOriginal, 901);
            $projectDerivative = generateProjectPresentationImage($projectOriginal, 901);
            phase2Assert(is_string($profileDerivative) && is_string($projectDerivative), 'High-resolution presentation derivatives were not generated.');
            $profileDimensions = getimagesize((string) resolvePrivateMediaPath($profileDerivative, 901, 'profile_presentation'));
            $projectDimensions = getimagesize((string) resolvePrivateMediaPath($projectDerivative, 901, 'project_presentation'));
            phase2Assert(is_array($profileDimensions) && max($profileDimensions[0], $profileDimensions[1]) === PROFILE_PRESENTATION_MAX_DIMENSION, 'Profile derivative was not normalized to its presentation target.');
            phase2Assert(is_array($projectDimensions) && max($projectDimensions[0], $projectDimensions[1]) === PROJECT_PRESENTATION_MAX_DIMENSION, 'Project derivative was not normalized to its presentation target.');
            phase2Assert(getimagesize((string) resolvePrivateMediaPath($profileOriginal, 901, 'profile_original'))[0] === 4032, 'Profile original was changed during normalization.');
            phase2Assert(getimagesize((string) resolvePrivateMediaPath($projectOriginal, 901, 'projects'))[0] === 4032, 'Project original was changed during normalization.');

            self::assertStorageFailureContract($standard, $storageRoot);

            self::assertBusinessLimit($standard, PROFILE_IMAGE_MAX_BYTES, 'Profile', 'validateProfileImageUpload');
            self::assertBusinessLimit($standard, PROJECT_IMAGE_MAX_BYTES, 'Project', 'validateProjectImageUpload');

            $small = $environment->storageRoot . DIRECTORY_SEPARATOR . 'small.jpg';
            self::createJpeg($small, 399, 399);
            phase2Assert(validateProfileImageUpload(self::upload($small)) === 'Profile photo must be at least 400 × 400 pixels.', 'Profile minimum dimensions were weakened.');
            phase2Assert(profileImageDimensionsAreSafe([4000, 3500], 'image/jpeg', $phone) && projectImageDimensionsAreSafe([4000, 3500], 'image/jpeg', $phone), 'The supported JPEG ingestion envelope must include 14 MP sources.');
            $pathological = $environment->storageRoot . DIRECTORY_SEPARATOR . 'pathological.png';
            self::createPngHeader($pathological, 13000, 5000);
            phase2Assert(validateProfileImageUpload(self::upload($pathological)) === 'Profile photo dimensions are too large.', 'Profile pathological dimensions were not rejected before decoding.');
            phase2Assert(validateProjectImageUpload(self::upload($pathological)) === 'Project image dimensions are too large.', 'Project pathological dimensions were not rejected before decoding.');

            $fake = $environment->storageRoot . DIRECTORY_SEPARATOR . 'fake.jpg';
            file_put_contents($fake, '<?php echo "not an image";');
            phase2Assert(validateProfileImageUpload(self::upload($fake)) === 'Please upload a JPG or PNG image.', 'Profile MIME validation accepted a fake image extension.');
            phase2Assert(validateProjectImageUpload(self::upload($fake)) === 'Only JPG, PNG, and WEBP images are allowed.', 'Project MIME validation accepted a fake image extension.');

            if (function_exists('imagewebp') && function_exists('imagecreatefromwebp')) {
                $webp = $environment->storageRoot . DIRECTORY_SEPARATOR . 'project.webp';
                self::createWebp($webp, 1200, 675);
                phase2Assert(is_array(validateProjectImageUpload(self::upload($webp))), 'The declared Project WebP contract is unavailable.');
                $webpOriginal = copyFileToPrivateMedia($webp, 901, 'projects', 'project.webp');
                phase2Assert(is_string($webpOriginal) && is_string(generateProjectPresentationImage($webpOriginal, 901)), 'A validated Project WebP could not be normalized.');
            }
        } finally {
            $priorStorageRoot === false ? putenv('ATHERCAR_STORAGE_ROOT') : putenv('ATHERCAR_STORAGE_ROOT=' . $priorStorageRoot);
            if ($priorDocumentRoot === null) {
                unset($_SERVER['DOCUMENT_ROOT']);
            } else {
                $_SERVER['DOCUMENT_ROOT'] = $priorDocumentRoot;
            }
        }
    }

    private static function createJpeg(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        phase2Assert($image !== false, 'JPEG fixture allocation failed.');
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 160));
        phase2Assert(imagejpeg($image, $path, 88), 'JPEG fixture write failed.');
        imagedestroy($image);
    }

    private static function createWebp(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        phase2Assert($image !== false, 'WebP fixture allocation failed.');
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 100, 170));
        phase2Assert(imagewebp($image, $path, 82), 'WebP fixture write failed.');
        imagedestroy($image);
    }

    private static function createPngHeader(string $path, int $width, int $height): void
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        };
        $header = "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . $chunk('IEND', '');
        phase2Assert(file_put_contents($path, $header) !== false, 'Pathological PNG header fixture could not be written.');
    }

    private static function padTo(string $path, int $bytes): void
    {
        $handle = fopen($path, 'ab');
        phase2Assert(is_resource($handle) && ftruncate($handle, $bytes), 'Image fixture could not be padded to the requested encoded size.');
        fclose($handle);
        clearstatcache(true, $path);
    }

    /** @return array{error: int, tmp_name: string, size: int} */
    private static function upload(string $path): array
    {
        return ['error' => UPLOAD_ERR_OK, 'tmp_name' => $path, 'size' => (int) filesize($path)];
    }

    private static function assertBusinessLimit(string $source, int $limit, string $label, string $validator): void
    {
        $below = $source . '.' . strtolower($label) . '-below.jpg';
        $above = $source . '.' . strtolower($label) . '-above.jpg';
        phase2Assert(copy($source, $below) && copy($source, $above), "{$label} size fixtures could not be copied.");
        self::padTo($below, $limit - 1);
        self::padTo($above, $limit + 1);
        phase2Assert(is_array($validator(self::upload($below))), "{$label} rejected a valid image immediately below its business limit.");
        phase2Assert(is_string($validator(self::upload($above))), "{$label} accepted an image above its business limit.");
    }

    private static function assertStorageFailureContract(string $source, string $storageRoot): void
    {
        $log = $storageRoot . DIRECTORY_SEPARATOR . 'storage-failure.log';
        $priorLogErrors = ini_get('log_errors');
        $priorErrorLog = ini_get('error_log');
        ini_set('log_errors', '1');
        ini_set('error_log', $log);

        try {
            $profileErrors = [];
            $projectErrors = [];
            phase2Assert(storeValidatedProfileImage(self::upload($source), $profileErrors, 902) === null, 'A non-uploaded Profile fixture unexpectedly reached storage.');
            phase2Assert(storeValidatedProjectImage(self::upload($source), $projectErrors, 902) === null, 'A non-uploaded Project fixture unexpectedly reached storage.');
            phase2AssertSame(['The image could not be saved. Please try again.'], $profileErrors, 'Profile storage failure message is inaccurate.');
            phase2AssertSame(['The image could not be saved. Please try again.'], $projectErrors, 'Project storage failure message is inaccurate.');
            phase2Assert(!file_exists($storageRoot . DIRECTORY_SEPARATOR . 'portfolios' . DIRECTORY_SEPARATOR . '902'), 'A rejected storage attempt left private media behind.');

            $events = is_file($log) ? (string) file_get_contents($log) : '';
            phase2Assert(substr_count($events, '"event":"media_upload_rejected"') === 2, 'Storage failures did not emit both rejection events.');
            phase2Assert(substr_count($events, '"stage":"storage"') === 2 && substr_count($events, '"reason":"private_staging_failed"') === 2, 'Storage rejection telemetry lacks its stable stage or reason.');
            foreach (['tmp_name', $source, 'filename', 'email', 'exif', 'gps'] as $privateValue) {
                phase2Assert(!str_contains(strtolower($events), strtolower($privateValue)), 'Storage rejection telemetry leaked private upload data.');
            }
        } finally {
            ini_set('log_errors', (string) $priorLogErrors);
            ini_set('error_log', (string) $priorErrorLog);
        }
    }
}
