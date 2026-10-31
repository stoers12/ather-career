<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ATHER_EMAIL_PREVIEW', true);
$root = dirname(__DIR__, 2);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$render = static function (string $screen, array $query) use ($root): string {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    http_response_code(200);
    $_GET = $query;
    $verificationFixture = $screen;
    ob_start();
    try {
        require $root . ($screen === 'signup' ? '/handlers/signup.php' : '/tests/Support/email-verification-preview.php');
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};

function checkSignupInvitationPresentation(callable $render, callable $check): void
{
    foreach (['invite', '1', '0'] as $mode) {
        putenv('ATHERCAR_REGISTRATION_ENABLED=' . $mode);
        foreach (['ar', 'en'] as $locale) {
            foreach ([null, 'invalid', ['invalid']] as $error) {
                $query = ['lang' => $locale, 'invitation' => $error, 'invitation_code' => '<script>private-code</script>'];
                $html = $render('signup', $query);
                $dom = new DOMDocument();
                @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
                $xpath = new DOMXPath($dom);
                $names = [];
                /** @var DOMElement $field */
                foreach ($xpath->query('//form//*[@name]') as $field) { $names[] = $field->getAttribute('name'); }
                $check($names === ($mode === 'invite' ? ['csrf_token', 'invitation_code'] : ($mode === '1' ? ['csrf_token'] : [])), 'Registration field set must exactly match the mode contract.');
                $input = $xpath->query('//input[@name="invitation_code"]')->item(0);
                $check(($input !== null) === ($mode === 'invite'), 'Invitation field must be invite-only.');
                $invalid = $mode === 'invite' && $error === 'invalid';
                $check($xpath->query('//*[@id="invitation-error" and @role="alert"]')->length === (int) $invalid, 'Only the exact invalid state must announce an inline error.');
                $check(!str_contains($html, 'private-code'), 'Submitted invitation must never be echoed.');
                if ($mode !== 'invite') {
                    $check($html === $render('signup', ['lang' => $locale]), 'Invitation query must not alter open or closed presentation.');
                    continue;
                }
                if (!$input instanceof DOMElement) { throw new RuntimeException('Invitation input missing.'); }
                foreach (['required' => '', 'maxlength' => '64', 'dir' => 'ltr', 'autocomplete' => 'off', 'autocapitalize' => 'characters', 'spellcheck' => 'false'] as $attribute => $value) {
                    $check($input->hasAttribute($attribute) && $input->getAttribute($attribute) === $value, 'Invitation input attribute mismatch: ' . $attribute);
                }
                $check(!$input->hasAttribute('value'), 'Invitation field must always start empty.');
                $check($xpath->query('//label[@for="invitation-code"]')->item(0)->textContent === html_entity_decode(signUpCopy('invitation_label', $locale), ENT_QUOTES, 'UTF-8'), 'Visible translated invitation label missing.');
                $check(str_contains($html, signUpCopy('invitation_format', $locale)), 'Invitation format hint missing.');
                $check($input->getAttribute('aria-describedby') === ($invalid ? 'invitation-hint invitation-error' : 'invitation-hint'), 'Hint/error must be connected to the field.');
                $check($input->hasAttribute('aria-invalid') === $invalid, 'Invalid field state mismatch.');
                if ($invalid) { $check(str_contains($html, signUpCopy('invitation_invalid', $locale)), 'Translated invitation error missing.'); }
                $check($xpath->query('//script[not(@src)]|//*[@style or @onclick]')->length === 0, 'Invitation presentation must respect CSP.');
            }
        }
    }
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    putenv('ATHERCAR_REGISTRATION_ENABLED');
}

try {
    // A fixture must not turn browser-selected claims into local authentication state.
    $_SESSION = ['existing_marker' => 'unchanged'];
    $expectedSession = $_SESSION;
    $supportedScreens = ['signup', 'pending', 'verified', 'resent', 'expired', 'error', 'disabled'];
    $screens = array_slice($argv, 1) ?: $supportedScreens;
    foreach ($screens as $screen) {
        $check(in_array($screen, $supportedScreens, true), 'Unknown requested presentation test.');
        foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
            $html = $render($screen, ['lang' => $locale]);
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($dom);
            $check(str_contains($html, '<html lang="' . $locale . '" dir="' . $direction . '">'), 'Locale/direction mismatch.');
            $check($xpath->query('//h1')->length === 1, 'A screen must have one heading.');
            $check($xpath->query('//form|//input|//iframe')->length === 0, 'No credential collection or submit surface is allowed.');
            $check(!str_contains($html, 'owner_login.php') && !str_contains($html, 'owner_onboarding.php') && !str_contains($html, 'owner_switch_account.php'), 'Presentation must not advertise authentication/mutation destinations.');
            $buttons = $xpath->query('//button[not(contains(@class, "hf-theme"))]');
            $check($buttons->length === ['signup' => 3, 'pending' => 2, 'verified' => 1, 'resent' => 2, 'expired' => 1, 'error' => 1, 'disabled' => 2][$screen], 'Unexpected action count.');
            foreach ($buttons as $button) {
                $check($button->hasAttribute('disabled') && $button->getAttribute('type') === 'button', 'Unsupported action must be natively disabled.');
                $check(!$button->hasAttribute('onclick') && !$button->hasAttribute('formaction'), 'Disabled action must have no handler/destination.');
                foreach (explode(' ', $button->getAttribute('aria-describedby')) as $id) {
                    $check($xpath->query('//*[@id="' . $id . '"]')->length === 1, 'Disabled action must have a visible explanation.');
                }
            }
            $ids = [];
            foreach ($xpath->query('//*[@id]') as $element) {
                $check(!isset($ids[$element->getAttribute('id')]), 'Duplicate element ID.');
                $ids[$element->getAttribute('id')] = true;
            }
            $check($xpath->query('//script[not(@src="/assets/js/landing.js?v=' . substr(hash_file('sha256', $root . '/public/assets/js/landing.js'), 0, 16) . '")]')->length === 0, 'Only the existing theme script is permitted.');
            $check(!str_contains($html, 'data-screen') && !str_contains($html, 'data-size') && !str_contains($html, 'toolbar'), 'Prototype inspection/state controls must be absent.');
            if ($screen === 'signup') {
                $check(str_contains($html, signInCopy('unavailable', $locale)), 'Approved registration policy must be visible.');
                $check(str_contains($html, 'href="signin.php?lang=' . $locale . '"'), 'Existing users need the supported Sign In destination.');
            } else {
                $newFixture = in_array($screen, ['resent', 'expired', 'error', 'disabled'], true);
                $check(str_contains($html, emailVerificationCopy($newFixture ? 'preview_unchanged' : 'preview', $locale)), 'Verification fixture needs an explicit no-send/no-access disclosure.');
                $check(str_contains($html, emailVerificationCopy($screen . '_heading', $locale)), 'Fixture heading must be correct.');
                if (in_array($screen, ['error', 'disabled'], true)) {
                    $check(str_contains($html, emailVerificationCopy($screen . '_copy', $locale)), 'Fixture must identify an illustrative state.');
                    $check(str_contains($html, emailVerificationCopy($screen . '_unavailable', $locale)), 'Unavailable operations must be explained.');
                    $check(str_contains($html, emailVerificationCopy('example_reference', $locale)), 'Reference code must be labelled as an example.');
                    $check($xpath->query('//*[contains(@class, "es-email")]')->length === 0, 'No real or fixture account address is needed.');
                    $check($xpath->query('//div[@class="es-actions"]/*')->length === ($screen === 'error' ? 2 : 3), 'Reference action arrangement must be retained.');
                    foreach ($xpath->query('//a[@href]') as $link) {
                        $check(in_array($link->getAttribute('href'), ['#verification-fixture', './?lang=' . $locale, '?lang=' . ($locale === 'ar' ? 'en' : 'ar'), 'signin.php?lang=' . $locale], true), 'Only safe localized navigation is permitted.');
                    }
                }
                if ($newFixture) {
                    $check(str_contains($html, 'href="signin.php?lang=' . $locale . '"'), 'Fixture needs safe localized Sign In navigation.');
                    $check(!preg_match('/\b\d{2}:\d{2}\b|setInterval|setTimeout/', $html), 'Fixture must not simulate a countdown.');
                }
            }
            $tampered = $render($screen, ['lang' => $locale, 'state' => 'verified', 'verified' => '1', 'email_verified' => 'true', 'email' => '<script>alert(1)</script>', 'screen' => 'verified']);
            $check($tampered === $html, 'Client-selected state/address must not affect rendering.');
            $_COOKIE = ['verificationFixture' => 'verified', 'email_verified' => 'true', 'ather_verified' => '1'];
            $check($render($screen, ['lang' => $locale]) === $html, 'Cookies must not select verification state.');
            $check($_SESSION === $expectedSession && session_status() === PHP_SESSION_NONE, 'Rendering must not start or mutate authentication/session state.');
        }
        $check($render($screen, ['lang' => ['en']]) === $render($screen, []), 'Structured language must safely fall back to Arabic.');
    }
    try {
        $render('unknown', []);
        throw new RuntimeException('Unknown fixture accepted.');
    } catch (InvalidArgumentException) {
        ++$checks;
    }
    if (in_array('signup', $screens, true)) { checkSignupInvitationPresentation($render, $check); }
    echo 'PASS account entry presentation boundaries: ' . $checks . " checks.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL account entry: ' . $exception->getMessage() . "\n");
    exit(1);
}
