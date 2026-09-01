<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1' || count($argv) !== 2) exit(2);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth0_identity.php';

try {
    $configuration = new Auth0OidcConfiguration('https://test-tenant.us.auth0.com/', 'test-tenant.us.auth0.com', 'test-client', 'test-secret', 'https://app.example.test/owner_oidc_callback.php');
    $user = resolveAuth0InternalUser(getDatabaseConnection(), $configuration, new Auth0ValidatedIdentity($configuration->issuer, $argv[1]));
    echo $user['user_id'] . "\n";
} catch (Throwable) {
    exit(1);
}
