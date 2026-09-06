<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../tests/phase2/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/portfolio_scoped_data.php';
require_once __DIR__ . '/../includes/public_contact.php';
require_once __DIR__ . '/../includes/public_lifecycle.php';
require_once __DIR__ . '/../includes/public_url.php';
require_once __DIR__ . '/../includes/owner_publication_presentation.php';
require_once __DIR__ . '/../includes/media_access.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/storage.php';

const PUBLICATION_LIFECYCLE_E2E_ISSUER = 'https://issuer.test/ather-career';

function publicationLifecycleE2eRunPhp(array $arguments): string
{
    $pipes = [];
    $process = proc_open($arguments, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start a disposable lifecycle child process.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Disposable lifecycle child process failed: ' . trim((string) $stderr));
    }

    return (string) $stdout;
}

function publicationLifecycleE2eCreateUser(PDO $database, string $label, string $status = 'active'): int
{
    $statement = $database->prepare(
        'INSERT INTO users (oidc_issuer, oidc_subject, account_status, authz_version)
         VALUES (:issuer, :subject, :account_status, 1)'
    );
    $statement->execute([
        'issuer' => PUBLICATION_LIFECYCLE_E2E_ISSUER,
        'subject' => 'publication-e2e-' . $label,
        'account_status' => $status,
    ]);

    return (int) $database->lastInsertId();
}

function publicationLifecycleE2eCreatePortfolio(PDO $database, int $userId): int
{
    $statement = $database->prepare('INSERT INTO portfolios (owner_user_id) VALUES (:owner_user_id)');
    $statement->execute(['owner_user_id' => $userId]);

    return (int) $database->lastInsertId();
}

function publicationLifecycleE2eContext(int $userId, int $portfolioId): AuthorizedPortfolioContext
{
    return AuthorizedPortfolioContext::fromValidatedOwnership(
        AuthenticatedUserContext::fromValidatedUser($userId),
        $portfolioId,
    );
}

/** @return array<string, mixed> */
function publicationLifecycleE2eProfileValues(string $name, string $profileImage): array
{
    return [
        'full_name' => $name,
        'professional_title' => 'Synthetic Lifecycle Engineer',
        'hero_headline' => $name . ' builds isolated tests.',
        'email' => 'hidden@example.test',
        'phone_primary' => '',
        'phone_secondary' => '',
        'location' => 'Test City',
        'about_me' => $name . ' synthetic profile.',
        'work_description' => 'Synthetic lifecycle fixture.',
        'linkedin_url' => 'https://example.test/linkedin',
        'github_url' => 'https://example.test/github',
        'instagram_url' => '',
        'facebook_url' => '',
        'website_url' => '',
        'profile_image_path' => $profileImage,
        'public_contact_visible' => false,
    ];
}

function publicationLifecycleE2eCreatePng(string $path, int $width, int $height, int $red): void
{
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('Could not create a synthetic image fixture.');
    }
    imagefill($image, 0, 0, imagecolorallocate($image, $red, 80, 160));
    if (!imagepng($image, $path)) {
        imagedestroy($image);
        throw new RuntimeException('Could not write a synthetic image fixture.');
    }
    imagedestroy($image);
}

