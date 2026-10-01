-- Add an optional editable code to achievement criteria.
ALTER TABLE criteris_assoliment
    ADD COLUMN codi VARCHAR(50) NULL AFTER objectiu_id;
