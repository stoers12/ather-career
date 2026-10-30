<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OwnerSettingsPresentationTest extends TestCase
{
    private function render(array $data): DOMXPath
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_settings_presentation.php';
        ob_start();
        try { renderOwnerSettingsPage($data); $html = (string) ob_get_contents(); }
        finally { ob_end_clean(); }
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($dom);
    }

    public function testOnlyTwoRealSectionsInBothLanguages(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_settings.php';
        foreach (['ar', 'en'] as $locale) {
            $xpath = $this->render(ownerSettingsPageData($locale));
            self::assertSame(2, $xpath->query('//nav[@class="owner-settings-nav"]/a')->length);
            foreach (['appearance', 'help'] as $id) {
                self::assertSame(1, $xpath->query('//section[@id="' . $id . '"]')->length);
                self::assertSame(1, $xpath->query('//nav/a[@href="#' . $id . '"]')->length);
            }
            self::assertSame(4, $xpath->query('//details[@class="owner-settings-question"]/summary')->length);
            self::assertSame(3, $xpath->query('//fieldset[@hidden]//input[@type="radio" and @name="appearance"]')->length);
            self::assertSame(2, $xpath->query('//div[@hidden]/select')->length);
            self::assertSame(0, $xpath->query('//form | //script | //*[@style] | //*[@disabled]')->length);
            self::assertSame(0, $xpath->query('//*[@id="notifications" or @id="account" or @id="privacy"]')->length);
            $text = $xpath->evaluate('string(//body)');
            foreach (['Notifications', 'Account & security', 'Privacy & data', 'Coming soon', 'الإشعارات', 'الحساب والأمان', 'الخصوصية والبيانات'] as $forbidden) self::assertStringNotContainsString($forbidden, $text);
            self::assertSame('English', trim($xpath->evaluate('string(//span[@class="owner-public-language"])')));
        }
    }

    public function testContractLanguageValuesAndUrlsAreEscaped(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_settings.php';
        $data = ownerSettingsPageData('en');
        $code = '"><script>language</script>';
        $url = '/owner_settings.php?lang=en&value="<script>url</script>';
        $data['languages'] = [['code' => $code, 'url' => $url, 'current' => false]];
        $xpath = $this->render($data);
        self::assertSame(0, $xpath->query('//script')->length);
        $link = $xpath->query('//div[@class="owner-language-options"]/a')->item(0);
        self::assertInstanceOf(DOMElement::class, $link);
        self::assertSame($code, $link->textContent);
        self::assertSame($code, $link->getAttribute('lang'));
        self::assertSame($url, $link->getAttribute('href'));
    }
}
