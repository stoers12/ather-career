<?php

declare(strict_types=1);

require_once __DIR__ . '/project_evidence_repository.php';
require_once __DIR__ . '/transaction.php';
require_once __DIR__ . '/evidence_hub_undo_lifecycle.php';

const EVIDENCE_HUB_UNDO_FEEDBACK_KEY = 'evidence_hub_undo_feedback';
const EVIDENCE_HUB_UNDO_LIFETIME_SECONDS = 600;

/** @param array<string,mixed> $values */
function evidenceHubSavedFieldFingerprint(array $values): string
{
    $ordered = [];
    foreach (projectEvidenceLogicalToStorageFields() as $logical => $_column) {
        if (!array_key_exists($logical, $values) || ($values[$logical] !== null && !is_string($values[$logical]))) {
            throw new InvalidArgumentException('Evidence fingerprint fields are invalid.');
        }
        $ordered[$logical] = $values[$logical];
    }
    return hash('sha256', json_encode($ordered, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
}

/**
 * The caller owns the transaction. The previous values are captured under the
 * same row lock used by the save and are returned only to the server session.
 * @param array{problem:string,personal_role:string,measurable_outcome:string} $submitted
 * @return array{changed:bool,previous?:array<string,mixed>,saved_fingerprint?:string,updated_at?:string}|null
 */
function saveAuthorizedProjectEvidenceWithUndo(PDO $database, AuthorizedPortfolioContext $context, int $projectId, array $submitted): ?array
{
    $before = findAuthorizedProjectEvidenceForEdit($database, $context, $projectId, true);
    if ($before === null || !saveAuthorizedProjectEvidence($database, $context, $projectId, $submitted)) {
        return null;
    }
    $after = findAuthorizedProjectEvidenceForEdit($database, $context, $projectId, true);
    if ($after === null) {
        throw new RuntimeException('Saved Evidence could not be verified.');
    }
    if ($after['values'] === $before['values']) {
        return ['changed' => false];
    }
    if (!is_string($after['updated_at']) || $after['updated_at'] === '') {
        throw new RuntimeException('Saved Evidence timestamp is unavailable.');
    }
    return [
        'changed' => true,
        'previous' => $before['values'],
        'saved_fingerprint' => evidenceHubSavedFieldFingerprint($after['values']),
        'updated_at' => $after['updated_at'],
    ];
}

/** @param array{changed:bool,previous?:array<string,mixed>,saved_fingerprint?:string,updated_at?:string} $save */
function rememberEvidenceHubUndo(AuthorizedPortfolioContext $context, int $projectId, array $save, int $now): void
{
    unset($_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY]);
    if (!$save['changed']) {
        $_SESSION[EVIDENCE_HUB_UNDO_FEEDBACK_KEY] = 'saved_without_undo';
        return;
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY] = [
        'token_hash' => hash('sha256', $token),
        'token' => $token,
        'owner_id' => $context->userId,
        'portfolio_id' => $context->portfolioId,
        'project_id' => $projectId,
        'previous' => $save['previous'],
        'saved_fingerprint' => $save['saved_fingerprint'],
        'updated_at' => $save['updated_at'],
        'created_at' => $now,
        'display_pending' => true,
    ];
    unset($_SESSION[EVIDENCE_HUB_UNDO_FEEDBACK_KEY]);
}

/** @return array{kind:string,token?:string} */
function takeImmediateEvidenceHubUndoFeedback(AuthorizedPortfolioContext $context, int $now): array
{
    $flash = $_SESSION[EVIDENCE_HUB_UNDO_FEEDBACK_KEY] ?? null;
    unset($_SESSION[EVIDENCE_HUB_UNDO_FEEDBACK_KEY]);
    if (is_string($flash) && in_array($flash, ['undone', 'conflict', 'unavailable', 'saved_without_undo'], true)) {
        return ['kind' => $flash];
    }
    $record = $_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY] ?? null;
    if (!evidenceHubUndoRecordIsValid($record, $context, $now) || $record['display_pending'] !== true) {
        unset($_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY]);
        return ['kind' => 'none'];
    }
    $_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY]['display_pending'] = false;
    return ['kind' => 'saved', 'token' => $record['token']];
}

function evidenceHubUndoRecordIsValid(mixed $record, AuthorizedPortfolioContext $context, int $now): bool
{
    if (!is_array($record) || !is_string($record['token'] ?? null)
        || preg_match('/\A[a-f0-9]{64}\z/D', $record['token']) !== 1
        || !is_string($record['token_hash'] ?? null)
        || !hash_equals($record['token_hash'], hash('sha256', $record['token']))
        || ($record['owner_id'] ?? null) !== $context->userId
        || ($record['portfolio_id'] ?? null) !== $context->portfolioId
        || !is_int($record['project_id'] ?? null) || $record['project_id'] < 1
        || !is_int($record['created_at'] ?? null)
        || $record['created_at'] > $now || $now - $record['created_at'] > EVIDENCE_HUB_UNDO_LIFETIME_SECONDS
        || !is_bool($record['display_pending'] ?? null)
        || !is_array($record['previous'] ?? null)
        || !is_string($record['saved_fingerprint'] ?? null)
        || preg_match('/\A[a-f0-9]{64}\z/D', $record['saved_fingerprint']) !== 1
        || !is_string($record['updated_at'] ?? null) || $record['updated_at'] === '') {
        return false;
    }
    try {
        evidenceHubSavedFieldFingerprint($record['previous']);
        return true;
    } catch (InvalidArgumentException) {
        return false;
    }
}

/** Return `undone`, `conflict`, or the generic `unavailable`. */
function executeImmediateEvidenceHubUndo(PDO $database, AuthorizedPortfolioContext $context, mixed $token, int $now): string
{
    $record = $_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY] ?? null;
    if (!is_string($token) || preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1
        || !evidenceHubUndoRecordIsValid($record, $context, $now)
        || $record['display_pending'] !== false
        || !hash_equals($record['token_hash'], hash('sha256', $token))) {
        return 'unavailable';
    }
    $result = runDatabaseTransaction($database, static function () use ($database, $context, $record): string {
        $current = findAuthorizedProjectEvidenceForEdit($database, $context, $record['project_id'], true);
        if ($current === null) {
            return 'unavailable';
        }
        if ($current['updated_at'] !== $record['updated_at']
            || !hash_equals($record['saved_fingerprint'], evidenceHubSavedFieldFingerprint($current['values']))) {
            return 'conflict';
        }
        $timestamp = $database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "strftime('%Y-%m-%dT%H:%M:%fZ', 'now')" : 'CURRENT_TIMESTAMP(6)';
        $statement = $database->prepare(
            'UPDATE projects SET problem_statement = :problem, personal_role = :personal_role,
                 measurable_outcome = :measurable_outcome, updated_at = ' . $timestamp . '
             WHERE id = :project_id AND portfolio_id = :portfolio_id'
        );
        $statement->execute([
            'problem' => $record['previous']['problem'],
            'personal_role' => $record['previous']['personal_role'],
            'measurable_outcome' => $record['previous']['measurable_outcome'],
            'project_id' => $record['project_id'],
            'portfolio_id' => $context->portfolioId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Evidence restoration failed.');
        }
        return 'undone';
    });
    unset($_SESSION[EVIDENCE_HUB_UNDO_SESSION_KEY]);
    return $result;
}
