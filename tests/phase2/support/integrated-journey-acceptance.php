<?php
declare(strict_types=1);

// Synthetic identity/policies only: reuse the guarded CLI source-copy fixture.
require_once __DIR__ . '/journey-routing-fixtures.php';
require_once __DIR__ . '/completion-fixtures.php';
require_once __DIR__ . '/../../../includes/onboarding_drafts.php';

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
        if (is_link($root . '/http/app')) { unlink($root . '/http/app'); }
        routingAssert(is_string($originalApp) && symlink($originalApp, $root . '/http/app'), 'Original test source restoration failed.');
        if (is_int($userId)) {
            $database->prepare('DELETE FROM user_identities WHERE user_id=?')->execute([$userId]);
            completionCleanup($database, [$userId]);
        }
        routingStartApp('1');
    }
    routingAssert($before === routingRows($database), 'Integrated fixture did not preserve all preexisting rows.');
    echo "PASS routing integrated SYNTHETIC registration callback to empty draft save pause resume explicit attestations review confirmation receipt Dashboard and later Profile preservation; fixture rows restored\n";
}
