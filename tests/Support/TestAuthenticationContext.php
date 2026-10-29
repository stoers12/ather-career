<?php

declare(strict_types=1);

/**
 * Test-data carrier only. It is not a session, HTTP endpoint, or production
 * authentication adapter. P2J-01 will define the production domain contract.
 */
final readonly class TestAuthenticationContext
{
    public function __construct(
        public int $userId,
        public string $accountStatus,
    ) {
    }

    public static function fromFixture(string $fixture): self
    {
        $fixtures = SyntheticFixtures::all();
        if (!isset($fixtures[$fixture]) || !isset($fixtures[$fixture]['id'], $fixtures[$fixture]['account_status'])) {
            throw new InvalidArgumentException("Fixture {$fixture} cannot create an authentication context.");
        }

        return new self((int) $fixtures[$fixture]['id'], (string) $fixtures[$fixture]['account_status']);
    }
}
