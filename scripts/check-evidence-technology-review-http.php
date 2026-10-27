<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/^ather_career_test_[a-f0-9]{24}$/D', (string) getenv('DB_NAME')) !== 1
    || getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP') !== '1') {
    throw new RuntimeException('Disposable Evidence technology review HTTP test authorization is missing.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/evidence_hub_taxonomy.php';
require_once __DIR__ . '/../includes/evidence_hub_technology_mapper.php';
require_once __DIR__ . '/../includes/evidence_hub_owner_core.php';
require_once __DIR__ . '/evidence-hub-http-client.php';

/*
 * Owner technology review over real Apache, sessions and MySQL 8: mapping,
 * unmapping, proposals and withdrawal, with CSRF, tenant and shape denials,
 * and the migration 023/024 constraints. The seeded Owner A projects share
 * one unknown label; Owner B has its own. The check restores Owner A to an
 * unmapped label with only withdrawn proposals.
 */

$cookieA = getenv('EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_A');
$cookieB = getenv('EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_B');
requireHttp(is_string($cookieA) && $cookieA !== '' && is_string($cookieB) && $cookieB !== '', 'Owner session cookie jars are required.');

$database = getDatabaseConnection();
$portfolio = $database->prepare('SELECT portfolios.id FROM portfolios JOIN users ON users.id = portfolios.owner_user_id WHERE users.oidc_issuer = :issuer AND users.oidc_subject = :subject');
$portfolio->execute(['issuer' => getenv('EXPECTED_OIDC_ISSUER'), 'subject' => getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A')]);
$portfolioA = (int) $portfolio->fetchColumn();
$portfolio->execute(['issuer' => getenv('EXPECTED_OIDC_ISSUER'), 'subject' => getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B')]);
$portfolioB = (int) $portfolio->fetchColumn();
requireHttp($portfolioA > 0 && $portfolioB > 0, 'Disposable owner portfolios are missing.');
$digest = evidenceHubTechnologyLabelDigest((string) normalizeEvidenceHubTechnologyLabel('h1-unmapped-a'));
$rows = static function (string $table, int $portfolioId) use ($database): array {
    $statement = $database->prepare('SELECT * FROM ' . $table . ' WHERE portfolio_id = :portfolio_id ORDER BY 1');
    $statement->execute(['portfolio_id' => $portfolioId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
};
$hub = static fn (string $cookie, string $locale = 'en'): array => httpEvidenceRequest('GET', '/owner/evidence-hub', null, $cookie, [], $locale);
$post = static fn (string $cookie, array $fields): array => httpEvidenceRequest('POST', '/owner/evidence-hub', $fields, $cookie);
$formValue = static function (string $html, string $action, string $field): string {
    $pattern = '/name="action" value="' . preg_quote($action, '/') . '">(?:<input type="hidden" name="[a-z_]+" value="[^"]*">)*?<input type="hidden" name="' . preg_quote($field, '/') . '" value="([^"]+)"/';
    requireHttp(preg_match($pattern, $html, $matches) === 1, "The {$action} form lacks {$field}.");
    return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
};

// The Owner sees their own label, escaped project links, and opaque form references only.
$page = $hub($cookieA);
requireHttp($page['status'] === 200 && str_contains($page['body'], '<bdi dir="auto">h1-unmapped-a</bdi>'), 'Owner A does not see their unknown technology label.');
requireHttp(!str_contains($page['body'], 'h1-unmapped-b') && !str_contains($page['body'], $digest), 'The review disclosed a foreign label or a label digest.');
requireHttp(!str_contains($page['body'], '<script>unsafe</script>') && preg_match('/href="\/owner_projects\.php\?edit=[1-9][0-9]*"/', $page['body']) === 1, 'Fix-in-project links are missing or unescaped.');
requireHttp(str_contains($hub($cookieA, 'ar')['body'], 'اربطها بتقنية معروفة'), 'The Arabic Owner locale does not localize the technology review.');
preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $page['body'], $csrfMatch);
$csrfA = $csrfMatch[1] ?? '';
$reference = $formValue($page['body'], 'map_technology', 'technology_ref');
requireHttp($csrfA !== '' && preg_match('/^[A-Za-z0-9_-]{20,}$/', $reference) === 1, 'The map form lacks a CSRF token or opaque reference.');
preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $hub($cookieB)['body'], $csrfMatchB);
$csrfB = $csrfMatchB[1] ?? '';

$map = ['csrf_token' => $csrfA, 'action' => 'map_technology', 'technology_ref' => $reference, 'canonical_id' => 'tech.react'];
requireHttp($post($cookieA, ['csrf_token' => str_repeat('0', 64)] + $map)['status'] === 403, 'Invalid CSRF was accepted.');
requireHttp($post($cookieB, ['csrf_token' => $csrfB] + $map)['status'] === 409, 'Owner B resolved Owner A\'s technology reference.');
requireHttp($post($cookieA, ['canonical_id' => 'tech.not-in-taxonomy'] + $map)['status'] === 422, 'An unknown mapping target was accepted.');
requireHttp($post($cookieA, $map + ['portfolio_id' => (string) $portfolioB])['status'] === 422, 'An unexpected request field was accepted.');
requireHttp($rows('technology_owner_mappings', $portfolioA) === [] && $rows('technology_owner_mappings', $portfolioB) === [], 'A rejected request stored a mapping.');

$mapped = $post($cookieA, $map);
requireHttp($mapped['status'] === 303 && str_contains($mapped['headers'], 'Location: /owner/evidence-hub#technologies'), 'A valid mapping did not use PRG back to the technologies.');
$stored = $rows('technology_owner_mappings', $portfolioA);
requireHttp(count($stored) === 1 && $stored[0]['label_digest'] === $digest && $stored[0]['canonical_id'] === 'tech.react' && $stored[0]['taxonomy_version'] === evidenceHubContractVersionDefinition(EVIDENCE_HUB_OWNER_CONTRACT_VERSION)['taxonomy_version'], 'The mapping was not stored once as a digest.');
requireHttp($rows('technology_owner_mappings', $portfolioB) === [], 'The mapping crossed into Owner B.');
$after = $hub($cookieA);
requireHttp(str_contains($after['body'], 'Technology mapped.') && str_contains($after['body'], 'Mapped by you') && !str_contains($after['body'], 'value="map_technology"><input type="hidden" name="technology_ref" value="' . $reference . '"'), 'The mapped technology did not move into the Owner-mapped list.');

$unmap = ['csrf_token' => $csrfA, 'action' => 'unmap_technology', 'technology_ref' => $formValue($after['body'], 'unmap_technology', 'technology_ref')];
requireHttp($post($cookieB, ['csrf_token' => $csrfB] + $unmap)['status'] === 409, 'Owner B removed Owner A\'s mapping.');
requireHttp($post($cookieA, $unmap)['status'] === 303 && $rows('technology_owner_mappings', $portfolioA) === [], 'Removing the mapping did not restore the unmapped state.');
requireHttp($post($cookieA, $unmap)['status'] === 409, 'Removing an absent mapping succeeded.');

$propose = ['csrf_token' => $csrfA, 'action' => 'propose_technology', 'technology_ref' => $reference, 'proposed_label' => 'H1 Toolkit', 'category' => 'not-a-category', 'note' => ''];
$invalid = $post($cookieA, $propose);
requireHttp($invalid['status'] === 422 && str_contains($invalid['body'], 'Choose a category.') && str_contains($invalid['body'], 'value="H1 Toolkit"'), 'An invalid proposal did not re-render its field error and value.');
requireHttp($post($cookieA, ['category' => 'library'] + $propose)['status'] === 303, 'A valid proposal failed.');
requireHttp($post($cookieA, ['category' => 'library'] + $propose)['status'] === 422, 'A second pending proposal for one label was accepted over HTTP.');
$proposals = $rows('technology_proposals', $portfolioA);
requireHttp(count($proposals) === 1 && $proposals[0]['state'] === 'pending' && $proposals[0]['proposed_label'] === 'H1 Toolkit' && $proposals[0]['note'] === null && $proposals[0]['pending_label_digest'] === $digest, 'The proposal was not stored as one pending row.');
$withdraw = ['csrf_token' => $csrfA, 'action' => 'withdraw_technology_proposal', 'proposal_id' => (string) $proposals[0]['id']];
requireHttp($post($cookieB, ['csrf_token' => $csrfB] + $withdraw)['status'] === 409, 'Owner B withdrew Owner A\'s proposal.');
requireHttp($post($cookieA, ['proposal_id' => 'one'] + $withdraw)['status'] === 422, 'A malformed proposal ID was accepted.');
requireHttp($post($cookieA, $withdraw)['status'] === 303, 'Withdrawing the proposal failed.');
$withdrawn = $rows('technology_proposals', $portfolioA)[0];
requireHttp($withdrawn['state'] === 'withdrawn' && $withdrawn['state_changed_at'] !== null && $withdrawn['pending_label_digest'] === null, 'The proposal was not withdrawn with its timestamp.');

// The database itself enforces what the application validates.
$rejects = static function (string $sql, array $parameters) use ($database): bool {
    try {
        $database->prepare($sql)->execute($parameters);
    } catch (PDOException) {
        return true;
    }
    return false;
};
$insertMapping = 'INSERT INTO technology_owner_mappings (portfolio_id, label_digest, canonical_id, taxonomy_version) VALUES (:portfolio_id, :label_digest, :canonical_id, :taxonomy_version)';
requireHttp($rejects($insertMapping, ['portfolio_id' => $portfolioA, 'label_digest' => 'not-a-digest', 'canonical_id' => 'tech.react', 'taxonomy_version' => 'v4']), 'MySQL accepted an invalid label digest.');
requireHttp($rejects($insertMapping, ['portfolio_id' => $portfolioA, 'label_digest' => $digest, 'canonical_id' => 'React', 'taxonomy_version' => 'v4']), 'MySQL accepted an invalid canonical ID.');
requireHttp($rejects($insertMapping, ['portfolio_id' => 2147483000, 'label_digest' => $digest, 'canonical_id' => 'tech.react', 'taxonomy_version' => 'v4']), 'MySQL accepted a mapping for a missing Portfolio.');
$insertProposal = "INSERT INTO technology_proposals (portfolio_id, label_digest, proposed_label, category, state) VALUES (:portfolio_id, :label_digest, 'Direct', :category, 'pending')";
requireHttp($rejects($insertProposal, ['portfolio_id' => $portfolioA, 'label_digest' => $digest, 'category' => 'not-a-category']), 'MySQL accepted an invalid proposal category.');
$database->beginTransaction();
try {
    $database->prepare($insertProposal)->execute(['portfolio_id' => $portfolioA, 'label_digest' => $digest, 'category' => 'tool']);
    requireHttp($rejects($insertProposal, ['portfolio_id' => $portfolioA, 'label_digest' => $digest, 'category' => 'tool']), 'MySQL accepted two pending proposals for one label.');
} finally {
    $database->rollBack();
}
requireHttp($rejects("UPDATE technology_proposals SET state = 'rejected' WHERE id = :id", ['id' => $withdrawn['id']]) === false, 'A decided proposal could not record its decision.');
$database->prepare("UPDATE technology_proposals SET state = 'withdrawn' WHERE id = :id")->execute(['id' => $withdrawn['id']]);
requireHttp($rejects("UPDATE technology_proposals SET state = 'pending' WHERE id = :id", ['id' => $withdrawn['id']]), 'MySQL accepted a pending proposal with a decision timestamp.');

echo "PASS Evidence technology review HTTP mapping, proposals, CSRF, tenant denial, and MySQL constraints\n";
