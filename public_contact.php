<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/public_contact.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/portfolio_presentation.php';

const PUBLIC_CONTACT_RATE_LIMIT_ATTEMPTS = 3;
const PUBLIC_CONTACT_RATE_LIMIT_WINDOW_SECONDS = 900;

function publicContactNotFound(): never
{
    httpSetHtmlResponse(404);
    httpRenderStatusPage('Portfolio not found', 'Portfolio not found.');
    exit;
}

function publicContactError(int $status, array $errors): never
{
    httpSetHtmlResponse($status);
    httpRenderStatusPage('Message unavailable', 'Message unavailable.', array_values(array_map(static fn (mixed $error): string => (string) $error, $errors)));
    exit;
}

/** @param array{name: string, email: string, message: string} $values
 *  @param array<string, string> $fieldErrors
 */
function publicContactValidationFailure(PDO $database, PublicReadContext $context, string $slug, array $values, array $fieldErrors): never
{
    $profile = loadPublicPersonalInfo($database, $context);
    if ($profile === null || trim((string) $profile['full_name']) === '') {
        publicContactNotFound();
    }

    $skills = listPublicSkills($database, $context);
    $projects = listPublicProjects($database, $context);
    $experiences = listPublicExperiences($database, $context);
    $encodedSlug = rawurlencode($slug);

    http_response_code(422);
    renderPortfolioPresentation($profile, $skills, $projects, [
        'profile_media_url' => (string) ($profile['profile_image_path'] ?? '') !== '' ? "/p/{$encodedSlug}/media/profile" : '',
        'project_media_url' => static fn (int $projectId): string => "/p/{$encodedSlug}/media/project/{$projectId}",
        'contact_action' => "/p/{$encodedSlug}/contact",
        'contact_values' => $values,
        'contact_field_errors' => $fieldErrors,
        'contact_form_error' => 'Please correct the highlighted fields and try again.',
        'experiences' => $experiences,
    ]);
    exit;
}

httpRegisterExceptionBoundary('public_contact.php');
httpRequireMethod(['POST']);

header('Cache-Control: no-store');

try {
    $database = getDatabaseConnection();
    $submission = preparePublicContactSubmission($database, $_GET['slug'] ?? null, $_POST);
    if ($submission['context'] === null) {
        publicContactNotFound();
    }
    if ($submission['errors'] !== []) {
        $slug = normalizePublicSlug($_GET['slug'] ?? null);
        if ($slug === null) {
            publicContactNotFound();
        }
        publicContactValidationFailure(
            $database,
            $submission['context'],
            $slug,
            $submission['values'],
            $submission['field_errors'],
        );
    }

    $rateLimit = consumeRateLimit(
        'contact',
        rateLimitClientIp(),
        PUBLIC_CONTACT_RATE_LIMIT_ATTEMPTS,
        PUBLIC_CONTACT_RATE_LIMIT_WINDOW_SECONDS,
    );
    if (!$rateLimit['allowed']) {
        reportSecurityEvent('rate_limit_denial', 'denied', ['scope' => 'public_contact', 'reason' => 'threshold_exceeded']);
        header('Retry-After: ' . $rateLimit['retry_after']);
        publicContactError(429, ['Please wait before sending another message.']);
    }

    createPublicContactMessage($database, $submission['context'], $submission['values']);
    $slug = normalizePublicSlug($_GET['slug'] ?? null);
    if ($slug === null) {
        publicContactNotFound();
    }
    httpRedirect('/p/' . rawurlencode($slug) . '?contact=sent#contact');
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'public_contact.php', 'public_contact_submit');
    publicContactError(503, ['The message could not be saved right now.']);
} catch (Throwable $exception) {
    reportApplicationError($exception, 'public_contact.php', 'public_contact_rate_limit');
    publicContactError(503, ['Messages are temporarily unavailable. Please try again later.']);
}
