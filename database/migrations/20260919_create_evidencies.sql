CREATE TABLE IF NOT EXISTS evidencies_categoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    descripcio TEXT NULL,
    color_code VARCHAR(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidencies_tipus (
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

CREATE TABLE IF NOT EXISTS evidencies_alumnes (
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
