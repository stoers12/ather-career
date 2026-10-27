<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/^ather_career_test_[a-f0-9]{24}$/D', (string) getenv('DB_NAME')) !== 1
    || getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP') !== '1') {
    throw new RuntimeException('Disposable Evidence edit HTTP test authorization is missing.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/evidence_writing_guidance.php';
require_once __DIR__ . '/evidence-hub-http-client.php';

$database = getDatabaseConnection();
$database->exec("SET time_zone = '+00:00'");
$ownerProjects = $database->prepare('SELECT projects.id FROM projects JOIN portfolios ON portfolios.id = projects.portfolio_id JOIN users ON users.id = portfolios.owner_user_id WHERE users.oidc_issuer = :issuer AND users.oidc_subject = :subject ORDER BY projects.id');
$ownerProjects->execute(['issuer' => getenv('EXPECTED_OIDC_ISSUER'), 'subject' => getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A')]);
$projectId = (int) $ownerProjects->fetchColumn();
$ownerProjects->execute(['issuer' => getenv('EXPECTED_OIDC_ISSUER'), 'subject' => getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B')]);
$foreignProjectId = (int) $ownerProjects->fetchColumn();
requireHttp($projectId > 0 && $foreignProjectId > 0, 'Disposable owner projects are missing.');
$route = '/owner/projects/' . $projectId . '/evidence';
$foreignRoute = '/owner/projects/' . $foreignProjectId . '/evidence';
$updateTitle = $database->prepare('UPDATE projects SET title = :title, description = :description WHERE id = :id');
$updateTitle->execute(['title' => '<script>unsafe</script>', 'description' => 'PRIVATE_DESCRIPTION_MARKER', 'id' => $projectId]);
$readStatement = $database->prepare('SELECT problem_statement,personal_role,measurable_outcome,updated_at FROM projects WHERE id = :id');
$read = static function () use ($readStatement, $projectId): array {
    $readStatement->execute(['id' => $projectId]);
    return $readStatement->fetch(PDO::FETCH_ASSOC);
};
$before = $read();
$anonymous = httpEvidenceRequest('GET', $route);
requireHttp(in_array($anonymous['status'], [302,303], true) && str_contains($anonymous['headers'], '/owner_login.php'), 'Anonymous Evidence edit route did not require login.');
$method = httpEvidenceRequest('PUT', $route);
requireHttp($method['status'] === 405 && str_contains($method['headers'], 'Allow: GET, POST'), 'Unsupported method was not rejected.');

$existingCookieA = getenv('EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_A');
$existingCookieB = getenv('EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_B');
$useExistingSessions = is_string($existingCookieA) && $existingCookieA !== ''
    && is_string($existingCookieB) && $existingCookieB !== '';
$cookieA = $useExistingSessions ? $existingCookieA : tempnam(sys_get_temp_dir(), 'evidence-a-');
$cookieB = $useExistingSessions ? $existingCookieB : tempnam(sys_get_temp_dir(), 'evidence-b-');
if ($cookieA === false || $cookieB === false) throw new RuntimeException('Test cookies unavailable.');
try {
    if (!$useExistingSessions) {
        foreach (['A' => $cookieA, 'B' => $cookieB] as $owner => $cookie) {
            $nonce = getenv('EVIDENCE_HUB_WEB_SESSION_BOOTSTRAP_NONCE_' . $owner);
            $bootstrap = httpEvidenceRequest('POST', '/evidence-hub-web-session-bootstrap.php', [], $cookie, ['X-Evidence-Hub-Bootstrap-Nonce: ' . $nonce]);
            requireHttp($bootstrap['status'] === 204, "Synthetic owner {$owner} could not authenticate.");
        }
    }
    $get = httpEvidenceRequest('GET', $route, null, $cookieA);
    requireHttp($get['status'] === 200 && str_contains($get['body'], 'Edit project evidence'), 'Owner GET did not render Evidence edit.');
    requireHttp(str_contains($get['headers'], 'Cache-Control: no-store') && str_contains($get['headers'], 'X-Request-ID:'), 'Private GET lacks no-store or request ID.');
    requireHttp(str_contains($get['body'], '&lt;script&gt;unsafe&lt;/script&gt;') && !str_contains($get['body'], '<script>unsafe</script>'), 'Project title was not escaped.');
    requireHttp(!str_contains($get['body'], 'PRIVATE_DESCRIPTION_MARKER') && !str_contains($get['body'], 'FIELD_NOT_AVAILABLE'), 'Private descriptions or reason codes leaked.');
    requireHttp(str_contains($get['body'], 'name="problem"') && !str_contains($get['body'], 'name="problem_statement"'), 'Logical Problem form key is wrong.');
    requireHttp(str_contains($get['body'], 'id="problem"') && str_contains($get['body'], 'id="personal-role"') && str_contains($get['body'], 'id="measurable-outcome"'), 'Field fragment targets are missing.');
    preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $get['body'], $matches);
    $csrf = $matches[1] ?? null;
    requireHttp(is_string($csrf), 'Evidence form CSRF token is missing.');
    foreach ([[$foreignRoute, $cookieA], [$route, $cookieB], ['/owner/projects/999999999/evidence', $cookieA]] as [$path, $cookie]) {
        $denied = httpEvidenceRequest('GET', $path, null, $cookie);
        requireHttp($denied['status'] === 404 && !str_contains($denied['body'], 'Foreign Project'), 'Cross-tenant or missing project access was disclosed.');
    }
    requireHttp(httpEvidenceRequest('GET', '/owner_project_evidence.php', null, $cookieA)['status'] === 404, 'Direct PHP filename was accepted as a canonical route.');
    $slash = httpEvidenceRequest('GET', $route . '/', null, $cookieA);
    requireHttp($slash['status'] === 302 && str_contains($slash['headers'], 'Location: ' . $route), 'Trailing slash canonicalization failed.');

    $fields = [
        'csrf_token' => $csrf,
        'problem' => 'Manual weekly reporting affected the team because six hours of reconciliation delayed operational decisions and monthly reviews.',
        'personal_role' => 'I designed the reporting pipeline and implemented validation checks across each imported data source.',
        'measurable_outcome' => 'Reduced weekly reporting time from six hours to one hour.',
    ];
    $csrfDenied = $fields;
    $csrfDenied['csrf_token'] = str_repeat('0', 64);
    requireHttp(httpEvidenceRequest('POST', $route, $csrfDenied, $cookieA)['status'] === 403, 'Invalid CSRF was accepted.');
    requireHttp($read() === $before, 'CSRF rejection changed evidence or timestamp.');
    $invalid = $fields;
    $invalid['problem'] = ['not text'];
    $failed = httpEvidenceRequest('POST', $route, $invalid, $cookieA);
    requireHttp($failed['status'] === 422 && str_contains($failed['body'], 'Review the highlighted fields') && str_contains($failed['body'], 'aria-invalid="true"'), 'Invalid POST did not render accessible errors.');
    requireHttp(str_contains($failed['body'], $fields['personal_role']), 'Validation failure did not preserve submitted values.');
    requireHttp($read() === $before, 'Validation failure partially saved evidence or timestamp.');
    $crossPost = httpEvidenceRequest('POST', $foreignRoute, $fields, $cookieA);
    requireHttp($crossPost['status'] === 404, 'Cross-tenant POST was not denied.');
    $saved = httpEvidenceRequest('POST', $route, $fields, $cookieA);
    requireHttp($saved['status'] === 303 && str_contains($saved['headers'], 'Location: /owner/evidence-hub#project-' . $projectId), 'Successful save did not use the approved PRG fragment.');
    $after = $read();
    requireHttp($after['problem_statement'] === $fields['problem'] && $after['personal_role'] === $fields['personal_role'] && $after['measurable_outcome'] === $fields['measurable_outcome'], 'Valid POST did not atomically save all fields.');
    requireHttp($after['updated_at'] !== $before['updated_at'], 'Meaningful evidence edit did not advance updated_at.');
    $hub = httpEvidenceRequest('GET', '/owner/evidence-hub', null, $cookieA);
    requireHttp($hub['status'] === 200 && str_contains($hub['body'], 'Evidence saved successfully') && str_contains($hub['body'], 'Undo last evidence update') && str_contains($hub['body'], 'id="project-' . $projectId . '"'), 'Immediate Undo success panel or return card is missing.');
    $savedEditor = httpEvidenceRequest('GET', $route, null, $cookieA);
    requireHttp(preg_match('/data-evidence-writing-hint="outcome_without_number"[^>]*data-hint-when="absent">/', $savedEditor['body']) === 1 && str_contains($savedEditor['body'], 'data-evidence-insert-starter'), 'Saved outcome without digits did not render the non-blocking number hint and starter controls.');
    $unchanged = $fields;
    $unchanged['problem'] = '  ' . $fields['problem'] . '  ';
    requireHttp(httpEvidenceRequest('POST', $route, $unchanged, $cookieA)['status'] === 303, 'Equivalent normalized POST did not use PRG.');
    requireHttp($read()['updated_at'] === $after['updated_at'], 'Equivalent normalized POST advanced updated_at.');
    $arabic = httpEvidenceRequest('GET', $route, null, $cookieA, [], 'ar');
    requireHttp($arabic['status'] === 200 && str_contains($arabic['body'], '<html lang="ar" dir="rtl">') && str_contains($arabic['body'], 'تعديل أدلة المشروع') && str_contains($arabic['body'], $fields['problem']), 'Arabic Owner locale did not render the localized editor with unchanged owner content.');
    $arabicInvalid = httpEvidenceRequest('POST', $route, $invalid, $cookieA, [], 'ar');
    requireHttp($arabicInvalid['status'] === 422 && str_contains($arabicInvalid['body'], 'راجع الحقول المحددة') && !str_contains($arabicInvalid['body'], 'Review the highlighted fields'), 'Arabic validation summary is not localized.');
    // Last, because it replaces the evidence: unedited starters save but never count as evidence.
    $starters = ['csrf_token' => $csrf];
    foreach (evidenceWritingGuidanceFields() as $key => $field) {
        $starters[$key] = $field['starter'];
    }
    requireHttp(httpEvidenceRequest('POST', $route, $starters, $cookieA)['status'] === 303, 'Unedited starters were not accepted as storable text.');
    $starterEditor = httpEvidenceRequest('GET', $route, null, $cookieA);
    requireHttp(substr_count($starterEditor['body'], 'evidence-hub-state--needs_attention') === 3 && !str_contains($starterEditor['body'], 'evidence-hub-state--complete'), 'Unedited starters were counted as complete evidence.');
    echo "PASS Evidence edit HTTP auth, tenant, CSRF, validation, atomic save, PRG, unchanged timestamp, and writing aids\n";
} finally {
    if (!$useExistingSessions) {
        @unlink($cookieA);
        @unlink($cookieB);
    }
}
