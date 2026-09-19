CREATE TABLE IF NOT EXISTS evidencies_categoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    descripcio TEXT NULL,
    color_code VARCHAR(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evidencia_categoria_id INT NOT NULL,
    tipus_evidencia VARCHAR(50) NOT NULL,
    titol VARCHAR(255) NOT NULL,
    descripcio TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_evidencies_categoria (evidencia_categoria_id),
    CONSTRAINT fk_evidencies_categoria FOREIGN KEY (evidencia_categoria_id)
        REFERENCES evidencies_categoria(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
