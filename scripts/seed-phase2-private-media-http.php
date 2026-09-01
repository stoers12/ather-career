<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1') {
    fwrite(STDERR, "Disposable HTTP media fixture requires explicit test mode.\n");
    exit(1);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/profile_presentation.php';

function httpMediaPng(string $path, int $red): void
{
    $image = imagecreatetruecolor(480, 480);
    imagefill($image, 0, 0, imagecolorallocate($image, $red, 50, 100));
    if (!imagepng($image, $path)) { imagedestroy($image); throw new RuntimeException('HTTP media fixture failed.'); }
    imagedestroy($image);
}

$database = getDatabaseConnection();
$temporary = tempnam(sys_get_temp_dir(), 'p2j07-media-');
if ($temporary === false) throw new RuntimeException('HTTP media fixture temp file failed.');

try {
    $result = [];
    foreach ([['a', true], ['b', false]] as [$label, $published]) {
        httpMediaPng($temporary, $label === 'a' ? 140 : 210);
        $user = $database->prepare("INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version) VALUES ('https://issuer.test/ather-career', :subject, 'active', 1)");
        $user->execute(['subject' => 'p2j07-http-' . $label . '-' . bin2hex(random_bytes(6))]);
        $userId = (int) $database->lastInsertId();
        $portfolio = $database->prepare('INSERT INTO portfolios (owner_user_id, public_slug, is_published, published_at) VALUES (:user_id, :slug, :published, :published_at)');
        $portfolio->execute(['user_id' => $userId, 'slug' => 'http-media-' . $label, 'published' => $published ? 1 : 0, 'published_at' => $published ? '2026-01-01 00:00:00' : null]);
        $portfolioId = (int) $database->lastInsertId();
        $profileKey = copyFileToPrivateMedia($temporary, $portfolioId, 'profile_original', createManagedUploadFilename('profile', 'png'));
        if ($profileKey === null || generateProfilePresentationImage($profileKey, $portfolioId) === null) throw new RuntimeException('HTTP profile fixture failed.');
        $projectKey = copyFileToPrivateMedia($temporary, $portfolioId, 'projects', createManagedUploadFilename('project', 'png'));
        if ($projectKey === null) throw new RuntimeException('HTTP project fixture failed.');
        $profile = $database->prepare('INSERT INTO personal_info (portfolio_id, full_name, profile_image_path) VALUES (:portfolio_id, :name, :key)');
        $profile->execute(['portfolio_id' => $portfolioId, 'name' => 'HTTP Media ' . strtoupper($label), 'key' => $profileKey]);
        $project = $database->prepare("INSERT INTO projects (portfolio_id, title, category, description, github_url, image_path) VALUES (:portfolio_id, :title, 'Media', 'HTTP media fixture', 'https://example.test/media', :key)");
        $project->execute(['portfolio_id' => $portfolioId, 'title' => 'HTTP Project ' . strtoupper($label), 'key' => $projectKey]);
        $result[$label] = ['user_id' => $userId, 'portfolio_id' => $portfolioId, 'project_id' => (int) $database->lastInsertId()];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
} finally {
    @unlink($temporary);
}
