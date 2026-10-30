-- Evidence sources (B-06): links an Owner adds to support a Project's evidence.
-- Private to the Owner workspace; never part of any public read. The
-- application supplies the tenant from AuthorizedPortfolioContext, copies
-- portfolio_id from the authorized Project row and keeps at most 10 per Project.
CREATE TABLE project_evidence_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    url VARCHAR(500) NOT NULL,
    note VARCHAR(300) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_project_evidence_sources_project (project_id, id),
    KEY idx_project_evidence_sources_portfolio (portfolio_id),
    CONSTRAINT fk_project_evidence_sources_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_project_evidence_sources_portfolio
        FOREIGN KEY (portfolio_id) REFERENCES portfolios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_project_evidence_sources_title
        CHECK (CHAR_LENGTH(TRIM(title)) > 0),
    CONSTRAINT chk_project_evidence_sources_url
        CHECK (url REGEXP '^https?://[^[:space:]]+$'),
    CONSTRAINT chk_project_evidence_sources_note
        CHECK (note IS NULL OR CHAR_LENGTH(TRIM(note)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
