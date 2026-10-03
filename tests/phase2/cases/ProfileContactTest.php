<?php

declare(strict_types=1);

final class ProfileContactTest
{
    public static function render(array $profile, array $options = []): string
    {
        ob_start();
        renderPortfolioPresentation($profile, [], [], $options);
        return (string) ob_get_clean();
    }

    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_actions.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/public_lifecycle.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/portfolio_presentation.php';
        $profile = ['full_name' => ' Test <Owner> ', 'location' => ' Amman & Jordan ', 'phone_primary' => ' +962 79 123 4567 ', 'email' => ' contact@example.org '];
        $html = self::render($profile, ['preview' => true]);
        preg_match('/<div class="portfolio-hero-profile-details">(.*?)<\/article>/s', $html, $match);
        $card = $match[1] ?? '';
        $previous = -1;
        foreach (['Test &lt;Owner&gt;', 'Amman &amp; Jordan', 'Phone', 'Email'] as $text) {
            $position = strpos($card, $text);
            phase2Assert($position !== false && $position > $previous, 'Card identity/contact order or escaping failed.');
            $previous = $position;
        }
        phase2Assert(str_contains($card, 'href="tel:+962791234567"') && str_contains($card, 'href="mailto:contact@example.org"') && str_contains($card, 'dir="ltr"'), 'Contact link destinations or direction failed.');
        $empty = self::render(['full_name' => 'Empty', 'phone_primary' => ' ', 'email' => '', 'phone_secondary' => '123456789']);
        phase2Assert(!str_contains($empty, 'portfolio-hero-profile-contact') && !str_contains($empty, '123456789'), 'Empty fields or secondary fallback produced a contact group.');
        foreach (['call 123456789', '123;456789', '+962<script>123456789', '++123456789', '123', '123(456', '123456789 ext 4'] as $invalid) {
            phase2AssertSame('', portfolioPresentationPhoneAction($invalid), 'Invalid phone became dialable.');
        }
        phase2AssertSame('tel:0791234567', portfolioPresentationPhoneAction('(079) 123-4567'), 'Conventional local phone formatting was not supported.');
        $unsafe = self::render(['full_name' => 'Safe', 'email' => '<script>alert(1)</script>', 'phone_primary' => '"><img src=x>']);
        phase2Assert(!str_contains($unsafe, 'mailto:') && !str_contains($unsafe, 'tel:') && !str_contains($unsafe, '<script>alert') && str_contains($unsafe, '&lt;script&gt;'), 'Invalid contact values were linked or unescaped.');
        $special = self::render(['email' => 'hello?subject=bad@example.org']);
        phase2Assert(str_contains($special, 'mailto:hello%3Fsubject%3Dbad@example.org'), 'Valid email punctuation injected a mailto query.');

