-- Evidence Hub v1 optional project evidence. Existing project descriptions are
-- intentionally not copied or interpreted as these independently authored fields.
ALTER TABLE projects
    ADD COLUMN problem_statement TEXT NULL DEFAULT NULL AFTER description,
    ADD COLUMN personal_role TEXT NULL DEFAULT NULL AFTER problem_statement,
    ADD COLUMN measurable_outcome TEXT NULL DEFAULT NULL AFTER personal_role;
