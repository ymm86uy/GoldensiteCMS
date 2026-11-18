CREATE TABLE IF NOT EXISTS goldensite_catalog_media (
 entry_id INT UNSIGNED PRIMARY KEY, original_id INT UNSIGNED NULL, preview_id INT UNSIGNED NULL,
 view_mode ENUM('public','member','off') NOT NULL DEFAULT 'public',
 preview_mode ENUM('public','member','off') NOT NULL DEFAULT 'off',
 download_mode ENUM('public','member','off') NOT NULL DEFAULT 'off', INDEX original_file (original_id), INDEX preview_file (preview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_catalog_submissions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id INT UNSIGNED NOT NULL,
 author_id INT UNSIGNED NOT NULL, file_id INT UNSIGNED NOT NULL, title VARCHAR(200) NOT NULL,
 description TEXT NOT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX queue (status,id), INDEX author_queue (author_id,status), INDEX submission_file (file_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_community_profiles (
 user_id INT UNSIGNED PRIMARY KEY, display_name VARCHAR(80) NOT NULL,
 visibility ENUM('public','member','off') NOT NULL DEFAULT 'off',
 history_mode ENUM('public','member','off') NOT NULL DEFAULT 'off',
 fields_json TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
