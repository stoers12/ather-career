<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/evidence_hub_owner_recommendations.php';

const EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_SESSION_KEY = 'evidence_hub_recommendation_action_tokens';
const EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_SEQUENCE_SESSION_KEY = 'evidence_hub_recommendation_action_token_sequence';
const EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_LIMIT = 24;
const EVIDENCE_HUB_RECOMMENDATION_ACTION_FEEDBACK_SESSION_KEY = 'evidence_hub_recommendation_action_feedback';

final class EvidenceHubRecommendationActionException extends RuntimeException
{
}

/** @param callable(int):string|null $tokenSource */
function issueEvidenceHubRecommendationActionToken(AuthorizedPortfolioContext $context, array $candidate, string $action, ?callable $tokenSource = null): string
{
    $binding = evidenceHubRecommendationActionCandidateBinding($candidate);
    evidenceHubRecommendationActionRequire($action);
    $bytes = ($tokenSource ?? 'random_bytes')(32);
    if (!is_string($bytes) || strlen($bytes) !== 32) {
        throw new EvidenceHubRecommendationActionException('Action token source is invalid.');
    }
    $token = evidenceHubRecommendationActionTokenEncode($bytes);
    $hash = hash('sha256', $token);
    $records = evidenceHubRecommendationActionTokenRecords();
    $sequence = ($_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_SEQUENCE_SESSION_KEY] ?? 0);
    if (!is_int($sequence) || $sequence < 0 || $sequence === PHP_INT_MAX) {
        $sequence = 0;
    }
    $sequence++;
    while (count($records) >= EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_LIMIT) {
        uasort($records, static fn (array $left, array $right): int => ($left['sequence'] ?? 0) <=> ($right['sequence'] ?? 0));
        unset($records[array_key_first($records)]);
    }
    $records[$hash] = [
        'user_id' => $context->userId,
        'portfolio_id' => $context->portfolioId,
        'action' => $action,
        'recommendation_key' => $binding['recommendation_key'],
        'rule_version' => $binding['rule_version'],
        'evidence_fingerprint' => $binding['evidence_fingerprint'],
        'sequence' => $sequence,
    ];
    $_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_SESSION_KEY] = $records;
    $_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_SEQUENCE_SESSION_KEY] = $sequence;

    return $token;
}

function evidenceHubRecommendationActionTokenEncode(string $bytes): string
{
    if (strlen($bytes) !== 32) {
        throw new EvidenceHubRecommendationActionException('Action token bytes are invalid.');
    }
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/** @return array{user_id:int, portfolio_id:int, action:string, recommendation_key:string, rule_version:string, evidence_fingerprint:string} | null */
function consumeEvidenceHubRecommendationActionToken(AuthorizedPortfolioContext $context, string $action, mixed $token): ?array
{
    evidenceHubRecommendationActionRequire($action);
    if (!is_string($token) || preg_match('/^[A-Za-z0-9_-]{43}$/D', $token) !== 1) {
        return null;
    }
    $records = evidenceHubRecommendationActionTokenRecords();
    $hash = hash('sha256', $token);
    $record = $records[$hash] ?? null;
    unset($records[$hash]);
    $_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_SESSION_KEY] = $records;
    if (!is_array($record) || !evidenceHubRecommendationActionTokenRecordIsValid($record)
        || $record['user_id'] !== $context->userId
        || $record['portfolio_id'] !== $context->portfolioId
        || !hash_equals($record['action'], $action)) {
        return null;
    }
    unset($record['sequence']);
    return $record;
}

/** @return array{recommendation_key:string, rule_version:string, evidence_fingerprint:string} */
function evidenceHubRecommendationActionCandidateBinding(array $candidate): array
{
    $key = $candidate['recommendation_key'] ?? null;
    $version = $candidate['rule_version'] ?? null;
    $fingerprint = $candidate['evidence_fingerprint'] ?? null;
    if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1
        || !is_string($version) || preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1
        || !is_string($fingerprint) || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
        throw new EvidenceHubRecommendationActionException('Current recommendation binding is invalid.');
    }
    return ['recommendation_key' => $key, 'rule_version' => $version, 'evidence_fingerprint' => $fingerprint];
}

/** @return array<string, array<string, mixed>> */
function evidenceHubRecommendationActionTokenRecords(): array
{
    $records = $_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_SESSION_KEY] ?? [];
    return is_array($records) && !array_is_list($records) ? $records : [];
}

/** @param array<string,mixed> $record */
function evidenceHubRecommendationActionTokenRecordIsValid(array $record): bool
{
    $expected = ['user_id', 'portfolio_id', 'action', 'recommendation_key', 'rule_version', 'evidence_fingerprint', 'sequence'];
    if (array_diff(array_keys($record), $expected) !== [] || array_diff($expected, array_keys($record)) !== []) {
        return false;
    }
    try {
        evidenceHubRecommendationActionRequire($record['action']);
        return is_int($record['user_id']) && $record['user_id'] > 0
            && is_int($record['portfolio_id']) && $record['portfolio_id'] > 0
            && is_int($record['sequence']) && $record['sequence'] > 0
            && evidenceHubRecommendationActionCandidateBinding($record) !== [];
    } catch (EvidenceHubRecommendationActionException) {
        return false;
    }
}

function evidenceHubRecommendationActionRequire(mixed $action): void
{
    if (!is_string($action) || !in_array($action, ['snooze', 'dismiss'], true)) {
        throw new EvidenceHubRecommendationActionException('Recommendation action is invalid.');
    }
}

/** @param array{user_id:int, portfolio_id:int, action:string, recommendation_key:string, rule_version:string, evidence_fingerprint:string} $tokenRecord */
function executeAuthorizedEvidenceHubRecommendationAction(PDO $database, AuthorizedPortfolioContext $context, array $tokenRecord, int $utcNow): bool
{
    $state = buildConfiguredAuthorizedEvidenceHubOwnerRecommendationState($database, $context, $utcNow);
    $resolved = resolveAuthorizedEvidenceHubOwnerRecommendation($state, $tokenRecord['recommendation_key']);
    if (!is_array($resolved)) {
        return false;
    }
    $candidate = $resolved['candidate'];
    $binding = evidenceHubRecommendationActionCandidateBinding($candidate);
    if (!hash_equals($tokenRecord['recommendation_key'], $binding['recommendation_key'])
        || !hash_equals($tokenRecord['rule_version'], $binding['rule_version'])
        || !hash_equals($tokenRecord['evidence_fingerprint'], $binding['evidence_fingerprint'])) {
        return false;
    }
    $active = filterEvidenceHubRecommendationCandidates([$candidate], listAuthorizedEvidenceHubRecommendationDispositions($database, $context), $utcNow);
    if ($active === []) {
        return false;
    }
    storeAuthorizedEvidenceHubRecommendationDisposition($database, $context, $candidate, $tokenRecord['action'] === 'snooze' ? 'snoozed' : 'dismissed', $utcNow);
    return true;
}

function setEvidenceHubRecommendationActionFeedback(string $message): void
{
    $_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_FEEDBACK_SESSION_KEY] = $message;
}

function takeEvidenceHubRecommendationActionFeedback(): string
{
    $message = $_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_FEEDBACK_SESSION_KEY] ?? '';
    unset($_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_FEEDBACK_SESSION_KEY]);
    return is_string($message) && in_array($message, ['Recommendation snoozed for 14 days.', 'Recommendation dismissed.'], true) ? $message : '';
}
