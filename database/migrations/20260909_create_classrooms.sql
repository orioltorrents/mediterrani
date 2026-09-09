-- Normalize the existing Classroom table to the Google-prefixed column names.
ALTER TABLE classrooms
    MODIFY classroom_key VARCHAR(100) NOT NULL,
    MODIFY classroom_name VARCHAR(255) NOT NULL,
    MODIFY classroom_url VARCHAR(500) NULL,
    ADD UNIQUE KEY uq_classrooms_key_year (academic_year_id, classroom_key),
    ADD UNIQUE KEY uq_classrooms_google_id (google_classroom_id);
