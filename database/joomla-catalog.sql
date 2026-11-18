CREATE TABLE IF NOT EXISTS goldensite_joomla_categories (
 source_id INT UNSIGNED PRIMARY KEY, parent_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL,
 alias VARCHAR(191) NOT NULL, description TEXT NOT NULL, position INT NOT NULL, is_visible TINYINT NOT NULL,
 INDEX siblings (parent_id,is_visible,position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_entries (
 source_id INT UNSIGNED PRIMARY KEY, category_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL,
 alias VARCHAR(191) NOT NULL, description TEXT NOT NULL, version_name VARCHAR(255) NOT NULL,
 size_label VARCHAR(40) NOT NULL, downloads INT UNSIGNED NOT NULL, created_at DATETIME NULL,
 original_file VARCHAR(255) NOT NULL, preview_file VARCHAR(255) NOT NULL, position INT NOT NULL, is_visible TINYINT NOT NULL,
 INDEX category_recent (category_id,is_visible,created_at,source_id), INDEX recent (is_visible,created_at,source_id), INDEX source_original (original_file), INDEX source_preview (preview_file)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_menus (
 source_id INT UNSIGNED PRIMARY KEY, label VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL,
 component VARCHAR(64) NOT NULL, view_name VARCHAR(64) NOT NULL, target_id INT NOT NULL, position INT NOT NULL, shop TINYINT NOT NULL,
 INDEX menu_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_articles (
 source_id INT UNSIGNED PRIMARY KEY, slug VARCHAR(255) NOT NULL, category_id INT NOT NULL, category_title VARCHAR(255) NOT NULL,
 INDEX category_list (category_id,slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_tags (
 source_id INT UNSIGNED PRIMARY KEY, title VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_entry_tags (
 entry_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL, PRIMARY KEY (entry_id,tag_id),
 INDEX by_tag (tag_id,entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
