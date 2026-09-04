<?php

declare(strict_types=1);

final class PrivateMediaStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $storage = self::read('includes/storage.php');
        $access = self::read('includes/media_access.php');
        $ownerRoute = self::read('owner_media.php');
        $publicRoute = self::read('public_media.php');
        $ownerActions = self::read('includes/owner_actions.php');
        $profileActions = self::read('includes/profile_actions.php');
        $projectActions = self::read('includes/project_actions.php');
        $profilePresentation = self::read('includes/profile_presentation.php');
        $projectPresentation = self::read('includes/project_presentation.php');
        $processor = self::read('includes/image_processor.php');
        $ownerProfile = self::read('owner_profile.php');
        $legacyProfile = self::read('personal_info.php');
        $ownerProjects = self::read('owner_projects.php');
        $legacyProjects = self::read('projects.php');
        $adminScript = self::read('admin.js');
        $vhost = self::read('docker/apache/production-vhost.conf');
        $accessPolicy = self::read('docker/apache/access-policy.conf');
        $compose = self::read('docker-compose.production.yml');
        $developmentDockerfile = self::read('Dockerfile');
        $productionDockerfile = self::read('Dockerfile.production');
        $productionIni = self::read('docker/php/production.ini');

        phase2Assert(str_contains($storage, "getenv('ATHERCAR_STORAGE_ROOT')") && str_contains($storage, 'isAbsoluteFilesystemPath') && str_contains($storage, 'outside the public document root'), 'P2J-07 private root contract is incomplete.');
        phase2Assert(str_contains($storage, 'parseManagedMediaKey') && str_contains($storage, "str_contains(\$candidate, '%')") && str_contains($storage, "str_contains(\$candidate, '\\\\')"), 'P2J-07 managed key traversal rejection is incomplete.');
        phase2Assert(str_contains($storage, "'portfolios/' . \$portfolioId") && str_contains($storage, 'move_uploaded_file') && str_contains($storage, "'.stage-'") && str_contains($storage, 'rename('), 'P2J-07 tenant layout and staged upload sequence are incomplete.');
        phase2Assert(str_contains($access, 'findAuthorizedProject($database, $context') && str_contains($access, 'portfolio_id = :public_portfolio_id'), 'P2J-07 media access is not scoped through owner/public Portfolio contexts.');
        phase2Assert(!str_contains($ownerRoute, 'portfolio_id') && !str_contains($ownerRoute, 'user_id') && !str_contains($ownerRoute, 'key') && str_contains($ownerRoute, 'requireOwnerPortfolioContext'), 'P2J-07 owner handler accepts forbidden authority.');
        phase2Assert(!str_contains($publicRoute, 'requireOwnerPortfolioContext') && !str_contains($publicRoute, 'key') && str_contains($publicRoute, 'resolvePublicReadContext'), 'P2J-07 public handler does not use public authority exclusively.');
        phase2Assert(str_contains($ownerActions, 'storeValidatedProfileImage($files[\'profile_image\'] ?? [], $errors, $context->portfolioId)') && str_contains($ownerActions, 'storeValidatedProjectImage($files[\'project_image\'], $errors, $context->portfolioId)'), 'P2J-07 uploads are not Portfolio scoped.');
        phase2Assert(str_contains($projectActions, 'PROJECT_IMAGE_MAX_BYTES = 5 * 1024 * 1024') && str_contains($projectActions, 'validateProjectImageUpload($file)') && str_contains($projectActions, 'projectImageSizeIsAllowed($actualSize)') && str_contains($projectActions, 'finfo_open(FILEINFO_MIME_TYPE)') && str_contains($projectActions, '@getimagesize($file[\'tmp_name\'])') && str_contains($projectActions, "'image/jpeg' => 'jpg'") && str_contains($projectActions, "'image/png' => 'png'") && str_contains($projectActions, "'image/webp' => 'webp'") && !str_contains($projectActions, "'image/svg+xml'"), 'Project covers must use the 5 MB server limit and actual file size while retaining MIME, image-content, and no-SVG validation.');
        phase2Assert(str_contains($profileActions, 'PROFILE_IMAGE_MAX_BYTES = 8 * 1024 * 1024') && str_contains($profileActions, "'image/jpeg' => 'jpg'") && str_contains($profileActions, "'image/png' => 'png'") && !str_contains($profileActions, "'image/svg+xml'"), 'Profile photos must retain their 8 MB business limit and safe MIME allowlist.');
        phase2Assert(str_contains($ownerProfile, 'at least 400 × 400 px, maximum <?php echo profileImageMaximumMegabytes(); ?> MB') && str_contains($ownerProfile, 'High-resolution images are optimized automatically.') && str_contains($legacyProfile, 'Minimum 400 × 400 px') && str_contains($legacyProfile, 'High-resolution images are optimized automatically.'), 'Profile upload guidance must preserve its minimum/maximum business contract and explain automatic normalization.');
        phase2Assert(str_contains($profilePresentation, 'PROFILE_PRESENTATION_MAX_DIMENSION = 960') && str_contains($projectPresentation, 'PROJECT_PRESENTATION_MAX_DIMENSION = 1600') && str_contains($profilePresentation, 'normalizePortfolioImage(') && str_contains($projectPresentation, 'normalizePortfolioImage(') && str_contains($processor, "'--auto-rotate'") && str_contains($processor, '[Q=84,strip]') && str_contains($processor, '[compression=7,strip]') && str_contains($processor, '[Q=82,strip]'), 'Profile and Project presentation normalization must use independent libvips targets, orientation normalization, and metadata-stripped format outputs.');
        phase2Assert(str_contains($access, "'project_presentation'") && str_contains($access, 'generateProjectPresentationImage(') && !str_contains($access, "privateMediaDescriptor(\$key, \$context->portfolioId, 'projects')"), 'Project media access must serve a normalized derivative rather than its private original.');
        phase2Assert(str_contains($developmentDockerfile, 'upload_max_filesize=12M') && str_contains($developmentDockerfile, 'post_max_size=16M') && str_contains($productionIni, 'upload_max_filesize = 12M') && str_contains($productionIni, 'post_max_size = 16M'), 'PHP transport limits must safely exceed the largest 8 MB media business limit.');
        phase2Assert(str_contains($developmentDockerfile, 'libwebp-dev') && str_contains($developmentDockerfile, 'libvips-tools') && str_contains($developmentDockerfile, 'VIPS_BLOCK_UNTRUSTED=1') && str_contains($productionDockerfile, 'libwebp-dev') && str_contains($productionDockerfile, 'libvips-tools') && str_contains($productionDockerfile, 'VIPS_BLOCK_UNTRUSTED=1'), 'Both reproducible PHP images must provide the hardened libvips thumbnail processor and declared Project WebP format.');
        phase2Assert(str_contains($ownerProjects, 'Recommended: 1200 × 675 px (16:9)') && str_contains($ownerProjects, 'JPG, PNG or WebP · Max <?php echo projectImageMaximumMegabytes(); ?> MB') && str_contains($ownerProjects, 'High-resolution images are optimized automatically.') && str_contains($legacyProjects, 'data-project-image-max-bytes="<?php echo PROJECT_IMAGE_MAX_BYTES; ?>"') && str_contains($adminScript, 'projectImageInput.dataset.projectImageMaxBytes') && !str_contains($adminScript, '2 * 1024 * 1024'), 'Project cover guidance and client feedback must derive from the 5 MB server contract and explain automatic normalization.');
        phase2Assert(str_contains($ownerActions, "\$removeImage = isset(\$post['remove_image'])") && str_contains($ownerActions, "cleanProjectImage(\$oldImagePath, 'owner_project_update_old_image'") && str_contains($ownerActions, "cleanProjectImage(\$project['image_path'] ?? null, 'owner_project_delete'"), 'S05A project cover replacement and removal cleanup must remain intact.');
        phase2Assert(!preg_match('/^\s*Alias\s+\/uploads\//mi', $vhost) && str_contains($accessPolicy, '^/uploads(?:/|$)'), 'P2J-07 direct upload access was not retired.');
        phase2Assert(str_contains($compose, 'ATHERCAR_STORAGE_ROOT: /var/lib/ather-career/storage') && str_contains($compose, 'portfolio_production_storage:/var/lib/ather-career/storage'), 'P2J-07 production private storage configuration is incomplete.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/project_actions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/profile_actions.php';
        $twoPointThreeMegabytes = (int) ceil(2.3 * 1024 * 1024);
        phase2Assert(projectImageSizeIsAllowed(PROJECT_IMAGE_MAX_BYTES) && projectImageSizeIsAllowed(0) && !projectImageSizeIsAllowed(PROJECT_IMAGE_MAX_BYTES + 1), 'S05A project cover size validation must accept up to 5 MB and reject larger uploads.');
        phase2Assert(projectImageSizeIsAllowed($twoPointThreeMegabytes), 'The Project contract must accept a 2.3 MB upload.');
        phase2Assert(profileImageSizeIsAllowed($twoPointThreeMegabytes) && profileImageSizeIsAllowed(PROFILE_IMAGE_MAX_BYTES) && !profileImageSizeIsAllowed(PROFILE_IMAGE_MAX_BYTES + 1), 'The Profile contract must accept 2.3 MB through 8 MB and reject larger uploads.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
