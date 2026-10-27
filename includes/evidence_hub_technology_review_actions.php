<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_owner_core.php';
require_once __DIR__ . '/evidence_hub_project_facts.php';
require_once __DIR__ . '/evidence_hub_technology_review.php';
require_once __DIR__ . '/evidence_hub_technology_review_repository.php';
require_once __DIR__ . '/transaction.php';

/*
 * Owner technology review actions posted to /owner/evidence-hub. The route
 * checks CSRF first; this layer checks the exact request shape, resolves the
 * opaque reference against the Portfolio's current labels, validates and
 * writes. It never trusts a label, digest or Portfolio from the request.
 */

const EVIDENCE_HUB_TECHNOLOGY_REVIEW_ACTIONS = [
    'map_technology' => ['csrf_token', 'action', 'technology_ref', 'canonical_id'],
    'unmap_technology' => ['csrf_token', 'action', 'technology_ref'],
    'propose_technology' => ['csrf_token', 'action', 'technology_ref', 'proposed_label', 'category', 'note'],
    'withdraw_technology_proposal' => ['csrf_token', 'action', 'proposal_id'],
];

function evidenceHubIsTechnologyReviewAction(mixed $action): bool
{
    return is_string($action) && isset(EVIDENCE_HUB_TECHNOLOGY_REVIEW_ACTIONS[$action]);
}

/**
 * 'done' carries a feedback message for PRG; 'rejected' carries proposal
 * field errors to re-render (422); 'invalid' is a malformed request (422);
 * 'unavailable' is a stale or foreign target (409).
 *
 * @param array<mixed> $request
 * @return array{status: 'done', feedback: string}|array{status: 'rejected', form: array{ref: string, values: array<string, mixed>, errors: array<string, string>}}|array{status: 'invalid'}|array{status: 'unavailable'}
 */
function executeEvidenceHubTechnologyReviewAction(PDO $database, AuthorizedPortfolioContext $context, string $opaqueTargetHmacMaterial, array $request): array
{
    $action = $request['action'] ?? null;
    if (!evidenceHubIsTechnologyReviewAction($action) || array_is_list($request)) {
        return ['status' => 'invalid'];
    }
    $expected = EVIDENCE_HUB_TECHNOLOGY_REVIEW_ACTIONS[$action];
    if (array_diff(array_keys($request), $expected) !== [] || array_diff($expected, array_keys($request)) !== []) {
        return ['status' => 'invalid'];
    }

    if ($action === 'withdraw_technology_proposal') {
        $proposalId = is_string($request['proposal_id']) ? authorizationPositiveInteger($request['proposal_id']) : null;
        if ($proposalId === null) {
            return ['status' => 'invalid'];
        }
        $withdrawn = runDatabaseTransaction($database, static fn (): bool => withdrawAuthorizedTechnologyProposal($database, $context, $proposalId));

        return $withdrawn ? ['status' => 'done', 'feedback' => 'Technology proposal withdrawn.'] : ['status' => 'unavailable'];
    }

    $taxonomy = loadEvidenceHubOwnerTaxonomy();
    $item = evidenceHubResolveTechnologyReviewItem(
        evidenceHubTechnologyReviewItemsForContext($database, $context, $taxonomy),
        $opaqueTargetHmacMaterial,
        $context,
        $request['technology_ref'],
    );
    if ($item === null) {
        return ['status' => 'unavailable'];
    }

    return match ($action) {
        'map_technology' => evidenceHubMapTechnologyAction($database, $context, $taxonomy, $item, $request['canonical_id']),
        'unmap_technology' => evidenceHubUnmapTechnologyAction($database, $context, $item),
        'propose_technology' => evidenceHubProposeTechnologyAction($database, $context, $taxonomy, $item, $request),
    };
}

/** @return array<string, array<string, mixed>> */
function evidenceHubTechnologyReviewItemsForContext(PDO $database, AuthorizedPortfolioContext $context, array $taxonomy): array
{
    $projects = array_map(static fn (array $facts): array => [
        'id' => $facts['project_ref'],
        'title' => '',
        'technology_storage' => ['storage_state' => $facts['technology_storage_state'], 'labels' => $facts['technologies']],
    ], loadAuthorizedEvidenceHubProjectFacts($database, $context));

    return evidenceHubTechnologyReviewItems($projects, $taxonomy, loadAuthorizedTechnologyOwnerMappings($database, $context));
}

/** Remapping an Owner-mapped label to another entry is allowed; a taxonomy match never reaches here. */
function evidenceHubMapTechnologyAction(PDO $database, AuthorizedPortfolioContext $context, array $taxonomy, array $item, mixed $canonicalId): array
{
    if (!evidenceHubTechnologyMappingTargetIsValid($taxonomy, $canonicalId)) {
        return ['status' => 'invalid'];
    }
    runDatabaseTransaction($database, static fn () => storeAuthorizedTechnologyOwnerMapping($database, $context, $item['digest'], $canonicalId, $taxonomy['taxonomy_version']));

    return ['status' => 'done', 'feedback' => 'Technology mapped.'];
}

function evidenceHubUnmapTechnologyAction(PDO $database, AuthorizedPortfolioContext $context, array $item): array
{
    if ($item['mapping_state'] !== 'owner_mapped') {
        return ['status' => 'unavailable'];
    }
    $removed = runDatabaseTransaction($database, static fn (): bool => deleteAuthorizedTechnologyOwnerMapping($database, $context, $item['digest']));

    return $removed ? ['status' => 'done', 'feedback' => 'Technology mapping removed.'] : ['status' => 'unavailable'];
}

/** @param array<string, mixed> $request */
function evidenceHubProposeTechnologyAction(PDO $database, AuthorizedPortfolioContext $context, array $taxonomy, array $item, array $request): array
{
    if ($item['mapping_state'] !== 'unmapped') {
        return ['status' => 'unavailable'];
    }
    $validation = evidenceHubValidateTechnologyProposal($request['proposed_label'], $request['category'], $request['note'], $taxonomy);
    $form = ['ref' => $request['technology_ref'], 'values' => $validation['values'], 'errors' => $validation['errors']];
    if ($validation['errors'] !== []) {
        return ['status' => 'rejected', 'form' => $form];
    }
    $values = $validation['values'];
    $created = runDatabaseTransaction($database, static fn (): bool => createAuthorizedTechnologyProposal($database, $context, $item['digest'], $values['proposed_label'], $values['category'], $values['note']));
    if (!$created) {
        $form['errors'] = ['proposed_label' => 'A proposal for this technology is already pending.'];

        return ['status' => 'rejected', 'form' => $form];
    }

    return ['status' => 'done', 'feedback' => 'Technology proposal sent.'];
}
