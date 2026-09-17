<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/evidence_hub_recommendations.php';

final class EvidenceHubRecommendationDispositionException extends RuntimeException
{
}

/** @return list<array{recommendation_key: string, rule_version: string, evidence_fingerprint: string, disposition: string, snoozed_until: int|null}> */
function listAuthorizedEvidenceHubRecommendationDispositions(PDO $database, AuthorizedPortfolioContext $context): array
{
    evidenceHubAssertAuthorizedRecommendationDispositionPortfolio($database, $context);
    evidenceHubRecommendationDispositionUseUtcSession($database);
    $snoozedUntil = evidenceHubRecommendationDispositionEpochExpression($database);
    $statement = $database->prepare(
        'SELECT recommendation_dispositions.recommendation_key,
                recommendation_dispositions.rule_version,
                recommendation_dispositions.evidence_fingerprint,
                recommendation_dispositions.disposition,
                ' . $snoozedUntil . ' AS snoozed_until
         FROM recommendation_dispositions
         JOIN portfolios ON portfolios.id = recommendation_dispositions.portfolio_id
         WHERE recommendation_dispositions.portfolio_id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         ORDER BY recommendation_dispositions.recommendation_key ASC'
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'authorized_user_id' => $context->userId,
    ]);

    $dispositions = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $dispositions[] = evidenceHubRecommendationDispositionRecord($record);
    }

    return $dispositions;
}

/**
 * Persists a replacement current-state row for a current R2 output selected by
 * trusted Owner-core code. This boundary does not receive fingerprints or
 * opaque references separately from that server-derived recommendation.
 *
 * @param array<string, mixed> $currentRecommendation
 */
function storeAuthorizedEvidenceHubRecommendationDisposition(
    PDO $database,
    AuthorizedPortfolioContext $context,
    array $currentRecommendation,
    string $disposition,
    int $injectedUtcEpochSeconds,
): void {
    $current = evidenceHubValidateCurrentRecommendationForDisposition($currentRecommendation);
    if (!in_array($disposition, ['snoozed', 'dismissed'], true)) {
        throw new EvidenceHubRecommendationDispositionException('Recommendation disposition is invalid.');
    }
    if ($injectedUtcEpochSeconds < 0) {
        throw new EvidenceHubRecommendationDispositionException('Injected UTC time is invalid.');
    }

    evidenceHubAssertAuthorizedRecommendationDispositionPortfolio($database, $context);
    evidenceHubRecommendationDispositionUseUtcSession($database);
    $snoozedUntil = $disposition === 'snoozed'
        ? evidenceHubRecommendationSnoozeUntil($injectedUtcEpochSeconds)
        : null;
    $statement = $database->prepare(
        evidenceHubRecommendationDispositionWriteSql($database)
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'authorized_portfolio_id_insert' => $context->portfolioId,
        'authorized_user_id' => $context->userId,
        'recommendation_key' => $current['recommendation_key'],
        'rule_version' => $current['rule_version'],
        'evidence_fingerprint' => $current['evidence_fingerprint'],
        'disposition' => $disposition,
        'snoozed_until' => $snoozedUntil === null ? null : evidenceHubRecommendationDispositionUtcTimestamp($snoozedUntil),
    ]);
}

function evidenceHubAssertAuthorizedRecommendationDispositionPortfolio(PDO $database, AuthorizedPortfolioContext $context): void
{
    $statement = $database->prepare(
        'SELECT portfolios.id
         FROM portfolios
         WHERE portfolios.id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         LIMIT 1'
    );
    $statement->execute([
        'authorized_portfolio_id' => $context->portfolioId,
        'authorized_user_id' => $context->userId,
    ]);
    if ($statement->fetchColumn() === false) {
        throw new AuthorizationDeniedException('Portfolio authorization failed.');
    }
}

function evidenceHubRecommendationDispositionUseUtcSession(PDO $database): void
{
    if ($database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $database->exec("SET time_zone = '+00:00'");
    }
}

function evidenceHubRecommendationDispositionEpochExpression(PDO $database): string
{
    return match ($database->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        'mysql' => 'UNIX_TIMESTAMP(recommendation_dispositions.snoozed_until)',
        'sqlite' => "CASE WHEN recommendation_dispositions.snoozed_until IS NULL THEN NULL ELSE CAST(strftime('%s', recommendation_dispositions.snoozed_until) AS INTEGER) END",
        default => throw new EvidenceHubRecommendationDispositionException('Recommendation disposition storage driver is unsupported.'),
    };
}

