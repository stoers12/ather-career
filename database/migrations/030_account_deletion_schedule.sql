-- Account deletion schedule (L-03, P-05). A request sets both timestamps: the
-- account leaves public view at once, and an owner who signs in before
-- deletion_due_at can cancel, which clears both. After that date
-- scripts/ops/purge-deleted-accounts.php deletes the Auth0 user, the media and
-- every row of the account (see 031_account_deletions.sql).
ALTER TABLE users
    ADD COLUMN deletion_requested_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN deletion_due_at TIMESTAMP NULL DEFAULT NULL,
    ADD KEY idx_users_deletion_due (deletion_due_at),
    ADD CONSTRAINT chk_users_deletion_schedule CHECK (
        (deletion_requested_at IS NULL AND deletion_due_at IS NULL)
        OR (deletion_requested_at IS NOT NULL AND deletion_due_at > deletion_requested_at)
    );
