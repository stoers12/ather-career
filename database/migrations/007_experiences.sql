-- Portfolio-scoped Experience records preserve owner-entered month precision.
-- Date ranges are validated both here and by the owner write path.
CREATE TABLE experiences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT UNSIGNED NOT NULL,
    experience_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    role_title VARCHAR(120) NOT NULL,
    organization VARCHAR(160) NOT NULL,
    location VARCHAR(160) NULL,
    start_month CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    end_month CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_experiences_portfolio_display (portfolio_id, is_current, start_month, id),
    CONSTRAINT fk_experiences_portfolio
        FOREIGN KEY (portfolio_id) REFERENCES portfolios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_experiences_type
        CHECK (experience_type IN ('employment', 'training', 'internship', 'volunteer', 'leadership')),
    CONSTRAINT chk_experiences_start_month
        CHECK (start_month REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'),
    CONSTRAINT chk_experiences_end_month
        CHECK (end_month IS NULL OR end_month REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'),
    CONSTRAINT chk_experiences_current_value
        CHECK (is_current IN (0, 1)),
    CONSTRAINT chk_experiences_current_end
        CHECK ((is_current = 1 AND end_month IS NULL) OR (is_current = 0 AND end_month IS NOT NULL)),
    CONSTRAINT chk_experiences_chronology
        CHECK (end_month IS NULL OR end_month >= start_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
