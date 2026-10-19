<?php

declare(strict_types=1);

require_once __DIR__ . '/onboarding_attestations.php';

const ONBOARDING_ATTESTATION_PRESENTATION_KEY = 'onboarding_attestation_presentation';
const ONBOARDING_ATTESTATION_DOCUMENT_MAX_BYTES = 65536;

/** Fixed server-owned content contract: exactly age/terms/privacy, each with
 * language and exact UTF-8 plain-text bytes. No approved documents exist yet.
 * Never select content from request data, environment flags or remote downloads.
 * Future approved immutable content must be supplied in a separately reviewed change.
 * @return array<string, array{language:string, content:string}>|null
 */
function currentOnboardingAttestationDocuments(): ?array
{
    return null;
}

/** Verify ALL bytes and translations before presenting or recording any bundle.
 * A descriptor is not document content or evidence that the content was approved.
 * @return array{bundle:OnboardingAttestationBundle, documents:array}|null
 */
function resolveOnboardingAttestationPresentation(): ?array
{
    try {
        $bundle = currentOnboardingAttestationBundle();
        $documents = currentOnboardingAttestationDocuments();
    } catch (InvalidArgumentException) {
        return null;
    }
    if ($bundle === null || $documents === null) { return null; }
    $keys = array_keys($documents); sort($keys, SORT_STRING);
    if ($keys !== ['age', 'privacy', 'terms']) { return null; }
    $presented = [];
    foreach (['age', 'terms', 'privacy'] as $kind) {
        $document = $documents[$kind];
        if (!is_array($document)) { return null; }
        $keys = array_keys($document); sort($keys, SORT_STRING);
        if ($keys !== ['content', 'language'] || !is_string($document['content'])
            || $document['content'] === '' || strlen($document['content']) > ONBOARDING_ATTESTATION_DOCUMENT_MAX_BYTES
            || preg_match('//u', $document['content']) !== 1
            || $document['language'] !== $bundle->$kind->language
            || !hash_equals($bundle->$kind->digest, hash('sha256', $document['content']))) { return null; }
        $descriptor = $bundle->$kind;
        $presented[$kind] = ['identifier' => $descriptor->identifier, 'version' => $descriptor->version,
            'digest' => $descriptor->digest, 'language' => $descriptor->language,
            'content' => $document['content']];
    }
    return ['bundle' => $bundle, 'documents' => $presented];
}

/** One bounded record, no bodies or session secret. Activity refreshes do not
 * invalidate it; identity/authz/authentication or session-ID changes do.
 */
function onboardingAttestationPresentationBinding(AuthenticatedUserContext $user, OnboardingAttestationBundle $bundle): array
{
    $session = currentInternalUserSession();
    if (session_status() !== PHP_SESSION_ACTIVE || $session === null || $session['internal_user_id'] !== $user->userId) {
        throw new AuthorizationDeniedException('Current attestation session required.');
    }
    return ['user_id' => $user->userId, 'authz_version' => $session['authz_version'],
        'authenticated_at' => $session['authenticated_at'], 'session_digest' => hash('sha256', session_id()),
        'documents' => $bundle->parameters()];
}

/** Only exact-current status/time is projected, never an internal User selector. */
function onboardingAttestationEvidenceStatus(?array $record): array
{
    return ['recorded' => $record !== null, 'recorded_at' => $record['recorded_at'] ?? null];
}
