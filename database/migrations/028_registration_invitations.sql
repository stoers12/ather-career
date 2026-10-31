-- Registration invitations (L-01): single-use codes for the invite-only beta.
-- Only a SHA-256 digest of each code is stored; the code itself is shown once
-- by scripts/ops/registration-invitations.php. A code is consumed inside the
-- same transaction that creates the user, which sets used_at and used_by_user_id
-- together. Deleting a user keeps the spent row (used_by_user_id becomes NULL);
-- MySQL forbids a CHECK on a column that a foreign-key action updates.
CREATE TABLE registration_invitations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    label VARCHAR(120) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    used_by_user_id INT UNSIGNED NULL DEFAULT NULL,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_registration_invitations_digest (code_digest),
    CONSTRAINT fk_registration_invitations_user
        FOREIGN KEY (used_by_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_registration_invitations_digest
        CHECK (code_digest REGEXP '^[0-9a-f]{64}$'),
    CONSTRAINT chk_registration_invitations_label
        CHECK (label IS NULL OR CHAR_LENGTH(TRIM(label)) > 0),
    CONSTRAINT chk_registration_invitations_expiry
        CHECK (expires_at > created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