/** @return array{profile_id: int, project_ids: list<int>, profile_image: string, project_image: string} */
function publicationLifecycleE2eSeedTenant(PDO $database, AuthorizedPortfolioContext $context, string $label, string $name, string $sourceRoot): array
{
    $profileSource = $sourceRoot . DIRECTORY_SEPARATOR . $label . '-profile.png';
    $projectSource = $sourceRoot . DIRECTORY_SEPARATOR . $label . '-project.png';
    publicationLifecycleE2eCreatePng($profileSource, 480, 480, $label === 'a' ? 60 : 110);
    publicationLifecycleE2eCreatePng($projectSource, 960, 640, $label === 'a' ? 90 : 140);

    $profileImage = copyFileToPrivateMedia($profileSource, $context->portfolioId, 'profile_original', $label . '-profile.png');
    $projectImage = copyFileToPrivateMedia($projectSource, $context->portfolioId, 'projects', $label . '-project.png');
    if ($profileImage === null || $projectImage === null
        || generateProfilePresentationImage($profileImage, $context->portfolioId) === null
        || generateProjectPresentationImage($projectImage, $context->portfolioId) === null) {
        throw new RuntimeException('Could not create synthetic private media derivatives.');
    }

    $profileId = createAuthorizedPersonalInfo($database, $context, publicationLifecycleE2eProfileValues($name, $profileImage));
    createAuthorizedSkill($database, $context, strtoupper($label) . ' Skill One');
    createAuthorizedSkill($database, $context, strtoupper($label) . ' Skill Two');
    $projectWithMedia = createAuthorizedProject(
        $database,
        $context,
        'E2E ' . strtoupper($label) . ' Media Project',
        'Testing',
        'Synthetic public project with media.',
        'https://example.test/' . $label . '-media',
        $projectImage,
        ['PHP', 'Testing'],
    );
    $projectWithoutMedia = createAuthorizedProject(
        $database,
        $context,
        'E2E ' . strtoupper($label) . ' Text Project',
        'Testing',
        'Synthetic public project without media.',
        'https://example.test/' . $label . '-text',
        null,
        ['SQL'],
    );
    createAuthorizedExperience($database, $context, [
        'experience_type' => 'employment',
        'role_title' => 'E2E ' . strtoupper($label) . ' Role',
        'organization' => 'Synthetic Organization',
        'location' => 'Test City',
        'start_month' => '2024-01',
        'end_month' => '2024-12',
        'is_current' => false,
        'description' => 'Synthetic experience.',
    ]);

    return [
        'profile_id' => $profileId,
        'project_ids' => [$projectWithMedia, $projectWithoutMedia],
        'profile_image' => $profileImage,
        'project_image' => $projectImage,
    ];
}

function publicationLifecycleE2eExpectException(callable $operation, string $message, string ...$expected): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        foreach ($expected as $class) {
            if ($exception instanceof $class) {
                return;
            }
        }
        throw new RuntimeException($message . ' Received ' . $exception::class . '.', 0, $exception);
    }

    throw new RuntimeException($message);
}

/** @return array{process: resource, input: resource, port: int} */
function publicationLifecycleE2eStartHttpServer(TestEnvironment $environment): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) {
        throw new RuntimeException('Could not allocate a local lifecycle HTTP port: ' . $errorMessage);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = is_string($address) ? (int) substr(strrchr($address, ':'), 1) : 0;
    if ($port < 1) {
        throw new RuntimeException('Could not determine a local lifecycle HTTP port.');
    }

    $logPath = $environment->storageRoot . DIRECTORY_SEPARATOR . 'http-server.log';
    $pipes = [];
    $process = proc_open([
        PHP_BINARY,
        '-S',
        '127.0.0.1:' . $port,
        '-t',
        dirname(__DIR__),
    ], [
        0 => ['pipe', 'r'],
        1 => ['file', '/dev/null', 'a'],
        2 => ['file', $logPath, 'a'],
    ], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
    if (!is_resource($process) || !isset($pipes[0])) {
        throw new RuntimeException('Could not start the disposable lifecycle HTTP server.');
    }

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://127.0.0.1:' . $port, $connectCode, $connectMessage, 0.05);
        if (is_resource($connection)) {
            fclose($connection);
            return ['process' => $process, 'input' => $pipes[0], 'port' => $port];
        }
        $status = proc_get_status($process);
        if ($status['running'] !== true) {
            fclose($pipes[0]);
            proc_close($process);
            throw new RuntimeException('Disposable lifecycle HTTP server stopped before becoming ready.');
        }
        usleep(20_000);
    }

    fclose($pipes[0]);
    proc_terminate($process);
    proc_close($process);
    throw new RuntimeException('Disposable lifecycle HTTP server did not become ready.');
}

/** @param array{process: resource, input: resource, port: int}|null $server */
function publicationLifecycleE2eStopHttpServer(?array $server): void
{
    if ($server === null) {
        return;
    }
    fclose($server['input']);
    proc_terminate($server['process']);
    proc_close($server['process']);
}

