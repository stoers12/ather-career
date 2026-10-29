<?php

declare(strict_types=1);

// The preview router or focused CLI test must opt in. Production never routes here.
if (!defined('ATHER_ONBOARDING_PREVIEW') || ATHER_ONBOARDING_PREVIEW !== true || !in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/http.php';
require_once dirname(__DIR__, 2) . '/includes/onboarding_presentation.php';
httpRequireMethod(['GET', 'HEAD']);
$locale = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'ar';
$step = in_array($_GET['step'] ?? null, ['1', '2', '3', '4', '5'], true) ? (int) $_GET['step'] : 1;
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'");
renderOnboardingPresentation($locale, $step);
