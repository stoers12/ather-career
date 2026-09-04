-- Optional universal Hero proposition authored by the Portfolio owner.
ALTER TABLE personal_info
    ADD COLUMN hero_headline VARCHAR(180) NULL DEFAULT NULL AFTER professional_title;
