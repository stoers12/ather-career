-- Evidence Hub recommendation disposition current state. The application
-- supplies the tenant from AuthorizedPortfolioContext and calculates snooze expiry.
CREATE TABLE recommendation_dispositions (
    portfolio_id INT UNSIGNED NOT NULL,
    recommendation_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    rule_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    evidence_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    disposition VARCHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    snoozed_until TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (portfolio_id, recommendation_key),
    CONSTRAINT fk_recommendation_dispositions_portfolio
        FOREIGN KEY (portfolio_id) REFERENCES portfolios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_recommendation_dispositions_recommendation_key
        CHECK (recommendation_key REGEXP '^[a-f0-9]{64}$'),
    CONSTRAINT chk_recommendation_dispositions_evidence_fingerprint
        CHECK (evidence_fingerprint REGEXP '^[a-f0-9]{64}$'),
    CONSTRAINT chk_recommendation_dispositions_value
        CHECK (disposition IN ('snoozed', 'dismissed')),
    CONSTRAINT chk_recommendation_dispositions_snoozed_until
        CHECK ((disposition = 'snoozed' AND snoozed_until IS NOT NULL) OR (disposition = 'dismissed' AND snoozed_until IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
