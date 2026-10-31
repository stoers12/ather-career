<?php

declare(strict_types=1);

require_once __DIR__ . '/identity_repository.php';

// Privacy Policy and Terms of Use versions and registration consent (L-02).
// Raise a version whenever the published text changes what users agree to.

/** @var array{privacy: string, terms: string} */
const LEGAL_DOCUMENT_VERSIONS = ['privacy' => '1.0', 'terms' => '1.0'];

// The owner enables required consent together with publication on launch day.
const LEGAL_CONSENT_REQUIRED = false;

// Launch changes publish the pages, effective date and required consent together.
const LEGAL_PAGES_PUBLISHED = false;
const LEGAL_DOCUMENT_EFFECTIVE_DATE = null;

function legalPagesPublished(): bool
{
    return LEGAL_PAGES_PUBLISHED;
}

const LEGAL_CONSENT_SESSION_KEY = 'ather_registration_legal_consent';

/** The configured privacy contact, or null until the owner sets PRIVACY_CONTACT_EMAIL. */
function legalPrivacyContactEmail(): ?string
{
    $email = getenv('PRIVACY_CONTACT_EMAIL');
    if (!is_string($email) || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return null;
    }

    return $email;
}

/** Remember, in the fresh registration session, the versions shown when the user ticked consent. */
function rememberRegistrationLegalConsent(): void
{
    $_SESSION[LEGAL_CONSENT_SESSION_KEY] = LEGAL_DOCUMENT_VERSIONS;
}

/**
 * Record the remembered consent for a new user. Runs inside the registration
 * transaction, so a user is never created without the required consent rows.
 */
function recordRegistrationLegalConsent(PDO $database, int $userId): void
{
    if (!$database->inTransaction()) {
        throw new LogicException('Consent recording requires the registration transaction.');
    }
    $versions = $_SESSION[LEGAL_CONSENT_SESSION_KEY] ?? null;
    if ($versions !== LEGAL_DOCUMENT_VERSIONS) {
        if (LEGAL_CONSENT_REQUIRED) {
            throw new IdentityRepositoryException('Registration consent is required.');
        }
        unset($_SESSION[LEGAL_CONSENT_SESSION_KEY]);

        return;
    }
    $insert = $database->prepare('INSERT INTO policy_acceptances (user_id, document, version) VALUES (:user_id, :document, :version)');
    foreach ($versions as $document => $version) {
        $insert->execute(['user_id' => $userId, 'document' => $document, 'version' => $version]);
    }
    unset($_SESSION[LEGAL_CONSENT_SESSION_KEY]);
}
