<?php

declare(strict_types=1);

// This fixture may change only the disposable database owned by the CI stack.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';

const CI_SMOKE_DATABASE = 'ather_career_test_ci';
const CI_SMOKE_ISSUER = 'https://127.0.0.1:9443/';
const CI_SMOKE_SUBJECT = 'synthetic-preserved-owner';
const CI_SMOKE_SLUG = 'a-public';
const CI_SMOKE_NAME = 'Synthetic CI Portfolio Owner';

try {
    if (getenv('ATHERCAR_CI_SMOKE_FIXTURE') !== '1'
        || getenv('APP_ENV') !== 'production'
        || getenv('ATHERCAR_TEST_MODE') !== false
        || getenv('DB_HOST') !== 'db'
        || getenv('DB_NAME') !== CI_SMOKE_DATABASE
        || getenv('EXPECTED_OIDC_ISSUER') !== CI_SMOKE_ISSUER
        || getenv('PRESERVED_V1_OIDC_SUBJECT') !== CI_SMOKE_SUBJECT) {
        throw new RuntimeException('CI fixture environment is not isolated.');
    }

    $database = getDatabaseConnection();
    $database->beginTransaction();
    try {
        $users = $database->query('SELECT id, oidc_issuer, oidc_subject, account_status FROM users FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        $portfolios = $database->query('SELECT id, owner_user_id, public_slug, is_published, published_at FROM portfolios FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        if (count($users) !== 1 || count($portfolios) !== 1
            || $users[0]['oidc_issuer'] !== CI_SMOKE_ISSUER
            || $users[0]['oidc_subject'] !== CI_SMOKE_SUBJECT
            || $users[0]['account_status'] !== 'active'
            || (int) $portfolios[0]['owner_user_id'] !== (int) $users[0]['id']) {
            throw new RuntimeException('CI owner binding is not the expected singleton.');
        }
        $userId = (int) $users[0]['id'];
        $portfolioId = (int) $portfolios[0]['id'];
        $portfolio = $portfolios[0];
        if ($userId < 1 || $portfolioId < 1
            || !in_array($portfolio['public_slug'], [null, CI_SMOKE_SLUG], true)
            || !in_array((int) $portfolio['is_published'], [0, 1], true)
            || ((int) $portfolio['is_published'] === 1 && ($portfolio['public_slug'] !== CI_SMOKE_SLUG || $portfolio['published_at'] === null))
            || ($portfolio['published_at'] !== null && $portfolio['public_slug'] !== CI_SMOKE_SLUG)) {
            throw new RuntimeException('CI portfolio publication state conflicts with the fixture.');
        }

        $projects = $database->prepare('SELECT COUNT(*) FROM projects WHERE portfolio_id = :portfolio_id');
        $projects->execute(['portfolio_id' => $portfolioId]);
        if ((int) $projects->fetchColumn() < 1) {
            throw new RuntimeException('Preserved V1 project is missing.');
        }
        $profile = $database->prepare('SELECT full_name FROM personal_info WHERE portfolio_id = :portfolio_id FOR UPDATE');
        $profile->execute(['portfolio_id' => $portfolioId]);
        $names = $profile->fetchAll(PDO::FETCH_COLUMN);
        if ($names === []) {
            $insert = $database->prepare('INSERT INTO personal_info (portfolio_id, full_name) VALUES (:portfolio_id, :full_name)');
            $insert->execute(['portfolio_id' => $portfolioId, 'full_name' => CI_SMOKE_NAME]);
        } elseif ($names !== [CI_SMOKE_NAME]) {
            throw new RuntimeException('CI profile conflicts with the fixture.');
        }

        $context = AuthorizedPortfolioContext::fromValidatedOwnership(
            AuthenticatedUserContext::fromValidatedUser($userId),
            $portfolioId,
        );
        if ($portfolio['public_slug'] === null) {
            setOwnedPublicSlug($database, $context, CI_SMOKE_SLUG);
        }
        if ((int) $portfolio['is_published'] !== 1) {
            publishOwnedPortfolio($database, $context);
        }
        if (resolvePublicReadContext($database, CI_SMOKE_SLUG)?->portfolioId !== $portfolioId) {
            throw new RuntimeException('CI portfolio did not become public.');
        }
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
    echo "CI public smoke fixture ready.\n";
} catch (Throwable) {
    fwrite(STDERR, "CI public smoke fixture refused its environment or state.\n");
    exit(1);
}
