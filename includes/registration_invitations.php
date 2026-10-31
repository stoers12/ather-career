<?php

declare(strict_types=1);

require_once __DIR__ . '/identity_repository.php';

// Single-use registration invitations for the invite-only beta (L-01).
// Codes are 12 Crockford base32 characters (60 bits), shown as XXXX-XXXX-XXXX.
// Only their SHA-256 digest is stored; the code is printed once on creation.

const REGISTRATION_INVITATION_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
const REGISTRATION_INVITATION_LENGTH = 12;
const REGISTRATION_INVITATION_SESSION_KEY = 'ather_registration_invitation_id';
const REGISTRATION_INVITATION_DEFAULT_DAYS = 30;
const REGISTRATION_INVITATION_MAX_DAYS = 365;
const REGISTRATION_INVITATION_MAX_BATCH = 200;
const REGISTRATION_INVITATION_RATE_LIMIT_ATTEMPTS = 10;
const REGISTRATION_INVITATION_RATE_LIMIT_WINDOW_SECONDS = 900;

/** Uppercase, drop separators and spaces; null when the result is not a well-formed code. */
function normalizeRegistrationInvitationCode(mixed $input): ?string
{
    if (!is_string($input) || strlen($input) > 64) {
        return null;
    }
    $code = strtoupper(str_replace(['-', ' ', "\t"], '', trim($input)));

    return preg_match('/^[0-9A-HJKMNP-TV-Z]{' . REGISTRATION_INVITATION_LENGTH . '}$/', $code) === 1 ? $code : null;
}

function registrationInvitationDigest(string $normalizedCode): string
{
    return hash('sha256', $normalizedCode);
}

function formatRegistrationInvitationCode(string $normalizedCode): string
{
    return implode('-', str_split($normalizedCode, 4));
}

function generateRegistrationInvitationCode(): string
{
    $code = '';
    for ($index = 0; $index < REGISTRATION_INVITATION_LENGTH; $index++) {
        $code .= REGISTRATION_INVITATION_ALPHABET[random_int(0, 31)];
    }

    return $code;
}

/** The id of an unused, unrevoked, unexpired invitation for this code, else null. */
function findUsableRegistrationInvitation(PDO $database, mixed $input): ?int
{
    $code = normalizeRegistrationInvitationCode($input);
    if ($code === null) {
        return null;
    }
    $statement = $database->prepare(
        'SELECT id FROM registration_invitations
         WHERE code_digest = :digest AND used_at IS NULL AND revoked_at IS NULL AND expires_at > CURRENT_TIMESTAMP'
    );
    $statement->execute(['digest' => registrationInvitationDigest($code)]);
    $id = $statement->fetchColumn();

    return $id === false ? null : (int) $id;
}

function rememberRegistrationInvitation(int $invitationId): void
{
    $_SESSION[REGISTRATION_INVITATION_SESSION_KEY] = $invitationId;
}

function forgetRegistrationInvitation(): void
{
    unset($_SESSION[REGISTRATION_INVITATION_SESSION_KEY]);
}

/**
 * Spend the invitation remembered at registration start. Must run inside the
 * transaction that inserts the user: the conditional update lets exactly one
 * concurrent registration win, and any failure rolls the new user back.
 */
function consumeRegistrationInvitation(PDO $database, int $userId): void
{
    if (!$database->inTransaction()) {
        throw new LogicException('Invitation consumption requires the registration transaction.');
    }
    $invitationId = $_SESSION[REGISTRATION_INVITATION_SESSION_KEY] ?? null;
    if (!is_int($invitationId) || $invitationId < 1) {
        throw new IdentityRepositoryException('Registration invitation is required.');
    }
    $update = $database->prepare(
        'UPDATE registration_invitations SET used_at = CURRENT_TIMESTAMP, used_by_user_id = :user_id
         WHERE id = :id AND used_at IS NULL AND revoked_at IS NULL AND expires_at > CURRENT_TIMESTAMP'
    );
    $update->execute(['user_id' => $userId, 'id' => $invitationId]);
    if ($update->rowCount() !== 1) {
        throw new IdentityRepositoryException('Registration invitation is no longer usable.');
    }
    forgetRegistrationInvitation();
}

/** @return list<string> the new codes, formatted; they are not recoverable afterwards. */
function createRegistrationInvitations(PDO $database, int $count, int $days, ?string $label): array
{
    if ($count < 1 || $count > REGISTRATION_INVITATION_MAX_BATCH) {
        throw new InvalidArgumentException('Count must be between 1 and ' . REGISTRATION_INVITATION_MAX_BATCH . '.');
    }
    if ($days < 1 || $days > REGISTRATION_INVITATION_MAX_DAYS) {
        throw new InvalidArgumentException('Days must be between 1 and ' . REGISTRATION_INVITATION_MAX_DAYS . '.');
    }
    $label = $label === null ? null : trim($label);
    if ($label !== null && ($label === '' || mb_strlen($label) > 120 || preg_match('/[\x00-\x1f\x7f]/', $label) === 1)) {
        throw new InvalidArgumentException('Label must be 1-120 printable characters.');
    }
    $expiresAt = gmdate('Y-m-d H:i:s', time() + $days * 86400);
    $insert = $database->prepare(
        'INSERT INTO registration_invitations (code_digest, label, expires_at) VALUES (:digest, :label, :expires_at)'
    );
    $codes = [];
    $database->beginTransaction();
    try {
        while (count($codes) < $count) {
            $code = generateRegistrationInvitationCode();
            if (isset($codes[$code])) {
                continue;
            }
            $insert->execute(['digest' => registrationInvitationDigest($code), 'label' => $label, 'expires_at' => $expiresAt]);
            $codes[$code] = formatRegistrationInvitationCode($code);
        }
        $database->commit();
    } catch (Throwable $exception) {
        $database->rollBack();
        throw $exception;
    }

    return array_values($codes);
}

/** @return list<array{id: int, label: ?string, state: string, created_at: string, expires_at: string, used_at: ?string}> */
function listRegistrationInvitations(PDO $database): array
{
    $rows = $database->query(
        'SELECT id, label, created_at, expires_at, used_at, revoked_at,
                CASE WHEN revoked_at IS NOT NULL THEN \'revoked\'
                     WHEN used_at IS NOT NULL THEN \'used\'
                     WHEN expires_at <= CURRENT_TIMESTAMP THEN \'expired\'
                     ELSE \'available\' END AS state
         FROM registration_invitations ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'label' => $row['label'],
        'state' => (string) $row['state'],
        'created_at' => (string) $row['created_at'],
        'expires_at' => (string) $row['expires_at'],
        'used_at' => $row['used_at'],
    ], $rows);
}

function revokeRegistrationInvitation(PDO $database, int $invitationId): bool
{
    $update = $database->prepare(
        'UPDATE registration_invitations SET revoked_at = CURRENT_TIMESTAMP WHERE id = :id AND used_at IS NULL AND revoked_at IS NULL'
    );
    $update->execute(['id' => $invitationId]);

    return $update->rowCount() === 1;
}
