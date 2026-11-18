CREATE TABLE IF NOT EXISTS goldensite_social_echoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(100) NOT NULL,
    body TEXT NOT NULL,
    region CHAR(2) NULL,
    zone_row SMALLINT UNSIGNED NULL,
    zone_col SMALLINT UNSIGNED NULL,
    audio_mime VARCHAR(40) NULL,
    audio_key CHAR(32) NULL,
    image_key CHAR(32) NULL,
    image_alt VARCHAR(160) NULL,
    hidden_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (hidden_at,id),
    INDEX (region,hidden_at,id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_social_waves (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    echo_id BIGINT UNSIGNED NOT NULL,
    user_id INT NULL,
    body VARCHAR(500) NOT NULL,
    hidden_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (echo_id,hidden_at,id),
    FOREIGN KEY (echo_id) REFERENCES goldensite_social_echoes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_social_resonances (
    echo_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    attentive_seconds SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (echo_id,user_id),
    FOREIGN KEY (echo_id) REFERENCES goldensite_social_echoes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_social_presence (
    user_id INT PRIMARY KEY,
    region CHAR(2) NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX (expires_at,region),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