function evidenceHubRecommendationDispositionWriteSql(PDO $database): string
{
    return match ($database->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        'mysql' => 'INSERT INTO recommendation_dispositions (
                        portfolio_id, recommendation_key, rule_version, evidence_fingerprint, disposition, snoozed_until
                    ) SELECT
                        :authorized_portfolio_id_insert, :recommendation_key, :rule_version, :evidence_fingerprint, :disposition, :snoozed_until
                    FROM portfolios
                    WHERE portfolios.id = :authorized_portfolio_id
                      AND portfolios.owner_user_id = :authorized_user_id
                    ON DUPLICATE KEY UPDATE
                        rule_version = VALUES(rule_version),
                        evidence_fingerprint = VALUES(evidence_fingerprint),
                        disposition = VALUES(disposition),
                        snoozed_until = VALUES(snoozed_until)',
        'sqlite' => 'INSERT INTO recommendation_dispositions (
                         portfolio_id, recommendation_key, rule_version, evidence_fingerprint, disposition, snoozed_until
                     ) SELECT
                         :authorized_portfolio_id_insert, :recommendation_key, :rule_version, :evidence_fingerprint, :disposition, :snoozed_until
                     FROM portfolios
                     WHERE portfolios.id = :authorized_portfolio_id
                       AND portfolios.owner_user_id = :authorized_user_id
                     ON CONFLICT(portfolio_id, recommendation_key) DO UPDATE SET
                         rule_version = excluded.rule_version,
                         evidence_fingerprint = excluded.evidence_fingerprint,
                         disposition = excluded.disposition,
                         snoozed_until = excluded.snoozed_until',
        default => throw new EvidenceHubRecommendationDispositionException('Recommendation disposition storage driver is unsupported.'),
    };
}

/** @param array<string, mixed> $recommendation
 * @return array{recommendation_key: string, rule_version: string, evidence_fingerprint: string}
 */
function evidenceHubValidateCurrentRecommendationForDisposition(array $recommendation): array
{
    $required = [
        'rule_id', 'rule_version', 'recommendation_key', 'evidence_fingerprint',
        'lifecycle_state', 'priority_rank', 'display_order', 'reason_codes', 'target', 'snoozed_until',
    ];
    if (array_is_list($recommendation) || array_diff(array_keys($recommendation), $required) !== [] || array_diff($required, array_keys($recommendation)) !== []) {
        throw new EvidenceHubRecommendationDispositionException('Current recommendation shape is invalid.');
    }
    $ruleId = $recommendation['rule_id'];
    if (!is_string($ruleId) || !in_array($ruleId, [
        'add_first_project', 'complete_project_evidence', 'review_unmapped_technology', 'complete_portfolio_publication',
    ], true)) {
        throw new EvidenceHubRecommendationDispositionException('Current recommendation rule is invalid.');
    }
    if ($recommendation['rule_version'] !== EVIDENCE_HUB_RECOMMENDATION_RULE_VERSION
        || $recommendation['lifecycle_state'] !== 'active'
        || $recommendation['snoozed_until'] !== null
        || !is_int($recommendation['priority_rank'])
        || $recommendation['priority_rank'] !== evidenceHubRecommendationPriority($ruleId)
        || !is_int($recommendation['display_order'])
        || $recommendation['display_order'] < 0
        || !is_array($recommendation['reason_codes'])
        || !array_is_list($recommendation['reason_codes'])
        || !is_string($recommendation['recommendation_key'])
        || !is_string($recommendation['evidence_fingerprint'])
        || preg_match('/^[a-f0-9]{64}$/D', $recommendation['recommendation_key']) !== 1
        || preg_match('/^[a-f0-9]{64}$/D', $recommendation['evidence_fingerprint']) !== 1
    ) {
        throw new EvidenceHubRecommendationDispositionException('Current recommendation is invalid.');
    }
    return [
        'recommendation_key' => $recommendation['recommendation_key'],
        'rule_version' => $recommendation['rule_version'],
        'evidence_fingerprint' => $recommendation['evidence_fingerprint'],
    ];
}

/** @param array<string, mixed> $record
 * @return array{recommendation_key: string, rule_version: string, evidence_fingerprint: string, disposition: string, snoozed_until: int|null}
 */
function evidenceHubRecommendationDispositionRecord(array $record): array
{
    $key = $record['recommendation_key'] ?? null;
    $version = $record['rule_version'] ?? null;
    $fingerprint = $record['evidence_fingerprint'] ?? null;
    $disposition = $record['disposition'] ?? null;
    $until = $record['snoozed_until'] ?? null;
    if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1
        || !is_string($version) || preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1
        || !is_string($fingerprint) || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1
        || !is_string($disposition) || !in_array($disposition, ['snoozed', 'dismissed'], true)
    ) {
        throw new EvidenceHubRecommendationDispositionException('Stored recommendation disposition is invalid.');
    }
    if ($disposition === 'dismissed' && $until !== null) {
        throw new EvidenceHubRecommendationDispositionException('Stored dismissed recommendation is invalid.');
    }
    if ($disposition === 'snoozed') {
        if (is_string($until) && preg_match('/^(?:0|[1-9][0-9]*)$/D', $until) === 1) {
            $until = (int) $until;
        }
        if (!is_int($until) || $until < 0) {
            throw new EvidenceHubRecommendationDispositionException('Stored snooze instant is invalid.');
        }
    }

    return [
        'recommendation_key' => $key,
        'rule_version' => $version,
        'evidence_fingerprint' => $fingerprint,
        'disposition' => $disposition,
        'snoozed_until' => $until,
    ];
}

function evidenceHubRecommendationDispositionUtcTimestamp(int $epochSeconds): string
{
    return (new DateTimeImmutable('@' . $epochSeconds))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
