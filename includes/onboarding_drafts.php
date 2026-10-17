<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';

final class OnboardingDraftConflictException extends RuntimeException
{
}

const ONBOARDING_DRAFT_MAX_VERSION = 2147483647;
const ONBOARDING_DRAFT_MAX_BYTES = 16384;

/** Draft candidates only. These are not final product catalogs or completion rules. */
function onboardingDraftFields(): array
{
    return [
        'public_identity' => [
            'display_name' => [150, 600], 'name_ar' => [150, 600], 'name_en' => [150, 600],
            'city' => [100, 400], 'country_code' => [2, 2], 'preferred_language' => [35, 35],
        ],
        'professional_snapshot' => [
            'professional_status' => [80, 320], 'specialization' => [150, 600],
            'headline' => [200, 800], 'university' => [200, 800],
            'graduation_year' => [4, 4], 'biography' => [2000, 8000],
        ],
        'profile_identity' => ['slug_candidate' => [63, 63], 'phone' => [40, 160]],
    ];
}

/** NULL explicitly clears. Missing keys are never synthesized. */
function onboardingDraftValidatePatch(mixed $section, array $fields): array
{
    $sections = onboardingDraftFields();
    if (!is_string($section) || !isset($sections[$section])) {
        throw new InvalidArgumentException('Unsupported draft section.');
    }
    foreach ($fields as $field => $value) {
        if (!is_string($field) || !isset($sections[$section][$field])) {
            throw new InvalidArgumentException('Unsupported field for draft section.');
        }
        if ($value === null) { continue; }
        [$characters, $bytes] = $sections[$section][$field];
        if (!is_string($value) || strlen($value) > $bytes || preg_match('//u', $value) !== 1
            || mb_strlen($value, 'UTF-8') > $characters || preg_match('/(*UCP)\S/u', $value) !== 1
            || preg_match('/\p{Cc}/u', $field === 'biography' ? str_replace("\n", '', $value) : $value) !== 0) {
            throw new InvalidArgumentException('Invalid bounded draft text.');
        }
        $pattern = match ($field) {
            'country_code' => '/\A[A-Z]{2}\z/D',
            'preferred_language' => '/\A[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*\z/D',
            'graduation_year' => '/\A[0-9]{4}\z/D',
            'slug_candidate' => '/\A[a-z0-9-]{1,63}\z/D',
            default => null,
        };
        if ($pattern !== null && preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException('Invalid draft candidate format.');
        }
    }
    ksort($fields, SORT_STRING);
    return $fields;
}

/** Stable exact JSON; no trimming, normalization or private-data logging. */
function onboardingDraftEncode(array $fields): string
{
    ksort($fields, SORT_STRING);
    $json = json_encode((object) $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($json) > ONBOARDING_DRAFT_MAX_BYTES) {
        throw new InvalidArgumentException('Draft payload exceeds its byte bound.');
    }
    return $json;
}

/** Fail closed on corrupt/foreign payloads instead of projecting unknown fields. */
function onboardingDraftDecode(string $payload): array
{
    try {
        $object = json_decode($payload, false, 16, JSON_THROW_ON_ERROR);
        if (!$object instanceof stdClass) { throw new RuntimeException(); }
        $fields = (array) $object;
        $known = [];
        foreach (onboardingDraftFields() as $section => $rules) {
            $part = array_intersect_key($fields, $rules);
            onboardingDraftValidatePatch($section, $part);
            $known += $part;
        }
        if (count($known) !== count($fields) || in_array(null, $fields, true)
            || onboardingDraftEncode($fields) !== $payload) {
            throw new RuntimeException();
        }
        return $fields;
    } catch (Throwable $error) {
        throw new RuntimeException('Stored draft is incompatible.', 0, $error);
    }
}

