-- Add the persisted store for public pages and their optional Google Docs sync state.
CREATE TABLE IF NOT EXISTS site_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    language_code VARCHAR(10) NOT NULL DEFAULT 'ca',
    title VARCHAR(255) NOT NULL,
    google_file_id VARCHAR(255) NULL,
    content_json LONGTEXT NULL,
    plain_text LONGTEXT NULL,
    version_hash CHAR(64) NULL,
    last_synced_at DATETIME NULL,
    last_sync_status ENUM('never', 'completed', 'failed') NOT NULL DEFAULT 'never',
    last_sync_error TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_pages_slug_language (slug, language_code),
    KEY idx_site_pages_active_slug_language (is_active, slug, language_code),
    KEY idx_site_pages_google_file_id (google_file_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
