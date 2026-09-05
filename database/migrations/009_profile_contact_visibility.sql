ALTER TABLE personal_info
    ADD COLUMN public_contact_visible TINYINT(1) NOT NULL DEFAULT 0,
    ADD CONSTRAINT chk_profile_contact_visible CHECK (public_contact_visible IN (0, 1));
