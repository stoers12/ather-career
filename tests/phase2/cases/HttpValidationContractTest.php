<?php

declare(strict_types=1);

final class HttpValidationContractTest
{
    public static function run(TestEnvironment $environment): void
    {
        $http = self::read('includes/http.php');
        $validation = self::read('includes/validation.php');
        $ownerFlow = self::read('includes/owner_flow.php');
        $ownerActions = self::read('includes/owner_actions.php');
        $json = self::read('public_projects_json.php');

        foreach (['httpRequireMethod', 'httpRedirectTargetIsSafe', 'httpAbortHtml', 'httpJsonResponse', 'httpRegisterExceptionBoundary'] as $helper) {
            phase2Assert(str_contains($http, "function {$helper}"), "HTTP contract helper is missing: {$helper}.");
        }
        phase2Assert(str_contains($http, "header('Allow: '") && str_contains($http, 'http_response_code(405)'), 'Method contract does not emit HTTP 405 and Allow.');
        phase2Assert(str_contains($ownerFlow, 'function ownerAuthenticationRequired') && str_contains($ownerFlow, "httpRedirect('/owner_login.php')") && !str_contains($ownerFlow, "httpRedirect('owner_login.php')"), 'Unauthenticated Owner HTML workflow must redirect to the canonical origin-relative Owner login route.');
        phase2Assert(str_contains($ownerFlow, "httpRedirect('/owner_onboarding.php')") && !str_contains($ownerFlow, "httpRedirect('owner_onboarding.php')"), 'Owner onboarding redirect must remain canonical from nested protected routes.');
        phase2Assert(str_contains($ownerFlow, 'function ownerAuthorizationDenied') && str_contains($ownerFlow, 'httpAbortHtml(403'), 'Authenticated authorization denial no longer returns HTTP 403.');
        phase2Assert(str_contains($json, "httpMethodIsAllowed(['GET'])") && str_contains($json, 'httpJsonResponse(405') && str_contains($json, 'httpJsonResponse(404') && str_contains($json, 'listPublicProjectJsonPayload'), 'The active JSON read route does not keep its explicit method and missing-resource contracts.');

        foreach ([
            'index.php' => "httpRequireMethod(['GET', 'HEAD'])",
            'owner.php' => "httpRequireMethod(['GET', 'HEAD'])",
            'owner_profile.php' => "httpRequireMethod(['GET', 'HEAD', 'POST'])",
            'owner_projects.php' => "httpRequireMethod(['GET', 'HEAD', 'POST'])",
            'owner_experiences.php' => "httpRequireMethod(['GET', 'HEAD', 'POST'])",
            'owner_publication.php' => "httpRequireMethod(['GET', 'HEAD', 'POST'])",
            'owner_logout.php' => "httpRequireMethod(['POST'])",
            'public_contact.php' => "httpRequireMethod(['POST'])",
            'public_portfolio.php' => "httpRequireMethod(['GET', 'HEAD'])",
            'public_media.php' => "httpRequireMethod(['GET', 'HEAD'])",
        ] as $route => $contract) {
            $source = self::read($route);
            phase2Assert(str_contains($source, $contract) && str_contains($source, 'httpRegisterExceptionBoundary'), "{$route} is missing its HTTP contract boundary.");
        }
        phase2Assert(str_contains(self::read('ready.php'), "httpRequireMethod(['GET', 'HEAD'])"), 'Readiness no longer has an explicit safe method contract.');
        phase2Assert(str_contains(self::read('public/health.php'), "header('Allow: GET, HEAD')"), 'Standalone health endpoint no longer has an explicit safe method contract.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/http.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/validation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/public_lifecycle.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/public_contact.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/experience.php';

        phase2AssertSame('GET, POST', httpAllowHeader(['get', 'POST', 'GET']), 'Allow header must be ordered, normalized, and de-duplicated.');
        $previousServer = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        phase2Assert(httpMethodIsAllowed(['GET', 'POST']) && !httpMethodIsAllowed(['GET']), 'Method matching does not enforce the declared method set.');
        $_SERVER = $previousServer;

        foreach (['owner.php', '/p/safe-slug', '/p/safe-slug?contact=sent#contact'] as $target) {
            phase2Assert(httpRedirectTargetIsSafe($target), "Safe internal redirect was rejected: {$target}.");
        }
        foreach (['', '//example.test', 'https://example.test', "/owner.php\r\nX-Test: injected"] as $target) {
            phase2Assert(!httpRedirectTargetIsSafe($target), "Unsafe redirect was accepted: {$target}.");
        }

        phase2AssertSame(['value' => '', 'error' => 'Name is required.'], submittedStringField(['name' => " \t "], 'name', 3, 'Name', true), 'Whitespace-only required input was accepted.');
        phase2AssertSame(['value' => 'abc', 'error' => null], submittedStringField(['name' => ' abc '], 'name', 3, 'Name', true), 'Scalar input normalization or inclusive boundary validation changed.');
        phase2Assert(submittedStringField(['name' => 'abcd'], 'name', 3, 'Name', true)['error'] !== null, 'Over-boundary input was accepted.');
        phase2Assert(submittedStringField(['name' => ['not', 'scalar']], 'name', 3, 'Name', true)['error'] !== null, 'Array input was accepted for a scalar field.');
        phase2AssertSame(null, submittedEnumValue('invalid', EXPERIENCE_TYPE_VALUES), 'Invalid enum value was accepted.');
        phase2AssertSame(null, normalizePublicSlug(['not-a-slug']), 'Structured slug input was accepted.');
        phase2Assert(!isSafeHttpUrl('javascript:alert(1)') && isSafeHttpUrl('https://example.test/path'), 'URL scheme validation changed.');

        $contact = publicContactFormState(['name' => ['array'], 'email' => ' ', 'message' => '']);
        phase2AssertSame(['name', 'email', 'message'], array_keys($contact['field_errors']), 'Contact validation errors are not stable and field-keyed.');
        phase2Assert(isset(experienceSubmittedFieldErrors(['role_title' => ['array']])['role_title']), 'Experience scalar validation accepted an array.');
        $experienceErrors = experienceValidationFieldErrors([
            'experience_type' => 'invalid', 'role_title' => '', 'organization' => '', 'location' => '',
            'start_month' => '2026-13', 'end_month' => '2026-00', 'is_current' => false, 'description' => '',
        ], '2026-09');
        foreach (['experience_type', 'role_title', 'organization', 'start_month', 'end_month'] as $field) {
            phase2Assert(isset($experienceErrors[$field]), "Experience validation lacks stable {$field} field error.");
        }

        phase2Assert(str_contains($ownerActions, "'field_errors'") && str_contains($ownerActions, 'submittedStringField') && str_contains($ownerActions, 'experienceValidationFieldErrors'), 'Owner mutations do not expose stable field errors or consolidated validation.');
        phase2Assert(str_contains($http, 'reportApplicationError($exception, $route, \'unhandled_request\')') && !str_contains($http, 'getMessage()'), 'Uncaught exception boundary does not log safely or leaks exception messages.');
        phase2Assert(!str_contains($http, '$_POST') && !str_contains($http, '$_COOKIE'), 'HTTP exception boundary must not log request bodies or cookies.');

        $runtime = self::runtimePhp();
        $approvedRedirects = [
            'includes/http.php' => "header('Location: ' . \$target, true, \$status)",
            'owner_login.php' => "header('Location: ' . \$authorization['url'], true, 302)",
            'owner_switch_account.php' => "header('Location: ' . \$authorization['url'], true, 302)",
            'owner_auth_retry.php' => "header('Location: ' . \$authorization['url'], true, 302)",
        ];
        phase2Assert(substr_count($runtime, "header('Location:") === count($approvedRedirects), 'Runtime redirect inventory changed.');
        foreach ($approvedRedirects as $route => $statement) {
            $source = self::read($route);
            phase2Assert(substr_count($source, "header('Location:") === 1 && str_contains($source, $statement), "Unapproved direct redirect in {$route}.");
        }
        phase2Assert(str_contains(self::read('owner_switch_account.php'), 'auth0Discovery($configuration)')
            && str_contains(self::read('owner_switch_account.php'), "header('Location: ' . \$authorization['url'], true, 302)"), 'Existing account-switch redirect validation changed.');

        $retry = self::read('owner_auth_retry.php');
        $oidc = self::read('includes/auth0_oidc.php');
        $recovery = self::read('includes/owner_auth_recovery.php');
        $requiredOrder = [
            "httpRequireMethod(['POST'])",
            "requireValidCsrfToken(\$_POST['csrf_token'] ?? null)",
            "\$_GET !== [] || array_keys(\$_POST) !== ['csrf_token']",
            'auth0ConfigurationFromEnvironment()',
            'auth0Discovery($configuration)',
            'beginFreshOwnerAccountSelectionSession()',
            "beginAuth0Authorization(\$configuration, \$discovery['authorization_endpoint'], 'select_account')",
            "header('Location: ' . \$authorization['url'], true, 302)",
        ];
        $previousPosition = -1;
        foreach ($requiredOrder as $step) {
            $position = strpos($retry, $step);
            phase2Assert($position !== false && $position > $previousPosition, "Recovery redirect bypassed a required stage: {$step}.");
            $previousPosition = $position;
        }
        phase2Assert(str_contains($oidc, "'redirect_uri' => \$configuration->redirectUri")
            && str_contains($oidc, "auth0RequiredEnvironment('OIDC_REDIRECT_URI')")
            && str_contains($oidc, "strtolower((string) \$parts['host']) !== \$configuration->domain")
            && str_contains($oidc, 'hash_equals($configuration->issuer, (string) $payload[\'issuer\'])')
            && str_contains($recovery, 'action="/owner_auth_retry.php" method="POST"')
            && str_contains($recovery, 'href="/owner_login.php"')
            && !preg_match('/\$_(?:GET|REQUEST|SERVER)\s*\[|\$_POST\s*\[(?![\'\"]csrf_token[\'\"])/', $retry)
            && !preg_match('/returnTo|return_url|HTTP_HOST|REQUEST_SCHEME|error_description|error_uri/i', $retry . $recovery), 'Recovery redirect must use validated discovery, configured callback, and fixed local destinations only.');
        phase2Assert(str_contains(self::read('docker/apache/development-vhost.conf'), 'RewriteRule ^/p/') && str_contains(self::read('docker/apache/production-vhost.conf'), 'RewriteRule ^/p/'), 'Canonical public /p/<slug> routing is missing.');
    }

    private static function runtimePhp(): string
    {
        $contents = [];
        foreach (glob(PHASE2_REPOSITORY_ROOT . '/*.php') ?: [] as $path) {
            $source = file_get_contents($path);
            phase2Assert(is_string($source), 'Runtime source is unreadable: ' . $path);
            $contents[] = $source;
        }
        foreach (['config', 'includes', 'public'] as $directory) {
            $path = PHASE2_REPOSITORY_ROOT . '/' . $directory;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                    $source = file_get_contents($file->getPathname());
                    phase2Assert(is_string($source), 'Runtime source is unreadable: ' . $file->getPathname());
                    $contents[] = $source;
                }
            }
        }

        return implode("\n", $contents);
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
