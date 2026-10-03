<?php

declare(strict_types=1);

// Run only against a fresh, disposable MySQL container with this exact identity.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || getenv('DB_HOST') !== 'ather-profile-contact-mysql'
    || getenv('DB_NAME') !== 'ather_career_test_profile_contacts') {
    throw new RuntimeException('A dedicated disposable contact rehearsal database is required.');
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../includes/owner_actions.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';

function contactRehearsalChild(array $arguments): string
{
    $process = proc_open([PHP_BINARY, ...$arguments], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not run rehearsal child.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    phase2AssertSame(0, proc_close($process), 'Rehearsal child failed: ' . $error);
    return (string) $output;
}

$db = getDatabaseConnection();
phase2AssertSame([], $db->query('SHOW TABLES')->fetchAll(), 'Rehearsal requires an empty database.');
$db->exec((string) file_get_contents(__DIR__ . '/../database/portfolio_db.sql'));
$db->exec('DELETE FROM projects'); // Remove only the fresh bootstrap sample.
echo contactRehearsalChild([__DIR__ . '/../database/migrate.php', '--through=008']);
$db->exec("INSERT INTO users (oidc_issuer, oidc_subject) VALUES ('https://issuer.test/', 'contact-owner'), ('https://issuer.test/', 'new-owner');
    INSERT INTO portfolios (owner_user_id, public_slug, is_published) VALUES (1, 'contact-owner', 1), (2, 'new-owner', 0);
    INSERT INTO personal_info (portfolio_id, full_name, email, phone_primary) VALUES (1, 'Synthetic Owner', 'private@example.org', '+962 79 123 4567')");
echo contactRehearsalChild([__DIR__ . '/../database/migrate.php']);
phase2AssertSame('0', (string) $db->query('SELECT public_contact_visible FROM personal_info')->fetchColumn(), 'Migration opted in an existing profile.');
$context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 2);
createAuthorizedPersonalInfo($db, $context, ['full_name' => 'New owner']);
phase2AssertSame(0, (int) loadAuthorizedPersonalInfo($db, $context)['public_contact_visible'], 'New profile did not default OFF.');
phase2Assert(str_contains(contactRehearsalChild([__DIR__ . '/../database/migrate.php']), 'No pending migrations.'), 'Migration runner is not idempotent.');

// Run the real public page and contact-error entrypoints in child processes.
foreach ([0, 1, 0] as $visible) {
    $owner = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 1);
    updateAuthorizedPersonalInfo($db, $owner, 1, ['public_contact_visible' => $visible]);
    foreach (['public_portfolio.php', 'public_contact.php'] as $route) {
        $code = '$_SERVER["REQUEST_METHOD"]=' . var_export($route === 'public_contact.php' ? 'POST' : 'GET', true) . '; $_GET["slug"]="contact-owner"; $_POST=[]; require ' . var_export(dirname(__DIR__) . '/' . $route, true) . ';';
        $html = contactRehearsalChild(['-r', $code]);
        phase2Assert(str_contains($html, 'Synthetic Owner'), 'Real route did not render the profile.');
        phase2AssertSame((bool) $visible, str_contains($html, 'private@example.org'), 'Real public route violated email visibility.');
        phase2AssertSame((bool) $visible, str_contains($html, '+962 79 123 4567'), 'Real public route violated phone visibility.');
        phase2AssertSame((bool) $visible, str_contains($html, 'mailto:'), 'Real public route violated link visibility.');
        if ($route === 'public_contact.php') phase2Assert(str_contains($html, 'Please correct the highlighted fields'), 'Contact route did not exercise validation-error rendering.');
    }
}
echo "PASS MySQL migration defaults, repeat migration, scoped reads/writes, public page and contact-error route visibility\n";
