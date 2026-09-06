<?php

declare(strict_types=1);

/*
 * Fresh Production databases start from the immutable V1 baseline. Before the
 * ownership contract can be applied, that one preserved V1 Portfolio must be
 * bound to a separately verified durable OIDC subject. This CLI-only step is
 * deliberately idempotent and does not contact the OIDC provider.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth0_oidc.php';
require_once __DIR__ . '/ownership_backfill.php';

function productionOwnershipBootstrapFailure(string $message): never
{
    fwrite(STDERR, "Production ownership bootstrap stopped: {$message}\n");
    exit(1);
}

try {
    $database = getDatabaseConnection();
    $ownershipContractApplied = $database->prepare('SELECT name FROM schema_migrations WHERE version = "004" LIMIT 1');
    $ownershipContractApplied->execute();
    if ($ownershipContractApplied->fetchColumn() !== false) {
        echo "Production ownership bootstrap already complete.\n";
        exit;
    }

    $subject = getenv('PRESERVED_V1_OIDC_SUBJECT');
    if (!is_string($subject) || $subject === '' || strlen($subject) > 255) {
        productionOwnershipBootstrapFailure('PRESERVED_V1_OIDC_SUBJECT is required before the ownership contract is applied.');
    }
    $configuration = auth0ConfigurationFromEnvironment();
    OwnershipBackfill::execute($database, $configuration->issuer, $subject);
    echo "Production ownership bootstrap completed.\n";
} catch (Auth0OidcException | OwnershipBackfillException | PDOException | DatabaseConfigurationException $exception) {
    productionOwnershipBootstrapFailure('the verified preserved-owner binding could not be applied.');
} catch (Throwable $exception) {
    productionOwnershipBootstrapFailure('the ownership bootstrap could not be completed.');
}