/** @return array{status: int, headers: list<string>, body: string} */
function publicationLifecycleE2eHttpRequest(int $port, string $path, string $method = 'GET', array $form = []): array
{
    $headers = "Accept: */*\r\n";
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'follow_location' => 0,
        'max_redirects' => 0,
        'timeout' => 5,
        'header' => $headers,
    ];
    if ($method === 'POST') {
        $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $options['content'] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
    }

    $context = stream_context_create(['http' => $options]);
    $body = file_get_contents('http://127.0.0.1:' . $port . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $statusLine = $responseHeaders[0] ?? '';
    if (!preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
        throw new RuntimeException('Lifecycle HTTP response had no status line.');
    }

    return [
        'status' => (int) $matches[1],
        'headers' => $responseHeaders,
        'body' => is_string($body) ? $body : '',
    ];
}

/** @param list<string> $headers */
function publicationLifecycleE2eHeader(array $headers, string $name): ?string
{
    foreach ($headers as $header) {
        if (str_starts_with(strtolower($header), strtolower($name) . ':')) {
            return trim(substr($header, strlen($name) + 1));
        }
    }

    return null;
}

function publicationLifecycleE2eMessageCount(PDO $database, int $portfolioId): int
{
    $statement = $database->prepare('SELECT COUNT(*) FROM messages WHERE recipient_portfolio_id = :portfolio_id');
    $statement->execute(['portfolio_id' => $portfolioId]);

    return (int) $statement->fetchColumn();
}

function publicationLifecycleE2eOnlyMessageId(PDO $database, int $portfolioId): int
{
    $statement = $database->prepare(
        'SELECT id FROM messages WHERE recipient_portfolio_id = :portfolio_id ORDER BY id ASC LIMIT 1'
    );
    $statement->execute(['portfolio_id' => $portfolioId]);
    $messageId = $statement->fetchColumn();
    if ($messageId === false) {
        throw new RuntimeException('Expected the run-owned Contact message was not found.');
    }

    return (int) $messageId;
}

