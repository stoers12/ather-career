<?php

declare(strict_types=1);

// Router for `php -S 127.0.0.1:9080 scripts/dev/oidc-mock.php` (L-15; see
// docs/LOCAL_DEVELOPMENT.md). Development only: it refuses anywhere else.

require_once __DIR__ . '/oidc_mock_core.php';

function devOidcMockJson(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

function devOidcMockEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

if (PHP_SAPI !== 'cli-server' || !devOidcMockEnabled()) {
    http_response_code(404);
    exit;
}

try {
    $configuration = devOidcMockConfiguration();
    if (!is_dir($configuration['state_dir'])) {
        mkdir($configuration['state_dir'], 0700, true);
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if ($method === 'GET' && $path === '/.well-known/openid-configuration') {
        devOidcMockJson(200, devOidcMockDiscovery($configuration['issuer']));
    }
    if ($method === 'GET' && $path === '/jwks') {
        devOidcMockJson(200, devOidcMockJwks(devOidcMockSigningKey($configuration['state_dir'])));
    }
    if ($method === 'POST' && ($path === '/token' || $path === '/oauth/token')) {
        $form = $_POST;
        if ($form === [] && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $form = is_array($decoded) ? $decoded : [];
        }
        $result = devOidcMockToken($configuration, $form);
        devOidcMockJson($result['status'], $result['body']);
    }
    if ($method === 'DELETE' && preg_match('#^/api/v2/users/([^/]+)$#', $path, $match) === 1) {
        http_response_code(devOidcMockDeleteUser($configuration, (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $match[1]));
        exit;
    }
    if ($method === 'GET' && $path === '/v2/logout') {
        header('Location: ' . devOidcMockLogoutTarget($configuration, $_GET['returnTo'] ?? null), true, 302);
        exit;
    }
    if ($path === '/authorize' && in_array($method, ['GET', 'POST'], true)) {
        $source = $method === 'POST' ? $_POST : $_GET;
        $request = devOidcMockAuthorizationRequest($configuration, $source);
        if ($method === 'POST') {
            $separator = str_contains($configuration['redirect_uri'], '?') ? '&' : '?';
            if (($_POST['decision'] ?? null) === 'deny') {
                $target = $configuration['redirect_uri'] . $separator . http_build_query(['error' => 'access_denied', 'state' => $request['state']]);
            } else {
                $code = devOidcMockIssueCode($configuration, $request, devOidcMockSubject($_POST['subject'] ?? null, $_POST['custom_subject'] ?? null));
                $target = $configuration['redirect_uri'] . $separator . http_build_query(['code' => $code, 'state' => $request['state']]);
            }
            header('Location: ' . $target, true, 302);
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $hidden = '';
        foreach (['response_type', 'client_id', 'redirect_uri', 'code_challenge_method', 'state', 'nonce', 'code_challenge'] as $field) {
            $hidden .= '<input type="hidden" name="' . $field . '" value="' . devOidcMockEscape((string) $source[$field]) . '">';
        }
        $choices = '';
        foreach (DEV_OIDC_MOCK_USERS as $subject => $user) {
            $choices .= '<label><input type="radio" name="subject" value="' . devOidcMockEscape($subject) . '"' . ($choices === '' ? ' checked' : '') . '> '
                . devOidcMockEscape($user['label']) . ' <code>' . devOidcMockEscape($subject) . '</code></label><br>';
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Development sign-in (not Auth0)</title></head><body style="font-family:system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem">'
            . '<h1>Development sign-in</h1><p><strong>This is the local Auth0 mock (L-15).</strong> It exists only in development.</p>'
            . '<form method="post" action="/authorize">' . $hidden . $choices
            . '<p><label>Or another subject: <input name="custom_subject" placeholder="dev|alice"></label></p>'
            . '<button type="submit" name="decision" value="allow">Sign in</button> <button type="submit" name="decision" value="deny">Cancel</button>'
            . '</form></body></html>';
        exit;
    }
    devOidcMockJson(404, ['error' => 'not_found']);
} catch (DevOidcMockException $exception) {
    devOidcMockJson(400, ['error' => 'invalid_request', 'error_description' => $exception->getMessage()]);
}
