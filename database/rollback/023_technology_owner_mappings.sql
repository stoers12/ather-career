-- This companion is for disposable migration verification only. Running it on a
-- populated database removes Owner technology mappings and is never automated in production.
DROP TABLE technology_owner_mappings;
