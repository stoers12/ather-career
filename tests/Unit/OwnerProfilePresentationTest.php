<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OwnerProfilePresentationTest extends TestCase
{
    private function render(string $locale, bool $failed = false, string $message = '', string $errorField = 'linkedin_url'): DOMXPath
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_actions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_profile_presentation.php';
        $get = $_GET;
        $session = $_SESSION ?? [];
        $previousLocale = $GLOBALS['owner_workspace_locale'] ?? null;
        $_GET = ['lang' => $locale];
        $_SESSION['csrf_token'] = str_repeat('a', 64);
        $GLOBALS['owner_workspace_locale'] = $locale;
        $unsafe = '<script>"owner" & value</script>';
        $profile = array_fill_keys(array_keys(PERSONAL_INFO_FIELD_MAX_LENGTHS), $unsafe);
        $profile += ['profile_image_path' => 'private/photo.png', 'public_email_visible' => true, 'public_phone_visible' => false];
        if ($failed) $profile['_submitted_profile_version'] = '7';
        ob_start();
        try {
            renderOwnerProfilePage($profile, 19, '7', 73, [['id' => 3, 'skill_name' => $unsafe]], $message, $failed ? ['Profile changed; reload.'] : [], $failed ? [$errorField => 'Invalid URL <error>'] : [], null, $unsafe);
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            $_GET = $get;
            $_SESSION = $session;
            if ($previousLocale === null) unset($GLOBALS['owner_workspace_locale']);
            else $GLOBALS['owner_workspace_locale'] = $previousLocale;
        }
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($dom);
    }

    public function testOneSaveFormContainsBothPanelsAndAllExistingFields(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $x = $this->render($locale);
            self::assertSame(3, $x->query('//nav[@class="owner-profile-nav"]/a')->length);
            self::assertSame(3, $x->query('//*[@data-owner-profile-panel and not(@hidden)]')->length);
            self::assertSame(1, $x->query('//form[input[@name="action" and @value="save_profile"]]')->length);
            foreach (['basics', 'about'] as $id) self::assertSame(1, $x->query('//form[@id="profile-form"]/section[@id="' . $id . '"]')->length);
            foreach (['full_name','professional_title','hero_headline','email','phone_primary','phone_secondary','location','public_email_visible','public_email_present','public_phone_visible','public_phone_present','about_me','work_description','country_code','preferred_language','professional_status','specialization','linkedin_url','github_url','instagram_url','facebook_url','website_url','expected_profile_version','profile_id','csrf_token'] as $field) {
                self::assertSame(1, $x->query('//form[@id="profile-form"]//*[@name="' . $field . '"]')->length, $field);
            }
            foreach (['save_profile', 'upload_profile_image', 'remove_profile_image'] as $action) {
                self::assertSame('7', $x->evaluate('string(//form[input[@name="action" and @value="' . $action . '"]]/input[@name="expected_profile_version"]/@value)'));
                self::assertSame('19', $x->evaluate('string(//form[input[@name="action" and @value="' . $action . '"]]/input[@name="profile_id"]/@value)'));
            }
            self::assertSame(6, $x->query('//form[@method="POST" and @action="owner_profile.php"]/input[@name="csrf_token"]')->length);
            self::assertSame(1, $x->query('//form[@enctype="multipart/form-data"]//input[@type="file" and @name="profile_image" and @required]')->length);
            self::assertSame(1, $x->query('//form[@id="profile-form"]//input[@name="public_email_visible" and @checked]')->length);
            self::assertSame(0, $x->query('//input[@name="public_phone_visible" and @checked]')->length);
            self::assertSame('73', $x->evaluate('string(//progress/@value)'));
            self::assertSame($locale === 'ar' ? 'اكتمال الملف' : 'Profile completion', $x->evaluate('string(//progress/@aria-label)'));
            self::assertSame(0, $x->query('//script | //*[@style] | //form//form')->length);
            self::assertSame(0, $x->query('//*[@class="owner-profile-save-status"]/*')->length, 'No saved indicator without a server success message.');
        }
    }

    public function testValuesAndConfirmationAreEscapedAndErrorsStayConnected(): void
    {
        $x = $this->render('en', true);
        self::assertSame(0, $x->query('//script | //error')->length);
        self::assertSame('<script>"owner" & value</script>', $x->evaluate('string(//input[@name="full_name"]/@value)'));
        self::assertSame('<script>"owner" & value</script>', $x->evaluate('string(//textarea[@name="about_me"])'));
        self::assertStringContainsString('<script>"owner" & value</script>', $x->evaluate('string(//form[input[@value="delete_skill"]]/@data-confirm)'));
        self::assertSame('true', $x->evaluate('string(//input[@name="linkedin_url"]/@aria-invalid)'));
        self::assertSame('owner-field-error-linkedin_url', $x->evaluate('string(//input[@name="linkedin_url"]/@aria-describedby)'));
        self::assertSame('Invalid URL <error>', $x->evaluate('string(//*[@id="owner-field-error-linkedin_url"])'));
        self::assertSame(1, $x->query('//*[@role="alert" and @data-error-summary]')->length);
        self::assertSame(1, $x->query('//p[@class="owner-profile-reload"]/a[@href="owner_profile.php"]')->length);
    }

    public function testInvalidFieldsOpenTheirNativeDisclosureWithoutJavascript(): void
    {
        $extra = $this->render('en', true, '', 'hero_headline');
        self::assertSame(1, $extra->query('//details[@class="owner-profile-extra" and @open]')->length);
        $photo = $this->render('ar', true, '', 'profile_image');
        self::assertSame(1, $photo->query('//details[@class="owner-profile-photo" and @open]')->length);
    }

    public function testServerSuccessMessageIsTheOnlySavedIndicator(): void
    {
        $x = $this->render('en', false, 'Personal information saved successfully.');
        self::assertStringContainsString('Personal information saved successfully.', $x->evaluate('string(//*[@class="owner-profile-save-status"])'));
        self::assertSame(1, $x->query('//p[@class="status-message" and @role="status"]')->length);
    }
}
