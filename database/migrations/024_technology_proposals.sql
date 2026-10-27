-- Evidence Hub technology proposals: an Owner's structured request to add a
-- technology the taxonomy lacks. Nothing accepts proposals yet; the queue is
-- the interface a future curator works from. At most one proposal per label
-- is pending in a Portfolio; withdrawn and decided proposals are history.
CREATE TABLE technology_proposals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    portfolio_id INT UNSIGNED NOT NULL,
    label_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    proposed_label VARCHAR(60) NOT NULL,
    category VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    note VARCHAR(280) NULL DEFAULT NULL,
    state VARCHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    pending_label_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin
        GENERATED ALWAYS AS (CASE WHEN state = 'pending' THEN label_digest ELSE NULL END) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    state_changed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_technology_proposals_pending (portfolio_id, pending_label_digest),
    KEY idx_technology_proposals_portfolio (portfolio_id, created_at),
    CONSTRAINT fk_technology_proposals_portfolio
        FOREIGN KEY (portfolio_id) REFERENCES portfolios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_technology_proposals_label_digest
        CHECK (label_digest REGEXP '^[a-f0-9]{64}$'),
    CONSTRAINT chk_technology_proposals_category
        CHECK (category IN ('database', 'framework', 'language', 'library', 'platform', 'runtime', 'stylesheet', 'technique', 'tool')),
    CONSTRAINT chk_technology_proposals_state
        CHECK (state IN ('pending', 'withdrawn', 'accepted', 'rejected')),
    CONSTRAINT chk_technology_proposals_state_changed_at
        CHECK ((state = 'pending' AND state_changed_at IS NULL) OR (state <> 'pending' AND state_changed_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
