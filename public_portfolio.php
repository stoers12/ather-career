<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/public_lifecycle.php';
require_once __DIR__ . '/includes/portfolio_presentation.php';

function publicPortfolioNotFound(): never
{
    http_response_code(404);
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Portfolio not found</title></head><body><h1>Portfolio not found.</h1></body></html>';
    exit;
}

httpRegisterExceptionBoundary('public_portfolio.php');
httpRequireMethod(['GET', 'HEAD']);
header('Cache-Control: no-store');

try {
    $database = getDatabaseConnection();
    $context = resolvePublicReadContext($database, $_GET['slug'] ?? null);
    if ($context === null) {
        publicPortfolioNotFound();
    }
    $slug = normalizePublicSlug($_GET['slug'] ?? null);
    if ($slug === null) {
        publicPortfolioNotFound();
    }
    $profile = loadPublicPersonalInfo($database, $context);
    if ($profile === null || trim((string) $profile['full_name']) === '') {
        publicPortfolioNotFound();
    }
    $skills = listPublicSkills($database, $context);
    $projects = listPublicProjects($database, $context);
    $experiences = listPublicExperiences($database, $context);
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'public_portfolio.php', 'public_portfolio_load');
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Portfolio unavailable</title></head><body><h1>Portfolio temporarily unavailable.</h1></body></html>';
    exit;
}

$encodedSlug = rawurlencode($slug);
renderPortfolioPresentation($profile, $skills, $projects, [
    'profile_media_url' => (string) ($profile['profile_image_path'] ?? '') !== '' ? "/p/{$encodedSlug}/media/profile" : '',
    'project_media_url' => static fn (int $projectId): string => "/p/{$encodedSlug}/media/project/{$projectId}",
    'contact_action' => "/p/{$encodedSlug}/contact",
    'contact_sent' => ($_GET['contact'] ?? null) === 'sent',
    'experiences' => $experiences,
]);
