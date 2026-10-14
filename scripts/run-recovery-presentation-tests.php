<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ATHER_RECOVERY_PREVIEW', true);
$root = dirname(__DIR__);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$render = static function (string $screen, array $query) use ($root): string {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = $query;
    $recoveryFixture = $screen;
    ob_start();
    try {
        require $root . '/tests/phase2/support/recovery-preview.php';
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};

try {
    $_SESSION = ['existing_marker' => 'unchanged'];
    foreach (['forgot' => 1, 'reset' => 2] as $screen => $fieldCount) {
        foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
            $html = $render($screen, ['lang' => $locale]);
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($dom);
            $check(str_contains($html, '<html lang="' . $locale . '" dir="' . $direction . '">'), 'Locale or direction mismatch.');
            $check($xpath->query('//h1')->length === 1, 'Expected one screen heading.');
            $check($xpath->query('//form|//iframe')->length === 0, 'Recovery must not submit or embed a provider.');
            $check($xpath->query('//input')->length === $fieldCount, 'Unexpected recovery field count.');
            foreach ($xpath->query('//input') as $input) {
                $check($input->hasAttribute('readonly') && $input->getAttribute('value') === '' && !$input->hasAttribute('name'), 'Preview field must remain empty, readonly and unnamed.');
                $check($input->getAttribute('autocomplete') === 'off', 'Preview field must not request credentials.');
            }
            $action = $xpath->query('//button[contains(@class, "ap-primary")]')->item(0);
            $check($action instanceof DOMElement && $action->hasAttribute('disabled') && $action->getAttribute('type') === 'button', 'Recovery action must remain inactive.');
            $check($xpath->query('//button[not(contains(@class, "hf-theme")) and not(@disabled)]')->length === 0, 'Unexpected active action.');
            $check(str_contains($html, recoveryCopy($screen . '_footer', $locale)) && str_contains($html, recoveryCopy($screen . '_notice', $locale)), 'Preview explanation is missing.');
            $check(!str_contains($html, '&lt;br&gt;'), 'Heading line break must render as text layout.');
            $allowedLinks = ['#recovery-card', './?lang=' . $locale, 'signin.php?lang=' . $locale, ($screen === 'reset' ? 'reset' : 'forgot') . '-password.php?lang=' . ($locale === 'ar' ? 'en' : 'ar')];
            foreach ($xpath->query('//a[@href]') as $link) {
                $check(in_array($link->getAttribute('href'), $allowedLinks, true), 'Unexpected preview destination.');
            }
            $check($xpath->query('//script')->length === 1 && str_contains($html, 'landing.js?v='), 'Only the existing theme script is permitted.');
            $check($render($screen, ['lang' => $locale, 'screen' => $screen === 'reset' ? 'forgot' : 'reset', 'email' => 'someone@example.test', 'token' => 'unsafe']) === $html, 'Client claims changed the fixture.');
            $check($_SESSION === ['existing_marker' => 'unchanged'] && session_status() === PHP_SESSION_NONE, 'Fixture changed the session.');
        }
    }
    try {
        $render('unknown', []);
        throw new RuntimeException('Unknown recovery fixture accepted.');
    } catch (InvalidArgumentException) {
        ++$checks;
    }
    echo 'PASS recovery presentation boundaries: ' . $checks . " checks.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL recovery presentation: ' . $exception->getMessage() . "\n");
    exit(1);
}
