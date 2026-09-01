<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';

try {
    TestEnvironment::assertSafeEnvironment(getenv());
    if ($argc !== 4 || !ctype_digit($argv[1]) || !ctype_digit($argv[2])) {
        throw new InvalidArgumentException('Public slug race worker arguments are invalid.');
    }

    $user = AuthenticatedUserContext::fromValidatedUser((int) $argv[1]);
    $context = AuthorizedPortfolioContext::fromValidatedOwnership($user, (int) $argv[2]);
    $database = getDatabaseConnection();
    usleep(250000);
    setOwnedPublicSlug($database, $context, $argv[3]);
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Public slug race worker rejected the competing write.\n");
    exit(1);
}