/** @internal Private projection: no User selector or retry fingerprint is exposed. */
function onboardingDraftResult(?array $row): array
{
    return [
        'version' => $row === null ? 0 : (int) $row['version'],
        'fields' => $row === null ? [] : onboardingDraftDecode($row['payload']),
        'last_edited_section' => $row['last_section'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

/** @internal Owns all draft access. User locking also serializes absent-row creation
 * and authoritative account revocation; it never trusts the supplied context alone.
 */
function onboardingDraftTransaction(PDO $database, AuthenticatedUserContext $context, callable $operation): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new AuthorizationDeniedException('Draft requires an active session.');
    }
    if ($database->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION || $database->inTransaction()) {
        throw new LogicException('Draft requires exception mode and an owned transaction.');
    }
    $user = requireAuthenticatedUser($database);
    if ($user->userId !== $context->userId) {
        throw new AuthorizationDeniedException('Draft User context mismatch.');
    }
    $database->beginTransaction();
    try {
        $lock = $database->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $lock->execute([$user->userId]);
        $current = requireAuthenticatedUser($database);
        if ($current->userId !== $user->userId) {
            throw new AuthorizationDeniedException('Draft User context mismatch.');
        }
        $select = $database->prepare('SELECT * FROM onboarding_drafts WHERE user_id = ?');
        $select->execute([$current->userId]);
        $row = $select->fetch(PDO::FETCH_ASSOC) ?: null;
        $result = $operation($current->userId, $row);
        $database->commit();
        return $result;
    } catch (Throwable $error) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $error;
    }
}

/** Resume only the current User. Absence is version 0 and empty fields. */
function readOnboardingDraft(PDO $database, AuthenticatedUserContext $context): array
{
    return onboardingDraftTransaction($database, $context, static fn (int $userId, ?array $row): array => onboardingDraftResult($row));
}

/** Partial section save, optimistic version and one version-bound retry receipt.
 * No completion, consent, readiness, slug reservation or publication authority.
 * Future request integration must supply its own reviewed CSRF/presentation layer.
 */
function saveOnboardingDraft(PDO $database, AuthenticatedUserContext $context, mixed $expectedVersion, mixed $operationId, mixed $section, array $fields): array
{
    if (!is_int($expectedVersion) || $expectedVersion < 0 || $expectedVersion > ONBOARDING_DRAFT_MAX_VERSION
        || !is_string($operationId)
        || preg_match('/\A' . $expectedVersion . ':[a-f0-9]{32}\z/D', $operationId) !== 1) {
        throw new InvalidArgumentException('Draft version or version-bound operation ID is invalid.');
    }
    $patch = onboardingDraftValidatePatch($section, $fields);
    $fingerprint = hash('sha256', json_encode([$expectedVersion, $section, (object) $patch], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), true);
    return onboardingDraftTransaction($database, $context, static function (int $userId, ?array $row) use ($database, $expectedVersion, $operationId, $section, $patch, $fingerprint): array {
        if ($row !== null && hash_equals($row['last_operation_id'], $operationId)) {
            if (!hash_equals($row['last_operation_digest'], $fingerprint)) {
                throw new OnboardingDraftConflictException('Draft operation input conflict.');
            }
            return onboardingDraftResult($row);
        }
        $version = $row === null ? 0 : (int) $row['version'];
        if ($version !== $expectedVersion || $version >= ONBOARDING_DRAFT_MAX_VERSION) {
            throw new OnboardingDraftConflictException('Draft version conflict.');
        }
        $fields = $row === null ? [] : onboardingDraftDecode($row['payload']);
        foreach ($patch as $field => $value) {
            if ($value === null) { unset($fields[$field]); } else { $fields[$field] = $value; }
        }
        $parameters = [
            'user_id' => $userId, 'version' => $version + 1, 'payload' => onboardingDraftEncode($fields),
            'last_section' => $section, 'operation_id' => $operationId, 'digest' => $fingerprint,
        ];
        if ($row === null) {
            $write = $database->prepare('INSERT INTO onboarding_drafts (user_id, version, payload, last_section, last_operation_id, last_operation_digest, created_at, updated_at) VALUES (:user_id, :version, :payload, :last_section, :operation_id, :digest, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
        } else {
            $parameters['expected_version'] = $version;
            $write = $database->prepare('UPDATE onboarding_drafts SET version=:version, payload=:payload, last_section=:last_section, last_operation_id=:operation_id, last_operation_digest=:digest, updated_at=UTC_TIMESTAMP(6) WHERE user_id=:user_id AND version=:expected_version');
        }
        $write->execute($parameters);
        if ($write->rowCount() !== 1) { throw new OnboardingDraftConflictException('Draft changed during save.'); }
        $select = $database->prepare('SELECT * FROM onboarding_drafts WHERE user_id = ?');
        $select->execute([$userId]);
        $saved = $select->fetch(PDO::FETCH_ASSOC);
        if (!is_array($saved)) { throw new RuntimeException('Saved draft is unavailable.'); }
        return onboardingDraftResult($saved);
    });
}
