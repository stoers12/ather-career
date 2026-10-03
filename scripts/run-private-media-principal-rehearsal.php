<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/profile_actions.php';

if (function_exists('posix_geteuid')) {
    $runtimeUser = posix_getpwuid(posix_geteuid());
    if (!is_array($runtimeUser) || ($runtimeUser['name'] ?? null) !== 'www-data') {
        throw new RuntimeException('This rehearsal must run as www-data.');
    }
}

$root = requirePrivateStorageRoot(true);
$portfolioId = 2147483000;
$portfolioDirectory = portfolioStorageDirectory($portfolioId, true);
if (!is_string($portfolioDirectory)) {
    throw new RuntimeException('Private staging namespace could not be created.');
}

$privateDirectory = ensurePrivateMediaDirectory($portfolioId, 'profile_original');
$derivativeDirectory = ensurePrivateMediaDirectory($portfolioId, 'profile_presentation');
if (!is_string($privateDirectory) || !is_string($derivativeDirectory)) {
    throw new RuntimeException('Private media collections could not be created.');
}

$privateProbe = $privateDirectory . '/.principal-rehearsal';
$derivativeProbe = $derivativeDirectory . '/.stage-000000000000.jpg';
$lock = null;

try {
    if (file_put_contents($privateProbe, 'private-media-rehearsal', LOCK_EX) === false) {
        throw new RuntimeException('Private staging write failed.');
    }
    chmod($privateProbe, 0600);
    if (file_get_contents($privateProbe) !== 'private-media-rehearsal' || (fileperms($privateProbe) & 0777) !== 0600) {
        throw new RuntimeException('Private staging read or mode verification failed.');
    }

    $lock = acquirePortfolioImageNormalizationLock();
    if (!is_resource($lock)) {
        throw new RuntimeException('Normalization lock acquisition failed.');
    }

    if (file_put_contents($derivativeProbe, 'derivative-rehearsal', LOCK_EX) === false) {
        throw new RuntimeException('Derivative staging write failed.');
    }
    chmod($derivativeProbe, 0600);
    releasePortfolioImageNormalizationLock($lock);
    $lock = null;

    if (isset($argv[1])) {
        $source = $argv[1];
        $upload = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $source, 'size' => @filesize($source)];
        $validation = validateProfileImageUpload($upload);
        if (!is_array($validation)) {
            throw new RuntimeException('Real-image pre-validation failed.');
        }
        $sourceDimensions = @getimagesize($source);
        $sourceOrientation = jpegSourceOrientation($source);
        $originalKey = copyFileToPrivateMedia($source, $portfolioId, 'profile_original', createManagedUploadFilename('profile', $validation['extension']));
        if (!is_string($originalKey)) {
            throw new RuntimeException('Real-image private staging failed.');
        }
        try {
            $originalPath = resolvePrivateMediaPath($originalKey, $portfolioId, 'profile_original');
            if (!is_string($originalPath) || (fileperms($originalPath) & 0777) !== 0600) {
                throw new RuntimeException('Real-image private storage mode failed.');
            }
            $presentation = generateProfilePresentationResult($originalKey, $portfolioId);
            $presentationPath = is_string($presentation['key']) ? resolvePrivateMediaPath($presentation['key'], $portfolioId, 'profile_presentation') : null;
            $derivativeDimensions = is_string($presentationPath) ? @getimagesize($presentationPath) : false;
            if ($presentation['reason'] !== 'success' || !is_array($derivativeDimensions) || $derivativeDimensions[0] !== 640 || $derivativeDimensions[1] !== 960) {
                throw new RuntimeException('Real-image normalization or derivative validation failed.');
            }
            if ((fileperms($presentationPath) & 0777) !== 0600) {
                throw new RuntimeException('Real-image derivative storage mode failed.');
            }
            echo 'PASS real-image pipeline bytes=' . filesize($source)
                . ' source=' . $sourceDimensions[0] . 'x' . $sourceDimensions[1]
                . ' orientation=' . $sourceOrientation
                . ' derivative=' . $derivativeDimensions[0] . 'x' . $derivativeDimensions[1] . "\n";
        } finally {
            deleteProfilePresentationImage($originalKey, $portfolioId);
            deletePrivateMediaFile($originalKey, $portfolioId, 'profile_original');
        }
    }

    echo "PASS www-data private-media principal rehearsal\n";
} finally {
    if (is_resource($lock)) releasePortfolioImageNormalizationLock($lock);
    @unlink($privateProbe);
    @unlink($derivativeProbe);
    @rmdir($privateDirectory);
    @rmdir(dirname($privateDirectory));
    @rmdir($derivativeDirectory);
    @rmdir(dirname($derivativeDirectory));
    @rmdir($portfolioDirectory);
    @rmdir(dirname($portfolioDirectory));
}