$environment = null;
$database = null;
$server = null;
$passed = [];
try {
    TestEnvironment::assertSafeEnvironment(getenv());
    $environment = TestEnvironment::create();
    $environment->useExternallyProvisionedDatabase();
    $database = getDatabaseConnection();
    phase2AssertSame([], $database->query('SHOW TABLES')->fetchAll(), 'Lifecycle E2E requires an empty run-owned database.');
    $baselineSchema = file_get_contents(__DIR__ . '/../database/portfolio_db.sql');
    if (!is_string($baselineSchema) || $baselineSchema === '') {
        throw new RuntimeException('Fresh lifecycle baseline schema is unavailable.');
    }
    $database->exec($baselineSchema);
    $database->exec('DELETE FROM projects'); // Remove only the bootstrap sample from this disposable database.
    publicationLifecycleE2eRunPhp([PHP_BINARY, __DIR__ . '/../database/migrate.php']);

    $storageRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'private-media';
    $sourceRoot = $environment->storageRoot . DIRECTORY_SEPARATOR . 'sources';
    if (!mkdir($storageRoot, 0700, true) || !mkdir($sourceRoot, 0700, true)) {
        throw new RuntimeException('Could not create run-owned lifecycle media directories.');
    }
    putenv('ATHERCAR_STORAGE_ROOT=' . $storageRoot);
    putenv('RATE_LIMIT_STATE_DIR=' . $environment->storageRoot . DIRECTORY_SEPARATOR . 'rate-limit');
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

    $userA = publicationLifecycleE2eCreateUser($database, 'tenant-a');
    $portfolioA = publicationLifecycleE2eCreatePortfolio($database, $userA);
    $contextA = publicationLifecycleE2eContext($userA, $portfolioA);
    $userB = publicationLifecycleE2eCreateUser($database, 'tenant-b');
    $portfolioB = publicationLifecycleE2eCreatePortfolio($database, $userB);
    $contextB = publicationLifecycleE2eContext($userB, $portfolioB);
    $userInactive = publicationLifecycleE2eCreateUser($database, 'inactive', 'disabled');
    $portfolioInactive = publicationLifecycleE2eCreatePortfolio($database, $userInactive);
    $contextInactive = publicationLifecycleE2eContext($userInactive, $portfolioInactive);

    $aResources = publicationLifecycleE2eSeedTenant($database, $contextA, 'a', 'E2E A Published', $sourceRoot);
    $bResources = publicationLifecycleE2eSeedTenant($database, $contextB, 'b', 'E2E B Private', $sourceRoot);
    $inactiveResources = publicationLifecycleE2eSeedTenant($database, $contextInactive, 'inactive', 'E2E Inactive', $sourceRoot);
    $database->prepare('UPDATE portfolios SET public_slug = :slug, is_published = 1, published_at = CURRENT_TIMESTAMP WHERE id = :id')->execute([
        'slug' => 'e2e-inactive',
        'id' => $portfolioInactive,
    ]);

    phase2AssertSame(['public_slug' => null, 'is_published' => 0, 'published_at' => null], ownedPublicLifecycleState($database, $contextA), 'A new Portfolio did not begin private without a slug.');
    phase2AssertSame(null, resolvePublicReadContext($database, 'e2e-tenant-a'), 'A private Portfolio resolved publicly before reservation.');
    $server = publicationLifecycleE2eStartHttpServer($environment);
    foreach ([
        '/public_portfolio.php?slug=e2e-tenant-a',
        '/public_media.php?slug=e2e-tenant-a&type=profile',
        '/public_projects_json.php?slug=e2e-tenant-a',
        '/public_contact.php?slug=e2e-tenant-a',
    ] as $path) {
        phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], $path)['status'], 'Private Portfolio endpoint exposed data: ' . $path);
    }
    $passed[] = 'T-E2E-INITIAL-PRIVATE';

    phase2AssertSame('e2e-tenant-b', setOwnedPublicSlug($database, $contextB, 'E2E-Tenant-B'), 'B could not reserve its independent slug.');
    phase2AssertSame('e2e-tenant-a', setOwnedPublicSlug($database, $contextA, ' E2E-Tenant-A '), 'A slug was not normalized and reserved.');
    $reservedA = ownedPublicLifecycleState($database, $contextA);
    phase2AssertSame(0, $reservedA['is_published'], 'Reserved A slug incorrectly became public.');
    phase2AssertSame('https://localhost:8443/p/e2e-tenant-a', publicPortfolioUrl($reservedA['public_slug']), 'Reserved A URL did not use configured PUBLIC_BASE_URL.');
    $_SERVER['HTTP_HOST'] = 'attacker.example.test';
    phase2AssertSame('https://localhost:8443/p/e2e-tenant-a', publicPortfolioUrl($reservedA['public_slug']), 'Host header changed the public URL.');
    unset($_SERVER['HTTP_HOST']);
    ob_start();
    renderOwnerPublicationPresentation($reservedA, ownerPublicationPublicUrl($reservedA));
    $reservedMarkup = (string) ob_get_clean();
    phase2Assert(!str_contains($reservedMarkup, 'View Portfolio') && !str_contains($reservedMarkup, 'Copy Link') && str_contains($reservedMarkup, 'not live'), 'Reserved state offered misleading public actions.');
    foreach (['ab', str_repeat('a', 65), 'bad--slug', 'p', 'https://example.test/p/e2e-tenant-a'] as $invalidSlug) {
        publicationLifecycleE2eExpectException(
            static fn (): string => setOwnedPublicSlug($database, $contextA, $invalidSlug),
            'Invalid slug was accepted: ' . $invalidSlug,
            PublicLifecycleValidationException::class,
        );
    }
    publicationLifecycleE2eExpectException(
        static fn (): string => setOwnedPublicSlug($database, $contextA, 'e2e-tenant-b'),
        'Duplicate B slug was accepted by A.',
        PublicLifecycleConflictException::class,
    );
    publicationLifecycleE2eExpectException(
        static fn (): string => setOwnedPublicSlug($database, $contextB, 'e2e-tenant-a'),
        'Tenant B claimed A reserved slug.',
        PublicLifecycleConflictException::class,
    );
    phase2AssertSame('e2e-tenant-a', ownedPublicLifecycleState($database, $contextA)['public_slug'], 'B altered A reserved slug.');
    $passed[] = 'T-E2E-SLUG-RESERVATION';

    $userReadiness = publicationLifecycleE2eCreateUser($database, 'readiness');
    $portfolioReadiness = publicationLifecycleE2eCreatePortfolio($database, $userReadiness);
    $contextReadiness = publicationLifecycleE2eContext($userReadiness, $portfolioReadiness);
    publicationLifecycleE2eExpectException(
        static fn (): null => publishOwnedPortfolio($database, $contextReadiness),
        'Missing slug was publishable.',
        PublicLifecycleValidationException::class,
    );
    setOwnedPublicSlug($database, $contextReadiness, 'e2e-readiness');
    publicationLifecycleE2eExpectException(
        static fn (): null => publishOwnedPortfolio($database, $contextReadiness),
        'Missing professional name was publishable.',
        PublicLifecycleValidationException::class,
    );
    $passed[] = 'T-E2E-READINESS';

    publishOwnedPortfolio($database, $contextA);
    $publishedA = ownedPublicLifecycleState($database, $contextA);
    phase2AssertSame(1, $publishedA['is_published'], 'Publish did not set A public state.');
    phase2Assert(is_string($publishedA['published_at']) && $publishedA['published_at'] !== '', 'Publish did not set first publication time.');
    $firstPublishedAt = $publishedA['published_at'];
    publishOwnedPortfolio($database, $contextA);
    phase2AssertSame($firstPublishedAt, ownedPublicLifecycleState($database, $contextA)['published_at'], 'Idempotent publish changed first publication time.');
    ob_start();
    renderOwnerPublicationPresentation($publishedA, ownerPublicationPublicUrl($publishedA));
    $publishedMarkup = (string) ob_get_clean();
    $canonicalUrl = 'https://localhost:8443/p/e2e-tenant-a';
    phase2Assert(str_contains($publishedMarkup, 'Your Portfolio is live')
        && str_contains($publishedMarkup, 'Changes saved while published become publicly visible immediately.')
        && str_contains($publishedMarkup, 'href="' . $canonicalUrl . '"')
        && str_contains($publishedMarkup, 'data-copy-public-url="publication-public-url"')
        && str_contains($publishedMarkup, '<code>' . $canonicalUrl . '</code>'), 'Published state did not use one server-generated canonical URL.');
    phase2AssertSame(['public_slug' => 'e2e-tenant-b', 'is_published' => 0, 'published_at' => null], ownedPublicLifecycleState($database, $contextB), 'Publishing A changed B state.');
    $passed[] = 'T-E2E-PUBLISH-URL-UX';

    $publicA = resolvePublicReadContext($database, 'e2e-tenant-a');
    phase2AssertSame($portfolioA, $publicA?->portfolioId, 'Published A did not resolve to A public context.');
    $portfolioResponse = publicationLifecycleE2eHttpRequest($server['port'], '/public_portfolio.php?slug=e2e-tenant-a&portfolio_id=' . $portfolioB);
    phase2AssertSame(200, $portfolioResponse['status'], 'Published A Portfolio controller did not return 200.');
    phase2Assert(str_contains($portfolioResponse['body'], 'E2E A Published')
        && !str_contains($portfolioResponse['body'], 'E2E B Private')
        && str_contains($portfolioResponse['body'], 'A Skill One')
        && str_contains($portfolioResponse['body'], 'E2E A Role')
        && !str_contains($portfolioResponse['body'], 'Private Preview')
        && !str_contains($portfolioResponse['body'], 'owner_publication.php'), 'Public Portfolio content was not tenant-scoped.');
    phase2AssertSame(null, resolvePublicReadContext($database, 'missing-e2e-slug'), 'Unknown public slug resolved.');
    phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], '/public_portfolio.php?slug=bad--slug')['status'], 'Malformed public slug resolved.');
    $passed[] = 'T-E2E-PUBLIC-READ';

    $profileMedia = publicationLifecycleE2eHttpRequest($server['port'], '/public_media.php?slug=e2e-tenant-a&type=profile');
    $projectMedia = publicationLifecycleE2eHttpRequest($server['port'], '/public_media.php?slug=e2e-tenant-a&type=project&id=' . $aResources['project_ids'][0]);
    phase2AssertSame(200, $profileMedia['status'], 'Published A profile media was unavailable.');
    phase2AssertSame(200, $projectMedia['status'], 'Published A project media was unavailable.');
    phase2Assert(str_starts_with((string) publicationLifecycleE2eHeader($profileMedia['headers'], 'Content-Type'), 'image/')
        && str_starts_with((string) publicationLifecycleE2eHeader($projectMedia['headers'], 'Content-Type'), 'image/')
        && $profileMedia['body'] !== '' && $projectMedia['body'] !== '', 'Published media response was not a safe image response.');
    phase2Assert(!str_contains($profileMedia['body'], $storageRoot) && !str_contains($projectMedia['body'], $storageRoot), 'Private filesystem path appeared in media output.');
    foreach ([
        '/public_media.php?slug=e2e-tenant-a&type=project&id=' . $bResources['project_ids'][0],
        '/public_media.php?slug=e2e-tenant-a&type=project&id=0',
        '/public_media.php?slug=e2e-tenant-a&type=project&id=-1',
        '/public_media.php?slug=e2e-tenant-a&type=project&id=not-a-number',
        '/public_media.php?slug=e2e-tenant-a&type=project&id=999999',
    ] as $path) {
        phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], $path)['status'], 'Public media isolation failed: ' . $path);
    }
    $passed[] = 'T-E2E-PUBLIC-MEDIA-ISOLATION';

    $jsonResponse = publicationLifecycleE2eHttpRequest($server['port'], '/public_projects_json.php?slug=e2e-tenant-a');
    $decodedProjects = json_decode($jsonResponse['body'], true);
    phase2AssertSame(200, $jsonResponse['status'], 'Published A projects JSON did not return 200.');
    phase2Assert(is_array($decodedProjects) && ($decodedProjects['success'] ?? false) === true && isset($decodedProjects['projects']) && is_array($decodedProjects['projects']), 'Published A projects JSON was invalid.');
    phase2AssertSame($aResources['project_ids'], array_values(array_reverse(array_map(static fn (array $project): int => (int) $project['id'], $decodedProjects['projects']))), 'Projects JSON did not contain only A projects.');
    phase2Assert(!str_contains($jsonResponse['body'], 'E2E B Private') && !str_contains($jsonResponse['body'], $storageRoot), 'Projects JSON exposed foreign or filesystem data.');
    phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], '/public_projects_json.php?slug=bad--slug')['status'], 'Malformed slug reached projects JSON.');
    $passed[] = 'T-E2E-PROJECTS-JSON-ISOLATION';

    $contactPath = '/public_contact.php?slug=e2e-tenant-a';
    phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], $contactPath)['status'], 'Contact GET did not retain POST-only behavior.');
    $beforeMessages = publicationLifecycleE2eMessageCount($database, $portfolioA);
    $emptyContact = publicationLifecycleE2eHttpRequest($server['port'], $contactPath, 'POST', []);
    phase2AssertSame(422, $emptyContact['status'], 'Empty Contact POST did not return validation failure.');
    phase2AssertSame($beforeMessages, publicationLifecycleE2eMessageCount($database, $portfolioA), 'Empty Contact POST created a message.');
    $escapedContact = publicationLifecycleE2eHttpRequest($server['port'], $contactPath, 'POST', ['name' => 'Synthetic <sender>', 'email' => 'invalid-email', 'message' => 'safe']);
    phase2AssertSame(422, $escapedContact['status'], 'Invalid email did not return validation failure.');
    phase2Assert(str_contains($escapedContact['body'], 'Synthetic &lt;sender&gt;') && !str_contains($escapedContact['body'], 'Synthetic <sender>'), 'Contact validation did not preserve escaped submitted values.');
    phase2AssertSame($beforeMessages, publicationLifecycleE2eMessageCount($database, $portfolioA), 'Invalid Contact POST created a message.');
    $validContact = publicationLifecycleE2eHttpRequest($server['port'], $contactPath, 'POST', [
        'name' => 'Synthetic Sender',
        'email' => 'sender@example.test',
        'message' => 'Synthetic lifecycle contact.',
        'recipient_portfolio_id' => (string) $portfolioB,
        'portfolio_id' => (string) $portfolioB,
    ]);
    phase2AssertSame(303, $validContact['status'], 'Valid Contact POST did not PRG.');
    phase2AssertSame('/p/e2e-tenant-a?contact=sent#contact', publicationLifecycleE2eHeader($validContact['headers'], 'Location'), 'Valid Contact POST did not redirect to its canonical Portfolio URL.');
    phase2AssertSame(1, publicationLifecycleE2eMessageCount($database, $portfolioA), 'Valid Contact POST did not create exactly one A message.');
    phase2AssertSame(0, publicationLifecycleE2eMessageCount($database, $portfolioB), 'Forged Contact recipient created a B message.');
    $aMessageId = publicationLifecycleE2eOnlyMessageId($database, $portfolioA);
    phase2AssertSame(200, publicationLifecycleE2eHttpRequest($server['port'], '/public_portfolio.php?slug=e2e-tenant-a&contact=sent')['status'], 'Contact success GET did not render.');
    phase2AssertSame(1, publicationLifecycleE2eMessageCount($database, $portfolioA), 'Refreshing Contact success GET duplicated a message.');
    clearRateLimit('contact', '127.0.0.1');
    for ($attempt = 0; $attempt < 3; $attempt++) {
        phase2AssertSame(true, consumeRateLimit('contact', '127.0.0.1', 3, 900)['allowed'], 'Contact limiter did not accept an in-window attempt.');
    }
    phase2AssertSame(429, publicationLifecycleE2eHttpRequest($server['port'], $contactPath, 'POST', ['name' => 'Synthetic Sender', 'email' => 'sender@example.test', 'message' => 'Rate limited.'])['status'], 'Contact limiter did not deny the next request.');
    phase2AssertSame(1, publicationLifecycleE2eMessageCount($database, $portfolioA), 'Rate-limited Contact POST created a message.');
    $passed[] = 'T-E2E-CONTACT-PRG-ISOLATION';

    $profileA = loadAuthorizedPersonalInfo($database, $contextA);
    phase2Assert(is_array($profileA), 'A owner profile was unavailable for a live edit.');
    phase2AssertSame(true, updateAuthorizedPersonalInfo($database, $contextA, (int) $profileA['id'], ['hero_headline' => 'E2E A Live Update']), 'A owner live edit did not save.');
    $livePage = publicationLifecycleE2eHttpRequest($server['port'], '/public_portfolio.php?slug=e2e-tenant-a');
    phase2Assert(str_contains($livePage['body'], 'E2E A Live Update') && !str_contains($livePage['body'], 'E2E B Private'), 'Published live edit was not public immediately or leaked B.');
    phase2AssertSame(false, updateAuthorizedPersonalInfo($database, $contextA, $bResources['profile_id'], ['hero_headline' => 'Forged A Edit']), 'A edited B profile.');
    phase2AssertSame(null, findAuthorizedProject($database, $contextA, $bResources['project_ids'][0]), 'A read B owner project.');
    phase2AssertSame(null, findAuthorizedMessage($database, $contextB, $aMessageId), 'B read A owner message.');
    phase2AssertSame('e2e-tenant-b', ownedPublicLifecycleState($database, $contextB)['public_slug'], 'A action changed B publication state.');
    $passed[] = 'T-E2E-LIVE-EDIT-OWNER-ISOLATION';

    publishOwnedPortfolio($database, $contextB);
    $publicB = resolvePublicReadContext($database, 'e2e-tenant-b');
    phase2AssertSame($portfolioB, $publicB?->portfolioId, 'Published B did not resolve independently.');
    phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], '/public_media.php?slug=e2e-tenant-b&type=project&id=' . $aResources['project_ids'][0])['status'], 'B public media context exposed A project media.');
    $publishedB = ownedPublicLifecycleState($database, $contextB);

    unpublishOwnedPortfolio($database, $contextA);
    unpublishOwnedPortfolio($database, $contextA);
    $offlineA = ownedPublicLifecycleState($database, $contextA);
    phase2AssertSame(0, $offlineA['is_published'], 'Unpublish did not hide A.');
    phase2AssertSame($firstPublishedAt, $offlineA['published_at'], 'Unpublish changed first publication time.');
    phase2AssertSame($publishedB, ownedPublicLifecycleState($database, $contextB), 'A unpublish changed B publication state.');
    foreach ([
        '/public_portfolio.php?slug=e2e-tenant-a',
        '/public_media.php?slug=e2e-tenant-a&type=profile',
        '/public_media.php?slug=e2e-tenant-a&type=project&id=' . $aResources['project_ids'][0],
        '/public_projects_json.php?slug=e2e-tenant-a',
    ] as $path) {
        phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], $path)['status'], 'Unpublished A endpoint remained available: ' . $path);
    }
    phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], $contactPath, 'POST', ['name' => 'Synthetic Sender', 'email' => 'sender@example.test', 'message' => 'Denied.'])['status'], 'Unpublished A accepted Contact.');
    phase2AssertSame(1, publicationLifecycleE2eMessageCount($database, $portfolioA), 'Unpublished Contact changed message rows.');
    phase2Assert(is_array(loadAuthorizedPersonalInfo($database, $contextA)) && listAuthorizedProjects($database, $contextA) !== [], 'Authorized owner lost private Preview data after unpublish.');
    ob_start();
    renderOwnerPublicationPresentation($offlineA, ownerPublicationPublicUrl($offlineA));
    $offlineMarkup = (string) ob_get_clean();
    phase2Assert(str_contains($offlineMarkup, 'Your public link is offline') && !str_contains($offlineMarkup, 'View Portfolio'), 'Offline state offered a misleading View action.');
    publicationLifecycleE2eExpectException(
        static fn (): string => setOwnedPublicSlug($database, $contextA, 'e2e-a-renamed'),
        'Permanent A slug changed after unpublish.',
        PublicLifecycleConflictException::class,
    );
    $userD = publicationLifecycleE2eCreateUser($database, 'tenant-d');
    $portfolioD = publicationLifecycleE2eCreatePortfolio($database, $userD);
    publicationLifecycleE2eExpectException(
        static fn (): string => setOwnedPublicSlug($database, publicationLifecycleE2eContext($userD, $portfolioD), 'e2e-tenant-a'),
        'Another never-published tenant claimed A permanent slug.',
        PublicLifecycleConflictException::class,
    );
    $passed[] = 'T-E2E-UNPUBLISH-PERMANENCE';

    publishOwnedPortfolio($database, $contextA);
    $republishedA = ownedPublicLifecycleState($database, $contextA);
    phase2AssertSame(['public_slug' => 'e2e-tenant-a', 'is_published' => 1, 'published_at' => $firstPublishedAt], $republishedA, 'Republish changed permanent state.');
    phase2AssertSame($portfolioA, resolvePublicReadContext($database, 'e2e-tenant-a')?->portfolioId, 'Republished A was not public with its original slug.');
    phase2AssertSame(200, publicationLifecycleE2eHttpRequest($server['port'], '/public_portfolio.php?slug=e2e-tenant-a')['status'], 'Republished A Portfolio was unavailable.');
    phase2AssertSame(200, publicationLifecycleE2eHttpRequest($server['port'], '/public_media.php?slug=e2e-tenant-a&type=profile')['status'], 'Republished A profile media was unavailable.');
    phase2AssertSame(1, publicationLifecycleE2eMessageCount($database, $portfolioA), 'Republish changed existing A Contact association.');
    $passed[] = 'T-E2E-REPUBLISH';

    phase2AssertSame(null, resolvePublicReadContext($database, 'e2e-inactive'), 'Inactive owner Portfolio resolved publicly.');
    foreach ([
        '/public_portfolio.php?slug=e2e-inactive',
        '/public_media.php?slug=e2e-inactive&type=profile',
        '/public_projects_json.php?slug=e2e-inactive',
        '/public_contact.php?slug=e2e-inactive',
    ] as $path) {
        phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], $path)['status'], 'Inactive owner endpoint was available: ' . $path);
    }
    phase2AssertSame(404, publicationLifecycleE2eHttpRequest($server['port'], '/public_contact.php?slug=e2e-inactive', 'POST', [
        'name' => 'Synthetic Sender',
        'email' => 'sender@example.test',
        'message' => 'Inactive denial.',
    ])['status'], 'Inactive owner accepted Contact.');
    phase2AssertSame(0, publicationLifecycleE2eMessageCount($database, $portfolioInactive), 'Inactive Contact created a message.');
    $passed[] = 'T-E2E-INACTIVE-DENY';
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL publication lifecycle E2E: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    publicationLifecycleE2eStopHttpServer($server);
    $database = null;
    putenv('ATHERCAR_STORAGE_ROOT');
    putenv('RATE_LIMIT_STATE_DIR');
    if ($environment instanceof TestEnvironment) {
        try {
            $environment->tearDown();
            if (!$environment->wasTornDown()) {
                throw new RuntimeException('Run-owned lifecycle namespace remained after teardown.');
            }
        } catch (Throwable $exception) {
            fwrite(STDERR, 'FAIL publication lifecycle E2E teardown: ' . $exception->getMessage() . "\n");
            exit(1);
        }
    }
}

foreach ($passed as $name) {
    echo "PASS {$name}\n";
}
echo "PASS publication lifecycle E2E teardown\n";
