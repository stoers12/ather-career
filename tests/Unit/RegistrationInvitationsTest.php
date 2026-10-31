<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/auth0_oidc.php';
require_once dirname(__DIR__, 2) . '/includes/registration_invitations.php';

final class RegistrationInvitationsTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->database->exec(
            'CREATE TABLE registration_invitations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code_digest TEXT NOT NULL UNIQUE,
                label TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at TEXT NOT NULL,
                used_at TEXT NULL,
                used_by_user_id INTEGER NULL,
                revoked_at TEXT NULL
            )'
        );
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        putenv('ATHERCAR_REGISTRATION_ENABLED');
    }

    public function testCodesAreWellFormedAndNormalizedLeniently(): void
    {
        $code = generateRegistrationInvitationCode();
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{12}$/', $code);
        $formatted = formatRegistrationInvitationCode($code);
        self::assertMatchesRegularExpression('/^[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{4}$/', $formatted);
        self::assertSame($code, normalizeRegistrationInvitationCode(' ' . strtolower($formatted) . ' '));
        foreach ([null, 42, '', 'ABCD-EFGH-IJKL', 'ABCD-EFGH-123', str_repeat('A', 65), "ABCD\nEFGH1234"] as $invalid) {
            self::assertNull(normalizeRegistrationInvitationCode($invalid));
        }
    }

    public function testOnlyTheDigestIsStoredAndEachCodeIsUnique(): void
    {
        $codes = createRegistrationInvitations($this->database, 25, 30, 'Beta wave 1');
        self::assertCount(25, array_unique($codes));
        $stored = $this->database->query('SELECT code_digest, label FROM registration_invitations')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(25, $stored);
        $digests = array_column($stored, 'code_digest');
        foreach ($codes as $code) {
            self::assertContains(registrationInvitationDigest((string) normalizeRegistrationInvitationCode($code)), $digests);
            self::assertStringNotContainsString(str_replace('-', '', $code), implode('', $digests));
        }
        self::assertSame(['Beta wave 1'], array_values(array_unique(array_column($stored, 'label'))));
    }

    public function testCreationRejectsOutOfRangeInput(): void
    {
        foreach ([[0, 30, null], [201, 30, null], [1, 0, null], [1, 366, null], [1, 30, ' '], [1, 30, "a\x01"]] as [$count, $days, $label]) {
            try {
                createRegistrationInvitations($this->database, $count, $days, $label);
                self::fail('Out-of-range input was accepted.');
            } catch (InvalidArgumentException) {
                self::assertSame(0, (int) $this->database->query('SELECT COUNT(*) FROM registration_invitations')->fetchColumn());
            }
        }
    }

    public function testAnInvitationIsConsumedExactlyOnceInsideTheRegistrationTransaction(): void
    {
        [$code] = createRegistrationInvitations($this->database, 1, 30, null);
        $id = findUsableRegistrationInvitation($this->database, $code);
        self::assertIsInt($id);

        rememberRegistrationInvitation($id);
        $this->database->beginTransaction();
        consumeRegistrationInvitation($this->database, 7);
        $this->database->commit();
        self::assertArrayNotHasKey(REGISTRATION_INVITATION_SESSION_KEY, $_SESSION);
        self::assertSame('7', (string) $this->database->query('SELECT used_by_user_id FROM registration_invitations')->fetchColumn());
        self::assertNull(findUsableRegistrationInvitation($this->database, $code));

        // A second registration that validated the same code earlier loses the race.
        rememberRegistrationInvitation($id);
        $this->database->beginTransaction();
        try {
            consumeRegistrationInvitation($this->database, 8);
            self::fail('A spent invitation was consumed twice.');
        } catch (IdentityRepositoryException) {
            $this->database->rollBack();
        }
        self::assertSame('7', (string) $this->database->query('SELECT used_by_user_id FROM registration_invitations')->fetchColumn());
    }

    public function testConsumptionRequiresTheTransactionAndARememberedInvitation(): void
    {
        $this->expectException(LogicException::class);
        consumeRegistrationInvitation($this->database, 1);
    }

    public function testRegistrationWithoutARememberedInvitationIsDenied(): void
    {
        $this->database->beginTransaction();
        $this->expectException(IdentityRepositoryException::class);
        consumeRegistrationInvitation($this->database, 1);
    }

    public function testRevokedAndExpiredInvitationsAreUnusable(): void
    {
        [$revoked, $expired] = createRegistrationInvitations($this->database, 2, 30, null);
        $revokedId = (int) findUsableRegistrationInvitation($this->database, $revoked);
        self::assertTrue(revokeRegistrationInvitation($this->database, $revokedId));
        self::assertFalse(revokeRegistrationInvitation($this->database, $revokedId));
        self::assertNull(findUsableRegistrationInvitation($this->database, $revoked));

        $expiredDigest = registrationInvitationDigest((string) normalizeRegistrationInvitationCode($expired));
        $this->database->prepare('UPDATE registration_invitations SET expires_at = :past WHERE code_digest = :digest')
            ->execute(['past' => gmdate('Y-m-d H:i:s', time() - 60), 'digest' => $expiredDigest]);
        self::assertNull(findUsableRegistrationInvitation($this->database, $expired));

        $states = array_column(listRegistrationInvitations($this->database), 'state');
        sort($states);
        self::assertSame(['expired', 'revoked'], $states);
    }

    public function testRegistrationModes(): void
    {
        foreach (['0' => [false, false], '1' => [true, false], 'invite' => [true, true], 'INVITE' => [false, false], '' => [false, false]] as $value => [$enabled, $invite]) {
            putenv('ATHERCAR_REGISTRATION_ENABLED=' . $value);
            self::assertSame($enabled, auth0RegistrationIsEnabled(), "mode {$value}");
            self::assertSame($invite, auth0RegistrationRequiresInvitation(), "mode {$value}");
        }
    }
}
