CREATE TABLE IF NOT EXISTS goldensite_forum_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_boards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    INDEX (category_id, id),
    FOREIGN KEY (category_id) REFERENCES goldensite_forum_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_topics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    author_id INT NULL,
    title VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    kind VARCHAR(16) NOT NULL,
    pinned TINYINT NOT NULL DEFAULT 0,
    closed TINYINT NOT NULL DEFAULT 0,
    solution_id BIGINT UNSIGNED NULL,
    merged_into BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (board_id, deleted_at, pinned, active_at, id),
    INDEX (deleted_at, id),
    FOREIGN KEY (board_id) REFERENCES goldensite_forum_boards(id),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id BIGINT UNSIGNED NOT NULL,
    author_id INT NULL,
    body MEDIUMTEXT NOT NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (topic_id, deleted_at, id),
    INDEX (author_id, deleted_at, id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_topic_tags (
    topic_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (topic_id, tag_id),
    INDEX (tag_id, topic_id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (tag_id) REFERENCES goldensite_forum_tags(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_poll_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(120) NOT NULL,
    UNIQUE (topic_id, id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_votes (
    topic_id BIGINT UNSIGNED NOT NULL,
    option_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (topic_id, user_id),
    INDEX (topic_id, option_id),
    FOREIGN KEY (topic_id, option_id) REFERENCES goldensite_forum_poll_options(topic_id, id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_follows (
    topic_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    email_enabled TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (topic_id, user_id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    topic_id BIGINT UNSIGNED NOT NULL,
    reply_id BIGINT UNSIGNED NULL,
    reason VARCHAR(12) NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, read_at, id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (reply_id) REFERENCES goldensite_forum_replies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_reactions (
    reply_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(12) NOT NULL,
    PRIMARY KEY (reply_id, user_id),
    FOREIGN KEY (reply_id) REFERENCES goldensite_forum_replies(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_preferences (
    user_id INT PRIMARY KEY,
    direct_email TINYINT NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_attachments (
    id CHAR(32) PRIMARY KEY,
    topic_id BIGINT UNSIGNED NOT NULL,
    reply_id BIGINT UNSIGNED NULL,
    mime VARCHAR(32) NOT NULL,
    label VARCHAR(120) NOT NULL,
    INDEX (topic_id, reply_id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (reply_id) REFERENCES goldensite_forum_replies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
