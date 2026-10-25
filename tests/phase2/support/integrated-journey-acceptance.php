<?php
declare(strict_types=1);

// Synthetic identity/policies only: reuse the guarded CLI source-copy fixture.
require_once __DIR__ . '/journey-routing-fixtures.php';
require_once __DIR__ . '/completion-fixtures.php';
require_once __DIR__ . '/../../../includes/onboarding_drafts.php';

function routingProjectRead(PDO $database, \GuzzleHttp\Client $browser, string $path, int $status = 200, ?string $destination = null): string
{
    $before = routingRows($database);
    $response = $browser->get($path);
    routingAssert($response->getStatusCode() === $status && $response->getHeaderLine('Location') === ($destination ?? ''), 'Project invitation GET lost its authorized destination: ' . $path);
    routingAssert(str_contains($response->getHeaderLine('Cache-Control'), 'no-store'), 'Project invitation GET lost private caching.');
    routingAssert($before === routingRows($database), 'Project invitation GET changed projects or protected rows: ' . $path);
    return (string) $response->getBody();
}

function routingProjectInvitation(PDO $database, \GuzzleHttp\Client $browser, int $portfolioId): void
{
    $before = routingRows($database);
    routingAssert((int) $database->query('SELECT COUNT(*) FROM projects WHERE portfolio_id=' . $portfolioId)->fetchColumn() === 0, 'Completion must leave this owner with zero projects.');
    $check = static function (bool $hasProject) use ($database, $browser): void {
        $dashboard = new DOMDocument();
        // This acceptance checks English labels; the shared workspace defaults to Arabic.
        @$dashboard->loadHTML(routingProjectRead($database, $browser, '/owner.php?lang=en'));
        routingAssert($dashboard->documentElement->getAttribute('lang') === 'en', 'Project invitation fixture must use the English workspace.');
        $xpath = new DOMXPath($dashboard);
        $links = $xpath->query('//a[normalize-space(.)="Add project"]');
        routingAssert($links->length === 1, 'Dashboard must expose one usable ordinary Add Project action.');
        routingAssert($xpath->query('.//svg[@aria-hidden="true" and @focusable="false"]', $links->item(0))->length === 1, 'Dashboard Add Project icon must remain decorative.');
        $target = $links->item(0)->getAttribute('href');
        routingAssert($target === '/owner_projects.php?add=1', 'Dashboard Add Project lost the existing local add destination.');
        // Follow the rendered href, rather than requesting an independently constructed URL.
        $formPage = routingProjectRead($database, $browser, $target);
        $form = new DOMDocument();
        @$form->loadHTML($formPage);
        $xpath = new DOMXPath($form);
        $forms = $xpath->query('//form[@action="owner_projects.php" and translate(@method,"post","POST")="POST"][input[@name="action" and @value="add"]]');
        routingAssert($forms->length === 1 && $xpath->query('.//input[@name="id"]', $forms->item(0))->length === 0, 'Rendered invitation did not open the add-mode form.');
        routingAssert($xpath->query('.//input[@name="title" and @value=""]', $forms->item(0))->length === 1 && str_contains($formPage, 'Add New Project'), 'Add form inherited an existing project or lost its heading.');
        routingAssert($xpath->query('.//input[@name="csrf_token" and string-length(@value)=64]', $forms->item(0))->length === 1, 'Authorized add form lost its CSRF control.');
        $projects = routingProjectRead($database, $browser, '/owner_projects.php');
        foreach ([$formPage, $projects] as $page) {
            routingAssert(str_contains($page, 'Add your first project') === !$hasProject && str_contains($page, 'No projects yet') === !$hasProject, 'Projects empty-state invitation does not match the owner project rows.');
        }
    };
    $check(false);
    echo "PASS routing ONBOARD-UI-003 completed zero-project owner follows rendered Dashboard Add Project to authorized add form; GET preserves all rows\n";

    $projectId = null;
    try {
        // Disposable fixture only: never submit a project-creation POST.
        $database->prepare('INSERT INTO projects (portfolio_id,title,category,description,github_url) VALUES (?,?,?,?,?)')->execute([$portfolioId, 'Synthetic existing invitation project', 'Synthetic', 'Private acceptance fixture only', '']);
        $projectId = (int) $database->lastInsertId();
        $existing = $database->query('SELECT * FROM projects WHERE id=' . $projectId)->fetch(PDO::FETCH_ASSOC);
        $check(true);
        routingAssert($database->query('SELECT * FROM projects WHERE id=' . $projectId)->fetch(PDO::FETCH_ASSOC) === $existing, 'Invitation reads changed the existing fixture project.');
        echo "PASS routing ONBOARD-UI-003 completed existing-project owner retains ordinary Dashboard Add Project and add form without Projects empty-state invitation; all rows preserved\n";
    } finally {
        if (is_int($projectId)) { $database->prepare('DELETE FROM projects WHERE id=? AND portfolio_id=?')->execute([$projectId, $portfolioId]); }
    }
    routingAssert($before === routingRows($database), 'Project invitation fixture cleanup changed preexisting rows.');
}