        // Exercise the actual scoped SQL and action functions against disposable data.
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $fields = array_values(array_diff(AUTHORIZED_PERSONAL_INFO_FIELDS, ['public_contact_visible', 'profile_image_path']));
        $columns = implode(', ', array_map(static fn ($field) => $field . ' TEXT', [...$fields, 'profile_image_path']));
        $db->exec('CREATE TABLE personal_info (id INTEGER PRIMARY KEY, portfolio_id INTEGER UNIQUE, updated_at TEXT, public_contact_visible INTEGER NOT NULL DEFAULT 0 CHECK(public_contact_visible IN (0,1)), ' . $columns . ')');
        $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, account_status TEXT); CREATE TABLE portfolios (id INTEGER PRIMARY KEY, owner_user_id INTEGER, public_slug TEXT, is_published INTEGER); CREATE TABLE skills (id INTEGER PRIMARY KEY, portfolio_id INTEGER, skill_name TEXT)");
        $db->exec("INSERT INTO users VALUES (1,'active'),(2,'active'); INSERT INTO portfolios VALUES (1,1,'contact-owner',1),(2,2,'other-owner',1)");
        $context = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 1);
        $other = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 2);
        $id = createAuthorizedPersonalInfo($db, $context, array_map('trim', $profile));
        $foreignId = createAuthorizedPersonalInfo($db, $other, ['full_name' => 'Other', 'email' => 'foreign@example.org']);
        $publicContext = resolvePublicReadContext($db, 'contact-owner');
        phase2Assert($publicContext instanceof PublicReadContext, 'Published owner did not resolve.');
        $current = loadAuthorizedPersonalInfo($db, $context);
        phase2AssertSame(0, (int) $current['public_contact_visible'], 'New profile visibility is not OFF.');
        $post = array_merge(array_fill_keys($fields, ''), array_map('trim', $profile), ['action' => 'save_profile', 'profile_id' => (string) $id]);
        foreach ([false, true, false] as $visible) {
            if ($visible) $post['public_contact_visible'] = '1'; else unset($post['public_contact_visible']);
            $result = handleAuthorizedProfileAction($db, $context, $post, [], $current, $fields, $current);
            phase2AssertSame([], $result['errors'], 'Authorized visibility save failed.');
            $current = loadAuthorizedPersonalInfo($db, $context);
            phase2AssertSame((int) $visible, (int) $current['public_contact_visible'], 'Visibility did not persist.');
            $public = loadPublicPersonalInfo($db, $publicContext);
            phase2AssertSame($visible ? 'contact@example.org' : null, $public['email'], 'Public SQL did not enforce email visibility.');
            phase2AssertSame($visible ? '+962 79 123 4567' : null, $public['phone_primary'], 'Public SQL did not enforce phone visibility.');
            foreach ([[], ['contact_form_error' => 'Please correct the fields.', 'contact_values' => ['name' => 'Visitor'], 'contact_action' => '/p/contact-owner/contact']] as $options) {
                $rendered = self::render($public, $options);
                phase2AssertSame($visible, str_contains($rendered, 'contact@example.org'), 'Public/error rendering leaked or omitted contact.');
                phase2AssertSame($visible, str_contains($rendered, 'mailto:'), 'Public/error rendering violated direct-email visibility.');
                phase2AssertSame($visible, str_contains($rendered, '+962 79 123 4567'), 'Public/error rendering violated phone visibility.');
            }
            phase2Assert(str_contains(self::render($current, ['preview' => true]), 'contact@example.org'), 'Private preview hid saved owner contacts.');
            handleAuthorizedProfileAction($db, $context, ['action' => 'add_skill', 'skill_name' => $visible ? 'On' : 'Off'], [], $current, $fields, $current);
            updateAuthorizedPersonalInfo($db, $context, $id, ['profile_image_path' => null]);
            phase2AssertSame((int) $visible, (int) loadAuthorizedPersonalInfo($db, $context)['public_contact_visible'], 'Unrelated skill/photo update changed visibility.');
        }
        $post['profile_id'] = (string) $foreignId;
        $post['public_contact_visible'] = '1';
        $result = handleAuthorizedProfileAction($db, $context, $post, [], $current, $fields, $current);
        phase2AssertSame(['Profile not found.'], $result['errors'], 'Foreign profile mutation was accepted.');
        phase2AssertSame(0, (int) loadAuthorizedPersonalInfo($db, $other)['public_contact_visible'], 'Foreign contact visibility changed.');
        $post['profile_id'] = (string) $id;
        $post['public_contact_visible'] = ['1'];
        $result = handleAuthorizedProfileAction($db, $context, $post, [], $current, $fields, $current);
        phase2Assert($result['errors'] !== [] && (int) loadAuthorizedPersonalInfo($db, $context)['public_contact_visible'] === 0, 'Malformed checkbox input changed visibility.');
        $db->exec('UPDATE portfolios SET is_published=0 WHERE id=1');
        phase2AssertSame(null, resolvePublicReadContext($db, 'contact-owner'), 'Draft Portfolio became public.');
        $db->exec("UPDATE portfolios SET is_published=1 WHERE id=1; UPDATE users SET account_status='disabled' WHERE id=1");
        phase2AssertSame(null, resolvePublicReadContext($db, 'contact-owner'), 'Disabled account became public.');
    }
}
