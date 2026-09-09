-- Add the real class relation to project teams.
-- Review unmatched class_group values before dropping the legacy column later.

ALTER TABLE project_teams
    ADD COLUMN class_id INT NULL AFTER team_name,
    ADD KEY idx_project_teams_class (class_id),
    ADD CONSTRAINT fk_project_teams_class
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    ADD UNIQUE KEY uq_project_team_name_class (project_academic_year_id, class_id, team_name);

UPDATE project_teams pt
INNER JOIN classes c ON c.class_code = pt.class_group
SET pt.class_id = c.id
WHERE pt.class_group IS NOT NULL
  AND TRIM(pt.class_group) <> '';

-- Check this before removing the legacy field:
-- SELECT id, team_code, class_group FROM project_teams WHERE class_id IS NULL AND class_group IS NOT NULL;
