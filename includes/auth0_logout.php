<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0_oidc.php';
require_once __DIR__ . '/public_url.php';
require_once __DIR__ . '/http.php';

/** Fixed application return URI; register this exact value for the configured client. */
function auth0LogoutUrl(): string
{
    $configuration = auth0ConfigurationFromEnvironment();
    if (filter_var($configuration->issuer, FILTER_VALIDATE_URL) === false
        || preg_match('/[\x00-\x20\x7f]/', $configuration->issuer) === 1) {
        throw new Auth0OidcException('issuer_configuration_invalid');
    }

    // Alternative Logout supports the existing configuration without retaining
    // an ID token or sid. No request input or federated/provider logout is used.
    return $configuration->issuer . 'v2/logout?' . http_build_query([
        'client_id' => $configuration->clientId,
        'returnTo' => publicBaseUrl() . '/signin.php',
    ], '', '&', PHP_QUERY_RFC3986);
}

/** Called only after local session retirement; does not claim remote success. */
function renderOwnerPartialLogout(): never
{
    httpSetHtmlResponse(503);
    header('Cache-Control: private, no-store');
    header('Content-Language: ar, en');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>خروج جزئي — Partial sign out</title></head><body><main>'
        . '<h1>انتهت جلسة أثر</h1>'
        . '<p>تعذّر بدء تسجيل الخروج من Auth0. قد تبقى جلسة تسجيل الدخول الموحد نشطة، وكذلك جلسة المتصفح لدى Google أو مزوّد الهوية.</p>'
        . '<div lang="en" dir="ltr"><h2>You are signed out of Ather</h2>'
        . '<p>Auth0 sign-out could not be started. Its SSO session may still be active, and your Google or other identity-provider browser session may remain active.</p></div>'
        . '<a href="/signin.php">تسجيل الدخول / Sign In</a>'
        . '</main></body></html>';
    exit;
}
