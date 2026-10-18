<?php

declare(strict_types=1);

require_once __DIR__ . '/portfolio_scoped_data.php';
require_once __DIR__ . '/profile_version.php';

/** Current-session authorization is repeated under User/Portfolio locks. The
 * expected version always comes from the loaded form, never from this read.
 * @return array{id:int, profile_version:int, previous_image_path:?string}
 */
function saveOwnedProfilePatch(PDO $database, AuthorizedPortfolioContext $context, ?int $profileId, int $expectedVersion, array $patch): array
{
    if ($expectedVersion < 0 || $expectedVersion > PROFILE_MAX_VERSION) { throw new InvalidArgumentException('Invalid profile version.'); }
    $patch = authorizedPersonalInfoValues($patch);
    $user = requireAuthenticatedUser($database);
    if ($user->userId !== $context->userId) { throw new AuthorizationDeniedException('Profile User mismatch.'); }
    return runProfileWriteTransaction($database, static function () use ($database, $context, $profileId, $expectedVersion, $patch): array {
        $lock = $database->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $lock->execute([$context->userId]);
        $current = requireOwnedPortfolioContext($database);
        if ($current->userId !== $context->userId || $current->portfolioId !== $context->portfolioId) {
            throw new AuthorizationDeniedException('Profile ownership mismatch.');
        }
        $lock = $database->prepare('SELECT id FROM portfolios WHERE id = ? AND owner_user_id = ? FOR UPDATE');
        $lock->execute([$context->portfolioId, $context->userId]);
        if ($lock->fetchColumn() === false) { throw new AuthorizationDeniedException('Profile ownership missing.'); }
        $stored = loadAuthorizedPersonalInfo($database, $current);
        if ($expectedVersion === 0) {
            if ($stored !== null || $profileId !== null) { throw new ProfileVersionConflictException('Profile was created after this form was opened.'); }
            $id = createAuthorizedPersonalInfo($database, $current, $patch);
            return ['id' => $id, 'profile_version' => 1, 'previous_image_path' => null];
        }
        if ($stored === null || ($profileId !== null && $profileId !== (int) $stored['id'])) {
            throw new ProfileVersionConflictException('Profile changed after this form was opened.');
        }
        $name = array_key_exists('full_name', $patch) ? $patch['full_name'] : $stored['full_name'];
        if (!is_string($name) || trim($name) === '') { throw new InvalidArgumentException('A professional name is required.'); }
        if (!updateAuthorizedPersonalInfo($database, $current, (int) $stored['id'], $patch, $expectedVersion)) {
            throw new ProfileVersionConflictException('Profile changed after this form was opened.');
        }
        return ['id' => (int) $stored['id'], 'profile_version' => $expectedVersion + 1,
            'previous_image_path' => $stored['profile_image_path']];
    });
}
