<?php

declare(strict_types=1);

final class PublicContactStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $contact = self::read('includes/public_contact.php');
        $route = self::read('public_contact.php');
        $portfolio = self::read('public_portfolio.php');
        $presentation = self::read('includes/portfolio_presentation.php');
        $vhost = self::read('docker/apache/production-vhost.conf');

        phase2Assert(str_contains($contact, 'preparePublicContactSubmission') && str_contains($contact, 'resolvePublicReadContext($database, $slug)'), 'P2J-06 must resolve the public slug for every contact submission.');
        phase2Assert(str_contains($contact, 'publicContactFormState') && str_contains($contact, 'field_errors'), 'Contact validation must expose transient field-specific recovery state.');
        phase2Assert(str_contains($contact, 'createPublicContactMessage(PDO $database, PublicReadContext $context') && str_contains($contact, "'recipient_portfolio_id' => \$context->portfolioId"), 'P2J-06 recipient authority must come from PublicReadContext only.');
        phase2Assert(!str_contains($contact, "['recipient_portfolio_id']") && !str_contains($contact, "['portfolio_id']") && !str_contains($contact, "['user_id']"), 'P2J-06 must not accept client recipient identifiers.');
        phase2Assert(str_contains($route, '$_SERVER[\'REQUEST_METHOD\'] !== \'POST\'') && str_contains($route, 'preparePublicContactSubmission') && str_contains($route, 'publicContactValidationFailure') && str_contains($route, 'consumeRateLimit') && str_contains($route, 'true, 303'), 'P2J-06 contact route must be POST-only, re-resolve, render 422 recovery state, rate-limit, and PRG.');
        phase2Assert(!str_contains($route, 'requireOwnerPortfolioContext') && !str_contains($route, '$_SESSION') && !str_contains($route, 'recipient_portfolio_id') && !str_contains($route, 'portfolio_id') && !str_contains($route, 'user_id'), 'P2J-06 public contact route must not use owner or client recipient authority.');
        phase2Assert(str_contains($portfolio, "'contact_action' => \"/p/{\$encodedSlug}/contact\"") && str_contains($portfolio, 'renderPortfolioPresentation('), 'P2J-06 public form must receive only the resolved slug contact route.');
        phase2Assert(str_contains($presentation, '<form method="post"') && str_contains($presentation, 'action="<?php echo portfolioPresentationEscape($contactAction); ?>#contact"') && str_contains($presentation, 'contact_field_errors') && str_contains($presentation, 'aria-invalid="true"') && str_contains($presentation, 'portfolio-contact-field-error') && !str_contains($presentation, 'name="recipient_portfolio_id"') && !str_contains($presentation, 'name="portfolio_id"') && !str_contains($presentation, 'name="user_id"'), 'P2J-06 shared public form must preserve accessible field errors without accepting recipient authority.');
        phase2Assert(str_contains($presentation, '<p class="portfolio-section-kicker">CONTACT</p><h2 id="contact-title">Let’s start a conversation.</h2>') && str_contains($presentation, 'Have a project, role, or collaboration opportunity in mind?') && str_contains($presentation, '<h3 id="portfolio-contact-form-title">Send a message</h3>') && str_contains($presentation, 'portfolio-preview-note--contact') && str_contains($presentation, 'portfolioPresentationSocialIcon(\'Email\')') && !str_contains($presentation, 'CONTACT / 05') && !str_contains($presentation, 'Let’s build something useful.'), 'Contact presentation must use the approved neutral copy, form heading, and private-preview status row.');
        phase2Assert(str_contains($vhost, '/contact/?$ /p_contact.php?slug=$1'), 'P2J-06 contact route is not wired through the production public vhost.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/public_contact.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/portfolio_presentation.php';

        $valid = publicContactFormState(['name' => ' Sender ', 'email' => 'sender@example.test ', 'message' => ' Hello ']);
        phase2AssertSame(['name' => 'Sender', 'email' => 'sender@example.test', 'message' => 'Hello'], $valid['values'], 'Valid Contact values must normalize before submission.');
        phase2AssertSame([], $valid['field_errors'], 'Valid Contact values must not produce field errors.');

        $invalid = publicContactFormState(['name' => '<Sender>', 'email' => 'not-an-email', 'message' => '<Message>']);
        phase2AssertSame('<Sender>', $invalid['values']['name'], 'Safe Contact name values must survive validation recovery.');
        phase2AssertSame('not-an-email', $invalid['values']['email'], 'Safe Contact email values must survive validation recovery.');
        phase2AssertSame('<Message>', $invalid['values']['message'], 'Safe Contact message values must survive validation recovery.');
        phase2Assert(isset($invalid['field_errors']['email']) && !isset($invalid['field_errors']['name'], $invalid['field_errors']['message']), 'Contact validation must map errors to the affected fields.');

        $multiple = publicContactFormState(['name' => '', 'email' => '', 'message' => '']);
        phase2AssertSame(['name', 'email', 'message'], array_keys($multiple['field_errors']), 'Multiple Contact validation failures must remain field-specific.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Contact Owner'], [], [], [
            'contact_values' => $invalid['values'],
            'contact_field_errors' => $invalid['field_errors'],
            'contact_form_error' => 'Please correct the highlighted fields and try again.',
        ]);
        $rendered = ob_get_clean();
        phase2Assert(is_string($rendered) && str_contains($rendered, 'value="&lt;Sender&gt;"') && str_contains($rendered, 'value="not-an-email"') && str_contains($rendered, '&lt;Message&gt;</textarea>') && str_contains($rendered, 'aria-invalid="true" aria-describedby="portfolio-contact-email-error"') && str_contains($rendered, 'id="portfolio-contact-email-error"') && str_contains($rendered, 'role="alert"'), 'Contact recovery rendering must escape values and expose accessible field/form errors.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Contact Owner'], [], [], [
            'contact_values' => $multiple['values'],
            'contact_field_errors' => $multiple['field_errors'],
        ]);
        $multipleRendered = ob_get_clean();
        phase2Assert(is_string($multipleRendered) && str_contains($multipleRendered, 'aria-describedby="portfolio-contact-name-error"') && str_contains($multipleRendered, 'aria-describedby="portfolio-contact-email-error"') && str_contains($multipleRendered, 'aria-describedby="portfolio-contact-message-error"'), 'Every invalid Contact field must associate its visible error text.');

        ob_start();
        renderPortfolioPresentation([
            'full_name' => 'Contact Owner',
            'linkedin_url' => 'https://linkedin.example.test/contact-owner',
            'github_url' => 'https://github.example.test/contact-owner',
            'instagram_url' => 'javascript:alert(1)',
            'website_url' => 'https://contact-owner.example.test/',
        ], [], [], ['contact_action' => '/p/contact-owner/contact']);
        $socialRendered = ob_get_clean();
        $contactSection = is_string($socialRendered) && preg_match('/<section class="portfolio-section portfolio-closing-panel portfolio-contact".*?<\/section>/s', $socialRendered, $contactSectionMatch) === 1 ? $contactSectionMatch[0] : '';
        $contactForm = preg_match('/<form method="post".*?<\/form>/s', $contactSection, $contactFormMatch) === 1 ? $contactFormMatch[0] : '';
        phase2Assert($contactSection !== '' && str_contains($contactSection, 'portfolio-contact-social') && substr_count($contactSection, '<a ') === 3 && str_contains($contactSection, 'aria-label="LinkedIn for Contact Owner"') && str_contains($contactSection, 'aria-label="GitHub for Contact Owner"') && str_contains($contactSection, 'aria-label="Website for Contact Owner"') && str_contains($contactSection, '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">') && !str_contains($contactSection, 'javascript:') && !str_contains($contactSection, '↗') && str_contains($contactForm, 'action="/p/contact-owner/contact#contact"') && str_contains($contactForm, '<span>Send message</span>') && !str_contains($contactForm, ' disabled'), 'Contact social controls must remain validated icon links while the public form remains enabled and scoped.');

        ob_start();
        renderPortfolioPresentation(['full_name' => 'Preview Contact Owner'], [], [], ['preview' => true]);
        $previewRendered = ob_get_clean();
        $previewContact = is_string($previewRendered) && preg_match('/<section class="portfolio-section portfolio-closing-panel portfolio-contact".*?<\/section>/s', $previewRendered, $previewContactMatch) === 1 ? $previewContactMatch[0] : '';
        phase2Assert($previewContact !== '' && str_contains($previewContact, 'portfolio-preview-note--contact') && str_contains($previewContact, 'role="status"') && str_contains($previewContact, 'The contact form is inactive in private preview.') && str_contains($previewContact, 'disabled aria-disabled="true"'), 'Private preview Contact must remain disabled with an intentional informational status row.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
