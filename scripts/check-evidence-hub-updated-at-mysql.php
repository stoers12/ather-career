<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || getenv('EVIDENCE_HUB_UPDATED_AT_MYSQL_TEST') !== '1'
    || preg_match('/^ather_career_test_[a-f0-9]{24}$/D', (string) getenv('DB_NAME')) !== 1) {
    throw new RuntimeException('Disposable Evidence Hub MySQL test authorization is missing.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portfolio_scoped_data.php';
require_once __DIR__ . '/../includes/project_evidence_repository.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';

$database = getDatabaseConnection();
$database->exec("SET time_zone = '+00:00'");
$context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
$rows = $database->query('SELECT id, UNIX_TIMESTAMP(created_at) AS created_epoch, UNIX_TIMESTAMP(updated_at) AS updated_epoch FROM projects WHERE id IN (2,3) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) !== 2 || (int) $rows[0]['created_epoch'] !== 1767225600 || (int) $rows[1]['created_epoch'] !== 1772582400) {
    throw new RuntimeException('Synthetic migration rows are missing.');
}
foreach ($rows as $row) {
    if ((int) $row['created_epoch'] !== (int) $row['updated_epoch']) {
        throw new RuntimeException('Existing project timestamp was not backfilled from created_at.');
    }
}
$column = $database->query("SELECT LOWER(column_type) AS type, is_nullable AS nullable, LOWER(extra) AS extra FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'projects' AND column_name = 'updated_at'")->fetch(PDO::FETCH_ASSOC);
if ($column === false || $column['type'] !== 'timestamp(6)' || $column['nullable'] !== 'NO' || str_contains($column['extra'], 'on update')) {
    throw new RuntimeException('Updated timestamp column has an incompatible or automatic definition.');
}
$before = $database->query('SELECT updated_at FROM projects WHERE id=2')->fetchColumn();
$database->exec("UPDATE projects SET description = CONCAT(description, 'x') WHERE id=2");
if ($database->query('SELECT updated_at FROM projects WHERE id=2')->fetchColumn() !== $before) {
    throw new RuntimeException('Unrelated direct update changed the timestamp automatically.');
}
$project = findAuthorizedProject($database, $context, 2);
if ($project === null) throw new RuntimeException('Synthetic project was not owner-scoped.');
$args = [$database, $context, 2, $project['title'], $project['category'], $project['description'], $project['github_url'], $project['image_path'], $project['technologies']];
if (!updateAuthorizedProject(...$args)) throw new RuntimeException('Unchanged project save failed.');
if ($database->query('SELECT updated_at FROM projects WHERE id=2')->fetchColumn() !== $before) {
    throw new RuntimeException('Unchanged project save advanced the timestamp.');
}
$args[3] .= ' revised';
if (!updateAuthorizedProject(...$args)) throw new RuntimeException('Meaningful project edit failed.');
$afterProjectEdit = $database->query('SELECT updated_at FROM projects WHERE id=2')->fetchColumn();
if ($afterProjectEdit === $before) throw new RuntimeException('Meaningful project edit did not advance the timestamp.');
$args[8] = ['JavaScript'];
if (!updateAuthorizedProject(...$args)) throw new RuntimeException('Technology edit failed.');
$afterTechnology = $database->query('SELECT updated_at FROM projects WHERE id=2')->fetchColumn();
if ($afterTechnology === $afterProjectEdit) throw new RuntimeException('Technology edit did not advance the timestamp.');
$evidence = [
    'problem' => 'Manual weekly reporting affected the team because six hours of reconciliation delayed operational decisions.',
    'personal_role' => 'I designed the reporting pipeline and implemented validation checks across each imported data source.',
    'measurable_outcome' => 'Reduced weekly reporting time from six hours to one hour.',
];
if (!saveAuthorizedProjectEvidence($database, $context, 2, $evidence)) throw new RuntimeException('Evidence save failed.');
$afterEvidence = $database->query('SELECT updated_at FROM projects WHERE id=2')->fetchColumn();
if ($afterEvidence === $afterTechnology) throw new RuntimeException('Evidence content change did not advance the timestamp.');
$evidence['problem'] = '  ' . $evidence['problem'] . '  ';
if (!saveAuthorizedProjectEvidence($database, $context, 2, $evidence)) throw new RuntimeException('Equivalent evidence save failed.');
if ($database->query('SELECT updated_at FROM projects WHERE id=2')->fetchColumn() !== $afterEvidence) {
    throw new RuntimeException('Equivalent normalized evidence advanced the timestamp.');
}
findAuthorizedProjectEvidenceForEdit($database, $context, 2);
if ($database->query('SELECT updated_at FROM projects WHERE id=2')->fetchColumn() !== $afterEvidence) {
    throw new RuntimeException('Read-only evidence access advanced the timestamp.');
}
$database->exec("UPDATE portfolios SET public_slug = 'synthetic-owner' WHERE id=10");
$database->exec("INSERT INTO personal_info (portfolio_id, full_name) VALUES (10, 'Synthetic Owner')");
$beforePublication = $database->query('SELECT updated_at FROM projects WHERE id=3')->fetchColumn();
publishOwnedPortfolio($database, $context);
$afterPublication = $database->query('SELECT updated_at FROM projects WHERE id=3')->fetchColumn();
if ($afterPublication === $beforePublication) throw new RuntimeException('Publication toggle did not advance all project timestamps.');
publishOwnedPortfolio($database, $context);
if ($database->query('SELECT updated_at FROM projects WHERE id=3')->fetchColumn() !== $afterPublication) {
    throw new RuntimeException('Unchanged publication state advanced project timestamps.');
}
unpublishOwnedPortfolio($database, $context);
if ($database->query('SELECT updated_at FROM projects WHERE id=3')->fetchColumn() === $afterPublication) {
    throw new RuntimeException('Unpublication toggle did not advance project timestamps.');
}

echo "PASS Evidence Hub MySQL migration backfill and content-only timestamps\n";
