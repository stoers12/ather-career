<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';

/** Server-owned immutable document metadata, NEVER hydrated from request data.
 * Versions must identify immutable content; digest is lowercase SHA-256 of the
 * exact presented document bytes, language identifies that document translation.
 */
final readonly class OnboardingDocumentDescriptor
{
    public function __construct(
        public string $identifier,
        public string $version,
        public string $digest,
        public string $language,
    ) {
        foreach ([$identifier, $version] as $value) {
            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,63}\z/D', $value) !== 1) {
                throw new InvalidArgumentException('Invalid server document identifier or version.');
            }
        }
        if (preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1
            || preg_match('/\A[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*\z/D', $language) !== 1
            || strlen($language) > 35) {
            throw new InvalidArgumentException('Invalid server document digest or language.');
        }
    }
}

/** Trusted server resolution of the exact three documents presented together.
 * The age descriptor versions the 18+ self-attestation statement (not age proof).
 * Internal construction is a trust boundary, not approval of legal content.
 */
final readonly class OnboardingAttestationBundle
{
    public function __construct(
        public OnboardingDocumentDescriptor $age,
        public OnboardingDocumentDescriptor $terms,
        public OnboardingDocumentDescriptor $privacy,
    ) {}

    /** @return array<string, string> */
    public function parameters(): array
    {
        $parameters = [];
        foreach (['age', 'terms', 'privacy'] as $kind) {
            foreach (['identifier', 'version', 'digest', 'language'] as $field) {
                $parameters[$kind . '_' . $field] = $this->$kind->$field;
            }
        }
        return $parameters;
    }
}

/** No approved policies exist. No environment switch or synthetic fallback. */
function currentOnboardingAttestationBundle(): ?OnboardingAttestationBundle
{
    return null;
}

/**
 * Explicit internal write. Caller must resolve/present trusted server documents;
 * only the three strict boolean affirmations may originate in a request. Future
 * routes must supply CSRF/input controls and bind the presented bundle themselves.
 * No Portfolio, account-state, email-readiness or onboarding-completion authority.
 * A row records all three positive actions together: 18+ self-attestation,
 * Terms acceptance, and Privacy Notice acknowledgement (not blanket consent).
 *
 * @return array<string, mixed> Exact durable evidence, including UTC recorded_at.
 */
function recordOnboardingAttestations(PDO $database, AuthenticatedUserContext $context, ?OnboardingAttestationBundle $bundle, array $affirmations): array
{
    $keys = array_keys($affirmations);
    sort($keys);
    if ($keys !== ['age_18_or_older', 'privacy_notice_acknowledged', 'terms_accepted']
        || $affirmations['age_18_or_older'] !== true
        || $affirmations['terms_accepted'] !== true
        || $affirmations['privacy_notice_acknowledged'] !== true) {
        throw new InvalidArgumentException('Three explicit positive affirmations are required.');
    }
    if ($bundle === null) {
        throw new LogicException('Onboarding policies are unavailable.');
    }
    return onboardingAttestationOperation($database, $context, $bundle, true);
}

/** Returns only evidence for the exact requested trusted bundle, never a latest
 * row or a carried-forward acceptance. NULL means no evidence or unavailable.
 * @return array<string, mixed>|null
 */
function readOnboardingAttestations(PDO $database, AuthenticatedUserContext $context, ?OnboardingAttestationBundle $bundle): ?array
{
    return onboardingAttestationOperation($database, $context, $bundle, false);
}

/** @internal Transaction-aware lookup only. Caller already holds the current
 * authorized User lock; exact binary descriptors, never "latest" or client data.
 * Public attestation operations retain their own authorization/transaction below.
 */
function findLockedOnboardingAttestation(PDO $database, AuthenticatedUserContext $context, OnboardingAttestationBundle $bundle): ?array
{
    if (!$database->inTransaction()) { throw new LogicException('Exact evidence lookup requires the authorized User lock transaction.'); }
    $parameters=['user_id'=>$context->userId]+$bundle->parameters();
    $where=implode(' AND ',array_map(static fn(string $key): string=>"$key = :$key",array_keys($parameters)));
    $select=$database->prepare('SELECT * FROM onboarding_attestations WHERE '.$where.' FOR SHARE');
    $select->execute($parameters);
    return $select->fetch(PDO::FETCH_ASSOC) ?: null;
}

function onboardingAttestationOperation(PDO $database, AuthenticatedUserContext $context, ?OnboardingAttestationBundle $bundle, bool $write): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new AuthorizationDeniedException('Attestations require a current active session.');
    }
    if ($database->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION || $database->inTransaction()) {
        throw new LogicException('Attestation operation requires exception mode and an owned transaction.');
    }
    $user = requireAuthenticatedUser($database);
    if ($user->userId !== $context->userId) {
        throw new AuthorizationDeniedException('Attestation User context mismatch.');
    }
    $database->beginTransaction();
    try {
        $lock = $database->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $lock->execute([$user->userId]);
        $current = requireAuthenticatedUser($database);
        if ($current->userId !== $user->userId) {
            throw new AuthorizationDeniedException('Attestation User context mismatch.');
        }
        $record = null;
        if ($bundle !== null) {
            $parameters = ['user_id' => $current->userId] + $bundle->parameters();
            $record = findLockedOnboardingAttestation($database, $current, $bundle);
            if ($write && $record === null) {
                $columns = implode(', ', array_keys($parameters));
                $values = implode(', ', array_map(static fn (string $key): string => ':' . $key, array_keys($parameters)));
                $insert = $database->prepare("INSERT INTO onboarding_attestations ($columns, recorded_at) VALUES ($values, UTC_TIMESTAMP(6))");
                $insert->execute($parameters);
                $record = findLockedOnboardingAttestation($database, $current, $bundle);
                if (!is_array($record)) {
                    throw new RuntimeException('Attestation evidence could not be read.');
                }
            }
        }
        $database->commit();
        return $record;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}
