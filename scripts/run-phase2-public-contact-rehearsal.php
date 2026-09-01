<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/public_contact.php';
require_once __DIR__ . '/../includes/portfolio_scoped_data.php';
require_once __DIR__ . '/../includes/rate_limit.php';

const PUBLIC_CONTACT_TEST_ISSUER = 'https://issuer.test/ather-career';

function publicContactCreateUser(PDO $database, string $label): int
{
    $statement = $database->prepare(
        'INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
         VALUES (:issuer, :subject, \'active\', 1)'
    );
    $statement->execute([
        'issuer' => PUBLIC_CONTACT_TEST_ISSUER,
        'subject' => 'p2j06-' . $label . '-' . bin2hex(random_bytes(8)),
    ]);

    return (int) $database->lastInsertId();
}

function publicContactCreatePortfolio(PDO $database, int $userId, string $slug, string $name): array
{
    $portfolio = $database->prepare('INSERT INTO portfolios (owner_user_id, public_slug, is_published, published_at) VALUES (:owner_user_id, :public_slug, 1, CURRENT_TIMESTAMP)');
    $portfolio->execute(['owner_user_id' => $userId, 'public_slug' => $slug]);
    $portfolioId = (int) $database->lastInsertId();
    $profile = $database->prepare('INSERT INTO personal_info (portfolio_id, full_name) VALUES (:portfolio_id, :full_name)');
    $profile->execute(['portfolio_id' => $portfolioId, 'full_name' => $name]);

    return [$portfolioId, AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($userId), $portfolioId)];
}

function publicContactMessageCount(PDO $database, int $portfolioId): int
{
    $statement = $database->prepare('SELECT COUNT(*) FROM messages WHERE recipient_portfolio_id = :portfolio_id');
    $statement->execute(['portfolio_id' => $portfolioId]);

    return (int) $statement->fetchColumn();
}

/** @return 'created'|'not_found'|'invalid' */
function publicContactSubmit(PDO $database, string $slug, array $submitted): string
{
    $submission = preparePublicContactSubmission($database, $slug, $submitted);
    if ($submission['context'] === null) {
        return 'not_found';
    }
    if ($submission['errors'] !== []) {
        return 'invalid';
    }
    createPublicContactMessage($database, $submission['context'], $submission['values']);

    return 'created';
}

