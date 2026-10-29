<?php

declare(strict_types=1);

/**
 * SQLite equivalents of migrations 023 and 024 for in-memory Owner tests. A
 * partial unique index stands in for MySQL's generated pending-label column;
 * the MySQL definitions themselves are proven by the disposable MySQL gates.
 */
function phase2EvidenceHubTechnologyReviewSqliteSchema(): string
{
    return "CREATE TABLE technology_owner_mappings (
            portfolio_id INTEGER NOT NULL,
            label_digest TEXT NOT NULL CHECK (length(label_digest) = 64),
            canonical_id TEXT NOT NULL,
            taxonomy_version TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (portfolio_id, label_digest)
        );
        CREATE TABLE technology_proposals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            portfolio_id INTEGER NOT NULL,
            label_digest TEXT NOT NULL CHECK (length(label_digest) = 64),
            proposed_label TEXT NOT NULL,
            category TEXT NOT NULL,
            note TEXT NULL,
            state TEXT NOT NULL CHECK (state IN ('pending', 'withdrawn', 'accepted', 'rejected')),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            state_changed_at TEXT NULL
        );
        CREATE UNIQUE INDEX uq_technology_proposals_pending ON technology_proposals (portfolio_id, label_digest) WHERE state = 'pending';";
}
