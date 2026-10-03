<?php

declare(strict_types=1);

final class OwnerTransactionIntegrityStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $transaction = self::read('includes/transaction.php');
        $ownerActions = self::read('includes/owner_actions.php');
        $lifecycle = self::read('includes/public_lifecycle.php');
        $scopedData = self::read('includes/portfolio_scoped_data.php');
        $profileActions = self::read('includes/profile_actions.php');
        $projectActions = self::read('includes/project_actions.php');

        phase2Assert(str_contains($transaction, 'function runDatabaseTransaction(PDO $database, callable $operation): mixed'), 'Stage-2C transaction boundary helper is missing.');
        phase2Assert(str_contains($transaction, '$ownsTransaction = !$database->inTransaction()') && str_contains($transaction, '$database->beginTransaction()') && str_contains($transaction, '$database->commit()') && str_contains($transaction, '$database->rollBack()'), 'Stage-2C transaction helper does not start, commit, and roll back correctly.');
        phase2Assert(str_contains($transaction, 'rather than nested'), 'Stage-2C transaction policy must explicitly reject nested transaction behavior.');

        foreach ([
            'createAuthorizedPersonalInfo',
            'updateAuthorizedPersonalInfo',
            'createAuthorizedSkill',
            'updateAuthorizedSkill',
            'deleteAuthorizedSkill',
            'createAuthorizedProject',
            'updateAuthorizedProject',
            'deleteAuthorizedProject',
            'createAuthorizedExperience',
            'updateAuthorizedExperience',
            'deleteAuthorizedExperience',
        ] as $mutation) {
            phase2Assert(str_contains($ownerActions, "runDatabaseTransaction(\$database") && str_contains($ownerActions, $mutation), "Stage-2C owner mutation {$mutation} is not covered by the use-case transaction boundary.");
        }
        phase2Assert(str_contains($lifecycle, 'runDatabaseTransaction($database') && str_contains($lifecycle, 'AND owner_user_id = :authorized_user_id'), 'Stage-2C publication state writes must be transactional and owner-scoped.');
        phase2Assert(str_contains($scopedData, 'function authorizedProjectImageIsUnreferenced') && str_contains($scopedData, 'WHERE portfolio_id = :authorized_portfolio_id'), 'Stage-2C project media retirement lacks an owner-scoped reference check.');
        phase2Assert(str_contains($profileActions, 'deleteProfilePresentationImage($key, $portfolioId)') && str_contains($projectActions, 'deleteProjectPresentationImage($key, $portfolioId)'), 'Stage-2C failed media normalization does not compensate both original and derived files.');
        phase2Assert(!str_contains($ownerActions, '$_POST[\'portfolio_id\']') && !str_contains($ownerActions, '$_POST[\'owner_user_id\']'), 'Stage-2C owner mutations must not trust submitted tenant selectors.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
