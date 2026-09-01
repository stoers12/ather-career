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
        $vhost = self::read('docker/apache/production-vhost.conf');
        $accessPolicy = self::read('docker/apache/access-policy.conf');
        $compose = self::read('docker-compose.production.yml');

        phase2Assert(str_contains($storage, "getenv('ATHERCAR_STORAGE_ROOT')") && str_contains($storage, 'isAbsoluteFilesystemPath') && str_contains($storage, 'outside the public document root'), 'P2J-07 private root contract is incomplete.');
        phase2Assert(str_contains($storage, 'parseManagedMediaKey') && str_contains($storage, "str_contains(\$candidate, '%')") && str_contains($storage, "str_contains(\$candidate, '\\\\')"), 'P2J-07 managed key traversal rejection is incomplete.');
        phase2Assert(str_contains($storage, "'portfolios/' . \$portfolioId") && str_contains($storage, 'move_uploaded_file') && str_contains($storage, "'.stage-'") && str_contains($storage, 'rename('), 'P2J-07 tenant layout and staged upload sequence are incomplete.');
        phase2Assert(str_contains($access, 'findAuthorizedProject($database, $context') && str_contains($access, 'portfolio_id = :public_portfolio_id'), 'P2J-07 media access is not scoped through owner/public Portfolio contexts.');
        phase2Assert(!str_contains($ownerRoute, 'portfolio_id') && !str_contains($ownerRoute, 'user_id') && !str_contains($ownerRoute, 'key') && str_contains($ownerRoute, 'requireOwnerPortfolioContext'), 'P2J-07 owner handler accepts forbidden authority.');
        phase2Assert(!str_contains($publicRoute, 'requireOwnerPortfolioContext') && !str_contains($publicRoute, 'key') && str_contains($publicRoute, 'resolvePublicReadContext'), 'P2J-07 public handler does not use public authority exclusively.');
        phase2Assert(str_contains($ownerActions, 'storeValidatedProfileImage($files[\'profile_image\'] ?? [], $errors, $context->portfolioId)') && str_contains($ownerActions, 'storeValidatedProjectImage($files[\'project_image\'], $errors, $context->portfolioId)'), 'P2J-07 uploads are not Portfolio scoped.');
        phase2Assert(!preg_match('/^\s*Alias\s+\/uploads\//mi', $vhost) && str_contains($accessPolicy, '^/uploads(?:/|$)'), 'P2J-07 direct upload access was not retired.');
        phase2Assert(str_contains($compose, 'ATHERCAR_STORAGE_ROOT: /var/lib/ather-career/storage') && str_contains($compose, 'portfolio_production_storage:/var/lib/ather-career/storage'), 'P2J-07 production private storage configuration is incomplete.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
