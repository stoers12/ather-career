<?php

declare(strict_types=1);

/**
 * Focused production-route integration test. It is an HTTP client only:
 * Apache creates the authenticated sessions and curl retains them in the
 * task-owned cookie jars. Docker lifecycle remains outside this test.
 */
final class EvidenceHubRecommendationActionHttpMySqlTest
{
    private const ROUTE = '/owner/evidence-hub';
    private const SNOOZE_SECONDS = 1209600;

    private PDO $database;
    private string $baseUrl;
    /** @var array{a:string,b:string,invalid:string} */
    private array $cookieJars;
    private int $positiveAssertions = 0;
    private int $negativeAssertions = 0;
    private int $disclosureAssertions = 0;
    /** @var array{user_a:int,portfolio_a:int,user_b:int,portfolio_b:int} */
    private array $fixture;
    /** @var list<string> */
    private array $privateMarkers = [];
    public static function seedSyntheticOwners(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_recommendations.php';
        $test = new self();
        $test->configure(false);
        $test->seed();
    }

    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_recommendations.php';
        $test = new self();
        $test->configure(true);
        $test->loadFixture();
        $test->anonymousRouteContract();
        $test->fabricatedCookieDenial();
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

    private function configure(bool $requireCookieJars): void
    {
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A', 'EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B'] as $name) {
            $value = getenv($name);
            if (!is_string($value) || $value === '') {
                throw new RuntimeException('HTTP action test configuration is incomplete.');
            }
        }
        $databaseName = (string) getenv('DB_NAME');
        if (preg_match('/^ather_career_test_[a-f0-9]{24}$/', $databaseName) !== 1) {
            throw new RuntimeException('HTTP action test refuses a non-disposable database.');
        }
        $this->baseUrl = rtrim((string) (getenv('EVIDENCE_HUB_HTTP_ACTION_BASE_URL') ?: ''), '/');
        if ($requireCookieJars && $this->baseUrl !== 'http://127.0.0.1') {
            throw new RuntimeException('HTTP action test requires the isolated loopback Apache route.');
        }
        if ($requireCookieJars) {
            $this->cookieJars = [];
            foreach (['a', 'b', 'invalid'] as $owner) {
                $name = 'EVIDENCE_HUB_HTTP_ACTION_COOKIE_JAR_' . strtoupper($owner);
                $jar = getenv($name);
                if (!is_string($jar) || !str_starts_with($jar, '/tmp/bridge/') || !is_file($jar) || !is_readable($jar) || !is_writable($jar)) {
                    throw new RuntimeException('HTTP action test cookie jar is unavailable.');
                }
                $this->cookieJars[$owner] = $jar;
            }
            if (!is_executable('/usr/bin/curl')) {
                throw new RuntimeException('HTTP action test requires the maintained curl client.');
            }
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
        $issuer = getenv('EXPECTED_OIDC_ISSUER');
        if (!is_string($issuer) || $issuer === '') throw new RuntimeException('synthetic Web-SAPI issuer is unavailable');
        $users = $this->database->prepare('INSERT INTO users (id, oidc_issuer, oidc_subject, account_status, authz_version) VALUES (:id, :issuer, :subject, \'active\', 1)');
        foreach ([$firstUser => (string) getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A'), $secondUser => (string) getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B')] as $id => $subject) {
            $users->execute(['id' => $id, 'issuer' => $issuer, 'subject' => $subject]);
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

    private function loadFixture(): void
    {
        $issuer = getenv('EXPECTED_OIDC_ISSUER');
        if (!is_string($issuer) || $issuer === '') throw new RuntimeException('synthetic Web-SAPI issuer is unavailable');
        $users = $this->database->prepare('SELECT id, oidc_subject FROM users WHERE oidc_issuer = :issuer AND oidc_subject IN (:subject_a, :subject_b) ORDER BY id');
        $users->execute(['issuer' => $issuer, 'subject_a' => (string) getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A'), 'subject_b' => (string) getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B')]);
        $rows = $users->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 2) throw new RuntimeException('synthetic Web-SAPI owners are unavailable');
        $ids = [];
        foreach ($rows as $row) $ids[(string) $row['oidc_subject']] = (int) $row['id'];
        $userA = $ids[(string) getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A')] ?? 0;
        $userB = $ids[(string) getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B')] ?? 0;
        $portfolio = $this->database->prepare('SELECT id FROM portfolios WHERE owner_user_id = :user_id LIMIT 1');
        $portfolio->execute(['user_id' => $userA]); $portfolioA = (int) $portfolio->fetchColumn();
        $portfolio->execute(['user_id' => $userB]); $portfolioB = (int) $portfolio->fetchColumn();
        if ($userA < 1 || $userB < 1 || $portfolioA < 1 || $portfolioB < 1) throw new RuntimeException('synthetic Web-SAPI portfolios are unavailable');
        $this->fixture = ['user_a' => $userA, 'portfolio_a' => $portfolioA, 'user_b' => $userB, 'portfolio_b' => $portfolioB];
        $this->privateMarkers = [(string) getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_A'), (string) getenv('EVIDENCE_HUB_HTTP_ACTION_SUBJECT_B'), 'Synthetic.', (string) $userA, (string) $portfolioA, '/var/lib/ather-career/storage'];
    }

    private function anonymousRouteContract(): void
    {
        $response = $this->request('GET', self::ROUTE);
        $this->assertPrivate($response, 303);
        self::assertSame('/owner_login.php', $response['headers']['location'] ?? '', 'anonymous route redirect must use the canonical origin-relative login target');
        $this->positiveAssertions += 3;
        $this->pass('anonymous route contract');
    }

    private function fabricatedCookieDenial(): void
    {
        $response = $this->request('GET', self::ROUTE, [], $this->ownerJar('invalid'));
        $this->assertPrivate($response, 303);
        self::assertSame('/owner_login.php', $response['headers']['location'] ?? '', 'fabricated cookie bypassed the owner login boundary');
        $this->negativeAssertions += 2;
        $this->pass('fabricated-cookie denial');
    }

    private function authenticatedRouteContract(): void
    {
        $session = $this->ownerJar('a');
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
        $session = $this->ownerJar('a');
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
        $session = $this->ownerJar('a');
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
        $session = $this->ownerJar('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $fields = $form['fields']; $fields['csrf_token'] = str_repeat('0', 64);
        $this->reject('invalid CSRF', $this->request('POST', self::ROUTE, $fields, $session), $this->tenantRowCount('a'), 403);
    }

    private function invalidRequestShape(): void
    {
        $session = $this->ownerJar('a');
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
        $session = $this->ownerJar('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $malformed = $form['fields']; $malformed['action_token'] = 'invalid';
        $this->reject('invalid token', $this->request('POST', self::ROUTE, $malformed, $session), $this->tenantRowCount('a'), 409, false);
        $unknown = $form['fields']; $unknown['action_token'] = str_repeat('A', 43);
        $this->reject('invalid token', $this->request('POST', self::ROUTE, $unknown, $session), $this->tenantRowCount('a'), 409, false);
        $this->pass('invalid token');
    }

    private function replayRejection(): void
    {
        $session = $this->ownerJar('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $this->assertPrg($this->request('POST', self::ROUTE, $form['fields'], $session));
        $this->reject('replay rejection', $this->request('POST', self::ROUTE, $form['fields'], $session), $this->tenantRowCount('a'));
    }

    private function staleRejection(bool $emit = true): void
    {
        $session = $this->ownerJar('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], 'snooze');
        $this->database->prepare('UPDATE projects SET problem_statement = :value WHERE portfolio_id = :portfolio_id')->execute(['value' => 'changed', 'portfolio_id' => $this->fixture['portfolio_a']]);
        $this->reject('stale rejection', $this->request('POST', self::ROUTE, $form['fields'], $session), $this->tenantRowCount('a'), 409, $emit);
    }

    private function wrongActionRejection(): void
    {
        $session = $this->ownerJar('a');
        foreach ([['snooze', 'dismiss'], ['dismiss', 'snooze']] as [$issued, $submitted]) {
            $form = $this->form($this->request('GET', self::ROUTE, [], $session)['body'], $issued);
            $fields = $form['fields']; $fields['action'] = $submitted;
            $this->reject('wrong-action rejection', $this->request('POST', self::ROUTE, $fields, $session), $this->tenantRowCount('a'), 409, false);
        }
        $this->pass('wrong-action rejection');
    }

    private function wrongContextRejection(): void
    {
        $ownerA = $this->ownerJar('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $ownerA)['body'], 'snooze');
        $ownerB = $this->ownerJar('b');
        $ownerBForm = $this->form($this->request('GET', self::ROUTE, [], $ownerB)['body'], 'snooze');
        $fields = $form['fields']; $fields['csrf_token'] = $ownerBForm['fields']['csrf_token'];
        $this->reject('wrong-context rejection', $this->request('POST', self::ROUTE, $fields, $ownerB), $this->tenantRowCount('a'));
    }

    private function crossTenantDenial(): void
    {
        $ownerA = $this->ownerJar('a');
        $form = $this->form($this->request('GET', self::ROUTE, [], $ownerA)['body'], 'dismiss');
        $ownerB = $this->ownerJar('b');
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
        $session = $this->ownerJar('a');
        $response = $this->request('GET', self::ROUTE, [], $session);
        $forms = $this->forms($response['body']);
        $candidate = $this->firstCurrentCandidate();
        self::assert(count($forms) >= 2, 'page did not issue enough action forms');
        foreach ($forms as $form) {
            $this->assertTokenContext($response['body'], $form['fields']['action_token']);
        }
        foreach (array_merge($this->privateMarkers, [$candidate['recommendation_key'], $candidate['evidence_fingerprint'], $candidate['target_ref'], (string) getenv('EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY'), (string) getenv('DB_PASSWORD')]) as $forbidden) {
            self::assert($forbidden === '' || !str_contains($response['body'], $forbidden), 'private fixture data was rendered');
        }
        $this->disclosureAssertions += count($forms) + count($this->privateMarkers) + 6;
        $this->pass('contextual disclosure');
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private function request(string $method, string $path, array $fields = [], ?string $jar = null): array
    {
        $headerPath = tempnam(sys_get_temp_dir(), 'evidence-hub-http-header-');
        $bodyPath = tempnam(sys_get_temp_dir(), 'evidence-hub-http-body-');
        if ($headerPath === false || $bodyPath === false) throw new RuntimeException('HTTP action test response files are unavailable.');
        $command = ['/usr/bin/curl', '--silent', '--show-error', '--request', $method, '--header', 'Accept: text/html', '--max-redirs', '0', '--connect-timeout', '10', '--dump-header', $headerPath, '--output', $bodyPath, '--write-out', '%{http_code}'];
        if ($jar !== null) array_push($command, '--cookie', $jar, '--cookie-jar', $jar);
        if ($fields !== []) array_push($command, '--header', 'Content-Type: application/x-www-form-urlencoded', '--data', http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
        $process = proc_open(array_merge($command, [$this->baseUrl . $path]), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { @unlink($headerPath); @unlink($bodyPath); throw new RuntimeException('HTTP action curl client could not start.'); }
        $status = trim((string) stream_get_contents($pipes[1]));
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
        $raw = file($headerPath, FILE_IGNORE_NEW_LINES);
        $body = file_get_contents($bodyPath);
        @unlink($headerPath); @unlink($bodyPath);
        if ($exit !== 0 || !preg_match('/^\d{3}$/', $status)) throw new RuntimeException('HTTP action test curl request failed' . ($error === '' ? '.' : '.'));
        $parsed = [];
        foreach (array_slice(is_array($raw) ? $raw : [], 1) as $header) {
            $pair = explode(':', $header, 2);
            if (count($pair) === 2) $parsed[strtolower(trim($pair[0]))] = trim($pair[1]);
        }
        return ['status' => (int) $status, 'headers' => $parsed, 'body' => is_string($body) ? $body : ''];
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

    private function ownerJar(string $owner): string
    {
        if (!array_key_exists($owner, $this->cookieJars)) throw new RuntimeException('HTTP action test selected an unknown cookie jar.');
        return $this->cookieJars[$owner];
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
