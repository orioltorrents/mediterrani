ALTER TABLE classrooms
    CHANGE google_classroom_key classroom_key VARCHAR(100) NOT NULL,
    CHANGE google_classroom_name classroom_name VARCHAR(255) NOT NULL,
    CHANGE google_classroom_url classroom_url VARCHAR(500) NULL;
