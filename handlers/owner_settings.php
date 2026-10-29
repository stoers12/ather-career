<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/owner_session.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/error_reporting.php';
require_once dirname(__DIR__) . '/includes/http.php';
require_once dirname(__DIR__) . '/includes/owner_flow.php';
require_once dirname(__DIR__) . '/includes/portfolio_scoped_data.php';
require_once dirname(__DIR__) . '/includes/owner_layout.php';
require_once dirname(__DIR__) . '/includes/owner_settings.php';
require_once dirname(__DIR__) . '/includes/owner_settings_presentation.php';

// Owner settings (decision ق-31): only preferences the product supports. The
// page writes nothing, so it is GET-only; see includes/owner_settings.php.
startOwnerSession();
httpRegisterExceptionBoundary('owner_settings.php');
httpRequireMethod(['GET', 'HEAD']);

try {
    $database = getDatabaseConnection();
    $context = requireOwnerPortfolioContext($database);
    $ownerName = (string) ((loadAuthorizedPersonalInfo($database, $context) ?: [])['full_name'] ?? '');
} catch (PDOException | DatabaseConfigurationException $exception) {
    // Without the database the owner cannot be confirmed, so no account chrome renders.
    reportApplicationError($exception, 'owner_settings.php', 'owner_settings_load');
    httpAbortHtml(503, 'Settings are temporarily unavailable.');
}

$locale = ownerLocale();
ownerLayoutStart('Settings', 'settings', true, ['locale' => $locale, 'account_name' => $ownerName]);
renderOwnerSettingsPage(ownerSettingsPageData($locale));
ownerLayoutEnd();
