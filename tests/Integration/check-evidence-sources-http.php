<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/^ather_career_test_[a-f0-9]{24}$/D', (string) getenv('DB_NAME')) !== 1
    || getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP') !== '1') {
    throw new RuntimeException('Disposable Evidence sources HTTP test authorization is missing.');
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/evidence-hub-http-client.php';

/*
 * Evidence sources (B-06) over real Apache, sessions and MySQL 8: add, the
 * 10-source limit, remove, CSRF, tenant and shape denials, the Evidence Hub
 * summary, and the migration 027 constraints and cascade. The check removes
 * every source it created.
 */

$cookieA = getenv('EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_A');
$cookieB = getenv('EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_B');
requireHttp(is_string($cookieA) && $cookieA !== '' && is_string($cookieB) && $cookieB !== '', 'Owner session cookie jars are required.');

$database = getDatabaseConnection();
$owner = $database->prepare('SELECT portfolios.id AS portfolio_id, MIN(projects.id) AS project_id FROM portfolios JOIN users ON users.id = portfolios.owner_user_id JOIN projects ON projects.portfolio_id = portfolios.id WHERE users.oidc_issuer = :issuer AND users.oidc_subject = :subject GROUP BY portfolios.id');
$owner->execute(['issuer' => getenv('EXPECTED_OIDC_ISSUER'), 'subject' => getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A')]);
$a = $owner->fetch(PDO::FETCH_ASSOC);
$owner->execute(['issuer' => getenv('EXPECTED_OIDC_ISSUER'), 'subject' => getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B')]);
$b = $owner->fetch(PDO::FETCH_ASSOC);
requireHttp(is_array($a) && is_array($b), 'Disposable owner projects are missing.');
[$portfolioA, $projectA, $portfolioB, $projectB] = [(int) $a['portfolio_id'], (int) $a['project_id'], (int) $b['portfolio_id'], (int) $b['project_id']];
$route = static fn (int $projectId): string => '/owner/projects/' . $projectId . '/evidence';
$get = static fn (string $cookie, int $projectId, string $locale = 'en'): array => httpEvidenceRequest('GET', $route($projectId), null, $cookie, [], $locale);
$post = static fn (string $cookie, int $projectId, array $fields): array => httpEvidenceRequest('POST', $route($projectId), $fields, $cookie);
$rows = static function (int $portfolioId) use ($database): array {
    $statement = $database->prepare('SELECT id, project_id, title, url, note FROM project_evidence_sources WHERE portfolio_id = :portfolio_id ORDER BY id');
    $statement->execute(['portfolio_id' => $portfolioId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
};
$csrf = static function (array $response): string {
    requireHttp(preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $response['body'], $matches) === 1, 'The evidence editor lacks a CSRF token.');
    return $matches[1];
};
requireHttp($rows($portfolioA) === [] && $rows($portfolioB) === [], 'The disposable owners already have sources.');

try {
    $page = $get($cookieA, $projectA);
    requireHttp($page['status'] === 200 && str_contains($page['body'], 'id="sources"') && str_contains($page['body'], '0 of 10 sources'), 'The evidence editor lacks the sources section.');
    requireHttp(str_contains($get($cookieA, $projectA, 'ar')['body'], 'المصادر'), 'The Arabic Owner locale does not localize the sources section.');
    $csrfA = $csrf($page);
    $csrfB = $csrf($get($cookieB, $projectB));

    $add = ['csrf_token' => $csrfA, 'action' => 'add_source', 'title' => ' Synthetic report ', 'url' => 'https://example.test/report?a=1&b=2', 'note' => ''];
    requireHttp($post($cookieA, $projectA, ['csrf_token' => str_repeat('0', 64)] + $add)['status'] === 403, 'Invalid CSRF was accepted.');
    requireHttp($post($cookieB, $projectA, ['csrf_token' => $csrfB] + $add)['status'] === 404, 'Owner B added a source to Owner A\'s project.');
    requireHttp($post($cookieA, $projectA, $add + ['portfolio_id' => (string) $portfolioB])['status'] === 422, 'An unexpected request field was accepted.');
    requireHttp($post($cookieA, $projectA, ['action' => 'publish_source'] + $add)['status'] === 422, 'An unknown source action was accepted.');
    $invalid = $post($cookieA, $projectA, ['url' => 'javascript:alert(1)'] + $add);
    requireHttp($invalid['status'] === 422 && str_contains($invalid['body'], 'Please enter a valid HTTP or HTTPS URL.') && str_contains($invalid['body'], 'value="Synthetic report"'), 'An unsafe URL did not re-render its field error and value.');
    requireHttp($post($cookieA, $projectA, ['url' => 'http://localhost/admin'] + $add)['status'] === 422, 'A local URL was accepted.');
    requireHttp($rows($portfolioA) === [] && $rows($portfolioB) === [], 'A rejected request stored a source.');

    $added = $post($cookieA, $projectA, $add);
    requireHttp($added['status'] === 303 && str_contains($added['headers'], 'Location: /owner/projects/' . $projectA . '/evidence#sources'), 'A valid source did not use PRG back to the sources.');
    $stored = $rows($portfolioA);
    requireHttp(count($stored) === 1 && (int) $stored[0]['project_id'] === $projectA && $stored[0]['title'] === 'Synthetic report' && $stored[0]['url'] === 'https://example.test/report?a=1&b=2' && $stored[0]['note'] === null, 'The source was not stored once, trimmed, with a NULL note.');
    $after = $get($cookieA, $projectA);
    requireHttp(str_contains($after['body'], 'href="https://example.test/report?a=1&amp;b=2" rel="noopener noreferrer"') && str_contains($after['body'], '1 of 10 sources'), 'The editor does not list the added source.');
    requireHttp(!str_contains($get($cookieB, $projectB)['body'], 'example.test/report'), 'The source crossed into Owner B.');
    $hub = httpEvidenceRequest('GET', '/owner/evidence-hub', null, $cookieA);
    requireHttp($hub['status'] === 200 && str_contains($hub['body'], 'Manage sources') && str_contains($hub['body'], 'href="/owner/projects/' . $projectA . '/evidence#sources"'), 'The Evidence Hub does not summarize the project\'s sources.');

    // The tenth source is the last; the limit holds over HTTP.
    $fill = $database->prepare('INSERT INTO project_evidence_sources (portfolio_id, project_id, title, url) VALUES (:portfolio_id, :project_id, :title, :url)');
    for ($i = 2; $i <= 10; ++$i) {
        $fill->execute(['portfolio_id' => $portfolioA, 'project_id' => $projectA, 'title' => 'Filler ' . $i, 'url' => 'https://example.test/' . $i]);
    }
    $full = $post($cookieA, $projectA, ['title' => 'Eleventh'] + $add);
    requireHttp($full['status'] === 422 && str_contains($full['body'], 'This project already has 10 sources.') && !str_contains($full['body'], 'value="add_source"'), 'An eleventh source was not refused.');
    requireHttp(count($rows($portfolioA)) === 10, 'The limit did not hold.');

    // Removal: only the Owner's own source, through its own project.
    $fill->execute(['portfolio_id' => $portfolioB, 'project_id' => $projectB, 'title' => 'Foreign', 'url' => 'https://example.test/foreign']);
    $foreignId = (int) $rows($portfolioB)[0]['id'];
    $ownId = (int) $stored[0]['id'];
    $remove = ['csrf_token' => $csrfA, 'action' => 'remove_source', 'source_id' => (string) $ownId];
    requireHttp($post($cookieB, $projectA, ['csrf_token' => $csrfB] + $remove)['status'] === 404, 'Owner B removed Owner A\'s source.');
    requireHttp($post($cookieA, $projectA, ['source_id' => (string) $foreignId] + $remove)['status'] === 404, 'Owner A removed Owner B\'s source.');
    requireHttp($post($cookieA, $projectA, ['source_id' => 'one'] + $remove)['status'] === 422, 'A malformed source ID was accepted.');
    requireHttp(count($rows($portfolioB)) === 1 && count($rows($portfolioA)) === 10, 'A denied removal changed rows.');
    $removed = $post($cookieA, $projectA, $remove);
    requireHttp($removed['status'] === 303 && str_contains($removed['headers'], 'Location: /owner/projects/' . $projectA . '/evidence#sources'), 'Removing a source did not use PRG.');
    requireHttp(!in_array($ownId, array_map('intval', array_column($rows($portfolioA), 'id')), true) && count($rows($portfolioA)) === 9, 'The source was not removed exactly once.');
    requireHttp($post($cookieA, $projectA, $remove)['status'] === 404, 'Removing an absent source succeeded.');

    // The database itself enforces what the application validates.
    $rejects = static function (string $sql, array $parameters) use ($database): bool {
        try {
            $database->prepare($sql)->execute($parameters);
        } catch (PDOException) {
            return true;
        }
        return false;
    };
    $insert = 'INSERT INTO project_evidence_sources (portfolio_id, project_id, title, url, note) VALUES (:portfolio_id, :project_id, :title, :url, :note)';
    $valid = ['portfolio_id' => $portfolioA, 'project_id' => $projectA, 'title' => 'Direct', 'url' => 'https://example.test/direct', 'note' => null];
    requireHttp($rejects($insert, ['url' => 'javascript:alert(1)'] + $valid), 'MySQL accepted a non-HTTP URL.');
    requireHttp($rejects($insert, ['title' => '   '] + $valid), 'MySQL accepted a blank title.');
    requireHttp($rejects($insert, ['note' => ' '] + $valid), 'MySQL accepted a blank note.');
    requireHttp($rejects($insert, ['title' => str_repeat('a', 121)] + $valid), 'MySQL accepted an over-long title.');
    requireHttp($rejects($insert, ['project_id' => 2147483000] + $valid), 'MySQL accepted a source for a missing project.');
    requireHttp($rejects($insert, ['portfolio_id' => 2147483000] + $valid), 'MySQL accepted a source for a missing Portfolio.');

    // Deleting a project deletes its sources (checked and rolled back).
    $database->beginTransaction();
    try {
        $database->prepare("INSERT INTO projects (portfolio_id, title, category, description, github_url, technologies) VALUES (:portfolio_id, 'Cascade', 'Test', 'Cascade check', '', '[]')")->execute(['portfolio_id' => $portfolioA]);
        $cascadeProject = (int) $database->lastInsertId();
        $database->prepare($insert)->execute(['project_id' => $cascadeProject] + $valid);
        $database->prepare('DELETE FROM projects WHERE id = :id')->execute(['id' => $cascadeProject]);
        $left = $database->prepare('SELECT COUNT(*) FROM project_evidence_sources WHERE project_id = :id');
        $left->execute(['id' => $cascadeProject]);
        requireHttp((int) $left->fetchColumn() === 0, 'Deleting a project left its sources.');
    } finally {
        $database->rollBack();
    }
} finally {
    $database->prepare('DELETE FROM project_evidence_sources WHERE portfolio_id IN (:a, :b)')->execute(['a' => $portfolioA, 'b' => $portfolioB]);
}

echo "PASS Evidence sources HTTP add, limit, remove, CSRF, tenant denial, Evidence Hub summary, and MySQL constraints\n";
