-- Evidence Hub Owner technology mappings: one Owner decision that an unmapped
-- project technology label means an existing taxonomy entry. The application
-- supplies the tenant from AuthorizedPortfolioContext and stores only the
-- normalized label digest, never the label text.
CREATE TABLE technology_owner_mappings (
    portfolio_id INT UNSIGNED NOT NULL,
    label_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    canonical_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    taxonomy_version VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (portfolio_id, label_digest),
    CONSTRAINT fk_technology_owner_mappings_portfolio
        FOREIGN KEY (portfolio_id) REFERENCES portfolios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_technology_owner_mappings_label_digest
        CHECK (label_digest REGEXP '^[a-f0-9]{64}$'),
    CONSTRAINT chk_technology_owner_mappings_canonical_id
        CHECK (canonical_id REGEXP '^tech[.][a-z0-9-]+$'),
    CONSTRAINT chk_technology_owner_mappings_taxonomy_version
        CHECK (taxonomy_version REGEXP '^v[1-9][0-9]*$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
