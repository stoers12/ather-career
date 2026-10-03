-- This companion is for disposable migration verification only. Running it on a
-- populated database removes optional Evidence Hub values and is therefore never automated in production.
ALTER TABLE projects
    DROP COLUMN measurable_outcome,
    DROP COLUMN personal_role,
    DROP COLUMN problem_statement;
