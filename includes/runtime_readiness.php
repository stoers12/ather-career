<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth0_oidc.php';
require_once __DIR__ . '/public_url.php';
require_once __DIR__ . '/storage.php';

/** @return list<array{version: string, name: string}> */
function runtimeExpectedMigrations(): array
{
    $directory = realpath(__DIR__ . '/../database/migrations');
    if ($directory === false || !is_dir($directory)) {
        throw new RuntimeException('Migration manifest is unavailable.');
    }

    $migrations = [];
    foreach (new DirectoryIterator($directory) as $file) {
        if ($file->isDot()) {
            continue;
        }
        if (!$file->isFile() || preg_match('/^(\d{3})_([a-z0-9][a-z0-9_-]*)\.sql$/', $file->getFilename(), $matches) !== 1) {
            throw new RuntimeException('Migration manifest is invalid.');
        }
        $migrations[] = ['version' => $matches[1], 'name' => $matches[2]];
    }
    usort($migrations, static fn (array $left, array $right): int => strcmp($left['version'], $right['version']));

    return $migrations;
}

function runtimeDatabaseConfigurationHasSafeFormat(): bool
{
    try {
        $host = getRequiredDatabaseEnvironment('DB_HOST', 'PORTFOLIO_DB_HOST');
        $port = getRequiredDatabaseEnvironment('DB_PORT');
        $database = getRequiredDatabaseEnvironment('DB_NAME', 'PORTFOLIO_DB_NAME');
        $user = getRequiredDatabaseEnvironment('DB_USER', 'PORTFOLIO_DB_USER');
        $password = getRequiredDatabaseEnvironment('DB_PASSWORD', 'PORTFOLIO_DB_PASSWORD');
    } catch (DatabaseConfigurationException) {
        return false;
    }

    return preg_match('/^[A-Za-z0-9.-]{1,253}$/', $host) === 1
        && ctype_digit($port) && (int) $port >= 1 && (int) $port <= 65535
        && preg_match('/^[A-Za-z0-9_]{1,64}$/', $database) === 1
        && preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $user) === 1
        && $password !== '' && strlen($password) <= 1024;
}

function runtimeSessionCookieConfigurationHasSafeFormat(): bool
{
    $configured = getenv('SESSION_COOKIE_SECURE');
    if (!is_string($configured)) {
        return false;
    }

    return in_array(strtolower(trim($configured)), ['1', '0', 'true', 'false', 'on', 'off', 'yes', 'no'], true);
}

function runtimeMigrationLedgerMatches(PDO $database): bool
{
    $expected = runtimeExpectedMigrations();
    $actual = $database->query('SELECT version, name FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($actual) || count($actual) !== count($expected)) {
        return false;
    }

    foreach ($expected as $index => $migration) {
        if (($actual[$index]['version'] ?? null) !== $migration['version'] || ($actual[$index]['name'] ?? null) !== $migration['name']) {
            return false;
        }
    }

    return true;
}

function runtimeSchemaIsCompatible(PDO $database): bool
{
    $requiredColumns = [
        ['users', 'oidc_subject'],
        ['portfolios', 'owner_user_id'],
        ['portfolios', 'public_slug'],
        ['portfolios', 'is_published'],
        ['personal_info', 'hero_headline'],
        ['personal_info', 'public_contact_visible'],
        ['projects', 'technologies'],
        ['experiences', 'portfolio_id'],
        ['messages', 'recipient_portfolio_id'],
    ];

    $conditions = [];
    $parameters = [];
    foreach ($requiredColumns as $index => [$table, $column]) {
        $conditions[] = "(table_name = :table_{$index} AND column_name = :column_{$index})";
        $parameters["table_{$index}"] = $table;
        $parameters["column_{$index}"] = $column;
    }
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND (' . implode(' OR ', $conditions) . ')'
    );
    $statement->execute($parameters);

    return (int) $statement->fetchColumn() === count($requiredColumns);
}

/** Returns null only when normal traffic can safely be served. */
function runtimeReadinessFailureReason(): ?string
{
    if (!runtimeDatabaseConfigurationHasSafeFormat() || !runtimeSessionCookieConfigurationHasSafeFormat()) {
        return 'configuration_invalid';
    }

    try {
        $database = getDatabaseConnection();
        if ((int) $database->query('SELECT 1')->fetchColumn() !== 1) {
            return 'database_unavailable';
        }
    } catch (PDOException) {
        return 'database_unavailable';
    } catch (Throwable) {
        return 'configuration_invalid';
    }

    try {
        if (!runtimeMigrationLedgerMatches($database) || !runtimeSchemaIsCompatible($database)) {
            return 'schema_incompatible';
        }
    } catch (Throwable) {
        return 'schema_incompatible';
    }

    try {
        requirePrivateStorageRoot(true);
    } catch (Throwable) {
        return 'storage_unavailable';
    }

    try {
        publicBaseUrl();
        auth0ConfigurationFromEnvironment();
    } catch (Throwable) {
        return 'configuration_invalid';
    }

    return null;
}
