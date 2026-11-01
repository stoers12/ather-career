<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/owner_settings.php';
require_once dirname(__DIR__, 2) . '/includes/owner_settings_presentation.php';
require_once dirname(__DIR__, 2) . '/includes/owner_data_rights_presentation.php';
require_once dirname(__DIR__, 2) . '/includes/owner_data_rights_notice.php';

final class OwnerDataRightsPresentationTest extends TestCase
{
    /** @return iterable<string, array{string, bool, ?string}> */
    public static function states(): iterable
    {
        foreach (['ar', 'en'] as $locale) {
            foreach ([false, true] as $recent) {
                foreach ([null, 'reauthenticate', 'confirmation'] as $notice) {
                    yield $locale . '-' . (int) $recent . '-' . ($notice ?? 'none') => [$locale, $recent, $notice];
                }
            }
        }
    }

    /** @return array{export_url: string, reauthenticate_url: string, deletion_url: string, recently_authenticated: bool, recent_until: ?int, confirmation_phrase: string, grace_days: int, notice: ?string} */
    private function rights(bool $recent, ?string $notice): array
    {
        return ['export_url' => '/owner_data_export.php', 'reauthenticate_url' => '/owner_reauthenticate.php',
            'deletion_url' => '/owner_account_deletion.php', 'recently_authenticated' => $recent,
            'recent_until' => null, 'confirmation_phrase' => 'sami-<work>&"', 'grace_days' => 37, 'notice' => $notice];
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function document(string $html): array
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        return [$document, new DOMXPath($document)];
    }

    /** @param array<string, string> $expected */
    private function assertForm(DOMXPath $xpath, string $url, array $expected): void
    {
        $forms = $xpath->query('//form[@action="' . $url . '"]');
        self::assertCount(1, $forms);
        $form = $forms->item(0);
        self::assertInstanceOf(DOMElement::class, $form);
        self::assertSame('post', strtolower($form->getAttribute('method')));
        $actual = [];
        foreach ($xpath->query('.//input[@name]', $form) as $input) {
            self::assertInstanceOf(DOMElement::class, $input);
            self::assertArrayNotHasKey($input->getAttribute('name'), $actual);
            $actual[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        self::assertSame($expected, $actual);
    }

    #[DataProvider('states')]
    public function testSettingsContract(string $locale, bool $recent, ?string $notice): void
    {
        $_SESSION['csrf_token'] = str_repeat('a', 64);
        ob_start();
        renderOwnerSettingsPage(ownerSettingsPageData($locale) + ['data_rights' => $this->rights($recent, $notice)]);
        $html = (string) ob_get_clean();
        [$document, $xpath] = $this->document($html);
        self::assertCount(1, $xpath->query('//a[@href="#data"]'));
        self::assertCount(1, $xpath->query('//section[@id="data"]'));
        self::assertStringContainsString(ownerDataRightsCopy('title', $locale), $document->textContent);
        self::assertCount($notice === null ? 0 : 1, $xpath->query('//section[@id="data"]//*[@role="alert"]'));
        if ($notice !== null) self::assertStringContainsString(ownerDataRightsCopy($notice, $locale), $document->textContent);
        $token = ['csrf_token' => str_repeat('a', 64)];
        if (!$recent) {
            $this->assertForm($xpath, '/owner_reauthenticate.php', $token + ['return' => 'data']);
            self::assertCount(1, $xpath->query('//section[@id="data"]//form'));
        } else {
            $this->assertForm($xpath, '/owner_data_export.php', $token);
            $this->assertForm($xpath, '/owner_account_deletion.php', $token + ['action' => 'request', 'confirmation' => '']);
            self::assertCount(1, $xpath->query('//details//form[@action="/owner_account_deletion.php"]'));
            self::assertCount($notice === 'confirmation' ? 1 : 0, $xpath->query('//details[@open]'));
            self::assertSame('sami-<work>&"', $xpath->query('//label[@for="data-confirmation"]/bdi')->item(0)->textContent);
            self::assertStringContainsString('37', $document->textContent);
            self::assertStringNotContainsString('sami-<work>', $html);
            self::assertCount(1, $xpath->query('//input[@name="confirmation"][@required]'));
        }
        self::assertCount(0, $xpath->query('//*[@style or @onclick] | //script[not(@src)]'));
    }

    #[DataProvider('states')]
    public function testScheduledPageContract(string $locale, bool $recent, ?string $notice): void
    {
        $_SESSION['csrf_token'] = str_repeat('b', 64);
        $page = ['locale' => $locale, 'requested_at' => '2026-11-01T23:30:00Z', 'due_at' => '2026-12-01T23:30:00Z',
            'grace_days' => 30, 'cancel_url' => '/owner_account_deletion.php', 'export_url' => '/owner_data_export.php',
            'reauthenticate_url' => '/owner_reauthenticate.php', 'logout_url' => '/owner_logout.php',
            'recently_authenticated' => $recent, 'notice' => $notice];
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Amman');
        try {
            ob_start(); renderOwnerAccountDeletionPage($page); $html = (string) ob_get_clean();
        } finally {
            date_default_timezone_set($timezone);
        }
        [$document, $xpath] = $this->document($html);
        self::assertStringContainsString('<html lang="' . $locale . '" dir="' . ($locale === 'ar' ? 'rtl' : 'ltr') . '">', $html);
        self::assertStringContainsString(ownerDataRightsCopy('scheduled_title', $locale), $document->textContent);
        self::assertStringContainsString(ownerDataRightsCopy('hidden', $locale), $document->textContent);
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Asia/Amman', IntlDateFormatter::GREGORIAN);
        self::assertSame($formatter->format(new DateTimeImmutable($page['due_at'])), $xpath->query('//time')->item(0)->textContent);
        $time = $xpath->query('//time')->item(0);
        self::assertInstanceOf(DOMElement::class, $time);
        self::assertSame($page['due_at'], $time->getAttribute('datetime'));
        $token = ['csrf_token' => str_repeat('b', 64)];
        $this->assertForm($xpath, '/owner_account_deletion.php', $token + ['action' => 'cancel']);
        $this->assertForm($xpath, '/owner_logout.php', $token);
        $this->assertForm($xpath, $recent ? '/owner_data_export.php' : '/owner_reauthenticate.php', $recent ? $token : $token + ['return' => 'deletion']);
        self::assertCount(3, $xpath->query('//form'));
        self::assertCount($notice === 'reauthenticate' ? 1 : 0, $xpath->query('//*[@role="alert"]'));
        self::assertCount(0, $xpath->query('//aside | //*[@style or @onclick] | //script[not(@src)]'));
    }

    public function testQueryNoticesAreStrictAndLocalized(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (['signin' => 'deletion_scheduled', 'dashboard' => 'deletion_cancelled'] as $location => $value) {
                foreach ([null, '', 'unknown', '<script>alert(1)</script>', [$value], $value, $location === 'signin' ? 'deletion_cancelled' : 'deletion_scheduled'] as $input) {
                    ob_start(); renderOwnerDataRightsAccountNotice($input, $locale, $location); $html = (string) ob_get_clean();
                    if ($input !== $value) self::assertSame('', $html);
                    else {
                        self::assertStringContainsString('role="status"', $html);
                        self::assertStringContainsString(ownerDataRightsCopy($location === 'signin' ? 'scheduled_notice' : 'cancelled_notice', $locale), $html);
                    }
                }
            }
        }
    }
}
