<?php

class DatabaseConfigurationException extends RuntimeException
{
}

function getRequiredDatabaseEnvironment(string $name, ?string $compatibilityAlias = null): string
{
    $value = getenv($name);
    if ((!is_string($value) || $value === '') && $compatibilityAlias !== null) {
        $value = getenv($compatibilityAlias);
    }

    if (!is_string($value) || $value === '') {
        throw new DatabaseConfigurationException("Required database configuration is missing: {$name}.");
    }

    return $value;
}

function getDatabaseConnection(): PDO
{
    // Preserve the existing PORTFOLIO_DB_* aliases while DB_* remains the primary configuration.
    $host = getRequiredDatabaseEnvironment('DB_HOST', 'PORTFOLIO_DB_HOST');
    $port = getRequiredDatabaseEnvironment('DB_PORT');
    $database = getRequiredDatabaseEnvironment('DB_NAME', 'PORTFOLIO_DB_NAME');
    $username = getRequiredDatabaseEnvironment('DB_USER', 'PORTFOLIO_DB_USER');
    $password = getRequiredDatabaseEnvironment('DB_PASSWORD', 'PORTFOLIO_DB_PASSWORD');

    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new DatabaseConfigurationException('Required database configuration is invalid: DB_PORT.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