$environment = null;
$passed = [];
try {
    TestEnvironment::assertSafeEnvironment(getenv());
    $environment = TestEnvironment::create();
    putenv('RATE_LIMIT_STATE_DIR=' . $environment->storageRoot . DIRECTORY_SEPARATOR . 'rate-limit');
    $database = getDatabaseConnection();
    phase2AssertSame('public_lifecycle', $database->query("SELECT name FROM schema_migrations WHERE version = '005'")->fetchColumn(), 'P2J-06 requires the public lifecycle migration.');

    $userA = publicContactCreateUser($database, 'a');
    [$portfolioA, $contextA] = publicContactCreatePortfolio($database, $userA, 'contact-a', 'Contact A');
    $userB = publicContactCreateUser($database, 'b');
    [$portfolioB, $contextB] = publicContactCreatePortfolio($database, $userB, 'contact-b', 'Contact B');
    $valid = ['name' => 'Public Sender', 'email' => 'sender@example.test', 'message' => 'Hello from the public page.'];

    phase2AssertSame('created', publicContactSubmit($database, 'contact-a', $valid + ['recipient_portfolio_id' => (string) $portfolioB, 'portfolio_id' => (string) $portfolioB, 'user_id' => (string) $userB]), 'A public contact was not created.');
    phase2AssertSame(1, publicContactMessageCount($database, $portfolioA), 'A contact did not reach A.');
    phase2AssertSame(0, publicContactMessageCount($database, $portfolioB), 'Forged recipient fields redirected A contact to B.');
    phase2AssertSame(['Public Sender'], array_column(listAuthorizedMessages($database, $contextA), 'name'), 'Owner A inbox did not receive only A messages.');
    phase2AssertSame([], listAuthorizedMessages($database, $contextB), 'Owner B inbox received A messages.');
    $passed[] = 'T-CONTACT-A-FORGED-RECIPIENT';

    phase2AssertSame('created', publicContactSubmit($database, 'contact-b', ['name' => 'Second Sender', 'email' => 'second@example.test', 'message' => 'Hello B.', 'recipient_portfolio_id' => (string) $portfolioA, 'portfolio_id' => (string) $portfolioA, 'user_id' => (string) $userA]), 'B public contact was not created.');
    phase2AssertSame(1, publicContactMessageCount($database, $portfolioA), 'B contact changed A message count.');
    phase2AssertSame(1, publicContactMessageCount($database, $portfolioB), 'B contact did not reach B.');
    phase2AssertSame(['Public Sender'], array_column(listAuthorizedMessages($database, $contextA), 'name'), 'Owner A inbox leaked B messages.');
    phase2AssertSame(['Second Sender'], array_column(listAuthorizedMessages($database, $contextB), 'name'), 'Owner B inbox did not isolate B messages.');
    $passed[] = 'T-CONTACT-A-B-ISOLATION';

    $beforeDenied = publicContactMessageCount($database, $portfolioA) + publicContactMessageCount($database, $portfolioB);
    phase2AssertSame('not_found', publicContactSubmit($database, 'missing-contact', $valid), 'Unknown slug accepted a message.');
    $database->prepare('UPDATE portfolios SET is_published = 0 WHERE id = :id')->execute(['id' => $portfolioA]);
    phase2AssertSame('not_found', publicContactSubmit($database, 'contact-a', $valid), 'Unpublished Portfolio accepted a message.');
    $database->prepare('UPDATE users SET account_status = \'disabled\' WHERE id = :id')->execute(['id' => $userB]);
    phase2AssertSame('not_found', publicContactSubmit($database, 'contact-b', $valid), 'Disabled-owner Portfolio accepted a message.');
    phase2AssertSame($beforeDenied, publicContactMessageCount($database, $portfolioA) + publicContactMessageCount($database, $portfolioB), 'Denied contact attempts changed message rows.');
    $passed[] = 'T-CONTACT-DENIED-ZERO-DELTA';

    $database->prepare('UPDATE portfolios SET is_published = 1 WHERE id = :id')->execute(['id' => $portfolioA]);
    phase2Assert(resolvePublicReadContext($database, 'contact-a') !== null, 'Published GET setup did not resolve A.');
    $database->prepare('UPDATE portfolios SET is_published = 0 WHERE id = :id')->execute(['id' => $portfolioA]);
    phase2AssertSame('not_found', publicContactSubmit($database, 'contact-a', $valid), 'POST reused stale public eligibility.');
    phase2AssertSame($beforeDenied, publicContactMessageCount($database, $portfolioA) + publicContactMessageCount($database, $portfolioB), 'Stale-public POST changed message rows.');
    $passed[] = 'T-CONTACT-POST-RECHECK';

    $database->prepare('UPDATE portfolios SET is_published = 1 WHERE id = :id')->execute(['id' => $portfolioA]);
    $beforeValidation = publicContactMessageCount($database, $portfolioA) + publicContactMessageCount($database, $portfolioB);
    phase2AssertSame('invalid', publicContactSubmit($database, 'contact-a', ['name' => '', 'email' => 'not-an-email', 'message' => '']), 'Existing contact validation did not remain active.');
    phase2AssertSame($beforeValidation, publicContactMessageCount($database, $portfolioA) + publicContactMessageCount($database, $portfolioB), 'Denied validation changed message rows.');
    clearRateLimit('contact', 'p2j06-rate-limit');
    phase2AssertSame(true, consumeRateLimit('contact', 'p2j06-rate-limit', 1, 900)['allowed'], 'Existing contact limiter rejected its first attempt.');
    phase2AssertSame(false, consumeRateLimit('contact', 'p2j06-rate-limit', 1, 900)['allowed'], 'Existing contact limiter did not limit repeated attempts.');
    $passed[] = 'T-CONTACT-VALIDATION-LIMITER';
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL public contact rehearsal: {$exception->getMessage()}\n");
    exit(1);
} finally {
    putenv('RATE_LIMIT_STATE_DIR');
    if ($environment instanceof TestEnvironment) {
        try {
            $environment->tearDown();
        } catch (Throwable $exception) {
            fwrite(STDERR, "FAIL public contact test namespace teardown: {$exception->getMessage()}\n");
            exit(1);
        }
    }
}

foreach ($passed as $name) {
    echo "PASS {$name}\n";
}
echo "PASS public contact rehearsal\n";
