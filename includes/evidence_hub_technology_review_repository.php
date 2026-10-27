<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';

/*
 * Tenant-scoped storage for Owner technology mappings and proposals. Every
 * statement joins portfolios on the authorized Owner, so a foreign Portfolio
 * can neither be read nor written. Callers validate values first
 * (evidence_hub_technology_review.php) and run writes in a transaction.
 */

final class EvidenceHubTechnologyReviewStorageException extends RuntimeException
{
}

/** @return array<string, string> normalized label digest => canonical technology ID */
function loadAuthorizedTechnologyOwnerMappings(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT technology_owner_mappings.label_digest, technology_owner_mappings.canonical_id
         FROM technology_owner_mappings
         JOIN portfolios ON portfolios.id = technology_owner_mappings.portfolio_id
         WHERE technology_owner_mappings.portfolio_id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         ORDER BY technology_owner_mappings.label_digest ASC'
    );
    $statement->execute(evidenceHubTechnologyReviewScope($context));
    $mappings = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $digest = $record['label_digest'] ?? null;
        $canonicalId = $record['canonical_id'] ?? null;
        if (!evidenceHubTechnologyReviewDigestIsValid($digest) || !is_string($canonicalId) || preg_match('/^tech\.[a-z0-9-]+$/D', $canonicalId) !== 1) {
            throw new EvidenceHubTechnologyReviewStorageException('Stored technology mapping is invalid.');
        }
        $mappings[$digest] = $canonicalId;
    }

    return $mappings;
}

/** Replaces the Owner's mapping for one label. */
function storeAuthorizedTechnologyOwnerMapping(PDO $database, AuthorizedPortfolioContext $context, string $digest, string $canonicalId, string $taxonomyVersion): void
{
    if (!evidenceHubTechnologyReviewDigestIsValid($digest) || preg_match('/^tech\.[a-z0-9-]+$/D', $canonicalId) !== 1 || preg_match('/^v[1-9][0-9]*$/D', $taxonomyVersion) !== 1) {
        throw new EvidenceHubTechnologyReviewStorageException('Technology mapping is invalid.');
    }
    // An unchanged upsert affects no rows on MySQL, so authorize explicitly first.
    evidenceHubAssertAuthorizedTechnologyReviewPortfolio($database, $context);
    $conflict = match ($database->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        'mysql' => 'ON DUPLICATE KEY UPDATE canonical_id = VALUES(canonical_id), taxonomy_version = VALUES(taxonomy_version)',
        'sqlite' => 'ON CONFLICT(portfolio_id, label_digest) DO UPDATE SET canonical_id = excluded.canonical_id, taxonomy_version = excluded.taxonomy_version',
        default => throw new EvidenceHubTechnologyReviewStorageException('Technology mapping storage driver is unsupported.'),
    };
    $statement = $database->prepare(
        'INSERT INTO technology_owner_mappings (portfolio_id, label_digest, canonical_id, taxonomy_version)
         SELECT portfolios.id, :label_digest, :canonical_id, :taxonomy_version
         FROM portfolios
         WHERE portfolios.id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         ' . $conflict
    );
    $statement->execute(evidenceHubTechnologyReviewScope($context) + [
        'label_digest' => $digest,
        'canonical_id' => $canonicalId,
        'taxonomy_version' => $taxonomyVersion,
    ]);
}

function evidenceHubAssertAuthorizedTechnologyReviewPortfolio(PDO $database, AuthorizedPortfolioContext $context): void
{
    $statement = $database->prepare(
        'SELECT portfolios.id FROM portfolios
         WHERE portfolios.id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         LIMIT 1'
    );
    $statement->execute(evidenceHubTechnologyReviewScope($context));
    if ($statement->fetchColumn() === false) {
        throw new AuthorizationDeniedException('Portfolio authorization failed.');
    }
}

/** @return bool whether a mapping existed */
function deleteAuthorizedTechnologyOwnerMapping(PDO $database, AuthorizedPortfolioContext $context, string $digest): bool
{
    if (!evidenceHubTechnologyReviewDigestIsValid($digest)) {
        return false;
    }
    $statement = $database->prepare(
        'DELETE FROM technology_owner_mappings
         WHERE technology_owner_mappings.portfolio_id = :authorized_portfolio_id
           AND technology_owner_mappings.label_digest = :label_digest
           AND EXISTS (
               SELECT 1 FROM portfolios
               WHERE portfolios.id = :authorized_portfolio_id_check
                 AND portfolios.owner_user_id = :authorized_user_id
           )'
    );
    $statement->execute(evidenceHubTechnologyReviewScope($context) + [
        'authorized_portfolio_id_check' => $context->portfolioId,
        'label_digest' => $digest,
    ]);

    return $statement->rowCount() === 1;
}

