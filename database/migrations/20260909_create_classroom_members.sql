CREATE TABLE IF NOT EXISTS classroom_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    user_id INT NOT NULL,
    google_user_id VARCHAR(100) NULL,
    google_photo_url VARCHAR(500) NULL,
    classroom_group VARCHAR(100) NULL,
    external_group_id VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_classroom_members_classroom_user (classroom_id, user_id),
    KEY idx_classroom_members_classroom_active (classroom_id, is_active),
    KEY idx_classroom_members_user (user_id),
    KEY idx_classroom_members_google_user (google_user_id),
    CONSTRAINT fk_classroom_members_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_classroom_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
