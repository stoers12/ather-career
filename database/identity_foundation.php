<?php

declare(strict_types=1);

final class IdentityFoundationPreconditionException extends RuntimeException
{
}

/** @return list<array{id: int, oidc_issuer: string, oidc_subject: string}> */
function identityFoundationLegacyUsers(PDO $database, bool $lock = false): array
{
    $statement = $database->query(
        'SELECT id, oidc_issuer, oidc_subject FROM users ORDER BY id' . ($lock ? ' FOR UPDATE' : '')
    );
    $users = [];
    $pairs = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int) $row['id'];
        $issuer = $row['oidc_issuer'];
        $subject = $row['oidc_subject'];
        if ($id < 1 || !is_string($issuer) || $issuer === '' || strlen($issuer) > 2048
            || !is_string($subject) || $subject === '' || strlen($subject) > 255) {
            throw new IdentityFoundationPreconditionException('Migration 014 found an incomplete legacy identity.');
        }
        $pair = hash('sha256', pack('N', strlen($issuer)) . $issuer . $subject);
        if (isset($pairs[$pair])) {
            throw new IdentityFoundationPreconditionException('Migration 014 found a duplicate legacy identity.');
        }
        $pairs[$pair] = true;
        $users[] = ['id' => $id, 'oidc_issuer' => $issuer, 'oidc_subject' => $subject];
    }

    return $users;
}

function identityFoundationVerifyTable(PDO $database): void
{
    requireColumnDefinition($database, 'user_identities', 'id', 'int unsigned', 'NO');
    requireColumnDefinition($database, 'user_identities', 'user_id', 'int unsigned', 'NO');
    requireColumnDefinition($database, 'user_identities', 'oidc_issuer', 'varbinary(2048)', 'NO');
    requireColumnDefinition($database, 'user_identities', 'oidc_subject', 'varbinary(255)', 'NO');
    requireColumnDefinition($database, 'user_identities', 'is_primary', 'tinyint(1)', 'NO');
    requireColumnDefinition($database, 'user_identities', 'provider_name', 'varchar(32)', 'YES');
    requireColumnDefinition($database, 'user_identities', 'connection_name', 'varchar(64)', 'YES');
    requireColumnDefinition($database, 'user_identities', 'created_at', 'timestamp', 'NO');
    if (!verifyPrimaryIndex($database, 'user_identities', 'id')
        || !hasExpectedIndex($database, 'user_identities', 'uq_user_identities_pair', ['oidc_issuer', 'oidc_subject'], true)
        || !hasExpectedIndex($database, 'user_identities', 'idx_user_identities_user', ['user_id'], false)
        || !hasExpectedForeignKey($database, 'user_identities', 'fk_user_identities_user', 'user_id', 'users', 'id')) {
        throw new IdentityFoundationPreconditionException('Migration 014 found an incompatible identity table.');
    }
    requireExpectedCheckConstraint($database, 'user_identities', 'chk_user_identities_primary', ['is_primary', '0', '1']);
}

function executeIdentityFoundationMigration(PDO $database): void
{
    // Fail before MySQL DDL if the old authority cannot be copied exactly.
    identityFoundationLegacyUsers($database);
    $database->exec(
        'CREATE TABLE IF NOT EXISTS user_identities (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            oidc_issuer VARBINARY(2048) NOT NULL,
            oidc_subject VARBINARY(255) NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 1,
            provider_name VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
            connection_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_identities_pair (oidc_issuer, oidc_subject),
            KEY idx_user_identities_user (user_id),
            CONSTRAINT fk_user_identities_user FOREIGN KEY (user_id) REFERENCES users (id)
                ON UPDATE RESTRICT ON DELETE RESTRICT,
            CONSTRAINT chk_user_identities_primary CHECK (is_primary IN (0, 1))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    identityFoundationVerifyTable($database);
    testOnlyMigrationFailurePoint('after-identity-table-create');

    $database->beginTransaction();
    try {
        $users = identityFoundationLegacyUsers($database, true);
        $expected = [];
        foreach ($users as $user) {
            $expected[$user['id']] = $user;
        }
        $existing = $database->query(
            'SELECT user_id, oidc_issuer, oidc_subject, is_primary, provider_name, connection_name
             FROM user_identities ORDER BY id FOR UPDATE'
        )->fetchAll(PDO::FETCH_ASSOC);
        $found = [];
        foreach ($existing as $binding) {
            $userId = (int) $binding['user_id'];
            $legacy = $expected[$userId] ?? null;
            if ($legacy === null || isset($found[$userId])
                || !hash_equals($legacy['oidc_issuer'], (string) $binding['oidc_issuer'])
                || !hash_equals($legacy['oidc_subject'], (string) $binding['oidc_subject'])
                || (int) $binding['is_primary'] !== 1
                || $binding['provider_name'] !== null || $binding['connection_name'] !== null) {
                throw new IdentityFoundationPreconditionException('Migration 014 found a conflicting identity binding.');
            }
            $found[$userId] = true;
        }
        $insert = $database->prepare(
            'INSERT INTO user_identities (user_id, oidc_issuer, oidc_subject)
             VALUES (:user_id, :issuer, :subject)'
        );
        foreach ($users as $user) {
            if (isset($found[$user['id']])) {
                continue;
            }
            $insert->execute([
                'user_id' => $user['id'],
                'issuer' => $user['oidc_issuer'],
                'subject' => $user['oidc_subject'],
            ]);
        }
        if ((int) $database->query('SELECT COUNT(*) FROM user_identities')->fetchColumn() !== count($users)) {
            throw new IdentityFoundationPreconditionException('Migration 014 identity count does not match legacy users.');
        }
        $database->commit();
    } catch (Throwable $exception) {
        $database->rollBack();
        throw $exception;
    }
}
