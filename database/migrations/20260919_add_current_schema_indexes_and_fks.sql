ALTER TABLE project_teams
    ADD KEY idx_project_teams_active_order (project_academic_year_id, is_active, display_order);

ALTER TABLE classrooms
    ADD KEY idx_classrooms_year_active (academic_year_id, is_active),
    ADD KEY idx_classrooms_project_year_active (project_academic_year_id, is_active);

ALTER TABLE project_team_members
    ADD KEY idx_ptm_class (class_id),
    ADD KEY idx_ptm_role (project_role_id),
    ADD CONSTRAINT fk_ptm_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_ptm_role FOREIGN KEY (project_role_id) REFERENCES project_roles(id) ON DELETE SET NULL;
