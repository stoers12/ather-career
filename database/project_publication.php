<?php
declare(strict_types=1);

function verifyProjectPublicationSchema(PDO $database, bool $staging = false): void
{
    $column = requireColumnDefinition($database, 'projects', 'is_published', 'tinyint(1)', $staging ? 'YES' : 'NO');
    if (($staging ? $column['default_value'] !== null : (string) $column['default_value'] !== '0') || $column['extra'] !== '') {
        throw new RuntimeException('Incompatible project publication attributes.');
    }
    $statement = $database->query("SELECT cc.check_clause AS check_clause, tc.enforced AS enforced FROM information_schema.table_constraints tc
        JOIN information_schema.check_constraints cc ON cc.constraint_schema=tc.constraint_schema AND cc.constraint_name=tc.constraint_name
        WHERE tc.table_schema=DATABASE() AND tc.table_name='projects' AND tc.constraint_name='chk_projects_is_published'");
    $check = $statement->fetch(PDO::FETCH_ASSOC);
    $clause = is_array($check) ? preg_replace('/[\\s`]/', '', $check['check_clause']) : '';
    if (!is_array($check) || $check['enforced'] !== 'YES' || $clause !== '(is_publishedin(0,1))') {
        throw new RuntimeException('Incompatible project publication check.');
    }
}

/** Technical exposure-preserving transition, never evidence of individual consent.
 * Requires continuous exclusive maintenance, including Portfolio publication writers.
 * The migration lock does not stop application traffic. NULL means uninitialized only.
 */
function executeProjectPublicationMigration(PDO $database): void
{
    $column = fetchColumnDefinition($database, 'projects', 'is_published');
    if ($column === null) {
        $database->exec('ALTER TABLE projects ADD COLUMN is_published TINYINT(1) NULL DEFAULT NULL,
            ADD CONSTRAINT chk_projects_is_published CHECK (is_published IN (0,1))');
        $column = fetchColumnDefinition($database, 'projects', 'is_published');
    }
    if ($column['nullable'] === 'NO') {
        // Final DDL may have committed before ledger acknowledgement. Never backfill again.
        verifyProjectPublicationSchema($database);
        return;
    }
    verifyProjectPublicationSchema($database, true);
    testOnlyMigrationFailurePoint('after-project-publication-add');
    if ((int) $database->query('SELECT COUNT(*) FROM projects LEFT JOIN portfolios ON portfolios.id=projects.portfolio_id
        WHERE portfolios.id IS NULL OR portfolios.is_published IS NULL OR portfolios.is_published NOT IN (0,1)')->fetchColumn() !== 0) {
        throw new RuntimeException('Project publication transition has invalid ownership or Portfolio state.');
    }
    $database->exec('UPDATE projects JOIN portfolios ON portfolios.id=projects.portfolio_id
        SET projects.is_published=portfolios.is_published, projects.updated_at=projects.updated_at
        WHERE projects.is_published IS NULL');
    testOnlyMigrationFailurePoint('after-project-publication-backfill');
    $database->exec('ALTER TABLE projects MODIFY COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0');
    verifyProjectPublicationSchema($database);
    testOnlyMigrationFailurePoint('after-project-publication-finalize');
}
