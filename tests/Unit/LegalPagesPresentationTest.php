<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/legal_presentation.php';

final class LegalPagesPresentationTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('PRIVACY_CONTACT_EMAIL');
    }

    public function testPublicationAndConsentRemainOffWithoutAnEffectiveDate(): void
    {
        self::assertFalse(LEGAL_PAGES_PUBLISHED);
        self::assertFalse(legalPagesPublished());
        self::assertNull(LEGAL_DOCUMENT_EFFECTIVE_DATE);
        self::assertFalse(LEGAL_CONSENT_REQUIRED);
        // A request or environment variable cannot publish legal documents.
        $_GET['legal_published'] = '1';
        putenv('LEGAL_PAGES_PUBLISHED=1');
        self::assertFalse(legalPagesPublished());
        unset($_GET['legal_published']); putenv('LEGAL_PAGES_PUBLISHED');
    }

    public function testUnpublishedLinksAndConsentProduceNoMarkup(): void
    {
        foreach (['ar', 'en'] as $locale) {
            ob_start();
            foreach (['auth', 'portfolio', 'settings'] as $context) { renderLegalLinks($locale, $context); }
            renderRegistrationLegalConsent($locale, true);
            self::assertSame('', ob_get_clean());
        }
    }

    public function testApprovedCopyHasOnlyTheOwnerAuthorizedTransformations(): void
    {
        // Hashes of the approved files after the explicitly authorized import substitutions.
        $hashes = [
            'resources/legal/privacy.ar.md' => 'e714078a1b5951e2ea2497e1718b899dfa2c5e9536a1686f718f000ee694abdd',
            'resources/legal/privacy.en.md' => '675bdd5664a2351f43d2d221c1acbff3dc39e42a1c6c26829d68cfb905d305ae',
            'resources/legal/terms.ar.md' => 'ecf2b2a09f56322c6de690623805c317dd0c80045aed946d4769bfd9826ee030',
            'resources/legal/terms.en.md' => '58cfabba44e3a9e16273c20cd00134840d00ee6991c5d83287a7d89434955c4a',
        ];
        foreach ($hashes as $path => $hash) {
            $file = dirname(__DIR__, 2) . '/' . $path;
            self::assertSame($hash, hash_file('sha256', $file), $path);
            $copy = file_get_contents($file);
            self::assertStringNotContainsString('[[', $copy);
            self::assertStringNotContainsString('⏳', $copy);
            self::assertStringNotContainsString('@', $copy);
            self::assertStringNotContainsString("\r", $copy);
        }
    }

    public function testBothLocalesRetainAllApprovedSectionsWhenContactIsConfigured(): void
    {
        putenv('PRIVACY_CONTACT_EMAIL=privacy@example.invalid');
        foreach (['ar', 'en'] as $locale) {
            self::assertCount(12, legalDocumentContent('privacy', $locale)['sections']);
            self::assertCount(13, legalDocumentContent('terms', $locale)['sections']);
        }
    }

    public function testNullContactOmitsEveryContactLineAndPreservesSectionAnchors(): void
    {
        putenv('PRIVACY_CONTACT_EMAIL');
        foreach (['ar', 'en'] as $locale) {
            foreach (['privacy', 'terms'] as $document) {
                $content = legalDocumentContent($document, $locale);
                self::assertCount($document === 'privacy' ? 12 : 13, $content['sections']);
                foreach ($content['sections'] as $section) { self::assertStringNotContainsString('{{contact}}', $section['body']); }
            }
        }
    }

    public function testInlineFormattingEscapesTextAndOnlyLinksKnownLocalDocuments(): void
    {
        putenv('PRIVACY_CONTACT_EMAIL=privacy@example.invalid');
        $html = legalInline('<script>alert(1)</script> **safe** [Privacy](/privacy) {{contact}}', 'terms', 'ar');
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('<strong>safe</strong>', $html);
        self::assertStringContainsString('href="/privacy?lang=ar"', $html);
        self::assertStringContainsString('privacy@example.invalid', $html);
        self::assertStringNotContainsString('href="javascript:', legalInline('[bad](javascript:alert(1))', 'privacy', 'en'));
    }

    public function testUnknownDocumentCannotSelectAFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        legalDocumentContent('../legal_documents', 'en');
    }
}
