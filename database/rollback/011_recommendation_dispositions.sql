-- This companion is for disposable migration verification only. Running it on a
-- populated database removes recommendation disposition state and is never automated in production.
DROP TABLE recommendation_dispositions;
