-- Completed account deletions (L-03, P-05): only the internal id and the two
-- dates, never personal data. It has no foreign key because the users row is
-- gone; InnoDB never reuses an AUTO_INCREMENT id, so the id stays unambiguous.
CREATE TABLE account_deletions (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    requested_at TIMESTAMP NOT NULL,
    deleted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
