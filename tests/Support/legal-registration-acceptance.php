<?php

declare(strict_types=1);

require_once __DIR__ . '/legal-page-fixtures.php';
require_once dirname(__DIR__, 2) . '/includes/registration_invitations.php';
require_once dirname(__DIR__, 2) . '/includes/legal_documents.php';

function routingLegalConsentAcceptance(PDO $database, string $prefix): void
{
    global $root;
    $before = routingRows($database);
    $copy = $root . '/legal-app';
    legalFixtureCopyApplication(dirname(__DIR__, 2), $copy);
    $entries = ['signup.php', 'signin.php', 'owner_register.php', 'owner_oidc_callback.php'];
    $originals = []; $users = [];
    foreach ($entries as $entry) {
        $file = $root . '/http/public/' . $entry;
        $originals[$file] = file_get_contents($file);
        file_put_contents($file, '<?php require ' . var_export($copy . '/handlers/' . $entry, true) . ';');
    }
    try {
        foreach (['1', 'invite'] as $mode) {
            routingStartApp($mode);
            clearRateLimit('oidc_start', '127.0.0.1');
            clearRateLimit('registration_invitation', '127.0.0.1');
            $browser = routingBrowser();
            $response = $browser->get('/signup.php?lang=en');
            $dom = new DOMDocument(); @$dom->loadHTML((string) $response->getBody()); $xpath = new DOMXPath($dom);
            $fields = [];
            /** @var DOMElement $input */
            foreach ($xpath->query('//form[@action="/owner_register.php"]//*[@name]') as $input) { $fields[$input->getAttribute('name')] = $input->getAttribute('value'); }
            routingAssert(array_keys($fields) === ($mode === 'invite' ? ['csrf_token', 'invitation_code', 'accept_legal'] : ['csrf_token', 'accept_legal']), 'Published legal form field set changed.');
            routingAssert($fields['accept_legal'] === '1' && $xpath->query('//input[@name="accept_legal" and @required]')->length === 1, 'Legal consent checkbox contract changed.');
            if ($mode === 'invite') { $fields['invitation_code'] = createRegistrationInvitations($database, 1, 1, $prefix)[0]; }
            $response = $browser->post('/owner_register.php', ['form_params' => $fields]);
            routingAssert($response->getStatusCode() === 302, 'Consent registration start failed.');
            routingAssert((routingSession($browser)[LEGAL_CONSENT_SESSION_KEY] ?? null) === LEGAL_DOCUMENT_VERSIONS, 'Fresh registration session did not remember both document versions.');
            parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $start);
            $response = routingCallback($browser, $start, $prefix . '-' . $mode);
            $id = routingSession($browser)[INTERNAL_USER_SESSION_KEY]['internal_user_id'] ?? null;
            routingAssert($response->getStatusCode() === 303 && is_int($id), 'Consenting registration did not create its authenticated user.');
            $users[] = $id;
            $statement = $database->prepare('SELECT document, version FROM policy_acceptances WHERE user_id = ? ORDER BY document'); $statement->execute([$id]);
            routingAssert($statement->fetchAll(PDO::FETCH_ASSOC) === [['document' => 'privacy', 'version' => LEGAL_DOCUMENT_VERSIONS['privacy']], ['document' => 'terms', 'version' => LEGAL_DOCUMENT_VERSIONS['terms']]], 'MySQL registration must record exactly both current documents.');
            if ($mode === 'invite') {
                $statement = $database->prepare('SELECT used_by_user_id FROM registration_invitations WHERE label = ?'); $statement->execute([$prefix]);
                routingAssert((int) $statement->fetchColumn() === $id, 'Invitation and legal consent did not commit for the same user.');
            }
        }
    } finally {
        routingStop($GLOBALS['appProcess']);
        foreach ($originals as $file => $source) { file_put_contents($file, $source); }
        foreach ($users as $id) {
            $database->prepare('DELETE FROM user_identities WHERE user_id = ?')->execute([$id]);
            $database->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        }
        $database->prepare('DELETE FROM registration_invitations WHERE label = ?')->execute([$prefix]);
    }
    routingAssert($before === routingRows($database), 'Legal acceptance fixture changed preexisting rows or left its consent records.');
    echo "PASS legal published signup exact fields, fresh remembered versions, MySQL Privacy/Terms consent rows, invite consumption in the same account and complete fixture cleanup\n";
}
