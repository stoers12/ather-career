<?php

declare(strict_types=1);

/**
 * Focused production-route integration test. It runs inside the disposable
 * application container so its session writer and Apache share PHP session
 * storage. Docker lifecycle remains outside this test.
 */
final class EvidenceHubRecommendationActionHttpMySqlTest
{
    private const ROUTE = '/owner/evidence-hub';
    private const SNOOZE_SECONDS = 1209600;

    private PDO $database;
    private string $baseUrl;
    private string $sessionDirectory;
    private int $positiveAssertions = 0;
    private int $negativeAssertions = 0;
    private int $disclosureAssertions = 0;
    /** @var array{user_a:int,portfolio_a:int,user_b:int,portfolio_b:int} */
    private array $fixture;
    /** @var list<string> */
    private array $privateMarkers = [];
    /** @var null|Closure(string):void */
    private ?Closure $ownerASessionConsumer = null;

    public static function run(?callable $ownerASessionConsumer = null): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_session.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_recommendations.php';
        $test = new self();
        $test->ownerASessionConsumer = $ownerASessionConsumer === null ? null : Closure::fromCallable($ownerASessionConsumer);
        $test->configure();
        $test->seed();
        $test->anonymousRouteContract();
        $test->authenticatedRouteContract();
        $test->staleRejection(false);
        $test->validSnooze();
        $test->validDismiss();
        $test->invalidCsrf();
        $test->invalidRequestShape();
        $test->invalidToken();
        $test->replayRejection();
        $test->pass('stale rejection');
        $test->wrongActionRejection();
        $test->wrongContextRejection();
        $test->crossTenantDenial();
        $test->contextualDisclosure();
        fwrite(STDOUT, "HTTP_POSITIVE_ASSERTIONS={$test->positiveAssertions}\n");
        fwrite(STDOUT, "HTTP_NEGATIVE_ASSERTIONS={$test->negativeAssertions}\n");
        fwrite(STDOUT, "HTTP_DISCLOSURE_ASSERTIONS={$test->disclosureAssertions}\n");
    }

    private function configure(): void
    {
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'EVIDENCE_HUB_HTTP_ACTION_BASE_URL', 'EVIDENCE_HUB_HTTP_ACTION_SESSION_DIR'] as $name) {
            $value = getenv($name);
            if (!is_string($value) || $value === '') {
                throw new RuntimeException('HTTP action test configuration is incomplete.');
            }
        }
        $databaseName = (string) getenv('DB_NAME');
        if (preg_match('/^ather_career_test_[a-f0-9]{24}$/', $databaseName) !== 1) {
            throw new RuntimeException('HTTP action test refuses a non-disposable database.');
        }
        $this->baseUrl = rtrim((string) getenv('EVIDENCE_HUB_HTTP_ACTION_BASE_URL'), '/');
        if ($this->baseUrl !== 'http://127.0.0.1') {
            throw new RuntimeException('HTTP action test requires the isolated loopback Apache route.');
        }
        $this->sessionDirectory = (string) getenv('EVIDENCE_HUB_HTTP_ACTION_SESSION_DIR');
        if (!is_dir($this->sessionDirectory) || !is_writable($this->sessionDirectory)) {
            throw new RuntimeException('HTTP action test session storage is unavailable.');
        }
        $this->database = new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . $databaseName . ';charset=utf8mb4',
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $this->database->exec("SET time_zone = '+00:00'");
        self::assertSame('+00:00', (string) $this->database->query('SELECT @@SESSION.time_zone')->fetchColumn(), 'verification timezone is not UTC');
    }

    private function seed(): void
    {
        $firstUser = random_int(1000000, 1500000);
        $secondUser = random_int(1600000, 2000000);
        $firstPortfolio = random_int(2100000, 2500000);
        $secondPortfolio = random_int(2600000, 3000000);
        $suffix = bin2hex(random_bytes(8));
        $this->fixture = ['user_a' => $firstUser, 'portfolio_a' => $firstPortfolio, 'user_b' => $secondUser, 'portfolio_b' => $secondPortfolio];
        $this->privateMarkers = ['h1-owner-a-' . $suffix, 'h1-owner-b-' . $suffix, 'h1-' . $suffix . '@invalid.example', 'Synthetic.', (string) $firstUser, (string) $firstPortfolio, '/var/lib/ather-career/storage'];
        $users = $this->database->prepare('INSERT INTO users (id, oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:id, :issuer, :subject, \'active\', 1)');
        foreach ([$firstUser => 'h1-owner-a-' . $suffix, $secondUser => 'h1-owner-b-' . $suffix] as $id => $subject) {
            $users->execute(['id' => $id, 'issuer' => 'https://issuer.invalid/h1', 'subject' => $subject]);
        }
        $portfolios = $this->database->prepare('INSERT INTO portfolios (id, owner_user_id, public_slug, is_published) VALUES (:id, :owner_user_id, :slug, 0)');
        $portfolios->execute(['id' => $firstPortfolio, 'owner_user_id' => $firstUser, 'slug' => 'h1-a-' . $suffix]);
        $portfolios->execute(['id' => $secondPortfolio, 'owner_user_id' => $secondUser, 'slug' => 'h1-b-' . $suffix]);
        $projects = $this->database->prepare('INSERT INTO projects (title, category, description, github_url, technologies, portfolio_id, problem_statement, personal_role, measurable_outcome) VALUES (:title, :category, :description, :github_url, :technologies, :portfolio_id, NULL, NULL, NULL)');
        $personal = $this->database->prepare('INSERT INTO personal_info (full_name, email, portfolio_id) VALUES (:full_name, :email, :portfolio_id)');
        $personal->execute(['full_name' => 'Synthetic H1 A', 'email' => 'h1-' . $suffix . '@invalid.example', 'portfolio_id' => $firstPortfolio]);
        $personal->execute(['full_name' => 'Synthetic H1 B', 'email' => null, 'portfolio_id' => $secondPortfolio]);
        foreach ([$firstPortfolio, $firstPortfolio, $firstPortfolio, $secondPortfolio] as $index => $portfolioId) {
            $projects->execute([
                'title' => 'Synthetic H1', 'category' => 'Synthetic', 'description' => 'Synthetic.', 'github_url' => 'https://example.invalid/h1',
                'technologies' => json_encode(['h1-unmapped-' . ($index === 3 ? 'b' : 'a')], JSON_THROW_ON_ERROR), 'portfolio_id' => $portfolioId,
            ]);
        }
    }

    private function anonymousRouteContract(): void
    {
        $response = $this->request('GET', self::ROUTE);
        $this->assertPrivate($response, 303);
        self::assertSame('/owner_login.php', $response['headers']['location'] ?? '', 'anonymous route redirect must use the canonical origin-relative login target');
        $this->positiveAssertions += 3;
        $this->pass('anonymous route contract');
    }

    private function authenticatedRouteContract(): void
    {
        $session = $this->ownerSession('a');
        $get = $this->request('GET', self::ROUTE, [], $session);
        $this->assertPrivate($get, 200);
        $this->form($get['body'], 'snooze');
        $head = $this->request('HEAD', self::ROUTE, [], $session);
        $this->assertPrivate($head, 200);
        self::assertSame('', $head['body'], 'HEAD returned a private body');
        $trailing = $this->request('GET', self::ROUTE . '/', [], $session);
        $this->assertPrivate($trailing, 302);
        self::assertSame(self::ROUTE, $trailing['headers']['location'] ?? '', 'trailing route redirect changed');
        $unsupported = $this->request('DELETE', self::ROUTE, [], $session);
        $this->assertPrivate($unsupported, 405);
        self::assertSame('GET, HEAD, POST', $unsupported['headers']['allow'] ?? '', 'method contract changed');
        $this->positiveAssertions += 9;
        $this->pass('authenticated route contract');
    }

    private function validSnooze(): void
    {
        $session = $this->ownerSession('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $candidate = $this->firstCurrentCandidate();
        $before = time();
        $response = $this->request('POST', self::ROUTE, $form['fields'], $session);
        $after = time();
        $this->assertPrg($response);
        $row = $this->row($candidate);
        self::assertSame('snoozed', $row['disposition'], 'snooze disposition changed');
        self::assert(is_int($row['expiry']) && $row['expiry'] >= $before + self::SNOOZE_SECONDS && $row['expiry'] <= $after + self::SNOOZE_SECONDS, 'snooze expiry is outside the HTTP time bound');
        if ($before === $after) self::assertSame($before + self::SNOOZE_SECONDS, $row['expiry'], 'snooze expiry is not exact');
        self::assertSame(0, $this->tenantRowCount('b'), 'snooze changed another tenant');
        $this->positiveAssertions += 6;
        $this->pass('valid snooze');
    }

    private function validDismiss(): void
    {
        $session = $this->ownerSession('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'dismiss');
        $candidate = $this->firstCurrentCandidate();
        $response = $this->request('POST', self::ROUTE, $form['fields'], $session);
        $this->assertPrg($response);
        $row = $this->row($candidate);
        self::assertSame('dismissed', $row['disposition'], 'dismiss disposition changed');
        self::assertSame(null, $row['expiry'], 'dismiss stored a snooze expiry');
        self::assertSame(1, (int) $row['count'], 'dismiss created disposition history');
        self::assertSame(0, $this->tenantRowCount('b'), 'dismiss changed another tenant');
        $this->positiveAssertions += 6;
        $this->pass('valid dismiss');
    }

    private function invalidCsrf(): void
    {
        $session = $this->ownerSession('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $fields = $form['fields']; $fields['csrf_token'] = str_repeat('0', 64);
        $this->reject('invalid CSRF', $this->request('POST', self::ROUTE, $fields, $session), $this->tenantRowCount('a'), 403);
    }

    private function invalidRequestShape(): void
    {
        $session = $this->ownerSession('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $missing = $form['fields']; unset($missing['action_token']);
        $this->reject('invalid request shape', $this->request('POST', self::ROUTE, $missing, $session), $this->tenantRowCount('a'), 422, false);
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $extra = $form['fields']; $extra['unexpected'] = '1';
        $this->reject('invalid request shape', $this->request('POST', self::ROUTE, $extra, $session), $this->tenantRowCount('a'), 422, false);
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $unsupported = $form['fields']; $unsupported['action'] = 'invalid';
        $this->reject('invalid request shape', $this->request('POST', self::ROUTE, $unsupported, $session), $this->tenantRowCount('a'), 422, false);
        $this->pass('invalid request shape');
    }

    private function invalidToken(): void
    {
        $session = $this->ownerSession('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $malformed = $form['fields']; $malformed['action_token'] = 'invalid';
        $this->reject('invalid token', $this->request('POST', self::ROUTE, $malformed, $session), $this->tenantRowCount('a'), 409, false);
        $unknown = $form['fields']; $unknown['action_token'] = str_repeat('A', 43);
        $this->reject('invalid token', $this->request('POST', self::ROUTE, $unknown, $session), $this->tenantRowCount('a'), 409, false);
        $this->pass('invalid token');
    }

    private function replayRejection(): void
    {
        $session = $this->ownerSession('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $this->assertPrg($this->request('POST', self::ROUTE, $form['fields'], $session));
        $this->reject('replay rejection', $this->request('POST', self::ROUTE, $form['fields'], $session), $this->tenantRowCount('a'));
    }

    private function staleRejection(bool $emit = true): void
    {
        $session = $this->ownerSession('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $this->database->prepare('UPDATE projects SET problem_statement = :value WHERE portfolio_id = :portfolio_id')->execute(['value' => 'changed', 'portfolio_id' => $this->fixture['portfolio_a']]);
        $this->reject('stale rejection', $this->request('POST', self::ROUTE, $form['fields'], $session), $this->tenantRowCount('a'), 409, $emit);
    }

    private function wrongActionRejection(): void
    {
        $session = $this->ownerSession('a');
        foreach ([['snooze', 'dismiss'], ['dismiss', 'snooze']] as [$issued, $submitted]) {
            $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], $issued);
            $fields = $form['fields']; $fields['action'] = $submitted;
            $this->reject('wrong-action rejection', $this->request('POST', self::ROUTE, $fields, $session), $this->tenantRowCount('a'), 409, false);
        }
        $this->pass('wrong-action rejection');
    }

    private function wrongContextRejection(): void
    {
        $ownerA = $this->ownerSession('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $ownerA)['body'], 'snooze');
        $ownerB = $this->ownerSession('b');
        $ownerBForm = $this->form($this->request('GET', self::ROUTE, [], $ownerB)['body'], 'snooze');
        $fields = $form['fields']; $fields['csrf_token'] = $ownerBForm['fields']['csrf_token'];
        $this->reject('wrong-context rejection', $this->request('POST', self::ROUTE, $fields, $ownerB), $this->tenantRowCount('a'));
    }

    private function crossTenantDenial(): void
    {
        $ownerA = $this->ownerSession('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $ownerA)['body'], 'dismiss');
        $ownerB = $this->ownerSession('b');
        $ownerBForm = $this->form($this->request('GET', self::ROUTE, [], $ownerB)['body'], 'dismiss');
        $fields = $form['fields']; $fields['csrf_token'] = $ownerBForm['fields']['csrf_token'];
        $beforeA = $this->tenantRowCount('a'); $beforeB = $this->tenantRowCount('b');
        $response = $this->request('POST', self::ROUTE, $fields, $ownerB);
        $this->assertRejection($response, 409);
        self::assertSame($beforeA, $this->tenantRowCount('a'), 'cross-tenant request changed Owner A');
        self::assertSame($beforeB, $this->tenantRowCount('b'), 'cross-tenant request changed Owner B');
        $this->negativeAssertions += 3;
        $this->pass('cross-tenant denial');
    }

    private function contextualDisclosure(): void
    {
        $session = $this->ownerSession('a');
        $response = $this->request('GET', self::ROUTE, [], $session);
        $forms = $this->forms($response['body']);
        $candidate = $this->firstCurrentCandidate();
        self::assert(count($forms) >= 2, 'page did not issue enough action forms');
        foreach ($forms as $form) {
            $this->assertTokenContext($response['body'], $form['fields']['action_token']);
        }
        foreach (array_merge($this->privateMarkers, [$candidate['recommendation_key'], $candidate['evidence_fingerprint'], $candidate['target_ref'], (string) getenv('EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY'), (string) getenv('DB_PASSWORD'), $session]) as $forbidden) {
            self::assert($forbidden === '' || !str_contains($response['body'], $forbidden), 'private fixture data was rendered');
        }
        $this->disclosureAssertions += count($forms) + count($this->privateMarkers) + 6;
        $this->pass('contextual disclosure');
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private function request(string $method, string $path, array $fields = [], ?string $session = null): array
    {
        $headers = ['Accept: text/html'];
        if ($session !== null) $headers[] = 'Cookie: portfolio_owner_session=' . $session;
        $options = ['method' => $method, 'ignore_errors' => true, 'max_redirects' => 0, 'timeout' => 10, 'header' => implode("\r\n", $headers)];
        if ($fields !== []) {
            $options['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
            $options['content'] = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        }
        $body = file_get_contents($this->baseUrl . $path, false, stream_context_create(['http' => $options]));
        $raw = $http_response_header ?? [];
        if (!is_array($raw) || !isset($raw[0]) || preg_match('/\s(\d{3})\s/', $raw[0], $match) !== 1) throw new RuntimeException('HTTP action test did not receive an HTTP response.');
        $parsed = [];
        foreach (array_slice($raw, 1) as $header) {
            $pair = explode(':', $header, 2);
            if (count($pair) === 2) $parsed[strtolower(trim($pair[0]))] = trim($pair[1]);
        }
        return ['status' => (int) $match[1], 'headers' => $parsed, 'body' => is_string($body) ? $body : ''];
    }

    /** @return array{fields:array{csrf_token:string,action:string,action_token:string},token_hash:string} */
    private function form(string $html, string $action): array
    {
        foreach ($this->forms($html) as $form) if ($form['fields']['action'] === $action) return $form;
        throw new RuntimeException('required action form is unavailable');
    }

    /** @return list<array{fields:array{csrf_token:string,action:string,action_token:string},token_hash:string}> */
    private function forms(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new DOMXPath($document); $result = [];
        foreach ($xpath->query('//form[@action="/owner/evidence-hub"]') ?: [] as $node) {
            $fields = [];
            foreach ($xpath->query('.//input[@type="hidden"]', $node) ?: [] as $input) {
                $name = $input->getAttribute('name'); if (in_array($name, ['csrf_token', 'action', 'action_token'], true)) $fields[$name] = $input->getAttribute('value');
            }
            if (array_keys($fields) !== ['csrf_token', 'action', 'action_token'] || !in_array($fields['action'], ['snooze', 'dismiss'], true)) continue;
            $result[] = ['fields' => $fields, 'token_hash' => hash('sha256', $fields['action_token'])];
        }
        return $result;
    }

    private function ownerSession(string $owner): string
    {
        $id = bin2hex(random_bytes(16));
        $identity = $owner === 'a' ? $this->fixture['user_a'] : $this->fixture['user_b'];
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        session_save_path($this->sessionDirectory); ini_set('session.use_strict_mode', '0'); session_name('portfolio_owner_session'); session_id($id); session_start();
        $_SESSION = [INTERNAL_USER_SESSION_KEY => ['internal_user_id' => $identity, 'authz_version' => 1, 'authenticated_at' => time(), 'last_activity_at' => time()]];
        session_write_close(); ini_set('session.use_strict_mode', '1');
        if ($owner === 'a' && $this->ownerASessionConsumer !== null) {
            $consumer = $this->ownerASessionConsumer;
            $this->ownerASessionConsumer = null;
            $consumer($id);
        }
        return $id;
    }

    /** @return array{recommendation_key:string,rule_version:string,evidence_fingerprint:string,target_ref:string} */
    private function firstCurrentCandidate(): array
    {
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser($this->fixture['user_a']), $this->fixture['portfolio_a']);
        $state = buildConfiguredAuthorizedEvidenceHubOwnerRecommendationState($this->database, $context, time());
        $candidate = $state['contract']['recommendations'][0] ?? null;
        $target = is_array($candidate) ? ($candidate['target']['opaque_target_ref'] ?? null) : null;
        if (!is_array($candidate) || !is_string($candidate['recommendation_key'] ?? null) || !is_string($candidate['rule_version'] ?? null) || !is_string($candidate['evidence_fingerprint'] ?? null) || !is_string($target)) {
            throw new RuntimeException('current server recommendation is unavailable');
        }
        return ['recommendation_key' => $candidate['recommendation_key'], 'rule_version' => $candidate['rule_version'], 'evidence_fingerprint' => $candidate['evidence_fingerprint'], 'target_ref' => $target];
    }

    /** @param array{recommendation_key:string,rule_version:string,evidence_fingerprint:string,target_ref:string} $candidate
     * @return array{disposition:string,expiry:int|null,count:int}
     */
    private function row(array $candidate): array
    {
        $statement = $this->database->prepare('SELECT disposition, UNIX_TIMESTAMP(snoozed_until) AS expiry, COUNT(*) OVER () AS row_count, rule_version, evidence_fingerprint FROM recommendation_dispositions WHERE portfolio_id = :portfolio_id AND recommendation_key = :recommendation_key LIMIT 1');
        $statement->execute(['portfolio_id' => $this->fixture['portfolio_a'], 'recommendation_key' => $candidate['recommendation_key']]); $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('expected recommendation disposition is unavailable');
        self::assertSame($candidate['rule_version'], $row['rule_version'], 'stored recommendation version changed');
        self::assertSame($candidate['evidence_fingerprint'], $row['evidence_fingerprint'], 'stored recommendation fingerprint changed');
        return ['disposition' => (string) $row['disposition'], 'expiry' => $row['expiry'] === null ? null : (int) $row['expiry'], 'count' => (int) $row['row_count']];
    }

    private function tenantRowCount(string $owner): int
    {
        $portfolio = $owner === 'a' ? $this->fixture['portfolio_a'] : $this->fixture['portfolio_b'];
        $statement = $this->database->prepare('SELECT COUNT(*) FROM recommendation_dispositions WHERE portfolio_id = :portfolio_id'); $statement->execute(['portfolio_id' => $portfolio]); return (int) $statement->fetchColumn();
    }

    private function reject(string $scenario, array $response, int $before, int $status = 409, bool $emit = true): void
    {
        $this->assertRejection($response, $status); self::assertSame($before, $this->tenantRowCount('a'), $scenario . ' changed Owner A'); self::assertSame(0, $this->tenantRowCount('b'), $scenario . ' changed Owner B'); $this->negativeAssertions += 3; if ($emit) $this->pass($scenario);
    }

    private function assertPrg(array $response): void { $this->assertPrivate($response, 303); self::assertSame(self::ROUTE, $response['headers']['location'] ?? '', 'POST redirect target changed'); }
    private function assertRejection(array $response, int $status): void { $this->assertPrivate($response, $status); self::assert(str_contains($response['body'], 'Request unavailable.'), 'rejection response is not generic'); }
    private function assertPrivate(array $response, int $status): void { self::assertSame($status, $response['status'], 'HTTP status changed: expected ' . $status . ', received ' . $response['status']); self::assertSame('no-store', strtolower($response['headers']['cache-control'] ?? ''), 'private response cache policy changed'); }
    private function assertTokenContext(string $html, string $token): void { self::assert(!str_contains($this->visibleText($html), $token), 'opaque token appeared in visible text'); self::assertSame(1, substr_count($html, 'value="' . $token . '"'), 'opaque token escaped its hidden field'); $this->disclosureAssertions += 2; }
    private function visibleText(string $html): string { $document = new DOMDocument(); @$document->loadHTML($html, LIBXML_NONET); return (new DOMXPath($document))->evaluate('string(//body)'); }
    private function pass(string $scenario): void { fwrite(STDOUT, "PASS {$scenario}\n"); }
    private static function assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
    private static function assertSame(mixed $expected, mixed $actual, string $message): void { if ($expected !== $actual) throw new RuntimeException($message); }
}
