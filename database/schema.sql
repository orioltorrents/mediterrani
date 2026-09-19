-- Mediterrani: esquema net sense dades inicials.
-- La base de dades objectiu es configura fora d'aquest fitxer.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS evidencies_alumnes, student_indicador_assoliment, indicadors_assoliment, project_academic_year_objectius,
    evidencies_tipus, evidencies_categoria,
    project_team_member_roles, project_team_members, project_teams, project_sections, project_class_assignments,
    project_academic_years, project_translations, project_roles, classroom_members, classrooms, class_member_history,
    class_members, class_teachers, classes, academic_years, site_visits, login_attempts, user_activation_tokens, user_web_roles,
    web_roles, languages, users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    surname VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    google_id VARCHAR(100) NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    password_changed_at TIMESTAMP NULL,
    avatar_url VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    gender VARCHAR(50) NULL,
    article VARCHAR(10) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_activation_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_activation_token_hash (token_hash),
    KEY idx_user_activation_tokens_user (user_id),
    KEY idx_user_activation_tokens_expiration (expires_at, used_at),
    CONSTRAINT fk_user_activation_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE web_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_web_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_role (user_id, role_id),
    KEY idx_uwr_user (user_id), KEY idx_uwr_role (role_id),
    CONSTRAINT fk_uwr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_uwr_role FOREIGN KEY (role_id) REFERENCES web_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE,
    start_year YEAR NOT NULL,
    end_year YEAR NOT NULL,
    is_current TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT NOT NULL,
    class_name VARCHAR(100) NOT NULL,
    class_code VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_year_code (academic_year_id, class_code),
    KEY idx_classes_year (academic_year_id),
    CONSTRAINT fk_classes_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_member (user_id),
    KEY idx_class_members_class (class_id),
    CONSTRAINT fk_class_members_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_teacher (class_id, user_id),
    KEY idx_class_teachers_user (user_id),
    CONSTRAINT fk_class_teachers_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_teachers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_member_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_class_id INT NULL,
    to_class_id INT NULL,
    change_source VARCHAR(50) NOT NULL DEFAULT 'manual',
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_history_user (user_id), KEY idx_history_from_class (from_class_id), KEY idx_history_to_class (to_class_id),
    CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_from_class FOREIGN KEY (from_class_id) REFERENCES classes(id) ON DELETE SET NULL,
    CONSTRAINT fk_history_to_class FOREIGN KEY (to_class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    display_order INT NOT NULL,
    is_active TINYINT(1) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_academic_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_year (project_id, academic_year_id),
    KEY idx_pay_year (academic_year_id),
    CONSTRAINT fk_pay_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    language_id INT NOT NULL,
    title VARCHAR(255) NULL,
    description TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_project_language (project_id, language_id),
    CONSTRAINT fk_trans_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_trans_language FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_class_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_academic_year_id INT NOT NULL,
    class_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pendent',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_class (project_academic_year_id, class_id),
    KEY idx_pca_class (class_id),
    KEY idx_pca_status (status),
    CONSTRAINT fk_pca_project_year FOREIGN KEY (project_academic_year_id) REFERENCES project_academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_pca_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_academic_year_id INT NOT NULL,
    team_code VARCHAR(50) NOT NULL,
    team_name VARCHAR(150) NULL,
    class_id INT NULL,
    class_group VARCHAR(100) NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_team_code (project_academic_year_id, team_code),
    UNIQUE KEY uq_project_team_name_class (project_academic_year_id, class_id, team_name),
    KEY idx_project_teams_edition (project_academic_year_id),
    KEY idx_project_teams_active_order (project_academic_year_id, is_active, display_order),
    KEY idx_project_teams_class (class_id),
    CONSTRAINT fk_project_teams_edition FOREIGN KEY (project_academic_year_id) REFERENCES project_academic_years(id) ON DELETE CASCADE
    ,CONSTRAINT fk_project_teams_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_team_id INT NOT NULL,
    user_id INT NOT NULL,
    class_id INT NULL,
    project_role_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_user (project_team_id, user_id),
    KEY idx_ptm_team (project_team_id), KEY idx_ptm_user (user_id), KEY idx_ptm_class (class_id), KEY idx_ptm_role (project_role_id),
    CONSTRAINT fk_ptm_team FOREIGN KEY (project_team_id) REFERENCES project_teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_ptm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ptm_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    CONSTRAINT fk_ptm_role FOREIGN KEY (project_role_id) REFERENCES project_roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_team_member_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_team_member_id INT NOT NULL,
    project_role_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_role (project_team_member_id, project_role_id),
    CONSTRAINT fk_ptmr_member FOREIGN KEY (project_team_member_id) REFERENCES project_team_members(id) ON DELETE CASCADE,
    CONSTRAINT fk_ptmr_role FOREIGN KEY (project_role_id) REFERENCES project_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    section_key VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    section_type VARCHAR(50) NOT NULL DEFAULT 'custom',
    display_order INT NOT NULL DEFAULT 0,
    visibility_type VARCHAR(50) NOT NULL DEFAULT 'public',
    role_id INT NULL,
    class_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    config_json TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_section (project_id, section_key),
    KEY idx_sections_project (project_id), KEY idx_sections_order (display_order),
    CONSTRAINT fk_sections_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evidencies_categoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    descripcio TEXT NULL,
    color_code VARCHAR(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evidencies_tipus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evidencia_categoria_id INT NOT NULL,
    tipus_evidencia VARCHAR(50) NOT NULL,
    titol VARCHAR(255) NOT NULL,
    descripcio TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_evidencies_tipus_categoria (evidencia_categoria_id),
    CONSTRAINT fk_evidencies_tipus_categoria FOREIGN KEY (evidencia_categoria_id)
        REFERENCES evidencies_categoria(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE objectius_aprenentatge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NULL,
    codi VARCHAR(50) NOT NULL,
    titol TEXT NOT NULL,
    creat_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_objectius_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_academic_year_objectius (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_academic_year_id INT NOT NULL,
    objectiu_id INT NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_year_objectiu (project_academic_year_id, objectiu_id),
    CONSTRAINT fk_payo_project_year FOREIGN KEY (project_academic_year_id) REFERENCES project_academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_payo_objectiu FOREIGN KEY (objectiu_id) REFERENCES objectius_aprenentatge(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE indicadors_assoliment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    objectiu_id INT NOT NULL,
    color_semafor VARCHAR(20) NOT NULL,
    descriptor TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_objectiu_color (objectiu_id, color_semafor),
    CONSTRAINT fk_indicadors_objectiu FOREIGN KEY (objectiu_id) REFERENCES objectius_aprenentatge(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_indicador_assoliment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_academic_year_id INT NOT NULL,
    objectiu_id INT NOT NULL,
    color_semafor VARCHAR(20) NOT NULL,
    evaluated_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_edition_objectiu (user_id, project_academic_year_id, objectiu_id),
    CONSTRAINT fk_sia_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sia_project_year FOREIGN KEY (project_academic_year_id) REFERENCES project_academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_sia_objectiu FOREIGN KEY (objectiu_id) REFERENCES objectius_aprenentatge(id) ON DELETE CASCADE,
    CONSTRAINT fk_sia_evaluator FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evidencies_alumnes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_academic_year_id INT NOT NULL,
    objectiu_id INT NOT NULL,
    evidencia_categoria_id INT NULL,
    evidencia_tipus_id INT NULL,
    observacio TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ea_user (user_id),
    KEY idx_ea_project_year (project_academic_year_id),
    KEY idx_ea_objectiu (objectiu_id),
    KEY idx_ea_categoria (evidencia_categoria_id),
    KEY idx_ea_tipus (evidencia_tipus_id),
    CONSTRAINT fk_ea_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ea_project_year FOREIGN KEY (project_academic_year_id) REFERENCES project_academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_ea_objectiu FOREIGN KEY (objectiu_id) REFERENCES objectius_aprenentatge(id) ON DELETE CASCADE,
    CONSTRAINT fk_ea_categoria FOREIGN KEY (evidencia_categoria_id) REFERENCES evidencies_categoria(id) ON DELETE SET NULL,
    CONSTRAINT fk_ea_tipus FOREIGN KEY (evidencia_tipus_id) REFERENCES evidencies_tipus(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classrooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT NOT NULL,
    project_academic_year_id INT NOT NULL,
    classroom_key VARCHAR(100) NOT NULL,
    classroom_name VARCHAR(255) NOT NULL,
    classroom_url VARCHAR(500) NULL,
    google_classroom_id VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_classrooms_key_year (academic_year_id, classroom_key),
    UNIQUE KEY uq_classrooms_google_id (google_classroom_id),
    KEY idx_classrooms_year (academic_year_id),
    KEY idx_classrooms_project_year (project_academic_year_id),
    KEY idx_classrooms_year_active (academic_year_id, is_active),
    KEY idx_classrooms_project_year_active (project_academic_year_id, is_active),
    CONSTRAINT fk_classrooms_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_classrooms_project_year FOREIGN KEY (project_academic_year_id) REFERENCES project_academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classroom_members (
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

CREATE TABLE site_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NULL,
    user_id INT NULL,
    visited_at DATETIME NOT NULL,
    path VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    country_code VARCHAR(10) NULL,
    region VARCHAR(100) NULL,
    device_type VARCHAR(50) NULL,
    os_family VARCHAR(50) NULL,
    browser VARCHAR(50) NULL,
    user_agent TEXT NULL,
    KEY idx_visits_user (user_id), KEY idx_visits_date (visited_at), KEY idx_visits_path (path),
    CONSTRAINT fk_visits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(255) NOT NULL,
    success TINYINT NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL,
    KEY idx_login_ip_time (ip_address, attempted_at), KEY idx_login_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
