<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
    TestEnvironment::assertSafeEnvironment(getenv());
    if ($argc < 3) {
        throw new InvalidArgumentException('Ownership race worker arguments are invalid.');
    }

    $mode = $argv[1];
    $database = getDatabaseConnection();
    usleep(250000);

    if ($mode === 'identity' && $argc === 3) {
        $statement = $database->prepare(
            "INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
             VALUES ('https://issuer.test/ather-career', :subject, 'active', 1)"
        );
        $statement->execute(['subject' => $argv[2]]);
    } elseif ($mode === 'portfolio' && $argc === 3 && ctype_digit($argv[2])) {
        $statement = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:user_id)');
        $statement->execute(['user_id' => (int) $argv[2]]);
    } elseif ($mode === 'skill' && $argc === 4 && ctype_digit($argv[2])) {
        $statement = $database->prepare('INSERT INTO skills (portfolio_id, skill_name) VALUES (:portfolio_id, :skill_name)');
        $statement->execute(['portfolio_id' => (int) $argv[2], 'skill_name' => $argv[3]]);
    } else {
        throw new InvalidArgumentException('Ownership race worker mode is invalid.');
    }

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Ownership race worker rejected the competing write.\n');
    exit(1);
}
