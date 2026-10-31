<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/legal_documents.php';

final class LegalConsentTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->database->exec(
            'CREATE TABLE policy_acceptances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                document TEXT NOT NULL,
                version TEXT NOT NULL,
                accepted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (user_id, document, version)
            )'
        );
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        putenv('PRIVACY_CONTACT_EMAIL');
    }

    /** @return list<array{document: string, version: string}> */
    private function rows(int $userId): array
    {
        $statement = $this->database->prepare('SELECT document, version FROM policy_acceptances WHERE user_id = :id ORDER BY document');
        $statement->execute(['id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testRememberedConsentRecordsEveryCurrentDocumentVersionOnce(): void
    {
        rememberRegistrationLegalConsent();
        $this->database->beginTransaction();
        recordRegistrationLegalConsent($this->database, 5);
        $this->database->commit();

        self::assertSame([
            ['document' => 'privacy', 'version' => LEGAL_DOCUMENT_VERSIONS['privacy']],
            ['document' => 'terms', 'version' => LEGAL_DOCUMENT_VERSIONS['terms']],
        ], $this->rows(5));
        self::assertArrayNotHasKey(LEGAL_CONSENT_SESSION_KEY, $_SESSION);
    }

    public function testConsentFromAnOlderVersionIsNotRecordedAsCurrent(): void
    {
        $_SESSION[LEGAL_CONSENT_SESSION_KEY] = ['privacy' => '0.9', 'terms' => '0.9'];
        $this->database->beginTransaction();
        recordRegistrationLegalConsent($this->database, 6);
        $this->database->commit();

        self::assertSame([], $this->rows(6));
        self::assertArrayNotHasKey(LEGAL_CONSENT_SESSION_KEY, $_SESSION);
    }

    public function testWithoutConsentNothingIsRecordedWhileConsentIsOptional(): void
    {
        self::assertFalse(LEGAL_CONSENT_REQUIRED, 'Flip this expectation in the PR that adds the sign-up checkbox.');
        $this->database->beginTransaction();
        recordRegistrationLegalConsent($this->database, 7);
        $this->database->commit();

        self::assertSame([], $this->rows(7));
    }

    public function testRecordingRequiresTheRegistrationTransaction(): void
    {
        rememberRegistrationLegalConsent();
        $this->expectException(LogicException::class);
        recordRegistrationLegalConsent($this->database, 8);
    }

    public function testVersionsAreWellFormed(): void
    {
        self::assertSame(['privacy', 'terms'], array_keys(LEGAL_DOCUMENT_VERSIONS));
        foreach (LEGAL_DOCUMENT_VERSIONS as $version) {
            self::assertMatchesRegularExpression('/^[0-9]{1,3}\.[0-9]{1,3}$/', $version);
        }
    }

    public function testPrivacyContactComesOnlyFromAValidSetting(): void
    {
        putenv('PRIVACY_CONTACT_EMAIL');
        self::assertNull(legalPrivacyContactEmail());
        foreach (['not-an-email', 'a@b', str_repeat('a', 250) . '@x.io'] as $invalid) {
            putenv('PRIVACY_CONTACT_EMAIL=' . $invalid);
            self::assertNull(legalPrivacyContactEmail(), $invalid);
        }
        putenv('PRIVACY_CONTACT_EMAIL=privacy@ather.example');
        self::assertSame('privacy@ather.example', legalPrivacyContactEmail());
    }
}
