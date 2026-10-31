-- Legal document acceptances (L-02): which Privacy Policy and Terms version
-- each user accepted, and when. Append-only history: a new version adds rows.
-- Rows are written inside the transaction that creates the user and are
-- removed with that user (P-05).
CREATE TABLE policy_acceptances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    document VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    accepted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_policy_acceptances_user_document_version (user_id, document, version),
    CONSTRAINT fk_policy_acceptances_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT chk_policy_acceptances_document
        CHECK (document IN ('privacy', 'terms')),
    CONSTRAINT chk_policy_acceptances_version
        CHECK (version REGEXP '^[0-9]{1,3}[.][0-9]{1,3}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