function routingIntegratedJourney(PDO $database, string $subject): void
{
    global $root, $serverEnvironment;
    $before = routingRows($database);
    $originalEnvironment = $serverEnvironment;
    $originalApp = readlink($root . '/http/app');
    $userId = null;
    $private = $root . '/journey-app';
    mkdir($private, 0700);
    journeyFixtureCopy($private);
    $documents = [];
    foreach (['age', 'terms', 'privacy'] as $kind) {
        $documents[$kind] = ['language' => 'en', 'content' => 'SYNTHETIC ONLY integrated journey: ' . $kind];
    }
    file_put_contents($root . '/documents.json', json_encode($documents, JSON_THROW_ON_ERROR));
    chmod($root . '/documents.json', 0600);
    try {
        unlink($root . '/http/app');
        routingAssert(symlink($private, $root . '/http/app'), 'Integrated private source unavailable.');
        foreach (['DRAFT', 'ATTESTATION', 'COMPLETION'] as $gate) {
            $serverEnvironment['ATHERCAR_ONBOARDING_' . $gate . '_HTTP_ENABLED'] = '1';
        }
        routingStartApp('1');
        $browser = routingBrowser();
        $start = routingStart($browser);
        $response = routingCallback($browser, $start, $subject);
        routingAssert($response->getStatusCode() === 303 && $response->getHeaderLine('Location') === '/owner_onboarding_editor.php', 'Integrated registration did not reach the capable editor.');
        $state = routingSession($browser);
        $userId = $state[INTERNAL_USER_SESSION_KEY]['internal_user_id'] ?? null;
        routingAssert(is_int($userId) && ($state[OWNER_READINESS_SESSION_KEY]['internal_user_id'] ?? null) === $userId, 'Callback authority did not bind the integrated journey.');
        $session = routingCookie($browser);
        foreach (['/owner.php', '/owner_projects.php?add=1'] as $path) {
            routingProjectRead($database, $browser, $path, 303, '/owner_onboarding_editor.php');
        }
        echo "PASS routing ONBOARD-UI-003 incomplete authenticated Dashboard and direct project-add GET require the capable onboarding editor; all rows preserved\n";
        $json = static function (string $method, string $path, int $status = 200, array $options = []) use ($browser): array {
            $r = $browser->request($method, $path, $options);
            routingAssert($r->getStatusCode() === $status, 'Integrated ' . $path . ' expected ' . $status . ', got ' . $r->getStatusCode());
            routingAssert($r->getHeaderLine('Cache-Control') === 'private, no-store' && !$r->hasHeader('Location'), 'Integrated JSON lost its private response boundary.');
            return json_decode((string) $r->getBody(), true, 16, JSON_THROW_ON_ERROR);
        };
        routingAssert($browser->get('/owner_onboarding_editor.php')->getStatusCode() === 200, 'Callback session cannot open the editor.');
        $draft = $json('GET', '/owner_onboarding_draft.php');
        routingAssert($draft['draft']['version'] === 0 && $draft['draft']['fields'] === [], 'Integrated journey must start without a seeded draft.');
        $csrf = $draft['csrf_token'];
        $post = static fn(string $path, array $input, int $status = 200): array => $json('POST', $path, $status, ['headers' => ['X-CSRF-Token' => $csrf], 'json' => $input]);
        $fields = completionFields('integrated-' . bin2hex(random_bytes(8)));
        $savedIntent = null;
        foreach (onboardingDraftFields() as $section => $rules) {
            $version = $draft['draft']['version'];
            $savedIntent = ['expectedVersion' => $version, 'operationId' => $version . ':' . bin2hex(random_bytes(16)), 'section' => $section, 'fields' => (object) array_intersect_key($fields, $rules)];
            $draft = $post('/owner_onboarding_draft.php', $savedIntent);
        }
        $pausedRows = routingRows($database);
        $pause = $browser->get('/owner_onboarding.php?view=paused&lang=en');
        routingAssert($pause->getStatusCode() === 200 && str_contains((string) $pause->getBody(), 'data-journey-resume'), 'Saved registration journey cannot pause with explicit Resume.');
        $resume = $browser->get('/owner_onboarding.php?lang=en');
        routingAssert($resume->getStatusCode() === 303 && $resume->getHeaderLine('Location') === '/owner_onboarding_editor.php?lang=en', 'Explicit Resume lost current server routing.');
        routingAssert($json('GET', '/owner_onboarding_draft.php')['draft'] === $draft['draft'] && $pausedRows === routingRows($database), 'Pause/Resume changed or lost the acknowledged draft.');

        $version = $draft['draft']['version'];
        $reviewInput = ['action' => 'review', 'expectedVersion' => $version];
        $post('/owner_onboarding_completion.php', $reviewInput, 403);
        $presentation = $json('GET', '/owner_onboarding_attestation.php');
        routingAssert($presentation['evidence']['recorded'] === false && count($presentation['documents']) === 3, 'Synthetic documents must precede explicit attestation.');
        $attestation = $post('/owner_onboarding_attestation.php', ['presentation_token' => $presentation['presentation_token'], 'age_18_or_older' => true, 'terms_accepted' => true, 'privacy_notice_acknowledged' => true]);
        routingAssert($attestation['evidence']['recorded'] === true, 'Explicit attestations did not cross into completion authority.');
        $review = $post('/owner_onboarding_completion.php', $reviewInput)['review'];
        routingAssert($review['fields'] === $draft['draft']['fields'], 'Review did not use this registered User\'s exact acknowledged fields.');
        $intent = ['action' => 'complete', ...completionIntent($review)];
        $unconfirmed = $intent;
        $unconfirmed['acknowledge_private_draft'] = false;
        $post('/owner_onboarding_completion.php', $unconfirmed, 400);
        routingAssert((int) $database->query('SELECT COUNT(*) FROM portfolios WHERE owner_user_id=' . $userId)->fetchColumn() === 0, 'Review or missing confirmation created a Portfolio.');
        $receipt = $post('/owner_onboarding_completion.php', $intent)['receipt'];
        routingAssert($json('GET', '/owner_onboarding_completion.php')['receipt'] === $receipt, 'Original receipt was not recoverable.');
        $portfolioId = (int) $receipt['portfolio_id'];
        routingAssert((int) $database->query('SELECT is_published FROM portfolios WHERE id=' . $portfolioId)->fetchColumn() === 0, 'Integrated completion published the Portfolio.');
        routingAssert($browser->get('/owner.php')->getStatusCode() === 200, 'Completion receipt did not authorize Dashboard access.');
        routingProjectInvitation($database, $browser, $portfolioId);

        $edit = $browser->post('/owner_profile.php', ['form_params' => ['action' => 'save_profile', 'csrf_token' => $csrf, 'profile_id' => (string) $receipt['profile_id'], 'expected_profile_version' => '1', 'full_name' => 'Later integrated Profile']]);
        routingAssert($edit->getStatusCode() === 303 && $edit->getHeaderLine('Location') === 'owner_profile.php?saved=1', 'Later Profile HTTP save failed, status ' . $edit->getStatusCode());
        $profile = $database->query('SELECT * FROM personal_info WHERE id=' . (int) $receipt['profile_id'])->fetch(PDO::FETCH_ASSOC);
        routingAssert($profile['full_name'] === 'Later integrated Profile' && (int) $profile['profile_version'] === 2, 'Later Profile edit did not become authoritative.');
        $afterEdit = routingRows($database);
        // Remove synthetic policy availability, not any production policy or gate.
        unlink($root . '/documents.json');
        routingAssert($json('GET', '/owner_onboarding_completion.php')['receipt'] === $receipt && $post('/owner_onboarding_completion.php', $intent)['receipt'] === $receipt, 'Policy-independent receipt/retry did not preserve original completion.');
        $post('/owner_onboarding_draft.php', array_replace($savedIntent, ['expectedVersion' => $version, 'operationId' => $version . ':' . bin2hex(random_bytes(16))]), 409);
        routingAssert($browser->get('/owner.php')->getStatusCode() === 200 && $afterEdit === routingRows($database), 'Historical completion/draft requests rewrote later Profile or lost Dashboard authority.');
        routingAssert(routingCookie($browser) === $session && (routingSession($browser)[INTERNAL_USER_SESSION_KEY]['internal_user_id'] ?? null) === $userId, 'Integrated journey silently replaced callback identity/session.');
    } finally {
        global $appProcess;
        routingStop($appProcess);
        $serverEnvironment = $originalEnvironment;
        try {
            if (is_link($root . '/http/app')) { unlink($root . '/http/app'); }
            routingAssert(is_string($originalApp) && symlink($originalApp, $root . '/http/app'), 'Original test source restoration failed.');
        } finally {
            if (is_int($userId)) {
                $database->prepare('DELETE FROM user_identities WHERE user_id=?')->execute([$userId]);
                completionCleanup($database, [$userId]);
            }
        }
        // A restoration or cleanup exception must propagate before any restart.
        routingStartApp('1');
    }
    routingAssert($before === routingRows($database), 'Integrated fixture did not preserve all preexisting rows.');
    echo "PASS routing integrated SYNTHETIC registration callback to empty draft save pause resume explicit attestations review confirmation receipt Dashboard and later Profile preservation; fixture rows restored\n";
}