/** @return list<array{id: int, label_digest: string, proposed_label: string, category: string, note: ?string, state: string}> newest first */
function listAuthorizedTechnologyProposals(PDO $database, AuthorizedPortfolioContext $context): array
{
    $statement = $database->prepare(
        'SELECT technology_proposals.id, technology_proposals.label_digest, technology_proposals.proposed_label,
                technology_proposals.category, technology_proposals.note, technology_proposals.state
         FROM technology_proposals
         JOIN portfolios ON portfolios.id = technology_proposals.portfolio_id
         WHERE technology_proposals.portfolio_id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
         ORDER BY technology_proposals.id DESC'
    );
    $statement->execute(evidenceHubTechnologyReviewScope($context));
    $proposals = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $id = authorizationPositiveInteger($record['id'] ?? null);
        if ($id === null || !evidenceHubTechnologyReviewDigestIsValid($record['label_digest'] ?? null)
            || !is_string($record['proposed_label'] ?? null) || !is_string($record['category'] ?? null)
            || !in_array($record['state'] ?? null, ['pending', 'withdrawn', 'accepted', 'rejected'], true)
            || !(is_string($record['note'] ?? null) || ($record['note'] ?? null) === null)) {
            throw new EvidenceHubTechnologyReviewStorageException('Stored technology proposal is invalid.');
        }
        $proposals[] = [
            'id' => $id,
            'label_digest' => $record['label_digest'],
            'proposed_label' => $record['proposed_label'],
            'category' => $record['category'],
            'note' => $record['note'],
            'state' => $record['state'],
        ];
    }

    return $proposals;
}

/** @return bool false when a proposal for this label is already pending */
function createAuthorizedTechnologyProposal(PDO $database, AuthorizedPortfolioContext $context, string $digest, string $proposedLabel, string $category, ?string $note): bool
{
    if (!evidenceHubTechnologyReviewDigestIsValid($digest)) {
        throw new EvidenceHubTechnologyReviewStorageException('Technology proposal is invalid.');
    }
    $pending = $database->prepare(
        "SELECT 1 FROM technology_proposals
         JOIN portfolios ON portfolios.id = technology_proposals.portfolio_id
         WHERE technology_proposals.portfolio_id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id
           AND technology_proposals.label_digest = :label_digest
           AND technology_proposals.state = 'pending'
         LIMIT 1"
    );
    $pending->execute(evidenceHubTechnologyReviewScope($context) + ['label_digest' => $digest]);
    if ($pending->fetchColumn() !== false) {
        return false;
    }
    $statement = $database->prepare(
        "INSERT INTO technology_proposals (portfolio_id, label_digest, proposed_label, category, note, state)
         SELECT portfolios.id, :label_digest, :proposed_label, :category, :note, 'pending'
         FROM portfolios
         WHERE portfolios.id = :authorized_portfolio_id
           AND portfolios.owner_user_id = :authorized_user_id"
    );
    try {
        $statement->execute(evidenceHubTechnologyReviewScope($context) + [
            'label_digest' => $digest,
            'proposed_label' => $proposedLabel,
            'category' => $category,
            'note' => $note,
        ]);
    } catch (PDOException $exception) {
        // The pending-uniqueness constraint settles a concurrent duplicate.
        if (($exception->errorInfo[0] ?? null) === '23000') {
            return false;
        }
        throw $exception;
    }
    if ($statement->rowCount() !== 1) {
        throw new AuthorizationDeniedException('Portfolio authorization failed.');
    }

    return true;
}

/** @return bool whether a pending proposal of this Portfolio was withdrawn */
function withdrawAuthorizedTechnologyProposal(PDO $database, AuthorizedPortfolioContext $context, int $proposalId): bool
{
    $statement = $database->prepare(
        "UPDATE technology_proposals
         SET state = 'withdrawn', state_changed_at = CURRENT_TIMESTAMP
         WHERE technology_proposals.id = :proposal_id
           AND technology_proposals.portfolio_id = :authorized_portfolio_id
           AND technology_proposals.state = 'pending'
           AND EXISTS (
               SELECT 1 FROM portfolios
               WHERE portfolios.id = :authorized_portfolio_id_check
                 AND portfolios.owner_user_id = :authorized_user_id
           )"
    );
    $statement->execute(evidenceHubTechnologyReviewScope($context) + [
        'authorized_portfolio_id_check' => $context->portfolioId,
        'proposal_id' => $proposalId,
    ]);

    return $statement->rowCount() === 1;
}

/** @return array{authorized_portfolio_id: int, authorized_user_id: int} */
function evidenceHubTechnologyReviewScope(AuthorizedPortfolioContext $context): array
{
    return ['authorized_portfolio_id' => $context->portfolioId, 'authorized_user_id' => $context->userId];
}

function evidenceHubTechnologyReviewDigestIsValid(mixed $digest): bool
{
    return is_string($digest) && preg_match('/^[a-f0-9]{64}$/D', $digest) === 1;
}
